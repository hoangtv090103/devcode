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
 * Compare two submissions for plagiarism detection
 *
 * @package     mod_devcode
 * @copyright   2024 Your Name <your@email.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
$code1 = $submission1->code;
$code2 = $submission2->code;

// Calculate similarity directly
$normalized1 = $plagiarism->normalize_code($code1);
$normalized2 = $plagiarism->normalize_code($code2);
$similarity = $plagiarism->calculate_similarity($normalized1, $normalized2);

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
$reporturl = new moodle_url('/mod/devcode/plagiarism_report.php', array('id' => $cm->id, 'sid' => $sid1));
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