<?php
/**
 * AJAX handler for checking submission status
 *
 * @package     mod_devcode
 */

require_once('../../../config.php');
require_once(dirname(__FILE__) . '/../lib.php');

// Required for moodle_exception and context
require_once($CFG->libdir . '/exceptionlib.php');
require_once($CFG->libdir . '/accesslib.php');

// Ensure AJAX request
defined('AJAX_SCRIPT') || define('AJAX_SCRIPT', true);

// Parameters
$submissionid = required_param('submissionid', PARAM_INT); // Submission ID
$sesskey = required_param('sesskey', PARAM_RAW); // Session key for security

// Verify session key
if (!confirm_sesskey($sesskey)) {
    $response = ['error' => 'Invalid session key'];
    echo json_encode($response);
    die();
}

// Return data structure
$response = [
    'success' => false,
    'status' => '',
    'score' => null,
    'feedback' => '',
    'error' => ''
];

try {
    // Get submission
    $submission = $DB->get_record('devcode_submissions', ['id' => $submissionid]);
    if (!$submission) {
        throw new \core\exception\moodle_exception('submissionnotfound', 'devcode');
    }
    
    // Get course module
    $devcode = $DB->get_record('devcode', ['id' => $submission->devcodeid], '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('devcode', $devcode->id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    
    // Check login and permissions
    require_login($course, false, $cm);
    $context = \core\context\module::instance($cm->id);
    
    // Check if user has permission to view this submission
    $canview = $submission->userid == $USER->id || has_capability('mod/devcode:viewallsubmissions', $context);
    if (!$canview) {
        throw new \core\exception\moodle_exception('nopermissions', 'error', '', 'view submission');
    }
    
    // If submission is still processing, we can check with the API for updates
    if ($submission->status == 'processing') {
        // Make an API request to check the submission status
        $api_base = $CFG->devcode['api_base_url'];
        $submissions_endpoint = $CFG->devcode['api_endpoints']['submissions'];
        $status_url = $api_base . $submissions_endpoint . $submissionid;
        
        $api_response = devcode_api_request($status_url, 'GET');
        
        if ($api_response && !isset($api_response['error'])) {
            // If API returns a different status than what we have stored, update the database
            if (isset($api_response['status']) && $api_response['status'] !== $submission->status) {
                $submission->status = $api_response['status'];
                
                if (isset($api_response['score'])) {
                    $submission->score = $api_response['score'];
                }
                
                if (isset($api_response['feedback'])) {
                    $submission->feedback = $api_response['feedback'];
                }
                
                $submission->timemodified = time();
                $DB->update_record('devcode_submissions', $submission);
            }
        }
    }
    
    // Return updated status
    $response['success'] = true;
    $response['status'] = $submission->status;
    $response['score'] = $submission->score;
    $response['feedback'] = $submission->feedback;
    
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

// Ensure the response does not contain binary data that would cause JSON encoding issues
if (isset($response['feedback']) && !mb_check_encoding($response['feedback'], 'UTF-8')) {
    $response['feedback'] = mb_convert_encoding($response['feedback'], 'UTF-8', 'UTF-8, ASCII');
    // Remove any remaining non-printable characters
    $response['feedback'] = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $response['feedback']);
}

// Send JSON response
header('Content-Type: application/json');
echo json_encode($response);
die(); 