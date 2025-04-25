<?php
/*
 * Judge0 API Interface Functions
 * @package    mod_devcode
 */

defined('MOODLE_INTERNAL') || die();

define('DEVCODE_JUDGE0_ERROR_NONE', 0);
define('DEVCODE_JUDGE0_ERROR_CONNECTION', 1);
define('DEVCODE_JUDGE0_ERROR_HTTP', 2);
define('DEVCODE_JUDGE0_ERROR_RESPONSE', 3);
define('DEVCODE_JUDGE0_ERROR_TIMEOUT', 4);
define('DEVCODE_JUDGE0_ERROR_INVALID_TOKEN', 5);
define('DEVCODE_JUDGE0_ERROR_MISSING_PARAM', 6);

function devcode_get_judge0_config() {
    global $CFG;
    
    // Lấy cài đặt từ config, sau đó từ setting, rồi fallback to default
    return [
        'judge0_api_url' => $CFG->devcode['judge0']['api_url'] ?? 'https://judge0-ce.p.rapidapi.com',
        'judge0_api_key' => $CFG->devcode['judge0']['api_key'] ?? '',
        'judge0_timeout' => $CFG->devcode['judge0']['timeout'] ?? 45,
        'judge0_max_wait' => $CFG->devcode['judge0']['max_wait'] ?? 60,
        'judge0_poll_interval' => $CFG->devcode['judge0']['poll_interval'] ?? 3,
        'judge0_headers' => is_callable($CFG->devcode['judge0']['headers'] ?? null) ? 
                        call_user_func($CFG->devcode['judge0']['headers']) : 
                        ['Content-Type: application/json', 'Accept: application/json']
    ];
}

function devcode_send_to_api($data, $config = null) {
    if (!$config) $config = devcode_get_judge0_config();
    if (empty($data['source_code']) || empty($data['language_id'])) {
        return ['error'=>DEVCODE_JUDGE0_ERROR_MISSING_PARAM,'message'=>'Missing required parameters: source_code or language_id'];
    }
    $url = rtrim($config['judge0_api_url'], '/') . '/submissions?base64_encoded=false&wait=false';
    
    // Use headers from config
    $headers = [];
    if (isset($config['judge0_headers']) && is_array($config['judge0_headers'])) {
        foreach ($config['judge0_headers'] as $key => $value) {
            $headers[] = "$key: $value";
        }
    } else {
        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        if (!empty($config['judge0_api_key'])) {
            $headers[] = 'X-RapidAPI-Key: ' . $config['judge0_api_key'];
            $headers[] = 'X-RapidAPI-Host: ' . parse_url($config['judge0_api_url'], PHP_URL_HOST);
            $masked = substr($config['judge0_api_key'],0,4).'...'.substr($config['judge0_api_key'],-4);
            debugging('Using Judge0 API key: '.$masked, DEBUG_DEVELOPER);
        } else {
            debugging('Warning: No Judge0 API key provided', DEBUG_DEVELOPER);
        }
    }
    
    $submission_data = [
        'source_code' => $data['source_code'],
        'language_id' => $data['language_id'],
    ];
    if (!empty($data['stdin'])) $submission_data['stdin'] = $data['stdin'];
    if (!empty($data['expected_output'])) $submission_data['expected_output'] = $data['expected_output'];
    $max_retries = 3; $retry_delay = 2; $retries = 0;
    do {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($submission_data),
            CURLOPT_TIMEOUT => $config['judge0_timeout']
        ]);
        debugging('Sending request to Judge0 API: '.$url, DEBUG_DEVELOPER);
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            $error = curl_error($ch); curl_close($ch);
            if (++$retries <= $max_retries) { debugging("Connection error, retrying ($retries/$max_retries): $error", DEBUG_DEVELOPER); sleep($retry_delay); continue; }
            return ['error'=>DEVCODE_JUDGE0_ERROR_CONNECTION,'message'=>"Connection error after $max_retries attempts: $error"];
        }
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        debugging('Judge0 API response code: '.$http_code, DEBUG_DEVELOPER);
        if ($http_code < 200 || $http_code >= 300) {
            if ($http_code == 401) {
                debugging('Authentication failed: API key is invalid.', DEBUG_NORMAL);
                global $CFG;
                if (!empty($CFG->devcode['api_mock_enabled'])) {
                    debugging('Using mock response due to authentication failure', DEBUG_DEVELOPER);
                    return ['token'=>'mock_'.uniqid(),'mock'=>true];
                }
                return ['error'=>DEVCODE_JUDGE0_ERROR_HTTP,'message'=>'Authentication failed: API key is missing or invalid (HTTP 401)','response'=>$response];
            }
            if ($http_code == 429 && ++$retries <= $max_retries) {
                $wait = $retry_delay * (2 * $retries);
                debugging("Rate limit exceeded, retrying ($retries/$max_retries) after $wait seconds", DEBUG_DEVELOPER);
                sleep($wait); continue;
            }
            if (++$retries <= $max_retries) { debugging("HTTP error: $http_code, retrying ($retries/$max_retries)", DEBUG_DEVELOPER); sleep($retry_delay); continue; }
            return ['error'=>DEVCODE_JUDGE0_ERROR_HTTP,'message'=>"HTTP error: $http_code after $max_retries attempts",'response'=>$response];
        }
        $result = json_decode($response, true);
        if ($result === null) {
            if (++$retries <= $max_retries) { debugging('JSON parse error, retrying ('.$retries.'/'.$max_retries.'): '.json_last_error_msg(), DEBUG_DEVELOPER); sleep($retry_delay); continue; }
            return ['error'=>DEVCODE_JUDGE0_ERROR_RESPONSE,'message'=>'Failed to parse response: '.json_last_error_msg(),'response'=>$response];
        }
        if (!isset($result['token'])) {
            if (++$retries <= $max_retries) { debugging('No token in response, retrying ('.$retries.'/'.$max_retries.')', DEBUG_DEVELOPER); sleep($retry_delay); continue; }
            return ['error'=>DEVCODE_JUDGE0_ERROR_INVALID_TOKEN,'message'=>'No token in response after '.$max_retries.' attempts','response'=>$result];
        }
        debugging('Successfully received token from Judge0 API', DEBUG_DEVELOPER);
        return $result;
    } while ($retries <= $max_retries);
    return ['error'=>DEVCODE_JUDGE0_ERROR_RESPONSE,'message'=>'Unknown error after retries'];
}

function devcode_poll_submission($token, $config = null) {
    if (!$config) $config = devcode_get_judge0_config();
    if (empty($token)) return ['error'=>DEVCODE_JUDGE0_ERROR_MISSING_PARAM,'message'=>'Missing required parameter: token'];
    if (strpos($token, 'mock_') === 0) {
        debugging('Using mock response for polling with token: '.$token, DEBUG_DEVELOPER);
        return [
            'token'=>$token,'mock'=>true,
            'result'=>['status'=>['id'=>3,'description'=>'Accepted (Mock)'],'stdout'=>'Mock output for testing','time'=>0.5,'memory'=>10240]
        ];
    }
    $url = rtrim($config['judge0_api_url'], '/') . '/submissions/' . $token . '?base64_encoded=false';
    
    // Use headers from config
    $headers = [];
    if (isset($config['judge0_headers']) && is_array($config['judge0_headers'])) {
        foreach ($config['judge0_headers'] as $key => $value) {
            $headers[] = "$key: $value";
        }
    } else {
        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        if (!empty($config['judge0_api_key'])) {
            $headers[] = 'X-RapidAPI-Key: ' . $config['judge0_api_key'];
            $headers[] = 'X-RapidAPI-Host: ' . parse_url($config['judge0_api_url'], PHP_URL_HOST);
        }
    }
    
    $start = time();
    $max_wait = $config['judge0_max_wait'];
    $poll_interval = $config['judge0_poll_interval'];
    $max_retries = 3; $retry_delay = 2;
    while (true) {
        $retries = 0; $poll_result = null;
        do {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $config['judge0_timeout']
            ]);
            $response = curl_exec($ch);
            if (curl_errno($ch)) {
                $error = curl_error($ch); curl_close($ch);
                if (++$retries <= $max_retries) { debugging("Connection error during polling, retrying ($retries/$max_retries): $error", DEBUG_DEVELOPER); sleep($retry_delay); continue; }
                $poll_result = ['error'=>DEVCODE_JUDGE0_ERROR_CONNECTION,'message'=>"Connection error during polling after $max_retries attempts: $error"]; break;
            }
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($http_code < 200 || $http_code >= 300) {
                if ($http_code == 401) {
                    $poll_result = ['error'=>DEVCODE_JUDGE0_ERROR_HTTP,'message'=>'Authentication failed during polling: API key is missing or invalid (HTTP 401)','response'=>$response]; break;
                }
                if ($http_code == 429 && ++$retries <= $max_retries) {
                    $wait = $retry_delay * (2 * $retries);
                    debugging("Rate limit exceeded during polling, retrying ($retries/$max_retries) after $wait seconds", DEBUG_DEVELOPER);
                    sleep($wait); continue;
                }
                if (++$retries <= $max_retries) { debugging("HTTP error during polling: $http_code, retrying ($retries/$max_retries)", DEBUG_DEVELOPER); sleep($retry_delay); continue; }
                $poll_result = ['error'=>DEVCODE_JUDGE0_ERROR_HTTP,'message'=>"HTTP error during polling: $http_code after $max_retries attempts",'response'=>$response]; break;
            }
            $result = json_decode($response, true);
            if ($result === null) {
                if (++$retries <= $max_retries) { debugging('JSON parse error during polling, retrying ('.$retries.'/'.$max_retries.'): '.json_last_error_msg(), DEBUG_DEVELOPER); sleep($retry_delay); continue; }
                $poll_result = ['error'=>DEVCODE_JUDGE0_ERROR_RESPONSE,'message'=>'Failed to parse response during polling: '.json_last_error_msg(),'response'=>$response]; break;
            }
            $poll_result = $result; break;
        } while ($retries <= $max_retries);
        if (isset($poll_result['error'])) return $poll_result;
        if (isset($poll_result['status']['id']) && $poll_result['status']['id'] >= 3) {
            return ['token'=>$token,'result'=>$poll_result];
        }
        if (time() - $start > $max_wait) {
            return ['error'=>DEVCODE_JUDGE0_ERROR_TIMEOUT,'message'=>"Exceeded maximum wait time of $max_wait seconds",'token'=>$token];
        }
        sleep($poll_interval);
    }
}

function devcode_process_batch_submission($submissions, $config = null) {
    if (empty($submissions) || !is_array($submissions)) {
        return ['error'=>DEVCODE_JUDGE0_ERROR_MISSING_PARAM,'message'=>'Missing or invalid submissions array'];
    }
    if (!$config) $config = devcode_get_judge0_config();
    $results = ['submissions'=>count($submissions),'processed'=>0,'errors'=>0,'results'=>[]];
    foreach ($submissions as $i=>$s) {
        if (empty($s['source_code']) || empty($s['language_id'])) {
            $results['errors']++; $results['results'][$i] = ['error'=>DEVCODE_JUDGE0_ERROR_MISSING_PARAM,'message'=>'Missing required parameters: source_code or language_id']; continue;
        }
        $api_result = devcode_send_to_api($s, $config);
        if (!isset($api_result['error']) && isset($api_result['token'])) {
            $poll_result = devcode_poll_submission($api_result['token'], $config);
            if (!isset($poll_result['error'])) {
                $results['processed']++; $results['results'][$i] = $poll_result;
            } else {
                $results['errors']++; $results['results'][$i] = $poll_result;
            }
        } else {
            $results['errors']++; $results['results'][$i] = $api_result;
        }
    }
    return $results;
}

function devcode_get_languages($config = null) {
    if (!$config) $config = devcode_get_judge0_config();
    $url = rtrim($config['judge0_api_url'], '/') . '/languages';
    
    // Use headers from config
    $headers = [];
    if (isset($config['judge0_headers']) && is_array($config['judge0_headers'])) {
        foreach ($config['judge0_headers'] as $key => $value) {
            $headers[] = "$key: $value";
        }
    } else {
        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        if (!empty($config['judge0_api_key'])) {
            $headers[] = 'X-RapidAPI-Key: ' . $config['judge0_api_key'];
            $headers[] = 'X-RapidAPI-Host: ' . parse_url($config['judge0_api_url'], PHP_URL_HOST);
        }
    }
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $config['judge0_timeout']
    ]);
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        $error = curl_error($ch); curl_close($ch);
        return ['error'=>DEVCODE_JUDGE0_ERROR_CONNECTION,'message'=>'Connection error: '.$error];
    }
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($http_code < 200 || $http_code >= 300) {
        return ['error'=>DEVCODE_JUDGE0_ERROR_HTTP,'message'=>'HTTP error: '.$http_code,'response'=>$response];
    }
    $result = json_decode($response, true);
    if ($result === null) {
        return ['error'=>DEVCODE_JUDGE0_ERROR_RESPONSE,'message'=>'Failed to parse response: '.json_last_error_msg(),'response'=>$response];
    }
    return $result;
}

function devcode_get_status_mapping() {
    return [
        1=>'In Queue',2=>'Processing',3=>'Accepted',4=>'Wrong Answer',5=>'Time Limit Exceeded',
        6=>'Compilation Error',7=>'Runtime Error (SIGSEGV)',8=>'Runtime Error (SIGXFSZ)',9=>'Runtime Error (SIGFPE)',
        10=>'Runtime Error (SIGABRT)',11=>'Runtime Error (NZEC)',12=>'Runtime Error (Other)',13=>'Internal Error',14=>'Exec Format Error'
    ];
}

function devcode_map_status($status_id) {
    $m = devcode_get_status_mapping();
    return $m[$status_id] ?? 'Unknown Status';
}

function devcode_grade_with_judge0($submission, $testcases, $language_id, $devcode) {
    global $DB;
    debugging('Starting grading with Judge0 for submission: '.$submission->id, DEBUG_DEVELOPER);
    if (empty($testcases)) { debugging('No test cases found', DEBUG_DEVELOPER); return false; }
    $config = devcode_get_judge0_config();
    $total_points = $earned_points = $max_execution_time = $passed_tests = 0;
    $total_tests = count($testcases); $feedback = '';
    $DB->set_field('devcode_submissions', 'status', 'processing', ['id'=>$submission->id]);
    foreach ($testcases as $tc) {
        debugging('Processing test case: '.$tc->id, DEBUG_DEVELOPER);
        $api_data = [
            'source_code'=>$submission->code,
            'language_id'=>$language_id,
            'stdin'=>$tc->input,
            'expected_output'=>$tc->output
        ];
        $resp = devcode_send_to_api($api_data, $config);
        $result = (object)[
            'submissionid'=>$submission->id,
            'testcaseid'=>$tc->id,
            'output'=>'','error_message'=>'','execution_time'=>0,'passed'=>0,'points'=>0
        ];
        if (isset($resp['error'])) {
            debugging('Judge0 API error: '.$resp['message'], DEBUG_DEVELOPER);
            $result->error_message = 'API Error: '.$resp['message'];
            $has_api_error = true;
            $DB->insert_record('devcode_submission_results', $result);
            continue;
        }
        $token = $resp['token'];
        $poll = devcode_poll_submission($token, $config);
        if (isset($poll['error'])) {
            debugging('Judge0 polling error: '.$poll['message'], DEBUG_DEVELOPER);
            $result->error_message = 'Polling Error: '.$poll['message'];
            $has_api_error = true;
            $DB->insert_record('devcode_submission_results', $result);
            continue;
        }
        $jr = $poll['result'];
        $result->output = $jr['stdout'] ?? '';
        $result->error_message = $jr['stderr'] ?? '';
        $result->execution_time = isset($jr['time']) ? ($jr['time']*1000) : 0;
        $max_execution_time = max($max_execution_time, $result->execution_time);
        $status_id = $jr['status']['id'] ?? 0;
        $output_correct = false;
        if ($status_id == 3) {
            $expected = str_replace("\r\n","\n",trim($tc->output));
            $actual = str_replace("\r\n","\n",trim($result->output));
            $exact = ($expected === $actual);
            $expected_norm = preg_replace('/\s+/',' ',$expected);
            $actual_norm = preg_replace('/\s+/',' ',$actual);
            $result->normalized_match = ($expected_norm === $actual_norm);
            $output_correct = $exact;
        }
        $result->passed = $output_correct ? 1 : 0;
        $result->points = $output_correct ? $tc->points : 0;
        $total_points += $tc->points;
        $earned_points += $result->points;
        if ($result->passed) $passed_tests++;
        $DB->insert_record('devcode_submission_results', $result);
        if (!$result->passed) {
            $feedback .= 'Test case '.$tc->id.' failed: ';
            if ($status_id != 3) {
                $desc = $jr['status']['description'] ?? 'Unknown error';
                $feedback .= $desc;
                if (!empty($result->error_message)) $feedback .= "\n".$result->error_message;
                if (!empty($jr['compile_output'])) $feedback .= "\nCompile output: ".$jr['compile_output'];
            } else {
                $feedback .= "Output doesn't match expected result.\n";
                if (!empty($result->normalized_match)) $feedback .= "Note: Your output would match if all whitespace was normalized. Check for extra spaces, tabs, or line breaks.\n";
                $feedback .= 'Expected: "'.htmlspecialchars(trim($tc->output))."\"\n";
                $feedback .= 'Your output: "'.htmlspecialchars(trim($result->output))."\"\n";
            }
            $feedback .= "\n";
        }
    }
    $final_score = $total_points > 0 ? ($earned_points/$total_points)*10 : 0;
    $status = (isset($has_api_error) && $has_api_error) ? 'error' : 'graded';
    if ($status == 'error') debugging('Setting submission status to error due to API errors', DEBUG_DEVELOPER);
    $submission_update = (object)[
        'id'=>$submission->id,
        'status'=>$status,
        'score'=>$final_score,
        'feedback'=>$feedback,
        'timemodified'=>time(),
        'passed_tests'=>$passed_tests,
        'total_tests'=>$total_tests,
        'execution_time'=>$max_execution_time
    ];
    $DB->update_record('devcode_submissions', $submission_update);
    debugging('Grading completed for submission: '.$submission->id.' with score: '.$final_score, DEBUG_DEVELOPER);
    return true;
}