<?php


/**
 * AJAX handler for checking submission status
 *
 * @package     mod_devcode

 */

require_once('../../../config.php');
require_once(dirname(__FILE__) . '/../lib.php');

// Required for moodle_exception and context_module
require_once($CFG->libdir . '/exceptionlib.php');
require_once($CFG->libdir . '/accesslib.php');

// Ensure AJAX request
defined('AJAX_SCRIPT') || define('AJAX_SCRIPT', true);

// Parameters
$id = required_param('id', PARAM_INT); // Course Module ID
$submissionid = required_param('submission', PARAM_INT); // Submission ID

// Return data structure
$response = array(
    'success' => false,
    'status' => '',
    'error' => ''
);

try {
    // Get course module
    $cm = get_coursemodule_from_id('devcode', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $devcode = $DB->get_record('devcode', array('id' => $cm->instance), '*', MUST_EXIST);
    
    // Check login and permissions
    require_login($course, false, $cm);
    $context = \context_module::instance($cm->id);
    
    // Get submission
    $submission = $DB->get_record('devcode_submissions', array('id' => $submissionid));
    if (!$submission) {
        throw new \moodle_exception('submissionnotfound', 'devcode');
    }
    
    // Check if user has permission to view this submission
    if ($submission->userid != $USER->id && !has_capability('mod/devcode:viewallsubmissions', $context)) {
        throw new \moodle_exception('nopermissions', 'error', '', 'view submission');
    }
    
    // Check submission status with API if still processing
    if ($submission->status == 'processing' || $submission->status == 'submitted') {
        devcode_check_submission_status($submissionid);
        
        // Reload submission to get updated status
        $submission = $DB->get_record('devcode_submissions', array('id' => $submissionid));
    }
    
    // Return updated status
    $response['success'] = true;
    $response['status'] = $submission->status;
    
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

// Send JSON response
echo json_encode($response);
die(); 