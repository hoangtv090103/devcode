<?php
/**
 * Plagiarism detection functions for module devcode
 *
 * All functions related to plagiarism detection
 *
 * @package    mod_devcode
 * @copyright  2024 Your Name <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__) . '/config.php');
require_once(dirname(__FILE__) . '/apilib.php');
require_once(dirname(__FILE__) . '/gradelib.php');

/**
 * Checks a submission for plagiarism.
 *
 * @param int $submissionid The ID of the submission to check
 * @return bool True if plagiarism is detected, false otherwise
 */
function devcode_check_plagiarism($submissionid) {
    global $CFG, $DB;

    // Debugging to verify function entry point
    debugging('Starting devcode_check_plagiarism with submissionid: ' . $submissionid, DEBUG_DEVELOPER);

    // Đảm bảo config đã được load
    if (!isset($CFG->devcode)) {
        require_once(dirname(__FILE__) . '/config.php');
    }

    // Lấy thông tin bài nộp
    $submission = $DB->get_record('devcode_submissions', array('id' => $submissionid), '*', MUST_EXIST);
    $devcode = $DB->get_record('devcode', array('id' => $submission->devcodeid), '*', MUST_EXIST);
    
    // Đảm bảo có thông tin về khóa học
    if (empty($devcode->course)) {
        $cm = get_coursemodule_from_instance('devcode', $devcode->id, 0, false, MUST_EXIST);
        $devcode->course = $cm->course;
    }

    // Skip plagiarism check if it's disabled for this assignment
    if (empty($devcode->enable_plagiarism)) {
        return false;
    }

    // Xác định ngôn ngữ lập trình
    $language = $submission->language;
    if (!empty($devcode->language)) {
        $language = $devcode->language;
    }
    
    // Get other submissions for this assignment from other users
    $other_submissions = $DB->get_records_sql(
        "SELECT * FROM {devcode_submissions} 
         WHERE devcodeid = ? AND userid != ? AND id != ?
         ORDER BY timemodified DESC",
        array($devcode->id, $submission->userid, $submission->id)
    );
    
    // If there are no other submissions, no need to check for plagiarism
    if (empty($other_submissions)) {
        debugging('No other submissions found for plagiarism comparison', DEBUG_DEVELOPER);
        return false;
    }
    
    // Get Dolos configuration
    $config = get_config('mod_devcode');
    $dolos_enabled = !empty($config->dolos_api_url) || (isset($CFG->devcode['dolos']) && $CFG->devcode['plagiarism']['enabled']);
    
    // Determine if we should use direct Dolos API integration
    if ($dolos_enabled) {
        debugging('Using direct Dolos API integration for plagiarism detection', DEBUG_DEVELOPER);
        
        // Process using Dolos API
        $result = devcode_check_plagiarism_dolos($submission, $other_submissions, $language, $devcode);
        return $result;
    }
    
    // Legacy approach using backend API
    debugging('Using legacy backend API for plagiarism detection', DEBUG_DEVELOPER);

    // Chuẩn bị dữ liệu để gửi lên API
    $api_data = array(
        'assignment_id' => $devcode->id,
        'userid' => $submission->userid,
        'code' => $submission->code,
        'language' => $language,
        'plagiarism_check_only' => true
    );

    // Gửi bài nộp lên API
    $api_base = $CFG->devcode['api_base_url'];
    $submissions_endpoint = $CFG->devcode['api_endpoints']['submissions'];
    $submission_url = $api_base . $submissions_endpoint;

    $submission_response = devcode_api_request($submission_url, 'POST', $api_data);

    if (!$submission_response || isset($submission_response['error'])) {
        debugging('Lỗi khi gửi yêu cầu kiểm tra đạo văn lên API: ' . json_encode($submission_response), DEBUG_DEVELOPER);
        return false;
    }

    // Check if plagiarism was detected
    $plagiarism_detected = isset($submission_response['plagiarism_detected']) && 
                           $submission_response['plagiarism_detected'] === true;
    
    if ($plagiarism_detected) {
        // Update submission with plagiarism information
        $submission->status = 'plagiarism';
        $plagiarism_similarity = isset($submission_response['plagiarism_similarity']) ? 
                               floatval($submission_response['plagiarism_similarity']) : 0;
        $plagiarism_url = isset($submission_response['plagiarism_url']) ? 
                         $submission_response['plagiarism_url'] : '';
        
        $plagiarism_message = get_string('plagiarism_detected', 'mod_devcode', format_string($plagiarism_similarity));
        
        if (!empty($plagiarism_url)) {
            $submission->plagiarism_url = $plagiarism_url;
            $plagiarism_message .= ' ' . get_string('plagiarism_details', 'mod_devcode', $plagiarism_url);
        }
        
        $submission->score = 0;
        $submission->feedback = $plagiarism_message;
        $submission->timemodified = time();
        
        $DB->update_record('devcode_submissions', $submission);
        
        // Cập nhật điểm vào gradebook
        devcode_update_grades($devcode, $submission->userid);
        
        return true;
    }
    
    return false;
}

/**
 * Checks a submission for plagiarism using Dolos API directly
 * 
 * @param object $submission The submission record
 * @param array $other_submissions Array of other submission records
 * @param string $language The programming language 
 * @param object $devcode The assignment record
 * @return bool True if plagiarism is detected, false otherwise
 */
function devcode_check_plagiarism_dolos($submission, $other_submissions, $language, $devcode) {
    global $CFG, $DB, $USER;
    
    debugging('Starting dolos plagiarism check', DEBUG_DEVELOPER);
    
    // Get normalized language name for Dolos
    $norm_language = devcode_normalize_language_for_dolos($language);
    
    // Create submissions for Dolos analysis
    $formatted_submissions = [];
    
    // Add current submission
    $formatted_submissions[] = [
        "id" => "current",
        "filename" => "current_submission." . devcode_get_file_extension($norm_language),
        "code" => $submission->code,
        "username" => "user_" . $submission->userid
    ];
    
    // Add other submissions 
    foreach ($other_submissions as $other) {
        // Skip submissions with different language
        if ($other->language != $submission->language) {
            continue;
        }
        
        $formatted_submissions[] = [
            "id" => (string)$other->id,
            "filename" => "submission_" . $other->id . "." . devcode_get_file_extension($norm_language),
            "code" => $other->code,
            "username" => "user_" . $other->userid
        ];
    }
    
    // Need at least 2 submissions to compare
    if (count($formatted_submissions) < 2) {
        debugging('Not enough submissions for plagiarism detection', DEBUG_DEVELOPER);
        return false;
    }
    
    // Create zip file with all submissions
    $zip_buffer = devcode_create_submissions_zip($formatted_submissions);
    if (!$zip_buffer) {
        debugging('Failed to create zip file for Dolos', DEBUG_DEVELOPER);
        return false;
    }
    
    // Submit to Dolos API
    $dolos_result = devcode_submit_to_dolos_api($zip_buffer, "assignment_" . $devcode->id, $norm_language);
    
    if (!$dolos_result || !isset($dolos_result['id'])) {
        debugging('Failed to submit to Dolos API: ' . json_encode($dolos_result), DEBUG_DEVELOPER);
        return false;
    }
    
    $report_id = $dolos_result['id'];
    $html_url = $dolos_result['html_url'] ?? '';
    
    // Poll until report is complete
    $report = devcode_poll_dolos_report($report_id);
    if (!$report || $report['status'] != 'finished') {
        debugging('Failed to get Dolos report: ' . json_encode($report), DEBUG_DEVELOPER);
        return false;
    }
    
    // Get pairs data
    $pairs_data = devcode_get_dolos_report_data($report_id, 'pairs');
    if (!$pairs_data) {
        debugging('Failed to get pairs data from Dolos', DEBUG_DEVELOPER);
        return false;
    }
    
    // Get files data to map file paths to submission IDs
    $files_data = devcode_get_dolos_report_data($report_id, 'files');
    if (!$files_data) {
        debugging('Failed to get files data from Dolos', DEBUG_DEVELOPER);
        return false;
    }
    
    // Process pairs data to find matches with current submission
    $plagiarism_detected = false;
    $highest_similarity = 0;
    $similarity_details = [];
    $threshold = isset($devcode->similarity_threshold) ? 
        ($devcode->similarity_threshold / 100) : // Convert percentage to decimal
        $CFG->devcode['plagiarism']['threshold']; // Use default threshold
    
    // Build a map of file paths to submission IDs
    $file_path_map = [];
    foreach ($files_data as $file) {
        $path = $file['path'] ?? '';
        if (strpos($path, 'current_submission') !== false) {
            $file_path_map[$path] = 'current';
        } else {
            foreach ($formatted_submissions as $sub) {
                if ($sub['id'] != 'current' && strpos($path, $sub['filename']) !== false) {
                    $file_path_map[$path] = $sub['id'];
                    break;
                }
            }
        }
    }
    
    foreach ($pairs_data as $pair) {
        $left_path = $pair['leftFilePath'] ?? '';
        $right_path = $pair['rightFilePath'] ?? '';
        $similarity = isset($pair['similarity']) ? floatval($pair['similarity']) : 0;
        
        // Check if this pair involves the current submission
        $is_current_pair = false;
        $other_sub_id = null;
        
        if (isset($file_path_map[$left_path]) && $file_path_map[$left_path] == 'current') {
            $is_current_pair = true;
            $other_sub_id = $file_path_map[$right_path] ?? null;
        } else if (isset($file_path_map[$right_path]) && $file_path_map[$right_path] == 'current') {
            $is_current_pair = true;
            $other_sub_id = $file_path_map[$left_path] ?? null;
        }
        
        if ($is_current_pair && $other_sub_id) {
            if ($similarity > $highest_similarity) {
                $highest_similarity = $similarity;
            }
            
            // If similarity is above threshold, consider it plagiarism
            if ($similarity >= $threshold) {
                $plagiarism_detected = true;
                $similarity_details[] = [
                    'submission_id' => $other_sub_id,
                    'similarity' => $similarity,
                    'pair_data' => $pair
                ];
            }
        }
    }
    
    // If plagiarism was detected, update the submission record
    if ($plagiarism_detected) {
        $submission->status = 'plagiarism_detected';
        $similarity_percent = round($highest_similarity * 100, 2);
        $plagiarism_message = get_string('plagiarism_detected', 'devcode', $similarity_percent);
        
        if (!empty($html_url)) {
            $submission->plagiarism_url = $html_url;
            $plagiarism_message .= ' ' . get_string('plagiarism_details', 'devcode', $html_url);
        }
        
        $submission->score = 0;
        $submission->feedback = $plagiarism_message;
        $submission->timemodified = time();
        
        $DB->update_record('devcode_submissions', $submission);
        
        // Create plagiarism record for each match
        foreach ($similarity_details as $match) {
            $plagiarism_record = new stdClass();
            $plagiarism_record->submission1_id = $submission->id;
            $plagiarism_record->submission2_id = $match['submission_id'];
            $plagiarism_record->similarity_score = $match['similarity'];
            $plagiarism_record->devcodeid = $devcode->id;
            $plagiarism_record->details = json_encode($match['pair_data']);
            $plagiarism_record->flagged = 1;
            $plagiarism_record->timecreated = time();
            $plagiarism_record->timemodified = time();
            
            $DB->insert_record('devcode_plagiarism', $plagiarism_record);
        }
        
        // Cập nhật điểm vào gradebook
        devcode_update_grades($devcode, $submission->userid);
        
        return true;
    }
    
    return false;
}

/**
 * Create a zip file in memory from submission data
 * 
 * @param array $submissions Array of submission data with id, filename, and code
 * @return string|false Binary content of the zip file or false on failure
*/
function devcode_create_submissions_zip($submissions) {
    try {
        $zip_buffer = new ZipArchive();

        // Create a truly empty temp file, then delete it before open()
        $temp_file = tempnam(sys_get_temp_dir(), 'dolos_');
        if (!$temp_file || !file_exists($temp_file)) {
            debugging('Cannot create temporary file for ZIP archive', DEBUG_DEVELOPER);
            return false;
        }
        // Remove the empty file before open() to avoid ZipArchive deprecation warning
        unlink($temp_file);

        if ($zip_buffer->open($temp_file, ZipArchive::CREATE) !== true) {
            debugging('Cannot create ZIP archive', DEBUG_DEVELOPER);
            return false;
        }

        foreach ($submissions as $submission) {
            $zip_buffer->addFromString($submission['filename'], $submission['code']);
        }

        $zip_buffer->close();

        $content = file_get_contents($temp_file);
        unlink($temp_file);

        return $content;
    } catch (Exception $e) {
        debugging('Error creating ZIP file: ' . $e->getMessage(), DEBUG_DEVELOPER);
        return false;
    }
}

/**
 * Submit a zip file to Dolos API for analysis
 * 
 * @param string $zip_content Binary content of the zip file
 * @param string $name Name for the analysis
 * @param string $language Programming language
 * @return array|bool API response or false on error
 */
function devcode_submit_to_dolos_api($zip_content, $name, $language = '') {
    global $CFG;
    
    // Get Dolos configuration
    $config = devcode_get_dolos_config();
    
    $dolos_url = $config['dolos_api_url'];
    $endpoint = rtrim($dolos_url, '/') . '/reports';
    
    // Write zip to temp file
    $temp_zip = tempnam(sys_get_temp_dir(), 'dolos_zip_');
    file_put_contents($temp_zip, $zip_content);
    
    // Create curl request
    $curl = curl_init();
    
    // Create post data
    $post_data = [
        'name' => $name,
    ];
    
    if (!empty($language)) {
        $post_data['language'] = $language;
    }
    
    // Add file 
    $cfile = curl_file_create(
        $temp_zip,
        'application/zip',
        'submissions.zip'
    );
    $post_data['file'] = $cfile;
    
    // Configure curl
    curl_setopt_array($curl, [
        CURLOPT_URL => $endpoint,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $post_data,
        CURLOPT_TIMEOUT => $config['dolos_timeout']
    ]);
    
    // Add API key if available
    if (!empty($config['dolos_api_key'])) {
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $config['dolos_api_key']
        ]);
    }
    
    // Execute request
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    
    // Check for errors
    if (curl_errno($curl) || $http_code >= 400) {
        debugging('Failed to submit to Dolos API', DEBUG_DEVELOPER);
        curl_close($curl);
        unlink($temp_zip);
        return false;
    }
    
    curl_close($curl);
    unlink($temp_zip);
    
    // Decode response
    $result = json_decode($response, true);
    if (!$result) {
        debugging('Invalid JSON response from Dolos API', DEBUG_DEVELOPER);
        return false;
    }
    
    return $result;
}

/**
 * Poll Dolos API until the report is complete
 * 
 * @param string $report_id The ID of the report to poll
 * @return array|bool Report data or false on error
 */
function devcode_poll_dolos_report($report_id) {
    global $CFG;
    
    // Get Dolos configuration
    $config = devcode_get_dolos_config();
    
    $dolos_url = $config['dolos_api_url'];
    $endpoint = rtrim($dolos_url, '/') . '/reports/' . $report_id;
    $max_attempts = $config['dolos_max_poll_attempts'];
    $interval = $config['dolos_poll_interval'];
    
    // Set up request options
    $options = [
        'http' => [
            'method' => 'GET',
            'timeout' => $config['dolos_timeout']
        ]
    ];
    
    // Add API key if available
    if (!empty($config['dolos_api_key'])) {
        $options['http']['header'] = 'Authorization: Bearer ' . $config['dolos_api_key'];
    }
    
    // Poll until complete or max attempts reached
    $attempts = 0;
    while ($attempts < $max_attempts) {
        $context = stream_context_create($options);
        $response = @file_get_contents($endpoint, false, $context);
        
        if ($response === false) {
            return false;
        }
        
        $result = json_decode($response, true);
        if (!$result) {
            return false;
        }
        
        // Check if the report is complete
        if (isset($result['status']) && $result['status'] === 'processed') {
            return $result;
        } else if (isset($result['status']) && $result['status'] === 'failed') {
            debugging('Dolos report failed: ' . json_encode($result), DEBUG_DEVELOPER);
            return false;
        }
        
        // Wait before next attempt
        sleep($interval);
        $attempts++;
    }
    
    debugging('Max polling attempts reached for Dolos report', DEBUG_DEVELOPER);
    return false;
}

/**
 * Get report data from Dolos API
 * 
 * @param string $report_id The ID of the report
 * @param string $data_type Type of data (pairs or files)
 * @return array|bool Data or false on error
 */
function devcode_get_dolos_report_data($report_id, $data_type) {
    global $CFG;
    
    // Get Dolos configuration
    $config = devcode_get_dolos_config();
    
    $dolos_url = $config['dolos_api_url'];
    
    // Map data type to file name
    $file_name = '';
    if ($data_type === 'pairs') {
        $file_name = 'pairs.json';
    } else if ($data_type === 'files') {
        $file_name = 'files.json';
    } else {
        return false;
    }
    
    // Construct the endpoint URL
    $endpoint = rtrim($dolos_url, '/') . '/reports/' . $report_id . '/data/' . $file_name;
    
    // Set up request options
    $options = [
        'http' => [
            'method' => 'GET',
            'timeout' => $config['dolos_timeout'],
        ]
    ];
    
    // Add API key if available
    if (!empty($config['dolos_api_key'])) {
        $options['http']['header'] = 'Authorization: Bearer ' . $config['dolos_api_key'];
    }
    
    // Make request
    $context = stream_context_create($options);
    $response = @file_get_contents($endpoint, false, $context);
    
    if ($response === false) {
        debugging('Failed to get report data from Dolos API', DEBUG_DEVELOPER);
        return false;
    }
    
    // Parse the response
    $data = json_decode($response, true);
    if (!$data) {
        return false;
    }
    
    return $data;
}

/**
 * Normalize language name for Dolos API
 * 
 * @param string|int $language Language ID or name
 * @return string Normalized language name
 */
function devcode_normalize_language_for_dolos($language) {
    global $CFG;
    
    // If it's a language ID, get the language name first
    if (is_numeric($language)) {
        $language = devcode_get_language_by_id($language);
    }
    
    // Extract just the language name without version
    $language = strtolower($language);
    $language = preg_replace('/\(.*\)/', '', $language);
    $language = trim($language);
    
    // Map to Dolos language names
    $language_mapping = $CFG->devcode['plagiarism']['language_mapping'];
    
    foreach ($language_mapping as $key => $value) {
        if (strpos($language, $key) !== false) {
            return $value;
        }
    }
    
    // Default
    return 'generic';
}

/**
 * Get file extension for a given language
 * 
 * @param string $language Language name
 * @return string File extension
 */
function devcode_get_file_extension($language) {
    $extensions = [
        'python' => 'py',
        'java' => 'java',
        'cpp' => 'cpp',
        'c' => 'c',
        'javascript' => 'js',
        'typescript' => 'ts',
        'go' => 'go',
        'rust' => 'rs',
        'php' => 'php',
    ];
    
    $language = strtolower($language);
    
    foreach ($extensions as $lang => $ext) {
        if (strpos($language, $lang) !== false) {
            return $ext;
        }
    }
    
    return 'txt';
}

/**
 * Get Dolos configuration from plugin settings
 *
 * @return array Configuration array
 */
function devcode_get_dolos_config() {
    global $CFG;
    
    // First try to get from module settings
    $config = get_config('mod_devcode');
    
    // Default values from config.php if module settings not available
    $default_api_url = isset($CFG->devcode['dolos']['api_url']) ? 
                       $CFG->devcode['dolos']['api_url'] : 'https://dolos.ugent.be/api';
    
    $default_api_key = isset($CFG->devcode['dolos']['api_key']) ? 
                       $CFG->devcode['dolos']['api_key'] : '';
    
    $default_timeout = isset($CFG->devcode['dolos']['timeout']) ? 
                       $CFG->devcode['dolos']['timeout'] : 30;
    
    $default_max_poll_attempts = isset($CFG->devcode['dolos']['max_poll_attempts']) ? 
                                $CFG->devcode['dolos']['max_poll_attempts'] : 60;
    
    $default_poll_interval = isset($CFG->devcode['dolos']['poll_interval']) ? 
                            $CFG->devcode['dolos']['poll_interval'] : 1;
    
    // Create configuration array with fallbacks
    return [
        'dolos_api_url' => !empty($config->dolos_api_url) ? $config->dolos_api_url : $default_api_url,
        'dolos_api_key' => !empty($config->dolos_api_key) ? $config->dolos_api_key : $default_api_key,
        'dolos_timeout' => !empty($config->dolos_timeout) ? $config->dolos_timeout : $default_timeout,
        'dolos_max_poll_attempts' => $default_max_poll_attempts,
        'dolos_poll_interval' => $default_poll_interval
    ];
} 