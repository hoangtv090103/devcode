<?php


/**
 * Cấu hình quản trị plugin Devcode
 *
 * @package    mod_devcode
 * @copyright  2024 Your Name <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    global $CFG;
    require_once($CFG->dirroot . '/mod/devcode/config.php');

    // Nhóm thiết lập cho Judge0
    $settings->add(new admin_setting_heading(
        'mod_devcode/judge0_settings',
        get_string('judge0_settings', 'mod_devcode', null, true),
        get_string('judge0_settings_desc', 'mod_devcode', null, true)
    ));

    // Đường dẫn API Judge0
    $default_judge0_url = isset($CFG->devcode['judge0']['api_url']) ? 
                          $CFG->devcode['judge0']['api_url'] : 'https://judge0-ce.p.rapidapi.com';
    $settings->add(new admin_setting_configtext(
        'mod_devcode/judge0_api_url',
        get_string('judge0_api_url', 'mod_devcode', null, true),
        get_string('judge0_api_url_desc', 'mod_devcode', null, true),
        $default_judge0_url,
        PARAM_URL
    ));

    // API Key cho Judge0
    $default_judge0_key = isset($CFG->devcode['judge0']['api_key']) ? 
                          $CFG->devcode['judge0']['api_key'] : '';
    $settings->add(new admin_setting_configpasswordunmask(
        'mod_devcode/judge0_api_key',
        get_string('judge0_api_key', 'mod_devcode', null, true),
        get_string('judge0_api_key_desc', 'mod_devcode', null, true),
        $default_judge0_key,
        PARAM_TEXT
    ));

    // Timeout cho Judge0
    $default_timeout = isset($CFG->devcode['judge0']['timeout']) ? 
                      $CFG->devcode['judge0']['timeout'] : 45;
    $settings->add(new admin_setting_configtext(
        'mod_devcode/judge0_timeout',
        get_string('judge0_timeout', 'mod_devcode', null, true),
        get_string('judge0_timeout_desc', 'mod_devcode', null, true),
        $default_timeout,
        PARAM_INT
    ));

    // Thiết lập cho Dolos (kiểm tra đạo code)
    $settings->add(new admin_setting_heading(
        'mod_devcode/dolos_settings',
        get_string('dolos_settings', 'mod_devcode', null, true),
        get_string('dolos_settings_desc', 'mod_devcode', null, true)
    ));

    // Đường dẫn API Dolos
    $default_dolos_url = isset($CFG->devcode['dolos']['api_url']) ? 
                        $CFG->devcode['dolos']['api_url'] : 'https://dolos.ugent.be/api';
    $settings->add(new admin_setting_configtext(
        'mod_devcode/dolos_api_url',
        get_string('dolos_api_url', 'mod_devcode', null, true),
        get_string('dolos_api_url_desc', 'mod_devcode', null, true),
        $default_dolos_url,
        PARAM_URL
    ));

    // API Key cho Dolos (nếu cần)
    $default_dolos_key = isset($CFG->devcode['dolos']['api_key']) ? 
                        $CFG->devcode['dolos']['api_key'] : '';
    $settings->add(new admin_setting_configpasswordunmask(
        'mod_devcode/dolos_api_key',
        get_string('dolos_api_key', 'mod_devcode', null, true),
        get_string('dolos_api_key_desc', 'mod_devcode', null, true),
        $default_dolos_key,
        PARAM_TEXT
    ));

    // Timeout cho Dolos
    $default_dolos_timeout = isset($CFG->devcode['dolos']['timeout']) ? 
                            $CFG->devcode['dolos']['timeout'] : 30;
    $settings->add(new admin_setting_configtext(
        'mod_devcode/dolos_timeout',
        get_string('dolos_timeout', 'mod_devcode', null, true),
        get_string('dolos_timeout_desc', 'mod_devcode', null, true),
        $default_dolos_timeout,
        PARAM_INT
    ));
} 