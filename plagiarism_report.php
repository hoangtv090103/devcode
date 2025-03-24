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
 * Displays plagiarism reports for DevCode submissions
 *
 * @package     mod_devcode
 * @copyright   2024 Your Name <your@email.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot . '/mod/devcode/lib.php');
require_once($CFG->dirroot . '/mod/devcode/classes/plagiarism.php');
use core\output\notification;

// Parameters
$id = required_param('id', PARAM_INT); // Course module id
$sid = optional_param('sid', 0, PARAM_INT); // Submission id (if viewing a specific submission)
$uid = optional_param('userid', 0, PARAM_INT); // User id (if viewing all submissions by a user)

// Get necessary records
$cm = get_coursemodule_from_id('devcode', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$devcode = $DB->get_record('devcode', array('id' => $cm->instance), '*', MUST_EXIST);

// Set up the page
require_login($course, true, $cm);
$context = context_module::instance($cm->id);

// Check capabilities - only teachers can view plagiarism reports
require_capability('mod/devcode:manage', $context);

// Set up page parameters
$PAGE->set_url('/mod/devcode/plagiarism_report.php', array('id' => $cm->id, 'sid' => $sid, 'userid' => $uid));
$PAGE->set_title(format_string($devcode->name) . ': ' . get_string('plagiarismreport', 'mod_devcode'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_activity_record($devcode);

// Start output
echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($devcode->name) . ': ' . get_string('plagiarismreport', 'mod_devcode'));

// Check if plagiarism detection is enabled
if (empty($devcode->enable_plagiarism)) {
    echo $OUTPUT->notification(get_string('plagiarismnotenabled', 'mod_devcode'), 'warning');
    echo $OUTPUT->footer();
    exit;
}

$plagiarism = new mod_devcode_plagiarism($devcode);

// If viewing a specific submission
if ($sid) {
    $submission = $DB->get_record('devcode_submissions', array('id' => $sid), '*', MUST_EXIST);
    $student = $DB->get_record('user', array('id' => $submission->userid), '*', MUST_EXIST);
    
    // Show submission details
    echo '<div class="submission-info">';
    echo '<h3>' . get_string('submissioninfo', 'mod_devcode') . '</h3>';
    echo '<p><strong>' . get_string('student', 'mod_devcode') . ':</strong> ' . fullname($student) . '</p>';
    echo '<p><strong>' . get_string('submissiondate', 'mod_devcode') . ':</strong> ' . userdate($submission->timemodified) . '</p>';
    echo '</div>';
    
    // Get plagiarism results
    $results = $DB->get_records('devcode_plagiarism', array('submission_id' => $sid), 'similarity DESC');
    
    if (empty($results)) {
        // If no results are stored yet, run the check
        $checkresults = $plagiarism->check_submission($sid);
        $plagiarism->store_results($sid, $checkresults);
        
        // Refresh the results
        $results = $DB->get_records('devcode_plagiarism', array('submission_id' => $sid), 'similarity DESC');
    }
    
    if (empty($results)) {
        echo $OUTPUT->notification(get_string('noplagiarismfound', 'mod_devcode'), 'info');
    } else {
        // Display similarity results
        echo '<div class="similarity-results">';
        echo '<h3>' . get_string('similarityresults', 'mod_devcode') . '</h3>';
        echo '<p>' . get_string('similaritythresholdinfo', 'mod_devcode', $devcode->similarity_threshold) . '</p>';
        
        echo '<table class="generaltable">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . get_string('student', 'mod_devcode') . '</th>';
        echo '<th>' . get_string('similarity', 'mod_devcode') . '</th>';
        echo '<th>' . get_string('submissiondate', 'mod_devcode') . '</th>';
        echo '<th>' . get_string('actions', 'mod_devcode') . '</th>';
        echo '</tr>';
        echo '</thead>';
        
        echo '<tbody>';
        foreach ($results as $result) {
            $compared_submission = $DB->get_record('devcode_submissions', array('id' => $result->compared_with), '*', MUST_EXIST);
            $compared_student = $DB->get_record('user', array('id' => $compared_submission->userid), '*', MUST_EXIST);
            
            echo '<tr>';
            echo '<td>' . fullname($compared_student) . '</td>';
            
            // Color-coded similarity
            $class = '';
            if ($result->similarity >= 90) {
                $class = 'danger';
            } else if ($result->similarity >= 70) {
                $class = 'warning';
            } else {
                $class = 'info';
            }
            
            echo '<td><span class="badge badge-' . $class . '">' . $result->similarity . '%</span></td>';
            echo '<td>' . userdate($compared_submission->timemodified) . '</td>';
            
            // Action links
            echo '<td>';
            $compareurl = new moodle_url('/mod/devcode/compare_submissions.php', 
                array('id' => $cm->id, 'sid1' => $sid, 'sid2' => $result->compared_with));
            echo '<a href="' . $compareurl . '" class="btn btn-secondary btn-sm">' . 
                get_string('compare', 'mod_devcode') . '</a>';
            echo '</td>';
            
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }
} else if ($uid) {
    // If viewing all submissions by a user
    $student = $DB->get_record('user', array('id' => $uid), '*', MUST_EXIST);
    
    echo '<h3>' . get_string('allsubmissionsfor', 'mod_devcode', fullname($student)) . '</h3>';
    
    $submissions = $DB->get_records('devcode_submissions', 
        array('devcodeid' => $devcode->id, 'userid' => $uid), 'timemodified DESC');
    
    if (empty($submissions)) {
        echo $OUTPUT->notification(get_string('nosubmissionsfound', 'mod_devcode'), 'info');
    } else {
        echo '<table class="generaltable">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . get_string('submissiondate', 'mod_devcode') . '</th>';
        echo '<th>' . get_string('status', 'mod_devcode') . '</th>';
        echo '<th>' . get_string('actions', 'mod_devcode') . '</th>';
        echo '</tr>';
        echo '</thead>';
        
        echo '<tbody>';
        foreach ($submissions as $submission) {
            echo '<tr>';
            echo '<td>' . userdate($submission->timemodified) . '</td>';
            
            // Status
            $statusclass = 'status-' . $submission->status;
            $statustext = get_string('submissionstatus_' . $submission->status, 'mod_devcode');
            echo '<td><div class="' . $statusclass . '">' . $statustext . '</div></td>';
            
            // Action links
            echo '<td>';
            $reporturl = new moodle_url('/mod/devcode/plagiarism_report.php', 
                array('id' => $cm->id, 'sid' => $submission->id));
            echo '<a href="' . $reporturl . '" class="btn btn-secondary btn-sm">' . 
                get_string('plagiarismreport', 'mod_devcode') . '</a>';
            echo '</td>';
            
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
    }
} else {
    // Overview of all submissions with plagiarism issues
    echo '<h3>' . get_string('allplagiarismreports', 'mod_devcode') . '</h3>';
    
    // Get all submissions with plagiarism results above threshold
    $sql = "SELECT s.*, 
                  MAX(p.similarity) as max_similarity, 
                  COUNT(p.id) as matches_count
           FROM {devcode_submissions} s
           JOIN {devcode_plagiarism} p ON s.id = p.submission_id
           WHERE s.devcodeid = :devcodeid
           GROUP BY s.id
           HAVING MAX(p.similarity) >= :threshold
           ORDER BY max_similarity DESC";
    
    $params = array(
        'devcodeid' => $devcode->id,
        'threshold' => $devcode->similarity_threshold
    );
    
    $submissions = $DB->get_records_sql($sql, $params);
    
    if (empty($submissions)) {
        echo $OUTPUT->notification(get_string('noplagiarismdetected', 'mod_devcode'), 'info');
    } else {
        echo '<table class="generaltable">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . get_string('student', 'mod_devcode') . '</th>';
        echo '<th>' . get_string('submissiondate', 'mod_devcode') . '</th>';
        echo '<th>' . get_string('maxsimilarity', 'mod_devcode') . '</th>';
        echo '<th>' . get_string('matchescount', 'mod_devcode') . '</th>';
        echo '<th>' . get_string('actions', 'mod_devcode') . '</th>';
        echo '</tr>';
        echo '</thead>';
        
        echo '<tbody>';
        foreach ($submissions as $submission) {
            $student = $DB->get_record('user', array('id' => $submission->userid), '*', MUST_EXIST);
            
            echo '<tr>';
            echo '<td>' . fullname($student) . '</td>';
            echo '<td>' . userdate($submission->timemodified) . '</td>';
            
            // Color-coded similarity
            $class = '';
            if ($submission->max_similarity >= 90) {
                $class = 'danger';
            } else if ($submission->max_similarity >= 70) {
                $class = 'warning';
            } else {
                $class = 'info';
            }
            
            echo '<td><span class="badge badge-' . $class . '">' . $submission->max_similarity . '%</span></td>';
            echo '<td>' . $submission->matches_count . '</td>';
            
            // Action links
            echo '<td>';
            $reporturl = new moodle_url('/mod/devcode/plagiarism_report.php', 
                array('id' => $cm->id, 'sid' => $submission->id));
            echo '<a href="' . $reporturl . '" class="btn btn-secondary btn-sm">' . 
                get_string('viewdetails', 'mod_devcode') . '</a>';
            echo '</td>';
            
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
    }
}

// Return link
echo '<div class="back-links">';
$submissionsurl = new moodle_url('/mod/devcode/submissions.php', array('id' => $cm->id));
echo '<a href="' . $submissionsurl . '" class="btn btn-secondary">' . 
    get_string('backtosubmissions', 'mod_devcode') . '</a>';
echo '</div>';

// Finish the page
echo $OUTPUT->footer(); 