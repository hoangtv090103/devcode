<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * AJAX endpoint for AI services
 *
 * @package     mod_devcode
 * @copyright   2025 Your Name <your.email@example.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once('../../../config.php');
require_once($CFG->dirroot . '/mod/devcode/lib.php');

// Get parameters
$cmid = required_param('cmid', PARAM_INT);
$sid = required_param('sid', PARAM_INT);
$action = required_param('action', PARAM_ALPHA);
$sesskey = required_param('sesskey', PARAM_RAW);

// Validate session
if (!confirm_sesskey($sesskey)) {
    $error = array('error' => get_string('invalidsesskey', 'error'));
    echo json_encode($error);
    die;
}

// Get context
$cm = get_coursemodule_from_id('devcode', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$devcode = $DB->get_record('devcode', array('id' => $cm->instance), '*', MUST_EXIST);
$context = context_module::instance($cm->id);

// Check login
require_login($course, false, $cm);

// Check capability
require_capability('mod/devcode:submit', $context);

// Get submission
$submission = $DB->get_record('devcode_submissions', array('id' => $sid), '*', MUST_EXIST);

// Check ownership or manage capability
if ($submission->userid != $USER->id && !has_capability('mod/devcode:manage', $context)) {
    $error = array('error' => get_string('nopermissions', 'error', get_string('viewsubmission', 'devcode')));
    echo json_encode($error);
    die;
}

// Initialize AI service
$ai_service = new \mod_devcode\ai\service($devcode, $submission, $USER, $cm);

// Response array
$response = array(
    'success' => false,
    'message' => '',
    'content' => ''
);

try {
    switch ($action) {
        case 'explain':
            // Get error message to explain
            $error_message = $submission->feedback;
            
            if (empty($error_message)) {
                // If no feedback in submission, check for error messages in test results
                $test_results = $DB->get_records('devcode_submission_results', array('submissionid' => $submission->id));
                foreach ($test_results as $result) {
                    if (!empty($result->error_message)) {
                        $error_message = $result->error_message;
                        break;
                    }
                }
            }
            
            if (empty($error_message)) {
                throw new moodle_exception('noerrortoexplain', 'mod_devcode');
            }
            
            $explanation = $ai_service->get_error_explanation($error_message);
            $response['content'] = $explanation;
            $response['success'] = true;
            break;
            
        case 'hint':
            $hint = $ai_service->get_hint();
            $response['content'] = $hint;
            $response['success'] = true;
            break;
            
        case 'improve':
            $suggestions = $ai_service->get_improvement_suggestions();
            $response['content'] = $suggestions;
            $response['success'] = true;
            break;
            
        default:
            throw new moodle_exception('invalidaction', 'mod_devcode');
    }
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

// Add remaining usage info
$response['remaining'] = array(
    'explain' => $ai_service->get_remaining_usage('explain'),
    'hint' => $ai_service->get_remaining_usage('hint'),
    'improve' => $ai_service->get_remaining_usage('improve')
);

// Send JSON response
echo json_encode($response);
die; 