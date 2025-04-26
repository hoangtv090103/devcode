<?php
// This file is part of the DevCode module for Moodle
// Configuration for the Judge0 API integration

/**
 * Cấu hình kết nối đến Backend API cho DevCode
 *
 * @package     mod_devcode
 * @copyright   2024 Your Name <your@email.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Define the debugging constant if not already defined
if (!defined('DEBUG_DEVELOPER')) {
    define('DEBUG_DEVELOPER', 32767);
}

// DevCode module configuration with default values
// These can be overridden in settings.php
$CFG->devcode = [
    // Judge0 API integration
    'judge0' => [
        'api_url' => 'https://judge0-ce.p.rapidapi.com',
        'api_key' => 'b7cb79bc20msh631e775baf24956p192284jsnc6b0aa67f960',
        'timeout' => 30,
        'max_wait' => 60,
        'poll_interval' => 3,
        'wait_for_result' => false
    ]
];

// Note: These default values will be used unless overridden by settings in the Moodle admin UI
// The settings are managed in settings.php
