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
 * View all submissions for a devcode assignment
 *
 * @package     mod_devcode
 * @copyright   2024 Your Name <your@email.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot . '/mod/devcode/lib.php');

// Course module id
$id = required_param('id', PARAM_INT);
$group = optional_param('group', 0, PARAM_INT); // Group ID

// Get necessary records
$cm = get_coursemodule_from_id('devcode', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$devcode = $DB->get_record('devcode', array('id' => $cm->instance), '*', MUST_EXIST);

// Set up the page
require_login($course, true, $cm);

// Set up the page
$PAGE->set_url('/mod/devcode/submissions.php', array('id' => $cm->id, 'group' => $group));
$PAGE->set_title(format_string($devcode->name) . ': ' . get_string('allsubmissions', 'mod_devcode'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_activity_record($devcode);

// Check capabilities - only teachers and managers can view all submissions
require_capability('mod/devcode:manage', $PAGE->context);

// Add navigation node
$PAGE->navbar->add(get_string('allsubmissions', 'mod_devcode'));

// Get all students who can submit to this assignment
$students = get_enrolled_users($PAGE->context, 'mod/devcode:submit', $group, 'u.*', 'u.lastname, u.firstname');

// Start of output
echo $OUTPUT->header();

// Display assignment info
echo $OUTPUT->heading(format_string($devcode->name), 2);

// Display assignment information
echo '<div class="assignment-info">';
echo '<h3>' . get_string('submissionsfor', 'mod_devcode', format_string($devcode->name)) . '</h3>';

// Display due date if it exists
if (!empty($devcode->duedate)) {
    $duedate = userdate($devcode->duedate);
    echo '<p class="duedate">' . get_string('duedate', 'mod_devcode') . ': ' . $duedate . '</p>';
}
echo '</div>';

// Get groups if the course has groups enabled
$groupmode = groups_get_activity_groupmode($cm);
if ($groupmode) {
    groups_print_activity_menu($cm, $PAGE->url);
}

// Check if there are students enrolled
if (empty($students)) {
    echo $OUTPUT->notification(get_string('nostudentsyet', 'mod_devcode'), 'notifymessage');
} else {
    // Create table
    echo '<table class="generaltable submissions-table">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>' . get_string('fullname') . '</th>';
    echo '<th>' . get_string('submissions', 'mod_devcode') . '</th>';
    echo '<th>' . get_string('status', 'mod_devcode') . '</th>';
    echo '<th>' . get_string('grade', 'mod_devcode') . '</th>';
    echo '<th>' . get_string('timemodified', 'mod_devcode') . '</th>';
    echo '<th>' . get_string('actions', 'mod_devcode') . '</th>';
    echo '</tr>';
    echo '</thead>';
    
    echo '<tbody>';
    // Table data
    foreach ($students as $student) {
        // Get all submissions from this student, sorted by most recent first
        $submissions = $DB->get_records('devcode_submissions', 
            array('devcodeid' => $devcode->id, 'userid' => $student->id), 
            'timemodified DESC');
        
        // Get the latest submission (if any)
        $latestsubmission = reset($submissions);
        
        echo '<tr>';
        
        // Student name with profile link
        $studenturl = $CFG->wwwroot . '/user/view.php?id=' . $student->id . '&course=' . $course->id;
        echo '<td><a href="' . $studenturl . '">' . fullname($student) . '</a></td>';
        
        if ($latestsubmission) {
            // Submission count
            $submissioncount = count($submissions);
            $submissioncountdisplay = ($submissioncount > 1 ? 
                get_string('submissionsmultiple', 'mod_devcode', $submissioncount) : 
                get_string('submission', 'mod_devcode'));
            echo '<td>' . $submissioncountdisplay . '</td>';
            
            // Status
            $statusclass = 'status-' . $latestsubmission->status;
            $statustext = get_string('submissionstatus_' . $latestsubmission->status, 'mod_devcode', userdate($latestsubmission->timemodified));
            echo '<td><div class="' . $statusclass . '">' . $statustext . '</div></td>';
            
            // Grade
            if (isset($latestsubmission->score)) {
                echo '<td>' . $latestsubmission->score . '/10</td>';
            } else {
                echo '<td>-</td>';
            }
            
            // Date
            echo '<td>' . userdate($latestsubmission->timemodified) . '</td>';
            
            // Actions
            echo '<td class="submission-actions">';
            
            // View button
            $viewurl = $CFG->wwwroot . '/mod/devcode/view_result.php?id=' . $cm->id . '&sid=' . $latestsubmission->id . '&userid=' . $student->id;
            echo '<a href="' . $viewurl . '" class="btn btn-secondary btn-sm">' . get_string('viewsubmission', 'mod_devcode') . '</a>';
            
            // Grade button (if needed and ungraded)
            if ($latestsubmission->status != 'graded') {
                $gradeurl = $CFG->wwwroot . '/mod/devcode/grade.php?id=' . $cm->id . '&sid=' . $latestsubmission->id . '&userid=' . $student->id;
                echo ' <a href="' . $gradeurl . '" class="btn btn-primary btn-sm">' . get_string('grade', 'mod_devcode') . '</a>';
            }
            
            echo '</td>';
        } else {
            // No submissions yet
            echo '<td>0 ' . get_string('submission', 'mod_devcode') . '</td>';
            echo '<td><div class="status-notsubmitted">' . get_string('submissionstatus_notsubmitted', 'mod_devcode') . '</div></td>';
            echo '<td>-</td>';
            echo '<td>-</td>';
            echo '<td></td>';
        }
        
        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';
}

// Return to main page button
echo '<div class="back-link">';
$backurl = $CFG->wwwroot . '/mod/devcode/view.php?id=' . $cm->id;
echo '<a href="' . $backurl . '" class="btn btn-secondary">' . get_string('back', 'mod_devcode') . '</a>';
echo '</div>';

// Finish the page
echo $OUTPUT->footer(); 