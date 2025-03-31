<?php


/**
 * Student dashboard for DevCode submissions
 *
 * @package     mod_devcode

 */

require('../../config.php');
require_once($CFG->dirroot . '/mod/devcode/lib.php');

// Parameters
$id = optional_param('id', 0, PARAM_INT); // Course Module ID
$courseid = optional_param('course', 0, PARAM_INT); // Course ID

// Set up page based on parameters
if ($id) {
    $cm = get_coursemodule_from_id('devcode', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $devcode = $DB->get_record('devcode', array('id' => $cm->instance), '*', MUST_EXIST);
    
    // Page setup
    $PAGE->set_url('/mod/devcode/student_dashboard.php', array('id' => $cm->id));
    $PAGE->set_title(format_string($devcode->name) . ': ' . get_string('studentdashboard', 'mod_devcode'));
    $PAGE->set_heading(format_string($course->fullname));
    $PAGE->set_activity_record($devcode);
    
    // Check login
    require_login($course, true, $cm);
    $context = context_module::instance($cm->id);
    
    // View only this assignment's data
    $single_assignment = true;
} else if ($courseid) {
    $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
    
    // Page setup
    $PAGE->set_url('/mod/devcode/student_dashboard.php', array('course' => $courseid));
    $PAGE->set_title(get_string('coursestudentdashboard', 'mod_devcode'));
    $PAGE->set_heading(format_string($course->fullname));
    
    // Check login
    require_login($course);
    $context = context_course::instance($courseid);
    
    // View all assignments in the course
    $single_assignment = false;
} else {
    throw new moodle_exception('invalidparameter');
}

// Only students can view their own dashboard
require_capability('mod/devcode:submit', $context);

// Required JS for charts
$PAGE->requires->js_call_amd('mod_devcode/student_charts', 'init');

// Start output
echo $OUTPUT->header();

if ($single_assignment) {
    echo $OUTPUT->heading(format_string($devcode->name) . ': ' . get_string('studentdashboard', 'mod_devcode'));
} else {
    echo $OUTPUT->heading(get_string('coursestudentdashboard', 'mod_devcode'));
}

// Get the current user's submissions
$userid = $USER->id;

// Get submission data
$submissions = array();
$assignment_list = array();
$chart_data = array();

if ($single_assignment) {
    // Get submissions for this assignment
    $submissions = $DB->get_records('devcode_submissions', 
        array('devcodeid' => $devcode->id, 'userid' => $userid), 
        'timemodified DESC');
    
    if (empty($submissions)) {
        echo $OUTPUT->notification(get_string('nosubmissionsyet', 'mod_devcode'), 'info');
    } else {
        // Prepare data for charts
        $attempt_dates = array();
        $scores = array();
        
        foreach ($submissions as $submission) {
            $attempt_dates[] = userdate($submission->timemodified, get_string('strftimedatetimeshort', 'core_langconfig'));
            $scores[] = isset($submission->score) ? (int)$submission->score : 0;
        }
        
        // Use JS to render charts
        $chart_data = array(
            'labels' => array_reverse($attempt_dates),
            'scores' => array_reverse($scores),
            'assignmentName' => format_string($devcode->name)
        );
    }
} else {
    // Get all DevCode assignments in this course
    $modinfo = get_fast_modinfo($course);
    $cms = $modinfo->get_instances_of('devcode');
    
    if (empty($cms)) {
        echo $OUTPUT->notification(get_string('nodevcodefound', 'mod_devcode'), 'info');
    } else {
        // Get submissions data for all assignments
        $assignment_list = array();
        $best_scores = array();
        $completion_dates = array();
        $submission_counts = array();
        
        foreach ($cms as $mod) {
            if (!$mod->uservisible) {
                continue;
            }
            
            $assignment = $DB->get_record('devcode', array('id' => $mod->instance), '*', MUST_EXIST);
            $assignment_list[$assignment->id] = format_string($assignment->name);
            
            // Get user's submissions for this assignment
            $user_submissions = $DB->get_records('devcode_submissions', 
                array('devcodeid' => $assignment->id, 'userid' => $userid), 
                'timemodified DESC');
            
            // Get best score and completion status
            $best_score = 0;
            $is_completed = false;
            $completion_date = 0;
            
            if (!empty($user_submissions)) {
                foreach ($user_submissions as $sub) {
                    if (isset($sub->score) && $sub->score > $best_score) {
                        $best_score = $sub->score;
                        if ($sub->status === 'graded') {
                            $is_completed = true;
                            $completion_date = $sub->timemodified;
                        }
                    }
                }
                
                $best_scores[$assignment->id] = $best_score;
                $completion_dates[$assignment->id] = $is_completed ? userdate($completion_date, get_string('strftimedatetimeshort', 'core_langconfig')) : '';
                $submission_counts[$assignment->id] = count($user_submissions);
            } else {
                $best_scores[$assignment->id] = 0;
                $completion_dates[$assignment->id] = '';
                $submission_counts[$assignment->id] = 0;
            }
        }
        
        // Prepare data for charts
        $chart_data = array(
            'assignmentNames' => array_values($assignment_list),
            'bestScores' => array_values($best_scores),
            'submissionCounts' => array_values($submission_counts)
        );
    }
}

// Display dashboard content
echo '<div class="dashboard-container">';

// Display statistics section
echo '<div class="statistics-section">';
echo '<h3>' . get_string('statistics', 'mod_devcode') . '</h3>';

if ($single_assignment) {
    // Statistics for a single assignment
    if (!empty($submissions)) {
        $attempt_count = count($submissions);
        $best_score = 0;
        $recent_score = isset($submissions[0]->score) ? $submissions[0]->score : 0;
        $first_attempt_time = end($submissions)->timemodified;
        $best_submission = null;
        
        foreach ($submissions as $submission) {
            if (isset($submission->score) && $submission->score > $best_score) {
                $best_score = $submission->score;
                $best_submission = $submission;
            }
        }
        
        echo '<div class="statistics-grid">';
        
        // Attempts count
        echo '<div class="stat-card">';
        echo '<div class="stat-title">' . get_string('attemptsmade', 'mod_devcode') . '</div>';
        echo '<div class="stat-value">' . $attempt_count . '</div>';
        echo '</div>';
        
        // Best score
        echo '<div class="stat-card ' . ($best_score == 10 ? 'perfect-score' : '') . '">';
        echo '<div class="stat-title">' . get_string('bestscore', 'mod_devcode') . '</div>';
        echo '<div class="stat-value">' . $best_score . '/10</div>';
        if ($best_submission) {
            echo '<div class="stat-subtitle">' . userdate($best_submission->timemodified) . '</div>';
        }
        echo '</div>';
        
        // Most recent score
        echo '<div class="stat-card">';
        echo '<div class="stat-title">' . get_string('recentscore', 'mod_devcode') . '</div>';
        echo '<div class="stat-value">' . $recent_score . '/10</div>';
        echo '<div class="stat-subtitle">' . userdate($submissions[0]->timemodified) . '</div>';
        echo '</div>';
        
        // Time since first attempt
        echo '<div class="stat-card">';
        echo '<div class="stat-title">' . get_string('firstattempttitle', 'mod_devcode') . '</div>';
        echo '<div class="stat-value">' . userdate($first_attempt_time) . '</div>';
        echo '<div class="stat-subtitle">' . format_time(time() - $first_attempt_time) . ' ' . get_string('ago', 'mod_devcode') . '</div>';
        echo '</div>';
        
        echo '</div>'; // End statistics-grid
    }
} else {
    // Course-wide statistics
    if (!empty($assignment_list)) {
        $total_assignments = count($assignment_list);
        $completed_count = 0;
        $total_score = 0;
        $max_possible_score = 0;
        $total_attempts = 0;
        
        foreach ($best_scores as $assignment_id => $score) {
            if (!empty($completion_dates[$assignment_id])) {
                $completed_count++;
            }
            $total_score += $score;
            $max_possible_score += 10; // Assuming max score is 10 per assignment
            $total_attempts += $submission_counts[$assignment_id];
        }
        
        $completion_percentage = $total_assignments ? round(($completed_count / $total_assignments) * 100) : 0;
        $average_score = $total_assignments ? round($total_score / $total_assignments, 1) : 0;
        
        echo '<div class="statistics-grid">';
        
        // Assignments completed
        echo '<div class="stat-card">';
        echo '<div class="stat-title">' . get_string('assignmentscompleted', 'mod_devcode') . '</div>';
        echo '<div class="stat-value">' . $completed_count . '/' . $total_assignments . '</div>';
        echo '<div class="stat-subtitle">' . $completion_percentage . '% ' . get_string('complete', 'mod_devcode') . '</div>';
        echo '</div>';
        
        // Total score
        echo '<div class="stat-card">';
        echo '<div class="stat-title">' . get_string('totalscore', 'mod_devcode') . '</div>';
        echo '<div class="stat-value">' . $total_score . '/' . $max_possible_score . '</div>';
        echo '</div>';
        
        // Average score
        echo '<div class="stat-card">';
        echo '<div class="stat-title">' . get_string('averagescore', 'mod_devcode') . '</div>';
        echo '<div class="stat-value">' . $average_score . '/10</div>';
        echo '</div>';
        
        // Total attempts
        echo '<div class="stat-card">';
        echo '<div class="stat-title">' . get_string('totalattempts', 'mod_devcode') . '</div>';
        echo '<div class="stat-value">' . $total_attempts . '</div>';
        echo '<div class="stat-subtitle">' . round($total_attempts / $total_assignments, 1) . ' ' . get_string('perassignment', 'mod_devcode') . '</div>';
        echo '</div>';
        
        echo '</div>'; // End statistics-grid
    }
}

echo '</div>'; // End statistics section

// Charts section
if (!empty($chart_data)) {
    echo '<div class="charts-section">';
    echo '<h3>' . get_string('performance', 'mod_devcode') . '</h3>';
    
    // Create chart containers
    if ($single_assignment) {
        echo '<div class="chart-container">';
        echo '<div id="progress-chart" class="chart"></div>';
        echo '</div>';
    } else {
        echo '<div class="charts-grid">';
        echo '<div class="chart-container">';
        echo '<div id="scores-chart" class="chart"></div>';
        echo '</div>';
        
        echo '<div class="chart-container">';
        echo '<div id="attempts-chart" class="chart"></div>';
        echo '</div>';
        echo '</div>'; // End charts-grid
    }
    
    // Pass chart data to JavaScript
    echo '<script>
        var chartData = ' . json_encode($chart_data) . ';
    </script>';
    
    echo '</div>'; // End charts section
}

// Recent submissions section
if ($single_assignment && !empty($submissions)) {
    echo '<div class="recent-submissions-section">';
    echo '<h3>' . get_string('recentsubmissions', 'mod_devcode') . '</h3>';
    
    echo '<table class="generaltable recent-submissions-table">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>' . get_string('date', 'mod_devcode') . '</th>';
    echo '<th>' . get_string('status', 'mod_devcode') . '</th>';
    echo '<th>' . get_string('score', 'mod_devcode') . '</th>';
    echo '<th>' . get_string('actions', 'mod_devcode') . '</th>';
    echo '</tr>';
    echo '</thead>';
    
    echo '<tbody>';
    
    // Show last 5 submissions
    $submissions_to_show = array_slice($submissions, 0, 5);
    
    foreach ($submissions_to_show as $submission) {
        echo '<tr>';
        
        // Date
        echo '<td>' . userdate($submission->timemodified) . '</td>';
        
        // Status
        $statusclass = 'status-' . $submission->status;
        $statustext = get_string('submissionstatus_' . $submission->status, 'mod_devcode');
        echo '<td><div class="' . $statusclass . '">' . $statustext . '</div></td>';
        
        // Score
        echo '<td>' . (isset($submission->score) ? $submission->score . '/10' : '-') . '</td>';
        
        // Actions
        echo '<td>';
        $viewurl = new moodle_url('/mod/devcode/view_result.php', array('id' => $cm->id, 'sid' => $submission->id));
        echo '<a href="' . $viewurl . '" class="btn btn-secondary btn-sm">' . get_string('view', 'mod_devcode') . '</a>';
        echo '</td>';
        
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    
    echo '</div>'; // End recent-submissions section
} else if (!$single_assignment && !empty($assignment_list)) {
    // Assignment overview for course dashboard
    echo '<div class="assignments-overview-section">';
    echo '<h3>' . get_string('assignmentsoverview', 'mod_devcode') . '</h3>';
    
    echo '<table class="generaltable assignments-overview-table">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>' . get_string('assignment', 'mod_devcode') . '</th>';
    echo '<th>' . get_string('bestscore', 'mod_devcode') . '</th>';
    echo '<th>' . get_string('attempts', 'mod_devcode') . '</th>';
    echo '<th>' . get_string('status', 'mod_devcode') . '</th>';
    echo '<th>' . get_string('actions', 'mod_devcode') . '</th>';
    echo '</tr>';
    echo '</thead>';
    
    echo '<tbody>';
    
    foreach ($cms as $mod) {
        if (!$mod->uservisible) {
            continue;
        }
        
        $assignmentid = $mod->instance;
        
        echo '<tr>';
        
        // Assignment name
        echo '<td>' . format_string($assignment_list[$assignmentid]) . '</td>';
        
        // Best score
        $score = isset($best_scores[$assignmentid]) ? $best_scores[$assignmentid] : 0;
        echo '<td>' . $score . '/10</td>';
        
        // Attempts
        $attempts = isset($submission_counts[$assignmentid]) ? $submission_counts[$assignmentid] : 0;
        echo '<td>' . $attempts . '</td>';
        
        // Status
        if (!empty($completion_dates[$assignmentid])) {
            echo '<td><div class="status-graded">' . get_string('completed', 'mod_devcode') . '</div></td>';
        } else if ($attempts > 0) {
            echo '<td><div class="status-submitted">' . get_string('inprogress', 'mod_devcode') . '</div></td>';
        } else {
            echo '<td><div class="status-notsubmitted">' . get_string('notstarted', 'mod_devcode') . '</div></td>';
        }
        
        // Actions
        echo '<td>';
        
        // View assignment link
        $viewurl = new moodle_url('/mod/devcode/view.php', array('id' => $mod->id));
        echo '<a href="' . $viewurl . '" class="btn btn-secondary btn-sm">' . get_string('view', 'mod_devcode') . '</a> ';
        
        // Dashboard link
        $dashboardurl = new moodle_url('/mod/devcode/student_dashboard.php', array('id' => $mod->id));
        echo '<a href="' . $dashboardurl . '" class="btn btn-info btn-sm">' . get_string('dashboard', 'mod_devcode') . '</a>';
        
        echo '</td>';
        
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    
    echo '</div>'; // End assignments-overview section
}

echo '</div>'; // End dashboard-container

// Add navigation links
echo '<div class="dashboard-navigation">';

if ($single_assignment) {
    // Link to course dashboard
    echo '<a href="' . $CFG->wwwroot . '/mod/devcode/student_dashboard.php?course=' . $course->id . '" class="btn btn-secondary">' . 
        get_string('viewcoursedashboard', 'mod_devcode') . '</a> ';
    
    // Back to assignment
    echo '<a href="' . $CFG->wwwroot . '/mod/devcode/view.php?id=' . $cm->id . '" class="btn btn-secondary">' . 
        get_string('back', 'mod_devcode') . '</a>';
} else {
    // Back to course
    echo '<a href="' . $CFG->wwwroot . '/course/view.php?id=' . $course->id . '" class="btn btn-secondary">' . 
        get_string('backtocourse', 'mod_devcode') . '</a>';
}

echo '</div>';

// Add CSS for the dashboard
echo '
<style>
    .dashboard-container {
        margin-bottom: 30px;
    }
    .statistics-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .stat-card {
        background-color: #f8f9fa;
        border-radius: 5px;
        padding: 15px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .stat-title {
        font-size: 14px;
        color: #6c757d;
        margin-bottom: 5px;
    }
    .stat-value {
        font-size: 24px;
        font-weight: bold;
        color: #212529;
    }
    .stat-subtitle {
        font-size: 12px;
        color: #6c757d;
        margin-top: 5px;
    }
    .perfect-score {
        background-color: rgba(40, 167, 69, 0.1);
    }
    .charts-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .chart-container {
        background-color: #ffffff;
        border-radius: 5px;
        padding: 15px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        margin-bottom: 20px;
        min-height: 300px;
    }
    .chart {
        width: 100%;
        height: 300px;
    }
    .dashboard-navigation {
        margin-top: 20px;
    }
    .status-graded {
        background-color: #d4edda;
        color: #155724;
        padding: 3px 8px;
        border-radius: 4px;
        display: inline-block;
    }
    .status-submitted {
        background-color: #cce5ff;
        color: #004085;
        padding: 3px 8px;
        border-radius: 4px;
        display: inline-block;
    }
    .status-notsubmitted {
        background-color: #f8f9fa;
        color: #6c757d;
        padding: 3px 8px;
        border-radius: 4px;
        display: inline-block;
    }
    @media (max-width: 768px) {
        .statistics-grid {
            grid-template-columns: 1fr;
        }
        .charts-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
';

// Finish the page
echo $OUTPUT->footer();