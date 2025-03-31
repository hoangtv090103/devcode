<?php


/**
 * Handle plagiarism actions (flag/pass)
 *
 * @package     mod_devcode

 */

require('../../config.php');
require_once($CFG->dirroot . '/mod/devcode/lib.php');
require_once($CFG->libdir . '/moodlelib.php'); // For print_error
use core\output\notification;
use context_module;

// Parameters
$id = required_param('id', PARAM_INT); // Course module id
$sid = required_param('sid', PARAM_INT); // Submission id
$action = required_param('action', PARAM_ALPHA); // Action (flag/pass)
$notes = optional_param('notes', '', PARAM_TEXT); // Teacher notes

// Validate session key for form submission
require_sesskey();

// Get necessary records
$cm = get_coursemodule_from_id('devcode', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$devcode = $DB->get_record('devcode', array('id' => $cm->instance), '*', MUST_EXIST);
$submission = $DB->get_record('devcode_submissions', array('id' => $sid), '*', MUST_EXIST);

// Set up the page
require_login($course, true, $cm);
$context = context_module::instance($cm->id);

// Check capabilities - only teachers can flag plagiarism
require_capability('mod/devcode:manage', $context);

// Process the action
$updatedata = new stdClass();
$updatedata->id = $sid;
$updatedata->feedback = $notes;

// Flag as plagiarism or pass
if ($action === 'flag') {
    // Update the submission status and feedback
    $updatedata->status = 'plagiarism';
    
    // Update the plagiarism records to mark them as reviewed and flagged as plagiarism
    $records = $DB->get_records_sql(
        "SELECT p.* FROM {devcode_plagiarism} p 
         WHERE (p.submission1_id = :sid1 OR p.submission2_id = :sid2)",
        array('sid1' => $sid, 'sid2' => $sid)
    );
    
    foreach ($records as $record) {
        $record->reviewed = 1;
        $record->flagged = 1; // Set the field to identify as plagiarism
        $record->timemodified = time();
        $DB->update_record('devcode_plagiarism', $record);
    }
    
    // Set the message
    $message = get_string('submissionflaggedasplagiarism', 'mod_devcode');
    $messagetype = notification::NOTIFY_WARNING;
} else if ($action === 'pass') {
    // Update the submission status and feedback
    $updatedata->status = 'graded';
    
    // Update the plagiarism records to mark them as reviewed and not flagged
    $records = $DB->get_records_sql(
        "SELECT p.* FROM {devcode_plagiarism} p 
         WHERE (p.submission1_id = :sid1 OR p.submission2_id = :sid2)",
        array('sid1' => $sid, 'sid2' => $sid)
    );
    
    foreach ($records as $record) {
        $record->reviewed = 1;
        $record->flagged = 0; // Not plagiarism
        $record->timemodified = time();
        $DB->update_record('devcode_plagiarism', $record);
    }
    
    // Process the submission via API
    if (!isset($CFG->devcode) || !isset($CFG->devcode['api_base_url'])) {
        require_once(dirname(__FILE__) . '/config.php');
    }
    
    $api_base_url = $CFG->devcode['api_base_url'];
    $api_endpoint = isset($CFG->devcode['api_endpoints']['submissions']) 
        ? $CFG->devcode['api_endpoints']['submissions'] 
        : '/api/v1/submissions/';
        
    $api_url = $api_base_url . $api_endpoint . $sid . '/process';
    
    // Get the testcases for this submission's assignment
    $testcases = $DB->get_records('devcode_testcases', array('devcodeid' => $devcode->id));
    $testcasesFormatted = array();
    
    foreach ($testcases as $testcase) {
        $testcasesFormatted[] = array(
            'id' => $testcase->id,
            'input' => $testcase->input,
            'output' => $testcase->output,
            'points' => (float)$testcase->points,
            'time_limit' => (int)$testcase->time_limit
        );
    }
    
    // Prepare request body with relevant data
    $requestData = [
        'submission_id' => $sid,
        'action' => 'process',
        'reviewed' => true,
        'status' => 'graded',
        'testcases' => $testcasesFormatted // Required field according to the Pydantic schema
    ];
    
    $response = devcode_api_request($api_url, 'POST', $requestData);
    
    if (isset($response['error'])) {
        // Handle API error
        debugging('API error when processing submission: ' . print_r($response, true), DEBUG_DEVELOPER);
        
        // Use a default error message if the string is missing
        $errorMessage = get_string_manager()->string_exists('submissionprocesserror', 'mod_devcode') 
            ? get_string('submissionprocesserror', 'mod_devcode') 
            : 'Error occurred while processing the submission';
            
        redirect($CFG->wwwroot . '/mod/devcode/plagiarism_report.php?id=' . $id . '&sid=' . $sid,
            $errorMessage, null, notification::NOTIFY_ERROR);
        exit;
    }
    
    // Set the message
    $message = get_string('submissionmarkedaspassed', 'mod_devcode');
    $messagetype = notification::NOTIFY_SUCCESS;
} else {
    // Invalid action
    print_error('invalidaction', 'mod_devcode');
}

// Update the submission
$DB->update_record('devcode_submissions', $updatedata);

// Comment out event triggering code until the event classes are properly set up
/*
// Trigger an event for the action
$params = array(
    'context' => $context,
    'objectid' => $sid,
    'other' => array(
        'action' => $action,
        'devcodeid' => $devcode->id
    )
);

if ($action === 'flag') {
    $event = \mod_devcode\event\submission_flagged_plagiarism::create($params);
} else {
    $event = \mod_devcode\event\submission_passed_plagiarism::create($params);
}
$event->trigger();
*/

// Redirect back to the plagiarism report
$returnurl = $CFG->wwwroot . '/mod/devcode/plagiarism_report.php?id=' . $id . '&sid=' . $sid;
redirect($returnurl, $message, null, $messagetype); 