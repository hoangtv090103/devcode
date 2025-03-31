<?php


/**
 * Teacher dashboard for DevCode assignments
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
    $PAGE->set_url('/mod/devcode/teacher_dashboard.php', array('id' => $cm->id));
    $PAGE->set_title(format_string($devcode->name) . ': ' . get_string('teacherdashboard', 'mod_devcode'));
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
    $PAGE->set_url('/mod/devcode/teacher_dashboard.php', array('course' => $courseid));
    $PAGE->set_title(get_string('courseteacherdashboard', 'mod_devcode'));
    $PAGE->set_heading(format_string($course->fullname));
    
    // Check login
    require_login($course);
    $context = context_course::instance($courseid);
    
    // View all assignments in the course
    $single_assignment = false;
} else {
    throw new moodle_exception('invalidparameter');
}

// Only teachers can view teacher dashboard
require_capability('mod/devcode:manage', $context);

// Required JS for charts
$PAGE->requires->js_call_amd('mod_devcode/teacher_charts', 'init');

// Start output
echo $OUTPUT->header();

if ($single_assignment) {
    echo $OUTPUT->heading(format_string($devcode->name) . ': ' . get_string('teacherdashboard', 'mod_devcode'));
} else {
    echo $OUTPUT->heading(get_string('courseteacherdashboard', 'mod_devcode'));
}

// Get data for dashboard
$chart_data = array();

if ($single_assignment) {
    // Get all students
    $students = get_enrolled_users($context, 'mod/devcode:submit', 0, 'u.*', 'u.lastname, u.firstname');
    
    // Get all submissions for this assignment
    $submissions = $DB->get_records('devcode_submissions', array('devcodeid' => $devcode->id), 'timemodified DESC');
    
    // Process submissions data
    $submission_stats = array(
        'total_submissions' => count($submissions),
        'unique_students' => 0,
        'avg_score' => 0,
        'perfect_scores' => 0,
        'score_distribution' => array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0), // 0-10 score distribution
        'submission_times' => array(),
        'status_counts' => array(
            'graded' => 0,
            'submitted' => 0,
            'error' => 0
        )
    );
    
    // Student submission tracking
    $student_submissions = array();
    $student_best_scores = array();
    
    foreach ($submissions as $submission) {
        // Track unique students
        if (!isset($student_submissions[$submission->userid])) {
            $student_submissions[$submission->userid] = 0;
            $student_best_scores[$submission->userid] = 0;
        }
        
        $student_submissions[$submission->userid]++;
        
        // Track scores for graded submissions
        if ($submission->status === 'graded' && isset($submission->score)) {
            // Update student's best score
            if ($submission->score > $student_best_scores[$submission->userid]) {
                $student_best_scores[$submission->userid] = $submission->score;
            }
            
            // Count perfect scores
            if ($submission->score == 10) {
                $submission_stats['perfect_scores']++;
            }
            
            // Update score distribution (scores 0-10)
            $score_index = min(10, max(0, floor($submission->score)));
            $submission_stats['score_distribution'][$score_index]++;
        }
        
        // Track status counts
        if (isset($submission_stats['status_counts'][$submission->status])) {
            $submission_stats['status_counts'][$submission->status]++;
        }
        
        // Track submission times (by day)
        $day = date('Y-m-d', $submission->timemodified);
        if (!isset($submission_stats['submission_times'][$day])) {
            $submission_stats['submission_times'][$day] = 0;
        }
        $submission_stats['submission_times'][$day]++;
    }
    
    // Unique students who submitted
    $submission_stats['unique_students'] = count($student_submissions);
    
    // Calculate average best score across students
    $total_best_score = array_sum($student_best_scores);
    $submission_stats['avg_score'] = $submission_stats['unique_students'] > 0 ? 
        round($total_best_score / $submission_stats['unique_students'], 1) : 0;
    
    // Calculate submission rate
    $submission_stats['submission_rate'] = count($students) > 0 ? 
        round(($submission_stats['unique_students'] / count($students)) * 100) : 0;
    
    // Get submission times for chart (last 14 days)
    $dates = array();
    $counts = array();
    
    $end_date = time();
    $start_date = $end_date - (14 * 24 * 60 * 60); // 14 days ago
    
    for ($i = 0; $i < 14; $i++) {
        $day = date('Y-m-d', $start_date + ($i * 24 * 60 * 60));
        $dates[] = $day;
        $counts[] = isset($submission_stats['submission_times'][$day]) ? 
            $submission_stats['submission_times'][$day] : 0;
    }
    
    // Prepare chart data
    $chart_data = array(
        'assignmentName' => format_string($devcode->name),
        'scoreDistribution' => array(
            'labels' => array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10'),
            'data' => $submission_stats['score_distribution']
        ),
        'submissionActivity' => array(
            'labels' => $dates,
            'data' => $counts
        ),
        'statusDistribution' => array(
            'labels' => array(
                get_string('graded', 'mod_devcode'),
                get_string('submitted', 'mod_devcode'),
                get_string('error', 'mod_devcode')
            ),
            'data' => array(
                $submission_stats['status_counts']['graded'],
                $submission_stats['status_counts']['submitted'],
                $submission_stats['status_counts']['error']
            )
        )
    );
    
    // Display statistics section
    echo '<div class="dashboard-container">';
    echo '<div class="statistics-section">';
    echo '<h3>' . get_string('statistics', 'mod_devcode') . '</h3>';
    
    echo '<div class="statistics-grid">';
    
    // Submissions count
    echo '<div class="stat-card">';
    echo '<div class="stat-title">' . get_string('totalsubmissions', 'mod_devcode') . '</div>';
    echo '<div class="stat-value">' . $submission_stats['total_submissions'] . '</div>';
    echo '</div>';
    
    // Unique students
    echo '<div class="stat-card">';
    echo '<div class="stat-title">' . get_string('uniquestudents', 'mod_devcode') . '</div>';
    echo '<div class="stat-value">' . $submission_stats['unique_students'] . '/' . count($students) . '</div>';
    echo '<div class="stat-subtitle">' . $submission_stats['submission_rate'] . '% ' . 
        get_string('submissionrate', 'mod_devcode') . '</div>';
    echo '</div>';
    
    // Average score
    echo '<div class="stat-card">';
    echo '<div class="stat-title">' . get_string('averagescore', 'mod_devcode') . '</div>';
    echo '<div class="stat-value">' . $submission_stats['avg_score'] . '/10</div>';
    echo '</div>';
    
    // Perfect scores
    echo '<div class="stat-card">';
    echo '<div class="stat-title">' . get_string('perfectscores', 'mod_devcode') . '</div>';
    echo '<div class="stat-value">' . $submission_stats['perfect_scores'] . '</div>';
    echo '</div>';
    
    echo '</div>'; // End statistics-grid
    echo '</div>'; // End statistics-section
    
    // Charts section
    echo '<div class="charts-section">';
    echo '<h3>' . get_string('charts', 'mod_devcode') . '</h3>';
    
    echo '<div class="charts-grid">';
    
    // Score distribution chart
    echo '<div class="chart-container">';
    echo '<div id="score-distribution-chart" class="chart"></div>';
    echo '</div>';
    
    // Submission activity chart
    echo '<div class="chart-container">';
    echo '<div id="submission-activity-chart" class="chart"></div>';
    echo '</div>';
    
    // Status distribution chart
    echo '<div class="chart-container">';
    echo '<div id="status-distribution-chart" class="chart"></div>';
    echo '</div>';
    
    echo '</div>'; // End charts-grid
    echo '</div>'; // End charts-section
    
    // Top performers section
    echo '<div class="top-performers-section">';
    echo '<h3>' . get_string('topperformers', 'mod_devcode') . '</h3>';
    
    // Sort students by best score
    arsort($student_best_scores);
    
    echo '<table class="generaltable top-performers-table">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>' . get_string('rank', 'mod_devcode') . '</th>';
    echo '<th>' . get_string('student', 'mod_devcode') . '</th>';
    echo '<th>' . get_string('bestscore', 'mod_devcode') . '</th>';
    echo '<th>' . get_string('attempts', 'mod_devcode') . '</th>';
    echo '<th>' . get_string('actions', 'mod_devcode') . '</th>';
    echo '</tr>';
    echo '</thead>';
    
    echo '<tbody>';
    
    $rank = 1;
    // Show top 10 students
    $counter = 0;
    
    foreach ($student_best_scores as $userid => $score) {
        if (++$counter > 10) break; // Limit to top 10
        
        // Skip students with no submissions
        if (!isset($students[$userid])) continue;
        
        echo '<tr>';
        
        // Rank
        echo '<td>' . $rank++ . '</td>';
        
        // Student name
        echo '<td>' . fullname($students[$userid]) . '</td>';
        
        // Best score
        echo '<td>' . $score . '/10</td>';
        
        // Attempts
        echo '<td>' . $student_submissions[$userid] . '</td>';
        
        // Actions
        echo '<td>';
        $viewurl = new moodle_url('/mod/devcode/submissions.php', 
            array('id' => $cm->id, 'userid' => $userid));
        echo '<a href="' . $viewurl . '" class="btn btn-secondary btn-sm">' . 
            get_string('viewsubmissions', 'mod_devcode') . '</a>';
        echo '</td>';
        
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    
    // Show all students link
    $viewallurl = new moodle_url('/mod/devcode/submissions.php', array('id' => $cm->id));
    echo '<div class="text-center mt-3">';
    echo '<a href="' . $viewallurl . '" class="btn btn-secondary">' . 
        get_string('viewallsubmissions', 'mod_devcode') . '</a>';
    echo '</div>';
    
    echo '</div>'; // End top-performers-section
    
} else {
    // Course-wide dashboard
    // Get all DevCode assignments in the course
    $modinfo = get_fast_modinfo($course);
    $cms = $modinfo->get_instances_of('devcode');
    
    if (empty($cms)) {
        echo $OUTPUT->notification(get_string('nodevcodefound', 'mod_devcode'), 'info');
    } else {
        // Course-wide statistics
        $course_stats = array(
            'total_assignments' => count($cms),
            'total_submissions' => 0,
            'total_students' => 0,
            'avg_submission_rate' => 0,
            'avg_course_score' => 0,
            'assignments_data' => array()
        );
        
        // Get all enrolled students
        $all_students = get_enrolled_users($context, 'mod/devcode:submit');
        $course_stats['total_students'] = count($all_students);
        
        // Arrays for chart data
        $assignment_names = array();
        $submission_counts = array();
        $average_scores = array();
        $submission_rates = array();
        
        // Collect data for each assignment
        foreach ($cms as $mod) {
            $assignment = $DB->get_record('devcode', array('id' => $mod->instance), '*', MUST_EXIST);
            $assignment_names[] = format_string($assignment->name);
            
            // Get submissions for this assignment
            $submissions = $DB->get_records('devcode_submissions', array('devcodeid' => $assignment->id));
            $submission_counts[] = count($submissions);
            $course_stats['total_submissions'] += count($submissions);
            
            // Track unique students and best scores
            $student_submissions = array();
            $student_best_scores = array();
            
            foreach ($submissions as $submission) {
                if (!isset($student_submissions[$submission->userid])) {
                    $student_submissions[$submission->userid] = 0;
                    $student_best_scores[$submission->userid] = 0;
                }
                
                $student_submissions[$submission->userid]++;
                
                if ($submission->status === 'graded' && isset($submission->score) && 
                    $submission->score > $student_best_scores[$submission->userid]) {
                    $student_best_scores[$submission->userid] = $submission->score;
                }
            }
            
            // Calculate average score and submission rate
            $unique_students = count($student_submissions);
            $submission_rate = $course_stats['total_students'] > 0 ? 
                round(($unique_students / $course_stats['total_students']) * 100) : 0;
            $submission_rates[] = $submission_rate;
            
            $avg_score = $unique_students > 0 ? 
                round(array_sum($student_best_scores) / $unique_students, 1) : 0;
            $average_scores[] = $avg_score;
            
            // Store assignment-specific data
            $course_stats['assignments_data'][$assignment->id] = array(
                'name' => format_string($assignment->name),
                'unique_students' => $unique_students,
                'submission_rate' => $submission_rate,
                'avg_score' => $avg_score,
                'total_submissions' => count($submissions)
            );
        }
        
        // Calculate course-wide average submission rate and score
        if ($course_stats['total_assignments'] > 0) {
            $course_stats['avg_submission_rate'] = round(array_sum($submission_rates) / $course_stats['total_assignments']);
            $course_stats['avg_course_score'] = round(array_sum($average_scores) / $course_stats['total_assignments'], 1);
        }
        
        // Prepare chart data
        $chart_data = array(
            'assignmentNames' => $assignment_names,
            'submissionCounts' => $submission_counts,
            'averageScores' => $average_scores,
            'submissionRates' => $submission_rates
        );
        
        // Display statistics section
        echo '<div class="dashboard-container">';
        echo '<div class="statistics-section">';
        echo '<h3>' . get_string('coursestatistics', 'mod_devcode') . '</h3>';
        
        echo '<div class="statistics-grid">';
        
        // Total assignments
        echo '<div class="stat-card">';
        echo '<div class="stat-title">' . get_string('totalassignments', 'mod_devcode') . '</div>';
        echo '<div class="stat-value">' . $course_stats['total_assignments'] . '</div>';
        echo '</div>';
        
        // Total submissions
        echo '<div class="stat-card">';
        echo '<div class="stat-title">' . get_string('totalsubmissions', 'mod_devcode') . '</div>';
        echo '<div class="stat-value">' . $course_stats['total_submissions'] . '</div>';
        echo '<div class="stat-subtitle">' . 
            round($course_stats['total_submissions'] / $course_stats['total_assignments'], 1) . ' ' . 
            get_string('perassignment', 'mod_devcode') . '</div>';
        echo '</div>';
        
        // Average submission rate
        echo '<div class="stat-card">';
        echo '<div class="stat-title">' . get_string('avgsubmissionrate', 'mod_devcode') . '</div>';
        echo '<div class="stat-value">' . $course_stats['avg_submission_rate'] . '%</div>';
        echo '</div>';
        
        // Average course score
        echo '<div class="stat-card">';
        echo '<div class="stat-title">' . get_string('avgcoursescore', 'mod_devcode') . '</div>';
        echo '<div class="stat-value">' . $course_stats['avg_course_score'] . '/10</div>';
        echo '</div>';
        
        echo '</div>'; // End statistics-grid
        echo '</div>'; // End statistics-section
        
        // Charts section
        echo '<div class="charts-section">';
        echo '<h3>' . get_string('charts', 'mod_devcode') . '</h3>';
        
        echo '<div class="charts-grid">';
        
        // Submission count chart
        echo '<div class="chart-container">';
        echo '<div id="submission-count-chart" class="chart"></div>';
        echo '</div>';
        
        // Average score chart
        echo '<div class="chart-container">';
        echo '<div id="average-score-chart" class="chart"></div>';
        echo '</div>';
        
        // Submission rate chart
        echo '<div class="chart-container">';
        echo '<div id="submission-rate-chart" class="chart"></div>';
        echo '</div>';
        
        echo '</div>'; // End charts-grid
        echo '</div>'; // End charts-section
        
        // Assignment overview section
        echo '<div class="assignment-overview-section">';
        echo '<h3>' . get_string('assignmentsoverview', 'mod_devcode') . '</h3>';
        
        echo '<table class="generaltable assignment-overview-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . get_string('assignment', 'mod_devcode') . '</th>';
        echo '<th>' . get_string('submissionrate', 'mod_devcode') . '</th>';
        echo '<th>' . get_string('uniquestudents', 'mod_devcode') . '</th>';
        echo '<th>' . get_string('avgassignscore', 'mod_devcode') . '</th>';
        echo '<th>' . get_string('totalsubmissions', 'mod_devcode') . '</th>';
        echo '<th>' . get_string('actions', 'mod_devcode') . '</th>';
        echo '</tr>';
        echo '</thead>';
        
        echo '<tbody>';
        
        // Sort assignments by submission rate (descending)
        uasort($course_stats['assignments_data'], function($a, $b) {
            return $b['submission_rate'] <=> $a['submission_rate'];
        });
        
        foreach ($course_stats['assignments_data'] as $assignmentid => $data) {
            $cm = $cms[$assignmentid];
            
            echo '<tr>';
            
            // Assignment name
            echo '<td>' . $data['name'] . '</td>';
            
            // Submission rate
            $rateClass = '';
            if ($data['submission_rate'] >= 90) {
                $rateClass = 'high-rate';
            } else if ($data['submission_rate'] >= 70) {
                $rateClass = 'medium-rate';
            } else if ($data['submission_rate'] >= 50) {
                $rateClass = 'low-rate';
            } else {
                $rateClass = 'very-low-rate';
            }
            
            echo '<td><span class="submission-rate ' . $rateClass . '">' . 
                $data['submission_rate'] . '%</span></td>';
            
            // Unique students
            echo '<td>' . $data['unique_students'] . '/' . $course_stats['total_students'] . '</td>';
            
            // Average score
            echo '<td>' . $data['avg_score'] . '/10</td>';
            
            // Total submissions
            echo '<td>' . $data['total_submissions'] . '</td>';
            
            // Actions
            echo '<td>';
            
            // View submissions link
            $submissionsurl = new moodle_url('/mod/devcode/submissions.php', array('id' => $cm->id));
            echo '<a href="' . $submissionsurl . '" class="btn btn-secondary btn-sm">' . 
                get_string('viewsubmissions', 'mod_devcode') . '</a> ';
            
            // Dashboard link
            $dashboardurl = new moodle_url('/mod/devcode/teacher_dashboard.php', array('id' => $cm->id));
            echo '<a href="' . $dashboardurl . '" class="btn btn-info btn-sm">' . 
                get_string('dashboard', 'mod_devcode') . '</a>';
            
            echo '</td>';
            
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
        
        echo '</div>'; // End assignment-overview-section
    }
}

echo '</div>'; // End dashboard-container

// Pass chart data to JavaScript
echo '<script>
    var teacherChartData = ' . json_encode($chart_data) . ';
</script>';

// Add navigation links
echo '<div class="dashboard-navigation">';

if ($single_assignment) {
    // Link to course dashboard
    echo '<a href="' . $CFG->wwwroot . '/mod/devcode/teacher_dashboard.php?course=' . $course->id . '" class="btn btn-secondary">' . 
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
    .submission-rate {
        padding: 3px 8px;
        border-radius: 4px;
        display: inline-block;
    }
    .high-rate {
        background-color: #d4edda;
        color: #155724;
    }
    .medium-rate {
        background-color: #fff3cd;
        color: #856404;
    }
    .low-rate {
        background-color: #f8d7da;
        color: #721c24;
    }
    .very-low-rate {
        background-color: #f8d7da;
        color: #721c24;
        font-weight: bold;
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