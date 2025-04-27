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

    // Make sure config is loaded
    if (!isset($CFG->devcode)) {
        require_once(dirname(__FILE__) . '/config.php');
    }

    // Include judge0_api.php if not already included
    require_once(dirname(__FILE__) . '/judge0_api.php');
    
    // Get Judge0 config
    $judge0_config = devcode_get_judge0_config();
    
    // Get languages from Judge0 API
    $result = devcode_get_languages($judge0_config);

    $languages = array();

    // Check if there was an error
    if (isset($result['error'])) {
        debugging('Error getting languages from Judge0 API: ' . $result['message'], DEBUG_DEVELOPER);
        // Return default languages if error
        return array(
            '71' => 'Python (3.8.1)',
            '62' => 'Java (JDK 13.0.1)',
            '54' => 'C++ (GCC 9.2.0)',
            '63' => 'JavaScript (Node.js 12.14.0)'
        );
    }

    // Process languages from Judge0 API
    foreach ($result as $lang) {
        if (isset($lang['id']) && isset($lang['name'])) {
            // Convert ID to string to avoid database issues
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
 * 
 * @param string $language_id The language ID to lookup
 * @return string The language name, or the ID if not found
 */
function devcode_get_language_by_id($language_id)
{
    global $CFG;

    // Static cache to avoid multiple API calls for the same language ID
    static $language_cache = array();
    
    // Return from cache if available
    if (isset($language_cache[$language_id])) {
        return $language_cache[$language_id];
    }

    // Make sure config is loaded
    if (!isset($CFG->devcode)) {
        require_once(dirname(__FILE__) . '/config.php');
    }

    // Include judge0_api.php if not already included
    require_once(dirname(__FILE__) . '/judge0_api.php');
    
    // Default languages for fallback
    $default_languages = array(
        '71' => 'Python (3.8.1)',
        '62' => 'Java (JDK 13.0.1)',
        '54' => 'C++ (GCC 9.2.0)',
        '63' => 'JavaScript (Node.js 12.14.0)'
    );

    // Check if language_id exists in default languages
    if (isset($default_languages[$language_id])) {
        $language_cache[$language_id] = $default_languages[$language_id];
        return $language_cache[$language_id];
    }

    // Try to get from Judge0 API
    try {
        // Get Judge0 config
        $config = devcode_get_judge0_config();
        
        // API endpoint URL
        $url = rtrim($config['judge0_api_url'], '/') . '/languages/' . $language_id;

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
            debugging('Cannot connect to language API: ' . $error, DEBUG_DEVELOPER);
            $language_cache[$language_id] = $language_id; // Cache the ID
            return $language_id; // Return ID if API call fails
        }
        
        // Get HTTP status code
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Check HTTP status code
        if ($http_code < 200 || $http_code >= 300) {
            debugging('HTTP error when fetching language: ' . $http_code, DEBUG_DEVELOPER);
            $language_cache[$language_id] = $language_id; // Cache the ID
            return $language_id; // Return ID on HTTP error
        }

        // Decode JSON response
        $lang_data = json_decode($response, true);
        
        // Check for JSON decode errors
        if ($lang_data === null) {
            debugging('Invalid JSON response from API: ' . json_last_error_msg(), DEBUG_DEVELOPER);
            $language_cache[$language_id] = $language_id; // Cache the ID
            return $language_id; // Return ID on JSON error
        }
        
        // Return language name if found
        if (isset($lang_data['name'])) {
            $language_cache[$language_id] = $lang_data['name']; // Cache the name
            return $lang_data['name'];
        }

        // If everything failed, return the ID
        $language_cache[$language_id] = $language_id; // Cache the ID
        return $language_id;
    } catch (Exception $e) {
        debugging('Error when getting language info: ' . $e->getMessage(), DEBUG_DEVELOPER);
        $language_cache[$language_id] = $language_id; // Cache the ID
        return $language_id;
    }
}

/**
 * Pre-cache all available languages to avoid multiple API calls
 * Call this function once at the beginning of a page to optimize multiple language lookups
 */
function devcode_optimize_language_lookup() 
{
    static $initialized = false;
    
    // Only initialize once per request
    if ($initialized) {
        return;
    }
    
    // Get all languages
    $languages = devcode_get_supported_languages();
    
    // Cache each language
    if (is_array($languages)) {
        foreach ($languages as $id => $name) {
            // Use static variable inside devcode_get_language_by_id
            // by calling it once for each language
            devcode_get_language_by_id($id);
        }
    }
    
    $initialized = true;
} 