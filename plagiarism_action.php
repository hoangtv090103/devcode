<?php
// TODO: Chỉnh sửa lại action của giáo viên
/**
 * Handle plagiarism actions (flag/pass)
 *
 * @package     mod_devcode
 * @copyright   2024 Your Name <your@email.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot . '/mod/devcode/lib.php');

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
    
    // Update the plagiarism records to mark them as reviewed
    $DB->set_field('devcode_plagiarism', 'reviewed', 1, 
        array('submission1_id' => $sid));
    $DB->set_field('devcode_plagiarism', 'reviewed', 1, 
        array('submission2_id' => $sid));
    
    // Set the message
    $message = get_string('submissionflaggedasplagiarism', 'mod_devcode');
    $messagetype = \core\output\notification::NOTIFY_WARNING;
} else if ($action === 'pass') {
    // Update the submission status and feedback
    $updatedata->status = 'graded';
    
    // Update the plagiarism records to mark them as reviewed and not flagged
    $DB->set_field('devcode_plagiarism', 'reviewed', 1, 
        array('submission1_id' => $sid));
    $DB->set_field('devcode_plagiarism', 'reviewed', 1, 
        array('submission2_id' => $sid));
    $DB->set_field('devcode_plagiarism', 'flagged', 0, 
        array('submission1_id' => $sid));
    $DB->set_field('devcode_plagiarism', 'flagged', 0, 
        array('submission2_id' => $sid));
    
    // Set the message
    $message = get_string('submissionmarkedaspassed', 'mod_devcode');
    $messagetype = \core\output\notification::NOTIFY_SUCCESS;
} else {
    // Invalid action
    print_error('invalidaction', 'mod_devcode');
}

// Update the submission
$DB->update_record('devcode_submissions', $updatedata);

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

// Redirect back to the plagiarism report
$returnurl = $CFG->wwwroot . '/mod/devcode/plagiarism_report.php?id=' . $id . '&sid=' . $sid;
redirect($returnurl, $message, null, $messagetype); 