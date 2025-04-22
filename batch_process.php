<?php
/*
 * Batch Processing Utilities for DevCode
 *
 * @package    mod_devcode
 * @copyright  2024 Your Name <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/judge0_api.php');

// Error status codes
define('DEVCODE_ERROR_STATUS_INTERNAL_ERROR', 'internal_error');
define('DEVCODE_ERROR_STATUS_CONNECTION_ERROR', 'connection_error');
define('DEVCODE_ERROR_STATUS_API_ERROR', 'api_error');

/**
 * Process a batch of submissions to Judge0 API
 *
 * @param array $submissions Array of submission data
 * @param array $config Judge0 API configuration (optional, will use module config if not provided)
 * @param bool $wait Whether to wait for results (default: false)
 * @param int $timeout Maximum time to wait in seconds (default: 60)
 * @return array The processing results
 */
function devcode_process_batch_api($submissions, $config = null, $wait = false, $timeout = 60) {
    // Check required parameters
    if (empty($submissions) || !is_array($submissions)) {
        return [
            'error' => true,
            'error_status' => DEVCODE_ERROR_STATUS_INTERNAL_ERROR,
            'error_message' => 'Invalid submissions parameter'
        ];
    }

    // If config not provided, get default config
    if ($config === null) {
        $config = devcode_get_judge0_config();
    }

    // Prepare the results array
    $results = [
        'error' => false,
        'processed' => 0,
        'successful' => 0,
        'failed' => 0,
        'submissions' => []
    ];

    // Process each submission
    foreach ($submissions as $index => $submission) {
        $results['processed']++;
        
        // Validate the submission
        if (empty($submission['source_code']) || empty($submission['language_id'])) {
            $results['failed']++;
            $results['submissions'][$index] = [
                'error' => true,
                'error_status' => DEVCODE_ERROR_STATUS_INTERNAL_ERROR,
                'error_message' => 'Missing required fields: source_code and language_id'
            ];
            continue;
        }
        
        // Send the submission to Judge0
        $response = devcode_send_to_api($submission, $config);
        
        // Check if submission was successful
        if (isset($response['error']) && $response['error']) {
            $results['failed']++;
            $results['submissions'][$index] = $response;
            continue;
        }
        
        // If wait is enabled, poll for results
        if ($wait && isset($response['token'])) {
            $poll_result = devcode_poll_submission($response['token'], $config, $timeout);
            $results['submissions'][$index] = $poll_result;
            
            if (isset($poll_result['error']) && $poll_result['error']) {
                $results['failed']++;
            } else {
                $results['successful']++;
            }
        } else {
            // Just return the token
            $results['successful']++;
            $results['submissions'][$index] = $response;
        }
    }
    
    // Update error flag if any submissions failed
    if ($results['failed'] > 0) {
        $results['error'] = true;
        $results['error_message'] = "{$results['failed']} out of {$results['processed']} submissions failed";
    }
    
    return $results;
}

/**
 * Process submissions for a DevCode activity
 *
 * @param object $devcode The DevCode instance
 * @param array $submissions Array of submission data
 * @param bool $wait Whether to wait for results (default: false)
 * @return array The processing results
 */
function devcode_process_activity_submissions($devcode, $submissions, $wait = false) {
    global $CFG, $DB;
    
    // Get Judge0 config from settings
    $config = devcode_get_judge0_config();
    
    // If there are activity-specific settings, override the defaults
    if (!empty($devcode->judge0_api_url)) {
        $config['judge0_api_url'] = $devcode->judge0_api_url;
    }
    
    if (!empty($devcode->judge0_api_key)) {
        $config['judge0_api_key'] = $devcode->judge0_api_key;
    }
    
    if (!empty($devcode->judge0_timeout)) {
        $config['judge0_timeout'] = $devcode->judge0_timeout;
    }
    
    // Process the batch submission
    $results = devcode_process_batch_api($submissions, $config, $wait, $config['judge0_timeout']);
    
    // Store results in database if needed
    if (isset($devcode->id) && $devcode->id > 0) {
        foreach ($results['submissions'] as $index => $result) {
            if (isset($submissions[$index]['user_id']) && isset($submissions[$index]['attempt'])) {
                // Record the submission in the database
                $submission_record = new stdClass();
                $submission_record->devcodeid = $devcode->id;
                $submission_record->userid = $submissions[$index]['user_id'];
                $submission_record->attempt = $submissions[$index]['attempt'];
                $submission_record->sourcecode = $submissions[$index]['source_code'];
                $submission_record->language = $submissions[$index]['language_id'];
                $submission_record->status = isset($result['error']) && $result['error'] ? 'error' : 'submitted';
                $submission_record->token = isset($result['token']) ? $result['token'] : null;
                $submission_record->timecreated = time();
                $submission_record->timemodified = time();
                
                // Add other submission data
                if (isset($submissions[$index]['stdin'])) {
                    $submission_record->stdin = $submissions[$index]['stdin'];
                }
                
                if (isset($submissions[$index]['expected_output'])) {
                    $submission_record->expectedoutput = $submissions[$index]['expected_output'];
                }
                
                // Store error if any
                if (isset($result['error']) && $result['error']) {
                    $submission_record->error = $result['error_message'];
                }
                
                // Add result information if available
                if (isset($result['result'])) {
                    $submission_record->stdout = isset($result['result']['stdout']) ? $result['result']['stdout'] : null;
                    $submission_record->stderr = isset($result['result']['stderr']) ? $result['result']['stderr'] : null;
                    $submission_record->compile_output = isset($result['result']['compile_output']) ? $result['result']['compile_output'] : null;
                    $submission_record->time = isset($result['result']['time']) ? $result['result']['time'] : null;
                    $submission_record->memory = isset($result['result']['memory']) ? $result['result']['memory'] : null;
                    
                    if (isset($result['result']['status']) && isset($result['result']['status']['id'])) {
                        $submission_record->status_id = $result['result']['status']['id'];
                        $submission_record->status = $result['result']['status']['description'];
                    }
                }
                
                // Insert or update the record
                if ($existing = $DB->get_record('devcode_submissions', ['devcodeid' => $devcode->id, 
                                                                       'userid' => $submission_record->userid, 
                                                                       'attempt' => $submission_record->attempt])) {
                    $submission_record->id = $existing->id;
                    $DB->update_record('devcode_submissions', $submission_record);
                } else {
                    $DB->insert_record('devcode_submissions', $submission_record);
                }
            }
        }
    }
    
    return $results;
}

/**
 * Queue a batch of submissions for asynchronous processing
 *
 * @param array $submissions Array of submission data
 * @param object $devcode The DevCode instance
 * @return array The queue results
 */
function devcode_queue_batch_submission($submissions, $devcode) {
    global $DB;
    
    $queue_results = [
        'error' => false,
        'queued' => 0,
        'skipped' => 0,
        'records' => []
    ];
    
    foreach ($submissions as $submission) {
        // Skip if missing required fields
        if (empty($submission['source_code']) || empty($submission['language_id']) || 
            empty($submission['user_id']) || !isset($submission['attempt'])) {
            $queue_results['skipped']++;
            continue;
        }
        
        // Create a queue record
        $queue_record = new stdClass();
        $queue_record->devcodeid = $devcode->id;
        $queue_record->userid = $submission['user_id'];
        $queue_record->attempt = $submission['attempt'];
        $queue_record->sourcecode = $submission['source_code'];
        $queue_record->language = $submission['language_id'];
        $queue_record->stdin = isset($submission['stdin']) ? $submission['stdin'] : '';
        $queue_record->expectedoutput = isset($submission['expected_output']) ? $submission['expected_output'] : '';
        $queue_record->status = 'queued';
        $queue_record->timecreated = time();
        $queue_record->timemodified = time();
        
        // Add to queue table
        $record_id = $DB->insert_record('devcode_submission_queue', $queue_record);
        
        if ($record_id) {
            $queue_results['queued']++;
            $queue_results['records'][] = $record_id;
        } else {
            $queue_results['skipped']++;
        }
    }
    
    // Schedule processing task if items were queued
    if ($queue_results['queued'] > 0) {
        // Create an adhoc task to process the queue
        $task = new \mod_devcode\task\process_batch_task();
        $task->set_custom_data(['devcodeid' => $devcode->id]);
        \core\task\manager::queue_adhoc_task($task);
    }
    
    return $queue_results;
}

/**
 * Process the submission queue
 *
 * @param int $limit Maximum number of items to process (default: 50)
 * @param int $devcodeid Optional DevCode ID to filter queue by
 * @return array Processing results
 */
function devcode_process_submission_queue($limit = 50, $devcodeid = null) {
    global $DB;
    
    $results = [
        'processed' => 0,
        'successful' => 0,
        'failed' => 0,
        'remaining' => 0
    ];
    
    // Build the query conditions
    $conditions = ['status' => 'queued'];
    $params = ['status' => 'queued'];
    
    if ($devcodeid) {
        $conditions['devcodeid'] = $devcodeid;
        $params['devcodeid'] = $devcodeid;
    }
    
    // Get records to process
    $queue_items = $DB->get_records('devcode_submission_queue', $params, 'timecreated ASC', '*', 0, $limit);
    
    // Group submissions by DevCode instance
    $grouped_submissions = [];
    foreach ($queue_items as $item) {
        if (!isset($grouped_submissions[$item->devcodeid])) {
            $grouped_submissions[$item->devcodeid] = [
                'devcode' => $DB->get_record('devcode', ['id' => $item->devcodeid]),
                'submissions' => []
            ];
        }
        
        $grouped_submissions[$item->devcodeid]['submissions'][] = [
            'queue_id' => $item->id,
            'source_code' => $item->sourcecode,
            'language_id' => $item->language,
            'stdin' => $item->stdin,
            'expected_output' => $item->expectedoutput,
            'user_id' => $item->userid,
            'attempt' => $item->attempt
        ];
    }
    
    // Process each group
    foreach ($grouped_submissions as $devcodeid => $group) {
        if (empty($group['devcode'])) {
            // Skip if DevCode instance doesn't exist
            foreach ($group['submissions'] as $submission) {
                $DB->set_field('devcode_submission_queue', 'status', 'error', ['id' => $submission['queue_id']]);
                $DB->set_field('devcode_submission_queue', 'error', 'DevCode instance not found', ['id' => $submission['queue_id']]);
                $results['processed']++;
                $results['failed']++;
            }
            continue;
        }
        
        // Process this batch
        $batch_results = devcode_process_activity_submissions($group['devcode'], $group['submissions'], true);
        
        // Update queue records
        foreach ($batch_results['submissions'] as $index => $result) {
            $queue_id = $group['submissions'][$index]['queue_id'];
            
            if (isset($result['error']) && $result['error']) {
                $DB->set_field('devcode_submission_queue', 'status', 'error', ['id' => $queue_id]);
                $DB->set_field('devcode_submission_queue', 'error', $result['error_message'], ['id' => $queue_id]);
                $results['failed']++;
            } else {
                $DB->set_field('devcode_submission_queue', 'status', 'processed', ['id' => $queue_id]);
                $results['successful']++;
            }
            
            $DB->set_field('devcode_submission_queue', 'timemodified', time(), ['id' => $queue_id]);
            $results['processed']++;
        }
    }
    
    // Count remaining queue items
    $results['remaining'] = $DB->count_records('devcode_submission_queue', ['status' => 'queued']);
    
    return $results;
}

/**
 * Process a batch of submissions efficiently
 *
 * @param array $submissions Array of submission data
 * @param object $devcode DevCode instance
 * @return array Processing results
 */
function devcode_process_batch($submissions, $devcode) {
    if (empty($submissions)) {
        return [
            'status' => 'error',
            'message' => 'No submissions to process',
            'processed' => 0
        ];
    }
    
    // Get configuration
    $config = devcode_get_judge0_config();
    
    // Prepare submissions for batch processing
    $batch_submissions = [];
    foreach ($submissions as $submission) {
        $batch_submissions[] = [
            'source_code' => $submission->sourcecode,
            'language_id' => $submission->languageid,
            'stdin' => $devcode->input_data,
            'expected_output' => $devcode->expected_output,
            'submission_id' => $submission->id,
            'user_id' => $submission->userid
        ];
    }
    
    // Process batch submission
    $result = devcode_process_batch_api($batch_submissions, $config);
    
    // Process results
    if (isset($result['error'])) {
        return [
            'status' => 'error',
            'message' => $result['message'] ?? 'Batch processing failed',
            'processed' => 0
        ];
    }
    
    $processed = 0;
    $errors = 0;
    
    if (isset($result['submissions']) && is_array($result['submissions'])) {
        foreach ($result['submissions'] as $submission_result) {
            if (isset($submission_result['token']) && !empty($submission_result['token'])) {
                // Poll for individual result
                $poll_result = devcode_poll_submission($submission_result['token'], $config);
                
                if (!isset($poll_result['error']) && 
                    isset($submission_result['submission_id']) && 
                    isset($submission_result['user_id'])) {
                    
                    // Grade the submission
                    if (devcode_grade_submission($devcode, $submission_result['user_id'], $poll_result)) {
                        $processed++;
                    } else {
                        $errors++;
                    }
                } else {
                    $errors++;
                }
            } else {
                $errors++;
            }
        }
    }
    
    return [
        'status' => 'success',
        'message' => "Processed $processed submissions with $errors errors",
        'processed' => $processed,
        'errors' => $errors
    ];
}

/**
 * Process multiple submissions in chunks to avoid timeout
 *
 * @param array $submissions Array of all submissions
 * @param object $devcode DevCode instance
 * @param int $chunk_size Size of each processing chunk
 * @return array Processing results
 */
function devcode_process_in_chunks($submissions, $devcode, $chunk_size = 10) {
    $total = count($submissions);
    $processed = 0;
    $errors = 0;
    $chunks = array_chunk($submissions, $chunk_size);
    
    foreach ($chunks as $chunk) {
        $result = devcode_process_batch($chunk, $devcode);
        
        if ($result['status'] === 'success') {
            $processed += $result['processed'];
            $errors += $result['errors'] ?? 0;
        } else {
            $errors += count($chunk);
        }
    }
    
    return [
        'status' => 'success',
        'message' => "Processed $processed of $total submissions with $errors errors",
        'processed' => $processed,
        'total' => $total,
        'errors' => $errors
    ];
}

/**
 * Schedule batch processing as a background task
 *
 * @param int $devcodeid DevCode instance ID
 * @param array $userids Optional array of user IDs to process
 * @return bool Success status
 */
function devcode_schedule_batch_processing($devcodeid, $userids = []) {
    global $CFG, $DB;
    
    // Create a task using the mod_devcode\task\process_batch_task class
    $task = new \mod_devcode\task\process_batch_task();
    
    // Set the task data
    $task->set_custom_data([
        'devcodeid' => $devcodeid,
        'userids' => $userids
    ]);
    
    // Queue the task
    return \core\task\manager::queue_adhoc_task($task);
}

/**
 * Export submissions for offline processing
 *
 * @param object $devcode DevCode instance
 * @param array $submissions Array of submissions
 * @return string JSON data for export
 */
function devcode_export_submissions_for_processing($devcode, $submissions) {
    $export_data = [
        'devcode' => [
            'id' => $devcode->id,
            'name' => $devcode->name,
            'input_data' => $devcode->input_data,
            'expected_output' => $devcode->expected_output,
            'grade' => $devcode->grade
        ],
        'submissions' => []
    ];
    
    foreach ($submissions as $submission) {
        $export_data['submissions'][] = [
            'id' => $submission->id,
            'userid' => $submission->userid,
            'sourcecode' => $submission->sourcecode,
            'languageid' => $submission->languageid,
            'timecreated' => $submission->timecreated
        ];
    }
    
    return json_encode($export_data);
}

/**
 * Import processed submissions results
 *
 * @param string $json_data JSON data with processing results
 * @return array Import statistics
 */
function devcode_import_processed_results($json_data) {
    global $DB;
    
    $data = json_decode($json_data, true);
    if (!$data || !isset($data['devcode']) || !isset($data['results'])) {
        return [
            'status' => 'error',
            'message' => 'Invalid import data format'
        ];
    }
    
    $devcode = $DB->get_record('devcode', ['id' => $data['devcode']['id']]);
    if (!$devcode) {
        return [
            'status' => 'error',
            'message' => 'DevCode instance not found'
        ];
    }
    
    $imported = 0;
    $errors = 0;
    
    foreach ($data['results'] as $result) {
        if (!isset($result['submission_id']) || !isset($result['userid']) || !isset($result['result'])) {
            $errors++;
            continue;
        }
        
        $submission = $DB->get_record('devcode_submissions', [
            'id' => $result['submission_id'],
            'devcodeid' => $devcode->id,
            'userid' => $result['userid']
        ]);
        
        if (!$submission) {
            $errors++;
            continue;
        }
        
        if (devcode_grade_submission($devcode, $result['userid'], $result['result'])) {
            $imported++;
        } else {
            $errors++;
        }
    }
    
    return [
        'status' => 'success',
        'message' => "Imported $imported results with $errors errors",
        'imported' => $imported,
        'errors' => $errors
    ];
} 
 