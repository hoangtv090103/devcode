<?php


/**
 * Trang nộp bài cho DevCode
 *
 * @package     mod_devcode

 */

require_once('../../config.php');
require_once(dirname(__FILE__) . '/lib.php');
require_once($CFG->dirroot . '/mod/devcode/classes/form/submission_form.php');

// Required imports for context and file handling
require_once($CFG->libdir . '/accesslib.php');
require_once($CFG->libdir . '/filelib.php');

// Import core classes
use core\output\html_writer;
use core\notification;
use core\context\module as context_module;
use core\context\user as context_user;

$id = required_param('id', PARAM_INT); // Course Module ID

// Lấy thông tin course module, course, và devcode
$cm = get_coursemodule_from_id('devcode', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$devcode = $DB->get_record('devcode', array('id' => $cm->instance), '*', MUST_EXIST);

$activityheader = ['description' => ''];

// Kiểm tra đăng nhập và quyền truy cập
require_login($course, true, $cm);
$context = context_module::instance($cm->id);
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

// Load JavaScript modules - bỏ comment dòng này để sử dụng JS modules
$PAGE->requires->js_call_amd('mod_devcode/code_editor', 'init');
$PAGE->requires->js_call_amd('mod_devcode/tabs', 'init');

// Add file upload styles via JavaScript
$PAGE->requires->js_init_code("
    document.addEventListener('DOMContentLoaded', function() {
        // Create style element
        var style = document.createElement('style');
        style.type = 'text/css';
        style.innerHTML = `
            .file-upload-container {
                padding: 25px;
                border: 2px dashed #ccc;
                border-radius: 8px;
                background-color: #f9f9f9;
                margin-bottom: 20px;
                transition: all 0.3s;
                text-align: center;
            }
            .file-upload-container:hover {
                border-color: #66afe9;
                background-color: #f4f8fa;
            }
            .file-upload-help {
                margin-bottom: 15px;
                color: #555;
                font-size: 1rem;
                font-weight: 500;
            }
            .file-upload-accepted-types {
                margin: 15px 0;
                font-size: 0.9rem;
            }
            .accepted-file-types {
                margin: 15px 0;
                padding: 12px;
                background-color: #f0f0f0;
                border-radius: 6px;
                border-left: 4px solid #0056b3;
            }
            .file-extension {
                display: inline-block;
                padding: 3px 8px;
                margin: 0 5px;
                background-color: #e7f3ff;
                border-radius: 3px;
                font-family: monospace;
                font-weight: bold;
                color: #0056b3;
            }
            .file-tab-active .file-tab-only {
                display: block;
            }
            .code-tab-active .file-tab-only {
                display: none;
            }
            .file-upload-element {
                display: none;
                margin: 20px auto;
                max-width: 500px;
                text-align: center;
            }
            .file-tab-active .file-upload-element {
                display: block;
            }
            .file-upload-icon {
                display: block;
                margin: 0 auto 15px;
                width: 60px;
                height: 60px;
                line-height: 60px;
                border-radius: 50%;
                background-color: #e7f3ff;
                color: #0056b3;
                font-size: 24px;
                text-align: center;
            }
            .submission-status {
                margin: 20px 0;
                padding: 10px 15px;
                border-radius: 4px;
                font-weight: 500;
            }
            .status-notsubmitted {
                background-color: #f8f9fa;
                border-left: 4px solid #6c757d;
                color: #6c757d;
            }
            .status-submitted {
                background-color: #e8f4f8;
                border-left: 4px solid #17a2b8;
                color: #17a2b8;
            }
            .status-graded {
                background-color: #e8f8ef;
                border-left: 4px solid #28a745;
                color: #28a745;
            }
            .status-plagiarism_detected {
                background-color: #fdf7e8;
                border-left: 4px solid #ffc107;
                color: #856404;
            }
        `;
        document.getElementsByTagName('head')[0].appendChild(style);
    });
");

// Thêm JavaScript inline tạm thời thay thế
/*
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
*/

// Kiểm tra nếu còn thời gian nộp bài
if (!empty($devcode->duedate) && $devcode->duedate < time()) {
    $renderer = $PAGE->get_renderer('mod_devcode');
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('assignmentclosed', 'devcode'), 'error');
    echo $OUTPUT->continue_button(new moodle_url('/mod/devcode/view.php', array('id' => $cm->id)));
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
    redirect(new moodle_url('/mod/devcode/view.php', array('id' => $cm->id)));
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

    // Xác định phương thức nộp bài (code hoặc file)
    $submission_method = isset($fromform->submission_method) ? $fromform->submission_method : 'code';

    // Lấy nội dung bài nộp dựa vào phương thức
    if ($submission_method === 'code') {
        // Lấy mã code từ textarea
        if (!empty($fromform->code)) {
            $code_content = trim($fromform->code);
        }

        // Kiểm tra nếu code trống
        if (!is_string($code_content) || trim($code_content) === '') {
            \core\notification::error(get_string('codeempty', 'devcode'));
            redirect(new moodle_url('/mod/devcode/submit.php', array('id' => $cm->id)));
            exit;
        }
    } else if ($submission_method === 'file') {
        // Xử lý nộp file
        $fs = get_file_storage();
        $context = context_module::instance($cm->id);

        // Lấy thông tin về file được upload
        $file_info = file_get_submitted_draft_itemid('sourcefile');

        if (!$file_info) {
            \core\notification::error(get_string('fileuploadrequired', 'devcode'));
            redirect(new moodle_url('/mod/devcode/submit.php', array('id' => $cm->id)));
            exit;
        }

        // Lấy các file đã upload
        $files = $fs->get_area_files(
            context_user::instance($USER->id)->id,
            'user',
            'draft',
            $file_info,
            'id',
            false
        );

        if (empty($files)) {
            \core\notification::error(get_string('fileuploadrequired', 'devcode'));
            redirect(new moodle_url('/mod/devcode/submit.php', array('id' => $cm->id)));
            exit;
        }

        // Lấy file đầu tiên
        $file = reset($files);
        $filename = $file->get_filename();
        $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        // Lấy thông tin ngôn ngữ
        $language_name = devcode_get_language_by_id($devcode->language);
        $language_name = strtolower($language_name);

        // Kiểm tra tính hợp lệ của loại file dựa vào ngôn ngữ
        $valid_extension = false;
        $accepted_extensions = [];

        // Kiểm tra phần mở rộng tệp với ngôn ngữ
        if (str_contains($language_name, 'python')) {
            $accepted_extensions = ['py'];
        } else if (str_contains($language_name, 'java')) {
            $accepted_extensions = ['java'];
        } else if ((str_contains($language_name, 'c++') || str_contains($language_name, 'cpp'))) {
            $accepted_extensions = ['cpp', 'cc', 'cxx', 'c++', 'h', 'hpp'];
        } else if (str_contains($language_name, 'javascript')) {
            $accepted_extensions = ['js'];
        } else if (str_contains($language_name, 'c')) {
            $accepted_extensions = ['c', 'h'];
        } else if (str_contains($language_name, 'c#')) {
            $accepted_extensions = ['cs'];
        }

        debugging('Accepted extensions: ' . implode(', ', $accepted_extensions));
        debugging('File extension: ' . $file_ext);
        debugging('Language name: ' . $language_name);

        if (!in_array($file_ext, $accepted_extensions)) {
            \core\notification::error(get_string('invalidfiletype', 'devcode', $language_name));
            redirect(new moodle_url('/mod/devcode/submit.php', array('id' => $cm->id)));
            exit;
        }

        // Đọc nội dung file
        $code_content = $file->get_content();

        if (empty($code_content)) {
            \core\notification::error(get_string('emptyfile', 'devcode'));
            redirect(new moodle_url('/mod/devcode/submit.php', array('id' => $cm->id)));
            exit;
        }
    }

    // Dữ liệu để tạo bản ghi submission mới
    $submission = new stdClass();
    $submission->devcodeid = $devcode->id;
    $submission->userid = $USER->id;
    $submission->code = $code_content;
    $submission->language = $devcode->language;
    $submission->timemodified = $now;
    $submission->timecreated = $now;
    $submission->status = 'processing';
    $submission->feedback = get_string('processing', 'devcode', '');

    // Thêm thông tin phương thức nộp bài
    $submission->submission_method = $submission_method;

    // Lưu bài nộp vào cơ sở dữ liệu
    $submission->id = $DB->insert_record('devcode_submissions', $submission);

    // Set initial status to "processing"
    $submission->status = 'processing';
    $submission->feedback = get_string('processing', 'devcode', '');
    $DB->update_record('devcode_submissions', $submission);

    // Trigger asynchronous processing via a separate API call
    $async_check_data = array(
        'submission_id' => $submission->id,
        'async' => true
    );
    $async_url = $CFG->devcode['api_base_url'] . $CFG->devcode['api_endpoints']['async_processing'];
    devcode_api_request($async_url, 'POST', $async_check_data);

    // Always show success message since processing is happening asynchronously
    \core\notification::success(get_string('submissionsuccess', 'devcode'));

    // Get the submission record to ensure it exists before redirecting
    $submission_exists = $DB->record_exists('devcode_submissions', array('id' => $submission->id));
    $cm_exists = $DB->record_exists('course_modules', array('id' => $cm->id));

    if ($submission_exists && $cm_exists) {
        // Chuyển hướng đến trang kết quả thay vì trang xem bài tập
        redirect(new moodle_url('/mod/devcode/view_result.php', array('id' => $cm->id, 'sid' => $submission->id)));
    } else {
        // Fallback if the submission or course module doesn't exist
        redirect(new moodle_url('/course/view.php', array('id' => $course->id)));
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

// Hiển thị trạng thái nộp bài
if ($submission) {
    // Xử lý riêng trạng thái plagiarism_detected để tránh lỗi nếu string không được tìm thấy
    if ($submission->status === 'plagiarism_detected') {
        $status_text = 'Potential plagiarism detected';
    } else {
        $status_text = get_string('submissionstatus_' . $submission->status, 'devcode', userdate($submission->timemodified));
    }
    echo html_writer::tag(
        'div',
        html_writer::tag('strong', get_string('submissionstatus', 'devcode') . ': ') .
            html_writer::tag('span', $status_text, array('class' => 'status-text')),
        array('class' => 'submission-status status-' . $submission->status)
    );
} else {
    echo html_writer::tag(
        'div',
        html_writer::tag('strong', get_string('submissionstatus', 'devcode') . ': ') .
            html_writer::tag('span', get_string('submissionstatus_notsubmitted', 'devcode'), array('class' => 'status-text')),
        array('class' => 'submission-status status-notsubmitted')
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
        new moodle_url('/mod/devcode/view_result.php', array('id' => $cm->id, 'sid' => $submission->id)),
        get_string('viewdetailedresults', 'devcode'),
        array('class' => 'btn btn-secondary')
    );
    echo html_writer::end_tag('div');

    echo html_writer::end_tag('div'); // grading-results
}

// Hiển thị form nộp bài
$mform->display();

// Hiển thị lịch sử nộp bài nếu có từ 2 bài nộp trở lên
// $submission_count = $DB->count_records('devcode_submissions', array(
//     'devcodeid' => $devcode->id,
//     'userid' => $USER->id
// ));

// if ($submission_count > 1) {
//     // Lấy lịch sử nộp bài
//     $submissions = $DB->get_records(
//         'devcode_submissions',
//         array('devcodeid' => $devcode->id, 'userid' => $USER->id),
//         'timecreated DESC'
//     );

//     // Hiển thị bảng lịch sử
//     echo html_writer::tag('h3', get_string('submissionhistory', 'devcode'));

//     echo html_writer::start_tag('table', array('class' => 'submission-history-table'));

//     // Header
//     echo html_writer::start_tag('thead');
//     echo html_writer::start_tag('tr');
//     echo html_writer::tag('th', get_string('submissiontime', 'devcode'));
//     echo html_writer::tag('th', get_string('status', 'devcode'));
//     echo html_writer::tag('th', get_string('pointsearned', 'devcode'));
//     echo html_writer::tag('th', get_string('actions', 'devcode'));
//     echo html_writer::end_tag('tr');
//     echo html_writer::end_tag('thead');

//     // Rows
//     echo html_writer::start_tag('tbody');
//     foreach ($submissions as $sub) {
//         echo html_writer::start_tag('tr');

//         // Thời gian nộp
//         echo html_writer::tag('td', userdate($sub->timecreated));

//         // Trạng thái
//         $status_class = 'status-' . $sub->status;
//         // Xử lý riêng trạng thái plagiarism_detected để tránh lỗi nếu string không được tìm thấy
//         if ($sub->status === 'plagiarism_detected') {
//             $status_text = 'Potential plagiarism detected';
//         } else {
//             $status_text = get_string('submissionstatus_' . $sub->status, 'devcode', userdate($sub->timemodified));
//         }
//         echo html_writer::tag('td', html_writer::tag('span', $status_text, array('class' => $status_class)));

//         // Điểm số
//         $score_text = isset($sub->score) ? $sub->score . '/10' : '-';
//         echo html_writer::tag('td', $score_text);

//         // Hành động
//         echo html_writer::start_tag('td');
//         echo html_writer::link(
//             new moodle_url('/mod/devcode/view_result.php', array('id' => $cm->id, 'sid' => $sub->id)),
//             get_string('viewdetails', 'devcode'),
//             array('class' => 'btn btn-sm btn-secondary')
//         );
//         echo html_writer::end_tag('td');

//         echo html_writer::end_tag('tr');
//     }
//     echo html_writer::end_tag('tbody');
//     echo html_writer::end_tag('table');
// }

echo $OUTPUT->footer();
