<?php

/**
 * Internal library of functions for module devcode
 *
 * All the devcode specific functions, needed to implement the module
 * logic, should be in here.
 *
 * @package    mod_devcode
 * @copyright  2024 Your Name <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/devcode/config.php');

/**
 * Kiểm tra phần mở rộng của file có phù hợp với ngôn ngữ lập trình không
 * 
 * @param string $extension Phần mở rộng file không bao gồm dấu chấm (.)
 * @param string $language Ngôn ngữ lập trình được yêu cầu
 * @return bool
 */
function devcode_is_valid_file_extension($extension, $language)
{
    $language = strtolower($language);
    $extension = strtolower($extension);
    
    // Kiểm tra các ngôn ngữ chung dựa trên chuỗi con
    if (stripos($language, 'python') !== false) {
        return in_array($extension, array('py'));
    } else if (stripos($language, 'java') !== false) {
        return in_array($extension, array('java'));
    } else if (stripos($language, 'c++') !== false || stripos($language, 'cpp') !== false) {
        return in_array($extension, array('cpp', 'cc', 'cxx', 'c++', 'h', 'hpp'));
    } else if (stripos($language, 'javascript') !== false || stripos($language, 'js') !== false) {
        return in_array($extension, array('js'));
    } else if (stripos($language, 'c ') !== false || $language === 'c') {
        return in_array($extension, array('c', 'h'));
    }
    
    // Nếu ngôn ngữ không khớp với bất kỳ kiểm tra nào, cho phép tất cả các định dạng
    return true;
}

/**
 * Lấy thống kê về test cases của một bài nộp
 * 
 * @param int $submissionid ID của bài nộp
 * @return object Đối tượng chứa thông tin về số test cases đã pass và tổng số test cases
 */
function devcode_get_submission_stats($submissionid)
{
    global $DB;

    $stats = new stdClass();
    $stats->passed_tests = 0;
    $stats->total_tests = 0;

    // Lấy thông tin bài nộp
    $submission = $DB->get_record('devcode_submissions', array('id' => $submissionid));
    if (!$submission) {
        return $stats;
    }

    // Nếu đã có thông tin cached trong bài nộp
    if (isset($submission->passed_tests) && isset($submission->total_tests)) {
        $stats->passed_tests = $submission->passed_tests;
        $stats->total_tests = $submission->total_tests;
        return $stats;
    }

    // Nếu không có thông tin cached, tính toán từ kết quả chi tiết
    $results = $DB->get_records('devcode_submission_results', array('submissionid' => $submissionid));
    $stats->total_tests = count($results);

    foreach ($results as $result) {
        if ($result->passed == 1) {
            $stats->passed_tests++;
        }
    }

    return $stats;
} 