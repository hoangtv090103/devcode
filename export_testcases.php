<?php

/**
 * Export test cases for a devcode instance
 *
 * @package    mod_devcode
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(__FILE__) . '/lib.php');

$id = required_param('id', PARAM_INT); // Course Module ID
$action = optional_param('action', 'view', PARAM_ALPHA); // Action: view or download
$format = optional_param('format', 'json', PARAM_ALPHA); // Format: json or txt

// Get the course module
$cm = get_coursemodule_from_id('devcode', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$devcode = $DB->get_record('devcode', array('id' => $cm->instance), '*', MUST_EXIST);

// Set up the page
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/devcode:manage', $context);

// Get test cases in JSON format
$json_data = devcode_export_testcases($devcode->id);

// Format the data for TXT export if needed
$txt_data = '';
if ($format == 'txt') {
    $testcases = json_decode($json_data, true);
    foreach ($testcases as $index => $testcase) {
        $txt_data .= "=== Test Case " . ($index + 1) . " ===\n";
        $txt_data .= "Input:\n" . $testcase['input'] . "\n\n";
        $txt_data .= "Output:\n" . $testcase['output'] . "\n\n";
        $txt_data .= "Points: " . $testcase['points'] . "\n";
        $txt_data .= "Time Limit: " . $testcase['time_limit'] . " ms\n";
        if (!empty($testcase['description'])) {
            $txt_data .= "Description: " . $testcase['description'] . "\n";
        }
        $txt_data .= "Visible to Student: " . ($testcase['visible_to_student'] ? 'Yes' : 'No') . "\n";
        $txt_data .= "======================\n\n";
    }
}

// Handle the export based on action
if ($action === 'download') {
    // Set headers based on format
    if ($format == 'txt') {
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="testcases_' . $devcode->id . '.txt"');
        header('Content-Length: ' . strlen($txt_data));
        echo $txt_data;
    } else { // Default to JSON
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="testcases_' . $devcode->id . '.json"');
        header('Content-Length: ' . strlen($json_data));
        echo $json_data;
    }
    exit;
} else {
    // Display the JSON in browser
    $PAGE->set_url('/mod/devcode/export_testcases.php', array('id' => $cm->id));
    $PAGE->set_title(format_string($devcode->name) . ': ' . get_string('testcaseexport', 'devcode'));
    $PAGE->set_heading(format_string($course->fullname));
    $PAGE->set_context($context);

    echo $OUTPUT->header();
    echo $OUTPUT->heading(format_string($devcode->name) . ': ' . get_string('testcaseexport', 'devcode'));

    // Add download buttons
    echo html_writer::start_div('export-actions');

    // JSON Download button
    $json_url = new moodle_url(
        '/mod/devcode/export_testcases.php',
        array('id' => $cm->id, 'action' => 'download', 'format' => 'json')
    );
    echo html_writer::start_div('action-button');
    echo $OUTPUT->single_button($json_url, get_string('downloadasjson', 'devcode', 'JSON'), 'get');
    echo html_writer::end_div();

    // TXT Download button
    $txt_url = new moodle_url(
        '/mod/devcode/export_testcases.php',
        array('id' => $cm->id, 'action' => 'download', 'format' => 'txt')
    );
    echo html_writer::start_div('action-button');
    echo $OUTPUT->single_button($txt_url, get_string('downloadastxt', 'devcode', 'TXT'), 'get');
    echo html_writer::end_div();

    echo html_writer::end_div();

    // Display the test cases in a pre element for easy copying
    echo html_writer::tag('pre', s($json_data), array('class' => 'testcase-json'));

    // Add some JavaScript to allow easy selection/copying
    $js = "
    require(['jquery'], function($) {
        $('.testcase-json').click(function() {
            var range = document.createRange();
            range.selectNodeContents(this);
            var selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(range);
        });
    });
    ";
    $PAGE->requires->js_amd_inline($js);

    // Add some basic styling
    $css = "
    .testcase-json {
        background-color: #f5f5f5;
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 15px;
        font-family: monospace;
        white-space: pre;
        overflow-x: auto;
        cursor: pointer;
        margin-top: 20px;
    }
    .export-actions {
        margin-bottom: 20px;
        display: flex;
        gap: 10px;
    }
    .action-button {
        display: inline-block;
    }
    ";
    echo html_writer::tag('style', $css);

    // Back to module link
    $module_url = new moodle_url('/mod/devcode/view.php', array('id' => $cm->id));
    echo html_writer::div(
        html_writer::link($module_url, get_string('backtocourse', 'devcode')),
        'backlink'
    );

    echo $OUTPUT->footer();
}
