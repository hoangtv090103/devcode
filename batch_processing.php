<?php
/*
 * Functions for batch processing of code submissions
 *
 * @package    mod_devcode
 * @copyright  2024 Your Name <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/judge0_api.php');

/**
 * Structure for error status codes used in batch processing
 */
define('DEVCODE_BATCH_ERROR_MISSING_PARAMS', 1);
define('DEVCODE_BATCH_ERROR_JUDGE0_CONFIG', 2);
define('DEVCODE_BATCH_ERROR_API_ERROR', 3);
define('DEVCODE_BATCH_ERROR_PARTIAL_FAILURE', 4);
define('DEVCODE_BATCH_ERROR_TIMEOUT', 5);

/**
 * Process multiple submissions in batch mode
 * 
 * @param array $submissions Array of submissions to process. Each submission should have:
 *   - id: The submission ID in the local system
 *   - source_code: The source code to submit
 *   - language_id: The Judge0 language ID
 *   - stdin: The input to the program (optional)
 *   - expected_output: The expected output (optional)
 *   - options: Additional options for Judge0 (optional)
 * @param array $batch_options Additional options for batch processing:
 *   - max_batch_size: Maximum number of submissions per batch (default: 10)
 *   - timeout: Maximum time to wait for results in seconds (default: 30)
 *   - poll_interval: Time between poll requests in seconds (default: 2)
 *   - parallel: Whether to process in parallel (default: true)
 * @return array Results of the batch processing
 */
function devcode_process_batch_submission($submissions, $batch_options = []) {
    global $CFG;
    
    // Check for required parameters
    if (empty($submissions) || !is_array($submissions)) {
        return [
            'status' => 'error',
            'error_code' => DEVCODE_BATCH_ERROR_MISSING_PARAMS,
            'message' => 'No submissions provided or invalid format',
            'time' => time()
        ];
    }
    
    // Get Judge0 configuration
    $judge0_api_url = get_config('mod_devcode', 'judge0_api_url');
    $judge0_api_key = get_config('mod_devcode', 'judge0_api_key');
    
    if (empty($judge0_api_url)) {
        return [
            'status' => 'error',
            'error_code' => DEVCODE_BATCH_ERROR_JUDGE0_CONFIG,
            'message' => 'Judge0 API URL is not configured',
            'time' => time()
        ];
    }
    
    // Set default batch options
    $defaults = [
        'max_batch_size' => 10,
        'timeout' => 30,
        'poll_interval' => 2,
        'parallel' => true
    ];
    
    $options = array_merge($defaults, $batch_options);
    
    // Initialize results array
    $results = [
        'status' => 'success',
        'total' => count($submissions),
        'success' => 0,
        'failed' => 0,
        'time_started' => time(),
        'time_completed' => null,
        'submissions' => []
    ];
    
    // Process submissions in batches
    $batches = array_chunk($submissions, $options['max_batch_size']);
    
    foreach ($batches as $batch_index => $batch) {
        $batch_results = devcode_process_single_batch($batch, $judge0_api_url, $judge0_api_key, $options);
        
        // Merge batch results into overall results
        foreach ($batch_results as $sub_id => $result) {
            $results['submissions'][$sub_id] = $result;
            
            if (isset($result['status']) && $result['status'] === 'success') {
                $results['success']++;
            } else {
                $results['failed']++;
            }
        }
        
        // Check for timeout or cancellation
        if (connection_aborted()) {
            $results['status'] = 'incomplete';
            $results['message'] = 'Batch processing was cancelled';
            break;
        }
    }
    
    // Set completion time
    $results['time_completed'] = time();
    $results['duration'] = $results['time_completed'] - $results['time_started'];
    
    // Check final status
    if ($results['status'] !== 'incomplete' && $results['failed'] > 0) {
        if ($results['failed'] === $results['total']) {
            $results['status'] = 'error';
            $results['error_code'] = DEVCODE_BATCH_ERROR_API_ERROR;
            $results['message'] = 'All submissions failed';
        } else {
            $results['status'] = 'partial';
            $results['error_code'] = DEVCODE_BATCH_ERROR_PARTIAL_FAILURE;
            $results['message'] = $results['failed'] . ' out of ' . $results['total'] . ' submissions failed';
        }
    }
    
    return $results;
}

/**
 * Process a single batch of submissions
 * 
 * @param array $batch Array of submissions to process
 * @param string $judge0_api_url The Judge0 API URL
 * @param string $judge0_api_key The Judge0 API key
 * @param array $options Batch processing options
 * @return array Results for each submission in the batch
 */
function devcode_process_single_batch($batch, $judge0_api_url, $judge0_api_key, $options) {
    $batch_results = [];
    $submissions_data = [];
    $tokens = [];
    
    // First, submit all code
    foreach ($batch as $submission) {
        // Skip submissions without required fields
        if (!isset($submission['id']) || !isset($submission['source_code']) || !isset($submission['language_id'])) {
            $batch_results[$submission['id']] = [
                'status' => 'error',
                'message' => 'Missing required fields for submission',
                'time' => time()
            ];
            continue;
        }
        
        // Prepare submission data
        $sub_data = [
            'source_code' => $submission['source_code'],
            'language_id' => $submission['language_id'],
            'stdin' => isset($submission['stdin']) ? $submission['stdin'] : '',
            'expected_output' => isset($submission['expected_output']) ? $submission['expected_output'] : '',
            'base64_encoded' => false
        ];
        
        // Add additional options
        if (isset($submission['options']) && is_array($submission['options'])) {
            $sub_data = array_merge($sub_data, $submission['options']);
        }
        
        $submissions_data[$submission['id']] = $sub_data;
    }
    
    // Submit batch to Judge0
    $batch_tokens = devcode_submit_batch_to_judge0($submissions_data, $judge0_api_url, $judge0_api_key);
    
    // Process batch submission results
    foreach ($batch_tokens as $sub_id => $token_result) {
        if (isset($token_result['token'])) {
            $tokens[$sub_id] = $token_result['token'];
        } else {
            $batch_results[$sub_id] = [
                'status' => 'error',
                'message' => isset($token_result['message']) ? $token_result['message'] : 'Failed to submit',
                'time' => time()
            ];
        }
    }
    
    // Wait and poll for results
    if (!empty($tokens)) {
        $start_time = time();
        $timeout_reached = false;
        $pending_tokens = $tokens;
        
        while (!empty($pending_tokens) && !$timeout_reached) {
            // Check each pending token
            foreach ($pending_tokens as $sub_id => $token) {
                $result = devcode_get_judge0_result_enhanced($token);
                
                // Check if result is ready
                if (isset($result['status']) && isset($result['status']['id']) && 
                    !in_array($result['status']['id'], [1, 2])) { // Not In Queue or Processing
                    
                    // Store result
                    $batch_results[$sub_id] = [
                        'status' => 'success',
                        'result' => $result,
                        'time' => time()
                    ];
                    
                    // Remove from pending tokens
                    unset($pending_tokens[$sub_id]);
                }
            }
            
            // Check for timeout
            $elapsed_time = time() - $start_time;
            if ($elapsed_time >= $options['timeout']) {
                $timeout_reached = true;
                
                // Mark remaining submissions as pending
                foreach ($pending_tokens as $sub_id => $token) {
                    $batch_results[$sub_id] = [
                        'status' => 'pending',
                        'token' => $token,
                        'message' => 'Processing timeout reached',
                        'time' => time()
                    ];
                }
            }
            
            // If still have pending tokens and not timed out, wait before next poll
            if (!empty($pending_tokens) && !$timeout_reached) {
                sleep($options['poll_interval']);
            }
            
            // Check for connection abort
            if (connection_aborted()) {
                break;
            }
        }
    }
    
    return $batch_results;
}

/**
 * Submit multiple submissions to Judge0 API in one request
 * 
 * @param array $submissions_data Array of submission data, keyed by submission ID
 * @param string $judge0_api_url The Judge0 API URL
 * @param string $judge0_api_key The Judge0 API key
 * @return array Array of token results, keyed by submission ID
 */
function devcode_submit_batch_to_judge0($submissions_data, $judge0_api_url, $judge0_api_key) {
    // Convert associative array to indexed array for Judge0
    $indexed_submissions = array_values($submissions_data);
    $sub_ids = array_keys($submissions_data);
    
    // Prepare the API request
    $batch_url = rtrim($judge0_api_url, '/') . '/submissions/batch';
    $request_data = ['submissions' => $indexed_submissions];
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json'
    ];
    
    // Add API key if available
    if (!empty($judge0_api_key)) {
        $headers[] = "X-Auth-Token: $judge0_api_key";
    }
    
    $json_data = json_encode($request_data);
    
    // Initialize cURL session
    $ch = curl_init($batch_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); // 60 seconds timeout for batch submissions
    
    // Execute the request
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    // Handle connection errors
    if ($response === false) {
        $error_result = [
            'status' => 'error',
            'message' => 'Connection error: ' . $curl_error,
            'time' => time()
        ];
        
        // Return error for all submissions
        $results = [];
        foreach ($sub_ids as $sub_id) {
            $results[$sub_id] = $error_result;
        }
        return $results;
    }
    
    // Handle HTTP errors
    if ($http_code < 200 || $http_code >= 300) {
        $error_result = [
            'status' => 'error',
            'message' => "HTTP Error $http_code: $response",
            'time' => time()
        ];
        
        // Return error for all submissions
        $results = [];
        foreach ($sub_ids as $sub_id) {
            $results[$sub_id] = $error_result;
        }
        return $results;
    }
    
    // Process the response
    $response_data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $error_result = [
            'status' => 'error',
            'message' => 'Invalid JSON response: ' . json_last_error_msg(),
            'time' => time()
        ];
        
        // Return error for all submissions
        $results = [];
        foreach ($sub_ids as $sub_id) {
            $results[$sub_id] = $error_result;
        }
        return $results;
    }
    
    // Map response tokens back to original submission IDs
    $results = [];
    if (isset($response_data['submissions']) && is_array($response_data['submissions'])) {
        foreach ($response_data['submissions'] as $index => $result) {
            $sub_id = isset($sub_ids[$index]) ? $sub_ids[$index] : $index;
            
            if (isset($result['token'])) {
                $results[$sub_id] = [
                    'status' => 'submitted',
                    'token' => $result['token'],
                    'time' => time()
                ];
            } else {
                $results[$sub_id] = [
                    'status' => 'error',
                    'message' => isset($result['error']) ? $result['error'] : 'No token in response',
                    'time' => time()
                ];
            }
        }
    } else {
        // Handle unexpected response format
        $error_result = [
            'status' => 'error',
            'message' => 'Unexpected response format: ' . print_r($response_data, true),
            'time' => time()
        ];
        
        foreach ($sub_ids as $sub_id) {
            $results[$sub_id] = $error_result;
        }
    }
    
    return $results;
}

/**
 * Batch retrieve results for multiple tokens
 * 
 * @param array $tokens Array of tokens, keyed by submission ID
 * @param string $judge0_api_url The Judge0 API URL
 * @param string $judge0_api_key The Judge0 API key
 * @param bool $include_details Whether to include detailed output
 * @return array Results for each token, keyed by submission ID
 */
function devcode_batch_get_results($tokens, $judge0_api_url, $judge0_api_key, $include_details = true) {
    $results = [];
    $tokens_str = [];
    
    // Convert tokens array to comma-separated string, tracking the mapping
    foreach ($tokens as $sub_id => $token) {
        if (!empty($token)) {
            $tokens_str[] = $token;
            $token_map[$token] = $sub_id;
        } else {
            $results[$sub_id] = [
                'status' => 'error',
                'message' => 'Empty token',
                'time' => time()
            ];
        }
    }
    
    if (empty($tokens_str)) {
        return $results;
    }
    
    // Prepare batch get parameters
    $tokens_param = implode(',', $tokens_str);
    $params = [
        'tokens=' . $tokens_param,
        'base64_encoded=false'
    ];
    
    if ($include_details) {
        $params[] = 'fields=*';
    }
    
    // Prepare the API request
    $batch_url = rtrim($judge0_api_url, '/') . '/submissions/batch?' . implode('&', $params);
    $headers = ['Accept: application/json'];
    
    // Add API key if available
    if (!empty($judge0_api_key)) {
        $headers[] = "X-Auth-Token: $judge0_api_key";
    }
    
    // Initialize cURL session
    $ch = curl_init($batch_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); // 60 seconds timeout for batch requests
    
    // Execute the request
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    // Handle connection errors
    if ($response === false) {
        $error_result = [
            'status' => 'error',
            'message' => 'Connection error: ' . $curl_error,
            'time' => time()
        ];
        
        // Return error for all submissions
        foreach ($tokens as $sub_id => $token) {
            if (!isset($results[$sub_id])) {
                $results[$sub_id] = $error_result;
            }
        }
        return $results;
    }
    
    // Handle HTTP errors
    if ($http_code < 200 || $http_code >= 300) {
        $error_result = [
            'status' => 'error',
            'message' => "HTTP Error $http_code: $response",
            'time' => time()
        ];
        
        // Return error for all submissions
        foreach ($tokens as $sub_id => $token) {
            if (!isset($results[$sub_id])) {
                $results[$sub_id] = $error_result;
            }
        }
        return $results;
    }
    
    // Process the response
    $response_data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $error_result = [
            'status' => 'error',
            'message' => 'Invalid JSON response: ' . json_last_error_msg(),
            'time' => time()
        ];
        
        // Return error for all submissions
        foreach ($tokens as $sub_id => $token) {
            if (!isset($results[$sub_id])) {
                $results[$sub_id] = $error_result;
            }
        }
        return $results;
    }
    
    // Process batch results
    if (isset($response_data['submissions']) && is_array($response_data['submissions'])) {
        foreach ($response_data['submissions'] as $result) {
            if (isset($result['token']) && isset($token_map[$result['token']])) {
                $sub_id = $token_map[$result['token']];
                
                // Enhance result with additional info
                if (isset($result['status']) && isset($result['status']['id'])) {
                    $status_info = devcode_get_judge0_status_info($result['status']['id']);
                    $result['status']['info'] = $status_info;
                }
                
                $results[$sub_id] = [
                    'status' => 'success',
                    'result' => $result,
                    'time' => time()
                ];
            }
        }
    }
    
    // Fill any missing results with errors
    foreach ($tokens as $sub_id => $token) {
        if (!isset($results[$sub_id])) {
            $results[$sub_id] = [
                'status' => 'error',
                'message' => 'No result found for token: ' . $token,
                'time' => time()
            ];
        }
    }
    
    return $results;
} 
 