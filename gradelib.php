<?php
/*
 * Grading library for DevCode module
 *
 * @package    mod_devcode
 * @copyright  2024 Your Name <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/gradelib.php');
require_once(__DIR__.'/lib.php');
require_once(__DIR__.'/judge0_api.php');

/**
 * Update grades in the gradebook
 *
 * @param object $devcode DevCode instance
 * @param int $userid specific user only, 0 means all users
 * @param bool $nullifnone If a single user is specified and $nullifnone is true, a grade item with a null rawgrade will be inserted
 */
function devcode_update_grades($devcode, $userid=0, $nullifnone=true) {
    global $CFG, $DB;
    
    if (!isset($devcode->grade) && !isset($devcode->total_points)) {
        return;
    }

    if ($grades = devcode_get_user_grades($devcode, $userid)) {
        devcode_grade_item_update($devcode, $grades);
    } else if ($userid && $nullifnone) {
            $grade = new stdClass();
        $grade->userid = $userid;
        $grade->rawgrade = null;
        devcode_grade_item_update($devcode, $grade);
    } else {
        devcode_grade_item_update($devcode);
    }
}

/**
 * Update/create grade item for given devcode
 *
 * @param object $devcode DevCode instance object with extra cmid property
 * @param mixed $grades Optional array/object of grade(s)
 * @return int 0 if ok, error code otherwise
 */
function devcode_grade_item_update($devcode, $grades=null) {
    global $CFG;

    if (!function_exists('grade_update')) {
        require_once($CFG->libdir.'/gradelib.php');
    }

    $params = [
        'itemname' => $devcode->name,
        'idnumber' => isset($devcode->cmid) ? $devcode->cmid : 0
    ];
    
    if ($devcode->grade > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax'] = $devcode->grade;
        $params['grademin'] = 0;
    } else if ($devcode->grade < 0) {
        $params['gradetype'] = GRADE_TYPE_SCALE;
        $params['scaleid'] = -$devcode->grade;
    } else {
        $params['gradetype'] = GRADE_TYPE_NONE;
    }
    
    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }
    
    return grade_update('mod/devcode', $devcode->course, 'mod', 'devcode', 
        $devcode->id, 0, $grades, $params);
}

/**
 * Get all user grades for a DevCode instance
 *
 * @param object $devcode DevCode instance
 * @param int $userid Optional user id, 0 means all users
 * @return array Array of grades, false if none
 */
function devcode_get_user_grades($devcode, $userid=0) {
    global $DB;
    
    $params = ['devcodeid' => $devcode->id];
    
    if ($userid) {
        $params['userid'] = $userid;
        $user_select = "AND u.id = :userid";
    } else {
        $user_select = "";
    }
    
    $sql = "SELECT u.id as userid, s.score as rawgrade, s.timemodified as dategraded
            FROM {user} u
            JOIN {devcode_submissions} s ON s.userid = u.id
            WHERE s.devcodeid = :devcodeid AND s.status = 'graded' $user_select
            GROUP BY u.id, s.score, s.timemodified";
    
    return $DB->get_records_sql($sql, $params);
}

/**
 * Grade a student submission
 *
 * @param object $devcode DevCode instance
 * @param int $userid User ID
 * @param array $result Judge0 result
 * @return bool Success status
 */
function devcode_grade_submission($devcode, $userid, $result) {
    global $DB, $USER;

    // Get the submission record
    $submission = $DB->get_record('devcode_submissions', [
        'devcodeid' => $devcode->id,
        'userid' => $userid,
        'status' => 'submitted'
    ]);
    
    if (!$submission) {
        return false;
    }

    // Default values
    $score = 0;
    $status = 'error';
    $feedback = '';
    
    // Process result from Judge0
    if (!isset($result['error'])) {
        $status_id = isset($result['result']['status']['id']) ? $result['result']['status']['id'] : 0;
        $status_description = isset($result['result']['status']['description']) ? $result['result']['status']['description'] : '';
        
        switch ($status_id) {
            case 3: // Accepted
                $score = $devcode->grade;
                $status = 'graded';
                $feedback = 'Accepted';
                break;
            case 4: // Wrong Answer
                $score = 0;
                $status = 'graded';
                $feedback = 'Wrong Answer';
                break;
            case 5: // Time Limit Exceeded
                $score = 0;
                $status = 'graded';
                $feedback = 'Time Limit Exceeded';
                break;
            case 6: // Compilation Error
                $score = 0;
                $status = 'graded';
                $feedback = 'Compilation Error: ' . 
                    (isset($result['result']['compile_output']) ? $result['result']['compile_output'] : '');
                break;
            default: // Other errors
                $score = 0;
                $status = 'graded';
                $feedback = 'Error: ' . $status_description;
                break;
        }
    } else {
        $feedback = 'Processing error: ' . $result['message'];
    }
    
    // Update submission record
    $submission->status = $status;
    $submission->score = $score;
    $submission->feedback = $feedback;
    $submission->timemodified = time();
    $submission->teacher = $USER->id;
        
    // Save submission
    $DB->update_record('devcode_submissions', $submission);
        
    // Update grade in gradebook
    devcode_update_grades($devcode, $userid);
        
    return true;
}

/**
 * Process all student submissions for a DevCode instance
 *
 * @param object $devcode DevCode instance
 * @return array Processing results
 */
function devcode_process_all_submissions($devcode) {
    global $DB;
    
    $submissions = $DB->get_records('devcode_submissions', [
        'devcodeid' => $devcode->id,
        'status' => 'submitted'
    ]);
    
    if (empty($submissions)) {
        return [
            'status' => 'error',
            'message' => 'No submissions to process',
            'processed' => 0
        ];
    }
    
    $count = 0;
    $errors = 0;

    foreach ($submissions as $submission) {
        $data = [
            'source_code' => $submission->sourcecode,
            'language_id' => $submission->languageid,
            'stdin' => $devcode->input_data,
            'expected_output' => $devcode->expected_output
        ];

        // Send to Judge0 API
        $result = devcode_send_to_api($data);
        
        if (!isset($result['error']) && isset($result['token'])) {
            // Poll for result
            $poll_result = devcode_poll_submission($result['token']);

            if (!isset($poll_result['error'])) {
                // Grade the submission
                devcode_grade_submission($devcode, $submission->userid, $poll_result);
                $count++;
            } else {
                $errors++;
            }
        } else {
            $errors++;
        }
    }
    
    return [
        'status' => 'success',
        'message' => "Processed $count submissions with $errors errors",
        'processed' => $count,
        'errors' => $errors
    ];
}

/**
 * Calculate statistics for all submissions for a DevCode instance
 *
 * @param object $devcode DevCode instance
 * @return array Statistics
 */
function devcode_calculate_stats($devcode) {
    global $DB;
    
    $stats = [
        'total' => 0,
        'submitted' => 0,
        'graded' => 0,
        'correct' => 0,
        'average_grade' => 0,
        'average_attempts' => 0,
        'highest_grade' => 0,
        'users_count' => 0
    ];
    
    // Total submissions
    $stats['total'] = $DB->count_records('devcode_submissions', ['devcodeid' => $devcode->id]);
    
    // Submitted, not yet graded
    $stats['submitted'] = $DB->count_records('devcode_submissions', [
        'devcodeid' => $devcode->id,
        'status' => 'submitted'
    ]);
    
    // Graded submissions
    $stats['graded'] = $DB->count_records('devcode_submissions', [
        'devcodeid' => $devcode->id,
        'status' => 'graded'
    ]);
    
    // Correct submissions (full grade)
    $stats['correct'] = $DB->count_records_select('devcode_submissions', 
        "devcodeid = :devcodeid AND status = 'graded' AND grade = :grade", 
        ['devcodeid' => $devcode->id, 'grade' => $devcode->grade]);
    
    // Average grade
    $avg = $DB->get_field_select('devcode_submissions', 'AVG(grade)', 
        "devcodeid = :devcodeid AND status = 'graded'", 
        ['devcodeid' => $devcode->id]);
    $stats['average_grade'] = $avg !== false ? round($avg, 2) : 0;
    
    // Highest grade
    $max = $DB->get_field_select('devcode_submissions', 'MAX(grade)', 
        "devcodeid = :devcodeid AND status = 'graded'", 
        ['devcodeid' => $devcode->id]);
    $stats['highest_grade'] = $max !== false ? $max : 0;
    
    // Count distinct users
    $stats['users_count'] = $DB->count_records_sql(
        "SELECT COUNT(DISTINCT userid) FROM {devcode_submissions} WHERE devcodeid = ?", 
        [$devcode->id]);

    // Average attempts per user
    if ($stats['users_count'] > 0) {
        $stats['average_attempts'] = round($stats['total'] / $stats['users_count'], 1);
    }
    
    return $stats;
}

/**
 * Get user submission statistics
 *
 * @param object $devcode DevCode instance
 * @param int $userid User ID
 * @return array User statistics
 */
function devcode_get_user_stats($devcode, $userid) {
    global $DB;
    
    $stats = [
        'total_attempts' => 0,
        'best_grade' => 0,
        'last_grade' => 0,
        'first_attempt' => 0,
        'last_attempt' => 0,
        'completed' => false
    ];
    
    // Total attempts
    $stats['total_attempts'] = $DB->count_records('devcode_submissions', [
        'devcodeid' => $devcode->id,
        'userid' => $userid
    ]);

    if ($stats['total_attempts'] == 0) {
        return $stats;
    }
    
    // Best grade
    $stats['best_grade'] = $DB->get_field_select('devcode_submissions', 'MAX(grade)', 
        "devcodeid = :devcodeid AND userid = :userid AND status = 'graded'", 
        ['devcodeid' => $devcode->id, 'userid' => $userid]);
    
    // Last submission
    $last = $DB->get_record_sql(
        "SELECT grade, timecreated FROM {devcode_submissions} 
         WHERE devcodeid = :devcodeid AND userid = :userid 
         ORDER BY timecreated DESC LIMIT 1", 
        ['devcodeid' => $devcode->id, 'userid' => $userid]);
    
    if ($last) {
        $stats['last_grade'] = $last->grade;
        $stats['last_attempt'] = $last->timecreated;
    }
    
    // First attempt
    $first = $DB->get_field_sql(
        "SELECT MIN(timecreated) FROM {devcode_submissions} 
         WHERE devcodeid = :devcodeid AND userid = :userid", 
        ['devcodeid' => $devcode->id, 'userid' => $userid]);
    
    if ($first) {
        $stats['first_attempt'] = $first;
    }
    
    // Check if completed (has full grade)
    $stats['completed'] = ($stats['best_grade'] == $devcode->grade);

    return $stats;
} 