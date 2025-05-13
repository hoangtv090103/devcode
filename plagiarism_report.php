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
$simfilter = optional_param('simfilter', 0, PARAM_INT); // Filter by similarity score
$sort = optional_param('sort', 'date', PARAM_ALPHA); // Sort field
$dir = optional_param('dir', 'desc', PARAM_ALPHA); // Sort direction
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

// Add necessary JavaScript for column sorting
$PAGE->requires->js_init_call('M.util.init_datatables', array());

// Start output
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('plagiarismreport', 'mod_devcode'));

// Add custom CSS for the plagiarism report
echo '
<style>
.similarity-score {
    text-align: center;
    font-weight: bold;
    border-radius: 4px;
    padding: 3px 8px;
}
.similarity-high {
    background-color: rgba(220, 53, 69, 0.2);
    color: #dc3545;
}
.similarity-medium {
    background-color: rgba(255, 193, 7, 0.2);
    color: #d97706;
}
.similarity-low {
    background-color: rgba(25, 135, 84, 0.2);
    color: #198754;
}
.sortable {
    cursor: pointer;
}
.sortable:hover {
    background-color: rgba(0, 0, 0, 0.05);
}
.sortable::after {
    content: "↕";
    opacity: 0.3;
    margin-left: 5px;
    font-size: 0.8em;
}
.sortable.asc::after {
    content: "↑";
    opacity: 1;
}
.sortable.desc::after {
    content: "↓";
    opacity: 1;
}
.flag-manual {
    background-color: rgba(13, 110, 253, 0.2);
    color: #0d6efd;
    font-size: 0.8em;
    padding: 2px 5px;
    border-radius: 3px;
    margin-left: 5px;
}
.review-status {
    display: inline-block;
    font-size: 0.8em;
    padding: 2px 5px;
    border-radius: 3px;
    margin-left: 5px;
}
.review-pending {
    background-color: rgba(255, 193, 7, 0.2);
    color: #ffc107;
}
.review-completed {
    background-color: rgba(25, 135, 84, 0.2);
    color: #198754;
}
.threshold-info {
    font-size: 0.9em;
    margin-bottom: 15px;
    color: #666;
}
</style>
';

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

// Display similarity threshold information
echo '<div class="threshold-info">';
echo '<strong>'.get_string('currentsimilaritythreshold', 'mod_devcode').':</strong> ' . $devcode->similarity_threshold . '% ';
echo '(submissions with similarity score above this value are automatically flagged)';
echo '</div>';

// Search and filter form
echo '<div class="plagiarism-filters card mb-4">';
echo '<div class="card-body">';
echo '<h5 class="card-title">Filter Report</h5>';
echo '<form action="' . $baseurl . '" method="get" class="form-inline">';
echo '<input type="hidden" name="id" value="' . $id . '">';
echo '<input type="hidden" name="sort" value="' . $sort . '">';
echo '<input type="hidden" name="dir" value="' . $dir . '">';

// Search box
echo '<div class="form-group mb-2 mr-2">';
echo '<label for="search" class="mr-2">' . get_string('search') . ': </label>';
echo '<input type="text" id="search" name="search" value="' . s($search) . '" class="form-control" placeholder="Student or assignment name">';
echo '</div>';

// Filter by assignment
echo '<div class="form-group mb-2 mr-2">';
echo '<label for="filter" class="mr-2">' . get_string('filterbyassignment', 'mod_devcode') . ': </label>';
echo '<select id="filter" name="filter" class="form-control">';
echo '<option value="0">' . get_string('allassignments', 'mod_devcode') . '</option>';
foreach ($assignments as $assign) {
    $selected = ($filter == $assign->id) ? 'selected' : '';
    echo '<option value="' . $assign->id . '" ' . $selected . '>' . format_string($assign->name) . '</option>';
}
echo '</select>';
echo '</div>';

// Filter by similarity score
echo '<div class="form-group mb-2 mr-2">';
echo '<label for="simfilter" class="mr-2">' . get_string('similarityrange', 'mod_devcode') . ': </label>';
echo '<select id="simfilter" name="simfilter" class="form-control">';
echo '<option value="0"' . ($simfilter == 0 ? ' selected' : '') . '>' . get_string('allscores', 'mod_devcode') . '</option>';
echo '<option value="1"' . ($simfilter == 1 ? ' selected' : '') . '>' . get_string('highsimilarity', 'mod_devcode') . '</option>';
echo '<option value="2"' . ($simfilter == 2 ? ' selected' : '') . '>' . get_string('mediumsimilarity', 'mod_devcode') . '</option>';
echo '<option value="3"' . ($simfilter == 3 ? ' selected' : '') . '>' . get_string('lowsimilarity', 'mod_devcode') . '</option>';
echo '</select>';
echo '</div>';

// Submit button
echo '<button type="submit" class="btn btn-primary mb-2">' . get_string('apply', 'mod_devcode') . '</button>';
echo '</form>';
echo '</div>';
echo '</div>';

// Get submissions with potential plagiarism issues
$params = array();
$where = array();

// Base condition
$where[] = "(p.flagged = 1 OR p.similarity_score >= :threshold)";
$params['threshold'] = $devcode->similarity_threshold;

// Filter by assignment
if ($filter) {
    // Filter must apply to both submissions in the plagiarism pair (they must belong to the same assignment)
    $where[] = "(s1.devcodeid = :devcodeid AND s2.devcodeid = :devcodeid)";
    $params['devcodeid'] = $filter;
}

// Filter by similarity score
if ($simfilter) {
    switch ($simfilter) {
        case 1: // High
            $where[] = "p.similarity_score >= 75";
            break;
        case 2: // Medium
            $where[] = "p.similarity_score >= 50 AND p.similarity_score < 75";
            break;
        case 3: // Low
            $where[] = "p.similarity_score < 50";
            break;
    }
}

// Search by student name
if ($search) {
    $fullname1 = $DB->sql_fullname('u1.firstname', 'u1.lastname');
    $fullname2 = $DB->sql_fullname('u2.firstname', 'u2.lastname');
    $where[] = "(" . $fullname1 . " LIKE :search1 OR " . $fullname2 . " LIKE :search2 OR d1.name LIKE :search3 OR d2.name LIKE :search4)";
    $params['search1'] = '%' . $search . '%';
    $params['search2'] = '%' . $search . '%';
    $params['search3'] = '%' . $search . '%';
    $params['search4'] = '%' . $search . '%';
}

// Determine sorting
$sortfield = "";
switch ($sort) {
    case 'student':
        $sortfield = $DB->sql_fullname('u1.firstname', 'u1.lastname') . " " . $dir;
        break;
    case 'assignment':
        $sortfield = "d1.name " . $dir;
        break;
    case 'similarity':
        $sortfield = "p.similarity_score " . $dir;
        break;
    case 'date':
    default:
        $sortfield = "s1.timemodified " . $dir;
        break;
}

// Build the query - Enhanced to include matched submission info
// The base condition might be causing issues with parameter counting since it has an OR condition
$wherestr = implode(' AND ', $where);

// Modified query to identify both submissions in a matched pair and include review status
$sql = "SELECT p.*, 
               s1.id as submission1_id, s1.timemodified as timemodified1, 
               u1.id as userid1, " . $DB->sql_fullname('u1.firstname', 'u1.lastname') . " as fullname1,
               s2.id as submission2_id, s2.timemodified as timemodified2, 
               u2.id as userid2, " . $DB->sql_fullname('u2.firstname', 'u2.lastname') . " as fullname2,
               d1.id as assignmentid, d1.name as assignmentname,
               p.similarity_score, p.flagged, p.reviewed
        FROM {devcode_plagiarism} p
        JOIN {devcode_submissions} s1 ON p.submission1_id = s1.id
        JOIN {devcode_submissions} s2 ON p.submission2_id = s2.id
        JOIN {user} u1 ON s1.userid = u1.id
        JOIN {user} u2 ON s2.userid = u2.id
        JOIN {devcode} d1 ON s1.devcodeid = d1.id
        JOIN {devcode} d2 ON s2.devcodeid = d2.id
        WHERE $wherestr
        ORDER BY $sortfield, p.similarity_score DESC";

// Count total records for pagination
$countsql = "SELECT COUNT(*) 
             FROM {devcode_plagiarism} p
             JOIN {devcode_submissions} s1 ON p.submission1_id = s1.id
             JOIN {devcode_submissions} s2 ON p.submission2_id = s2.id
             JOIN {user} u1 ON s1.userid = u1.id
             JOIN {user} u2 ON s2.userid = u2.id
             JOIN {devcode} d1 ON s1.devcodeid = d1.id
             JOIN {devcode} d2 ON s2.devcodeid = d2.id
             WHERE $wherestr";

// Check for parameter mismatches and fix them
preg_match_all('/:([a-zA-Z0-9_]+)/', $countsql . ' ' . $wherestr, $placeholders);
$expected_params = array_unique($placeholders[1]);
$actual_params = array_keys($params);

// Clean up parameters - only include what's needed in the query
foreach ($actual_params as $param) {
    if (!in_array($param, $expected_params)) {
        unset($params[$param]);
    }
}

// Make sure we have all required parameters
foreach ($expected_params as $param) {
    if (!isset($params[$param])) {
        // If a parameter is missing, add a placeholder value
        $params[$param] = 0; // Default value
    }
}

$totalcount = $DB->count_records_sql($countsql, $params);

// Re-check params again just to be safe
preg_match_all('/:([a-zA-Z0-9_]+)/', $sql, $sql_placeholders);
$sql_expected_params = array_unique($sql_placeholders[1]);
foreach ($sql_expected_params as $param) {
    if (!isset($params[$param])) {
        $params[$param] = 0; // Default value
    }
}

// Paginate the results
$plagiarism_pairs = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);

// Create sort URLs
function get_sort_url($field)
{
    global $baseurl, $sort, $dir, $search, $filter, $simfilter;
    $newdir = ($sort == $field && $dir == 'asc') ? 'desc' : 'asc';
    return $baseurl . '&sort=' . $field . '&dir=' . $newdir . '&search=' . urlencode($search) . '&filter=' . $filter . '&simfilter=' . $simfilter;
}

// Display results
echo '<div class="plagiarism-results">';
if (empty($plagiarism_pairs)) {
    echo $OUTPUT->notification(get_string('noplagiarismfound', 'mod_devcode'), 'notifyinfo');
} else {
    // Display results table
    echo '<table class="table table-striped plagiarism-table">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>' . get_string('submissionid', 'mod_devcode') . '</th>';

    // Sortable column headers
    $studentClass = ($sort == 'student') ? 'sortable ' . $dir : 'sortable';
    $assignmentClass = ($sort == 'assignment') ? 'sortable ' . $dir : 'sortable';
    $dateClass = ($sort == 'date') ? 'sortable ' . $dir : 'sortable';
    $similarityClass = ($sort == 'similarity') ? 'sortable ' . $dir : 'sortable';

    echo '<th><a href="' . get_sort_url('student') . '" class="' . $studentClass . '">' . get_string('student', 'mod_devcode') . '</a></th>';
    echo '<th><a href="' . get_sort_url('assignment') . '" class="' . $assignmentClass . '">' . get_string('assignment', 'mod_devcode') . '</a></th>';
    echo '<th><a href="' . get_sort_url('date') . '" class="' . $dateClass . '">' . get_string('submissiondate', 'mod_devcode') . '</a></th>';
    echo '<th><a href="' . get_sort_url('similarity') . '" class="' . $similarityClass . '">' . get_string('similaritylevel', 'mod_devcode') . '</a></th>';
    echo '<th>' . get_string('matchedwith', 'mod_devcode') . '</th>';
    echo '<th>' . get_string('statusreview', 'mod_devcode') . '</th>';
    echo '<th>' . get_string('actions', 'mod_devcode') . '</th>';
    echo '</tr>';
    echo '</thead>';

    echo '<tbody>';
    foreach ($plagiarism_pairs as $pair) {
        echo '<tr>';
        echo '<td>' . $pair->submission1_id . '</td>';
        echo '<td>' . $pair->fullname1 . '</td>';
        echo '<td>' . $pair->assignmentname . '</td>';
        echo '<td>' . userdate($pair->timemodified1) . '</td>';

        // Display similarity score as percentage with color coding
        $similarity_score = round($pair->similarity_score); // Value is already a percentage
        $score_class = 'similarity-score ';
        if ($similarity_score >= 75) {
            $score_class .= 'similarity-high';
            $similarity_level = get_string('highsimilarity', 'mod_devcode');
        } elseif ($similarity_score >= 50) {
            $score_class .= 'similarity-medium';
            $similarity_level = get_string('mediumsimilarity', 'mod_devcode');
        } else {
            $score_class .= 'similarity-low';
            $similarity_level = get_string('lowsimilarity', 'mod_devcode');
        }

        echo '<td>';
        echo '<span class="' . $score_class . '">' . $similarity_score . '%</span>';

        // Show if manually flagged
        if ($pair->flagged && $similarity_score < $devcode->similarity_threshold) {
            echo '<span class="flag-manual">' . get_string('manuallyflagged', 'mod_devcode') . '</span>';
        }
        echo '</td>';

        // Show matched submission info
        echo '<td>' . $pair->fullname2 . '</td>';

        // Show review status
        echo '<td>';
        if ($pair->reviewed) {
            echo '<span class="review-status review-completed">' . get_string('reviewed', 'mod_devcode') . '</span>';
        } else {
            echo '<span class="review-status review-pending">' . get_string('pending', 'mod_devcode') . '</span>';
        }
        echo '</td>';

        // Action links
        echo '<td>';
        $detailurl = $CFG->wwwroot . '/mod/devcode/plagiarism_report.php?id=' . $id . '&sid=' . $pair->submission1_id;
        echo '<a href="' . $detailurl . '" class="btn btn-sm btn-secondary">' .
            get_string('viewdetails', 'mod_devcode') . '</a>';
        echo '</td>';

        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';

    // Pagination
    echo $OUTPUT->paging_bar($totalcount, $page, $perpage, $baseurl . '&search=' . urlencode($search) .
        '&filter=' . $filter . '&simfilter=' . $simfilter . '&sort=' . $sort . '&dir=' . $dir);
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
