<?php


/**
 * Display leaderboard for DevCode assignments in a course
 *
 * @package     mod_devcode

 */

require('../../config.php');
require_once($CFG->dirroot . '/mod/devcode/lib.php');

// Parameters
$id = required_param('id', PARAM_INT); // Course Module ID
$courseid = optional_param('course', 0, PARAM_INT); // Course ID (if viewing course-wide leaderboard)

// Get records and check access
if ($id) {
    $cm = get_coursemodule_from_id('devcode', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $devcode = $DB->get_record('devcode', array('id' => $cm->instance), '*', MUST_EXIST);
    
    // Set up page
    $PAGE->set_url('/mod/devcode/leaderboard.php', array('id' => $cm->id));
    $PAGE->set_title(format_string($devcode->name) . ': ' . get_string('leaderboard', 'mod_devcode'));
    $PAGE->set_heading(format_string($course->fullname));
    $PAGE->set_activity_record($devcode);
    
    // Check login
    require_login($course, true, $cm);
    $context = context_module::instance($cm->id);
    
    // Set scope to current assignment only
    $single_assignment = true;
} else if ($courseid) {
    $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
    
    // Set up page
    $PAGE->set_url('/mod/devcode/leaderboard.php', array('course' => $courseid));
    $PAGE->set_title(get_string('courselevelleaderboard', 'mod_devcode'));
    $PAGE->set_heading(format_string($course->fullname));
    
    // Check login
    require_login($course);
    $context = context_course::instance($courseid);
    
    // Set scope to all assignments in course
    $single_assignment = false;
} else {
    throw new moodle_exception('invalidparameter');
}

// Start output
echo $OUTPUT->header();

if ($single_assignment) {
    echo $OUTPUT->heading(format_string($devcode->name) . ': ' . get_string('leaderboard', 'mod_devcode'));
} else {
    echo $OUTPUT->heading(get_string('courselevelleaderboard', 'mod_devcode'));
}

// Get the users who can submit to this assignment
$students = get_enrolled_users($context, 'mod/devcode:submit', 0, 'u.*', 'u.lastname, u.firstname');

if (empty($students)) {
    echo $OUTPUT->notification(get_string('nostudentsyet', 'mod_devcode'), 'notifymessage');
} else {
    // Get leaderboard data
    $leaderboard = array();
    
    if ($single_assignment) {
        // Get top scores for this assignment
        $sql = "SELECT s.userid, u.firstname, u.lastname, MAX(s.score) as best_score, 
                       MIN(CASE WHEN s.status = 'graded' THEN s.timemodified ELSE NULL END) as first_completion_time,
                       COUNT(s.id) as attempt_count
                FROM {devcode_submissions} s
                JOIN {user} u ON s.userid = u.id
                WHERE s.devcodeid = :devcodeid AND s.status = 'graded'
                GROUP BY s.userid, u.firstname, u.lastname
                ORDER BY best_score DESC, first_completion_time ASC";
        
        $params = array('devcodeid' => $devcode->id);
        $leaderboard = $DB->get_records_sql($sql, $params);
    } else {
        // Get scores across all assignments in the course
        $sql = "SELECT s.userid, u.firstname, u.lastname, 
                       SUM(s.score) as total_score,
                       COUNT(DISTINCT d.id) as assignments_completed,
                       COUNT(s.id) as total_attempts
                FROM {devcode_submissions} s
                JOIN {devcode} d ON s.devcodeid = d.id
                JOIN {course_modules} cm ON cm.instance = d.id
                JOIN {modules} m ON m.id = cm.module
                JOIN {user} u ON s.userid = u.id
                WHERE cm.course = :courseid 
                  AND m.name = 'devcode'
                  AND s.status = 'graded'
                GROUP BY s.userid, u.firstname, u.lastname
                ORDER BY total_score DESC, assignments_completed DESC";
        
        $params = array('courseid' => $course->id);
        $leaderboard = $DB->get_records_sql($sql, $params);
    }
    
    // Display leaderboard table
    echo '<div class="leaderboard-container">';
    
    // Empty leaderboard
    if (empty($leaderboard)) {
        echo $OUTPUT->notification(get_string('noleaderboarddata', 'mod_devcode'), 'info');
    } else {
        // Show table
        echo '<table class="generaltable leaderboard-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th class="rank-col">' . get_string('rank', 'mod_devcode') . '</th>';
        echo '<th>' . get_string('student', 'mod_devcode') . '</th>';
        
        if ($single_assignment) {
            echo '<th>' . get_string('bestscore', 'mod_devcode') . '</th>';
            echo '<th>' . get_string('firstcompletion', 'mod_devcode') . '</th>';
            echo '<th>' . get_string('attemptcount', 'mod_devcode') . '</th>';
        } else {
            echo '<th>' . get_string('totalscore', 'mod_devcode') . '</th>';
            echo '<th>' . get_string('assignmentscompleted', 'mod_devcode') . '</th>';
            echo '<th>' . get_string('totalattempts', 'mod_devcode') . '</th>';
        }
        
        echo '</tr>';
        echo '</thead>';
        
        echo '<tbody>';
        
        $rank = 0;
        $lastScore = PHP_INT_MAX;
        $sameRankCount = 0;
        
        foreach ($leaderboard as $entry) {
            // Calculate rank (handle ties)
            $currentScore = $single_assignment ? $entry->best_score : $entry->total_score;
            
            if ($currentScore < $lastScore) {
                $rank += 1 + $sameRankCount;
                $sameRankCount = 0;
            } else {
                $sameRankCount++;
            }
            
            $lastScore = $currentScore;
            
            // Apply styling for top 3
            $rowClass = '';
            if ($rank == 1) {
                $rowClass = 'first-place';
            } else if ($rank == 2) {
                $rowClass = 'second-place';
            } else if ($rank == 3) {
                $rowClass = 'third-place';
            }
            
            echo '<tr class="' . $rowClass . '">';
            
            // Rank with medal for top 3
            echo '<td class="rank-col">';
            if ($rank == 1) {
                echo '<span class="medal gold-medal">🥇</span> ';
            } else if ($rank == 2) {
                echo '<span class="medal silver-medal">🥈</span> ';
            } else if ($rank == 3) {
                echo '<span class="medal bronze-medal">🥉</span> ';
            }
            echo $rank;
            echo '</td>';
            
            // Student name
            echo '<td>' . fullname($entry) . '</td>';
            
            if ($single_assignment) {
                // Best score
                echo '<td>' . $entry->best_score . '</td>';
                
                // First completion time
                echo '<td>' . ($entry->first_completion_time ? userdate($entry->first_completion_time) : '-') . '</td>';
                
                // Attempt count
                echo '<td>' . $entry->attempt_count . '</td>';
            } else {
                // Total score
                echo '<td>' . $entry->total_score . '</td>';
                
                // Assignments completed
                echo '<td>' . $entry->assignments_completed . '</td>';
                
                // Total attempts
                echo '<td>' . $entry->total_attempts . '</td>';
            }
            
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
    }
    echo '</div>';
}

// Add navigation links
echo '<div class="leaderboard-navigation">';

// View assignment or course leaderboard
if ($single_assignment) {
    // Link to course leaderboard
    echo '<a href="' . $CFG->wwwroot . '/mod/devcode/leaderboard.php?course=' . $course->id . '" class="btn btn-secondary">' . 
        get_string('viewcourseleaderboard', 'mod_devcode') . '</a> ';
    
    // Back to assignment
    echo '<a href="' . $CFG->wwwroot . '/mod/devcode/view.php?id=' . $cm->id . '" class="btn btn-secondary">' . 
        get_string('back', 'mod_devcode') . '</a>';
} else {
    // Back to course
    echo '<a href="' . $CFG->wwwroot . '/course/view.php?id=' . $course->id . '" class="btn btn-secondary">' . 
        get_string('backtocourse', 'mod_devcode') . '</a>';
}

echo '</div>';

// Add some CSS styles for the leaderboard
echo '
<style>
    .leaderboard-table .rank-col {
        width: 80px;
        text-align: center;
    }
    .leaderboard-table .medal {
        font-size: 1.2em;
        margin-right: 5px;
    }
    .leaderboard-table .first-place {
        background-color: rgba(255, 215, 0, 0.1);
    }
    .leaderboard-table .second-place {
        background-color: rgba(192, 192, 192, 0.1);
    }
    .leaderboard-table .third-place {
        background-color: rgba(205, 127, 50, 0.1);
    }
    .leaderboard-navigation {
        margin-top: 20px;
    }
</style>
';

// Finish the page
echo $OUTPUT->footer(); 