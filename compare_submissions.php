<?php


/**
 * Compare two submissions for plagiarism detection
 *
 * @package     mod_devcode

 */

require('../../config.php');
require_once($CFG->dirroot . '/mod/devcode/lib.php');
require_once($CFG->dirroot . '/mod/devcode/classes/plagiarism.php');

// Parameters
$id = required_param('id', PARAM_INT); // Course module id
$sid1 = required_param('sid1', PARAM_INT); // First submission id
$sid2 = required_param('sid2', PARAM_INT); // Second submission id

// Get necessary records
$cm = get_coursemodule_from_id('devcode', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$devcode = $DB->get_record('devcode', array('id' => $cm->instance), '*', MUST_EXIST);

// Get both submissions
$submission1 = $DB->get_record('devcode_submissions', array('id' => $sid1), '*', MUST_EXIST);
$submission2 = $DB->get_record('devcode_submissions', array('id' => $sid2), '*', MUST_EXIST);

// Get student records
$student1 = $DB->get_record('user', array('id' => $submission1->userid), '*', MUST_EXIST);
$student2 = $DB->get_record('user', array('id' => $submission2->userid), '*', MUST_EXIST);

// Set up the page
require_login($course, true, $cm);
$context = context_module::instance($cm->id);

// Check capabilities - only teachers can compare submissions
require_capability('mod/devcode:manage', $context);

// Set up page parameters
$PAGE->set_url('/mod/devcode/compare_submissions.php', array('id' => $cm->id, 'sid1' => $sid1, 'sid2' => $sid2));
$PAGE->set_title(get_string('comparesubmissions', 'mod_devcode'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_activity_record($devcode);

// Add required JavaScript for diff viewer
$PAGE->requires->js_call_amd('mod_devcode/diff_viewer', 'init');

// Start output
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('comparesubmissions', 'mod_devcode'));

// Display submission information
echo '<div class="compare-submissions-info">';
echo '<div class="row">';

// First submission info
echo '<div class="col-md-6">';
echo '<div class="card">';
echo '<div class="card-header">' . get_string('submission', 'mod_devcode') . ' 1</div>';
echo '<div class="card-body">';
echo '<p><strong>' . get_string('student', 'mod_devcode') . ':</strong> ' . fullname($student1) . '</p>';
echo '<p><strong>' . get_string('submissiondate', 'mod_devcode') . ':</strong> ' . userdate($submission1->timemodified) . '</p>';
echo '</div>';
echo '</div>';
echo '</div>';

// Second submission info
echo '<div class="col-md-6">';
echo '<div class="card">';
echo '<div class="card-header">' . get_string('submission', 'mod_devcode') . ' 2</div>';
echo '<div class="card-body">';
echo '<p><strong>' . get_string('student', 'mod_devcode') . ':</strong> ' . fullname($student2) . '</p>';
echo '<p><strong>' . get_string('submissiondate', 'mod_devcode') . ':</strong> ' . userdate($submission2->timemodified) . '</p>';
echo '</div>';
echo '</div>';
echo '</div>';

echo '</div>'; // End row
echo '</div>'; // End compare-submissions-info

// Get similarity
$plagiarism = new mod_devcode_plagiarism($devcode);

// Always get the code content for display purposes
$code1 = $submission1->code;
$code2 = $submission2->code;

// Try to get the stored similarity score first - using two separate queries to avoid parameter issues
$sql1 = "SELECT similarity_score 
        FROM {devcode_plagiarism} 
        WHERE submission1_id = :sid1 AND submission2_id = :sid2";
$params1 = ['sid1' => $sid1, 'sid2' => $sid2];
$stored_result = $DB->get_record_sql($sql1, $params1);

// If not found in first configuration, try the reverse
if (!$stored_result) {
    $sql2 = "SELECT similarity_score 
            FROM {devcode_plagiarism} 
            WHERE submission1_id = :sid1 AND submission2_id = :sid2";
    $params2 = ['sid1' => $sid2, 'sid2' => $sid1];
    $stored_result = $DB->get_record_sql($sql2, $params2);
}

if ($stored_result && isset($stored_result->similarity_score)) {
    // Use the stored score
    $similarity = $stored_result->similarity_score;
} else {
    // Calculate similarity on-the-fly as fallback
    $normalized1 = $plagiarism->get_normalized_code($code1);
    $normalized2 = $plagiarism->get_normalized_code($code2);
    $similarity = $plagiarism->get_similarity($normalized1, $normalized2);

    // Store this result for future use
    $new_record = new stdClass();
    $new_record->submission1_id = $sid1;
    $new_record->submission2_id = $sid2;
    $new_record->similarity_score = $similarity;
    $new_record->timemodified = time();
    $DB->insert_record('devcode_plagiarism', $new_record);
}

// Display similarity information
echo '<div class="similarity-info mb-4">';
echo '<h3>' . get_string('similarityscore', 'mod_devcode') . ': ';

// Color-coded similarity
$class = '';
if ($similarity >= 90) {
    $class = 'danger';
} else if ($similarity >= 70) {
    $class = 'warning';
} else {
    $class = 'info';
}

echo '<span class="badge badge-' . $class . '">' . $similarity . '%</span></h3>';
echo '</div>';

// Display the diff viewer
echo '<div class="code-comparison-container">';
echo '<h3>' . get_string('codecomparison', 'mod_devcode') . '</h3>';
echo '<p>' . get_string('codecomparisoninfo', 'mod_devcode') . '</p>';

// Add the diff viewer container
echo '<div id="diff-viewer-container" class="mb-4">';
echo '<div id="diff-viewer"></div>';
echo '</div>';

// Add hidden input fields to store the code for JavaScript
echo '<input type="hidden" id="code1" value="' . htmlspecialchars($code1) . '">';
echo '<input type="hidden" id="code2" value="' . htmlspecialchars($code2) . '">';
echo '</div>';

// Add tabs for viewing original code
echo '<div class="code-tabs">';
echo '<ul class="nav nav-tabs" id="code-tabs" role="tablist">';
echo '<li class="nav-item">';
echo '<a class="nav-link active" id="submission1-tab" data-toggle="tab" href="#submission1" role="tab">' .
    get_string('submission', 'mod_devcode') . ' 1: ' . fullname($student1) . '</a>';
echo '</li>';
echo '<li class="nav-item">';
echo '<a class="nav-link" id="submission2-tab" data-toggle="tab" href="#submission2" role="tab">' .
    get_string('submission', 'mod_devcode') . ' 2: ' . fullname($student2) . '</a>';
echo '</li>';
echo '</ul>';

echo '<div class="tab-content" id="code-tabs-content">';
echo '<div class="tab-pane fade show active" id="submission1" role="tabpanel">';
echo '<div class="code-display">';
echo '<pre class="code-block"><code>' . htmlspecialchars($code1) . '</code></pre>';
echo '</div>';
echo '</div>';

echo '<div class="tab-pane fade" id="submission2" role="tabpanel">';
echo '<div class="code-display">';
echo '<pre class="code-block"><code>' . htmlspecialchars($code2) . '</code></pre>';
echo '</div>';
echo '</div>';
echo '</div>'; // End tab-content
echo '</div>'; // End code-tabs

// Return link
echo '<div class="back-links mt-4">';
$reporturl = $CFG->wwwroot . '/mod/devcode/plagiarism_report.php?id=' . $cm->id . '&sid=' . $sid1;
echo '<a href="' . $reporturl . '" class="btn btn-secondary">' .
    get_string('backtoplagiarismreport', 'mod_devcode') . '</a>';
echo '</div>';

// Add JavaScript for Bootstrap tabs
echo '<script>
document.addEventListener("DOMContentLoaded", function() {
    // Handle tab clicks
    const tabLinks = document.querySelectorAll("#code-tabs .nav-link");
    const tabPanes = document.querySelectorAll("#code-tabs-content .tab-pane");
    
    tabLinks.forEach(link => {
        link.addEventListener("click", function(e) {
            e.preventDefault();
            
            // Remove active class from all tabs
            tabLinks.forEach(l => l.classList.remove("active"));
            tabPanes.forEach(p => {
                p.classList.remove("show");
                p.classList.remove("active");
            });
            
            // Add active class to current tab
            this.classList.add("active");
            
            // Get the target pane
            const target = this.getAttribute("href").substring(1);
            const targetPane = document.getElementById(target);
            
            // Show the target pane
            targetPane.classList.add("show");
            targetPane.classList.add("active");
        });
    });
});
</script>';

// Finish the page
echo $OUTPUT->footer();
