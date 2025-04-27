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
 * Xử lý các bài nộp theo từng đợt (chunk) để tránh overload server
 *
 * @param array $submissions Danh sách bài nộp
 * @param object $devcode Thông tin module devcode
 * @param int $chunk_size Kích thước mỗi đợt (chunk)
 * @return array Kết quả xử lý
 */
function devcode_process_in_chunks($submissions, $devcode, $chunk_size = 10) {
    $total = count($submissions);
    $chunks = ceil($total / $chunk_size);
    $results = array();
    $errors = array();

    for ($i = 0; $i < $chunks; $i++) {
        $start = $i * $chunk_size;
        $chunk = array_slice($submissions, $start, $chunk_size);
        debugging('Processing chunk ' . ($i + 1) . ' of ' . $chunks . ' (' . count($chunk) . ' submissions)', DEBUG_DEVELOPER);

        // Tạo chuỗi submissions để xử lý batch
        $submissions_batch = [];
        foreach ($chunk as $submission) {
            $submission_obj = new stdClass();
            $submission_obj->id = $submission->id;
            $submission_obj->code = $submission->code;
            $submission_obj->language_id = $submission->language_id;

            // Lấy các test cases cho submission này
            $testcases = [];
            $db_testcases = $DB->get_records('devcode_testcases', ['devcodeid' => $devcode->id], 'id ASC');
            foreach ($db_testcases as $tc) {
                $testcases[] = $tc;
            }
            $submission_obj->testcases = $testcases;

            $submissions_batch[] = $submission_obj;
        }

        // Sử dụng batch API nếu có nhiều submission và testcases
        if (count($submissions_batch) > 1 && function_exists('devcode_process_submissions_with_batch')) {
            debugging('Using batch API for ' . count($submissions_batch) . ' submissions', DEBUG_DEVELOPER);
            
            $batch_options = [
                'wait' => true,
                'timeout' => 120,
                'max_attempts' => 15,
                'initial_wait' => 2
            ];
            
            $batch_result = devcode_process_submissions_with_batch($submissions_batch, $batch_options);
            
            if (isset($batch_result['success']) && $batch_result['success']) {
                debugging('Batch processing successful', DEBUG_DEVELOPER);
                
                // Xử lý và lưu kết quả
                if (!empty($batch_result['results'])) {
                    foreach ($batch_result['results'] as $sub_id => $test_results) {
                        // Tìm submission trong chunk
                        foreach ($chunk as $submission) {
                            if ($submission->id == $sub_id) {
                                // Cập nhật submission
                                $submission->status = 'graded';
                                $submission->timemodified = time();
                                
                                // Tính điểm dựa trên kết quả test
                                $total_points = 0;
                                $scored_points = 0;
                                $tests_passed = 0;
                                $tests_total = count($test_results);
                                
                                foreach ($test_results as $tc_id => $tc_result) {
                                    $testcase = $DB->get_record('devcode_testcases', ['id' => $tc_id]);
                                    if ($testcase) {
                                        $total_points += $testcase->points;
                                        
                                        if ($tc_result['status_id'] == 3) { // Accepted
                                            $scored_points += $testcase->points;
                                            $tests_passed++;
                                        }
                                    }
                                }
                                
                                // Tính điểm và cập nhật vào cơ sở dữ liệu
                                $grade = ($total_points > 0) ? ($scored_points / $total_points * $devcode->grade) : 0;
                                $submission->grade = $grade;
                                $submission->tests_passed = $tests_passed;
                                $submission->tests_total = $tests_total;
                                
                                // Cập nhật trạng thái submission
                                if ($tests_passed == $tests_total) {
                                    $submission->status = DEVCODE_STATUS_ACCEPTED;
                                    $submission->message = get_string('allteststpassed', 'devcode');
                                } else if ($tests_passed > 0) {
                                    $submission->status = DEVCODE_STATUS_PARTIALLY_ACCEPTED;
                                    $submission->message = get_string('someteststpassed', 'devcode', 
                                        ['passed' => $tests_passed, 'total' => $tests_total]);
                                } else {
                                    $submission->status = DEVCODE_STATUS_WRONG_ANSWER;
                                    $submission->message = get_string('noteststpassed', 'devcode');
                                }
                                
                                // Lưu kết quả chi tiết từng test case
                                $submission->result_data = json_encode($test_results);
                                
                                // Lưu vào cơ sở dữ liệu
                                $DB->update_record('devcode_submissions', $submission);
                                
                                // Cập nhật điểm số trên gradebook
                                devcode_update_grades($devcode, $submission->userid);
                                
                                $results[] = $submission->id;
                                break;
                            }
                        }
                    }
                }
            } else {
                // Xử lý lỗi batch
                debugging('Batch processing error: ' . ($batch_result['message'] ?? 'Unknown error'), DEBUG_DEVELOPER);
                
                // Sử dụng phương pháp xử lý từng submission nếu batch không thành công
                $chunk_result = devcode_process_batch($chunk, $devcode);
                $results = array_merge($results, $chunk_result['processed']);
                if (!empty($chunk_result['errors'])) {
                    $errors = array_merge($errors, $chunk_result['errors']);
                }
            }
        } else {
            // Sử dụng phương pháp xử lý cũ nếu không đủ điều kiện sử dụng batch
            $chunk_result = devcode_process_batch($chunk, $devcode);
            $results = array_merge($results, $chunk_result['processed']);
            if (!empty($chunk_result['errors'])) {
                $errors = array_merge($errors, $chunk_result['errors']);
            }
        }

        // Delay between chunks to avoid overloading
        if ($i < $chunks - 1) {
            sleep(2);
        }
    }

    return array(
        'processed' => $results,
        'errors' => $errors
    );
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

/**
 * Xử lý nhiều submissions cùng lúc sử dụng Judge0 Batch API
 * 
 * @param array $submissions Mảng chứa thông tin các submissions cần xử lý
 * @param array $options Tuỳ chọn cấu hình
 * @return array Kết quả xử lý
 */
function devcode_process_submissions_with_batch($submissions, $options = []) {
    global $CFG;
    
    if (empty($submissions)) {
        return [
            'error' => true,
            'message' => 'No submissions to process'
        ];
    }
    
    // Kiểm tra xem có thư viện Judge0 API không
    if (!file_exists($CFG->dirroot . '/mod/devcode/judge0_api.php')) {
        return [
            'error' => true,
            'message' => 'Judge0 API library not found'
        ];
    }
    
    require_once($CFG->dirroot . '/mod/devcode/judge0_api.php');
    
    // Lấy cấu hình Judge0
    $judge0_config = devcode_get_judge0_config();
    
    // Chuẩn bị dữ liệu cho batch submissions
    $batch_submissions = [];
    $submission_mapping = []; // Để lưu trữ mapping giữa batch index và submission id
    
    foreach ($submissions as $index => $submission) {
        if (empty($submission->code) || empty($submission->language_id)) {
            continue;
        }
        
        // Lấy các test cases
        $testcases = isset($submission->testcases) ? $submission->testcases : [];
        
        // Nếu không có test cases, bỏ qua submission này
        if (empty($testcases)) {
            continue;
        }
        
        foreach ($testcases as $tc_index => $testcase) {
            $batch_submissions[] = [
                'source_code' => $submission->code,
                'language_id' => $submission->language_id,
                'stdin' => $testcase->input ?? '',
                'expected_output' => $testcase->output ?? '',
                'cpu_time_limit' => isset($testcase->time_limit) ? ($testcase->time_limit / 1000) : 2,
                'memory_limit' => isset($testcase->memory_limit) ? $testcase->memory_limit : 128000
            ];
            
            // Lưu mapping để sau này có thể xác định submission và testcase tương ứng
            $submission_mapping[] = [
                'submission_id' => $submission->id,
                'submission_index' => $index,
                'testcase_id' => $testcase->id,
                'testcase_index' => $tc_index
            ];
        }
    }
    
    if (empty($batch_submissions)) {
        return [
            'error' => true,
            'message' => 'No valid submissions for batch processing'
        ];
    }
    
    // Gửi batch submissions lên Judge0
    $batch_response = devcode_send_batch_to_judge0($batch_submissions, $judge0_config);
    
    if (isset($batch_response['error']) && $batch_response['error']) {
        return $batch_response;
    }
    
    // Lấy tokens
    $tokens = [];
    if (!empty($batch_response['submissions'])) {
        foreach ($batch_response['submissions'] as $submission) {
            if (!empty($submission['token'])) {
                $tokens[] = $submission['token'];
            }
        }
    }
    
    if (empty($tokens)) {
        return [
            'error' => true,
            'message' => 'No tokens received from batch submissions'
        ];
    }
    
    // Đợi và lấy kết quả
    $wait = isset($options['wait']) ? $options['wait'] : false;
    
    if (!$wait) {
        // Nếu không đợi, trả về tokens để xử lý sau
        return [
            'success' => true,
            'tokens' => $tokens,
            'mapping' => $submission_mapping
        ];
    }
    
    // Đợi và lấy kết quả
    $max_attempts = isset($options['max_attempts']) ? $options['max_attempts'] : 10;
    $attempt = 0;
    $wait_time = isset($options['initial_wait']) ? $options['initial_wait'] : 1;
    $timeout = isset($options['timeout']) ? $options['timeout'] : 60;
    $start_time = time();
    
    while ($attempt < $max_attempts && (time() - $start_time) < $timeout) {
        sleep($wait_time);
        
        $results = devcode_get_batch_results($tokens, $judge0_config);
        
        if (isset($results['error']) && $results['error']) {
            $attempt++;
            $wait_time *= 2; // Exponential backoff
            continue;
        }
        
        // Kiểm tra xem tất cả các submissions đã hoàn thành chưa
        $all_complete = true;
        if (!empty($results['submissions'])) {
            foreach ($results['submissions'] as $result) {
                // Nếu status id = 1 hoặc 2, thì vẫn đang xử lý
                if (isset($result['status']['id']) && in_array($result['status']['id'], [1, 2])) {
                    $all_complete = false;
                    break;
                }
            }
        }
        
        if ($all_complete) {
            // Xử lý kết quả và trả về
            $processed_results = [];
            
            // Tạo mảng kết quả cho mỗi submission
            foreach ($submissions as $index => $submission) {
                $submission_results = [];
                
                // Tìm tất cả các kết quả testcase cho submission này
                foreach ($submission_mapping as $map_index => $mapping) {
                    if ($mapping['submission_index'] === $index && isset($results['submissions'][$map_index])) {
                        $result = $results['submissions'][$map_index];
                        $testcase_id = $mapping['testcase_id'];
                        
                        $submission_results[$testcase_id] = devcode_map_judge0_status($result);
                    }
                }
                
                $processed_results[$submission->id] = $submission_results;
            }
            
            return [
                'success' => true,
                'results' => $processed_results,
                'raw_results' => $results['submissions'],
                'mapping' => $submission_mapping
            ];
        }
        
        $attempt++;
        $wait_time *= 2; // Tăng thời gian chờ theo luỹ thừa
    }
    
    return [
        'error' => true,
        'message' => 'Timeout while waiting for batch results',
        'tokens' => $tokens,
        'mapping' => $submission_mapping
    ];
} 
 