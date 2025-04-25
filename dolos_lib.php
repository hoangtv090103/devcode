<?php
/**
 * Dolos API library for plagiarism detection
 * @package    mod_devcode
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Get report data from the Dolos API
 * 
 * @param string $report_id Report ID
 * @param string $data_type Type of data (pairs, files, etc.)
 * @return array Data from the report
 */
function dolos_get_report_data($report_id, $data_type) {
    $config = devcode_get_dolos_config();
    $file = $data_type . '.csv'; // Dolos API expects .csv extension
    $endpoint = rtrim($config['dolos_api_url'], '/') . "/reports/$report_id/data/$file";
    
    debugging("Requesting Dolos data from: $endpoint", DEBUG_DEVELOPER);
    
    $curl = curl_init($endpoint);
    $headers = [];
    if (!empty($config['dolos_api_key'])) {
        $headers[] = 'Authorization: Bearer ' . $config['dolos_api_key'];
    }
    if ($headers) {
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    }
    
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_TIMEOUT, $config['dolos_timeout']);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true); // Follow redirects
    
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($curl);
    
    curl_close($curl);
    
    if (curl_errno($curl) || $http_code >= 400) {
        debugging("Dolos report data error: $curl_error HTTP $http_code", DEBUG_DEVELOPER);
        return false;
    }
    
    if (empty($response)) {
        debugging('Empty response from Dolos API', DEBUG_DEVELOPER);
        return false;
    }
    
    // Parse CSV data
    return dolos_parse_csv($response);
}

/**
 * Parse CSV data from Dolos API
 *
 * @param string $csv_data CSV data as string
 * @return array Array of records
 */
function dolos_parse_csv($csv_data) {
    $lines = explode("\n", $csv_data);
    
    if (count($lines) < 2) {
        debugging("CSV data has insufficient lines", DEBUG_DEVELOPER);
        return [];
    }
    
    // Get headers from first line
    $header_line = array_shift($lines);
    $headers = str_getcsv($header_line);
    
    $result = [];
    foreach ($lines as $i => $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        $values = str_getcsv($line);
        
        // Skip lines that don't match header count
        if (count($values) !== count($headers)) {
            debugging("CSV line $i has incorrect field count", DEBUG_DEVELOPER);
            continue;
        }
        
        // Create record with named fields
        $record = [];
        foreach ($headers as $j => $header) {
            $record[$header] = $values[$j];
        }
        
        $result[] = $record;
    }
    
    debugging("Parsed " . count($result) . " records from CSV", DEBUG_DEVELOPER);
    return $result;
}

/**
 * Submit a ZIP file to Dolos API
 *
 * @param string $zip_content ZIP file contents
 * @param string $name Name for the analysis
 * @param string $language Programming language (optional)
 * @return array|false API response on success, false on failure
 */
function dolos_submit_zip($zip_content, $name, $language = '') {
    $config = devcode_get_dolos_config();
    $endpoint = rtrim($config['dolos_api_url'], '/') . '/reports';
    
    debugging("Dolos API endpoint: $endpoint", DEBUG_DEVELOPER);
    
    // Create temporary ZIP file
    $temp_zip = tempnam(sys_get_temp_dir(), 'dolos_zip_') . '.zip';
    file_put_contents($temp_zip, $zip_content);
    
    // Prepare multipart form data
    $post = ['dataset[name]' => $name];
    if ($language) {
        $post['dataset[programming_language]'] = $language;
    }
    $post['dataset[zipfile]'] = curl_file_create(
        $temp_zip, 
        'application/zip', 
        'submissions.zip'
    );
    
    // Set up curl request
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $endpoint,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $post,
        CURLOPT_TIMEOUT => $config['dolos_timeout'],
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true
    ]);
    
    // Add authentication if needed
    if (!empty($config['dolos_api_key'])) {
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $config['dolos_api_key']]);
    }
    
    // Execute request
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($curl);
    
    debugging("Dolos API response code: $http_code", DEBUG_DEVELOPER);
    if ($curl_error) {
        debugging("Curl error: $curl_error", DEBUG_DEVELOPER);
    }
    
    // Clean up
    curl_close($curl);
    unlink($temp_zip);
    
    // Handle errors
    if (curl_errno($curl) || $http_code >= 400) {
        debugging("Dolos API submission failed: $curl_error (HTTP $http_code)", DEBUG_DEVELOPER);
        return false;
    }
    
    // Parse JSON response
    $result = json_decode($response, true);
    if (!$result) {
        debugging("Invalid JSON from Dolos API: $response", DEBUG_DEVELOPER);
        return false;
    }
    
    debugging('Dolos API submission successful: ' . json_encode($result), DEBUG_DEVELOPER);
    return $result;
}

/**
 * Poll Dolos API until report is complete
 *
 * @param string $report_id Report ID
 * @return array|false Report data on success, false on failure
 */
function dolos_poll_report($report_id) {
    $config = devcode_get_dolos_config();
    $endpoint = rtrim($config['dolos_api_url'], '/') . "/reports/$report_id";
    
    // Configure curl
    $curl = curl_init($endpoint);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $config['dolos_timeout'],
        CURLOPT_FOLLOWLOCATION => true
    ]);
    
    // Add authentication if needed
    if (!empty($config['dolos_api_key'])) {
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $config['dolos_api_key']]);
    }
    
    // Poll until report is complete or max attempts reached
    $attempts = 0;
    while ($attempts++ < $config['dolos_max_poll_attempts']) {
        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        
        if (curl_errno($curl) || $http_code >= 400) {
            debugging('Dolos poll error: ' . curl_error($curl) . " (HTTP $http_code)", DEBUG_DEVELOPER);
            sleep($config['dolos_poll_interval']);
            continue;
        }
        
        $result = json_decode($response, true);
        if (!$result) {
            debugging('Invalid JSON from Dolos poll: ' . $response, DEBUG_DEVELOPER);
            sleep($config['dolos_poll_interval']);
            continue;
        }
        
        // Check if the report status is settled
        if (!empty($result['status'])) {
            debugging("Dolos report status: {$result['status']}", DEBUG_DEVELOPER);
            
            if (in_array($result['status'], ['finished', 'processed'])) {
                curl_close($curl);
                return $result;
            }
            
            if ($result['status'] === 'failed') {
                debugging('Dolos report failed: ' . json_encode($result), DEBUG_DEVELOPER);
                curl_close($curl);
                return false;
            }
        }
        
        // Wait before polling again
        sleep($config['dolos_poll_interval']);
    }
    
    curl_close($curl);
    debugging('Max Dolos poll attempts reached', DEBUG_DEVELOPER);
    return false;
} 