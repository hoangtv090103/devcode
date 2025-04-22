<?php


/**
 * Cấu hình kết nối đến Backend API cho DevCode
 *
 * @package     mod_devcode
 * @copyright   2024 Your Name <your@email.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Thiết lập kết nối backend API
$CFG->devcode = [
    // URL gốc của backend API
    'api_base_url' => 'http://localhost:8000',

    // API endpoints
    'api_endpoints' => [
        'submissions' => '/api/v1/submissions/',
        'languages' => '/api/v1/j0/languages',
        'statuses' => '/api/v1/j0/statuses',
        'async_processing' => '/api/v1/submissions/async-process',
    ],

    // Judge0 API Configuration
    'judge0' => [
        'api_url' => 'https://judge0-ce.p.rapidapi.com', // RapidAPI endpoint
        'api_key' => 'b7cb79bc20msh631e775baf24956p192284jsnc6b0aa67f960', // Replace with your RapidAPI key
        'timeout' => 45, // Tăng timeout để xử lý kết nối chậm
        'max_wait' => 60, // Tăng thời gian chờ tối đa để xử lý submission
        'poll_interval' => 3, // Tăng khoảng thời gian giữa các lần poll để tránh rate limit
    ],

    // Dolos API Configuration
    'dolos' => [
        'api_url' => 'https://dolos.ugent.be/api',
        'timeout' => 30,
        'max_poll_attempts' => 60,
        'poll_interval' => 1,
    ],

    // Plagiarism detection options
    'plagiarism' => [
        'enabled' => true,
        'threshold' => 0.7, // similarity threshold (0.0 to 1.0)
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
