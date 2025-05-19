<?php


/**
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

// Import context classes
use \core\context\module as context_module;
use \core\context\user as context_user;
require_once(dirname(__FILE__) . '/plagiarismlib.php');

require_once(__DIR__ . '/judge0_api.php');

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
 * Parse and process the uploaded testcase file
 * 
 * @param int $contextid The context id where the file is uploaded
 * @param int $draftitemid The draft item id
 * @param int $devcodeid The devcode instance id
 * @return array Result of processing with message and count of processed test cases
 * 
 * Note: If certain fields are missing in the test case JSON, the following default values will be used:
 * - points: 10.0 points if not specified
 * - time_limit: 3000 ms if not specified
 * - visible_to_student: 0 (false) if not specified
 * - description: Empty string if not specified
 * 
 * Only 'input' and 'output' fields are required for each test case.
 */
function devcode_process_testcase_file($contextid, $draftitemid, $devcodeid) {
    global $DB, $USER;
    
    $fs = get_file_storage();
    $usercontext = \core\context\user::instance($USER->id);
    
    // Get the uploaded file from draft area
    $files = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftitemid, 'id', false);
    
    if (empty($files)) {
        return false;
    }
    
    $file = reset($files);
    $filename = $file->get_filename();
    $extension = pathinfo($filename, PATHINFO_EXTENSION);
    
    if ($extension !== 'json' && $extension !== 'txt') {
        return false;
    }
    
    // Get file content
    $content = $file->get_content();
    
    if (empty($content)) {
        return false;
    }
    
    $testcases = array();
    
    // Process based on file type
    if ($extension === 'json') {
        $data = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return false;
        }
        
        // Check if it has the test_cases array directly
        if (isset($data['test_cases']) && is_array($data['test_cases'])) {
            $testcases = $data['test_cases'];
        } else if (is_array($data) && isset($data[0]['input'])) {
            // The JSON is just an array of test cases
            $testcases = $data;
        }
    } else {
        // Process TXT file - simple format, each line is a test case
        $lines = explode("\n", $content);
        // Process TXT format (implementation depends on your specific format)
        // ...
    }
    
    // Clear any old test cases for this activity
    $DB->delete_records('devcode_testcases', array('devcodeid' => $devcodeid));
    
    // Insert the new test cases
    $testcase_count = 0;
    foreach ($testcases as $testcase) {
        if (!isset($testcase['input']) || !isset($testcase['output'])) {
            continue; // Skip invalid test cases
        }
        
        $record = new \stdClass();
        $record->devcodeid = $devcodeid;
        $record->input = $testcase['input'];
        $record->output = $testcase['output'];
        $record->points = isset($testcase['points']) ? $testcase['points'] : 10;
        $record->time_limit = isset($testcase['time_limit']) ? $testcase['time_limit'] : 1000;
        
        // Convert memory limit from MB to KB if provided
        if (isset($testcase['memory_limit'])) {
            $record->memory_limit = (int)($testcase['memory_limit'] * 1024);
        } else {
            $record->memory_limit = 128 * 1024; // Default: 128 MB
        }
        
        $record->description = isset($testcase['description']) ? $testcase['description'] : '';
        $record->visible_to_student = isset($testcase['visible_to_student']) ? $testcase['visible_to_student'] : 0;
        
        $DB->insert_record('devcode_testcases', $record);
        $testcase_count++;
    }
    
    return $testcase_count;
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
    global $DB, $CFG;
    
    require_once($CFG->dirroot . '/mod/devcode/locallib.php');
    
    $data->timecreated = time();
    $data->timemodified = time();
    
    // Set default values if not provided
    if (!isset($data->enable_plagiarism)) {
        $data->enable_plagiarism = 0;
    }
    if (!isset($data->similarity_threshold)) {
        $data->similarity_threshold = 80;
    }
    
    // Insert the record
    $data->id = $DB->insert_record('devcode', $data);
    
    // Handle test case file upload
    if (!empty($data->testcasefile)) {
        $contextid = context_module::instance($data->coursemodule)->id;
        $testcase_count = devcode_process_testcase_file($contextid, $data->testcasefile, $data->id);
    }
    
    // Process test cases that were manually entered
    if (isset($data->testcase_input) && is_array($data->testcase_input)) {
        foreach ($data->testcase_input as $key => $input) {
            // Skip test cases with empty input and output
            if (empty(trim($input)) && empty(trim($data->testcase_output[$key]))) {
                continue;
            }
            
            $testcase = new stdClass();
            $testcase->devcodeid = $data->id;
            $testcase->input = $input;
            $testcase->output = isset($data->testcase_output[$key]) ? $data->testcase_output[$key] : '';
            $testcase->points = isset($data->testcase_points[$key]) ? $data->testcase_points[$key] : 10;
            $testcase->time_limit = isset($data->testcase_time_limit[$key]) ? $data->testcase_time_limit[$key] : 1000;
            $testcase->memory_limit = isset($data->testcase_memory_limit[$key]) ? $data->testcase_memory_limit[$key] : 128 * 1024; // Default: 128 MB
            $testcase->visible_to_student = isset($data->testcase_visible[$key]) ? $data->testcase_visible[$key] : 0;
            $testcase->description = isset($data->testcase_description[$key]) ? $data->testcase_description[$key] : '';
            $testcase->timecreated = time();
            $testcase->timemodified = time();
            
            $DB->insert_record('devcode_testcases', $testcase);
        }
    }
    
    // Process files for the intro field
    if (isset($data->introeditor)) {
        $draftitemid = $data->introeditor['itemid'];
        $data->id = $data->id;
        $data->coursemodule = $data->coursemodule;
        $data->intro = $data->introeditor['text'];
        $data->introformat = $data->introeditor['format'];
        $data = file_postupdate_standard_editor($data, 'intro', array('trusttext' => true), context_module::instance($data->coursemodule), 'mod_devcode', 'intro', 0);
        $DB->update_record('devcode', $data);
    }
    
    // Create grade item for this activity
    devcode_grade_item_update($data);
    
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
    global $DB, $CFG;
    
    require_once($CFG->dirroot . '/mod/devcode/locallib.php');
    
    $data->timemodified = time();
    $data->id = $data->instance;
    
    // Update the main record
    $DB->update_record('devcode', $data);
    
    $contextid = context_module::instance($data->coursemodule)->id;
    
    // Handle test case file upload
    if (!empty($data->testcasefile)) {
        // Process and add the test cases from the uploaded file
        $testcase_count = devcode_process_testcase_file($contextid, $data->testcasefile, $data->id);
    }
    
    // Handle existing test cases - check if any are marked for deletion 
    if (!empty($data->instance)) {
        // Loop through the testcase_delete fields looking for test cases to delete
        foreach ($_POST as $key => $value) {
            if (preg_match('/^testcase_delete_(\d+)$/', $key, $matches) && $value == 1) {
                $testcaseid = $matches[1];
                // Delete the test case if it belongs to this instance
                $DB->delete_records_select(
                    'devcode_testcases', 
                    'id = ? AND devcodeid = ?', 
                    array($testcaseid, $data->id)
                );
            }
        }
    }
    
    // Process manually entered test cases
    if (isset($data->testcase_input) && is_array($data->testcase_input)) {
        foreach ($data->testcase_input as $key => $input) {
            // Skip test cases with empty input and output
            if (empty(trim($input)) && empty(trim($data->testcase_output[$key]))) {
                continue;
            }
            
            // Check if we have the ID for this test case
            $testcaseid = isset($data->testcase_id[$key]) ? $data->testcase_id[$key] : 0;
            
            // Check if marked for deletion
            if (!empty($data->testcase_delete[$key])) {
                if ($testcaseid) {
                    $DB->delete_records('devcode_testcases', array('id' => $testcaseid, 'devcodeid' => $data->id));
                }
                continue;
            }
            
            $testcase = new stdClass();
            $testcase->devcodeid = $data->id;
            $testcase->input = $input;
            $testcase->output = isset($data->testcase_output[$key]) ? $data->testcase_output[$key] : '';
            $testcase->points = isset($data->testcase_points[$key]) ? $data->testcase_points[$key] : 10;
            $testcase->time_limit = isset($data->testcase_time_limit[$key]) ? $data->testcase_time_limit[$key] : 1000;
            $testcase->memory_limit = isset($data->testcase_memory_limit[$key]) ? $data->testcase_memory_limit[$key] : 128 * 1024; 
            $testcase->visible_to_student = isset($data->testcase_visible[$key]) ? $data->testcase_visible[$key] : 0;
            $testcase->description = isset($data->testcase_description[$key]) ? $data->testcase_description[$key] : '';
            $testcase->timemodified = time();
            
            if ($testcaseid) {
                // Update existing test case
                $testcase->id = $testcaseid;
                $DB->update_record('devcode_testcases', $testcase);
            } else {
                // Create new test case
                $testcase->timecreated = time();
                $DB->insert_record('devcode_testcases', $testcase);
            }
        }
    }
    
    // Process intro field files
    if (isset($data->introeditor)) {
        $data = file_postupdate_standard_editor($data, 'intro', array('trusttext' => true), context_module::instance($data->coursemodule), 'mod_devcode', 'intro', 0);
        $DB->update_record('devcode', $data);
    }
    
    // Update grade item for this activity
    devcode_grade_item_update($data);
    
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

    // Get all submissions for this devcode instance to delete related records
    $submissions = $DB->get_records('devcode_submissions', array('devcodeid' => $id));
    
    // For each submission, delete related records
    foreach ($submissions as $submission) {
        // Delete submission results
        $DB->delete_records('devcode_submission_results', array('submissionid' => $submission->id));
    }
    
    // Delete plagiarism records (need to delete for both submission1_id and submission2_id)
    $DB->delete_records('devcode_plagiarism', array('devcodeid' => $id));
    
    // Now delete all submissions
    $DB->delete_records('devcode_submissions', array('devcodeid' => $id));

    // Delete all test cases
    $DB->delete_records('devcode_testcases', array('devcodeid' => $id));
    
    // For completeness, also clean up any student stats related to this assignment
    // (This depends on your implementation, may need to recalculate stats rather than delete)
    $course_id = $devcode->course;
    if ($course_id) {
        // If your stats tables contain specific assignment references, clean them here
        // For example, update devcode_student_stats table
        $DB->delete_records('devcode_student_stats', array('courseid' => $course_id));
        
        // You may also want to delete/update report_devcodereports_stats records
        // Since they're indexed by courseid and userid, you'd need additional logic
        // to identify which ones are related to this specific assignment
    }

    // Finally delete the devcode instance
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

/**
 * Grades a single submission using Judge0 API.
 *
 * Fetches expected outputs from test cases, sends code to Judge0, polls for results,
 * compares outputs, calculates score, and updates the database.
 *
 * @param stdClass $submission The submission record from devcode_submissions table.
 * @param array $testcases Array of testcase records from devcode_testcases table.
 * @param int $language_id The Judge0 language ID for the submission.
 * @param stdClass $devcode The devcode activity instance record.
 * @return bool True on success, false on failure (e.g., no test cases).
 */
function devcode_grade_submission(stdClass $submission, array $testcases, int $language_id, stdClass $devcode): bool {
    global $DB;

    debugging("Starting Judge0 grading for submission ID: {$submission->id}", DEBUG_DEVELOPER);

    if (empty($testcases)) {
        debugging('No test cases found for devcode ID: ' . $devcode->id, DEBUG_NORMAL);
        // Update submission status to error if no test cases exist
        $submission_update = new stdClass();
        $submission_update->id = $submission->id;
        $submission_update->status = 'error';
        $submission_update->feedback = get_string('error_no_testcases', 'mod_devcode');
        $submission_update->timemodified = time();
        $DB->update_record('devcode_submissions', $submission_update);
        // Trigger grade update even with error
        devcode_grade_item_update($devcode);
        return false;
    }

    $config = devcode_get_judge0_config();
    $total_points = 0;
    $earned_points = 0;
    $max_execution_time = 0.0;
    $passed_tests = 0;
    $total_tests = count($testcases);
    $feedback_bits = []; // Array to store feedback for each test case
    $has_api_error = false;

    // Update submission status to 'processing'
    $DB->set_field('devcode_submissions', 'status', 'processing', ['id' => $submission->id]);
    debugging("Submission {$submission->id} status set to processing.", DEBUG_DEVELOPER);

    // Default limits (Judge0 defaults might vary, explicit is better)
    // Judge0 uses seconds for time, kilobytes for memory
    $default_time_limit = 5; // seconds
    $default_memory_limit = 128000; // KB (approx 128MB)

    foreach ($testcases as $tc) {
        debugging("Processing test case ID: {$tc->id} for submission ID: {$submission->id}", DEBUG_DEVELOPER);
        $total_points += (float)$tc->points;

        $api_data = [
            'language_id' => $language_id,
            'stdin' => $tc->input ?? '',
            // Judge0 uses seconds, Moodle form might use ms
            'cpu_time_limit' => isset($devcode->time_limit) && $devcode->time_limit > 0 ? ($devcode->time_limit / 1000.0) : $default_time_limit,
            // Judge0 uses KB
            'memory_limit' => isset($devcode->memory_limit) && $devcode->memory_limit > 0 ? $devcode->memory_limit : $default_memory_limit,
            'number_of_runs' => 1,
            'source_code' => $submission->code // Default, overridden for Python below
        ];

        // Special handling for Python (IDs 70, 71) to use command_line_arguments
        if ($language_id == 70 || $language_id == 71) {
            debugging("Python submission detected (lang ID: {$language_id}), using command_line_arguments.", DEBUG_DEVELOPER);
            $code_to_execute = $submission->code;

            // If stdin exists, wrap the code to handle it
            if (!empty($api_data['stdin'])) {
                debugging("Python submission has stdin, wrapping code.", DEBUG_DEVELOPER);
                $stdin_escaped = str_replace(['"', "\n"], ['\\"', '\\n'], $api_data['stdin']);
                $input_wrapper = <<<EOT
import sys
__stdin_content = "$stdin_escaped"
__input_lines = __stdin_content.split('\\n')
__line_index = 0
def input(*args):
    global __line_index
    if __line_index < len(__input_lines):
        result = __input_lines[__line_index]
        __line_index += 1
        return result
    raise EOFError('End of input reached') # Raise EOFError when input is exhausted

# Original user code:
{$code_to_execute}
EOT;
                $code_to_execute = $input_wrapper;
                unset($api_data['stdin']); // stdin is now part of the code
            }

            // Escape the code for the command line argument
            $escaped_code = str_replace(['"', "\n"], ['\\"', '\\n'], $code_to_execute);
            $api_data['command_line_arguments'] = '-c "' . $escaped_code . '"';
            // Provide a minimal valid source code as it's required by Judge0 but not executed
            $api_data['source_code'] = "# Code executed via command line arguments\npass";
            debugging("Python command line args set (first 100 chars): " . substr($api_data['command_line_arguments'], 0, 100), DEBUG_DEVELOPER);
        } else {
            // For other languages, simply pass the source code.
            // Judge0 generally handles standard filenames based on language ID.
            // 'additional_files' might be needed for complex setups or non-standard filenames,
            // but we avoid it for simplicity unless proven necessary.
             debugging("Standard submission for language ID: {$language_id}.", DEBUG_DEVELOPER);
        }


        $result_record = new stdClass();
        $result_record->submissionid = $submission->id;
        $result_record->testcaseid = $tc->id;
        $result_record->output = '';
        $result_record->error_message = ''; // Stores stderr, compile output, or API errors
        $result_record->execution_time = 0; // Store as float seconds from Judge0
        $result_record->memory_used = 0; // Store as KB from Judge0
        $result_record->passed = 0;
        $result_record->points = 0;
        $result_record->status_id = 0; // Store Judge0 status ID
        $result_record->status_description = ''; // Store Judge0 status description
        $result_record->timecreated = time();

        $testcase_feedback = ''; // Feedback specific to this test case

        // 1. Send to API
        $api_response = devcode_send_to_api($api_data, $config);

        if (isset($api_response['error'])) {
            debugging("Judge0 API Error (send): {$api_response['message']}", DEBUG_NORMAL);
            $result_record->error_message = "API Send Error: {$api_response['message']}";
            $has_api_error = true;
            $testcase_feedback = "Test case {$tc->id}: API Error - Could not submit code for execution.";
        } else {
            $token = $api_response['token'];
            debugging("Received token: {$token} for test case {$tc->id}", DEBUG_DEVELOPER);

            // 2. Poll for result
            $poll_response = devcode_poll_submission($token, $config);

            if (isset($poll_response['error'])) {
                debugging("Judge0 API Error (poll): {$poll_response['message']}", DEBUG_NORMAL);
                $result_record->error_message = "API Poll Error: {$poll_response['message']}";
                $has_api_error = true;
                 $testcase_feedback = "Test case {$tc->id}: API Error - Could not retrieve execution result.";
            } else {
                // 3. Process result
                debugging("Received poll result for token {$token}: " . json_encode($poll_response), DEBUG_DEVELOPER);
                $judge0_result = $poll_response; // Use the full poll response

                $result_record->status_id = $judge0_result['status']['id'] ?? 0;
                $result_record->status_description = $judge0_result['status']['description'] ?? 'Unknown Status';
                $result_record->output = $judge0_result['stdout'] ?? '';
                $result_record->error_message = $judge0_result['stderr'] ?? '';
                $result_record->compile_output = $judge0_result['compile_output'] ?? null; // Store compile output if present
                $result_record->execution_time = (float)($judge0_result['time'] ?? 0);
                $result_record->memory_used = (int)($judge0_result['memory'] ?? 0);

                $max_execution_time = max($max_execution_time, $result_record->execution_time);

                 // Handle Internal Error (Status 13) - Potentially retry?
                // For now, just log detailed info and mark as error. Retrying might hide persistent server issues.
                if ($result_record->status_id == 13) {
                    debugging("Judge0 Internal Error detected for token {$token}. Status: {$result_record->status_description}", DEBUG_NORMAL);
                    debugging("Judge0 Internal Error details: " . json_encode($judge0_result), DEBUG_DEVELOPER);
                    $result_record->error_message = "Judge0 Internal Error: {$result_record->status_description}";
                    if (!empty($result_record->compile_output)) $result_record->error_message .= "\nCompile Output: " . $result_record->compile_output;
                    if (!empty($judge0_result['stderr'])) $result_record->error_message .= "\nStderr: " . $judge0_result['stderr']; // Use original stderr if exists
                    if (!empty($judge0_result['message'])) $result_record->error_message .= "\nMessage: " . $judge0_result['message']; // Add extra message if any
                    
                    // Check for specific file not found errors
                    if (isset($judge0_result['message']) && strpos($judge0_result['message'], 'No such file or directory') !== false) {
                         debugging("File-related internal error detected: " . $judge0_result['message'], DEBUG_DEVELOPER);
                         $result_record->error_message .= "\n(Possible file system issue on execution server)";
                         $testcase_feedback = "Test case {$tc->id}: Execution Environment Error - Could not run code (file not found).";
                    } else {
                         $testcase_feedback = "Test case {$tc->id}: Judge0 Internal Error - Could not complete execution.";
                    }
                    
                    $has_api_error = true; // Treat internal errors as API/infra errors for overall status
                }
                // Check if Accepted (Status 3)
                else if ($result_record->status_id == 3) {
                    // Compare output with expected output from the testcase record
                    $expected_output = $tc->output ?? '';
                    $actual_output = $result_record->output;

                    // Normalize whitespace and line endings for comparison
                    // Replace \r\n with \n, then trim whitespace from start/end
                    $normalized_expected = trim(str_replace("\r\n", "\n", $expected_output));
                    $normalized_actual = trim(str_replace("\r\n", "\n", $actual_output));

                    // Check for exact match after normalization
                    if ($normalized_expected === $normalized_actual) {
                        debugging("Test case {$tc->id} Passed.", DEBUG_DEVELOPER);
                        $result_record->passed = 1;
                        $result_record->points = (float)$tc->points;
                        $earned_points += $result_record->points;
                        $passed_tests++;
                        $testcase_feedback = "Test case {$tc->id}: Passed.";
                    } else {
                        debugging("Test case {$tc->id} Failed: Output mismatch.", DEBUG_DEVELOPER);
                        debugging("Expected (Normalized): '{$normalized_expected}'", DEBUG_DEVELOPER);
                        debugging("Actual (Normalized): '{$normalized_actual}'", DEBUG_DEVELOPER);
                        $result_record->passed = 0;
                        $result_record->points = 0;
                        $testcase_feedback = "Test case {$tc->id}: Failed - Wrong Answer.\n";
                        $testcase_feedback .= 'Expected: "' . htmlspecialchars($normalized_expected) . "\"\n";
                        $testcase_feedback .= 'Your output: "' . htmlspecialchars($normalized_actual) . "\"\n";
                        
                        // Optional: Check if differs only by whitespace/case for more hints (more complex)
                    }
                } else {
                    // Any other status means the test case failed
                    debugging("Test case {$tc->id} Failed: Status {$result_record->status_id} - {$result_record->status_description}", DEBUG_DEVELOPER);
                    $result_record->passed = 0;
                    $result_record->points = 0;
                    $testcase_feedback = "Test case {$tc->id}: Failed - {$result_record->status_description}.\n";
                    if (!empty($result_record->compile_output)) {
                        $testcase_feedback .= "Compile Output:\n" . htmlspecialchars(trim($result_record->compile_output)) . "\n";
                        // Also store compile output in the main error_message field for the record
                         $result_record->error_message = ($result_record->error_message ? $result_record->error_message . "\n" : "") . "Compile Output: " . $result_record->compile_output;
                    }
                    if (!empty($judge0_result['stderr'])) { // Use original stderr here
                        $testcase_feedback .= "Error Output (stderr):\n" . htmlspecialchars(trim($judge0_result['stderr'])) . "\n";
                    }
                    
                }
            }
        }

        // 4. Save test case result to DB
        $DB->insert_record('devcode_submission_results', $result_record);
        $feedback_bits[] = $testcase_feedback; // Add feedback for this test case
    } // End foreach testcase

    // 5. Calculate final results and update submission record
    $final_score = $total_points > 0 ? round(($earned_points / $total_points) * 10, 2) : 0; // Scale to 0-10

    if ($has_api_error) {
        $final_status = 'error';
        // Prepend a general API error message if specific test case errors occurred
         $general_error_msg = get_string('error_judge0_api', 'mod_devcode') . "\n\n";
         if (strpos(implode("\n", $feedback_bits), 'Internal Error') !== false) {
             $general_error_msg = get_string('error_judge0_internal', 'mod_devcode') . "\n\n";
              if (strpos(implode("\n", $feedback_bits), 'file not found') !== false) {
                   $general_error_msg = get_string('error_judge0_filenotfound', 'mod_devcode') . "\n\n";
              }
         }
        $final_feedback = $general_error_msg . implode("\n", $feedback_bits);
        debugging("Submission {$submission->id} finished with API errors. Final status: error", DEBUG_NORMAL);
    } else if ($passed_tests == $total_tests) {
        $final_status = 'passed';
        $final_feedback = "All $total_tests test cases passed.\n\n" . implode("\n", $feedback_bits);
        debugging("Submission {$submission->id} passed all tests. Final status: passed", DEBUG_DEVELOPER);
    } else {
        $final_status = 'graded';
        $final_feedback = "$passed_tests out of $total_tests test cases passed.\n\n" . implode("\n", $feedback_bits);
         debugging("Submission {$submission->id} graded. Passed {$passed_tests}/{$total_tests}. Final status: graded", DEBUG_DEVELOPER);
    }

    $submission_update = new stdClass();
    $submission_update->id = $submission->id;
    $submission_update->status = $final_status;
    $submission_update->score = $final_score; // Score out of 10
    $submission_update->feedback = trim($final_feedback);
    $submission_update->timemodified = time();
    $submission_update->passed_tests = $passed_tests;
    $submission_update->total_tests = $total_tests;
    $submission_update->execution_time = round($max_execution_time * 1000); // Convert back to ms for DB consistency

    $DB->update_record('devcode_submissions', $submission_update);
    debugging("Submission {$submission->id} record updated. Score: {$final_score}", DEBUG_DEVELOPER);

    // 6. Update Moodle gradebook
    devcode_grade_item_update($devcode); // This function should handle fetching the score from DB

    debugging("Grading complete for submission {$submission->id}", DEBUG_DEVELOPER);
    return true;
}

// Make sure devcode_grade_item_update exists and functions correctly.
// It should fetch the latest grade for the user for this devcode instance.

/**
 * Export current test cases for a devcode instance to JSON format
 * 
 * @param int $devcodeid The devcode instance id
 * @return string JSON encoded test cases
 */
function devcode_export_testcases($devcodeid) {
    global $DB;
    
    $testcases = $DB->get_records('devcode_testcases', array('devcodeid' => $devcodeid), 'id ASC');
    
    $export_data = array();
    foreach ($testcases as $testcase) {
        $export_data[] = array(
            'input' => $testcase->input,
            'output' => $testcase->output,
            'points' => floatval($testcase->points),
            'time_limit' => intval($testcase->time_limit),
            'description' => $testcase->description,
            'visible_to_student' => intval($testcase->visible_to_student)
        );
    }
    
    return json_encode($export_data, JSON_PRETTY_PRINT);
}

