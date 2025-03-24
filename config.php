<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

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