<?php
/**
 * Test case template file for devcode
 *
 * @package    mod_devcode
 */

// Include Moodle configuration
require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(__FILE__) . '/lib.php');

// Now we have access to PARAM_ALPHA and optional_param
$format = optional_param('format', 'json', PARAM_ALPHA);

// Create a sample template with some example test cases
$template = array(
    array(
        'input' => "5\n2 3 1 5 4",
        'output' => "1 2 3 4 5",
        'points' => 10.0,
        'time_limit' => 3000,
        'description' => "Sort an array of integers in ascending order",
        'visible_to_student' => 1
    ),
    array(
        'input' => "3\nHello World Program",
        'output' => "HELLO\nWORLD\nPROGRAM",
        'points' => 5.0,
        'time_limit' => 2000,
        'description' => "Convert lowercase string to uppercase and split by spaces",
        'visible_to_student' => 0
    ),
    array(
        'input' => "4 5",
        'output' => "9",
        'points' => 2.0,
        'time_limit' => 1000,
        'memory_limit' => 128000,
        'description' => "Add two numbers",
        'visible_to_student' => 1
    )
);

// Add a documentation field
$template_with_docs = array(
    'description' => 'This is a template for test cases. You can modify this file and upload it when creating a new assignment.',
    'notes' => 'Required fields: input, output. Optional fields: points, time_limit, memory_limit, description, visible_to_student.',
    'test_cases' => $template
);

$json_data = json_encode($template_with_docs, JSON_PRETTY_PRINT);

// Format the data for TXT export if needed
$txt_data = '';
if ($format == 'txt') {
    $txt_data = "TEST CASE TEMPLATE\n";
    $txt_data .= "=================\n\n";
    $txt_data .= "Description: This is a template for test cases. You can modify this file and upload it when creating a new assignment.\n\n";
    $txt_data .= "Notes: Required fields: input, output. Optional fields: points, time_limit, memory_limit, description, visible_to_student.\n\n";
    $txt_data .= "EXAMPLE TEST CASES:\n";
    $txt_data .= "=================\n\n";
    
    foreach ($template as $index => $testcase) {
        $txt_data .= "=== Test Case " . ($index + 1) . " ===\n";
        $txt_data .= "Input:\n" . $testcase['input'] . "\n\n";
        $txt_data .= "Output:\n" . $testcase['output'] . "\n\n";
        $txt_data .= "Points: " . $testcase['points'] . "\n";
        $txt_data .= "Time Limit: " . $testcase['time_limit'] . " ms\n";
        if (isset($testcase['memory_limit'])) {
            $txt_data .= "Memory Limit: " . $testcase['memory_limit'] . " KB\n";
        }
        if (!empty($testcase['description'])) {
            $txt_data .= "Description: " . $testcase['description'] . "\n";
        }
        $txt_data .= "Visible to Student: " . ($testcase['visible_to_student'] ? 'Yes' : 'No') . "\n";
        $txt_data .= "======================\n\n";
    }
}

// Set headers based on format and output the template
if ($format == 'txt') {
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="testcase_template.txt"');
    header('Content-Length: ' . strlen($txt_data));
    echo $txt_data;
} else { // Default to JSON
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="testcase_template.json"');
    header('Content-Length: ' . strlen($json_data));
    echo $json_data;
} 