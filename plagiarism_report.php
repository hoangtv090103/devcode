<?php


/**
 * Displays plagiarism reports for DevCode submissions
 *
 * @package     mod_devcode

 */

require('../../config.php');
require_once($CFG->dirroot . '/mod/devcode/lib.php');
require_once($CFG->dirroot . '/mod/devcode/classes/plagiarism.php');

// Parameters
$id = required_param('id', PARAM_INT); // Course module id
$sid = optional_param('sid', 0, PARAM_INT); // Submission id (if viewing details for a specific submission)
$search = optional_param('search', '', PARAM_TEXT); // Search term
$filter = optional_param('filter', 0, PARAM_INT); // Filter by assignment ID
$page = optional_param('page', 0, PARAM_INT); // Pagination
$perpage = 10; // Items per page

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
$baseurl = $CFG->wwwroot . '/mod/devcode/plagiarism_report.php?id=' . $id;
$PAGE->set_url('/mod/devcode/plagiarism_report.php', array('id' => $id));
$PAGE->set_title(format_string($devcode->name) . ': ' . get_string('plagiarismreport', 'mod_devcode'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_activity_record($devcode);

// Start output
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('plagiarismreport', 'mod_devcode'));

// Check if plagiarism detection is enabled
if (empty($devcode->enable_plagiarism)) {
    echo $OUTPUT->notification(get_string('plagiarismnotenabled', 'mod_devcode'), 'notifyproblem');
    echo $OUTPUT->footer();
    exit;
}

// Initialize the plagiarism handler
$plagiarism = new mod_devcode_plagiarism($devcode);

// If viewing a specific submission detail
if ($sid) {
    include_once($CFG->dirroot . '/mod/devcode/plagiarism_report_detail.php');
    exit;
}

// Get all assignments in this course for filtering
$assignments = $DB->get_records('devcode', array('course' => $course->id), 'name ASC');

// Search and filter form
echo '<div class="plagiarism-filters">';
echo '<form action="' . $baseurl . '" method="get">';
echo '<input type="hidden" name="id" value="' . $id . '">';

// Search box
echo '<div class="form-group d-inline-block mr-2">';
echo '<label for="search">' . get_string('search') . ': </label>';
echo '<input type="text" id="search" name="search" value="' . s($search) . '" class="form-control">';
echo '</div>';

// Filter dropdown
echo '<div class="form-group d-inline-block mr-2">';
echo '<label for="filter">' . get_string('filterbyassignment', 'mod_devcode') . ': </label>';
echo '<select id="filter" name="filter" class="form-control">';
echo '<option value="0">' . get_string('allassignments', 'mod_devcode') . '</option>';
foreach ($assignments as $assign) {
    $selected = ($filter == $assign->id) ? 'selected' : '';
    echo '<option value="' . $assign->id . '" ' . $selected . '>' . format_string($assign->name) . '</option>';
}
echo '</select>';
echo '</div>';

// Submit button
echo '<button type="submit" class="btn btn-primary">' . get_string('apply', 'mod_devcode') . '</button>';
echo '</form>';
echo '</div>';

// Get submissions with potential plagiarism issues
$params = array();
$where = array();

// Base condition
$where[] = "p.flagged = 1 OR p.similarity_score >= :threshold";
$params['threshold'] = $devcode->similarity_threshold;

// Filter by assignment
if ($filter) {
    $where[] = "s.devcodeid = :devcodeid";
    $params['devcodeid'] = $filter;
}

// Search by student name
if ($search) {
    $fullname = $DB->sql_fullname('u.firstname', 'u.lastname');
    $where[] = "(" . $fullname . " LIKE :search OR d.name LIKE :search2)";
    $params['search'] = '%' . $search . '%';
    $params['search2'] = '%' . $search . '%';
}

// Build the query
$wherestr = implode(' AND ', $where);
$sql = "SELECT p.*, s.id as submissionid, s.timemodified, 
               u.id as userid, " . $DB->sql_fullname('u.firstname', 'u.lastname') . " as fullname,
               d.id as assignmentid, d.name as assignmentname
        FROM {devcode_plagiarism} p
        JOIN {devcode_submissions} s ON (p.submission1_id = s.id OR p.submission2_id = s.id)
        JOIN {user} u ON s.userid = u.id
        JOIN {devcode} d ON s.devcodeid = d.id
        WHERE $wherestr
        GROUP BY s.id
        ORDER BY s.timemodified DESC, p.similarity_score DESC";

// Count total records for pagination
$countsql = "SELECT COUNT(DISTINCT s.id) 
             FROM {devcode_plagiarism} p
             JOIN {devcode_submissions} s ON (p.submission1_id = s.id OR p.submission2_id = s.id)
             JOIN {user} u ON s.userid = u.id
             JOIN {devcode} d ON s.devcodeid = d.id
             WHERE $wherestr";
$totalcount = $DB->count_records_sql($countsql, $params);

// Paginate the results
$submissions = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);

// Display results
echo '<div class="plagiarism-results">';
if (empty($submissions)) {
    echo $OUTPUT->notification(get_string('noplagiarismfound', 'mod_devcode'), 'notifyinfo');
} else {
    // Display results table
    echo '<table class="table table-striped plagiarism-table">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>' . get_string('submissionid', 'mod_devcode') . '</th>';
    echo '<th>' . get_string('student', 'mod_devcode') . '</th>';
    echo '<th>' . get_string('assignment', 'mod_devcode') . '</th>';
    echo '<th>' . get_string('submissiondate', 'mod_devcode') . '</th>';
    echo '<th>' . get_string('actions', 'mod_devcode') . '</th>';
    echo '</tr>';
    echo '</thead>';
    
    echo '<tbody>';
    foreach ($submissions as $submission) {
        echo '<tr>';
        echo '<td>' . $submission->submissionid . '</td>';
        echo '<td>' . $submission->fullname . '</td>';
        echo '<td>' . $submission->assignmentname . '</td>';
        echo '<td>' . userdate($submission->timemodified) . '</td>';
        
        // Action links
        echo '<td>';
        $detailurl = $CFG->wwwroot . '/mod/devcode/plagiarism_report.php?id=' . $id . '&sid=' . $submission->submissionid;
        echo '<a href="' . $detailurl . '" class="btn btn-sm btn-secondary">' . 
            get_string('viewdetails', 'mod_devcode') . '</a>';
        echo '</td>';
        
        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';
    
    // Pagination
    echo $OUTPUT->paging_bar($totalcount, $page, $perpage, $baseurl . '&search=' . urlencode($search) . '&filter=' . $filter);
}
echo '</div>';

// Back link
echo '<div class="mt-3">';
$viewurl = $CFG->wwwroot . '/mod/devcode/view.php?id=' . $cm->id;
echo '<a href="' . $viewurl . '" class="btn btn-secondary">' . 
    get_string('back', 'mod_devcode') . '</a>';
echo '</div>';

// Finish the page
echo $OUTPUT->footer(); 