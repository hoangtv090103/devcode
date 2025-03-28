<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Trang nộp bài cho DevCode
 *
 * @package     mod_devcode
 * @copyright   2024 Your Name <your@email.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once(dirname(__FILE__) . '/lib.php');
require_once($CFG->dirroot . '/mod/devcode/classes/form/submission_form.php');

// Required imports for context_module 
require_once($CFG->libdir . '/accesslib.php');

use \core\output\html_writer;

$id = required_param('id', PARAM_INT); // Course Module ID

// Lấy thông tin course module, course, và devcode
$cm = get_coursemodule_from_id('devcode', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$devcode = $DB->get_record('devcode', array('id' => $cm->instance), '*', MUST_EXIST);

$activityheader = ['description' => ''];

// Kiểm tra đăng nhập và quyền truy cập
require_login($course, true, $cm);
$context = \context_module::instance($cm->id);
require_capability('mod/devcode:submit', $context);

// Thiết lập trang
$PAGE->set_url('/mod/devcode/submit.php', array('id' => $cm->id));
$PAGE->set_title(format_string($devcode->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Thiết lập activity record và tắt hiển thị mô tả
$PAGE->set_activity_record($devcode);
$PAGE->activityheader->set_attrs($activityheader);

// Thêm CSS cho code editor
$PAGE->requires->css('/mod/devcode/styles.css');

// Load JavaScript modules - bỏ comment dòng này nếu cần dùng lại sau khi đã build
// $PAGE->requires->js_call_amd('mod_devcode/code_editor', 'init');
// $PAGE->requires->js_call_amd('mod_devcode/tabs', 'init');

// Thêm JavaScript inline tạm thời thay thế
$PAGE->requires->js_init_code("
    require(['jquery'], function($) {
        // Code Editor functionality
        $('.code-editor').on('keydown', function(e) {
            if (e.keyCode === 9) { // Tab key
                e.preventDefault();
                
                // Thêm khoảng cách tab (4 dấu cách)
                var start = this.selectionStart;
                var end = this.selectionEnd;
                
                // Thay thế vị trí con trỏ bằng tab
                this.value = this.value.substring(0, start) + '    ' + this.value.substring(end);
                
                // Đặt lại vị trí con trỏ
                this.selectionStart = this.selectionEnd = start + 4;
            }
        });
        
        // Tabs functionality
        $('.tab-btn').on('click', function() {
            var targetTab = $(this).data('tab');
            
            // Update active tab button
            $('.tab-btn').removeClass('active');
            $(this).addClass('active');
            
            // Hide all tab panes and show the target one
            $('.tab-pane').removeClass('active');
            $('#' + targetTab).addClass('active');
            
            // Add/remove classes for conditional styling
            if (targetTab === 'code-tab') {
                $('.responsive-layout').addClass('code-tab-active').removeClass('file-tab-active');
            } else {
                $('.responsive-layout').addClass('file-tab-active').removeClass('code-tab-active');
            }
        });
    });
");

// Kiểm tra nếu còn thời gian nộp bài
if (!empty($devcode->duedate) && $devcode->duedate < time()) {
    $renderer = $PAGE->get_renderer('mod_devcode');
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('assignmentclosed', 'devcode'), 'error');
    echo $OUTPUT->continue_button(new \moodle_url('/mod/devcode/view.php', array('id' => $cm->id)));
    echo $OUTPUT->footer();
    exit;
}

// Lấy bài nộp hiện tại của người dùng (nếu có) - lấy bản ghi mới nhất
$submissions = $DB->get_records('devcode_submissions', array(
    'devcodeid' => $devcode->id,
    'userid' => $USER->id
), 'timemodified DESC, id DESC', '*', 0, 1);

$submission = reset($submissions); // Lấy bản ghi đầu tiên (mới nhất)

// Tạo form nộp bài
$formdata = array(
    'cmid' => $cm->id,
    'devcode' => $devcode,
    'submission' => $submission
);

$mform = new mod_devcode_submission_form(null, $formdata);

// Thêm debugging để xác định vấn đề
$was_posted = $mform->is_submitted();
$was_cancelled = $mform->is_cancelled();
$data_validated = $mform->is_validated();

if ($was_posted) {
    debugging('Form was submitted!', DEBUG_DEVELOPER);
} else {
    debugging('Form was not submitted.', DEBUG_DEVELOPER);
}

if ($was_cancelled) {
    debugging('Form was cancelled.', DEBUG_DEVELOPER);
}

if ($data_validated) {
    debugging('Form data was validated.', DEBUG_DEVELOPER);
} else if ($was_posted) {
    debugging('Form data was not validated.', DEBUG_DEVELOPER);
}

// Xử lý form khi được submit
if ($mform->is_cancelled()) {
    // Nếu người dùng hủy form
    redirect(new \moodle_url('/mod/devcode/view.php', array('id' => $cm->id)));
} else if ($fromform = $mform->get_data()) {
    // Nếu form được submit thành công
    debugging('Form data received: ' . print_r($fromform, true), DEBUG_DEVELOPER);

    // Chuẩn bị dữ liệu bài nộp
    $now = time();
    $code_content = '';

    // Check if form was submitted via POST with direct code input
    if (!isset($fromform->code) && isset($_POST['code']) && !empty($_POST['code'])) {
        $fromform->code = $_POST['code'];
    }

    // Xác định phương thức nộp bài (code trực tiếp hoặc file)
    if (!empty($fromform->submission_method) && $fromform->submission_method == 'file') {
        // Xử lý nộp bài bằng file
        $fs = get_file_storage();
        $context = \context_module::instance($cm->id);
        $files = $fs->get_area_files($context->id, 'mod_devcode', 'submission', $fromform->id, 'id', false);

        if ($files) {
            $file = reset($files);
            $code_content = $file->get_content();
        } else {
            \core\notification::error(get_string('filenotfound', 'devcode'));
            redirect(new \moodle_url('/mod/devcode/submit.php', array('id' => $cm->id)));
        }
    } else {
        // Xử lý nộp bài bằng code trực tiếp
        $code_content = isset($fromform->code) ? $fromform->code : '';
    }

    // Ensure we have a valid string before using trim()
    if (!is_string($code_content) || trim($code_content) === '') {
        \core\notification::error(get_string('codeempty', 'devcode'));
        redirect(new \moodle_url('/mod/devcode/submit.php', array('id' => $cm->id)));
    }

    if ($submission) {
        // Cập nhật bài nộp hiện tại
        $submission->code = $code_content;
        $submission->language = $fromform->language;
        $submission->status = 'submitted'; // Đặt trạng thái là đã nộp
        $submission->timemodified = $now;

        // Cập nhật record trong database
        $DB->update_record('devcode_submissions', $submission);
        $submissionid = $submission->id;
    } else {
        // Tạo bài nộp mới
        $newsubmission = new stdClass();
        $newsubmission->devcodeid = $devcode->id;
        $newsubmission->userid = $USER->id;
        $newsubmission->code = $code_content;
        $newsubmission->language = $fromform->language;
        $newsubmission->status = 'submitted';
        $newsubmission->timecreated = $now;
        $newsubmission->timemodified = $now;

        // Thêm bản ghi mới vào database
        $submissionid = $DB->insert_record('devcode_submissions', $newsubmission);
    }

    // Gọi hàm gửi code đến API để chấm
    $grading_result = devcode_send_to_api($submissionid);

    // Nếu không dùng API, có thể mô phỏng kết quả chấm
    if (!$grading_result) {
        // Giả lập kết quả chấm điểm (chỉ cho mục đích demo)
        // Trong thực tế, kết quả này sẽ được lấy từ API Judge0
        $submission = $DB->get_record('devcode_submissions', array('id' => $submissionid));

        // Chỉ cập nhật nếu chưa có kết quả từ API
        if ($submission && $submission->status !== 'graded') {
            $submission->score = 8;  // Điểm số (trên 10)
            $submission->feedback = get_string('mockresult', 'devcode') . ' 8/10';
            $submission->status = 'graded';
            $DB->update_record('devcode_submissions', $submission);

            // Tạo các kết quả test case mô phỏng
            $testcases = $DB->get_records('devcode_testcases', array('devcodeid' => $devcode->id), 'id ASC', '*', 0, 5);
            $testcase_count = 0;

            foreach ($testcases as $testcase) {
                $result = new stdClass();
                $result->submissionid = $submissionid;
                $result->testcaseid = $testcase->id;
                $result->passed = ($testcase_count < 4) ? 1 : 0; // 4/5 test cases pass
                $result->output = ($testcase_count < 4) ? $testcase->output : "Incorrect output";
                $result->error_message = ($testcase_count < 4) ? "" : "Expected: " . $testcase->output;
                $result->execution_time = rand(10, 500); // 10-500ms
                $result->memory_used = rand(1000, 5000); // 1-5MB
                $result->timecreated = time();

                $DB->insert_record('devcode_submission_results', $result);
                $testcase_count++;
            }
        }
    }

    // Hiển thị thông báo thành công - Set session notification BEFORE doing redirect
    \core\notification::success(get_string('submissionsuccess', 'devcode'));

    // Get the submission record to ensure it exists before redirecting
    $submission_exists = $DB->record_exists('devcode_submissions', array('id' => $submissionid));
    $cm_exists = $DB->record_exists('course_modules', array('id' => $cm->id));

    if ($submission_exists && $cm_exists) {
        // Chuyển hướng đến trang kết quả thay vì trang xem bài tập
        redirect(new \moodle_url('/mod/devcode/view_result.php', array('id' => $cm->id, 'sid' => $submissionid)));
    } else {
        // Fallback if the submission or course module doesn't exist
        redirect(new \moodle_url('/course/view.php', array('id' => $course->id)));
    }
}

// Hiển thị form
echo $OUTPUT->header();

// Hiển thị tên bài tập và đề bài
echo html_writer::tag('h1', format_string($devcode->name), array('class' => 'devcode-assignment-title'));
echo html_writer::tag('hr', '', array('class' => 'devcode-assignment-divider'));
// Hiển thị ngôn ngữ lập trình được sử dụng
$language_name = devcode_get_language_by_id($devcode->language);

    
// Replace simple paragraph with styled highlighted container
echo html_writer::start_tag('div', array('class' => 'devcode-language-highlight'));
echo html_writer::tag('span', $language_name, array('class' => 'devcode-language-value'));
echo html_writer::end_tag('div');

// Hiển thị mô tả bài tập
if (!empty($devcode->intro)) {
    echo html_writer::tag('h3', get_string('description', 'devcode'), array('class' => 'devcode-assignment-description-title'));
    echo html_writer::div(format_text($devcode->intro, $devcode->introformat), 'devcode-assignment-description');
}

// Hiển thị test cases
$visible_testcases = $DB->get_records(
    'devcode_testcases',
    array('devcodeid' => $devcode->id, 'visible_to_student' => 1),
    'id ASC'
);

if (!empty($visible_testcases)) {
    echo html_writer::tag('h3', get_string('testcases', 'devcode'), array('class' => 'devcode-testcases-title'));

    echo html_writer::start_tag('div', array('class' => 'devcode-testcases-container'));
    echo html_writer::start_tag('table', array('class' => 'generaltable devcode-testcases-table'));

    // Header row
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', get_string('input', 'devcode'));
    echo html_writer::tag('th', get_string('expectedoutput', 'devcode'));
    echo html_writer::tag('th', get_string('points', 'devcode'));
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');

    // Test case rows
    echo html_writer::start_tag('tbody');
    foreach ($visible_testcases as $testcase) {
        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', s($testcase->input), array('class' => 'testcase-input'));
        echo html_writer::tag('td', s($testcase->output), array('class' => 'testcase-output'));
        echo html_writer::tag('td', $testcase->points, array('class' => 'testcase-points'));
        echo html_writer::end_tag('tr');
    }
    echo html_writer::end_tag('tbody');

    echo html_writer::end_tag('table');
    echo html_writer::end_tag('div');
}

// Hiển thị deadline nếu có
if (!empty($devcode->duedate)) {
    $duedate = userdate($devcode->duedate);
    echo html_writer::tag(
        'div',
        html_writer::tag('strong', get_string('duedate', 'devcode') . ': ') . $duedate,
        array('class' => 'devcode-duedate')
    );
}

// Nếu đã có bài nộp trước đó, hiển thị kết quả ở phía trên form
if ($submission && !empty($submission->score)) {
    echo html_writer::start_tag('div', array('class' => 'grading-results'));

    // Tiêu đề kết quả
    echo html_writer::start_tag('div', array('class' => 'results-header'));
    echo html_writer::tag('h3', get_string('gradingresults', 'devcode'), array('class' => 'results-title'));
    echo html_writer::tag('div', $submission->score . '/10', array('class' => 'results-score'));
    echo html_writer::end_tag('div');

    // Chi tiết kết quả
    echo html_writer::start_tag('div', array('class' => 'results-details'));

    // Thông tin test cases
    $test_results = $DB->get_records('devcode_submission_results', array('submissionid' => $submission->id));
    $total_tests = count($test_results);
    $passed_tests = 0;
    foreach ($test_results as $test) {
        if ($test->passed) {
            $passed_tests++;
        }
    }

    // Item 1: Test cases passed
    echo html_writer::start_tag('div', array('class' => 'result-item'));
    echo html_writer::tag('div', get_string('testcasespassed', 'devcode'), array('class' => 'result-label'));
    echo html_writer::tag('div', $passed_tests . '/' . $total_tests, array('class' => 'result-value'));
    echo html_writer::end_tag('div');

    // Item 2: Execution time
    $execution_time = 0;
    foreach ($test_results as $test) {
        $execution_time = max($execution_time, $test->execution_time);
    }

    echo html_writer::start_tag('div', array('class' => 'result-item'));
    echo html_writer::tag('div', get_string('executiontime', 'devcode'), array('class' => 'result-label'));
    echo html_writer::tag('div', number_format($execution_time / 1000, 3) . ' s', array('class' => 'result-value'));
    echo html_writer::end_tag('div');

    echo html_writer::end_tag('div'); // results-details

    // Phản hồi
    if (!empty($submission->feedback)) {
        echo html_writer::start_tag('div', array('class' => 'feedback-container'));
        echo html_writer::tag('h4', get_string('feedback', 'devcode'));
        echo html_writer::tag('p', $submission->feedback);
        echo html_writer::end_tag('div');
    }

    // Nút xem kết quả chi tiết và nộp lại bài
    echo html_writer::start_tag('div', array('class' => 'result-actions'));
    echo html_writer::link(
        new \moodle_url('/mod/devcode/view_result.php', array('id' => $cm->id, 'sid' => $submission->id)),
        get_string('viewdetailedresults', 'devcode'),
        array('class' => 'btn btn-secondary')
    );
    echo html_writer::end_tag('div');

    echo html_writer::end_tag('div'); // grading-results
}

// Hiển thị form nộp bài
$mform->display();

// Hiển thị lịch sử nộp bài nếu có từ 2 bài nộp trở lên
$submission_count = $DB->count_records('devcode_submissions', array(
    'devcodeid' => $devcode->id,
    'userid' => $USER->id
));

if ($submission_count > 1) {
    // Lấy lịch sử nộp bài
    $submissions = $DB->get_records(
        'devcode_submissions',
        array('devcodeid' => $devcode->id, 'userid' => $USER->id),
        'timecreated DESC'
    );

    // Hiển thị bảng lịch sử
    echo html_writer::tag('h3', get_string('submissionhistory', 'devcode'));

    echo html_writer::start_tag('table', array('class' => 'submission-history-table'));

    // Header
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', get_string('submissiontime', 'devcode'));
    echo html_writer::tag('th', get_string('status', 'devcode'));
    echo html_writer::tag('th', get_string('pointsearned', 'devcode'));
    echo html_writer::tag('th', get_string('actions', 'devcode'));
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');

    // Rows
    echo html_writer::start_tag('tbody');
    foreach ($submissions as $sub) {
        echo html_writer::start_tag('tr');

        // Thời gian nộp
        echo html_writer::tag('td', userdate($sub->timecreated));

        // Trạng thái
        $status_class = 'status-' . $sub->status;
        // Xử lý riêng trạng thái plagiarism_detected để tránh lỗi nếu string không được tìm thấy
        if ($sub->status === 'plagiarism_detected') {
            $status_text = 'Potential plagiarism detected';
        } else {
            $status_text = get_string('submissionstatus_' . $sub->status, 'devcode', userdate($sub->timemodified));
        }
        echo html_writer::tag('td', html_writer::tag('span', $status_text, array('class' => $status_class)));

        // Điểm số
        $score_text = isset($sub->score) ? $sub->score . '/10' : '-';
        echo html_writer::tag('td', $score_text);

        // Hành động
        echo html_writer::start_tag('td');
        echo html_writer::link(
            new \moodle_url('/mod/devcode/view_result.php', array('id' => $cm->id, 'sid' => $sub->id)),
            get_string('viewdetails', 'devcode'),
            array('class' => 'btn btn-sm btn-secondary')
        );
        echo html_writer::end_tag('td');

        echo html_writer::end_tag('tr');
    }
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
}

echo $OUTPUT->footer();
