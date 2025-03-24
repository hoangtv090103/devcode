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
 * Displays a particular instance of devcode
 *
 * @package     mod_devcode
 * @copyright   2023 Your Name <your@email.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once(dirname(__FILE__) . '/lib.php');

// Course module id
$id = required_param('id', PARAM_INT);

// Get necessary records
$cm = get_coursemodule_from_id('devcode', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$devcode = $DB->get_record('devcode', array('id' => $cm->instance), '*', MUST_EXIST);

// Set up the page
require_login($course, true, $cm);
$context = context_module::instance($cm->id);

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
echo html_writer::start_tag('div', array('class' => 'devcode-language-highlight'));
echo html_writer::tag('span', $language_name, array('class' => 'devcode-language-value'));
echo html_writer::end_tag('div');


// Display the description more prominently with a header
echo $OUTPUT->heading(get_string('description', 'devcode'), 3);
echo $OUTPUT->box(format_text($devcode->intro, $devcode->introformat), 'generalbox mod_introbox', 'devcodeintro');

// Check due date and display if it exists
if (!empty($devcode->duedate)) {
    $duedate = userdate($devcode->duedate);
    echo html_writer::tag('p', get_string('duedate', 'devcode') . ': ' . $duedate, array('class' => 'duedate'));
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
    echo html_writer::start_tag('div', array('class' => 'testcases visible-testcases'));
    echo html_writer::tag('p', get_string('exampletestcasesintro', 'devcode'));

    echo html_writer::start_tag('table', array('class' => 'generaltable'));
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', get_string('testcaseinput', 'devcode'));
    echo html_writer::tag('th', get_string('testcaseoutput', 'devcode'));
    echo html_writer::tag('th', get_string('testcasepoints', 'devcode'));
    echo html_writer::tag('th', get_string('testcasetimelimit', 'devcode'));
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');

    echo html_writer::start_tag('tbody');
    foreach ($visibletestcases as $testcase) {
        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', s($testcase->input), array('class' => 'testcase-input'));
        echo html_writer::tag('td', s($testcase->output), array('class' => 'testcase-output'));
        echo html_writer::tag('td', $testcase->points, array('class' => 'testcase-points'));
        echo html_writer::tag('td', $testcase->time_limit . ' ms', array('class' => 'testcase-timelimit'));
        echo html_writer::end_tag('tr');
    }
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
    echo html_writer::end_tag('div');
} else {
    echo html_writer::tag('p', get_string('notestcasesyet', 'devcode'));
}

// Display hidden test cases (only for instructors)
if ($canmanage && !empty($hiddentestcases)) {
    echo $OUTPUT->heading(get_string('hiddentestcases', 'devcode'), 3);

    echo html_writer::start_tag('div', array('class' => 'testcases hidden-testcases'));
    echo html_writer::start_tag('table', array('class' => 'generaltable'));
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', get_string('testcaseinput', 'devcode'));
    echo html_writer::tag('th', get_string('testcaseoutput', 'devcode'));
    echo html_writer::tag('th', get_string('testcasepoints', 'devcode'));
    echo html_writer::tag('th', get_string('testcasetimelimit', 'devcode'));
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');

    echo html_writer::start_tag('tbody');
    foreach ($hiddentestcases as $testcase) {
        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', s($testcase->input), array('class' => 'testcase-input'));
        echo html_writer::tag('td', s($testcase->output), array('class' => 'testcase-output'));
        echo html_writer::tag('td', $testcase->points, array('class' => 'testcase-points'));
        echo html_writer::tag('td', $testcase->time_limit . ' ms', array('class' => 'testcase-timelimit'));
        echo html_writer::end_tag('tr');
    }
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
    echo html_writer::end_tag('div');
}

// Display submission button
if ($cansubmit) {
    echo html_writer::start_tag('div', array('class' => 'submitbutton'));
    $submiturl = new moodle_url('/mod/devcode/submit.php', array('id' => $cm->id));

    $buttontext = $usersubmission ? get_string('editsubmission', 'devcode') : get_string('submitassignment', 'devcode');
    echo html_writer::link($submiturl, $buttontext, array('class' => 'btn btn-primary'));
    echo html_writer::end_tag('div');
}

// Display other info or links for instructors
if ($canmanage) {
    echo html_writer::start_tag('div', array('class' => 'teacheroptions'));
    echo html_writer::start_tag('ul');

    // Link to view all submissions
    $viewsubmissionsurl = new moodle_url('/mod/devcode/submissions.php', array('id' => $cm->id));
    echo html_writer::tag('li', html_writer::link($viewsubmissionsurl, get_string('viewallsubmissions', 'devcode')));

    echo html_writer::end_tag('ul');
    echo html_writer::end_tag('div');
}

// Finish the page
echo $OUTPUT->footer();
