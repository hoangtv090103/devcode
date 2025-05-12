<?php

/**
 * Plagiarism detection functions for module devcode
 * @package    mod_devcode
 */

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once(__DIR__ . '/config.php');
require_once(__DIR__ . '/apilib.php');
require_once(__DIR__ . '/gradelib.php');
require_once(__DIR__ . '/dolos_lib.php');
require_once($CFG->dirroot . '/lib/accesslib.php');
require_once($CFG->dirroot . '/lib/enrollib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/modinfolib.php');

function devcode_check_plagiarism($submissionid)
{
    global $CFG, $DB;
    $oldlimit = ini_get('max_execution_time');
    set_time_limit(300);
    try {
        debugging("devcode_check_plagiarism: $submissionid", DEBUG_DEVELOPER);
        if (empty($CFG->devcode)) require_once(__DIR__ . '/config.php');
        $submission = $DB->get_record('devcode_submissions', ['id' => $submissionid], '*', MUST_EXIST);
        $devcode = $DB->get_record('devcode', ['id' => $submission->devcodeid], '*', MUST_EXIST);
        if (empty($devcode->course)) {
            $cm = get_coursemodule_from_instance('devcode', $devcode->id, 0, false, MUST_EXIST);
            $devcode->course = $cm->course;
        }
        if (empty($devcode->enable_plagiarism)) return false;

        // Handle either language or language_id field
        $language = !empty($devcode->language) ? $devcode->language : (isset($submission->language_id) ? $submission->language_id : $submission->language);

        // Only compare against student submissions 
        // First, check if current submission is from teacher/admin
        $is_teacher_admin = $DB->record_exists_sql(
            "SELECT 1 FROM {role_assignments} ra
             JOIN {role} r ON ra.roleid = r.id
             JOIN {user} u ON ra.userid = u.id
             JOIN {context} ctx ON ra.contextid = ctx.id
             WHERE u.id = ? AND (r.shortname = 'teacher' OR r.shortname = 'editingteacher' OR r.shortname = 'manager' OR r.shortname = 'admin')
             AND ctx.instanceid = ? AND ctx.contextlevel = 50", // 50 = CONTEXT_COURSE
            array($submission->userid, $devcode->course)
        );

        if ($is_teacher_admin) {
            debugging('Skipping plagiarism check for teacher/admin submission', DEBUG_DEVELOPER);
            return false;
        }

        // Get student IDs in this course (excluding the current user)
        $student_ids = $DB->get_fieldset_sql(
            "SELECT DISTINCT u.id 
            FROM {user} u
            JOIN {role_assignments} ra ON u.id = ra.userid
            JOIN {role} r ON ra.roleid = r.id
            JOIN {context} ctx ON ra.contextid = ctx.id
            WHERE r.shortname = 'student'
            AND ctx.instanceid = ? AND ctx.contextlevel = 50
            AND u.id <> ?",
            array($devcode->course, $submission->userid)
        );

        if (empty($student_ids)) {
            debugging('No other students found for plagiarism check', DEBUG_DEVELOPER);
            return false;
        }

        // Only compare with OLDER submissions (submitted before current one)
        // Create a string with placeholders for the student IDs
        $placeholders = implode(',', array_fill(0, count($student_ids), '?'));

        // If we have no student IDs, use a condition that will return no results
        if (empty($placeholders)) {
            $placeholders = '-1';
        }

        $sql = "SELECT s.* FROM {devcode_submissions} s
                INNER JOIN (
                    SELECT userid, MAX(timemodified) AS maxtime
                    FROM {devcode_submissions}
                    WHERE devcodeid = ? AND userid != ? AND timemodified < ?
                    AND (status = 'graded' OR status = 'completed' OR status = 'accepted')
                    GROUP BY userid
                ) latest
                ON s.userid = latest.userid AND s.timemodified = latest.maxtime
                WHERE s.devcodeid = ? AND s.userid IN ($placeholders)";

        $params = array($devcode->id, $submission->userid, $submission->timemodified, $devcode->id);
        $params = array_merge($params, $student_ids);

        $others = $DB->get_records_sql($sql, $params);
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
            $threshold = isset($devcode->similarity_threshold) ? $devcode->similarity_threshold : 80;
            if ($sim * 100 >= $threshold) {
                // Use consistent status 'plagiarism_detected' for all plagiarism cases
                $submission->status = 'plagiarism_detected';
                $submission->plagiarism_url = $resp['plagiarism_url'] ?? '';
                $percent = round($sim * 100, 2);
                $msg = get_string('plagiarism_detected', 'mod_devcode', format_string($percent));
                if (!empty($submission->plagiarism_url)) $msg .= ' ' . get_string('plagiarism_details', 'mod_devcode', $submission->plagiarism_url);
                $submission->score = 0;
                $submission->feedback = $msg;
                $submission->timemodified = time();
                $DB->update_record('devcode_submissions', $submission);
                devcode_update_grades($devcode, $submission->userid);
                debugging("Plagiarism detected in legacy method: " . $percent . "% >= threshold " . $threshold . "%", DEBUG_DEVELOPER);
                return true;
            }
        }
        return false;
    } finally {
        set_time_limit($oldlimit);
    }
}

function devcode_check_plagiarism_dolos($submission, $others, $language, $devcode)
{
    debugging('dolos plagiarism check', DEBUG_DEVELOPER);
    $normlang = devcode_normalize_language_for_dolos($language);
    $ext = devcode_get_file_extension($normlang);
    $subs = [[
        "id" => "current",
        "filename" => "current_submission.$ext",
        "code" => $submission->code,
        "username" => "user_" . $submission->userid,
        "timestamp" => $submission->timemodified // Add timestamp for chronological check
    ]];
    foreach ($others as $o) {
        // Check if languages match, supporting both language and language_id fields
        $current_lang = isset($submission->language_id) ? $submission->language_id : $submission->language;
        $other_lang = isset($o->language_id) ? $o->language_id : $o->language;

        // Only compare with submissions that have matching language and are older
        if ($other_lang != $current_lang || $o->timemodified >= $submission->timemodified) continue;

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
    if (!$report) {
        debugging('Report processing failed: ' . json_encode($report), DEBUG_DEVELOPER);
        return false;
    }

    // Handle tree-sitter-generic dependency issue
    if ($report['status'] === 'failed' && isset($report['error']) && $report['error'] === 'tree_sitter_generic_missing') {
        debugging("Detected tree-sitter-generic dependency issue. Retrying with 'c' language.", DEBUG_DEVELOPER);

        // Create a new submission with 'c' language
        $result = dolos_submit_zip($zip, "assignment_{$devcode->id}_retry", 'c');
        if (!$result) {
            debugging('Failed retry submission to Dolos API', DEBUG_DEVELOPER);
            return false;
        }

        $report_id = $result['id'] ?? '';
        if (!$report_id && isset($result['html_url']) && preg_match('/\/reports\/([^\/]+)/', $result['html_url'], $m)) {
            $report_id = $m[1];
        }
        if (!$report_id) {
            debugging('No report ID from retry submission', DEBUG_DEVELOPER);
            return false;
        }

        // Poll for report again
        $report = dolos_poll_report($report_id);
        if (!$report || $report['status'] !== 'finished') {
            debugging('Retry report processing failed: ' . json_encode($report), DEBUG_DEVELOPER);
            return false;
        }
    }

    if ($report['status'] !== 'finished') {
        debugging('Report status not finished: ' . $report['status'], DEBUG_DEVELOPER);
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
    $threshold = isset($devcode->similarity_threshold) ? $devcode->similarity_threshold : 80;
    debugging("Similarity threshold: " . $threshold . "%", DEBUG_DEVELOPER);

    $plag = false;
    $maxsim = 0;
    $matches = [];
    foreach ($pairs as $pair) {
        // Only process pairs where the left path (first file) is the current submission
        $current_submission_filename = "current_submission";
        if (!isset($pair['leftFilePath']) || strpos($pair['leftFilePath'], $current_submission_filename) === 0) {
            continue;
        }

        $sim = !empty($pair['similarity']) ? floatval($pair['similarity']) : 0;
        $percent = round($sim * 100, 2);

        // Debug the similarity comparison for better troubleshooting
        debugging("Checking pair similarity: " . $percent . "% vs threshold " . $threshold . "%", DEBUG_DEVELOPER);

        if ($sim > $maxsim) $maxsim = $sim;

        // Check if similarity exceeds threshold
        if ($percent >= $threshold) {
            debugging("Pair exceeds threshold: " . $percent . "% >= " . $threshold . "%", DEBUG_DEVELOPER);
            $plag = true;
            // Add note about which is the newer submission
            $pair['chronology_note'] = 'This is a newer submission with similarities to older student work';
            $matches[] = $pair;
        } else {
            debugging("Pair below threshold: " . $percent . "% < " . $threshold . "%", DEBUG_DEVELOPER);
        }
    }

    if ($plag) {
        $percent = round($maxsim * 100, 2);
        debugging("Plagiarism detected: " . $percent . "%", DEBUG_DEVELOPER);
        $submission->status = 'plagiarism_detected';
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
            $current_submission_id = $submission->id;
            $other_submission_id = 0;

            // Attempt to find the other submission ID from the right file path
            if (isset($pair['rightFilePath']) && preg_match('/submission_(\\d+)\\./', $pair['rightFilePath'], $path_matches)) {
                $other_submission_id = (int)$path_matches[1];
            } else {
                debugging("Could not extract submission ID from right path: " . ($pair['rightFilePath'] ?? 'Not set') . " for current submission ID: " . $current_submission_id, DEBUG_DEVELOPER);
                continue; // Skip if we can't identify the second submission
            }

            // Ensure IDs are different (a submission cannot be plagiarised against itself)
            if ($current_submission_id == $other_submission_id) {
                debugging("Skipping plagiarism check against self for submission ID: " . $current_submission_id, DEBUG_DEVELOPER);
                continue;
            }

            // Standardize the order of IDs: submission1_id will always be the smaller ID
            $s1_id = min($current_submission_id, $other_submission_id);
            $s2_id = max($current_submission_id, $other_submission_id);

            // Check if this pair already exists in the plagiarism table
            $existing_plagiarism_record = $DB->get_record('devcode_plagiarism', [
                'submission1_id' => $s1_id,
                'submission2_id' => $s2_id,
                'devcodeid' => $devcode->id
            ]);

            $new_similarity_score = round(floatval($pair['similarity'] ?? 0) * 100, 2);
            $new_details = json_encode($pair);

            if (!$existing_plagiarism_record) {
                // If the record does not exist, insert it
                $record_to_insert = (object)[
                    'submission1_id' => $s1_id,
                    'submission2_id' => $s2_id,
                    'similarity_score' => $new_similarity_score,
                    'devcodeid' => $devcode->id,
                    'details' => $new_details,
                    'flagged' => 1, // Flagged by default when inserted
                    'timecreated' => time(),
                    'timemodified' => time()
                ];
                $DB->insert_record('devcode_plagiarism', $record_to_insert);
                debugging("Inserted plagiarism record for pair ({$s1_id}, {$s2_id}) with score {$new_similarity_score}%", DEBUG_DEVELOPER);
            } else {
                // If the record exists, update it if the new score is higher or details changed
                if ($new_similarity_score > $existing_plagiarism_record->similarity_score || $existing_plagiarism_record->details !== $new_details) {
                     $existing_plagiarism_record->similarity_score = $new_similarity_score;
                     $existing_plagiarism_record->details = $new_details;
                     $existing_plagiarism_record->flagged = 1; // Ensure it's flagged if re-detected or score changes
                     $existing_plagiarism_record->timemodified = time();
                     $DB->update_record('devcode_plagiarism', $existing_plagiarism_record);
                     debugging("Updated plagiarism record for pair ({$s1_id}, {$s2_id}) to score {$new_similarity_score}%", DEBUG_DEVELOPER);
                } else {
                    debugging("Plagiarism record for pair ({$s1_id}, {$s2_id}) with score {$existing_plagiarism_record->similarity_score}% already exists and new score {$new_similarity_score}% is not higher. No changes made.", DEBUG_DEVELOPER);
                }
            }
        }

        devcode_update_grades($devcode, $submission->userid);
        return true;
    }

    debugging('No plagiarism above threshold', DEBUG_DEVELOPER);
    return false;
}

function devcode_create_submissions_zip($subs)
{
    $temp_dir = rtrim(sys_get_temp_dir(), '/') . '/' . uniqid('dolos_dir_');
    if (!mkdir($temp_dir, 0755, true)) {
        debugging('Cannot create temp dir for ZIP', DEBUG_DEVELOPER);
        return false;
    }
    foreach ($subs as $s) file_put_contents("$temp_dir/{$s['filename']}", $s['code']);
    $temp_zip = tempnam(sys_get_temp_dir(), 'dolos_zip_') . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($temp_zip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        debugging('Cannot create ZIP', DEBUG_DEVELOPER);
        array_map('unlink', glob("$temp_dir/*"));
        rmdir($temp_dir);
        return false;
    }
    foreach (glob("$temp_dir/*") as $f) $zip->addFile($f, basename($f));
    $zip->close();
    $content = file_get_contents($temp_zip);
    unlink($temp_zip);
    array_map('unlink', glob("$temp_dir/*"));
    rmdir($temp_dir);
    return $content;
}

function devcode_normalize_language_for_dolos($language)
{
    global $CFG;
    if (is_numeric($language)) $language = devcode_get_language_by_id($language);
    $language = strtolower(trim(preg_replace('/\(.*\)/', '', $language)));

    // Check if plagiarism configuration exists
    if (!isset($CFG->devcode) || !is_array($CFG->devcode)) {
        debugging('DevCode configuration not found in $CFG->devcode', DEBUG_DEVELOPER);
        // Default to 'c' instead of 'generic' to avoid tree-sitter-generic dependency
        return 'c';
    }

    if (!isset($CFG->devcode['plagiarism']) || !is_array($CFG->devcode['plagiarism'])) {
        debugging('Plagiarism configuration not found in $CFG->devcode[\'plagiarism\']', DEBUG_DEVELOPER);
        // Default to 'c' instead of 'generic' to avoid tree-sitter-generic dependency
        return 'c';
    }

    if (!isset($CFG->devcode['plagiarism']['language_mapping']) || !is_array($CFG->devcode['plagiarism']['language_mapping'])) {
        debugging('Language mapping not found in plagiarism configuration', DEBUG_DEVELOPER);
        // Default to 'c' instead of 'generic' to avoid tree-sitter-generic dependency
        return 'c';
    }

    // Check if there's a direct mapping for this language
    foreach ($CFG->devcode['plagiarism']['language_mapping'] as $pattern => $mapped) {
        if (stripos($language, $pattern) !== false) {
            // Make sure the mapped language isn't 'generic'
            if ($mapped === 'generic') {
                debugging("Language '$language' mapped to 'generic' which is unsupported, using 'c' instead", DEBUG_DEVELOPER);
                return 'c';
            }
            return $mapped;
        }
    }

    debugging("No mapping found for language '$language', defaulting to 'c'", DEBUG_DEVELOPER);
    // Default to 'c' instead of 'generic' to avoid tree-sitter-generic dependency
    return 'c';
}

function devcode_get_file_extension($language)
{
    $exts = [
        'python' => 'py',
        'java' => 'java',
        'cpp' => 'cpp',
        'c' => 'c',
        'javascript' => 'js',
        'typescript' => 'ts',
        'go' => 'go',
        'rust' => 'rs',
        'php' => 'php'
    ];
    $language = strtolower($language);
    foreach ($exts as $k => $v) if (strpos($language, $k) !== false) return $v;
    return 'txt';
}

function devcode_get_dolos_config()
{
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
