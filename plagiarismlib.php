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

    // Increase script execution time for this operation
    $original_time_limit = ini_get('max_execution_time');
    set_time_limit(300); // 5 minutes should be enough for plagiarism check
    
    try {
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
        
        // Chỉ lấy các lần nộp mới nhất của các user
        $other_submissions = $DB->get_records_sql(
            "SELECT s.*
             FROM {devcode_submissions} s
             INNER JOIN (
                 SELECT userid, MAX(timemodified) AS maxtime
                 FROM {devcode_submissions}
                 WHERE devcodeid = ? AND userid != ? AND id != ?
                 GROUP BY userid
             ) latest
             ON s.userid = latest.userid AND s.timemodified = latest.maxtime
             WHERE s.devcodeid = ? AND s.userid != ? AND s.id != ?",
            array($devcode->id, $submission->userid, $submission->id, $devcode->id, $submission->userid, $submission->id)
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
            // Get the similarity score from the response
            $plagiarism_similarity = isset($submission_response['plagiarism_similarity']) ? 
                                   floatval($submission_response['plagiarism_similarity']) : 0;
            
            // Get the threshold from the assignment settings
            $threshold = isset($devcode->similarity_threshold) ? 
                ($devcode->similarity_threshold / 100) : // Convert percentage to decimal
                0.8; // Default threshold if not defined
            
            // Only mark as plagiarism if it exceeds the threshold
            if ($plagiarism_similarity >= $threshold) {
                // Update submission with plagiarism information
                $submission->status = 'plagiarism';
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
        }
        
        return false;
    } finally {
        // Reset to original time limit
        set_time_limit($original_time_limit);
    }
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
    
    if (!$dolos_result) {
        debugging('Failed to submit to Dolos API: No result returned', DEBUG_DEVELOPER);
        return false;
    }
    
    // Extract the report URL directly from the API response (new Dolos API format)
    $html_url = $dolos_result['html_url'] ?? '';
    if (empty($html_url)) {
        debugging('No HTML URL in Dolos API response: ' . json_encode($dolos_result), DEBUG_DEVELOPER);
        return false;
    }
    
    debugging('Dolos report URL: ' . $html_url, DEBUG_DEVELOPER);
    
    // Extract the report ID from the URL or response
    $report_id = $dolos_result['id'] ?? '';
    if (empty($report_id)) {
        // Try to extract from URL
        if (preg_match('/\/reports\/([^\/]+)/', $html_url, $matches)) {
            $report_id = $matches[1];
        }
    }
    
    if (empty($report_id)) {
        debugging('Could not determine report ID from Dolos response', DEBUG_DEVELOPER);
        return false;
    }
    
    // Process the Dolos report data using our new function
    // This will only flag plagiarism if similarity exceeds threshold
    return devcode_process_dolos_report($report_id, $submission, $devcode);
}

/**
 * Create a zip file in memory from submission data
 * 
 * @param array $submissions Array of submission data with id, filename, and code
 * @return string|false Binary content of the zip file or false on failure
*/
function devcode_create_submissions_zip($submissions) {
    try {
        // Create a temporary directory for the files
        $temp_dir = rtrim(sys_get_temp_dir(), '/') . '/' . uniqid('dolos_dir_');
        if (!mkdir($temp_dir, 0755, true)) {
            debugging('Cannot create temporary directory for ZIP files', DEBUG_DEVELOPER);
            return false;
        }
        
        // Create a temporary file for each submission
        foreach ($submissions as $submission) {
            $file_path = $temp_dir . '/' . $submission['filename'];
            file_put_contents($file_path, $submission['code']);
        }
        
        // Create a temporary zip file
        $temp_zip = tempnam(sys_get_temp_dir(), 'dolos_zip_');
        $temp_zip_with_ext = $temp_zip . '.zip'; // Add .zip extension
        rename($temp_zip, $temp_zip_with_ext);
        $temp_zip = $temp_zip_with_ext;
        
        // Create the zip archive using ZipArchive
        $zip = new ZipArchive();
        if ($zip->open($temp_zip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            debugging('Cannot create ZIP archive', DEBUG_DEVELOPER);
            // Clean up temporary files
            array_map('unlink', glob("$temp_dir/*"));
            rmdir($temp_dir);
            return false;
        }
        
        // Add files to the zip archive
        $files = glob($temp_dir . '/*');
        foreach ($files as $file) {
            $zip->addFile($file, basename($file));
        }
        
        // Close the zip file
        $zip->close();
        
        // Get the contents of the zip file
        $content = file_get_contents($temp_zip);
        
        // Clean up temporary files
        unlink($temp_zip);
        array_map('unlink', glob("$temp_dir/*"));
        rmdir($temp_dir);
        
        return $content;
    } catch (Exception $e) {
        debugging('Error creating ZIP file: ' . $e->getMessage(), DEBUG_DEVELOPER);
        
        // Clean up any temporary files if they exist
        if (isset($temp_dir) && is_dir($temp_dir)) {
            array_map('unlink', glob("$temp_dir/*"));
            rmdir($temp_dir);
        }
        
        if (isset($temp_zip) && file_exists($temp_zip)) {
            unlink($temp_zip);
        }
        
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
    // Remove /api prefix as it's already included in the URL
    $endpoint = rtrim($dolos_url, '/') . '/reports';
    
    debugging('Submitting to Dolos API endpoint: ' . $endpoint, DEBUG_DEVELOPER);
    
    // Write zip to temp file with a proper filename
    $temp_zip = tempnam(sys_get_temp_dir(), 'dolos_zip_');
    $temp_zip_with_ext = $temp_zip . '.zip'; // Add .zip extension
    rename($temp_zip, $temp_zip_with_ext);
    $temp_zip = $temp_zip_with_ext;
    
    file_put_contents($temp_zip, $zip_content);
    
    // Create curl request with proper timeout
    $curl = curl_init();
    
    // Format the post data according to Dolos API requirements
    $post_data = [
        'dataset[name]' => $name
    ];
    
    // Add language if specified
    if (!empty($language)) {
        $post_data['dataset[language]'] = $language;
    }
    
    // Add zipfile using the correct parameter name and filename
    $cfile = curl_file_create(
        $temp_zip,
        'application/zip',
        'submissions.zip' // Use a proper filename
    );
    $post_data['dataset[zipfile]'] = $cfile;
    
    // Configure curl with increased timeout
    curl_setopt_array($curl, [
        CURLOPT_URL => $endpoint,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $post_data,
        CURLOPT_TIMEOUT => $config['dolos_timeout'],
        CURLOPT_CONNECTTIMEOUT => 30, // Add connection timeout
        CURLOPT_VERBOSE => true // Enable verbose mode for debugging
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
    $curl_error = curl_error($curl);
    
    // Log detailed information about the request
    debugging('Dolos API response code: ' . $http_code, DEBUG_DEVELOPER);
    if (!empty($curl_error)) {
        debugging('Curl error: ' . $curl_error, DEBUG_DEVELOPER);
    }
    
    // Check for errors
    if (curl_errno($curl) || $http_code >= 400) {
        debugging('Failed to submit to Dolos API: ' . $curl_error . ' HTTP code: ' . $http_code, DEBUG_DEVELOPER);
        curl_close($curl);
        unlink($temp_zip);
        return false;
    }
    
    curl_close($curl);
    unlink($temp_zip);
    
    // Decode response
    $result = json_decode($response, true);
    if (!$result) {
        debugging('Invalid JSON response from Dolos API: ' . $response, DEBUG_DEVELOPER);
        return false;
    }
    
    debugging('Dolos API response: ' . json_encode($result), DEBUG_DEVELOPER);
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
    
    // Set up curl for API request
    $curl = curl_init($endpoint);
    
    // Add API key if available
    $headers = [];
    if (!empty($config['dolos_api_key'])) {
        $headers[] = 'Authorization: Bearer ' . $config['dolos_api_key'];
    }
    
    if (!empty($headers)) {
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    }
    
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_TIMEOUT, $config['dolos_timeout']);
    
    // Poll until complete or max attempts reached
    $attempts = 0;
    while ($attempts < $max_attempts) {
        // Execute the request
        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        
        if (curl_errno($curl) || $http_code >= 400) {
            debugging('Error polling Dolos API: ' . curl_error($curl) . ' HTTP code: ' . $http_code, DEBUG_DEVELOPER);
            $attempts++;
            sleep($interval);
            continue;
        }
        
        // Parse the response
        $result = json_decode($response, true);
        if (!$result) {
            debugging('Invalid JSON response from Dolos API: ' . $response, DEBUG_DEVELOPER);
            $attempts++;
            sleep($interval);
            continue;
        }
        
        // Check if the report is complete
        if (isset($result['status']) && ($result['status'] === 'finished' || $result['status'] === 'processed')) {
            curl_close($curl);
            return $result;
        } else if (isset($result['status']) && $result['status'] === 'failed') {
            debugging('Dolos report failed: ' . json_encode($result), DEBUG_DEVELOPER);
            curl_close($curl);
            return false;
        }
        
        // Wait before next attempt
        sleep($interval);
        $attempts++;
    }
    
    curl_close($curl);
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
    
    // Construct the endpoint URL - updated to new API path
    $endpoint = rtrim($dolos_url, '/') . '/api/reports/' . $report_id . '/data/' . $file_name;
    
    // Set up curl for API request
    $curl = curl_init($endpoint);
    
    // Add API key if available
    $headers = [];
    if (!empty($config['dolos_api_key'])) {
        $headers[] = 'Authorization: Bearer ' . $config['dolos_api_key'];
    }
    
    if (!empty($headers)) {
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    }
    
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_TIMEOUT, $config['dolos_timeout']);
    
    // Execute the request
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    
    if (curl_errno($curl) || $http_code >= 400) {
        debugging('Error getting Dolos report data: ' . curl_error($curl) . ' HTTP code: ' . $http_code, DEBUG_DEVELOPER);
        curl_close($curl);
        return false;
    }
    
    curl_close($curl);
    
    // Parse the response
    $result = json_decode($response, true);
    if (!$result) {
        debugging('Invalid JSON response from Dolos API: ' . $response, DEBUG_DEVELOPER);
        return false;
    }
    
    return $result;
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
 * Get Dolos API configuration 
 * 
 * @return array Dolos API configuration
 */
function devcode_get_dolos_config() {
    global $CFG;
    
    // Ensure config is loaded
    if (!isset($CFG->devcode)) {
        require_once(dirname(__FILE__) . '/config.php');
    }
    
    // Get module config for Dolos
    $module_config = get_config('mod_devcode');
    
    // Create default configuration
    $default_config = [
        'dolos_api_url' => 'https://dolos.ugent.be',
        'dolos_api_key' => '',
        'dolos_timeout' => 30,
        'dolos_max_poll_attempts' => 20,
        'dolos_poll_interval' => 5,
        'dolos_threshold' => 0.8,  // 80% similarity threshold
    ];
    
    // Override with config.php settings if available
    if (isset($CFG->devcode['dolos']) && is_array($CFG->devcode['dolos'])) {
        foreach ($CFG->devcode['dolos'] as $key => $value) {
            $default_config[$key] = $value;
        }
    }
    
    // Override with module settings if available
    if (isset($module_config->dolos_api_url) && !empty($module_config->dolos_api_url)) {
        $default_config['dolos_api_url'] = $module_config->dolos_api_url;
    }
    
    if (isset($module_config->dolos_api_key) && !empty($module_config->dolos_api_key)) {
        $default_config['dolos_api_key'] = $module_config->dolos_api_key;
    }
    
    if (isset($module_config->dolos_timeout) && is_numeric($module_config->dolos_timeout)) {
        $default_config['dolos_timeout'] = (int)$module_config->dolos_timeout;
    }
    
    if (isset($module_config->dolos_threshold) && is_numeric($module_config->dolos_threshold)) {
        // Convert percentage to decimal
        $default_config['dolos_threshold'] = (float)$module_config->dolos_threshold / 100;
    }
    
    debugging('Dolos config: ' . json_encode($default_config), DEBUG_DEVELOPER);
    
    return $default_config;
}

/**
 * Process Dolos API report data with proper threshold checking
 * 
 * @param string $report_id The report ID from Dolos
 * @param object $submission The submission record
 * @param object $devcode The assignment record
 * @return bool True if plagiarism is detected above threshold, false otherwise
 */
function devcode_process_dolos_report($report_id, $submission, $devcode) {
    global $DB;
    
    debugging('Processing Dolos report: ' . $report_id, DEBUG_DEVELOPER);
    
    // Get the pairs data from the Dolos report
    $pairs_data = devcode_get_dolos_report_data($report_id, 'pairs');
    if (!$pairs_data || empty($pairs_data)) {
        debugging('No valid pairs data found in report', DEBUG_DEVELOPER);
        return false;
    }
    
    // Get the threshold from assignment settings
    $threshold = isset($devcode->similarity_threshold) ? 
        ($devcode->similarity_threshold / 100) : // Convert percentage to decimal
        0.8; // Default threshold if not defined
    
    debugging('Using similarity threshold: ' . ($threshold * 100) . '%', DEBUG_DEVELOPER);
    
    // Flag to track if any pair exceeds threshold
    $plagiarism_detected = false;
    $max_similarity = 0;
    $matching_pairs = [];
    
    // Find the current submission in the pairs data
    foreach ($pairs_data as $pair) {
        // Calculate similarity as a value between 0 and 1
        $similarity = isset($pair['similarity']) ? floatval($pair['similarity']) : 0;
        $max_similarity = max($max_similarity, $similarity);
        
        // Check if the similarity exceeds the threshold
        if ($similarity >= $threshold) {
            $plagiarism_detected = true;
            $matching_pairs[] = $pair;
        }
    }
    
    // Only mark as plagiarism if at least one pair exceeds the threshold
    if ($plagiarism_detected) {
        debugging('Plagiarism detected with similarity: ' . ($max_similarity * 100) . '%', DEBUG_DEVELOPER);
        
        // Update the submission record
        $submission->status = 'plagiarism_detected';
        $similarity_percent = round($max_similarity * 100, 2);
        $plagiarism_message = get_string('plagiarism_detected', 'devcode', $similarity_percent);
        
        // Get the report URL
        $report = devcode_poll_dolos_report($report_id);
        $html_url = isset($report['html_url']) ? $report['html_url'] : '';
        
        if (!empty($html_url)) {
            $submission->plagiarism_url = $html_url;
            $plagiarism_message .= ' ' . get_string('plagiarism_details', 'devcode', $html_url);
        }
        
        $submission->score = 0;
        $submission->feedback = $plagiarism_message;
        $submission->timemodified = time();
        
        $DB->update_record('devcode_submissions', $submission);
        
        // Create plagiarism records for each pair that exceeds threshold
        foreach ($matching_pairs as $pair) {
            $plagiarism_record = new stdClass();
            $plagiarism_record->submission1_id = $submission->id;
            // Try to find matching submission ID
            $plagiarism_record->submission2_id = 0; // Will be updated if we can match
            $plagiarism_record->similarity_score = isset($pair['similarity']) ? floatval($pair['similarity']) : 0;
            $plagiarism_record->devcodeid = $devcode->id;
            $plagiarism_record->details = json_encode($pair);
            $plagiarism_record->flagged = 1;
            $plagiarism_record->timecreated = time();
            $plagiarism_record->timemodified = time();
            
            $DB->insert_record('devcode_plagiarism', $plagiarism_record);
        }
        
        // Update grades in gradebook
        devcode_update_grades($devcode, $submission->userid);
        
        return true;
    }
    
    debugging('No plagiarism detected above threshold', DEBUG_DEVELOPER);
    return false;
} 