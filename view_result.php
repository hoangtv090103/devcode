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
 * Hiển thị kết quả chấm bài cho DevCode
 *
 * @package     mod_devcode
 * @copyright   2024 Your Name <your@email.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once(dirname(__FILE__) . '/lib.php');

// Required imports
require_once($CFG->libdir . '/accesslib.php');
use \core\output\html_writer;

// Course Module ID
$id = required_param('id', PARAM_INT);
$sid = required_param('sid', PARAM_INT); // Submission ID

// Lấy thông tin course module, course, và devcode
$cm = get_coursemodule_from_id('devcode', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$devcode = $DB->get_record('devcode', array('id' => $cm->instance), '*', MUST_EXIST);

// Kiểm tra đăng nhập và quyền truy cập
require_login($course, true, $cm);
$context = \context_module::instance($cm->id);

// Thiết lập trang
$PAGE->set_url('/mod/devcode/view_result.php', array('id' => $cm->id, 'sid' => $sid));
$PAGE->set_title(format_string($devcode->name) . ': ' . get_string('submissionresults', 'devcode'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Thiết lập activity record và tắt hiển thị mô tả
$PAGE->set_activity_record($devcode);
$activityheader = ['description' => ''];
$PAGE->activityheader->set_attrs($activityheader);

// Thêm CSS cho hiển thị kết quả
$PAGE->requires->css('/mod/devcode/styles.css');

// Lấy thông tin submission
$submission = $DB->get_record('devcode_submissions', array('id' => $sid), '*', MUST_EXIST);

// Kiểm tra quyền xem submission
$canviewsubmission = has_capability('mod/devcode:manage', $context) || 
                    ($submission->userid == $USER->id && has_capability('mod/devcode:submit', $context));

if (!$canviewsubmission) {
    throw new moodle_exception('nopermissions', 'error', '', get_string('viewsubmission', 'devcode'));
}

// Lấy thông tin ngôn ngữ
$assignment = $DB->get_record('devcode', array('id' => $cm->instance));
$languages = devcode_get_supported_languages();
$language_name = isset($languages[$assignment->id]) ? 
                $languages[$assignment->id] : 
                $submission->language;

// Lấy kết quả test cases nếu có
$test_results = $DB->get_records('devcode_submission_results', array('submissionid' => $submission->id));

// Lấy bài nộp và kết quả chi tiết
$results = $DB->get_records('devcode_submission_results', array('submissionid' => $sid));

// Lấy thống kê về test cases pass
$stats = devcode_get_submission_stats($sid);
$passed_tests = $stats->passed_tests;
$total_tests = $stats->total_tests;
$pass_rate = ($total_tests > 0) ? round(($passed_tests / $total_tests) * 100) : 0;

// Hiển thị trang
echo $OUTPUT->header();

// Hiển thị tiêu đề và thông tin chung
echo $OUTPUT->heading(format_string($devcode->name) . ': ' . get_string('submissionresults', 'devcode'));

// Container chính cho kết quả
echo html_writer::start_tag('div', array('class' => 'results-container'));

// Thông tin submission
echo html_writer::start_tag('div', array('class' => 'submission-info'));
echo html_writer::tag('h4', get_string('submissioninfo', 'devcode'));

echo html_writer::start_tag('div', array('class' => 'submission-details'));
// Thông tin người nộp
if (has_capability('mod/devcode:manage', $context)) {
    $user = $DB->get_record('user', array('id' => $submission->userid));
    $user_link = html_writer::link(
        new \moodle_url('/user/view.php', array('id' => $user->id, 'course' => $course->id)),
        fullname($user)
    );
    echo html_writer::tag('div', get_string('student', 'devcode') . ': ' . $user_link);
}

// Ngày nộp và ngôn ngữ
echo html_writer::tag('div', get_string('submissiontime', 'devcode') . ': ' . userdate($submission->timecreated));
echo html_writer::tag('div', get_string('programminglanguage', 'devcode') . ': ' . s($language_name));

// Hiển thị trạng thái
$status_text = get_string('submissionstatus_' . $submission->status, 'devcode', userdate($submission->timemodified));
$status_class = 'status-' . $submission->status;

// Hiển thị trạng thái với lớp CSS phù hợp
echo html_writer::tag('div', get_string('status', 'devcode') . ': ' . 
    html_writer::tag('span', $status_text, array('class' => $status_class)), 
    array('class' => 'submission-status'));

// Nếu submission bị lỗi, hiển thị thông báo lỗi nổi bật
if ($submission->status === 'failed' || $submission->status === 'error') {
    echo html_writer::start_tag('div', array('class' => 'submission-error alert alert-danger'));
    echo html_writer::tag('h5', get_string('error', 'core'), array('class' => 'alert-heading'));
    echo html_writer::tag('pre', $submission->feedback, array('class' => 'error-details'));
    echo html_writer::end_tag('div');
}

echo html_writer::end_tag('div'); // submission-details

echo html_writer::end_tag('div'); // submission-info

// Hiển thị kết quả chấm
echo html_writer::start_tag('div', array('class' => 'grading-results'));

// Tiêu đề kết quả
echo html_writer::start_tag('div', array('class' => 'results-header'));
echo html_writer::tag('h4', get_string('gradingresults', 'devcode'), array('class' => 'results-title'));
echo html_writer::tag('div', $submission->score . '/10', array('class' => 'results-score'));
echo html_writer::end_tag('div');

// Chi tiết kết quả
echo html_writer::start_tag('div', array('class' => 'results-details'));

// Thống kê test cases - sử dụng dữ liệu từ trường passed_tests và total_tests
if (isset($submission->passed_tests) && isset($submission->total_tests)) {
    $passed_tests = $submission->passed_tests;
    $total_tests = $submission->total_tests;
} else {
    // Tính toán từ kết quả chi tiết nếu không có dữ liệu cached
    $total_tests = count($test_results);
    $passed_tests = 0;
    foreach ($test_results as $test) {
        if ($test->passed) {
            $passed_tests++;
        }
    }
}

// Tính tỷ lệ phần trăm pass
$pass_percentage = ($total_tests > 0) ? round(($passed_tests / $total_tests) * 100) : 0;

// Item 1: Test cases passed
echo html_writer::start_tag('div', array('class' => 'result-item'));
echo html_writer::tag('div', get_string('testcasespassed', 'devcode'), array('class' => 'result-label'));
echo html_writer::tag('div', 
    $passed_tests . '/' . $total_tests . ' (' . $pass_percentage . '%)', 
    array('class' => 'result-value' . ($passed_tests == $total_tests ? ' all-passed' : '')));
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

echo html_writer::end_tag('div'); // grading-results

// Code đã nộp
echo html_writer::start_tag('div', array('class' => 'submitted-code'));
echo html_writer::tag('h4', get_string('submittedcode', 'devcode'));
echo html_writer::tag('pre', s($submission->code), array('class' => 'code-display'));
echo html_writer::end_tag('div');

// Chi tiết kết quả từng test case
if (!empty($test_results)) {
    echo html_writer::start_tag('div', array('class' => 'testcase-results'));
    echo html_writer::tag('h4', get_string('detailedresults', 'devcode'));
    
    echo html_writer::start_tag('table', array('class' => 'generaltable testcase-results-table'));
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', get_string('testcaseinput', 'devcode'));
    echo html_writer::tag('th', get_string('testcaseoutput', 'devcode'));
    echo html_writer::tag('th', get_string('youroutput', 'devcode'));
    echo html_writer::tag('th', get_string('result', 'devcode'));
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    
    echo html_writer::start_tag('tbody');
    foreach ($test_results as $result) {
        $testcase = $DB->get_record('devcode_testcases', array('id' => $result->testcaseid));
        
        echo html_writer::start_tag('tr', $result->passed ? array('class' => 'success-row') : array('class' => 'error-row'));
        echo html_writer::tag('td', s($testcase->input), array('class' => 'testcase-input'));
        echo html_writer::tag('td', s($testcase->output), array('class' => 'testcase-output'));
        echo html_writer::tag('td', s($result->output), array('class' => 'your-output'));
        
        // Kết quả
        $result_text = $result->passed ? get_string('passed', 'devcode') : get_string('failed', 'devcode');
        $result_class = $result->passed ? 'passed' : 'failed';
        echo html_writer::tag('td', html_writer::tag('span', $result_text, array('class' => $result_class)));
        
        echo html_writer::end_tag('tr');
        
        // Hiển thị thông báo lỗi nếu có
        if (!$result->passed && !empty($result->error_message)) {
            echo html_writer::start_tag('tr', array('class' => 'error-message-row'));
            echo html_writer::tag('td', get_string('errormessage', 'devcode') . ':', array('class' => 'error-label'));
            echo html_writer::tag('td', s($result->error_message), array('colspan' => '3', 'class' => 'error-message'));
            echo html_writer::end_tag('tr');
        }
    }
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
    
    echo html_writer::end_tag('div'); // testcase-results
}

echo html_writer::end_tag('div'); // results-container

// Nút trở về và nộp lại bài
echo html_writer::start_tag('div', array('class' => 'action-buttons'));
echo html_writer::link(
    new \moodle_url('/mod/devcode/view.php', array('id' => $cm->id)),
    get_string('backtocourse', 'devcode'),
    array('class' => 'btn btn-secondary')
);

// Nút nộp lại bài (nếu thời gian cho phép)
if (has_capability('mod/devcode:submit', $context) && 
    (!$devcode->duedate || $devcode->duedate > time())) {
    echo ' ';
    echo html_writer::link(
        new \moodle_url('/mod/devcode/submit.php', array('id' => $cm->id)),
        get_string('resubmit', 'devcode'),
        array('class' => 'btn btn-primary')
    );
}
echo html_writer::end_tag('div'); // action-buttons

echo $OUTPUT->footer(); 