<?php
/**
 * API communication functions for module devcode
 *
 * Contains all functions related to external API communication
 *
 * @package    mod_devcode
 * @copyright  2024 Your Name <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/devcode/config.php');

/**
 * Get supported programming languages from the API
 * 
 * @return array Array of language id => language name
 */
function devcode_get_supported_languages()
{
    global $CFG;

    // Đảm bảo config đã được load
    if (!isset($CFG->devcode)) {
        require_once(dirname(__FILE__) . '/config.php');
    }

    $api_base = $CFG->devcode['api_base_url'];
    $languages_endpoint = $CFG->devcode['api_endpoints']['languages'];
    $url = $api_base . $languages_endpoint;

    $languages = array();

    // Thử sử dụng file_get_contents với stream context để xử lý lỗi
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => $CFG->devcode['api_timeout'],
            'ignore_errors' => true
        ]
    ]);

    $response = @file_get_contents($url, false, $context);

    // Kiểm tra nếu API không hoạt động, trả về danh sách mặc định
    if ($response === false) {
        debugging('Không thể kết nối đến API ngôn ngữ. Sử dụng danh sách mặc định.', DEBUG_DEVELOPER);
        return array(
            '71' => 'Python (3.8.1)',
            '62' => 'Java (JDK 13.0.1)',
            '54' => 'C++ (GCC 9.2.0)',
            '63' => 'JavaScript (Node.js 12.14.0)'
        );
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        debugging('Định dạng JSON không hợp lệ từ API. Sử dụng danh sách mặc định.', DEBUG_DEVELOPER);
        return array(
            '71' => 'Python (3.8.1)',
            '62' => 'Java (JDK 13.0.1)',
            '54' => 'C++ (GCC 9.2.0)',
            '63' => 'JavaScript (Node.js 12.14.0)'
        );
    }

    foreach ($data as $lang) {
        if (isset($lang['id']) && isset($lang['name'])) {
            // Chuyển ID thành chuỗi để tránh lỗi khi lưu vào database
            $languages[strval($lang['id'])] = $lang['name'];
        }
    }

    return $languages;
}

/**
 * Hàm gửi API request
 */
function devcode_api_request($url, $method = 'GET', $data = null)
{
    global $CFG;

    // Đảm bảo config đã được load
    if (!isset($CFG->devcode)) {
        require_once(dirname(__FILE__) . '/config.php');
    }

    $timeout = $CFG->devcode['api_timeout'];
    $retry_count = $CFG->devcode['api_retry_count'];
    $retry_wait = $CFG->devcode['api_retry_wait'];

    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => 'Content-Type: application/json',
            'content' => $data ? json_encode($data) : null,
            'timeout' => $timeout,
            'ignore_errors' => true
        ]
    ]);

    // Thử lại nếu kết nối thất bại
    $attempt = 0;
    $response = false;

    while ($attempt <= $retry_count && $response === false) {
        if ($attempt > 0) {
            debugging("Thử kết nối lại lần $attempt...", DEBUG_DEVELOPER);
            sleep($retry_wait);
        }

        $response = @file_get_contents($url, false, $context);
        $attempt++;
    }

    if ($response === false) {
        // Kiểm tra lỗi HTTP
        $error = error_get_last();
        debugging('Lỗi khi gọi API: ' . ($error['message'] ?? 'Không rõ lỗi'), DEBUG_DEVELOPER);
        return false;
    }

    // Lấy HTTP status code
    $status_line = $http_response_header[0];
    preg_match('{HTTP\/\S*\s(\d{3})}', $status_line, $match);
    $status = $match[1];

    // Xử lý lỗi
    if ($status >= 400) {
        debugging('API trả về lỗi HTTP ' . $status . ': ' . $response, DEBUG_DEVELOPER);
        return ['error' => 'API error: HTTP ' . $status];
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        debugging('Định dạng JSON không hợp lệ từ API: ' . json_last_error_msg(), DEBUG_DEVELOPER);
        return ['error' => 'Invalid JSON response'];
    }

    return $data;
}

/**
 * Get language name by ID
 */
function devcode_get_language_by_id($language_id)
{
    global $CFG;

    // Đảm bảo config đã được load
    if (!isset($CFG->devcode)) {
        require_once(dirname(__FILE__) . '/config.php');
    }

    // Danh sách ngôn ngữ mặc định để fallback
    $default_languages = array(
        '71' => 'Python (3.8.1)',
        '62' => 'Java (JDK 13.0.1)',
        '54' => 'C++ (GCC 9.2.0)',
        '63' => 'JavaScript (Node.js 12.14.0)'
    );

    // Kiểm tra nếu language_id tồn tại trong danh sách mặc định
    if (isset($default_languages[$language_id])) {
        return $default_languages[$language_id];
    }

    // Nếu không tìm thấy trong danh sách mặc định, thử lấy từ API
    try {
        $api_base = $CFG->devcode['api_base_url'];
        $languages_endpoint = $CFG->devcode['api_endpoints']['languages'];
        $url = $api_base . $languages_endpoint;

        // Thử sử dụng file_get_contents với stream context để xử lý lỗi
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $CFG->devcode['api_timeout'],
                'ignore_errors' => true
            ]
        ]);

        $response = @file_get_contents($url, false, $context);

        // Kiểm tra nếu API không hoạt động, trả về từ danh sách mặc định
        if ($response === false) {
            debugging('Không thể kết nối đến API ngôn ngữ. Sử dụng danh sách mặc định.', DEBUG_DEVELOPER);
            return $language_id; // Trả về ID nếu không có tên
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            debugging('Định dạng JSON không hợp lệ từ API. Sử dụng ID.', DEBUG_DEVELOPER);
            return $language_id;
        }

        // Tìm ngôn ngữ trong danh sách API
        foreach ($data as $lang) {
            if (isset($lang['id']) && $lang['id'] == $language_id && isset($lang['name'])) {
                return $lang['name'];
            }
        }

        // Nếu không tìm thấy, trả về ID
        return $language_id;
    } catch (Exception $e) {
        debugging('Lỗi khi lấy thông tin ngôn ngữ: ' . $e->getMessage(), DEBUG_DEVELOPER);
        return $language_id;
    }
} 