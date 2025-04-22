<?php


/**
 * Library of interface functions and constants for module devcode
 *
 * All the core Moodle functions, neeeded to allow the module to work
 * integrated in Moodle should be placed here.
 *
 * All the devcode specific functions, needed to implement all the module
 * logic, should go to locallib.php. This will help to save some memory when
 * Moodle is performing actions across all modules.
 *
 * @package     mod_devcode
 * @copyright   2024 Your Name <your@email.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/moodlelib.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/lib/accesslib.php');
require_once($CFG->libdir.'/gradelib.php');

// Include module libraries
require_once(dirname(__FILE__) . '/locallib.php');
require_once(dirname(__FILE__) . '/apilib.php');
require_once(dirname(__FILE__) . '/gradelib.php');
require_once(dirname(__FILE__) . '/plagiarismlib.php');

/**
 * Returns the information on whether the module supports a feature
 *
 * @see plugin_supports() in lib/moodlelib.php
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed true if the feature is supported, null if unknown
 */
function devcode_supports($feature)
{
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_GRADE_OUTCOMES:
            return false;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_ASSESSMENT;
        default:
            return null;
    }
}

/**
 * Add devcode instance.
 *
 * @param stdClass $data
 * @param mod_devcode_mod_form $mform
 * @return int new devcode instance id
 */
function devcode_add_instance($data, $mform = null)
{
    global $DB;

    // Xử lý dữ liệu trước khi lưu
    $data->timemodified = time();
    $data->timecreated = time();

    // Đảm bảo programming_language là chuỗi
    if (isset($data->programming_language)) {
        $data->programming_language = strval($data->programming_language);
    }

    // Xử lý dữ liệu từ trình soạn thảo (editor)
    if (isset($data->intro) && is_array($data->intro)) {
        $data->intro = $data->intro['text'];
    }

    if (isset($data->introformat) && is_array($data->introformat)) {
        $data->introformat = FORMAT_HTML;
    } else if (!isset($data->introformat)) {
        $data->introformat = FORMAT_HTML;
    }

    // Chèn bản ghi
    $data->id = $DB->insert_record('devcode', $data);

    // Lưu test cases
    if (isset($data->testcase_input) && is_array($data->testcase_input)) {
        for ($i = 0; $i < count($data->testcase_input); $i++) {
            if (empty($data->testcase_input[$i]) && empty($data->testcase_output[$i])) {
                continue; // Bỏ qua test case trống
            }

            $testcase = new stdClass();
            $testcase->devcodeid = $data->id;
            $testcase->input = $data->testcase_input[$i];
            $testcase->output = $data->testcase_output[$i];
            $testcase->points = isset($data->testcase_points[$i]) ? floatval($data->testcase_points[$i]) : 10.0;
            $testcase->time_limit = isset($data->testcase_time_limit[$i]) ? intval($data->testcase_time_limit[$i]) : 3000;
            $testcase->visible_to_student = isset($data->testcase_visible[$i]) ? intval($data->testcase_visible[$i]) : 0;
            $testcase->timecreated = time();
            $testcase->timemodified = time();

            $DB->insert_record('devcode_testcases', $testcase);
        }
    }

    return $data->id;
}

/**
 * Update devcode instance.
 *
 * @param stdClass $data
 * @param mod_devcode_mod_form $mform
 * @return bool true
 */
function devcode_update_instance($data, $mform = null)
{
    global $DB;

    // Xử lý dữ liệu trước khi cập nhật
    $data->timemodified = time();
    $data->id = $data->instance;

    // Debug log for ALL form data
    error_log('=== FORM DATA START ===');
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            error_log("$key: " . json_encode($value));
        } else {
            error_log("$key: $value");
        }
    }
    error_log('=== FORM DATA END ===');

    // Đảm bảo programming_language là chuỗi
    if (isset($data->programming_language)) {
        $data->programming_language = strval($data->programming_language);
    }

    // Xử lý dữ liệu từ trình soạn thảo (editor)
    if (isset($data->intro) && is_array($data->intro)) {
        $data->intro = $data->intro['text'];
    }

    if (isset($data->introformat) && is_array($data->introformat)) {
        $data->introformat = FORMAT_HTML;
    } else if (!isset($data->introformat)) {
        $data->introformat = FORMAT_HTML;
    }
    
    // Save plagiarism detection settings
    if (isset($data->enable_plagiarism)) {
        $data->enable_plagiarism = $data->enable_plagiarism;
        if (isset($data->similarity_threshold)) {
            $data->similarity_threshold = $data->similarity_threshold;
        }
    } else {
        $data->enable_plagiarism = 0;
        $data->similarity_threshold = 80; // Default value
    }

    // Update the main module record
    $DB->update_record('devcode', $data);
    
    // Collect all test cases that need to be deleted
    $testcases_to_delete = array();
    
    // Debug log: list all testcases in the database before deletion
    $current_testcases = $DB->get_records('devcode_testcases', array('devcodeid' => $data->id));
    error_log('Current testcases in database: ' . count($current_testcases));
    foreach ($current_testcases as $tc) {
        error_log("TestCase ID: {$tc->id}, Input: {$tc->input}");
    }
    
    // Process testcases to delete - simplified approach using checkbox
    error_log('=== CHECKING FOR TESTCASES TO DELETE ===');
    if (isset($data->testcase_delete) && is_array($data->testcase_delete) && 
        isset($data->testcase_id) && is_array($data->testcase_id)) {
        
        foreach ($data->testcase_delete as $key => $delete_flag) {
            if ($delete_flag == 1 && isset($data->testcase_id[$key]) && !empty($data->testcase_id[$key])) {
                $testcase_id = $data->testcase_id[$key];
                $testcases_to_delete[] = $testcase_id;
                error_log("Found testcase to delete: $testcase_id at position $key");
            }
        }
    }
    
    // Delete the marked test cases
    if (!empty($testcases_to_delete)) {
        error_log('=== DELETING TESTCASES ===');
        error_log('Testcases to delete: ' . implode(', ', $testcases_to_delete));
        
        foreach ($testcases_to_delete as $testcase_id) {
            // Get the testcase record to confirm it exists
            $testcase = $DB->get_record('devcode_testcases', array('id' => $testcase_id, 'devcodeid' => $data->id));
            if ($testcase) {
                error_log("Preparing to delete testcase ID: {$testcase_id} with input: {$testcase->input}");
                $result = $DB->delete_records('devcode_testcases', array('id' => $testcase_id, 'devcodeid' => $data->id));
                error_log('Delete result: ' . ($result ? 'Success' : 'Failed'));
                
                // Verify deletion
                $check = $DB->record_exists('devcode_testcases', array('id' => $testcase_id));
                error_log('Verification - Record still exists: ' . ($check ? 'Yes (ERROR)' : 'No (Success)'));
            } else {
                error_log('Testcase ID ' . $testcase_id . ' not found or does not belong to devcode instance ' . $data->id);
            }
        }
        
        // List testcases after deletion
        $remaining_testcases = $DB->get_records('devcode_testcases', array('devcodeid' => $data->id));
        error_log('Remaining testcases after deletion: ' . count($remaining_testcases));
        foreach ($remaining_testcases as $tc) {
            error_log("TestCase ID: {$tc->id}, Input: {$tc->input}");
        }
    } else {
        error_log('No testcases marked for deletion');
    }
    
    // Process test cases
    if (isset($data->testcase_input) && is_array($data->testcase_input)) {
        $updated_ids = array();
        
        // Update or insert test cases
        foreach ($data->testcase_input as $key => $input) {
            // Skip empty test cases
            if (empty($input) && empty($data->testcase_output[$key])) {
                continue;
            }
            
            // Skip if this test case is marked for deletion
            if (!empty($data->testcase_id[$key]) && 
                in_array($data->testcase_id[$key], $testcases_to_delete)) {
                error_log('Skipping testcase ' . $data->testcase_id[$key] . ' as it is marked for deletion');
                continue;
            }
            
            $testcase = new stdClass();
            $testcase->devcodeid = $data->id;
            $testcase->input = $input;
            $testcase->output = $data->testcase_output[$key];
            $testcase->points = isset($data->testcase_points[$key]) ? floatval($data->testcase_points[$key]) : 10.0;
            $testcase->time_limit = isset($data->testcase_time_limit[$key]) ? intval($data->testcase_time_limit[$key]) : 3000;
            $testcase->visible_to_student = isset($data->testcase_visible[$key]) ? intval($data->testcase_visible[$key]) : 0;
            $testcase->timemodified = time();
            
            // Check if this is an update or insert
            if (!empty($data->testcase_id[$key])) {
                // Update existing
                $testcase->id = $data->testcase_id[$key];
                $DB->update_record('devcode_testcases', $testcase);
                $updated_ids[] = $testcase->id;
                
                // Debug log
                error_log('Updated testcase ID: ' . $testcase->id);
            } else {
                // Insert new
                $testcase->timecreated = time();
                $testcase_id = $DB->insert_record('devcode_testcases', $testcase);
                $updated_ids[] = $testcase_id;
                
                // Debug log
                error_log('Inserted new testcase with ID: ' . $testcase_id);
            }
        }
    }

    return true;
}

/**
 * Delete devcode instance.
 *
 * @param int $id
 * @return bool true
 */
function devcode_delete_instance($id)
{
    global $DB;

    if (!$devcode = $DB->get_record('devcode', array('id' => $id))) {
        return false;
    }

    // Delete all submissions
    $DB->delete_records('devcode_submissions', array('devcodeid' => $id));

    // Delete all test cases
    $DB->delete_records('devcode_testcases', array('devcodeid' => $id));

    // Delete the devcode instance
    $DB->delete_records('devcode', array('id' => $id));

    return true;
}

/**
 * Extends the global navigation tree by adding devcode nodes if there is a relevant content
 *
 * This can be called by an AJAX request so do not rely on $PAGE as it might not be set up properly.
 *
 * @param navigation_node $navref An object representing the navigation tree node of the devcode module instance
 * @param stdClass $course
 * @param stdClass $module
 * @param cm_info $modinfo
 */
function devcode_extend_navigation(navigation_node $navref, stdClass $course, stdClass $module, cm_info $modinfo)
{
    global $CFG, $DB;

    // Kiểm tra xem module id có tồn tại không
    if (empty($module->id) || !$DB->record_exists('course_modules', array('id' => $module->id))) {
        return;
    }

    try {
        // Sử dụng URL mặc định
        $url = $CFG->wwwroot . '/mod/devcode/view.php?id=' . $module->id;
        $navref->add(get_string('viewsubmissions', 'mod_devcode'), $url, navigation_node::TYPE_SETTING);
    } catch (Exception $e) {
        // Bỏ qua lỗi nếu có, tránh làm gián đoạn navigation
        return;
    }
}

/**
 * Extends the settings navigation with the devcode settings
 *
 * This function is called when the context for the page is a devcode module. This is not called by AJAX
 * so it is safe to rely on the $PAGE.
 *
 * @param settings_navigation $settingsnav {@link settings_navigation}
 * @param navigation_node $devcodenode {@link navigation_node}
 */
function devcode_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $devcodenode)
{
    global $PAGE, $CFG, $DB;

    // Kiểm tra xem $PAGE->cm có tồn tại không
    if (empty($PAGE->cm) || empty($PAGE->cm->id) || !$DB->record_exists('course_modules', array('id' => $PAGE->cm->id))) {
        return;
    }

    try {
        $context = $PAGE->cm->context;
        if (has_capability('mod/devcode:view', $context)) {
            $url = $CFG->wwwroot . '/mod/devcode/view.php?id=' . $PAGE->cm->id;
            $devcodenode->add(get_string('viewsubmissions', 'mod_devcode'), $url, navigation_node::TYPE_SETTING);
        }
    } catch (Exception $e) {
        // Bỏ qua lỗi nếu có
        return;
    }
}
