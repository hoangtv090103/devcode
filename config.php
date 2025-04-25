<?php


/**
 * Cấu hình kết nối đến Backend API cho DevCode
 *
 * @package     mod_devcode
 * @copyright   2024 Your Name <your@email.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Lấy cài đặt từ settings nếu có
$judge0_api_url = get_config('mod_devcode', 'judge0_api_url') ?: 'https://judge0-ce.p.rapidapi.com';
$judge0_api_key = get_config('mod_devcode', 'judge0_api_key') ?: 'b7cb79bc20msh631e775baf24956p192284jsnc6b0aa67f960';
$judge0_timeout = get_config('mod_devcode', 'judge0_timeout') ?: 45;
$dolos_api_url = get_config('mod_devcode', 'dolos_api_url') ?: 'https://dolos.ugent.be/api';
$dolos_timeout = get_config('mod_devcode', 'dolos_timeout') ?: 120;

// Thiết lập kết nối backend API
$CFG->devcode = [

    // Judge0 API Configuration
    'judge0' => [
        'api_url' => $judge0_api_url,
        'api_key' => $judge0_api_key,
        'timeout' => $judge0_timeout,
        'max_wait' => 60,
        'poll_interval' => 3,
        // Headers for API requests
        'headers' => function () {
            $headers = [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ];

            // Thêm API key vào header nếu có
            $api_key = get_config('mod_devcode', 'judge0_api_key');
            if (!empty($api_key)) {
                $headers['X-RapidAPI-Key'] = $api_key;
                $headers['X-RapidAPI-Host'] = parse_url(get_config('mod_devcode', 'judge0_api_url') ?: 'https://judge0-ce.p.rapidapi.com', PHP_URL_HOST);
            }

            return $headers;
        },
    ],

    // Dolos API Configuration
    'dolos' => [
        'api_url' => $dolos_api_url,
        'timeout' => $dolos_timeout,
        'max_poll_attempts' => 30,
        'poll_interval' => 5,
        'threshold' => 0.8,
        // Headers for API requests (không cần API key)
        'headers' => [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ],
    ],

    // Plagiarism detection options
    'plagiarism' => [
        'enabled' => true,
        'threshold' => 0.7,
        'language_mapping' => [
            'python' => 'python',
            'java' => 'java',
            'c' => 'c',
            'cpp' => 'cpp',
            'javascript' => 'javascript'
        ]
    ],

    // Thời gian timeout cho kết nối API (giây)
    'api_timeout' => 30,

    // Thử lại kết nối API khi thất bại
    'api_retry_count' => 2,

    // Thời gian đợi giữa các lần thử lại (giây)
    'api_retry_wait' => 2,

    // Bật/tắt chế độ mô phỏng khi không kết nối được API
    'api_mock_enabled' => true,

    // Ngôn ngữ lập trình mặc định
    'default_language' => '71', // Python 3.8.1

    // Thời gian thực thi tối đa cho mỗi test case (mili giây)
    'default_time_limit' => 5000,

    // Bộ nhớ tối đa cho mỗi test case (KB)
    'default_memory_limit' => 262144, // 256MB

    // Các loại file được chấp nhận khi upload
    'allowed_file_types' => ['.py', '.java', '.cpp', '.c', '.js', '.cs'],

    // Kích thước file tối đa (bytes)
    'max_file_size' => 1048576, // 1MB
];
