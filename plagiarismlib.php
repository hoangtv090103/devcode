<?php
/**
 * Plagiarism detection functions for module devcode
 * @package    mod_devcode
 */

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/apilib.php');
require_once(__DIR__ . '/gradelib.php');
require_once(__DIR__ . '/dolos_lib.php');

function devcode_check_plagiarism($submissionid) {
    global $CFG, $DB;
    $oldlimit = ini_get('max_execution_time');
    set_time_limit(300);
    try {
        debugging("devcode_check_plagiarism: $submissionid", DEBUG_DEVELOPER);
        if (empty($CFG->devcode)) require_once(__DIR__ . '/config.php');
        $submission = $DB->get_record('devcode_submissions', ['id'=>$submissionid], '*', MUST_EXIST);
        $devcode = $DB->get_record('devcode', ['id'=>$submission->devcodeid], '*', MUST_EXIST);
        if (empty($devcode->course)) {
            $cm = get_coursemodule_from_instance('devcode', $devcode->id, 0, false, MUST_EXIST);
            $devcode->course = $cm->course;
        }
        if (empty($devcode->enable_plagiarism)) return false;
        $language = !empty($devcode->language) ? $devcode->language : $submission->language;
        $params = [$devcode->id, $submission->userid, $submission->id, $devcode->id, $submission->userid, $submission->id];
        $others = $DB->get_records_sql(
            "SELECT s.* FROM {devcode_submissions} s
             INNER JOIN (
                 SELECT userid, MAX(timemodified) AS maxtime
                 FROM {devcode_submissions}
                 WHERE devcodeid = ? AND userid != ? AND id != ?
                 GROUP BY userid
             ) latest
             ON s.userid = latest.userid AND s.timemodified = latest.maxtime
             WHERE s.devcodeid = ? AND s.userid != ? AND s.id != ?", $params);
        if (!$others) {
            debugging('No other submissions for plagiarism', DEBUG_DEVELOPER);
            return false;
        }
        $config = get_config('mod_devcode');
        $dolos_enabled = !empty($config->dolos_api_url) || (!empty($CFG->devcode['dolos']) && !empty($CFG->devcode['plagiarism']['enabled']));
        if ($dolos_enabled) {
            debugging('Using Dolos API', DEBUG_DEVELOPER);
            return devcode_check_plagiarism_dolos($submission, $others, $language, $devcode);
        }
        debugging('Using legacy API', DEBUG_DEVELOPER);
        $api_data = [
            'assignment_id' => $devcode->id,
            'userid' => $submission->userid,
            'code' => $submission->code,
            'language' => $language,
            'plagiarism_check_only' => true
        ];
        $url = $CFG->devcode['api_base_url'] . $CFG->devcode['api_endpoints']['submissions'];
        $resp = devcode_api_request($url, 'POST', $api_data);
        if (!$resp || !empty($resp['error'])) {
            debugging('API plagiarism error: ' . json_encode($resp), DEBUG_DEVELOPER);
            return false;
        }
        $detected = !empty($resp['plagiarism_detected']);
        if ($detected) {
            $sim = !empty($resp['plagiarism_similarity']) ? floatval($resp['plagiarism_similarity']) : 0;
            $threshold = isset($devcode->similarity_threshold) ? $devcode->similarity_threshold/100 : 0.8;
            if ($sim >= $threshold) {
                $submission->status = 'plagiarism';
                $submission->plagiarism_url = $resp['plagiarism_url'] ?? '';
                $msg = get_string('plagiarism_detected', 'mod_devcode', format_string($sim));
                if (!empty($submission->plagiarism_url)) $msg .= ' ' . get_string('plagiarism_details', 'mod_devcode', $submission->plagiarism_url);
                $submission->score = 0;
                $submission->feedback = $msg;
                $submission->timemodified = time();
                $DB->update_record('devcode_submissions', $submission);
                devcode_update_grades($devcode, $submission->userid);
                return true;
            }
        }
        return false;
    } finally {
        set_time_limit($oldlimit);
    }
}

function devcode_check_plagiarism_dolos($submission, $others, $language, $devcode) {
    debugging('dolos plagiarism check', DEBUG_DEVELOPER);
    $normlang = devcode_normalize_language_for_dolos($language);
    $ext = devcode_get_file_extension($normlang);
    $subs = [[
        "id" => "current",
        "filename" => "current_submission.$ext",
        "code" => $submission->code,
        "username" => "user_" . $submission->userid
    ]];
    foreach ($others as $o) {
        if ($o->language != $submission->language) continue;
        $subs[] = [
            "id" => (string)$o->id,
            "filename" => "submission_{$o->id}.$ext",
            "code" => $o->code,
            "username" => "user_" . $o->userid
        ];
    }
    if (count($subs) < 2) {
        debugging('Not enough submissions for Dolos', DEBUG_DEVELOPER);
        return false;
    }
    $zip = devcode_create_submissions_zip($subs);
    if (!$zip) {
        debugging('Failed to create zip for Dolos', DEBUG_DEVELOPER);
        return false;
    }
    
    // Use the functions from dolos_lib.php
    $result = dolos_submit_zip($zip, "assignment_{$devcode->id}", $normlang);
    if (!$result) {
        debugging('No result from Dolos API', DEBUG_DEVELOPER);
        return false;
    }
    
    $html_url = $result['html_url'] ?? '';
    if (!$html_url) {
        debugging('No HTML URL in Dolos response: ' . json_encode($result), DEBUG_DEVELOPER);
        return false;
    }
    
    $report_id = $result['id'] ?? '';
    if (!$report_id && preg_match('/\/reports\/([^\/]+)/', $html_url, $m)) $report_id = $m[1];
    if (!$report_id) {
        debugging('No report ID from Dolos', DEBUG_DEVELOPER);
        return false;
    }
    
    // Wait for report to be processed
    $report = dolos_poll_report($report_id);
    if (!$report || $report['status'] !== 'finished') {
        debugging('Report processing failed: ' . json_encode($report), DEBUG_DEVELOPER);
        return false;
    }
    
    // Get report data using the proper function
    $pairs = dolos_get_report_data($report_id, 'pairs');
    if (!$pairs) {
        debugging('No pairs data from report', DEBUG_DEVELOPER);
        return false;
    }
    
    debugging('Successfully received report data with ' . count($pairs) . ' pairs', DEBUG_DEVELOPER);
    
    // Process the plagiarism report
    $threshold = isset($devcode->similarity_threshold) ? $devcode->similarity_threshold/100 : 0.8;
    debugging("Similarity threshold: " . ($threshold*100) . "%", DEBUG_DEVELOPER);
    
    $plag = false; 
    $maxsim = 0; 
    $matches = [];
    
    foreach ($pairs as $pair) {
        $sim = !empty($pair['similarity']) ? floatval($pair['similarity']) : 0;
        if ($sim > $maxsim) $maxsim = $sim;
        if ($sim >= $threshold) { 
            $plag = true; 
            $matches[] = $pair; 
        }
    }
    
    if ($plag) {
        debugging("Plagiarism detected: " . ($maxsim*100) . "%", DEBUG_DEVELOPER);
        $submission->status = 'plagiarism_detected';
        $percent = round($maxsim*100,2);
        $msg = get_string('plagiarism_detected', 'devcode', $percent);
        
        if ($html_url) {
            $submission->plagiarism_url = $html_url;
            $msg .= ' ' . get_string('plagiarism_details', 'devcode', $html_url);
        }
        
        $submission->score = 0;
        $submission->feedback = $msg;
        $submission->timemodified = time();
        
        global $DB;
        $DB->update_record('devcode_submissions', $submission);
        
        foreach ($matches as $pair) {
            $rec = (object)[
                'submission1_id'=>$submission->id,
                'submission2_id'=>0,
                'similarity_score'=>floatval($pair['similarity']??0),
                'devcodeid'=>$devcode->id,
                'details'=>json_encode($pair),
                'flagged'=>1,
                'timecreated'=>time(),
                'timemodified'=>time()
            ];
            $DB->insert_record('devcode_plagiarism', $rec);
        }
        
        devcode_update_grades($devcode, $submission->userid);
        return true;
    }
    
    debugging('No plagiarism above threshold', DEBUG_DEVELOPER);
    return false;
}

function devcode_create_submissions_zip($subs) {
    $temp_dir = rtrim(sys_get_temp_dir(), '/') . '/' . uniqid('dolos_dir_');
    if (!mkdir($temp_dir, 0755, true)) {
        debugging('Cannot create temp dir for ZIP', DEBUG_DEVELOPER);
        return false;
    }
    foreach ($subs as $s) file_put_contents("$temp_dir/{$s['filename']}", $s['code']);
    $temp_zip = tempnam(sys_get_temp_dir(), 'dolos_zip_') . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($temp_zip, ZipArchive::CREATE|ZipArchive::OVERWRITE) !== true) {
        debugging('Cannot create ZIP', DEBUG_DEVELOPER);
        array_map('unlink', glob("$temp_dir/*")); rmdir($temp_dir);
        return false;
    }
    foreach (glob("$temp_dir/*") as $f) $zip->addFile($f, basename($f));
    $zip->close();
    $content = file_get_contents($temp_zip);
    unlink($temp_zip);
    array_map('unlink', glob("$temp_dir/*")); rmdir($temp_dir);
    return $content;
}

function devcode_normalize_language_for_dolos($language) {
    global $CFG;
    if (is_numeric($language)) $language = devcode_get_language_by_id($language);
    $language = strtolower(trim(preg_replace('/\(.*\)/', '', $language)));
    foreach ($CFG->devcode['plagiarism']['language_mapping'] as $k => $v) {
        if (strpos($language, $k) !== false) return $v;
    }
    return 'generic';
}

function devcode_get_file_extension($language) {
    $exts = [
        'python'=>'py','java'=>'java','cpp'=>'cpp','c'=>'c','javascript'=>'js',
        'typescript'=>'ts','go'=>'go','rust'=>'rs','php'=>'php'
    ];
    $language = strtolower($language);
    foreach ($exts as $k=>$v) if (strpos($language, $k)!==false) return $v;
    return 'txt';
}

function devcode_get_dolos_config() {
    global $CFG;
    
    // Lấy cài đặt từ config (đã được cập nhật từ settings)
    return [
        'dolos_api_url' => $CFG->devcode['dolos']['api_url'] ?? 'https://dolos.ugent.be/api',
        'dolos_timeout' => $CFG->devcode['dolos']['timeout'] ?? 120,
        'dolos_max_poll_attempts' => $CFG->devcode['dolos']['max_poll_attempts'] ?? 30,
        'dolos_poll_interval' => $CFG->devcode['dolos']['poll_interval'] ?? 5,
        'dolos_threshold' => $CFG->devcode['dolos']['threshold'] ?? 0.8,
        'dolos_headers' => $CFG->devcode['dolos']['headers'] ?? [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ]
    ];
}