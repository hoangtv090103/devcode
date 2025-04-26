<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Constants for Devcode module
 *
 * @package     mod_devcode
 * @copyright   2023 Your Name <your.email@example.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Trạng thái bài nộp
define('DEVCODE_STATUS_PENDING', 'pending');       // Đang chờ chấm điểm
define('DEVCODE_STATUS_PROCESSING', 'processing'); // Đang xử lý
define('DEVCODE_STATUS_ACCEPTED', 'accepted');     // Đã chấp nhận (pass hết test)
define('DEVCODE_STATUS_PARTIALLY_ACCEPTED', 'partially_accepted'); // Đã pass một số test cases
define('DEVCODE_STATUS_WRONG_ANSWER', 'wrong_answer'); // Sai kết quả
define('DEVCODE_STATUS_TIME_LIMIT', 'time_limit'); // Vượt quá thời gian
define('DEVCODE_STATUS_MEMORY_LIMIT', 'memory_limit'); // Vượt quá bộ nhớ
define('DEVCODE_STATUS_COMPILE_ERROR', 'compile_error'); // Lỗi biên dịch
define('DEVCODE_STATUS_RUNTIME_ERROR', 'runtime_error'); // Lỗi runtime
define('DEVCODE_STATUS_ERROR', 'error');           // Lỗi khác

// Ngôn ngữ lập trình (Judge0 API)
define('DEVCODE_LANG_C', 50);        // C (GCC 9.2.0)
define('DEVCODE_LANG_CPP', 54);      // C++ (GCC 9.2.0)
define('DEVCODE_LANG_JAVA', 62);     // Java (OpenJDK 13.0.1)
define('DEVCODE_LANG_PYTHON', 71);   // Python (3.8.1)
define('DEVCODE_LANG_JAVASCRIPT', 63); // JavaScript (Node.js 12.14.0)
define('DEVCODE_LANG_PHP', 68);      // PHP (7.4.1)
define('DEVCODE_LANG_RUBY', 72);     // Ruby (2.7.0)
define('DEVCODE_LANG_GO', 60);       // Go (1.13.5)
define('DEVCODE_LANG_CSHARP', 51);   // C# (Mono 6.8.0)

// Khác
define('DEVCODE_MAX_ATTEMPTS', 3);   // Số lần thử tối đa cho mỗi testcase
define('DEVCODE_MAX_POLL_TIME', 60); // Thời gian tối đa chờ kết quả từ API (giây)

// Additional error codes not already defined in judge0_api.php
// Note: DEVCODE_JUDGE0_ERROR_NONE through DEVCODE_JUDGE0_ERROR_MISSING_PARAM
// are already defined in judge0_api.php
define('DEVCODE_JUDGE0_ERROR_AUTH', 7);            // Authentication error
define('DEVCODE_JUDGE0_ERROR_MAX_RETRIES', 8);     // Max retries reached
define('DEVCODE_JUDGE0_ERROR_EXCEPTION', 9);       // Exception occurred
define('DEVCODE_JUDGE0_ERROR_EMPTY_RESPONSE', 10); // Empty response
define('DEVCODE_JUDGE0_ERROR_INVALID_JSON', 11);   // Invalid JSON format
define('DEVCODE_JUDGE0_ERROR_INVALID_PARAMS', 12); // Invalid parameters 