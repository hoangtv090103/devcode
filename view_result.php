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
use \moodle_url;

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
try {
    $submission = $DB->get_record('devcode_submissions', array('id' => $sid), '*', MUST_EXIST);
} catch (dml_missing_record_exception $e) {
    // Handle the case when the submission doesn't exist
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('notfound', 'devcode') . ': ' . get_string('submission', 'devcode'), 'error');
    echo $OUTPUT->continue_button(new moodle_url('/mod/devcode/view.php', array('id' => $cm->id)));
    echo $OUTPUT->footer();
    exit;
}

// Kiểm tra quyền xem submission
$canviewsubmission = has_capability('mod/devcode:manage', $context) ||
    ($submission->userid == $USER->id && has_capability('mod/devcode:submit', $context));

if (!$canviewsubmission) {
    throw new moodle_exception('nopermissions', 'error', '', get_string('viewsubmission', 'devcode'));
}

// Lấy thông tin ngôn ngữ
$language_name = devcode_get_language_by_id($submission->language);

// Lấy kết quả test cases nếu có
$test_results = $DB->get_records('devcode_submission_results', array('submissionid' => $submission->id));

// Lấy thống kê về test cases pass
$stats = devcode_get_submission_stats($sid);
$passed_tests = $stats->passed_tests;
$total_tests = $stats->total_tests;
$pass_rate = ($total_tests > 0) ? round(($passed_tests / $total_tests) * 100) : 0;

// Hiển thị trang
echo $OUTPUT->header();

// Container chính cho trang kết quả
echo html_writer::start_tag('div', array('class' => 'devcode-results-page'));

// Header kết quả
echo html_writer::start_tag('div', array('class' => 'devcode-results-header'));
echo html_writer::tag('h2', format_string($devcode->name), array('class' => 'devcode-assignment-title'));
echo html_writer::tag('div', get_string('submissionresults', 'devcode'), array('class' => 'devcode-results-subtitle'));
echo html_writer::end_tag('div');

// Container chính cho nội dung kết quả
echo html_writer::start_tag('div', array('class' => 'devcode-results-container'));

// Card thông tin chung
echo html_writer::start_tag('div', array('class' => 'devcode-card submission-info-card'));
echo html_writer::start_tag('div', array('class' => 'devcode-card-header'));
echo html_writer::tag('h3', get_string('submissioninfo', 'devcode'));
echo html_writer::end_tag('div');

echo html_writer::start_tag('div', array('class' => 'devcode-card-body'));

// Hiển thị thông tin trong grid
echo html_writer::start_tag('div', array('class' => 'devcode-info-grid'));

// Thông tin người nộp
if (has_capability('mod/devcode:manage', $context)) {
    $user = $DB->get_record('user', array('id' => $submission->userid));
    $user_link = html_writer::link(
        new \moodle_url('/user/view.php', array('id' => $user->id, 'course' => $course->id)),
        fullname($user)
    );
    echo html_writer::start_tag('div', array('class' => 'devcode-info-item'));
    echo html_writer::tag('div', get_string('student', 'devcode'), array('class' => 'devcode-info-label'));
    echo html_writer::tag('div', $user_link, array('class' => 'devcode-info-value'));
    echo html_writer::end_tag('div');
}

// Ngày nộp
echo html_writer::start_tag('div', array('class' => 'devcode-info-item'));
echo html_writer::tag('div', get_string('submissiontime', 'devcode'), array('class' => 'devcode-info-label'));
echo html_writer::tag('div', userdate($submission->timecreated), array('class' => 'devcode-info-value'));
echo html_writer::end_tag('div');

// Ngôn ngữ
echo html_writer::start_tag('div', array('class' => 'devcode-info-item'));
echo html_writer::tag('div', get_string('programminglanguage', 'devcode'), array('class' => 'devcode-info-label'));
echo html_writer::tag('div', s($language_name), array('class' => 'devcode-info-value'));
echo html_writer::end_tag('div');

// Hiển thị trạng thái và thông tin chung
$status_class = 'badge ';
switch ($submission->status) {
    case 'graded':
        $status_class .= 'badge-success';
        break;
    case 'failed':
        $status_class .= 'badge-warning';
        break;
    case 'error':
        $status_class .= 'badge-danger';
        break;
    case 'processing':
        $status_class .= 'badge-info';
        break;
    case 'plagiarism':
        $status_class .= 'badge-danger';
        break;
    default:
        $status_class .= 'badge-primary';
}

echo html_writer::start_tag('div', array('class' => 'submission-status'));
echo html_writer::tag('span', get_string($submission->status, 'devcode'), array('class' => $status_class));
echo html_writer::end_tag('div');

// Hiển thị thông báo đang xử lý nếu submission đang ở trạng thái processing
if ($submission->status === 'processing') {
    echo html_writer::start_tag('div', array('class' => 'alert alert-info'));
    echo html_writer::tag('i', '', array('class' => 'fa fa-spinner fa-spin mr-2'));
    echo get_string('processing', 'devcode');
    echo html_writer::end_tag('div');

    // JavaScript để tự động làm mới trang sau 5 giây
    $PAGE->requires->js_init_code('
        setTimeout(function() {
            location.reload();
        }, 5000);
    ');
}

// Hiển thị cảnh báo đạo văn
if ($submission->status === 'plagiarism') {
    // Close the info grid before the alert to make it take full width
    echo html_writer::end_tag('div'); // end devcode-info-grid
    
    echo html_writer::start_tag('div', array('class' => 'alert alert-danger full-width-alert'));
    echo html_writer::tag('i', '', array('class' => 'fa fa-exclamation-triangle mr-2'));
    
    // Extract similarity score from the plagiarism record if available
    // Check both submission1_id and submission2_id fields since the submission could be in either one
    $plagiarism_record = $DB->get_record_sql(
        "SELECT * FROM {devcode_plagiarism} 
         WHERE submission1_id = ? OR submission2_id = ? 
         ORDER BY similarity_score DESC LIMIT 1",
        array($submission->id, $submission->id)
    );
    
    $similarity_score = $plagiarism_record ? $plagiarism_record->similarity_score : 0;
    
    // Generate the plagiarism message with the actual similarity score
    echo html_writer::tag('div', get_string('plagiarism_detected', 'devcode', format_float($similarity_score, 1)));
    
    // Add additional feedback if available (like URLs or additional details)
    if (!empty($submission->plagiarism_url)) {
        echo html_writer::start_tag('div', array('class' => 'plagiarism-details mt-2'));
        echo get_string('plagiarism_details', 'devcode', '');
        echo html_writer::tag('div', html_writer::tag('code', s($submission->plagiarism_url)), array('class' => 'url-container mt-1 mb-2'));
        echo html_writer::end_tag('div');
        
        echo html_writer::link(
            $submission->plagiarism_url, // Use the URL string directly
            get_string('view_plagiarism_report', 'devcode'),
            array('class' => 'btn btn-sm btn-outline-danger mt-2', 'target' => '_blank')
        );
    }

    echo html_writer::end_tag('div');
    
    // Start a new info grid after the alert
    echo html_writer::start_tag('div', array('class' => 'devcode-info-grid'));
} else {
    // Only close the info grid at the end if there's no plagiarism alert
    echo html_writer::end_tag('div'); // end devcode-info-grid
}

// Nếu submission bị lỗi, hiển thị thông báo lỗi nổi bật
if ($submission->status === 'failed' || $submission->status === 'error') {
    echo html_writer::start_tag('div', array('class' => 'devcode-error-message'));
    echo html_writer::tag('div', get_string('error', 'core'), array('class' => 'devcode-error-heading'));
    echo html_writer::tag('pre', $submission->feedback, array('class' => 'devcode-error-details'));
    echo html_writer::end_tag('div');
}

// Make sure we don't have an unclosed div if there's a plagiarism alert and no error
if ($submission->status === 'plagiarism') {
    echo html_writer::end_tag('div'); // end the new info grid started after the alert
}

echo html_writer::end_tag('div'); // end card-body
echo html_writer::end_tag('div'); // end submission-info-card

// Card kết quả chấm điểm
echo html_writer::start_tag('div', array('class' => 'devcode-card grading-results-card'));
echo html_writer::start_tag('div', array('class' => 'devcode-card-header with-score'));
echo html_writer::tag('h3', get_string('gradingresults', 'devcode'));
echo html_writer::tag('div', sprintf('%.1f', $submission->score) . '/10', array('class' => 'devcode-score'));
echo html_writer::end_tag('div');

echo html_writer::start_tag('div', array('class' => 'devcode-card-body'));

// Hiển thị statistics với progress bar
echo html_writer::start_tag('div', array('class' => 'devcode-stats-container'));

// Test cases passed với progress bar
echo html_writer::start_tag('div', array('class' => 'devcode-stats-item'));
echo html_writer::tag('div', get_string('testcasespassed', 'devcode'), array('class' => 'devcode-stats-label'));
echo html_writer::start_tag('div', array('class' => 'devcode-stats-value'));
echo html_writer::tag(
    'div',
    $passed_tests . '/' . $total_tests . ' (' . $pass_rate . '%)',
    array('class' => $passed_tests == $total_tests ? 'devcode-perfect-score' : '')
);

// Progress bar
$bar_class = ($passed_tests == $total_tests) ? 'devcode-progress-perfect' : ($pass_rate >= 50 ? 'devcode-progress-good' : 'devcode-progress-poor');
echo html_writer::start_tag('div', array('class' => 'devcode-progress-container'));
echo html_writer::tag('div', '', array(
    'class' => 'devcode-progress-bar ' . $bar_class,
    'style' => 'width: ' . $pass_rate . '%;'
));
echo html_writer::end_tag('div'); // end progress-container

echo html_writer::end_tag('div'); // end stats-value
echo html_writer::end_tag('div'); // end stats-item

// Execution time
$execution_time = 0;
foreach ($test_results as $test) {
    $execution_time = max($execution_time, $test->execution_time);
}

echo html_writer::start_tag('div', array('class' => 'devcode-stats-item'));
echo html_writer::tag('div', get_string('executiontime', 'devcode'), array('class' => 'devcode-stats-label'));
echo html_writer::tag('div', number_format($execution_time / 1000, 3) . ' s', array('class' => 'devcode-stats-value'));
echo html_writer::end_tag('div'); // end stats-item

echo html_writer::end_tag('div'); // end stats-container

// Phản hồi
if (!empty($submission->feedback)) {
    echo html_writer::start_tag('div', array('class' => 'devcode-feedback-container'));
    echo html_writer::tag('h4', get_string('feedback', 'devcode'), array('class' => 'devcode-feedback-title'));
    echo html_writer::tag('div', $submission->feedback, array('class' => 'devcode-feedback-content'));
    echo html_writer::end_tag('div');
}

echo html_writer::end_tag('div'); // end card-body
echo html_writer::end_tag('div'); // end grading-results-card

// Card mã nguồn đã nộp
echo html_writer::start_tag('div', array('class' => 'devcode-card submitted-code-card'));
echo html_writer::start_tag('div', array('class' => 'devcode-card-header'));
echo html_writer::tag('h3', get_string('submittedcode', 'devcode'));
echo html_writer::end_tag('div');

echo html_writer::start_tag('div', array('class' => 'devcode-card-body'));
echo html_writer::tag('pre', html_writer::tag('code', s($submission->code)), array('class' => 'devcode-code-display'));
echo html_writer::end_tag('div'); // end card-body
echo html_writer::end_tag('div'); // end submitted-code-card
// Nút điều hướng
echo html_writer::start_tag('div', array('class' => 'devcode-action-buttons'));
echo html_writer::link(
    new \moodle_url('/mod/devcode/view.php', array('id' => $cm->id)),
    get_string('backtocourse', 'devcode'),
    array('class' => 'btn btn-secondary devcode-action-btn')
);

// Nút nộp lại bài (nếu thời gian cho phép)
if (
    has_capability('mod/devcode:submit', $context) &&
    (!$devcode->duedate || $devcode->duedate > time())
) {
    echo html_writer::link(
        new \moodle_url('/mod/devcode/submit.php', array('id' => $cm->id)),
        get_string('resubmit', 'devcode'),
        array('class' => 'btn btn-primary devcode-action-btn')
    );
}
echo html_writer::end_tag('div'); // end action-buttons

// Card kết quả chi tiết các test case
if (!empty($test_results)) {
    echo html_writer::start_tag('div', array('class' => 'devcode-card testcase-results-card'));
    echo html_writer::start_tag('div', array('class' => 'devcode-card-header'));
    echo html_writer::tag('h3', get_string('detailedresults', 'devcode'));
    echo html_writer::end_tag('div');

    echo html_writer::start_tag('div', array('class' => 'devcode-card-body testcase-table-container'));
    echo html_writer::start_tag('table', array('class' => 'devcode-testcase-table'));
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
        // Thêm debugging chi tiết
        debugging("Looking for testcase with ID: {$result->testcaseid}, SubmissionID: {$result->submissionid}", DEBUG_DEVELOPER);

        // Thay đổi cách query để tìm kiếm test case chính xác hơn
        $testcase = $DB->get_record_sql(
            "SELECT * FROM {devcode_testcases} WHERE id = ?",
            [$result->testcaseid]
        );

        $row_class = $result->passed ? 'devcode-testcase-success' : 'devcode-testcase-failed';
        echo html_writer::start_tag('tr', array('class' => $row_class));

        // Kiểm tra testcase có tồn tại không và hiển thị thông báo chi tiết hơn
        $input_value = ($testcase && isset($testcase->input)) ? s($testcase->input) : '(Không tìm thấy dữ liệu - ID: ' . $result->testcaseid . ')';
        $output_value = ($testcase && isset($testcase->output)) ? s($testcase->output) : '(Không tìm thấy dữ liệu - ID: ' . $result->testcaseid . ')';

        echo html_writer::tag('td', html_writer::tag('pre', $input_value), array('class' => 'devcode-testcase-input'));
        echo html_writer::tag('td', html_writer::tag('pre', $output_value), array('class' => 'devcode-testcase-output'));
        echo html_writer::tag('td', html_writer::tag('pre', s($result->output)), array('class' => 'devcode-testcase-youroutput'));

        // Kết quả
        $result_text = $result->passed ? get_string('passed', 'devcode') : get_string('failed', 'devcode');
        $result_class = $result->passed ? 'devcode-result-passed' : 'devcode-result-failed';
        echo html_writer::tag(
            'td',
            html_writer::tag('span', $result_text, array('class' => $result_class)),
            array('class' => 'devcode-testcase-result')
        );

        echo html_writer::end_tag('tr');

        // Hiển thị thông báo lỗi nếu có
        if (!$result->passed && !empty($result->error_message)) {
            echo html_writer::start_tag('tr', array('class' => 'devcode-testcase-error-row'));
            echo html_writer::tag('td', get_string('errormessage', 'devcode') . ':', array('class' => 'devcode-error-label'));
            echo html_writer::tag(
                'td',
                html_writer::tag('pre', s($result->error_message)),
                array('colspan' => '3', 'class' => 'devcode-testcase-error-message')
            );
            echo html_writer::end_tag('tr');
        }
    }
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
    echo html_writer::end_tag('div'); // end card-body
    echo html_writer::end_tag('div'); // end testcase-results-card
}

echo html_writer::end_tag('div'); // end devcode-results-container

echo html_writer::end_tag('div'); // end devcode-results-page

// Custom inline CSS để làm đẹp hơn
echo html_writer::start_tag('style');
echo '
.devcode-results-page {
    max-width: 1200px;
    margin: 0 auto;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
}

.devcode-results-header {
    margin-bottom: 20px;
    border-bottom: 1px solid #e7e7e7;
    padding-bottom: 16px;
}

.devcode-assignment-title {
    font-size: 28px;
    margin: 0;
    color: #333;
}

.devcode-results-subtitle {
    color: #666;
    font-size: 16px;
    margin-top: 5px;
}

.devcode-results-container {
    display: grid;
    grid-template-columns: 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.devcode-card {
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.24);
    overflow: hidden;
    background-color: white;
}

.devcode-card-header {
    background-color: #f5f7fa;
    padding: 15px 20px;
    border-bottom: 1px solid #e7e7e7;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.devcode-card-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: #404d59;
}

.devcode-card-header.with-score {
    background-color: #f8faff;
}

.devcode-score {
    font-size: 24px;
    font-weight: 700;
    color: #3a7eba;
    padding: 5px 15px;
    background-color: rgba(58, 126, 186, 0.1);
    border-radius: 20px;
}

.devcode-card-body {
    padding: 20px;
}

.devcode-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
    position: relative;
}

.devcode-info-item {
    margin-bottom: 10px;
}

.devcode-info-label {
    font-size: 14px;
    color: #666;
    margin-bottom: 5px;
}

.devcode-info-value {
    font-size: 16px;
    color: #333;
}

.devcode-status-badge {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 500;
}

.status-submitted {
    background-color: #e7f1ff;
    color: #0d6efd;
}

.status-graded {
    background-color: #d1f2ea;
    color: #198754;
}

.status-failed {
    background-color: #fee2e2;
    color: #dc3545;
}

.status-error {
    background-color: #fee2e2;
    color: #dc3545;
}

.devcode-error-message {
    margin-top: 15px;
    background-color: #fee2e2;
    border-left: 4px solid #dc3545;
    padding: 15px;
    border-radius: 4px;
}

.devcode-error-heading {
    font-weight: 600;
    color: #dc3545;
    margin-bottom: 10px;
}

.devcode-error-details {
    background-color: #f5f5f5;
    padding: 10px;
    border-radius: 4px;
    max-height: 200px;
    overflow: auto;
    margin: 0;
    font-size: 14px;
}

/* Add styles for the plagiarism alert */
.alert-danger {
    word-wrap: break-word;
    overflow-wrap: break-word;
    word-break: break-all;
    margin-top: 15px;
    width: 100%;
    box-sizing: border-box;
    grid-column: 1 / -1; /* Make the alert span all columns in grid layouts */
}

.full-width-alert {
    margin: 15px 0;
    padding: 15px 20px;
    border-radius: 8px;
    background-color: #fee2e2;
    border-left: 4px solid #dc3545;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.alert-danger a {
    word-wrap: break-word;
    overflow-wrap: break-word;
    word-break: break-all;
}

/* Style for the plagiarism URL display */
.plagiarism-details {
    margin-top: 10px;
}

.url-container {
    background-color: rgba(0, 0, 0, 0.05);
    padding: 8px;
    border-radius: 4px;
    max-width: 100%;
    overflow-x: auto;
}

.url-container code {
    white-space: pre-wrap;
    word-break: break-all;
    color: #e83e8c;
}

/* Ensure the plagiarism alert is displayed correctly in the info grid */
.devcode-info-grid .alert {
    grid-column: 1 / -1;
    margin-top: 15px;
    width: 100%;
}

.devcode-stats-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.devcode-stats-label {
    font-size: 14px;
    color: #666;
    margin-bottom: 5px;
}

.devcode-stats-value {
    font-size: 16px;
    color: #333;
}

.devcode-perfect-score {
    color: #198754;
    font-weight: 600;
}

.devcode-progress-container {
    height: 10px;
    background-color: #e9ecef;
    border-radius: 5px;
    margin-top: 8px;
    overflow: hidden;
}

.devcode-progress-bar {
    height: 100%;
    border-radius: 5px;
}

.devcode-progress-perfect {
    background-color: #198754;
}

.devcode-progress-good {
    background-color: #0d6efd;
}

.devcode-progress-poor {
    background-color: #ffc107;
}

.devcode-feedback-container {
    margin-top: 15px;
    background-color: #f8f9fa;
    border-radius: 4px;
    padding: 15px;
}

.devcode-feedback-title {
    font-size: 16px;
    font-weight: 600;
    margin-top: 0;
    margin-bottom: 10px;
    color: #495057;
}

.devcode-feedback-content {
    font-size: 15px;
    line-height: 1.5;
    color: #333;
}

.devcode-code-display {
    background-color: #22272e;
    color: #adbac7;
    padding: 15px;
    border-radius: 6px;
    margin: 0;
    max-height: 400px;
    overflow: auto;
    font-family: SFMono-Regular, Consolas, "Liberation Mono", Menlo, monospace;
    font-size: 14px;
    line-height: 1.6;
}

.devcode-testcase-table {
    width: 100%;
    border-collapse: collapse;
}

.devcode-testcase-table th {
    background-color: #f8f9fa;
    padding: 12px;
    text-align: left;
    border-bottom: 2px solid #e9ecef;
    font-weight: 600;
}

.devcode-testcase-table td {
    padding: 12px;
    border-bottom: 1px solid #e9ecef;
    vertical-align: top;
}

.devcode-testcase-table pre {
    margin: 0;
    max-height: 100px;
    overflow: auto;
    background-color: #f8f9fa;
    padding: 8px;
    border-radius: 4px;
    font-family: monospace;
    font-size: 13px;
    white-space: pre-wrap;
}

.devcode-testcase-success {
    background-color: rgba(25, 135, 84, 0.05);
}

.devcode-testcase-failed {
    background-color: rgba(220, 53, 69, 0.05);
}

.devcode-result-passed {
    display: inline-block;
    padding: 4px 8px;
    background-color: #d1f2ea;
    color: #198754;
    border-radius: 4px;
    font-weight: 500;
}

.devcode-result-failed {
    display: inline-block;
    padding: 4px 8px;
    background-color: #fee2e2;
    color: #dc3545;
    border-radius: 4px;
    font-weight: 500;
}

.devcode-testcase-error-row {
    background-color: rgba(220, 53, 69, 0.03);
}

.devcode-testcase-error-message pre {
    color: #dc3545;
    background-color: #fff3f3;
}

.devcode-action-buttons {
    display: flex;
    gap: 10px;
    margin-top: 20px;
    justify-content: flex-start;
}

.devcode-action-btn {
    padding: 8px 16px;
    text-decoration: none;
    font-weight: 500;
}

@media (max-width: 768px) {
    .devcode-info-grid {
        grid-template-columns: 1fr;
    }
    
    .devcode-stats-container {
        grid-template-columns: 1fr;
    }
    
    .devcode-testcase-table {
        display: block;
        overflow-x: auto;
    }
}
';
echo html_writer::end_tag('style');

echo $OUTPUT->footer();
