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
        'api_key' => 'YOUR_JUDGE0_API_KEY_HERE',
        'timeout' => 30,
        'max_wait' => 60,
        'poll_interval' => 3,
        'wait_for_result' => false,
        'default_memory_limit' => 128000 // Default memory limit in KB (128MB)
    ],
    
    // Dolos plagiarism detection configuration
    'dolos' => [
        'api_url' => 'https://dolos.ugent.be/api',
        'api_key' => 'YOUR_DOLOS_API_KEY_HERE',
        'timeout' => 120,
        'max_poll_attempts' => 30,
        'poll_interval' => 5,
        'threshold' => 0.8
    ],
    
    // Plagiarism detection settings
    'plagiarism' => [
        'enabled' => true,
        'language_mapping' => [
            'c' => 'c',
            'cpp' => 'cpp',
            'java' => 'java',
            'python' => 'python',
            'javascript' => 'javascript',
            'php' => 'php',
            'ruby' => 'ruby',
            'go' => 'go',
            'csharp' => 'csharp',
            'kotlin' => 'kotlin',
            'rust' => 'rust',
            'swift' => 'swift',
            'typescript' => 'typescript'
        ]
    ]
];

// Note: Rename this file to config.php and replace the placeholder API keys with your actual keys
// The settings are managed in settings.php 