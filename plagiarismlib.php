<?php
/**
 * Plagiarism detection functions for module devcode
 *
 * All functions related to plagiarism detection
 *
 * @package    mod_devcode
 * @copyright  2024 Your Name <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__) . '/config.php');
require_once(dirname(__FILE__) . '/apilib.php');
require_once(dirname(__FILE__) . '/gradelib.php');

/**
 * Checks a submission for plagiarism.
 *
 * @param int $submissionid The ID of the submission to check
 * @return bool True if plagiarism is detected, false otherwise
 */
function devcode_check_plagiarism($submissionid) {
    global $CFG, $DB;

    // Debugging to verify function entry point
    debugging('Starting devcode_check_plagiarism with submissionid: ' . $submissionid, DEBUG_DEVELOPER);

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

    // Skip plagiarism check if it's disabled for this assignment
    if (empty($devcode->enable_plagiarism)) {
        return false;
    }

    // Xác định ngôn ngữ lập trình
    $language = $submission->language;
    if (!empty($devcode->language)) {
        $language = $devcode->language;
    }

    // Chuẩn bị dữ liệu để gửi lên API
    $api_data = array(
        'assignment_id' => $devcode->id,
        'userid' => $submission->userid,
        'code' => $submission->code,
        'language' => $language,
        'plagiarism_check_only' => true
    );

    // Gửi bài nộp lên API
    $api_base = $CFG->devcode['api_base_url'];
    $submissions_endpoint = $CFG->devcode['api_endpoints']['submissions'];
    $submission_url = $api_base . $submissions_endpoint;

    $submission_response = devcode_api_request($submission_url, 'POST', $api_data);

    if (!$submission_response || isset($submission_response['error'])) {
        debugging('Lỗi khi gửi yêu cầu kiểm tra đạo văn lên API: ' . json_encode($submission_response), DEBUG_DEVELOPER);
        return false;
    }

    // Check if plagiarism was detected
    $plagiarism_detected = isset($submission_response['plagiarism_detected']) && 
                           $submission_response['plagiarism_detected'] === true;
    
    if ($plagiarism_detected) {
        // Update submission with plagiarism information
        $submission->status = 'plagiarism';
        $plagiarism_similarity = isset($submission_response['plagiarism_similarity']) ? 
                               floatval($submission_response['plagiarism_similarity']) : 0;
        $plagiarism_url = isset($submission_response['plagiarism_url']) ? 
                         $submission_response['plagiarism_url'] : '';
        
        $plagiarism_message = get_string('plagiarism_detected', 'mod_devcode', format_string($plagiarism_similarity));
        
        if (!empty($plagiarism_url)) {
            $submission->plagiarism_url = $plagiarism_url;
            $plagiarism_message .= ' ' . get_string('plagiarism_details', 'mod_devcode', $plagiarism_url);
        }
        
        $submission->score = 0;
        $submission->feedback = $plagiarism_message;
        $submission->timemodified = time();
        
        $DB->update_record('devcode_submissions', $submission);
        
        // Cập nhật điểm vào gradebook
        devcode_update_grades($devcode, $submission->userid);
        
        return true;
    }
    
    return false;
} 