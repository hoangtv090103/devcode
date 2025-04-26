<?php
/*
 * Judge0 API Interface Functions
 * @package    mod_devcode
 */

defined('MOODLE_INTERNAL') || die();

// Yêu cầu file constants để sử dụng các hằng số
require_once(dirname(__FILE__) . '/constants.php');

define('DEVCODE_JUDGE0_ERROR_NONE', 0);
define('DEVCODE_JUDGE0_ERROR_CONNECTION', 1);
define('DEVCODE_JUDGE0_ERROR_HTTP', 2);
define('DEVCODE_JUDGE0_ERROR_RESPONSE', 3);
define('DEVCODE_JUDGE0_ERROR_TIMEOUT', 4);
define('DEVCODE_JUDGE0_ERROR_INVALID_TOKEN', 5);
define('DEVCODE_JUDGE0_ERROR_MISSING_PARAM', 6);

// Thêm các hằng số thiếu
define('DEVCODE_STATUS_TIMEOUT', 5);
define('DEVCODE_STATUS_PARTIALLY_ACCEPTED', 6);
// Note: DEVCODE_MAX_POLL_TIME is defined in constants.php and used from there

function devcode_get_judge0_config()
{
    global $CFG;

    $api_url = null;
    $api_key = null;
    $timeout = null;

    // First try to get settings from Moodle configuration if get_config function exists
    if (function_exists('get_config')) {
        $api_url = get_config('mod_devcode', 'judge0_api_url');
        $api_key = get_config('mod_devcode', 'judge0_api_key');
        $timeout = get_config('mod_devcode', 'judge0_timeout');
    }

    // If settings are not available, fall back to defaults from config.php
    return [
        'judge0_api_url' => !empty($api_url) ? $api_url : ($CFG->devcode['judge0']['api_url'] ?? 'https://judge0-ce.p.rapidapi.com'),
        'judge0_api_key' => !empty($api_key) ? $api_key : ($CFG->devcode['judge0']['api_key'] ?? 'b7cb79bc20msh631e775baf24956p192284jsnc6b0aa67f960'),
        'judge0_timeout' => !empty($timeout) ? $timeout : ($CFG->devcode['judge0']['timeout'] ?? 45),
        'judge0_max_wait' => $CFG->devcode['judge0']['max_wait'] ?? 60,
        'judge0_poll_interval' => $CFG->devcode['judge0']['poll_interval'] ?? 3,
        'judge0_wait_for_result' => $CFG->devcode['judge0']['wait_for_result'] ?? false,
        'judge0_headers' => is_callable($CFG->devcode['judge0']['headers'] ?? null) ?
            call_user_func($CFG->devcode['judge0']['headers']) :
            ['Content-Type: application/json', 'Accept: application/json']
    ];
}

function devcode_send_to_api($submission_data, $config)
{
    if (empty($submission_data) || !is_array($submission_data)) {
        return [
            'error' => true,
            'message' => 'Dữ liệu bài nộp không hợp lệ'
        ];
    }

    // Kiểm tra các trường bắt buộc
    if (empty($submission_data['source_code']) || !is_string($submission_data['source_code'])) {
        debugging('source_code không hợp lệ hoặc trống', DEBUG_DEVELOPER);
        return [
            'error' => true,
            'message' => 'Không tìm thấy source_code hoặc không đúng định dạng'
        ];
    }

    if (empty($submission_data['language_id']) || !is_numeric($submission_data['language_id'])) {
        debugging('language_id không hợp lệ: ' . (isset($submission_data['language_id']) ? $submission_data['language_id'] : 'không có'), DEBUG_DEVELOPER);
        return [
            'error' => true,
            'message' => 'Không tìm thấy language_id hoặc không đúng định dạng'
        ];
    }

    // Ensure source_code doesn't have encoding issues
    $source_code = $submission_data['source_code'];
    
    // Create a modified payload to handle different Judge0 implementations
    $api_payload = [
        'source_code' => $source_code,
        'language_id' => intval($submission_data['language_id']),
    ];
    
    // Add optional fields if they exist
    if (isset($submission_data['stdin'])) {
        $api_payload['stdin'] = $submission_data['stdin'];
    }
    
    if (isset($submission_data['expected_output'])) {
        $api_payload['expected_output'] = $submission_data['expected_output'];
    }
    
    if (isset($submission_data['cpu_time_limit'])) {
        $api_payload['cpu_time_limit'] = $submission_data['cpu_time_limit'];
    }
    
    if (isset($submission_data['memory_limit'])) {
        $api_payload['memory_limit'] = $submission_data['memory_limit'];
    }

    // Set the proper headers based on API type
    $headers = [];
    $is_local = (strpos($config['judge0_api_url'], 'localhost') !== false || strpos($config['judge0_api_url'], '127.0.0.1') !== false);
    $is_rapidapi = (strpos($config['judge0_api_url'], 'rapidapi.com') !== false);
    
    if ($is_rapidapi) {
        // RapidAPI format - using lowercase header names as shown in examples
        $headers = [
            "Content-Type: application/json",
            "x-rapidapi-host: judge0-ce.p.rapidapi.com",
            "x-rapidapi-key: " . $config['judge0_api_key']
        ];
    } else if ($is_local) {
        // For local Judge0 instance
        $headers = ['Content-Type: application/json', 'Accept: application/json'];
    } else if (is_array($config['judge0_headers'] ?? null)) {
        // For regular headers format from config
        $headers = $config['judge0_headers'];
    } else if (!empty($config['judge0_api_key'])) {
        // For other API services that might use a direct API key
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-API-Key: ' . $config['judge0_api_key']
        ];
    } else {
        // Default
        $headers = ['Content-Type: application/json', 'Accept: application/json'];
    }

    $wait_param = isset($config['judge0_wait_for_result']) && $config['judge0_wait_for_result'] ? 'true' : 'false';
    
    // Set up URL based on API type
    if ($is_rapidapi) {
        // RapidAPI format with fields=* parameter
        $url = $config['judge0_api_url'] . '/submissions?base64_encoded=false&wait=' . $wait_param . '&fields=*';
    } else if ($is_local) {
        // For local Judge0, we'll use the token endpoint instead of direct wait parameter
        $url = $config['judge0_api_url'] . '/submissions';
    } else {
        // Default format
        $url = $config['judge0_api_url'] . '/submissions?base64_encoded=false&wait=' . $wait_param;
    }

    debugging('Gửi dữ liệu đến Judge0 API: ' . $url, DEBUG_DEVELOPER);
    debugging('Headers: ' . json_encode($headers), DEBUG_DEVELOPER);

    // Tạo JSON payload và ghi log để debug
    $json_payload = json_encode($api_payload);
    debugging('JSON Payload: ' . $json_payload, DEBUG_DEVELOPER);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => $config['judge0_timeout'],
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $json_payload,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_errno = curl_errno($ch);
    $curl_error = curl_error($ch);

    debugging('HTTP Code: ' . $http_code, DEBUG_DEVELOPER);
    debugging('Raw Response: ' . $response, DEBUG_DEVELOPER);

    if ($curl_errno) {
        debugging('Lỗi cURL: ' . $curl_error, DEBUG_DEVELOPER);
        return [
            'error' => true,
            'message' => 'Lỗi kết nối: ' . $curl_error,
            'response' => $response
        ];
    }

    curl_close($ch);

    if ($http_code >= 400) {
        debugging('Lỗi HTTP: ' . $http_code . ' - Response: ' . $response, DEBUG_DEVELOPER);
        return [
            'error' => true,
            'message' => 'Lỗi API (HTTP ' . $http_code . ')',
            'response' => $response
        ];
    }

    $data = json_decode($response, true);
    debugging('Parsed API response: ' . json_encode($data), DEBUG_DEVELOPER);

    // Different Judge0 implementations might format the response differently
    if (!isset($data['token'])) {
        // Check if it's wrapped in a submission object
        if (isset($data['submission']) && isset($data['submission']['token'])) {
            return $data['submission'];
        }
        
        debugging('Token không tìm thấy trong phản hồi: ' . $response, DEBUG_DEVELOPER);
        return [
            'error' => true,
            'message' => 'Không tìm thấy token trong phản hồi API',
            'response' => $response
        ];
    }

    // If we're using a local Judge0 instance and wait=true was not supported,
    // we need to poll for results
    if ($is_local && isset($data['token'])) {
        $token = $data['token'];
        $poll_result = devcode_poll_submission($token, $config);
        
        // Return only if we got error or valid results
        if (isset($poll_result['error']) || 
            (isset($poll_result['result']) && !empty($poll_result['result']))) {
            return $poll_result;
        }
    }

    return $data;
}

function devcode_poll_submission($token, $config = null)
{
    if (empty($token)) {
        return [
            'error' => true,
            'message' => 'Token is empty',
            'error_code' => DEVCODE_JUDGE0_ERROR_MISSING_PARAM
        ];
    }
    
    if (strpos($token, 'mock_') === 0) {
        debugging('Sử dụng phản hồi giả để thăm dò với token: ' . $token, DEBUG_DEVELOPER);
        return [
            'token' => $token,
            'mock' => true,
            'result' => [
                'status' => ['id' => 3, 'description' => 'Accepted'],
                'stdout' => '2',
                'time' => '0.001',
                'memory' => 9216
            ]
        ];
    }
    
    // Get configuration
    if ($config === null) {
        $config = devcode_get_judge0_config();
    }
    
    $url = rtrim($config['judge0_api_url'], '/') . '/submissions/' . $token;
    $url .= '?base64_encoded=false&fields=*';
    
    debugging('Polling URL: ' . $url, DEBUG_DEVELOPER);

    // Prepare headers based on configuration
    $headers = [];
    if (isset($config['judge0_headers']) && is_array($config['judge0_headers'])) {
        foreach ($config['judge0_headers'] as $key => $value) {
            $headers[] = "$key: $value";
        }
    }
    
    // Add the API key if provided
    if (!empty($config['judge0_api_key'])) {
        $headers[] = 'x-rapidapi-host: judge0-ce.p.rapidapi.com';
        $headers[] = 'x-rapidapi-key: ' . $config['judge0_api_key'];
    }
    
    debugging('Poll Headers: ' . json_encode($headers), DEBUG_DEVELOPER);

    $start = time();
    $max_wait = $config['judge0_max_wait'];
    $poll_interval = $config['judge0_poll_interval'];
    $retries = 0;
    $max_retries = 3;
    $retry_delay = 2;
    $poll_result = null;
    
    do {
        if (time() - $start > $max_wait) {
            return [
                'error' => true,
                'message' => 'Maximum wait time exceeded',
                'error_code' => DEVCODE_JUDGE0_ERROR_TIMEOUT
            ];
        }
        
        try {
            $ch = curl_init($url);
            if (!empty($headers)) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            }
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            // Log the raw response for debugging
            debugging('Poll raw response: ' . $response, DEBUG_DEVELOPER);
            
            if (curl_errno($ch)) {
                $error = curl_error($ch);
                curl_close($ch);
                if (++$retries <= $max_retries) {
                    debugging("Lỗi kết nối khi thăm dò, thử lại ($retries/$max_retries): $error", DEBUG_DEVELOPER);
                    sleep($retry_delay);
                    continue;
                }
                return [
                    'error' => true,
                    'message' => 'Connection error: ' . $error,
                    'error_code' => DEVCODE_JUDGE0_ERROR_CONNECTION
                ];
            }
            curl_close($ch);
            
            debugging('Poll HTTP Code: ' . $http_code, DEBUG_DEVELOPER);
            
            if ($http_code < 200 || $http_code >= 300) {
                if ($http_code == 401) {
                    return [
                        'error' => true,
                        'message' => 'Unauthorized: Invalid API key',
                        'error_code' => DEVCODE_JUDGE0_ERROR_AUTH
                    ];
                }
                if ($http_code == 429 && ++$retries <= $max_retries) {
                    $wait = $retry_delay * (2 * $retries);
                    debugging("Vượt quá giới hạn tốc độ khi thăm dò, thử lại ($retries/$max_retries) sau $wait giây", DEBUG_DEVELOPER);
                    sleep($wait);
                    continue;
                }
                if (++$retries <= $max_retries) {
                    debugging("Lỗi HTTP khi thăm dò: $http_code, thử lại ($retries/$max_retries)", DEBUG_DEVELOPER);
                    sleep($retry_delay);
                    continue;
                }
                return [
                    'error' => true,
                    'message' => 'HTTP error: ' . $http_code,
                    'error_code' => DEVCODE_JUDGE0_ERROR_HTTP
                ];
            }
            
            // Improve JSON parsing with better error handling for truncated responses
            if (empty($response)) {
                if (++$retries <= $max_retries) {
                    debugging('Empty response from API, retrying (' . $retries . '/' . $max_retries . ')', DEBUG_DEVELOPER);
                    sleep($retry_delay);
                    continue;
                }
                return [
                    'error' => true,
                    'message' => 'Empty response from API',
                    'error_code' => DEVCODE_JUDGE0_ERROR_EMPTY_RESPONSE
                ];
            }
            
            // Check if the response looks like valid JSON
            if (!preg_match('/^\s*[\{\[]/', $response) || !preg_match('/[\}\]]\s*$/', $response)) {
                if (++$retries <= $max_retries) {
                    debugging('Invalid JSON format, retrying (' . $retries . '/' . $max_retries . '): ' . $response, DEBUG_DEVELOPER);
                    sleep($retry_delay);
                    continue;
                }
                return [
                    'error' => true,
                    'message' => 'Invalid JSON format from API',
                    'error_code' => DEVCODE_JUDGE0_ERROR_INVALID_JSON
                ];
            }
            
            $result = json_decode($response, true);
            if ($result === null) {
                if (++$retries <= $max_retries) {
                    debugging('Lỗi phân tích JSON khi thăm dò, thử lại (' . $retries . '/' . $max_retries . '): ' . json_last_error_msg(), DEBUG_DEVELOPER);
                    sleep($retry_delay);
                    continue;
                }
                return [
                    'error' => true,
                    'message' => 'JSON parsing error: ' . json_last_error_msg(),
                    'error_code' => DEVCODE_JUDGE0_ERROR_RESPONSE
                ];
            }
            $poll_result = $result;
            debugging('Poll result parsed: ' . json_encode($poll_result), DEBUG_DEVELOPER);
            break;
        } catch (Exception $e) {
            if (++$retries <= $max_retries) {
                debugging('Exception during polling, retrying (' . $retries . '/' . $max_retries . '): ' . $e->getMessage(), DEBUG_DEVELOPER);
                sleep($retry_delay);
                continue;
            }
            return [
                'error' => true,
                'message' => 'Exception: ' . $e->getMessage(),
                'error_code' => DEVCODE_JUDGE0_ERROR_EXCEPTION
            ];
        }
    } while ($retries <= $max_retries);
    
    if ($poll_result === null) {
        return [
            'error' => true,
            'message' => 'Failed to get results after multiple retries',
            'error_code' => DEVCODE_JUDGE0_ERROR_MAX_RETRIES
        ];
    }
    
    // Check status to see if the submission is complete
    if (isset($poll_result['status']) && isset($poll_result['status']['id'])) {
        $status_id = (int)$poll_result['status']['id'];
        
        // Process ID 1 and 2 are still in queue or processing
        if ($status_id <= 2) {
            // Wait and then poll again later
            sleep($poll_interval);
            return [
                'token' => $token,
                'processing' => true,
                'status_id' => $status_id
            ];
        }
    }
    
    return [
        'token' => $token,
        'result' => $poll_result
    ];
}

function devcode_process_batch_submission($submissions, $config = null)
{
    if (empty($submissions) || !is_array($submissions)) {
        return ['error' => DEVCODE_JUDGE0_ERROR_MISSING_PARAM, 'message' => 'Mảng submissions thiếu hoặc không hợp lệ'];
    }
    if (!$config) $config = devcode_get_judge0_config();
    $results = ['submissions' => count($submissions), 'processed' => 0, 'errors' => 0, 'results' => []];
    foreach ($submissions as $i => $s) {
        if (empty($s['source_code']) || empty($s['language_id'])) {
            $results['errors']++;
            $results['results'][$i] = ['error' => DEVCODE_JUDGE0_ERROR_MISSING_PARAM, 'message' => 'Thiếu tham số bắt buộc: source_code hoặc language_id'];
            continue;
        }
        $api_result = devcode_send_to_api($s, $config);
        if (!isset($api_result['error']) && isset($api_result['token'])) {
            $poll_result = devcode_poll_submission($api_result['token'], $config);
            if (!isset($poll_result['error'])) {
                $results['processed']++;
                $results['results'][$i] = $poll_result;
            } else {
                $results['errors']++;
                $results['results'][$i] = $poll_result;
            }
        } else {
            $results['errors']++;
            $results['results'][$i] = $api_result;
        }
    }
    return $results;
}

function devcode_get_languages($config = null)
{
    if (!$config) $config = devcode_get_judge0_config();
    $url = rtrim($config['judge0_api_url'], '/') . '/languages';

    // Prepare headers based on configuration
    $headers = [];
    $is_local = (strpos($config['judge0_api_url'], 'localhost') !== false || strpos($config['judge0_api_url'], '127.0.0.1') !== false);
    $is_rapidapi = (strpos($config['judge0_api_url'], 'rapidapi.com') !== false);
    
    if ($is_rapidapi) {
        // RapidAPI format - using lowercase header names as shown in examples
        $headers = [
            "Content-Type: application/json",
            "x-rapidapi-host: judge0-ce.p.rapidapi.com",
            "x-rapidapi-key: " . $config['judge0_api_key']
        ];
    } else if ($is_local) {
        // For local Judge0 instance
        $headers = ['Content-Type: application/json', 'Accept: application/json'];
    } else if (is_array($config['judge0_headers'] ?? null)) {
        // For regular headers format from config
        $headers = $config['judge0_headers'];
    } else if (!empty($config['judge0_api_key'])) {
        // For other API services that might use a direct API key
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-API-Key: ' . $config['judge0_api_key']
        ];
    } else {
        // Default
        $headers = ['Content-Type: application/json', 'Accept: application/json'];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => $config['judge0_timeout'],
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        return ['error' => DEVCODE_JUDGE0_ERROR_CONNECTION, 'message' => 'Lỗi kết nối: ' . $error];
    }
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($http_code < 200 || $http_code >= 300) {
        return ['error' => DEVCODE_JUDGE0_ERROR_HTTP, 'message' => 'Lỗi HTTP: ' . $http_code, 'response' => $response];
    }
    $result = json_decode($response, true);
    if ($result === null) {
        return ['error' => DEVCODE_JUDGE0_ERROR_RESPONSE, 'message' => 'Không thể phân tích phản hồi: ' . json_last_error_msg(), 'response' => $response];
    }
    return $result;
}

function devcode_get_status_mapping()
{
    return [
        1 => 'Đang chờ',
        2 => 'Đang xử lý',
        3 => 'Được chấp nhận',
        4 => 'Sai đáp án',
        5 => 'Vượt quá giới hạn thời gian',
        6 => 'Lỗi biên dịch',
        7 => 'Lỗi runtime (SIGSEGV)',
        8 => 'Lỗi runtime (SIGXFSZ)',
        9 => 'Lỗi runtime (SIGFPE)',
        10 => 'Lỗi runtime (SIGABRT)',
        11 => 'Lỗi runtime (NZEC)',
        12 => 'Lỗi runtime (Khác)',
        13 => 'Lỗi nội bộ',
        14 => 'Lỗi định dạng thực thi'
    ];
}

function devcode_map_status($status_id)
{
    $m = devcode_get_status_mapping();
    return $m[$status_id] ?? 'Trạng thái không xác định';
}

/**
 * So sánh đầu ra để kiểm tra kết quả
 *
 * @param string $expected Kết quả mong đợi
 * @param string $actual Kết quả thực tế
 * @return bool Trả về true nếu khớp, false nếu không khớp
 */
function devcode_compare_outputs($expected, $actual)
{
    $expected = trim($expected);
    $actual = trim($actual);

    // Xử lý khác biệt về ký tự xuống dòng
    $expected = str_replace("\r\n", "\n", $expected);
    $actual = str_replace("\r\n", "\n", $actual);

    // Strip any trailing newlines that might affect comparison
    $expected = rtrim($expected, "\n");
    $actual = rtrim($actual, "\n");

    return $expected === $actual;
}

/**
 * Chuyển đổi trạng thái Judge0 sang trạng thái của module
 *
 * @param int $judge0_status ID trạng thái từ Judge0
 * @return int Trạng thái tương ứng trong module
 */
function devcode_map_judge0_status($judge0_status)
{
    switch ($judge0_status) {
        case 3: // Accepted
            return DEVCODE_STATUS_ACCEPTED;
        case 4: // Wrong Answer
            return DEVCODE_STATUS_WRONG_ANSWER;
        case 5: // Time Limit Exceeded
            return DEVCODE_STATUS_TIME_LIMIT;
        case 6: // Compilation Error
            return DEVCODE_STATUS_COMPILE_ERROR;
        case 7: // Runtime Error (SIGSEGV)
        case 8: // Runtime Error (SIGXFSZ)
        case 9: // Runtime Error (SIGFPE)
        case 10: // Runtime Error (SIGABRT)
        case 11: // Runtime Error (NZEC)
        case 12: // Runtime Error (Other)
            return DEVCODE_STATUS_RUNTIME_ERROR;
        case 13: // Internal Error
        case 14: // Exec Format Error
        default:
            return DEVCODE_STATUS_ERROR;
    }
}

/**
 * Grade a submission with Judge0.
 *
 * @param object $submission The submission record to grade.
 * @param object $devcode The devcode instance record.
 * @param object $modulecontext The module context.
 * @return object Results of submission with grade.
 */
function devcode_grade_with_judge0($submission, $devcode, $modulecontext)
{
    global $DB, $CFG;

    if (empty($submission->code)) {
        debugging('Empty submission code', DEBUG_DEVELOPER);
        $submission->status = DEVCODE_STATUS_ERROR;
        $submission->error_msg = 'Empty submission code';
        return $submission;
    }

    if (empty($submission->language_id)) {
        debugging('Empty language_id', DEBUG_DEVELOPER);
        $submission->status = DEVCODE_STATUS_ERROR;
        $submission->error_msg = 'No language selected';
        return $submission;
    }

    $language_id = (int) $submission->language_id;
    debugging('Processing submission - source_code length: ' . strlen($submission->code) . ', language_id: ' . $language_id, DEBUG_DEVELOPER);

    $config = devcode_get_judge0_config();
    if ($config === false) {
        $submission->status = DEVCODE_STATUS_ERROR;
        $submission->error_msg = 'Judge0 configuration error';
        return $submission;
    }

    // Ensure submission->code doesn't have encoding issues
    $code = $submission->code;

    // Test cases for the submission.
    $tcs = $DB->get_records('devcode_testcases', array('devcodeid' => $devcode->id), 'id ASC');
    if (empty($tcs)) {
        debugging('No test cases found', DEBUG_DEVELOPER);
        $submission->status = DEVCODE_STATUS_ERROR;
        $submission->error_msg = 'No test cases found';
        return $submission;
    }

    $submission->tests_total = count($tcs);
    $submission->tests_passed = 0;
    $test_results = array();

    foreach ($tcs as $tc) {
        // Prepare data.
        $api_data = [
            'source_code' => $code,
            'language_id' => $language_id,
            'stdin' => isset($tc->input) ? $tc->input : '',
            'expected_output' => isset($tc->output) ? $tc->output : '',
            'cpu_time_limit' => isset($devcode->time_limit) ? ($devcode->time_limit / 1000) : 5,
            'memory_limit' => isset($devcode->memory_limit) ? $devcode->memory_limit : 128000
        ];

        debugging('API data: ' . json_encode($api_data), DEBUG_DEVELOPER);

        // Send code to Judge0 API.
        $submission_data = devcode_send_to_api($api_data, $config);

        // Check for errors in submission
        if (isset($submission_data['error']) && $submission_data['error'] === true) {
            debugging('API submission error: ' . $submission_data['message'], DEBUG_DEVELOPER);
            $submission->status = DEVCODE_STATUS_ERROR;
            $submission->error_msg = 'Judge0 API error: ' . $submission_data['message'];
            return $submission;
        }

        if (!isset($submission_data['token'])) {
            debugging('No token returned from API: ' . json_encode($submission_data), DEBUG_DEVELOPER);
            $submission->status = DEVCODE_STATUS_ERROR;
            $submission->error_msg = 'Judge0 API error: No token returned';
            return $submission;
        }

        // Poll for results.
        $token = $submission_data['token'];
        $poll_count = 0;
        $result = null;
        $error_code = DEVCODE_JUDGE0_ERROR_NONE;

        // Check if the result was already returned in the submission data
        if (isset($submission_data['result']) && !empty($submission_data['result'])) {
            $result = $submission_data['result'];
            debugging('Result already available in submission data: ' . json_encode($result), DEBUG_DEVELOPER);
        } else {
            // Need to poll for results
            while ($poll_count < DEVCODE_MAX_POLL_TIME) {
                $poll_count++;

                // Delay between polls.
                sleep(1);

                // Get submission status.
                $poll_result = devcode_poll_submission($token, $config);
                
                // Check for errors during polling
                if (isset($poll_result['error'])) {
                    debugging('API polling error: ' . $poll_result['message'], DEBUG_DEVELOPER);
                    $error_code = $poll_result['error'];
                    break;
                }

                // Check if processing is complete.
                if (isset($poll_result['result']) && !empty($poll_result['result'])) {
                    $result = $poll_result['result'];
                    break;
                }
            }

            if ($poll_count >= DEVCODE_MAX_POLL_TIME) {
                debugging('Max poll count reached', DEBUG_DEVELOPER);
                $submission->status = DEVCODE_STATUS_TIMEOUT;
                $submission->error_msg = 'Judge0 API timeout';
                return $submission;
            }
        }

        if ($error_code != DEVCODE_JUDGE0_ERROR_NONE) {
            debugging('API polling error: ' . $error_code, DEBUG_DEVELOPER);
            $submission->status = DEVCODE_STATUS_ERROR;
            $submission->error_msg = 'Judge0 API polling error: ' . $error_code;
            return $submission;
        }

        // Process test result.
        $test_result = new stdClass();
        $test_result->test_id = $tc->id;
        $test_result->input = $tc->input ?? '';
        $test_result->expected = $tc->output ?? '';
        $test_result->time = 0;  // Default execution time
        $test_result->memory = 0; // Default memory usage

        // Handle different response formats
        if (!is_array($result) || empty($result)) {
            $test_result->status = DEVCODE_STATUS_ERROR;
            $test_result->message = 'Empty or invalid response from Judge0';
            $test_result->actual = '';
        } else if (isset($result['result']) && is_array($result['result'])) {
            // Handle wrapped result format
            $actual_result = $result['result'];
            
            // Extract time and memory info if available
            if (isset($actual_result['time'])) {
                $test_result->time = floatval($actual_result['time']) * 1000; // Convert to ms
            }
            if (isset($actual_result['memory'])) {
                $test_result->memory = floatval($actual_result['memory']);
            }
            
            if (!empty($actual_result['stderr'])) {
                $test_result->status = DEVCODE_STATUS_ERROR;
                $test_result->message = $actual_result['stderr'];
                $test_result->actual = '';
            } else if (isset($actual_result['status']) && isset($actual_result['status']['id'])) {
                // Check status_id first to determine if there was an execution error
                if ($actual_result['status']['id'] != 3) {
                    // Map the Judge0 status to our status
                    $test_result->status = devcode_map_judge0_status($actual_result['status']['id']);
                    $test_result->message = $actual_result['status']['description'] ?? 'Unknown error';
                    $test_result->actual = $actual_result['stdout'] ?? '';
                } else {
                    // Status is "Accepted", now compare the actual output with expected output
                    $test_result->actual = $actual_result['stdout'] ?? '';
                    
                    // Compare outputs
                    if (devcode_compare_outputs($test_result->expected, $test_result->actual)) {
                        $test_result->status = DEVCODE_STATUS_ACCEPTED;
                        $test_result->message = 'Correct output';
                        $submission->tests_passed++;
                    } else {
                        $test_result->status = DEVCODE_STATUS_WRONG_ANSWER;
                        $test_result->message = 'Wrong output';
                    }
                }
            } else {
                // No status available, compare outputs directly
                $test_result->actual = $actual_result['stdout'] ?? '';
                
                if (devcode_compare_outputs($test_result->expected, $test_result->actual)) {
                    $test_result->status = DEVCODE_STATUS_ACCEPTED;
                    $test_result->message = 'Correct output';
                    $submission->tests_passed++;
                } else {
                    $test_result->status = DEVCODE_STATUS_WRONG_ANSWER;
                    $test_result->message = 'Wrong output';
                }
            }
        } else {
            // Handle direct response format
            // Extract time and memory info if available
            if (isset($result['time'])) {
                $test_result->time = floatval($result['time']) * 1000; // Convert to ms
            }
            if (isset($result['memory'])) {
                $test_result->memory = floatval($result['memory']);
            }
            
            if (!empty($result['stderr'])) {
                $test_result->status = DEVCODE_STATUS_ERROR;
                $test_result->message = $result['stderr'];
                $test_result->actual = '';
            } else if (isset($result['status']) && isset($result['status']['id'])) {
                // Check status_id first to determine if there was an execution error
                if ($result['status']['id'] != 3) {
                    // Map the Judge0 status to our status
                    $test_result->status = devcode_map_judge0_status($result['status']['id']);
                    $test_result->message = $result['status']['description'] ?? 'Unknown error';
                    $test_result->actual = $result['stdout'] ?? '';
                } else {
                    // Status is "Accepted", now compare the actual output with expected output
                    $test_result->actual = $result['stdout'] ?? '';
                    
                    if (devcode_compare_outputs($test_result->expected, $test_result->actual)) {
                        $test_result->status = DEVCODE_STATUS_ACCEPTED;
                        $test_result->message = 'Correct output';
                        $submission->tests_passed++;
                    } else {
                        $test_result->status = DEVCODE_STATUS_WRONG_ANSWER;
                        $test_result->message = 'Wrong output';
                    }
                }
            } else {
                // No status available, compare outputs directly
                $test_result->actual = $result['stdout'] ?? '';
                
                if (devcode_compare_outputs($test_result->expected, $test_result->actual)) {
                    $test_result->status = DEVCODE_STATUS_ACCEPTED;
                    $test_result->message = 'Correct output';
                    $submission->tests_passed++;
                } else {
                    $test_result->status = DEVCODE_STATUS_WRONG_ANSWER;
                    $test_result->message = 'Wrong output';
                }
            }
        }

        $test_results[] = $test_result;
    }

    // Calculate final status.
    if ($submission->tests_passed == $submission->tests_total) {
        $submission->status = DEVCODE_STATUS_ACCEPTED;
        $submission->message = get_string('allteststpassed', 'devcode');
    } else if ($submission->tests_passed > 0) {
        $submission->status = DEVCODE_STATUS_PARTIALLY_ACCEPTED;
        $submission->message = get_string('someteststpassed', 'devcode', 
            ['passed' => $submission->tests_passed, 'total' => $submission->tests_total]);
    } else {
        $submission->status = DEVCODE_STATUS_WRONG_ANSWER;
        $submission->message = get_string('noteststpassed', 'devcode');
    }

    // Calculate grade.
    // Use total_points field instead of grade (which doesn't exist)
    $total_points = isset($devcode->total_points) ? $devcode->total_points : 10.0;
    $submission->grade = ($submission->tests_passed / $submission->tests_total) * $total_points;
    $submission->test_results = json_encode($test_results);

    return $submission;
}
