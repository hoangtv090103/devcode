<?php


/**
 * Displays a particular instance of devcode
 *
 * @package     mod_devcode
 * @copyright   2023 Your Name <your@email.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once(dirname(__FILE__) . '/lib.php');
require_once($CFG->dirroot . '/lib/classes/output/url_select.php');
require_once('judge0_api.php');

// Import required classes
use \core\output\html_writer;

// Course module id
$id = required_param('id', PARAM_INT);

// Get necessary records
$cm = get_coursemodule_from_id('devcode', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$devcode = $DB->get_record('devcode', array('id' => $cm->instance), '*', MUST_EXIST);

// Set up the page
require_login($course, true, $cm);
$context = \context_module::instance($cm->id);

// Check capabilities
$cansubmit = has_capability('mod/devcode:submit', $context);
$canview = has_capability('mod/devcode:view', $context);
$canmanage = has_capability('mod/devcode:manage', $context);

// Set up the page
$PAGE->set_url('/mod/devcode/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($devcode->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->set_activity_record($devcode);

// Configure activity header to not show intro description
$activityheader = ['description' => ''];
$PAGE->activityheader->set_attrs($activityheader);

// Get visible test cases for this assignment
$testcases = $DB->get_records_select(
    'devcode_testcases',
    'devcodeid = :devcodeid AND (visible_to_student = 1 OR :canmanage = 1)',
    array('devcodeid' => $devcode->id, 'canmanage' => $canmanage ? 1 : 0),
    'id ASC'
);

// Check if the user has already submitted - lấy bản ghi mới nhất
$usersubmissions = $DB->get_records(
    'devcode_submissions',
    array('devcodeid' => $devcode->id, 'userid' => $USER->id),
    'timemodified DESC, id DESC',
    '*',
    0,
    1
);
$usersubmission = reset($usersubmissions); // Lấy bản ghi đầu tiên (mới nhất)

// Start of output
echo $OUTPUT->header();

$language_name = devcode_get_language_by_id($devcode->language);

// Replace simple paragraph with styled highlighted container
echo \core\output\html_writer::start_tag('div', array('class' => 'devcode-language-highlight'));
echo \core\output\html_writer::tag('span', $language_name, array('class' => 'devcode-language-value'));
echo \core\output\html_writer::end_tag('div');


// Display the description more prominently with a header
echo $OUTPUT->heading(get_string('description', 'devcode'), 3);
echo $OUTPUT->box(format_text($devcode->intro, $devcode->introformat), 'generalbox mod_introbox', 'devcodeintro');

// Check due date and display if it exists
if (!empty($devcode->duedate)) {
    $duedate = userdate($devcode->duedate);
    echo \core\output\html_writer::tag('p', get_string('duedate', 'devcode') . ': ' . $duedate, array('class' => 'duedate'));
}

// Display example test cases
echo $OUTPUT->heading(get_string('visibletestcases', 'devcode'), 3);

// Separate visible and hidden test cases
$visibletestcases = array();
$hiddentestcases = array();
foreach ($testcases as $testcase) {
    if ($testcase->visible_to_student) {
        $visibletestcases[] = $testcase;
    } else {
        $hiddentestcases[] = $testcase;
    }
}

if (!empty($visibletestcases)) {
    echo \core\output\html_writer::start_tag('div', array('class' => 'testcases visible-testcases'));
    echo \core\output\html_writer::tag('p', get_string('exampletestcasesintro', 'devcode'));

    echo \core\output\html_writer::start_tag('table', array('class' => 'generaltable'));
    echo \core\output\html_writer::start_tag('thead');
    echo \core\output\html_writer::start_tag('tr');
    echo \core\output\html_writer::tag('th', get_string('testcaseinput', 'devcode'));
    echo \core\output\html_writer::tag('th', get_string('testcaseoutput', 'devcode'));
    echo \core\output\html_writer::tag('th', get_string('testcasepoints', 'devcode'));
    echo \core\output\html_writer::tag('th', get_string('testcasetimelimit', 'devcode'));
    echo \core\output\html_writer::tag('th', get_string('testcasememorylimit', 'devcode'));
    echo \core\output\html_writer::end_tag('tr');
    echo \core\output\html_writer::end_tag('thead');

    echo \core\output\html_writer::start_tag('tbody');
    foreach ($visibletestcases as $testcase) {
        echo \core\output\html_writer::start_tag('tr');
        echo \core\output\html_writer::tag('td', s($testcase->input), array('class' => 'testcase-input'));
        echo \core\output\html_writer::tag('td', s($testcase->output), array('class' => 'testcase-output'));
        echo \core\output\html_writer::tag('td', $testcase->points, array('class' => 'testcase-points'));
        echo \core\output\html_writer::tag('td', $testcase->time_limit . ' ms', array('class' => 'testcase-timelimit'));
        echo \core\output\html_writer::tag('td', devcode_format_memory_mb($testcase->memory_limit ?? 128000), array('class' => 'testcase-memorylimit'));
        echo \core\output\html_writer::end_tag('tr');
    }
    echo \core\output\html_writer::end_tag('tbody');
    echo \core\output\html_writer::end_tag('table');
    echo \core\output\html_writer::end_tag('div');
} else {
    echo \core\output\html_writer::tag('p', get_string('notestcasesyet', 'devcode'));
}

// Display hidden test cases (only for instructors)
if ($canmanage && !empty($hiddentestcases)) {
    echo $OUTPUT->heading(get_string('hiddentestcases', 'devcode'), 3);

    echo \core\output\html_writer::start_tag('div', array('class' => 'testcases hidden-testcases'));
    echo \core\output\html_writer::start_tag('table', array('class' => 'generaltable'));
    echo \core\output\html_writer::start_tag('thead');
    echo \core\output\html_writer::start_tag('tr');
    echo \core\output\html_writer::tag('th', get_string('testcaseinput', 'devcode'));
    echo \core\output\html_writer::tag('th', get_string('testcaseoutput', 'devcode'));
    echo \core\output\html_writer::tag('th', get_string('testcasepoints', 'devcode'));
    echo \core\output\html_writer::tag('th', get_string('testcasetimelimit', 'devcode'));
    echo \core\output\html_writer::tag('th', get_string('testcasememorylimit', 'devcode'));
    echo \core\output\html_writer::end_tag('tr');
    echo \core\output\html_writer::end_tag('thead');

    echo \core\output\html_writer::start_tag('tbody');
    foreach ($hiddentestcases as $testcase) {
        echo \core\output\html_writer::start_tag('tr');
        echo \core\output\html_writer::tag('td', s($testcase->input), array('class' => 'testcase-input'));
        echo \core\output\html_writer::tag('td', s($testcase->output), array('class' => 'testcase-output'));
        echo \core\output\html_writer::tag('td', $testcase->points, array('class' => 'testcase-points'));
        echo \core\output\html_writer::tag('td', $testcase->time_limit . ' ms', array('class' => 'testcase-timelimit'));
        echo \core\output\html_writer::tag('td', devcode_format_memory_mb($testcase->memory_limit ?? 128000), array('class' => 'testcase-memorylimit'));
        echo \core\output\html_writer::end_tag('tr');
    }
    echo \core\output\html_writer::end_tag('tbody');
    echo \core\output\html_writer::end_tag('table');
    echo \core\output\html_writer::end_tag('div');
}

// Display submission button
if ($cansubmit) {
    echo \core\output\html_writer::start_tag('div', array('class' => 'submitbutton'));
    $submiturl = new \moodle_url('/mod/devcode/submit.php', array('id' => $cm->id));

    $buttontext = $usersubmission ? get_string('editsubmission', 'devcode') : get_string('submitassignment', 'devcode');
    echo \core\output\html_writer::link($submiturl, $buttontext, array('class' => 'btn btn-primary'));
    echo \core\output\html_writer::end_tag('div');
}

// Display other info or links for instructors
if ($canmanage) {
    echo \core\output\html_writer::start_tag('div', array('class' => 'teacheroptions'));
    echo \core\output\html_writer::start_tag('ul');

    // Link to view all submissions
    $viewsubmissionsurl = new \moodle_url('/mod/devcode/submissions.php', array('id' => $cm->id));
    echo \core\output\html_writer::tag('li', \core\output\html_writer::link($viewsubmissionsurl, get_string('viewallsubmissions', 'devcode')));
    
    // Add link to plagiarism report if plagiarism detection is enabled
    if (!empty($devcode->enable_plagiarism)) {
        $plagiarismurl = new \moodle_url('/mod/devcode/plagiarism_report.php', array('id' => $cm->id));
        echo \core\output\html_writer::tag('li', \core\output\html_writer::link($plagiarismurl, get_string('plagiarismreport', 'devcode')));
    }

    echo \core\output\html_writer::end_tag('ul');
    echo \core\output\html_writer::end_tag('div');
}

// Display submission history for current user if they have submissions
if ($cansubmit) {
    $submission_count = $DB->count_records('devcode_submissions', array(
        'devcodeid' => $devcode->id,
        'userid' => $USER->id
    ));

    if ($submission_count > 0) {
        // Lấy lịch sử nộp bài
        $submissions = $DB->get_records(
            'devcode_submissions',
            array('devcodeid' => $devcode->id, 'userid' => $USER->id),
            'timecreated DESC'
        );

        // Hiển thị bảng lịch sử
        echo $OUTPUT->heading(get_string('yoursubmissionhistory', 'devcode'), 3);

        echo \core\output\html_writer::start_tag('div', array('class' => 'submission-history-container'));
        echo \core\output\html_writer::start_tag('table', array('class' => 'generaltable submission-history-table'));

        // Header
        echo \core\output\html_writer::start_tag('thead');
        echo \core\output\html_writer::start_tag('tr');
        echo \core\output\html_writer::tag('th', get_string('submissiontime', 'devcode'));
        echo \core\output\html_writer::tag('th', get_string('status', 'devcode'));
        echo \core\output\html_writer::tag('th', get_string('pointsearned', 'devcode'));
        echo \core\output\html_writer::tag('th', get_string('actions', 'devcode'));
        echo \core\output\html_writer::end_tag('tr');
        echo \core\output\html_writer::end_tag('thead');

        // Calculate maximum possible score from test cases once before the loop
        $all_testcases_for_score = $DB->get_records('devcode_testcases', array('devcodeid' => $devcode->id));
        $max_score = 0;
        foreach ($all_testcases_for_score as $tc) {
            $max_score += isset($tc->points) ? (float)$tc->points : 0;
        }
        // Fallback if max score is 0
        if ($max_score <= 0) {
            debugging("Warning: Maximum score calculated as 0 or less for devcode ID {$devcode->id} in view.php. Defaulting to 10.", DEBUG_DEVELOPER);
            $max_score = 10.0; 
        }

        // Rows
        echo \core\output\html_writer::start_tag('tbody');
        foreach ($submissions as $sub) {
            echo \core\output\html_writer::start_tag('tr');

            // Thời gian nộp
            echo \core\output\html_writer::tag('td', userdate($sub->timecreated));

            // Trạng thái - Apply color coding logic from view_result.php
            // --- START EDIT: Status color coding ---
            $status_value = $sub->status;
            // Map numeric statuses if needed (assuming similar mapping might be required)
            // You might need to adjust this map based on actual status values used here
            if (is_numeric($status_value)) {
                $status_map = [
                    1 => 'accepted', 2 => 'wrong_answer', 3 => 'time_limit', 4 => 'memory_limit',
                    5 => 'compile_error', 6 => 'partially_accepted', 7 => 'runtime_error',
                    8 => 'pending', 9 => 'processing'
                ];
                $status_value = $status_map[$status_value] ?? 'unknown_status';
            }

            $status_class = 'badge '; // Base class
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

            // Get the display string for the status, with fallbacks
            $status_string = '';
            if (!empty($status_value)) {
                $status_string_id = 'submissionstatus_' . $status_value;
                if (get_string_manager()->string_exists($status_string_id, 'devcode')) {
                    $status_string = get_string($status_string_id, 'devcode');
                } else if (get_string_manager()->string_exists($status_value, 'devcode')) {
                    $status_string = get_string($status_value, 'devcode');
                } else {
                    // Fallback if specific string not found
                    $status_string = ucfirst(str_replace('_', ' ', $status_value));
                    debugging('Missing string definition for status: ' . $status_value, DEBUG_DEVELOPER);
                }
            } else {
                $status_string = get_string('unknown'); // Default for empty status
            }

            echo \core\output\html_writer::start_tag('td');
            echo \core\output\html_writer::tag('span', $status_string, array('class' => $status_class, 'style' => 'font-size: 0.9em; padding: 0.3em 0.6em;')); // Use span with class
            echo \core\output\html_writer::end_tag('td');
            // --- END EDIT: Status color coding ---

            // Điểm số - Updated to use calculated max_score
            $current_score = isset($sub->score) ? (float)$sub->score : 0.0;
            $score_text = sprintf('%.1f / %.1f', $current_score, $max_score);
            echo \core\output\html_writer::tag('td', $score_text);

            // Hành động
            echo \core\output\html_writer::start_tag('td');
            echo \core\output\html_writer::link(
                new \moodle_url('/mod/devcode/view_result.php', array('id' => $cm->id, 'sid' => $sub->id)),
                get_string('viewdetails', 'devcode'),
                array('class' => 'btn btn-sm btn-secondary')
            );
            echo \core\output\html_writer::end_tag('td');

            echo \core\output\html_writer::end_tag('tr');
        }
        echo \core\output\html_writer::end_tag('tbody');
        echo \core\output\html_writer::end_tag('table');
        echo \core\output\html_writer::end_tag('div');
    }
}

// Finish the page
echo $OUTPUT->footer();
