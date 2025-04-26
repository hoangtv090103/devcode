<?php


/**
 * Handle plagiarism actions (flag/pass)
 *
 * @package     mod_devcode

 */

require('../../config.php'); // This already includes moodle_url class
require_once($CFG->dirroot . '/mod/devcode/lib.php');
require_once($CFG->dirroot . '/mod/devcode/judge0_api.php');
require_once($CFG->libdir . '/moodlelib.php'); // For print_error
use core\output\notification;
use core\context\module as context_module;

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
    
    // Get the testcases for this submission's assignment
    $testcases = $DB->get_records('devcode_testcases', array('devcodeid' => $devcode->id));
    
    // Process the submission through judge0_api functions
    require_once(dirname(__FILE__) . '/config.php');
    
    // Use Judge0 API to grade the submission
    if (!empty($testcases)) {
        // Update submission with language_id if using 'language' field
        if (!isset($submission->language_id) && isset($submission->language)) {
            $submission->language_id = $submission->language;
        }
        
        $result = devcode_grade_with_judge0($submission, $devcode, $context);
    
        if (!$result) {
            // Handle grading error
            debugging('Error occurred while grading the submission', DEBUG_DEVELOPER);
        
        // Use a default error message if the string is missing
        $errorMessage = get_string_manager()->string_exists('submissionprocesserror', 'mod_devcode') 
            ? get_string('submissionprocesserror', 'mod_devcode') 
            : 'Error occurred while processing the submission';
            
            // Use string concatenation for URL instead of moodle_url object
            $redirecturl = $CFG->wwwroot . '/mod/devcode/plagiarism_report.php?id=' . $id . '&sid=' . $sid;
            redirect($redirecturl, $errorMessage, null, notification::NOTIFY_ERROR);
        exit;
        }
    }
    
    // Set the message
    $message = get_string('submissionmarkedaspassed', 'mod_devcode');
    $messagetype = notification::NOTIFY_SUCCESS;
} else {
    // Invalid action - use redirect with error notification instead of print_error
    $errorMessage = get_string_manager()->string_exists('invalidaction', 'mod_devcode') 
        ? get_string('invalidaction', 'mod_devcode') 
        : 'Invalid action specified';
    
    // Use string concatenation for URL instead of moodle_url object
    $redirecturl = $CFG->wwwroot . '/mod/devcode/plagiarism_report.php?id=' . $id . '&sid=' . $sid;
    redirect($redirecturl, $errorMessage, null, notification::NOTIFY_ERROR);
    exit;
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

// Redirect back to the plagiarism report - use string concatenation for URL
$returnurl = $CFG->wwwroot . '/mod/devcode/plagiarism_report.php?id=' . $id . '&sid=' . $sid;
redirect($returnurl, $message, null, $messagetype); 