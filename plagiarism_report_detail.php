<?php


/**
 * Displays detailed plagiarism report for a specific submission
 *
 * @package     mod_devcode

 */

defined('MOODLE_INTERNAL') || die();


// Ensure the main script has set up the necessary variables
if (!isset($sid) || !isset($cm) || !isset($devcode) || !isset($plagiarism)) {
    print_error('invalidaccess', 'mod_devcode');
}

// Get submission and student details
$submission = $DB->get_record('devcode_submissions', array('id' => $sid), '*', MUST_EXIST);
$student = $DB->get_record('user', array('id' => $submission->userid), '*', MUST_EXIST);
$assignment = $DB->get_record('devcode', array('id' => $submission->devcodeid), '*', MUST_EXIST);

// Check if this submission has been reviewed
$reviewedCheck = $DB->get_records_sql(
    "SELECT p.reviewed 
     FROM {devcode_plagiarism} p
     WHERE (p.submission1_id = :sid1 OR p.submission2_id = :sid2)
     AND p.reviewed = 1
     LIMIT 1",
    array('sid1' => $sid, 'sid2' => $sid)
);
$isReviewed = !empty($reviewedCheck);

// Get plagiarism results for this submission
$sql = "SELECT p.*, 
               s.id as compared_submission_id, 
               s.userid as compared_user_id,
               s.timemodified as compared_timemodified,
               " . $DB->sql_fullname('u.firstname', 'u.lastname') . " as compared_fullname
        FROM {devcode_plagiarism} p
        JOIN {devcode_submissions} s ON (
            (p.submission1_id = :sid1 AND p.submission2_id = s.id) OR 
            (p.submission2_id = :sid2 AND p.submission1_id = s.id)
        )
        JOIN {user} u ON s.userid = u.id
        WHERE p.similarity_score >= :threshold
        ORDER BY p.similarity_score DESC";

$params = array(
    'sid1' => $sid,
    'sid2' => $sid,
    'threshold' => $assignment->similarity_threshold
);

$results = $DB->get_records_sql($sql, $params);

// If no results are found, run the plagiarism check
if (empty($results)) {
    $check_results = $plagiarism->check_submission($sid);
    if (!empty($check_results)) {
        $plagiarism->store_results($sid, $check_results);
        // Refresh results
        $results = $DB->get_records_sql($sql, $params);
    }
}

// Start output
echo $OUTPUT->heading(get_string('plagiarismdetailreport', 'mod_devcode') . ' - ' . get_string('submission', 'mod_devcode') . ' ' . $sid);

// Display submission details card
echo '<div class="submission-info card mb-4">';
echo '<div class="card-header">' . get_string('submissiondetails', 'mod_devcode') . '</div>';
echo '<div class="card-body">';
echo '<div class="row">';

// Get the compared student for display (if available)
$compared_student_name = '';
if (!empty($results)) {
    // Get the first result's compared student name
    $first_result = reset($results);
    $compared_student_name = $first_result->compared_fullname;
}

// Student info - Now showing the compared student (the one being copied from)
echo '<div class="col-md-4">';
if (!empty($compared_student_name)) {
    echo '<p><strong>' . get_string('student', 'mod_devcode') . ':</strong> ' . $compared_student_name . '</p>';
} else {
    // Fallback to original student if no compared student is found
    echo '<p><strong>' . get_string('student', 'mod_devcode') . ':</strong> ' . fullname($student) . '</p>';
}
echo '</div>';

// Assignment info
echo '<div class="col-md-4">';
echo '<p><strong>' . get_string('assignment', 'mod_devcode') . ':</strong> ' . format_string($assignment->name) . '</p>';
echo '</div>';

// Submission date
echo '<div class="col-md-4">';
echo '<p><strong>' . get_string('submissiondate', 'mod_devcode') . ':</strong> ' . userdate($submission->timemodified) . '</p>';
echo '</div>';

echo '</div>'; // End row
echo '</div>'; // End card-body
echo '</div>'; // End card

// Display results
echo '<div class="similar-submissions-container">';
echo '<h4>' . get_string('similarsubmissions', 'mod_devcode') . '</h4>';

if (empty($results)) {
    echo $OUTPUT->notification(get_string('nosimilarsubmissionsfound', 'mod_devcode'), 'notifyinfo');
} else {
    // Display table of similar submissions
    echo '<table class="table table-striped similarity-table">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>' . get_string('submissionid', 'mod_devcode') . '</th>';
    echo '<th>' . get_string('student', 'mod_devcode') . '</th>';
    echo '<th>' . get_string('similarityscore', 'mod_devcode') . '</th>';
    echo '<th>' . get_string('actions', 'mod_devcode') . '</th>';
    echo '</tr>';
    echo '</thead>';

    echo '<tbody>';
    foreach ($results as $result) {
        echo '<tr>';
        echo '<td>' . $result->compared_submission_id . '</td>';
        
        // Now showing the current student (the one who copied) instead of compared student
        echo '<td>' . fullname($student) . '</td>';

        // Color-coded similarity
        $class = '';
        if ($result->similarity_score >= 90) {
            $class = 'danger';
        } else if ($result->similarity_score >= 70) {
            $class = 'warning';
        } else {
            $class = 'info';
        }

        echo '<td><span class="badge badge-' . $class . '">' . $result->similarity_score . '%</span></td>';

        // Actions
        echo '<td>';
        $compareurl = $CFG->wwwroot . '/mod/devcode/compare_submissions.php?id=' . $cm->id . '&sid1=' . $sid . '&sid2=' . $result->compared_submission_id;
        echo '<a href="' . $compareurl . '" class="btn btn-sm btn-secondary">' .
            get_string('viewsourcecode', 'mod_devcode') . '</a>';
        echo '</td>';

        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';
}
echo '</div>';

// Notes and review section
echo '<div class="review-section card mt-4 mb-4">';
echo '<div class="card-header">' . get_string('teachernotes', 'mod_devcode') . '</div>';
echo '<div class="card-body">';

// Form for adding/updating notes and flagging
echo '<form action="' . $CFG->wwwroot . '/mod/devcode/plagiarism_action.php" method="post">';
echo '<input type="hidden" name="id" value="' . $cm->id . '">';
echo '<input type="hidden" name="sid" value="' . $sid . '">';
echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';

// Notes field
echo '<div class="form-group">';
echo '<label for="notes">' . get_string('notes', 'mod_devcode') . ':</label>';
$notes = $DB->get_field('devcode_submissions', 'feedback', array('id' => $sid));
echo '<textarea class="form-control" id="notes" name="notes" rows="4">' . s($notes) . '</textarea>';
echo '</div>';

// Action buttons - only show if not reviewed
if (!$isReviewed) {
    echo '<div class="form-group">';
    echo '<button type="submit" name="action" value="flag" class="btn btn-danger mr-2">' .
        get_string('flagasplagiarism', 'mod_devcode') . '</button>';
    echo '<button type="submit" name="action" value="pass" class="btn btn-success">' .
        get_string('markaspassed', 'mod_devcode') . '</button>';
    echo '</div>';
} else {
    // Show a message that this submission has been reviewed
    echo '<div class="alert alert-info">' . get_string('submissionalreadyreviewed', 'mod_devcode', $submission->status) . '</div>';
}

echo '</form>';
echo '</div>'; // End card-body
echo '</div>'; // End card

// Back link
echo '<div>';
$reporturl = $CFG->wwwroot . '/mod/devcode/plagiarism_report.php?id=' . $cm->id;
echo '<a href="' . $reporturl . '" class="btn btn-secondary">' .
    get_string('backtoplagiarismlist', 'mod_devcode') . '</a>';
echo '</div>';

// Finish the page (will be handled by the main script) 