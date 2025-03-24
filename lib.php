<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/moodlelib.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/lib/accesslib.php');

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

    $DB->update_record('devcode', $data);

    // Xóa tất cả test cases hiện tại
    $DB->delete_records('devcode_testcases', array('devcodeid' => $data->id));

    // Thêm test cases mới
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
 * Update grades in central gradebook
 *
 * @param stdClass $devcode null means all devcode instances
 * @param int $userid specific user only, 0 means all
 */
function devcode_update_grades($devcode = null, $userid = 0)
{
    global $DB, $USER;

    if ($devcode !== null) {
        $where = array('devcodeid' => $devcode->id);
        if ($userid) {
            $where['userid'] = $userid;
        }
    } else {
        $where = array();
        if ($userid) {
            $where['userid'] = $userid;
        }
    }

    // Xây dựng mệnh đề WHERE thủ công thay vì sử dụng sql_where
    $whereclause = '';
    $params = array();

    if (!empty($where)) {
        $conditions = array();
        $i = 0;

        foreach ($where as $field => $value) {
            $conditions[] = "$field = :param$i";
            $params["param$i"] = $value;
            $i++;
        }

        $whereclause = ' WHERE ' . implode(' AND ', $conditions);
    }

    $sql = "SELECT s.*, r.passed, r.output as result_output
            FROM {devcode_submissions} s
            LEFT JOIN {devcode_submission_results} r ON s.id = r.submissionid" . $whereclause;

    $submissions = $DB->get_records_sql($sql, $params);

    foreach ($submissions as $submission) {
        if ($submission->score !== null) {
            $grade = new stdClass();
            $grade->userid = $submission->userid;
            $grade->rawgrade = $submission->score;
            $grade->feedback = $submission->feedback;
            $grade->feedbackformat = FORMAT_HTML;
            $grade->usermodified = $USER->id;
            $grade->dategraded = time();
            $grade->datesubmitted = $submission->timecreated;

            grade_update('mod/devcode', $submission->devcodeid, 'mod', 'devcode', 0, 0, $grade);
        }
    }
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
        // Sử dụng context_module class đầy đủ
        $context = \context_module::instance($module->id);
        if (has_capability('mod/devcode:view', $context)) {
            $url = $CFG->wwwroot . '/mod/devcode/view.php?id=' . $module->id;
            $navref->add(get_string('viewsubmissions', 'mod_devcode'), $url, navigation_node::TYPE_SETTING);
        }
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
 * Get supported programming languages from the API
 * 
 * @return array Array of language id => language name
 */
function devcode_get_supported_languages()
{
    global $CFG;

    // Đảm bảo config đã được load
    if (!isset($CFG->devcode)) {
        require_once(dirname(__FILE__) . '/config.php');
    }

    $api_base = $CFG->devcode['api_base_url'];
    $languages_endpoint = $CFG->devcode['api_endpoints']['languages'];
    $url = $api_base . $languages_endpoint;

    $languages = array();

    // Thử sử dụng file_get_contents với stream context để xử lý lỗi
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => $CFG->devcode['api_timeout'],
            'ignore_errors' => true
        ]
    ]);

    $response = @file_get_contents($url, false, $context);

    // Kiểm tra nếu API không hoạt động, trả về danh sách mặc định
    if ($response === false) {
        debugging('Không thể kết nối đến API ngôn ngữ. Sử dụng danh sách mặc định.', DEBUG_DEVELOPER);
        return array(
            '71' => 'Python (3.8.1)',
            '62' => 'Java (JDK 13.0.1)',
            '54' => 'C++ (GCC 9.2.0)',
            '63' => 'JavaScript (Node.js 12.14.0)'
        );
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        debugging('Định dạng JSON không hợp lệ từ API. Sử dụng danh sách mặc định.', DEBUG_DEVELOPER);
        return array(
            '71' => 'Python (3.8.1)',
            '62' => 'Java (JDK 13.0.1)',
            '54' => 'C++ (GCC 9.2.0)',
            '63' => 'JavaScript (Node.js 12.14.0)'
        );
    }

    foreach ($data as $lang) {
        if (isset($lang['id']) && isset($lang['name'])) {
            // Chuyển ID thành chuỗi để tránh lỗi khi lưu vào database
            $languages[strval($lang['id'])] = $lang['name'];
        }
    }

    return $languages;
}

// Hàm gửi bài nộp đến backend để chấm điểm
function devcode_send_to_api($submissionid)
{
    global $CFG, $DB;

    // Debugging to verify function entry point
    debugging('Starting devcode_send_to_api with submissionid: ' . $submissionid, DEBUG_DEVELOPER);

    // Đảm bảo config đã được load
    if (!isset($CFG->devcode)) {
        require_once(dirname(__FILE__) . '/config.php');
    }

    // Lấy thông tin bài nộp
    $submission = $DB->get_record('devcode_submissions', array('id' => $submissionid), '*', MUST_EXIST);
    $devcode = $DB->get_record('devcode', array('id' => $submission->devcodeid), '*', MUST_EXIST);

    // Lấy tất cả test cases của bài tập
    $testcases = $DB->get_records('devcode_testcases', array('devcodeid' => $devcode->id), 'id ASC');

    if (empty($testcases)) {
        debugging('Không tìm thấy test cases nào cho bài tập này.', DEBUG_DEVELOPER);
        return false;
    }

    // Xác định ngôn ngữ lập trình - ưu tiên sử dụng ngôn ngữ từ assignment nếu có
    $language = $submission->language;
    if (!empty($devcode->language)) {
        $language = $devcode->language;
        debugging('Sử dụng ngôn ngữ lập trình từ assignment: ' . $language, DEBUG_DEVELOPER);
    }

    // Chuẩn bị dữ liệu để gửi lên API
    $api_data = array(
        'assignment_id' => $devcode->id,
        'userid' => $submission->userid,
        'code' => $submission->code,
        'language' => $language
    );

    // Gửi bài nộp lên API
    $api_base = $CFG->devcode['api_base_url'];
    $submissions_endpoint = $CFG->devcode['api_endpoints']['submissions'];
    $submission_url = $api_base . $submissions_endpoint;

    $submission_response = devcode_api_request($submission_url, 'POST', $api_data);

    if (!$submission_response || isset($submission_response['error'])) {
        debugging('Lỗi khi gửi bài nộp lên API: ' . json_encode($submission_response), DEBUG_DEVELOPER);

        // Cập nhật trạng thái lỗi
        $submission->status = 'error';
        $submission->feedback = isset($submission_response['error']) ? $submission_response['error'] : 'Không thể kết nối đến dịch vụ chấm điểm.';
        $DB->update_record('devcode_submissions', $submission);

        // Kiểm tra nếu mô phỏng được bật
        if ($CFG->devcode['api_mock_enabled']) {
            debugging('Sử dụng mô phỏng do API không khả dụng.', DEBUG_DEVELOPER);
            return false; // Trả về false để kích hoạt mô phỏng
        }

        return false;
    }

    // Lấy ID bài nộp từ API response
    $api_submission_id = $submission_response['id'];

    // Chuẩn bị dữ liệu test cases
    $testcase_data = array('testcases' => array());
    foreach ($testcases as $testcase) {
        $testcase_data['testcases'][] = array(
            'id' => $testcase->id,
            'input' => $testcase->input,
            'output' => $testcase->output,
            'points' => floatval($testcase->points),
            'time_limit' => intval($testcase->time_limit)
        );
    }

    // Gửi yêu cầu xử lý và chấm điểm
    $process_url = $api_base . $submissions_endpoint . $api_submission_id . '/process';
    $process_response = devcode_api_request($process_url, 'POST', $testcase_data);

    if (!$process_response || isset($process_response['error'])) {
        debugging('Lỗi khi xử lý bài nộp: ' . json_encode($process_response), DEBUG_DEVELOPER);

        // Cập nhật trạng thái lỗi
        $submission->status = 'error';
        $submission->feedback = isset($process_response['error']) ? $process_response['error'] : 'Lỗi khi chấm điểm bài nộp.';
        $DB->update_record('devcode_submissions', $submission);

        return false;
    }

    // Lấy kết quả chi tiết từng test case
    $results_url = $api_base . $submissions_endpoint . $api_submission_id . '/results';
    $results_response = devcode_api_request($results_url, 'GET');

    if (!$results_response || isset($results_response['error'])) {
        debugging('Lỗi khi lấy kết quả chi tiết: ' . json_encode($results_response), DEBUG_DEVELOPER);
        return false;
    }

    // Xử lý kết quả từng test case
    $total_points = 0;
    $earned_points = 0;
    $passed_tests = 0;
    $total_testcases = count($testcases);
    $latest_failed_result = null;
    $latest_failed_testcase = null;

    // Xóa kết quả cũ (nếu có)
    $DB->delete_records('devcode_submission_results', array('submissionid' => $submissionid));

    // Sắp xếp kết quả theo thứ tự index để đảm bảo đúng thứ tự
    usort($results_response, function ($a, $b) {
        return $a['index'] - $b['index'];
    });

    // Xác định các test cases đã pass và test case bị fail gần nhất (có index lớn nhất)
    $passed_test_results = array();

    foreach ($results_response as $result) {
        $testcase_id = $result['testcase_id'];
        $testcase = $DB->get_record('devcode_testcases', array('id' => $testcase_id));

        if ($testcase) {
            $total_points += $testcase->points;
            $earned_points += $result['points_earned'];

            // Tạo đối tượng kết quả test
            $test_result = new stdClass();
            $test_result->submissionid = $submissionid;
            $test_result->testcaseid = $testcase_id;
            $test_result->passed = $result['passed'];
            $test_result->output = $result['output'];
            $test_result->error_message = $result['error_message'];
            $test_result->execution_time = $result['execution_time'];
            $test_result->memory_used = $result['memory_used'];
            $test_result->timecreated = time();

            if ($result['passed'] == 1) {
                // Nếu test case pass, thêm vào danh sách passed
                $passed_tests++;
                $passed_test_results[] = $test_result;
            } else {
                // Nếu test case fail, kiểm tra xem có phải là cái mới nhất (index lớn nhất) không
                if ($latest_failed_result === null || $result['index'] > $latest_failed_result['index']) {
                    $latest_failed_result = $result;
                    $latest_failed_testcase = $testcase;
                    // Không lưu lại kết quả fail này ngay, chỉ giữ lại thông tin
                }
            }
        }
    }

    // Lưu tất cả test case đã pass
    foreach ($passed_test_results as $passed_result) {
        $DB->insert_record('devcode_submission_results', $passed_result);
    }

    // Lưu test case fail mới nhất nếu có
    if ($latest_failed_result !== null) {
        $test_result = new stdClass();
        $test_result->submissionid = $submissionid;
        $test_result->testcaseid = $latest_failed_testcase->id;
        $test_result->passed = 0;
        $test_result->output = $latest_failed_result['output'];
        $test_result->error_message = $latest_failed_result['error_message'];
        $test_result->execution_time = $latest_failed_result['execution_time'];
        $test_result->memory_used = $latest_failed_result['memory_used'];
        $test_result->timecreated = time();

        $DB->insert_record('devcode_submission_results', $test_result);

        // Lưu thông tin test case bị lỗi để hiển thị feedback
        $failed_test_info = array(
            'testcase_id' => $latest_failed_testcase->id,
            'input' => $latest_failed_testcase->input,
            'expected' => $latest_failed_testcase->output,
            'actual' => $latest_failed_result['output'],
            'error' => $latest_failed_result['error_message'],
            'result' => isset($latest_failed_result['result']) ? $latest_failed_result['result'] : 'Wrong Answer'
        );
    }

    // Tính điểm tổng và cập nhật bài nộp
    $final_score = ($total_points > 0) ? ($earned_points / $total_points) * 10 : 0;

    // Chuẩn bị phản hồi
    $feedback = "";
    if ($latest_failed_result !== null) {
        // Nếu có test case bị lỗi, hiển thị thông tin chi tiết
        $submission->status = 'failed';

        if ($failed_test_info) {
            $feedback = "Test case failed. ";

            if (!empty($failed_test_info['error'])) {
                // Lỗi runtime/compilation
                $feedback .= "Error: " . $failed_test_info['error'];
            } else {
                // Sai kết quả
                $feedback .= "Failed on test case #" . $failed_test_info['testcase_id'] . ".\n";
                $feedback .= "Input: " . $failed_test_info['input'] . "\n";
                $feedback .= "Expected output: " . $failed_test_info['expected'] . "\n";
                $feedback .= "Your output: " . $failed_test_info['actual'];

                if (isset($failed_test_info['result']) && $failed_test_info['result'] != 'Wrong Answer') {
                    $feedback .= "\nResult: " . $failed_test_info['result'];
                }
            }
        } else {
            $feedback = "Test execution failed with an unknown error.";
        }
    } else {
        // Nếu tất cả test case đều pass, thông báo kết quả tổng quan
        $submission->status = 'graded';
        $feedback = "Passed $passed_tests out of $total_testcases test cases.";
    }

    // Cập nhật thông tin bài nộp
    $submission->score = $final_score;
    $submission->feedback = $feedback;
    $submission->passed_tests = $passed_tests;
    $submission->total_tests = $total_testcases;
    $submission->timemodified = time();

    $DB->update_record('devcode_submissions', $submission);

    // Cập nhật điểm vào gradebook
    devcode_update_grades($devcode, $submission->userid);

    return true;
}

/**
 * Hàm gửi request đến backend API
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
 * Lấy thống kê về test cases của một bài nộp
 * 
 * @param int $submissionid ID của bài nộp
 * @return object Đối tượng chứa thông tin về số test cases đã pass và tổng số test cases
 */
function devcode_get_submission_stats($submissionid)
{
    global $DB;

    $stats = new stdClass();
    $stats->passed_tests = 0;
    $stats->total_tests = 0;

    // Lấy thông tin bài nộp
    $submission = $DB->get_record('devcode_submissions', array('id' => $submissionid));
    if (!$submission) {
        return $stats;
    }

    // Nếu đã có thông tin cached trong bài nộp
    if (isset($submission->passed_tests) && isset($submission->total_tests)) {
        $stats->passed_tests = $submission->passed_tests;
        $stats->total_tests = $submission->total_tests;
        return $stats;
    }

    // Nếu không có thông tin cached, tính toán từ kết quả chi tiết
    $results = $DB->get_records('devcode_submission_results', array('submissionid' => $submissionid));
    $stats->total_tests = count($results);

    foreach ($results as $result) {
        if ($result->passed == 1) {
            $stats->passed_tests++;
        }
    }

    return $stats;
}

function devcode_get_language_by_id($language_id)
{
    global $CFG;

    // Đảm bảo config đã được load
    if (!isset($CFG->devcode)) {
        require_once(dirname(__FILE__) . '/config.php');
    }

    // Danh sách ngôn ngữ mặc định để fallback
    $default_languages = array(
        '71' => 'Python (3.8.1)',
        '62' => 'Java (JDK 13.0.1)',
        '54' => 'C++ (GCC 9.2.0)',
        '63' => 'JavaScript (Node.js 12.14.0)'
    );

    // Kiểm tra nếu language_id tồn tại trong danh sách mặc định
    if (isset($default_languages[$language_id])) {
        return $default_languages[$language_id];
    }

    // Nếu không tìm thấy trong danh sách mặc định, thử lấy từ API
    try {
        $api_base = $CFG->devcode['api_base_url'];
        $languages_endpoint = $CFG->devcode['api_endpoints']['languages'];
        $url = $api_base . $languages_endpoint;

        // Thử sử dụng file_get_contents với stream context để xử lý lỗi
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $CFG->devcode['api_timeout'],
                'ignore_errors' => true
            ]
        ]);

        $response = @file_get_contents($url, false, $context);

        // Kiểm tra nếu API không hoạt động, trả về từ danh sách mặc định
        if ($response === false) {
            debugging('Không thể kết nối đến API ngôn ngữ. Sử dụng danh sách mặc định.', DEBUG_DEVELOPER);
            return $language_id; // Trả về ID nếu không có tên
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            debugging('Định dạng JSON không hợp lệ từ API. Sử dụng ID.', DEBUG_DEVELOPER);
            return $language_id;
        }

        // Tìm ngôn ngữ trong danh sách API
        foreach ($data as $lang) {
            if (isset($lang['id']) && $lang['id'] == $language_id && isset($lang['name'])) {
                return $lang['name'];
            }
        }

        // Nếu không tìm thấy, trả về ID
        return $language_id;
    } catch (Exception $e) {
        debugging('Lỗi khi lấy thông tin ngôn ngữ: ' . $e->getMessage(), DEBUG_DEVELOPER);
        return $language_id;
    }
}
