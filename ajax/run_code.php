<?php
/**
 * AJAX handler for running code without submitting
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
        
        // If we're in a web context and not returning JSON yet, you can log to the error log
        error_log('DEVCODE DEBUG: ' . json_encode($log));
        
        return $log;
    }
    return null;
}

$cmid = required_param('cmid', PARAM_INT);      // Course Module ID
$code = required_param('code', PARAM_RAW);      // Source code to run
$input = optional_param('input', '', PARAM_RAW); // Optional custom input
$use_batch = optional_param('use_batch', 1, PARAM_BOOL); // Whether to use batch submission

debug_log('Starting run_code.php execution', [
    'cmid' => $cmid,
    'code_length' => strlen($code),
    'input_length' => strlen($input),
    'use_batch' => $use_batch
]);

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
    $context = context_module::instance($cm->id);
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

// Get Judge0 config
$config = devcode_get_judge0_config();
debug_log('Retrieved Judge0 config', [
    'api_url' => $config['judge0_api_url'],
    'timeout' => $config['judge0_timeout'],
    'poll_interval' => $config['judge0_poll_interval']
]);

$debug_info = [];

// Lấy các test cases hiển thị để chạy batch
$test_cases = [];

// Nếu có input từ người dùng, thêm nó như một test case
if (!empty($input)) {
    $test_cases[] = [
        'input' => $input,
        'output' => '',
        'time_limit' => isset($devcode->time_limit) ? ($devcode->time_limit / 1000) : 5,
        'memory_limit' => isset($devcode->memory_limit) ? $devcode->memory_limit : 128000,
        'is_custom' => true
    ];
} else {
    // Lấy các test cases hiển thị từ cơ sở dữ liệu
    $visible_testcases = $DB->get_records('devcode_testcases', 
        array('devcodeid' => $devcode->id, 'visible_to_student' => 1), 'id ASC');
        
    if (!empty($visible_testcases)) {
        foreach ($visible_testcases as $tc) {
            $test_cases[] = [
                'input' => $tc->input,
                'output' => $tc->output,
                'time_limit' => isset($tc->time_limit) ? ($tc->time_limit / 1000) : 5,
                'memory_limit' => isset($tc->memory_limit) ? $tc->memory_limit : 128000,
                'is_example' => true,
                'testcase_id' => $tc->id,
                'description' => $tc->description
            ];
        }
    } else {
        // Nếu không có test cases, tạo một test case rỗng
        $test_cases[] = [
            'input' => '',
            'output' => '',
            'time_limit' => isset($devcode->time_limit) ? ($devcode->time_limit / 1000) : 5,
            'memory_limit' => isset($devcode->memory_limit) ? $devcode->memory_limit : 128000,
            'is_default' => true
        ];
    }
}

try {
    if ($use_batch && count($test_cases) > 1) {
        // Sử dụng batch submission nếu có nhiều test cases
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
        
        // Nếu chỉ có một kết quả (custom input), trả về theo định dạng cũ
        if (count($formatted_results) == 1 && isset($test_cases[0]['is_custom']) && $test_cases[0]['is_custom']) {
            $result = $formatted_results[0];
            $response = [
                'status' => 'success',
                'execution_time' => $result['execution_time'],
                'memory_used' => $result['memory_used'],
                'stdout' => $result['stdout'],
                'stderr' => $result['stderr'],
                'compile_output' => $result['compile_output'],
                'status_id' => $result['status_id'],
                'status_description' => $result['status_description']
            ];
        } else {
            // Nếu có nhiều kết quả, trả về dạng batch
            $response = [
                'status' => 'success',
                'batch' => true,
                'results' => $formatted_results
            ];
        }
        
        // Thêm debug info nếu debug mode được bật
        if ($debug_mode) {
            $response['debug'] = $debug_info;
        }
        
        echo json_encode($response);
    } else {
        // Sử dụng single submission nếu chỉ có một test case hoặc không dùng batch
        debug_log('Using single submission for ' . count($test_cases) . ' test cases');
        
        // Lấy test case đầu tiên
        $test_case = $test_cases[0];
        
        // Prepare data for Judge0 API
        $api_data = [
            'source_code' => $code,
            'language_id' => $devcode->language,
            'stdin' => $test_case['input'],
            'cpu_time_limit' => $test_case['time_limit'],
            'memory_limit' => $test_case['memory_limit']
        ];
        
        debug_log('Prepared Judge0 API data', [
            'language_id' => $api_data['language_id'],
            'cpu_time_limit' => $api_data['cpu_time_limit'],
            'memory_limit' => $api_data['memory_limit'],
            'stdin_length' => strlen($api_data['stdin'])
        ]);
        
        // Send code to Judge0 API
        debug_log('Sending code to Judge0 API');
        $submission_data = devcode_send_to_api($api_data, $config);
        debug_log('Received response from Judge0 API', $submission_data);
        
        $debug_info['submission_data'] = $submission_data;

        // Check for errors in submission
        if (isset($submission_data['error']) && $submission_data['error'] === true) {
            debug_log('Judge0 API error', $submission_data, true);
            echo json_encode([
                'status' => 'error',
                'message' => 'Judge0 API error: ' . $submission_data['message'],
                'debug' => $debug_info
            ]);
            die;
        }

        if (!isset($submission_data['token'])) {
            debug_log('No token returned from Judge0 API', $submission_data, true);
            echo json_encode([
                'status' => 'error',
                'message' => 'Judge0 API error: No token returned',
                'debug' => $debug_info
            ]);
            die;
        }

        // Poll for results
        $token = $submission_data['token'];
        debug_log('Got token from Judge0 API', ['token' => $token]);
        $result = null;
        $poll_count = 0;
        $max_polls = 30; // Maximum number of polling attempts
        $debug_info['token'] = $token;
        $debug_info['polling'] = [];

        // Check if the result was already returned in the submission data
        if (isset($submission_data['result']) && !empty($submission_data['result'])) {
            debug_log('Result already available in submission data');
            $result = $submission_data['result'];
            $debug_info['result_from'] = 'submission_data';
        } else {
            // Need to poll for results
            debug_log('Starting polling for results');
            $debug_info['result_from'] = 'polling';
            
            while ($poll_count < $max_polls) {
                $poll_count++;
                debug_log('Poll attempt', ['count' => $poll_count, 'max' => $max_polls]);

                // Delay between polls
                sleep(1);

                // Get submission status
                $poll_result = devcode_poll_submission($token, $config);
                $debug_info['polling'][$poll_count] = $poll_result;
                debug_log('Poll result', ['attempt' => $poll_count, 'result' => $poll_result]);
                
                // Check for errors during polling
                if (isset($poll_result['error'])) {
                    debug_log('Error during polling', $poll_result, true);
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'Judge0 API polling error: ' . $poll_result['message'],
                        'debug' => $debug_info
                    ]);
                    die;
                }

                // Check if processing is complete
                if (isset($poll_result['result']) && !empty($poll_result['result'])) {
                    debug_log('Processing complete', ['attempts' => $poll_count]);
                    $result = $poll_result['result'];
                    break;
                }
            }

            if ($poll_count >= $max_polls) {
                debug_log('Polling timed out after ' . $max_polls . ' attempts', [], true);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Code execution timed out. Try simplifying your code or reducing input size.',
                    'debug' => $debug_info
                ]);
                die;
            }
        }

        // Format result for display
        $formatted_result = [
            'status' => 'success',
            'execution_time' => isset($result['time']) ? floatval($result['time']) : 0,
            'memory_used' => isset($result['memory']) ? intval($result['memory']) : 0,
            'stdout' => isset($result['stdout']) ? $result['stdout'] : '',
            'stderr' => isset($result['stderr']) ? $result['stderr'] : '',
            'compile_output' => isset($result['compile_output']) ? $result['compile_output'] : '',
            'message' => isset($result['message']) ? $result['message'] : '',
            'status_id' => isset($result['status']['id']) ? $result['status']['id'] : 0,
            'status_description' => isset($result['status']['description']) ? $result['status']['description'] : 'Unknown'
        ];
        
        debug_log('Formatted result', $formatted_result);

        // If debug mode is enabled, include debug info in the response
        if ($debug_mode) {
            $formatted_result['debug'] = $debug_info;
        }

        // Send formatted response
        echo json_encode($formatted_result);
    }
} catch (Exception $e) {
    debug_log('Exception caught', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()], true);
    echo json_encode([
        'status' => 'error',
        'message' => 'Runtime error: ' . $e->getMessage(),
        'debug' => $debug_info
    ]);
} 