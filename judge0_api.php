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

// Các hằng số này đã được định nghĩa trong constants.php
// define('DEVCODE_STATUS_TIMEOUT', 5);
// define('DEVCODE_STATUS_PARTIALLY_ACCEPTED', 6);

// Note: DEVCODE_MAX_POLL_TIME is defined in constants.php and used from there

function devcode_get_judge0_config()
{
    global $CFG;

    // Ensure config.php is loaded if $CFG->devcode is not set
    if (!isset($CFG->devcode)) {
        require_once(dirname(__FILE__) . '/config.php');
    }

    // Default configuration values for Judge0
    $defaults = [
        'api_url' => 'https://judge0-ce.p.rapidapi.com',
        'api_key' => null,
        'timeout' => 30, // Default timeout for single API call
        'max_wait' => 60, // Max wait time for polling results
        'poll_interval' => 2, // Interval between polling requests
        'wait_for_result' => false, // Whether submissions endpoint should wait for result
        'default_time_limit' => 2, // Default time limit for execution in seconds
        'default_memory_limit' => 128000, // Default memory limit in KB (128MB)
    ];

    // Merge with settings from config.php or Moodle admin settings
    $config = array_merge($defaults, (array)($CFG->devcode['judge0'] ?? []));

    // Construct the final configuration array for Judge0 API calls
    return [
        'judge0_api_url' => rtrim($config['api_url'], '/'),
        'judge0_api_key' => $config['api_key'],
        'judge0_timeout' => (int)$config['timeout'],
        'judge0_max_wait' => (int)$config['max_wait'],
        'judge0_poll_interval' => (int)$config['poll_interval'],
        'judge0_wait_for_result' => (bool)$config['wait_for_result'],
        'judge0_default_time_limit' => (float)$config['default_time_limit'],
        'judge0_default_memory_limit' => (int)$config['default_memory_limit'],
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

    // Prepare payload
    // Explicitly define the payload to avoid sending unwanted data from $submission_data
    $payload = [
        'source_code' => $source_code,
        'language_id' => (int)$submission_data['language_id'],
    ];

    if (isset($submission_data['stdin'])) {
        $payload['stdin'] = $submission_data['stdin'];
    }
    if (isset($submission_data['expected_output'])) {
        $payload['expected_output'] = $submission_data['expected_output'];
    }
    if (isset($submission_data['cpu_time_limit'])) {
        $payload['cpu_time_limit'] = (float)$submission_data['cpu_time_limit'];
    }
    if (isset($submission_data['memory_limit'])) {
        $payload['memory_limit'] = (int)$submission_data['memory_limit']; // Added memory_limit
    }
    if (isset($submission_data['compiler_options'])) {
        $payload['compiler_options'] = $submission_data['compiler_options'];
    }
    if (isset($submission_data['command_line_arguments'])) {
        $payload['command_line_arguments'] = $submission_data['command_line_arguments'];
    }
    // Add wall_time_limit if needed, Judge0 default is 5s. We can make this configurable.
    // $payload['wall_time_limit'] = $submission_data['wall_time_limit'] ?? 10; 

    // If wait_for_result is enabled in config, use submissions?base64_encoded=false&wait=true
    $waitparam = $config['judge0_wait_for_result'] ? '&wait=true' : '';
    $url = $config['judge0_api_url'] . '/submissions?base64_encoded=false' . $waitparam;

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

    debugging('Gửi dữ liệu đến Judge0 API: ' . $url, DEBUG_DEVELOPER);
    debugging('Headers: ' . json_encode($headers), DEBUG_DEVELOPER);

    // Tạo JSON payload và ghi log để debug
    $json_payload = json_encode($payload);
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

    // If using base64_encoded=true, decode relevant fields in the response
    if (strpos($url, 'base64_encoded=true') !== false && !empty($data)) {
        // If result is directly in the response
        if (isset($data['stdout']) && !empty($data['stdout'])) {
            $data['stdout'] = base64_decode($data['stdout']);
        }
        if (isset($data['stderr']) && !empty($data['stderr'])) {
            $data['stderr'] = base64_decode($data['stderr']);
        }
        if (isset($data['compile_output']) && !empty($data['compile_output'])) {
            $data['compile_output'] = base64_decode($data['compile_output']);
        }
        if (isset($data['message']) && !empty($data['message'])) {
            $data['message'] = base64_decode($data['message']);
        }
    }

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
        if (
            isset($poll_result['error']) ||
            (isset($poll_result['result']) && !empty($poll_result['result']))
        ) {
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
    $url .= '?base64_encoded=true&fields=*';

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

            $poll_result = json_decode($response, true);
            if ($poll_result === null) {
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

            // If we're using base64_encoded=true, decode the fields
            if (strpos($url, 'base64_encoded=true') !== false && !empty($poll_result)) {
                // Decode stdout if present
                if (isset($poll_result['stdout']) && !empty($poll_result['stdout'])) {
                    $poll_result['stdout'] = base64_decode($poll_result['stdout']);
                }

                // Decode stderr if present
                if (isset($poll_result['stderr']) && !empty($poll_result['stderr'])) {
                    $poll_result['stderr'] = base64_decode($poll_result['stderr']);
                }

                // Decode compile_output if present
                if (isset($poll_result['compile_output']) && !empty($poll_result['compile_output'])) {
                    $poll_result['compile_output'] = base64_decode($poll_result['compile_output']);
                }

                // Decode message if present
                if (isset($poll_result['message']) && !empty($poll_result['message'])) {
                    $poll_result['message'] = base64_decode($poll_result['message']);
                }
            }

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
        $submission->message = get_string(
            'someteststpassed',
            'devcode',
            ['passed' => $submission->tests_passed, 'total' => $submission->tests_total]
        );
    } else {
        $submission->status = DEVCODE_STATUS_WRONG_ANSWER;
        $submission->message = get_string('noteststpassed', 'devcode');
    }

    // Calculate total points and earned points from test cases
    $total_points = 0;
    $earned_points = 0;

    // Calculate total available points
    foreach ($test_results as $test_result) {
        $testcase = $DB->get_record('devcode_testcases', array('id' => $test_result->test_id), 'points');
        if ($testcase) {
            $total_points += $testcase->points;

            // Add points if test passed
            if ($test_result->status === DEVCODE_STATUS_ACCEPTED) {
                $earned_points += $testcase->points;
                $test_result->earned_points = $testcase->points;
            } else {
                $test_result->earned_points = 0;
            }
        }
    }

    // Set grade to earned points
    $submission->grade = $earned_points;
    $submission->test_results = json_encode($test_results);

    return $submission;
}

/**
 * Gửi nhiều bài nộp dưới dạng batch submission lên Judge0 API
 * 
 * @param array $batch_submissions Mảng chứa các thông tin bài nộp (mỗi phần tử cần có language_id và source_code)
 * @param array $config Cấu hình Judge0 API (nếu null, sẽ lấy từ cài đặt)
 * @return array Mảng chứa kết quả từ API, bao gồm tokens hoặc error message
 */
function devcode_send_batch_to_judge0($batch_submissions, $config = null)
{
    if (empty($config)) {
        $config = devcode_get_judge0_config();
    }

    if (empty($batch_submissions) || !is_array($batch_submissions)) {
        return [
            'error' => true,
            'message' => 'Dữ liệu batch submissions không hợp lệ'
        ];
    }

    // Chuẩn bị dữ liệu cho API
    $submissions = [];
    foreach ($batch_submissions as $submission) {
        if (empty($submission['source_code']) || empty($submission['language_id'])) {
            continue;
        }

        $sub = [
            'language_id' => intval($submission['language_id']),
            'source_code' => $submission['source_code']
        ];

        // Thêm các trường tùy chọn nếu có
        if (!empty($submission['stdin'])) {
            $sub['stdin'] = $submission['stdin'];
        }
        if (!empty($submission['expected_output'])) {
            $sub['expected_output'] = $submission['expected_output'];
        }
        if (!empty($submission['cpu_time_limit'])) {
            $sub['cpu_time_limit'] = $submission['cpu_time_limit'];
        }
        if (!empty($submission['memory_limit'])) {
            $sub['memory_limit'] = $submission['memory_limit'];
        }

        $submissions[] = $sub;
    }

    if (empty($submissions)) {
        return [
            'error' => true,
            'message' => 'Không có dữ liệu hợp lệ để gửi trong batch'
        ];
    }

    $api_payload = ['submissions' => $submissions];

    // Tạo URL và headers dựa trên cấu hình
    $is_rapidapi = (strpos($config['judge0_api_url'], 'rapidapi.com') !== false);
    $is_local = (strpos($config['judge0_api_url'], 'localhost') !== false || strpos($config['judge0_api_url'], '127.0.0.1') !== false);

    // Chuẩn bị headers
    $headers = [];
    if ($is_rapidapi) {
        $headers = [
            "Content-Type: application/json",
            "x-rapidapi-host: judge0-ce.p.rapidapi.com",
            "x-rapidapi-key: " . $config['judge0_api_key']
        ];
    } else if ($is_local) {
        $headers = ['Content-Type: application/json', 'Accept: application/json'];
    } else {
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-API-Key: ' . $config['judge0_api_key']
        ];
    }

    // Tạo URL
    $base_url = rtrim($config['judge0_api_url'], '/');
    $batch_endpoint = "/submissions/batch";
    $url = $base_url . $batch_endpoint . "?base64_encoded=true";

    // Thực hiện curl request
    $json_payload = json_encode($api_payload);

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

    curl_close($ch);

    if ($curl_errno) {
        return [
            'error' => true,
            'message' => 'Lỗi kết nối: ' . $curl_error,
            'response' => $response
        ];
    }

    if ($http_code >= 400) {
        return [
            'error' => true,
            'message' => 'Lỗi API (HTTP ' . $http_code . ')',
            'response' => $response
        ];
    }

    $data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'error' => true,
            'message' => 'Lỗi khi parse JSON response: ' . json_last_error_msg(),
            'response' => $response
        ];
    }

    // Decode base64 fields in submissions if needed
    if (strpos($url, 'base64_encoded=true') !== false && !empty($data['submissions'])) {
        foreach ($data['submissions'] as &$submission) {
            // Decode stdout if present
            if (isset($submission['stdout']) && !empty($submission['stdout'])) {
                $submission['stdout'] = base64_decode($submission['stdout']);
            }

            // Decode stderr if present
            if (isset($submission['stderr']) && !empty($submission['stderr'])) {
                $submission['stderr'] = base64_decode($submission['stderr']);
            }

            // Decode compile_output if present
            if (isset($submission['compile_output']) && !empty($submission['compile_output'])) {
                $submission['compile_output'] = base64_decode($submission['compile_output']);
            }

            // Decode message if present
            if (isset($submission['message']) && !empty($submission['message'])) {
                $submission['message'] = base64_decode($submission['message']);
            }
        }
    }

    return $data;
}

/**
 * Lấy kết quả từ batch submissions dựa trên danh sách tokens
 *
 * @param array $tokens Mảng chứa các tokens từ batch submissions
 * @param array $config Cấu hình Judge0 API
 * @return array Mảng chứa kết quả của các submissions
 */
function devcode_get_batch_results($tokens, $config = null)
{
    if (empty($config)) {
        $config = devcode_get_judge0_config();
    }

    if (empty($tokens) || !is_array($tokens)) {
        return [
            'error' => true,
            'message' => 'Tokens không hợp lệ'
        ];
    }

    // Nối các tokens bằng dấu phẩy
    $tokens_param = implode(',', $tokens);

    // Tạo URL và headers dựa trên cấu hình
    $is_rapidapi = (strpos($config['judge0_api_url'], 'rapidapi.com') !== false);
    $is_local = (strpos($config['judge0_api_url'], 'localhost') !== false || strpos($config['judge0_api_url'], '127.0.0.1') !== false);

    // Chuẩn bị headers
    $headers = [];
    if ($is_rapidapi) {
        $headers = [
            "x-rapidapi-host: judge0-ce.p.rapidapi.com",
            "x-rapidapi-key: " . $config['judge0_api_key']
        ];
    } else if ($is_local) {
        $headers = ['Accept: application/json'];
    } else {
        $headers = [
            'Accept: application/json',
            'X-API-Key: ' . $config['judge0_api_key']
        ];
    }

    // Tạo URL
    $base_url = rtrim($config['judge0_api_url'], '/');
    $batch_endpoint = "/submissions/batch";
    $url = $base_url . $batch_endpoint . "?tokens=" . urlencode($tokens_param) . "&base64_encoded=true&fields=*";

    // Thực hiện curl request
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
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_errno = curl_errno($ch);
    $curl_error = curl_error($ch);

    curl_close($ch);

    if ($curl_errno) {
        return [
            'error' => true,
            'message' => 'Lỗi kết nối: ' . $curl_error,
            'response' => $response
        ];
    }

    if ($http_code >= 400) {
        return [
            'error' => true,
            'message' => 'Lỗi API (HTTP ' . $http_code . ')',
            'response' => $response
        ];
    }

    $data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'error' => true,
            'message' => 'Lỗi khi parse JSON response: ' . json_last_error_msg(),
            'response' => $response
        ];
    }

    // Decode base64 fields in submissions if needed
    if (strpos($url, 'base64_encoded=true') !== false && !empty($data['submissions'])) {
        foreach ($data['submissions'] as &$submission) {
            // Decode stdout if present
            if (isset($submission['stdout']) && !empty($submission['stdout'])) {
                $submission['stdout'] = base64_decode($submission['stdout']);
            }

            // Decode stderr if present
            if (isset($submission['stderr']) && !empty($submission['stderr'])) {
                $submission['stderr'] = base64_decode($submission['stderr']);
            }

            // Decode compile_output if present
            if (isset($submission['compile_output']) && !empty($submission['compile_output'])) {
                $submission['compile_output'] = base64_decode($submission['compile_output']);
            }

            // Decode message if present
            if (isset($submission['message']) && !empty($submission['message'])) {
                $submission['message'] = base64_decode($submission['message']);
            }
        }
    }

    return $data;
}

/**
 * Thực hiện chạy code với nhiều test cases sử dụng batch submissions
 *
 * @param string $source_code Mã nguồn
 * @param int $language_id ID ngôn ngữ
 * @param array $test_cases Mảng các test cases
 * @param array $config Cấu hình Judge0 API
 * @return array Kết quả của tất cả các test cases
 */
function devcode_run_code_with_batch($source_code, $language_id, $test_cases, $config = null)
{
    if (empty($config)) {
        $config = devcode_get_judge0_config();
    }

    if (empty($source_code) || empty($language_id) || empty($test_cases)) {
        return [
            'error' => true,
            'message' => 'Thiếu thông tin cần thiết để chạy code'
        ];
    }

    // Chuẩn bị batch submissions
    $batch_submissions = [];
    foreach ($test_cases as $test_case) {
        $submission_item = [
            'source_code' => $source_code,
            'language_id' => (int)$language_id,
            'stdin' => $test_case['input'],
            'expected_output' => $test_case['output'] ?? null,
            'cpu_time_limit' => $test_case['time_limit'] ?? $config['judge0_default_time_limit'],
            'memory_limit' => isset($test_case['memory_limit']) ? (int)$test_case['memory_limit'] : $config['judge0_default_memory_limit'],
            // 'wall_time_limit' => $test_case['wall_time_limit'] ?? ($config['judge0_default_wall_time_limit'] ?? 10), // Optional: if we want to control wall time
        ];

        $batch_submissions[] = $submission_item;
    }

    // Gửi batch submissions
    $batch_response = devcode_send_batch_to_judge0($batch_submissions, $config);

    if (isset($batch_response['error']) && $batch_response['error']) {
        return $batch_response;
    }

    // Lấy tokens
    $tokens = [];
    if (!empty($batch_response['submissions'])) {
        foreach ($batch_response['submissions'] as $submission) {
            if (!empty($submission['token'])) {
                $tokens[] = $submission['token'];
            }
        }
    }

    if (empty($tokens)) {
        return [
            'error' => true,
            'message' => 'Không nhận được tokens từ batch submissions'
        ];
    }

    // Đợi và lấy kết quả
    $max_attempts = 10;
    $attempt = 0;
    $wait_time = 1; // Bắt đầu với 1 giây

    while ($attempt < $max_attempts) {
        sleep($wait_time);

        $results = devcode_get_batch_results($tokens, $config);

        if (isset($results['error']) && $results['error']) {
            $attempt++;
            $wait_time *= 2; // Exponential backoff
            continue;
        }

        // Kiểm tra xem tất cả các submissions đã hoàn thành chưa
        $all_complete = true;
        if (!empty($results['submissions'])) {
            foreach ($results['submissions'] as $result) {
                // Nếu status id = 1 hoặc 2, thì vẫn đang xử lý
                if (isset($result['status']['id']) && in_array($result['status']['id'], [1, 2])) {
                    $all_complete = false;
                    break;
                }
            }
        }

        if ($all_complete) {
            // Xử lý kết quả và trả về
            $processed_results = [];
            if (!empty($results['submissions'])) {
                foreach ($results['submissions'] as $index => $result) {
                    $test_case = $test_cases[$index] ?? [];
                    $processed_results[] = [
                        'test_case' => $test_case,
                        'result' => devcode_map_judge0_status($result['status']['id'] ?? 0),
                        'execution_time' => $result['time'] ?? null,
                        'memory' => $result['memory'] ?? null,
                        'stdout' => $result['stdout'] ?? null,
                        'stderr' => $result['stderr'] ?? null,
                        'compile_output' => $result['compile_output'] ?? null,
                        'exit_code' => $result['exit_code'] ?? null,
                        'status' => $result['status'] ?? null,
                        'passed' => devcode_compare_outputs($test_case['output'] ?? '', $result['stdout'] ?? '')
                    ];
                }
            }

            return [
                'success' => true,
                'results' => $processed_results
            ];
        }

        $attempt++;
        $wait_time *= 2; // Tăng thời gian chờ theo luỹ thừa
    }

    return [
        'error' => true,
        'message' => 'Hết thời gian chờ kết quả từ batch submissions'
    ];
}

/**
 * Helper function to convert memory in KB to MB for display
 * 
 * @param int $memory_kb Memory in KB
 * @param bool $format Whether to format with "MB" suffix
 * @return string|float Memory in MB (formatted with "MB" suffix if $format is true)
 */
function devcode_format_memory_mb($memory_kb, $format = true) {
    // Check if memory is provided and is a number
    if (!isset($memory_kb) || !is_numeric($memory_kb)) {
        return $format ? '0 MB' : 0;
    }
    
    // Convert KB to MB (divide by 1024)
    $memory_mb = round($memory_kb / 1024, 2);
    
    // Return formatted string or just the value
    return $format ? $memory_mb . ' MB' : $memory_mb;
}
