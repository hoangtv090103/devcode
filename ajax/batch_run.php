<?php
/**
 * AJAX handler for running code in batch mode
 *
 * @package     mod_devcode
 */

// We need to define that this script needs Moodle session
define('AJAX_SCRIPT', true);

require_once('../../../config.php');
require_once($CFG->dirroot . '/mod/devcode/lib.php');
require_once($CFG->dirroot . '/mod/devcode/judge0_api.php');

// Enable debugging mode if requested
$debug_mode = optional_param('debug', 0, PARAM_INT);

// Function to log debug information
function debug_log($message, $data = null, $debug = false) {
    global $debug_mode;
    if ($debug_mode || $debug) {
        $log = [
            'timestamp' => microtime(true),
            'message' => $message,
            'data' => $data
        ];
        
        // Log to the error log
        error_log('DEVCODE BATCH DEBUG: ' . json_encode($log));
        
        return $log;
    }
    return null;
}

$cmid = required_param('cmid', PARAM_INT);      // Course Module ID
$code = required_param('code', PARAM_RAW);      // Source code to run
$test_ids = required_param('test_ids', PARAM_RAW); // Test case IDs, comma-separated

debug_log('Starting batch_run.php execution', [
    'cmid' => $cmid,
    'code_length' => strlen($code),
    'test_ids' => $test_ids
]);

// Parse test case IDs
$test_ids_array = explode(',', $test_ids);
$test_ids_array = array_map('trim', $test_ids_array);
$test_ids_array = array_filter($test_ids_array, 'is_numeric');

if (empty($test_ids_array)) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'No valid test case IDs provided'
    ]);
    die;
}

// Get course module, course and devcode instance
try {
    $cm = get_coursemodule_from_id('devcode', $cmid, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $devcode = $DB->get_record('devcode', array('id' => $cm->instance), '*', MUST_EXIST);
    
    debug_log('Retrieved course module and devcode instance', [
        'course_id' => $course->id,
        'devcode_id' => $devcode->id,
        'devcode_language' => $devcode->language
    ]);
} catch (Exception $e) {
    debug_log('Error retrieving course module', ['error' => $e->getMessage()], true);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to retrieve course module: ' . $e->getMessage(),
        'debug' => debug_log('Failed to retrieve course module', ['error' => $e->getMessage()], true)
    ]);
    die;
}

// Check login and permissions
try {
    require_login($course, false, $cm);
    $context = \core\context\module::instance($cm->id);
    if (!$context) {
        throw new \core\exception\moodle_exception('invalidcontext');
    }
    require_capability('mod/devcode:submit', $context);
    debug_log('User authenticated and has required capabilities');
} catch (Exception $e) {
    debug_log('Authentication or permission error', ['error' => $e->getMessage()], true);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Authentication error: ' . $e->getMessage(),
        'debug' => debug_log('Authentication error', ['error' => $e->getMessage()], true)
    ]);
    die;
}

// Set JSON response headers
header('Content-Type: application/json');

// Check if code is empty
if (empty(trim($code))) {
    debug_log('Code is empty', [], true);
    echo json_encode([
        'status' => 'error',
        'message' => get_string('codeempty', 'devcode'),
        'debug' => debug_log('Empty code submitted', [], true)
    ]);
    die;
}

// Retrieve test cases by ID
$test_cases = [];
foreach ($test_ids_array as $test_id) {
    $testcase = $DB->get_record('devcode_testcases', [
        'id' => $test_id,
        'devcodeid' => $devcode->id
    ]);
    
    if ($testcase) {
        $test_cases[] = [
            'id' => $testcase->id,
            'input' => $testcase->input,
            'output' => $testcase->output,
            'time_limit' => isset($testcase->time_limit) ? ($testcase->time_limit / 1000) : 5,
            'memory_limit' => isset($testcase->memory_limit) ? (int)$testcase->memory_limit : 128000,
            'points' => $testcase->points,
            'description' => $testcase->description,
            'visible' => $testcase->visible
        ];
    }
}

if (empty($test_cases)) {
    debug_log('No test cases found', ['test_ids' => $test_ids_array], true);
    echo json_encode([
        'status' => 'error',
        'message' => 'No valid test cases found',
        'debug' => debug_log('No test cases found', ['test_ids' => $test_ids_array], true)
    ]);
    die;
}

// Get Judge0 config
$config = devcode_get_judge0_config();
debug_log('Retrieved Judge0 config', [
    'api_url' => $config['judge0_api_url'],
    'timeout' => $config['judge0_timeout'],
    'poll_interval' => $config['judge0_poll_interval']
]);

$debug_info = [];

try {
    // Run code with batch
    debug_log('Using batch submission for ' . count($test_cases) . ' test cases');
    
    $batch_result = devcode_run_code_with_batch($code, $devcode->language, $test_cases, $config);
    $debug_info['batch_result'] = $batch_result;
    
    if (isset($batch_result['error']) && $batch_result['error']) {
        debug_log('Batch submission error', $batch_result, true);
        echo json_encode([
            'status' => 'error',
            'message' => 'Judge0 API error: ' . $batch_result['message'],
            'debug' => $debug_info
        ]);
        die;
    }
    
    // Xử lý kết quả batch
    $formatted_results = [];
    if (!empty($batch_result['results'])) {
        foreach ($batch_result['results'] as $index => $result) {
            $test_case = $test_cases[$index] ?? [];
            
            $formatted_result = [
                'test_case_id' => $test_case['id'],
                'test_case' => $test_case,
                'execution_time' => $result['execution_time'] ?? 0,
                'memory_used' => $result['memory'] ?? 0,
                'stdout' => $result['stdout'] ?? '',
                'stderr' => $result['stderr'] ?? '',
                'compile_output' => $result['compile_output'] ?? '',
                'passed' => $result['passed'] ?? false,
                'status_id' => isset($result['status']['id']) ? $result['status']['id'] : 0,
                'status_description' => isset($result['status']['description']) ? $result['status']['description'] : 'Unknown'
            ];
            
            $formatted_results[] = $formatted_result;
        }
    }
    
    $response = [
        'status' => 'success',
        'batch' => true,
        'results' => $formatted_results
    ];
    
    // Thêm debug info nếu debug mode được bật
    if ($debug_mode) {
        $response['debug'] = $debug_info;
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    debug_log('Exception caught', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()], true);
    echo json_encode([
        'status' => 'error',
        'message' => 'Runtime error: ' . $e->getMessage(),
        'debug' => $debug_info
    ]);
} 