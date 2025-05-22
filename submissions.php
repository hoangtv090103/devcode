<?php


/**
 * View all submissions for a devcode assignment
 *
 * @package     mod_devcode

 */

require('../../config.php');
require_once($CFG->dirroot . '/mod/devcode/lib.php');

// Required imports
use \core\output\html_writer;

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
            // --- Apply color logic similar to view_result.php ---
            $status_value = $latestsubmission->status;
            if (is_numeric($status_value)) {
                $status_map = [
                    1 => 'accepted', 2 => 'wrong_answer', 3 => 'time_limit', 4 => 'memory_limit',
                    5 => 'compile_error', 6 => 'partially_accepted', 7 => 'runtime_error',
                    8 => 'pending', 9 => 'processing'
                ];
                $status_value = $status_map[$status_value] ?? 'unknown_status';
            }

            $status_class = 'badge ';
            switch ($status_value) {
                case 'accepted':
                case 'graded':
                    $status_class .= 'badge-success'; break;
                case 'pending':
                case 'processing':
                    $status_class .= 'badge-secondary'; break;
                case 'partially_accepted':
                    $status_class .= 'badge-info'; break;
                case 'plagiarism':
                case 'plagiarism_detected':
                    $status_class .= 'badge-warning'; break;
                case 'wrong_answer':
                case 'time_limit':
                case 'memory_limit':
                case 'compile_error':
                case 'runtime_error':
                case 'failed':
                case 'error':
                    $status_class .= 'badge-danger'; break;
                default:
                    $status_class .= 'badge-light';
            }
            
            $status_string_id = 'submissionstatus_' . $status_value;
            if (get_string_manager()->string_exists($status_string_id, 'devcode')) {
                $statustext = get_string($status_string_id, 'devcode');
            } else if (get_string_manager()->string_exists($status_value, 'devcode')) {
                 $statustext = get_string($status_value, 'devcode');
            } else {
                 $statustext = get_string('submissionstatus_error', 'devcode');
            }
            // --- End color logic ---
            
            // Output status using the badge class
            echo '<td>' . html_writer::tag('span', $statustext, array('class' => $status_class)) . '</td>';
            
            // Grade - Show score/total points from test cases
            if (isset($latestsubmission->score)) {
                // Get total points possible from all test cases
                $total_points = $DB->get_field_sql(
                    "SELECT SUM(points) FROM {devcode_testcases} WHERE devcodeid = ?",
                    array($devcode->id)
                );
                
                // Default to 10 if no test cases with points are found
                $total_points = $total_points ? $total_points : 10;
                
                echo '<td>' . $latestsubmission->score . '/' . $total_points . '</td>';
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
            /* // Temporarily hidden as requested
            if ($latestsubmission->status != 'graded') {
                $gradeurl = $CFG->wwwroot . '/mod/devcode/grade.php?id=' . $cm->id . '&sid=' . $latestsubmission->id . '&userid=' . $student->id;
                echo ' <a href="' . $gradeurl . '" class="btn btn-primary btn-sm">' . get_string('grade', 'mod_devcode') . '</a>';
            }
            */
            
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