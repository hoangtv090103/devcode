<?php
/*
 * Judge0 API Interface Functions
 *
 * @package    mod_devcode
 * @copyright  2024 Your Name <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Error status constants for Judge0 API
 */
define('DEVCODE_JUDGE0_ERROR_NONE', 0);
define('DEVCODE_JUDGE0_ERROR_CONNECTION', 1);
define('DEVCODE_JUDGE0_ERROR_HTTP', 2);
define('DEVCODE_JUDGE0_ERROR_RESPONSE', 3);
define('DEVCODE_JUDGE0_ERROR_TIMEOUT', 4);
define('DEVCODE_JUDGE0_ERROR_INVALID_TOKEN', 5);
define('DEVCODE_JUDGE0_ERROR_MISSING_PARAM', 6);

/**
 * Get Judge0 configuration from plugin settings
 *
 * @return array Configuration array
 */
function devcode_get_judge0_config() {
    global $CFG;
    
    // First try to get from module settings
    $config = get_config('mod_devcode');
    
    // Default values from config.php if module settings not available
    $default_api_url = isset($CFG->devcode['judge0']['api_url']) ? 
                       $CFG->devcode['judge0']['api_url'] : 'https://judge0-ce.p.rapidapi.com';
    
    $default_api_key = isset($CFG->devcode['judge0']['api_key']) ? 
                       $CFG->devcode['judge0']['api_key'] : '';
    
    $default_timeout = isset($CFG->devcode['judge0']['timeout']) ? 
                       $CFG->devcode['judge0']['timeout'] : 45;
    
    $default_max_wait = isset($CFG->devcode['judge0']['max_wait']) ? 
                        $CFG->devcode['judge0']['max_wait'] : 90;
    
    $default_poll_interval = isset($CFG->devcode['judge0']['poll_interval']) ? 
                            $CFG->devcode['judge0']['poll_interval'] : 3;
    
    // Create configuration array with fallbacks
    return [
        'judge0_api_url' => !empty($config->judge0_api_url) ? $config->judge0_api_url : $default_api_url,
        'judge0_api_key' => !empty($config->judge0_api_key) ? $config->judge0_api_key : $default_api_key,
        'judge0_timeout' => !empty($config->judge0_timeout) ? $config->judge0_timeout : $default_timeout,
        'judge0_max_wait' => !empty($config->judge0_max_wait) ? $config->judge0_max_wait : $default_max_wait,
        'judge0_poll_interval' => !empty($config->judge0_poll_interval) ? $config->judge0_poll_interval : $default_poll_interval
    ];
}

/**
 * Send a code submission to Judge0 API
 *
 * @param array $data Submission data
 * @param array $config Judge0 configuration, optional
 * @return array Response from Judge0 API
 */
function devcode_send_to_api($data, $config = null) {
    if ($config === null) {
        $config = devcode_get_judge0_config();
    }
    
    // Required parameters check
    if (empty($data['source_code']) || empty($data['language_id'])) {
        return [
            'error' => DEVCODE_JUDGE0_ERROR_MISSING_PARAM,
            'message' => 'Missing required parameters: source_code or language_id'
        ];
    }
    
    // API endpoint URL
    $url = rtrim($config['judge0_api_url'], '/') . '/submissions?base64_encoded=false&wait=false';
    
    // Prepare request headers
    $headers = [
        'Content-Type: application/json',
    ];
    
    // Add API key if available
    if (!empty($config['judge0_api_key'])) {
        $headers[] = 'X-RapidAPI-Key: ' . $config['judge0_api_key'];
        $headers[] = 'X-RapidAPI-Host: judge0-ce.p.rapidapi.com';
        
        // Debug the API key being used (masked for security)
        $masked_key = substr($config['judge0_api_key'], 0, 4) . '...' . substr($config['judge0_api_key'], -4);
        debugging('Using Judge0 API key: ' . $masked_key, DEBUG_DEVELOPER);
    } else {
        debugging('Warning: No Judge0 API key provided', DEBUG_DEVELOPER);
    }
    
    // Prepare submission data
    $submission_data = [
        'source_code' => $data['source_code'],
        'language_id' => $data['language_id'],
    ];
    
    // Optional parameters
    if (!empty($data['stdin'])) {
        $submission_data['stdin'] = $data['stdin'];
    }
    
    if (!empty($data['expected_output'])) {
        $submission_data['expected_output'] = $data['expected_output'];
    }
    
    // Retry logic settings
    $max_retries = 3;  // Maximum number of retry attempts
    $retry_delay = 2;  // Seconds to wait between retries
    $retries = 0;
    
    while ($retries <= $max_retries) {
        // Initialize curl
        $ch = curl_init($url);
        
        // Set curl options
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($submission_data));
        curl_setopt($ch, CURLOPT_TIMEOUT, $config['judge0_timeout']);
        
        // Log the request for debugging
        debugging('Sending request to Judge0 API: ' . $url, DEBUG_DEVELOPER);
        
        // Execute curl request
        $response = curl_exec($ch);
        
        // Check for curl errors
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($retries < $max_retries) {
                $retries++;
                debugging('Connection error, retrying (' . $retries . '/' . $max_retries . '): ' . $error, DEBUG_DEVELOPER);
                sleep($retry_delay);
                continue;
            }
            
            return [
                'error' => DEVCODE_JUDGE0_ERROR_CONNECTION,
                'message' => 'Connection error after ' . $max_retries . ' attempts: ' . $error
            ];
        }
        
        // Get HTTP status code
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $request_info = curl_getinfo($ch);
        curl_close($ch);
        
        // Log response for debugging
        debugging('Judge0 API response code: ' . $http_code, DEBUG_DEVELOPER);
        
        // Check HTTP status code
        if ($http_code < 200 || $http_code >= 300) {
            // Special handling for specific HTTP errors
            if ($http_code == 401) {
                // Authentication error - no need to retry with same credentials
                debugging('Authentication failed: API key is invalid. Please check your Judge0 API key configuration.', DEBUG_NORMAL);
                
                // Check if mock mode is enabled in config
                global $CFG;
                $use_mock = isset($CFG->devcode['api_mock_enabled']) && $CFG->devcode['api_mock_enabled'];
                
                if ($use_mock) {
                    debugging('Using mock response due to authentication failure', DEBUG_DEVELOPER);
                    // Generate a fake token for mock handling
                    $mock_token = 'mock_' . uniqid();
                    return [
                        'token' => $mock_token,
                        'mock' => true
                    ];
                }
                
                return [
                    'error' => DEVCODE_JUDGE0_ERROR_HTTP,
                    'message' => 'Authentication failed: API key is missing or invalid (HTTP 401)',
                    'response' => $response
                ];
            } else if ($http_code == 429) {
                // Rate limit exceeded - wait longer before retrying
                if ($retries < $max_retries) {
                    $retries++;
                    $retry_wait = $retry_delay * (2 * $retries); // Exponential backoff
                    debugging('Rate limit exceeded, retrying (' . $retries . '/' . $max_retries . ') after ' . $retry_wait . ' seconds', DEBUG_DEVELOPER);
                    sleep($retry_wait);
                    continue;
                }
            }
            
            // For other HTTP errors, retry with standard delay if attempts remain
            if ($retries < $max_retries) {
                $retries++;
                debugging('HTTP error: ' . $http_code . ', retrying (' . $retries . '/' . $max_retries . ')', DEBUG_DEVELOPER);
                sleep($retry_delay);
                continue;
            }
            
            return [
                'error' => DEVCODE_JUDGE0_ERROR_HTTP,
                'message' => 'HTTP error: ' . $http_code . ' after ' . $max_retries . ' attempts',
                'response' => $response
            ];
        }
        
        // Decode JSON response
        $result = json_decode($response, true);
        
        // Check for JSON decode errors
        if ($result === null) {
            if ($retries < $max_retries) {
                $retries++;
                debugging('JSON parse error, retrying (' . $retries . '/' . $max_retries . '): ' . json_last_error_msg(), DEBUG_DEVELOPER);
                sleep($retry_delay);
                continue;
            }
            
            return [
                'error' => DEVCODE_JUDGE0_ERROR_RESPONSE,
                'message' => 'Failed to parse response: ' . json_last_error_msg(),
                'response' => $response
            ];
        }
        
        // Check if token is present in response
        if (!isset($result['token'])) {
            if ($retries < $max_retries) {
                $retries++;
                debugging('No token in response, retrying (' . $retries . '/' . $max_retries . ')', DEBUG_DEVELOPER);
                sleep($retry_delay);
                continue;
            }
            
            return [
                'error' => DEVCODE_JUDGE0_ERROR_INVALID_TOKEN,
                'message' => 'No token in response after ' . $max_retries . ' attempts',
                'response' => $result
            ];
        }
        
        // Success, break retry loop
        debugging('Successfully received token from Judge0 API', DEBUG_DEVELOPER);
        break;
    }
    
    return $result;
}

/**
 * Poll for submission results using token
 *
 * @param string $token Submission token
 * @param array $config Judge0 configuration, optional
 * @return array Submission result from Judge0 API
 */
function devcode_poll_submission($token, $config = null) {
    if ($config === null) {
        $config = devcode_get_judge0_config();
    }
    
    // Required parameters check
    if (empty($token)) {
        return [
            'error' => DEVCODE_JUDGE0_ERROR_MISSING_PARAM,
            'message' => 'Missing required parameter: token'
        ];
    }
    
    // Check if this is a mock token
    if (strpos($token, 'mock_') === 0) {
        debugging('Using mock response for polling with token: ' . $token, DEBUG_DEVELOPER);
        
        // Return a simulated successful response
        return [
            'token' => $token,
            'mock' => true,
            'result' => [
                'status' => [
                    'id' => 3, // Accepted
                    'description' => 'Accepted (Mock)'
                ],
                'stdout' => 'Mock output for testing',
                'time' => 0.5,
                'memory' => 10240
            ]
        ];
    }
    
    // API endpoint URL
    $url = rtrim($config['judge0_api_url'], '/') . '/submissions/' . $token . '?base64_encoded=false';
    
    // Prepare request headers
    $headers = [
        'Content-Type: application/json',
    ];
    
    // Add API key if available
    if (!empty($config['judge0_api_key'])) {
        $headers[] = 'X-RapidAPI-Key: ' . $config['judge0_api_key'];
        $headers[] = 'X-RapidAPI-Host: judge0-ce.p.rapidapi.com';
    }
    
    // Initialize variables for polling
    $start_time = time();
    $max_wait = $config['judge0_max_wait'];
    $poll_interval = $config['judge0_poll_interval'];
    
    // Retry logic settings for each poll attempt
    $max_retries = 3;  // Maximum number of retry attempts per poll
    $retry_delay = 2;  // Seconds to wait between retries
    
    // Poll until we get a result or timeout
    while (true) {
        $retries = 0;
        $poll_success = false;
        $poll_result = null;
        
        // Retry loop for this specific poll attempt
        while ($retries <= $max_retries && !$poll_success) {
            // Initialize curl
            $ch = curl_init($url);
            
            // Set curl options
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, $config['judge0_timeout']);
            
            // Execute curl request
            $response = curl_exec($ch);
            
            // Check for curl errors
            if (curl_errno($ch)) {
                $error = curl_error($ch);
                curl_close($ch);
                
                if ($retries < $max_retries) {
                    $retries++;
                    debugging('Connection error during polling, retrying (' . $retries . '/' . $max_retries . '): ' . $error, DEBUG_DEVELOPER);
                    sleep($retry_delay);
                    continue;
                }
                
                $poll_result = [
                    'error' => DEVCODE_JUDGE0_ERROR_CONNECTION,
                    'message' => 'Connection error during polling after ' . $max_retries . ' attempts: ' . $error
                ];
                break;
            }
            
            // Get HTTP status code
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            // Check HTTP status code
            if ($http_code < 200 || $http_code >= 300) {
                // Special handling for specific HTTP errors
                if ($http_code == 401) {
                    // Authentication error - no need to retry with same credentials
                    $poll_result = [
                        'error' => DEVCODE_JUDGE0_ERROR_HTTP,
                        'message' => 'Authentication failed during polling: API key is missing or invalid (HTTP 401)',
                        'response' => $response
                    ];
                    break;
                } else if ($http_code == 429) {
                    // Rate limit exceeded - wait longer before retrying
                    if ($retries < $max_retries) {
                        $retries++;
                        $retry_wait = $retry_delay * (2 * $retries); // Exponential backoff
                        debugging('Rate limit exceeded during polling, retrying (' . $retries . '/' . $max_retries . ') after ' . $retry_wait . ' seconds', DEBUG_DEVELOPER);
                        sleep($retry_wait);
                        continue;
                    }
                }
                
                // For other HTTP errors, retry with standard delay if attempts remain
                if ($retries < $max_retries) {
                    $retries++;
                    debugging('HTTP error during polling: ' . $http_code . ', retrying (' . $retries . '/' . $max_retries . ')', DEBUG_DEVELOPER);
                    sleep($retry_delay);
                    continue;
                }
                
                $poll_result = [
                    'error' => DEVCODE_JUDGE0_ERROR_HTTP,
                    'message' => 'HTTP error during polling: ' . $http_code . ' after ' . $max_retries . ' attempts',
                    'response' => $response
                ];
                break;
            }
            
            // Decode JSON response
            $result = json_decode($response, true);
            
            // Check for JSON decode errors
            if ($result === null) {
                if ($retries < $max_retries) {
                    $retries++;
                    debugging('JSON parse error during polling, retrying (' . $retries . '/' . $max_retries . '): ' . json_last_error_msg(), DEBUG_DEVELOPER);
                    sleep($retry_delay);
                    continue;
                }
                
                $poll_result = [
                    'error' => DEVCODE_JUDGE0_ERROR_RESPONSE,
                    'message' => 'Failed to parse response during polling: ' . json_last_error_msg(),
                    'response' => $response
                ];
                break;
            }
            
            // Poll was successful
            $poll_success = true;
            $poll_result = $result;
        }
        
        // If poll failed after all retries, return the error
        if (!$poll_success && isset($poll_result['error'])) {
            return $poll_result;
        }
        
        // Check if we have a result
        if (isset($poll_result['status']) && isset($poll_result['status']['id'])) {
            // Check if processing is complete
            if ($poll_result['status']['id'] >= 3) { // Status IDs 3+ indicate completion
                return [
                    'token' => $token,
                    'result' => $poll_result
                ];
            }
        }
        
        // Check if we've exceeded the maximum wait time
        if (time() - $start_time > $max_wait) {
            return [
                'error' => DEVCODE_JUDGE0_ERROR_TIMEOUT,
                'message' => 'Exceeded maximum wait time of ' . $max_wait . ' seconds',
                'token' => $token
            ];
        }
        
        // Wait before polling again
        sleep($poll_interval);
    }
}

/**
 * Process a batch of submissions
 *
 * @param array $submissions Array of submission data
 * @param array $config Judge0 configuration, optional
 * @return array Results of batch processing
 */
function devcode_process_batch_submission($submissions, $config = null) {
    // Check for required parameters
    if (empty($submissions) || !is_array($submissions)) {
        return [
            'error' => DEVCODE_JUDGE0_ERROR_MISSING_PARAM,
            'message' => 'Missing or invalid submissions array'
        ];
    }
    
    // Get configuration if not provided
    if ($config === null) {
        $config = devcode_get_judge0_config();
    }
    
    $results = [
        'submissions' => count($submissions),
        'processed' => 0,
        'errors' => 0,
        'results' => []
    ];
    
    // Process each submission
    foreach ($submissions as $index => $submission) {
        // Skip invalid submissions
        if (empty($submission['source_code']) || empty($submission['language_id'])) {
            $results['errors']++;
            $results['results'][$index] = [
                'error' => DEVCODE_JUDGE0_ERROR_MISSING_PARAM,
                'message' => 'Missing required parameters: source_code or language_id'
            ];
            continue;
        }
        
        // Send to API
        $api_result = devcode_send_to_api($submission, $config);
        
        // If successful, poll for result
        if (!isset($api_result['error']) && isset($api_result['token'])) {
            $poll_result = devcode_poll_submission($api_result['token'], $config);
            
            if (!isset($poll_result['error'])) {
                $results['processed']++;
                $results['results'][$index] = $poll_result;
            } else {
                $results['errors']++;
                $results['results'][$index] = $poll_result;
            }
        } else {
            $results['errors']++;
            $results['results'][$index] = $api_result;
        }
    }
    
    return $results;
}

/**
 * Get supported languages from Judge0 API
 *
 * @param array $config Judge0 configuration, optional
 * @return array List of supported languages
 */
function devcode_get_languages($config = null) {
    if ($config === null) {
        $config = devcode_get_judge0_config();
    }
    
    // API endpoint URL
    $url = rtrim($config['judge0_api_url'], '/') . '/languages';
    
    // Prepare request headers
    $headers = ['Content-Type: application/json'];
    
    // Add API key if available
    if (!empty($config['judge0_api_key'])) {
        $headers[] = 'X-RapidAPI-Key: ' . $config['judge0_api_key'];
        $headers[] = 'X-RapidAPI-Host: judge0-ce.p.rapidapi.com';
    }
    
    // Initialize curl
    $ch = curl_init($url);
    
    // Set curl options
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $config['judge0_timeout']);
    
    // Execute curl request
    $response = curl_exec($ch);
    
    // Check for curl errors
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        return [
            'error' => DEVCODE_JUDGE0_ERROR_CONNECTION,
            'message' => 'Connection error: ' . $error
        ];
    }
    
    // Get HTTP status code
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Check HTTP status code
    if ($http_code < 200 || $http_code >= 300) {
        return [
            'error' => DEVCODE_JUDGE0_ERROR_HTTP,
            'message' => 'HTTP error: ' . $http_code,
            'response' => $response
        ];
    }
    
    // Decode JSON response
    $result = json_decode($response, true);
    
    // Check for JSON decode errors
    if ($result === null) {
        return [
            'error' => DEVCODE_JUDGE0_ERROR_RESPONSE,
            'message' => 'Failed to parse response: ' . json_last_error_msg(),
            'response' => $response
        ];
    }
    
    return $result;
}

/**
 * Get status mapping for Judge0 API
 *
 * @return array Status mapping
 */
function devcode_get_status_mapping() {
    return [
        1 => 'In Queue',
        2 => 'Processing',
        3 => 'Accepted',
        4 => 'Wrong Answer',
        5 => 'Time Limit Exceeded',
        6 => 'Compilation Error',
        7 => 'Runtime Error (SIGSEGV)',
        8 => 'Runtime Error (SIGXFSZ)',
        9 => 'Runtime Error (SIGFPE)',
        10 => 'Runtime Error (SIGABRT)',
        11 => 'Runtime Error (NZEC)',
        12 => 'Runtime Error (Other)',
        13 => 'Internal Error',
        14 => 'Exec Format Error'
    ];
}

/**
 * Map Judge0 status ID to a readable status
 *
 * @param int $status_id Status ID from Judge0
 * @return string Human-readable status
 */
function devcode_map_status($status_id) {
    $mapping = devcode_get_status_mapping();
    
    if (isset($mapping[$status_id])) {
        return $mapping[$status_id];
    }
    
    return 'Unknown Status';
}

/**
 * Grade a submission using Judge0 API
 *
 * @param object $submission The submission object
 * @param array $testcases Array of test cases
 * @param int $language_id The language ID
 * @param object $devcode The devcode instance
 * @return bool True if grading successful, false otherwise
 */
function devcode_grade_with_judge0($submission, $testcases, $language_id, $devcode) {
    global $DB;
    
    debugging('Starting grading with Judge0 for submission: ' . $submission->id, DEBUG_DEVELOPER);
    
    if (empty($testcases)) {
        debugging('No test cases found for this assignment', DEBUG_DEVELOPER);
        return false;
    }
    
    // Get Judge0 configuration
    $config = devcode_get_judge0_config();
    
    // Initialize variables for grading
    $total_points = 0;
    $earned_points = 0;
    $max_execution_time = 0;
    $total_tests = count($testcases);
    $passed_tests = 0;
    $feedback = '';
    
    // Set submission status to processing
    $DB->set_field('devcode_submissions', 'status', 'processing', array('id' => $submission->id));
    
    // Process each test case
    foreach ($testcases as $testcase) {
        debugging('Processing test case: ' . $testcase->id, DEBUG_DEVELOPER);
        
        // Prepare data for Judge0 API
        $api_data = array(
            'source_code' => $submission->code,
            'language_id' => $language_id,
            'stdin' => $testcase->input,
            'expected_output' => $testcase->output
        );
        
        // Send to Judge0 API
        $response = devcode_send_to_api($api_data, $config);
        
        // Check for API errors
        if (isset($response['error'])) {
            debugging('Judge0 API error: ' . $response['message'], DEBUG_DEVELOPER);
            
            // Create test result with error
            $result = new stdClass();
            $result->submissionid = $submission->id;
            $result->testcaseid = $testcase->id;
            $result->output = '';
            $result->error_message = 'API Error: ' . $response['message'];
            $result->execution_time = 0;
            $result->passed = 0;
            $result->points = 0;
            
            // Flag the submission for status update to 'error' at the end
            $has_api_error = true;
            
            // Save result
            $DB->insert_record('devcode_submission_results', $result);
            continue;
        }
        
        // Get token from response
        $token = $response['token'];
        
        // Poll for results
        $poll_result = devcode_poll_submission($token, $config);
        
        // Check for polling errors
        if (isset($poll_result['error'])) {
            debugging('Judge0 polling error: ' . $poll_result['message'], DEBUG_DEVELOPER);
            
            // Create test result with error
            $result = new stdClass();
            $result->submissionid = $submission->id;
            $result->testcaseid = $testcase->id;
            $result->output = '';
            $result->error_message = 'Polling Error: ' . $poll_result['message'];
            $result->execution_time = 0;
            $result->passed = 0;
            $result->points = 0;
            
            // Flag the submission for status update to 'error' at the end
            $has_api_error = true;
            
            // Save result
            $DB->insert_record('devcode_submission_results', $result);
            continue;
        }
        
        // Extract result data
        $judge_result = $poll_result['result'];
        
        // Initialize result object
        $result = new stdClass();
        $result->submissionid = $submission->id;
        $result->testcaseid = $testcase->id;
        $result->output = isset($judge_result['stdout']) ? $judge_result['stdout'] : '';
        $result->error_message = isset($judge_result['stderr']) ? $judge_result['stderr'] : '';
        
        // Get execution time in milliseconds
        $result->execution_time = isset($judge_result['time']) ? ($judge_result['time'] * 1000) : 0;
        
        // Track maximum execution time
        $max_execution_time = max($max_execution_time, $result->execution_time);
        
        // Check if test passed
        $status_id = isset($judge_result['status']['id']) ? $judge_result['status']['id'] : 0;
        $output_correct = false;
        
        // Check output only if status is Accepted (3)
        if ($status_id == 3) {
            // Normalize line endings and whitespace
            $expected = str_replace("\r\n", "\n", trim($testcase->output));
            $actual = str_replace("\r\n", "\n", trim($result->output));
            
            // Try both exact match and whitespace-normalized match
            $exact_match = ($expected === $actual);
            
            // Normalize all whitespace (spaces, tabs, newlines) to single spaces
            $expected_normalized = preg_replace('/\s+/', ' ', $expected);
            $actual_normalized = preg_replace('/\s+/', ' ', $actual);
            $whitespace_normalized_match = ($expected_normalized === $actual_normalized);
            
            // Use exact match for correctness
            $output_correct = $exact_match;
            
            // Store normalized match result for feedback
            $result->normalized_match = $whitespace_normalized_match;
        }
        
        // Set passed flag and points
        $result->passed = $output_correct ? 1 : 0;
        $result->points = $output_correct ? $testcase->points : 0;
        
        // Update counters
        $total_points += $testcase->points;
        $earned_points += $result->points;
        
        if ($result->passed) {
            $passed_tests++;
        }
        
        // Save result
        $DB->insert_record('devcode_submission_results', $result);
        
        // Generate feedback for failed tests
        if (!$result->passed) {
            $feedback .= 'Test case ' . $testcase->id . ' failed: ';
            
            if ($status_id != 3) {
                // Error in execution
                $status_desc = isset($judge_result['status']['description']) ? $judge_result['status']['description'] : 'Unknown error';
                $feedback .= $status_desc;
                
                if (!empty($result->error_message)) {
                    $feedback .= "\n" . $result->error_message;
                }
                
                if (isset($judge_result['compile_output']) && !empty($judge_result['compile_output'])) {
                    $feedback .= "\nCompile output: " . $judge_result['compile_output'];
                }
            } else {
                // Output mismatch
                $feedback .= "Output doesn't match expected result.\n";
                
                // If normalized match succeeded but exact match failed, suggest checking whitespace
                if (!empty($result->normalized_match) && $result->normalized_match) {
                    $feedback .= "Note: Your output would match if all whitespace was normalized. Check for extra spaces, tabs, or line breaks.\n";
                }
                
                $feedback .= "Expected: \"" . htmlspecialchars(trim($testcase->output)) . "\"\n";
                $feedback .= "Your output: \"" . htmlspecialchars(trim($result->output)) . "\"\n";
            }
            
            $feedback .= "\n";
        }
    }
    
    // Calculate final score (0-10 scale)
    $final_score = ($total_points > 0) ? (($earned_points / $total_points) * 10) : 0;
    
    // Check if we had any API errors
    $status = 'graded';
    if (isset($has_api_error) && $has_api_error) {
        $status = 'error';
        debugging('Setting submission status to error due to API errors', DEBUG_DEVELOPER);
    }
    
    // Update submission with results
    $submission_update = new stdClass();
    $submission_update->id = $submission->id;
    $submission_update->status = $status;
    $submission_update->score = $final_score;
    $submission_update->feedback = $feedback;
    $submission_update->timemodified = time();
    // Add test statistics
    $submission_update->passed_tests = $passed_tests;
    $submission_update->total_tests = $total_tests;
    $submission_update->execution_time = $max_execution_time;
    
    $DB->update_record('devcode_submissions', $submission_update);
    
    debugging('Grading completed for submission: ' . $submission->id . ' with score: ' . $final_score, DEBUG_DEVELOPER);
    
    return true;
} 
 