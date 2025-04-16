<?php
/*
 * All functions related to grading and updating the gradebook
 *
 * @package    mod_devcode
 * @copyright  2024 Your Name <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Update grades in central gradebook
 *
 * @param stdClass $devcode null means all devcode instances
 * @param int $userid specific user only, 0 means all
 */
function devcode_update_grades($devcode = null, $userid = 0)
{
    global $CFG, $DB, $USER;

    $params = array();
    $params[] = $devcode->id;
    $whereclause = ' WHERE s.devcodeid = ?';

    if ($userid) {
        $params[] = $userid;
        $whereclause .= ' AND s.userid = ?';
    }

    $sql = "SELECT DISTINCT s.id, s.*
            FROM {devcode_submissions} s
            LEFT JOIN {devcode_submission_results} r ON s.id = r.submissionid" . $whereclause . "
            ORDER BY s.id";

    $submissions = $DB->get_records_sql($sql, $params);

    foreach ($submissions as $submission) {
        if ($submission->score !== null) {
            $grade = new stdClass();
            $grade->userid = $submission->userid;
            $grade->rawgrade = $submission->score;
            $grade->feedback = $submission->feedback;
            $grade->feedbackformat = FORMAT_HTML;
            $grade->usermodified = $USER->id;
            $grade->dategraded = time();
            $grade->datesubmitted = $submission->timecreated;

            grade_update('mod/devcode', $devcode->course, 'mod', 'devcode', $devcode->id, 0, $grade);
        }
    }
}

// Hàm gửi bài nộp đến backend để chấm điểm
function devcode_send_to_api($submissionid)
{
    global $CFG, $DB;

    // Debugging to verify function entry point
    debugging('Starting devcode_send_to_api with submissionid: ' . $submissionid, DEBUG_DEVELOPER);

    // Đảm bảo config đã được load
    if (!isset($CFG->devcode)) {
        require_once(dirname(__FILE__) . '/config.php');
    }

    // Lấy thông tin bài nộp
    $submission = $DB->get_record('devcode_submissions', array('id' => $submissionid), '*', MUST_EXIST);
    $devcode = $DB->get_record('devcode', array('id' => $submission->devcodeid), '*', MUST_EXIST);
    
    // Đảm bảo có thông tin về khóa học
    if (empty($devcode->course)) {
        $cm = get_coursemodule_from_instance('devcode', $devcode->id, 0, false, MUST_EXIST);
        $devcode->course = $cm->course;
    }

    // Cập nhật trạng thái thành "processing" - đang xử lý
    // $submission->status = 'processing';
    // $submission->feedback = get_string('processing', 'mod_devcode', '');
    // $DB->update_record('devcode_submissions', $submission);

    // Lấy tất cả test cases của bài tập
    $testcases = $DB->get_records('devcode_testcases', array('devcodeid' => $devcode->id), 'id ASC');

    if (empty($testcases)) {
        debugging('Không tìm thấy test cases nào cho bài tập này.', DEBUG_DEVELOPER);
        return false;
    }

    // Xác định ngôn ngữ lập trình - ưu tiên sử dụng ngôn ngữ từ assignment nếu có
    $language = $submission->language;
    if (!empty($devcode->language)) {
        $language = $devcode->language;
        debugging('Sử dụng ngôn ngữ lập trình từ assignment: ' . $language, DEBUG_DEVELOPER);
    }

    // Chuẩn bị dữ liệu để gửi lên API
    $api_data = array(
        'assignment_id' => $devcode->id,
        'userid' => $submission->userid,
        'code' => $submission->code,
        'language' => $language
    );

    // Gửi bài nộp lên API
    $api_base = $CFG->devcode['api_base_url'];
    $submissions_endpoint = $CFG->devcode['api_endpoints']['submissions'];
    $submission_url = $api_base . $submissions_endpoint;

    $submission_response = devcode_api_request($submission_url, 'POST', $api_data);

    if (!$submission_response || isset($submission_response['error'])) {
        debugging('Lỗi khi gửi bài nộp lên API: ' . json_encode($submission_response), DEBUG_DEVELOPER);

        // Cập nhật trạng thái lỗi
        $submission->status = 'error';
        $submission->feedback = isset($submission_response['error']) ? $submission_response['error'] : 'Không thể kết nối đến dịch vụ chấm điểm.';
        $DB->update_record('devcode_submissions', $submission);

        // Kiểm tra nếu mô phỏng được bật
        if ($CFG->devcode['api_mock_enabled']) {
            debugging('Sử dụng mô phỏng do API không khả dụng.', DEBUG_DEVELOPER);
            return false; // Trả về false để kích hoạt mô phỏng
        }

        return false;
    }

    // Lấy ID bài nộp từ API response
    $api_submission_id = $submission_response['id'];

    // Chuẩn bị dữ liệu test cases
    $testcase_data = array('testcases' => array());
    foreach ($testcases as $testcase) {
        $testcase_data['testcases'][] = array(
            'id' => $testcase->id,
            'input' => $testcase->input,
            'output' => $testcase->output,
            'points' => floatval($testcase->points),
            'time_limit' => intval($testcase->time_limit)
        );
    }

    // Gửi yêu cầu xử lý và chấm điểm
    $process_url = $api_base . $submissions_endpoint . $api_submission_id . '/process';
    $process_response = devcode_api_request($process_url, 'POST', $testcase_data);

    if (!$process_response || isset($process_response['error'])) {
        debugging('Lỗi khi xử lý bài nộp: ' . json_encode($process_response), DEBUG_DEVELOPER);

        // Cập nhật trạng thái lỗi
        $submission->status = 'error';
        $submission->feedback = isset($process_response['error']) ? $process_response['error'] : 'Lỗi khi chấm điểm bài nộp.';
        $DB->update_record('devcode_submissions', $submission);

        return false;
    }

    // Kiểm tra kết quả kiểm tra đạo văn
    $plagiarism_detected = isset($process_response['plagiarism_detected']) && $process_response['plagiarism_detected'] === true;
    $plagiarism_url = isset($process_response['plagiarism_url']) ? $process_response['plagiarism_url'] : '';
    $plagiarism_similarity = isset($process_response['plagiarism_similarity']) ? floatval($process_response['plagiarism_similarity']) : 0;
    
    // Nếu phát hiện đạo code, cập nhật trạng thái thành "plagiarism"
    if ($plagiarism_detected) {
        // $submission->status = 'plagiarism';
        $plagiarism_message = get_string('plagiarism_detected', 'mod_devcode', format_string($plagiarism_similarity));
        
        if (!empty($plagiarism_url)) {
            $submission->plagiarism_url = $plagiarism_url;
            $plagiarism_message .= ' ' . get_string('plagiarism_details', 'mod_devcode', $plagiarism_url);
        }
        
        $submission->feedback = $plagiarism_message;
        $DB->update_record('devcode_submissions', $submission);
        
        // Cập nhật điểm về 0 nếu phát hiện đạo văn
        $submission->score = 0;
        $submission->feedback = $plagiarism_message;
        $submission->passed_tests = 0; 
        $submission->total_tests = count($testcases);
        $submission->timemodified = time();
        
        $DB->update_record('devcode_submissions', $submission);
        
        // Cập nhật điểm vào gradebook
        devcode_update_grades($devcode, $submission->userid);
        
        return true;
    }

    // Lấy kết quả chi tiết từng test case
    $results_url = $api_base . $submissions_endpoint . $api_submission_id . '/results';
    $results_response = devcode_api_request($results_url, 'GET');

    if (!$results_response || isset($results_response['error'])) {
        debugging('Lỗi khi lấy kết quả chi tiết: ' . json_encode($results_response), DEBUG_DEVELOPER);
        return false;
    }

    // Xử lý kết quả từng test case
    $total_points = 0;
    $earned_points = 0;
    $passed_tests = 0;
    $total_testcases = count($testcases);
    $latest_failed_result = null;
    $latest_failed_testcase = null;

    // Xóa kết quả cũ (nếu có)
    $DB->delete_records('devcode_submission_results', array('submissionid' => $submissionid));

    // Sắp xếp kết quả theo thứ tự index để đảm bảo đúng thứ tự
    usort($results_response, function ($a, $b) {
        return isset($a['index']) && isset($b['index']) ? $a['index'] - $b['index'] : 0;
    });

    // Xác định các test cases đã pass và test case bị fail gần nhất (có index lớn nhất)
    $passed_test_results = array();

    foreach ($results_response as $result) {
        $testcase_id = $result['testcase_id'];
        $testcase = $DB->get_record('devcode_testcases', array('id' => $testcase_id));

        if ($testcase) {
            $total_points += $testcase->points;
            $earned_points += $result['points_earned'];

            // Tạo đối tượng kết quả test
            $test_result = new stdClass();
            $test_result->submissionid = $submissionid;
            $test_result->testcaseid = $testcase_id;
            $test_result->passed = isset($result['passed']) ? $result['passed'] : 0;
            $test_result->output = $result['output'];
            $test_result->error_message = $result['error_message'];
            $test_result->execution_time = $result['execution_time'];
            $test_result->memory_used = $result['memory_used'];
            $test_result->timecreated = time();

            if (isset($result['passed']) && $result['passed'] == 1) {
                // Nếu test case pass, thêm vào danh sách passed
                $passed_tests++;
                $passed_test_results[] = $test_result;
            } else {
                // Nếu test case fail, kiểm tra xem có phải là cái mới nhất (index lớn nhất) không
                if ($latest_failed_result === null || (isset($result['index']) && (!isset($latest_failed_result['index']) || $result['index'] > $latest_failed_result['index']))) {
                    $latest_failed_result = $result;
                    $latest_failed_testcase = $testcase;
                    // Không lưu lại kết quả fail này ngay, chỉ giữ lại thông tin
                }
            }
        }
    }

    // Lưu tất cả test case đã pass
    foreach ($passed_test_results as $passed_result) {
        $DB->insert_record('devcode_submission_results', $passed_result);
    }

    // Lưu test case fail mới nhất nếu có
    if ($latest_failed_result !== null) {
        $test_result = new stdClass();
        $test_result->submissionid = $submissionid;
        $test_result->testcaseid = $latest_failed_testcase->id;
        $test_result->passed = 0;
        $test_result->output = $latest_failed_result['output'];
        $test_result->error_message = $latest_failed_result['error_message'];
        $test_result->execution_time = $latest_failed_result['execution_time'];
        $test_result->memory_used = $latest_failed_result['memory_used'];
        $test_result->timecreated = time();

        $DB->insert_record('devcode_submission_results', $test_result);

        // Lưu thông tin test case bị lỗi để hiển thị feedback
        $failed_test_info = array(
            'testcase_id' => $latest_failed_testcase->id,
            'input' => $latest_failed_testcase->input,
            'expected' => $latest_failed_testcase->output,
            'actual' => $latest_failed_result['output'],
            'error' => $latest_failed_result['error_message'],
            'result' => isset($latest_failed_result['result']) ? $latest_failed_result['result'] : 'Wrong Answer'
        );
    }

    // Tính điểm tổng và cập nhật bài nộp
    $final_score = ($total_points > 0) ? ($earned_points / $total_points) * 10 : 0;

    // Chuẩn bị phản hồi
    $feedback = "";
    if ($latest_failed_result !== null) {
        // Nếu có test case bị lỗi, hiển thị thông tin chi tiết
        // $submission->status = 'failed';

        if ($failed_test_info) {
            $feedback = "Test case failed. ";

            if (!empty($failed_test_info['error'])) {
                // Lỗi runtime/compilation
                $feedback .= "Error: " . $failed_test_info['error'];
            } else {
                // Sai kết quả
                $feedback .= "Failed on test case #" . $failed_test_info['testcase_id'] . ".\n";
                $feedback .= "Input: " . $failed_test_info['input'] . "\n";
                $feedback .= "Expected output: " . $failed_test_info['expected'] . "\n";
                $feedback .= "Your output: " . $failed_test_info['actual'];

                if (isset($failed_test_info['result']) && $failed_test_info['result'] != 'Wrong Answer') {
                    $feedback .= "\nResult: " . $failed_test_info['result'];
                }
            }
        } else {
            $feedback = "Test execution failed with an unknown error.";
        }
    } else {
        // Nếu tất cả test case đều pass, thông báo kết quả tổng quan
        // $submission->status = 'graded';
        $feedback = "Passed $passed_tests out of $total_testcases test cases.";
    }

    // Cập nhật thông tin bài nộp
    $submission->score = $final_score;
    $submission->feedback = $feedback;
    $submission->passed_tests = $passed_tests;
    $submission->total_tests = $total_testcases;
    $submission->timemodified = time();

    $DB->update_record('devcode_submissions', $submission);

    // Cập nhật điểm vào gradebook
    devcode_update_grades($devcode, $submission->userid);

    return true;
} 