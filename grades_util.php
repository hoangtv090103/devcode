<?php
/*
 * Utilities for grade handling in DevCode
 *
 * @package    mod_devcode
 * @copyright  2024 Your Name <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/judge0_api.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->libdir . '/gradelib.php');

/**
 * Update grades in the gradebook
 *
 * @param object $devcode The DevCode module instance
 * @param int $userid The user ID to update grades for, or 0 for all users
 * @param bool $nullifnone Whether to use NULL if no grade exists
 * @return bool True if successful
 */
function devcode_update_grades($devcode, $userid = 0, $nullifnone = true) {
    global $CFG, $DB;
    require_once($CFG->libdir . '/gradelib.php');

    // Check if we're using grades
    if ($devcode->grade == 0) {
        return true;
    }

    // Get all the required records
    $params = [
        'devcodemoduleid' => $devcode->id
    ];

    if ($userid > 0) {
        $params['userid'] = $userid;
        $userids = [$userid];
    } else {
        // Get all users with attempts
        $userids = $DB->get_fieldset_sql(
            "SELECT DISTINCT userid FROM {devcode_submissions} WHERE devcodemoduleid = ?",
            [$devcode->id]
        );
    }

    if (empty($userids)) {
        return true;
    }

    $grades = [];
    
    foreach ($userids as $uid) {
        $grade = devcode_calculate_user_grade($devcode, $uid);
        
        // If there is no grade and $nullifnone is true, set grade to null
        if ($grade === false && $nullifnone) {
            $grades[$uid] = null;
        } else if ($grade !== false) {
            $grades[$uid] = (object)[
                'userid' => $uid,
                'rawgrade' => $grade
            ];
        }
    }

    // Update the grades in the gradebook
    $result = devcode_grade_item_update($devcode, $grades);
    
    // Cập nhật thống kê cho student dashboard nếu plugin report_devcodereports có cài đặt
    if (file_exists($CFG->dirroot . '/report/devcodereports/lib.php')) {
        require_once($CFG->dirroot . '/report/devcodereports/lib.php');
        if (function_exists('report_devcodereports_update_student_stats')) {
            debugging('Updating student stats after grade update', DEBUG_DEVELOPER);
            
            if ($userid > 0) {
                // Cập nhật cho một sinh viên cụ thể
                report_devcodereports_update_student_stats($devcode->course, $userid);
            } else {
                // Cập nhật cho tất cả sinh viên đã nộp bài
                foreach ($userids as $uid) {
                    report_devcodereports_update_student_stats($devcode->course, $uid);
                }
            }
        }
    }
    
    return $result;
}

/**
 * Create or update the grade item for a given DevCode instance
 *
 * @param object $devcode The DevCode module instance
 * @param mixed $grades Optional array/object of grade(s)
 * @return int 0 if OK, error code otherwise
 */
function devcode_grade_item_update($devcode, $grades = null) {
    global $CFG;
    
    if (!function_exists('grade_update')) {
        require_once($CFG->libdir . '/gradelib.php');
    }
    
    $params = [
        'itemname' => $devcode->name,
        'idnumber' => $devcode->cmidnumber
    ];
    
    // Set grading details
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
 * Calculate the grade for a user based on their submissions
 *
 * @param object $devcode The DevCode module instance
 * @param int $userid The user ID to calculate grade for
 * @return float|bool The calculated grade or false if no grade
 */
function devcode_calculate_user_grade($devcode, $userid) {
    global $DB;
    
    // Get the grading strategy
    $grading_strategy = isset($devcode->grading_strategy) ? $devcode->grading_strategy : 'best';
    
    // Get all the submissions for this user and this assignment
    $sql = "SELECT s.id, s.status, s.grade, s.timecreated, s.timemodified
            FROM {devcode_submissions} s
            WHERE s.devcodemoduleid = ? AND s.userid = ?
            ORDER BY s.timemodified DESC";
    
    $submissions = $DB->get_records_sql($sql, [$devcode->id, $userid]);
    
    if (empty($submissions)) {
        return false;
    }
    
    // Use the appropriate grading strategy
    switch ($grading_strategy) {
        case 'last':
            // Get the most recent submission
            $submission = reset($submissions);
            return is_numeric($submission->grade) ? $submission->grade : false;
            
        case 'best':
            // Get the best grade from all submissions
            $best_grade = false;
            foreach ($submissions as $submission) {
                if (is_numeric($submission->grade) && 
                    ($best_grade === false || $submission->grade > $best_grade)) {
                    $best_grade = $submission->grade;
                }
            }
            return $best_grade;
            
        case 'average':
            // Calculate the average grade for all graded submissions
            $total = 0;
            $count = 0;
            foreach ($submissions as $submission) {
                if (is_numeric($submission->grade)) {
                    $total += $submission->grade;
                    $count++;
                }
            }
            return $count > 0 ? $total / $count : false;
            
        default:
            // Default to the best grade
            $best_grade = false;
            foreach ($submissions as $submission) {
                if (is_numeric($submission->grade) && 
                    ($best_grade === false || $submission->grade > $best_grade)) {
                    $best_grade = $submission->grade;
                }
            }
            return $best_grade;
    }
}

/**
 * Grade a submission based on its test results
 *
 * @param object $submission The submission record
 * @param array $test_results The test results from Judge0
 * @param object $devcode The DevCode module instance
 * @return float The calculated grade
 */
function devcode_grade_submission($submission, $test_results, $devcode) {
    global $DB;
    
    // Get grading method
    $grading_method = isset($devcode->grading_method) ? $devcode->grading_method : 'tests';
    
    // Initialize the grade variables
    $max_grade = $devcode->grade;
    $grade = 0;
    
    // Calculate grade based on the grading method
    switch ($grading_method) {
        case 'tests':
            // Grade based on test cases
            $grade = devcode_grade_by_tests($test_results, $max_grade);
            break;
            
        case 'rubric':
            // Grade using a rubric (if defined)
            $grade = devcode_grade_by_rubric($submission, $test_results, $devcode);
            break;
            
        case 'manual':
            // No automatic grading, set grade to null for manual grading
            return null;
            
        default:
            // Default to test-based grading
            $grade = devcode_grade_by_tests($test_results, $max_grade);
    }
    
    // Update the submission grade
    $submission->grade = $grade;
    $submission->timemodified = time();
    
    // Save the updated submission
    $DB->update_record('devcode_submissions', $submission);
    
    // Update grades in the gradebook
    devcode_update_grades($devcode, $submission->userid);
    
    // Cập nhật thống kê cho student dashboard nếu plugin report_devcodereports có cài đặt
    if (file_exists($CFG->dirroot . '/report/devcodereports/lib.php')) {
        require_once($CFG->dirroot . '/report/devcodereports/lib.php');
        if (function_exists('report_devcodereports_update_student_stats')) {
            debugging('Updating student stats after submission grading', DEBUG_DEVELOPER);
            report_devcodereports_update_student_stats($devcode->course, $submission->userid);
        }
    }
    
    return $grade;
}

/**
 * Grade a submission based on test results
 *
 * @param array $test_results The test results from Judge0
 * @param float $max_grade The maximum possible grade
 * @return float The calculated grade
 */
function devcode_grade_by_tests($test_results, $max_grade) {
    if (empty($test_results) || !isset($test_results['submissions'])) {
        return 0;
    }
    
    $submissions = $test_results['submissions'];
    $total_tests = count($submissions);
    
    if ($total_tests == 0) {
        return 0;
    }
    
    $passed_tests = 0;
    
    foreach ($submissions as $test) {
        // Consider a test passed if status is 3 (Accepted)
        if (isset($test['status']) && isset($test['status']['id']) && $test['status']['id'] == 3) {
            $passed_tests++;
        }
    }
    
    // Calculate grade as a percentage of passed tests
    $grade = ($passed_tests / $total_tests) * $max_grade;
    
    return $grade;
}

/**
 * Grade a submission using a rubric
 *
 * @param object $submission The submission record
 * @param array $test_results The test results from Judge0
 * @param object $devcode The DevCode module instance
 * @return float The calculated grade
 */
function devcode_grade_by_rubric($submission, $test_results, $devcode) {
    global $DB, $CFG;
    
    // Check if we have the advanced grading framework available
    if (!file_exists($CFG->dirroot . '/grade/grading/lib.php')) {
        // Fall back to test-based grading
        return devcode_grade_by_tests($test_results, $devcode->grade);
    }
    
    require_once($CFG->dirroot . '/grade/grading/lib.php');
    
    $context = context_module::instance($devcode->coursemodule);
    $gradingmanager = get_grading_manager($context, 'mod_devcode', 'submissions');
    $gradingmethod = $gradingmanager->get_active_method();
    
    // Check if a rubric is defined
    if ($gradingmethod != 'rubric') {
        // Fall back to test-based grading
        return devcode_grade_by_tests($test_results, $devcode->grade);
    }
    
    // Get the grading instance
    $gradinginstance = $gradingmanager->get_active_controller()->get_or_create_instance(
        0, // gradeid
        $submission->userid,
        $submission->grader
    );
    
    // Get the rubric criteria
    $criteria = $gradinginstance->get_definition()->rubric_criteria;
    
    if (empty($criteria)) {
        // Fall back to test-based grading
        return devcode_grade_by_tests($test_results, $devcode->grade);
    }
    
    // Calculate the rubric grade
    $rubric_grade = 0;
    
    // For simplicity, this example just calculates a proportion of passed tests
    // In a real implementation, you would map test results to specific rubric criteria
    if (!empty($test_results) && isset($test_results['submissions'])) {
        $submissions = $test_results['submissions'];
        $total_tests = count($submissions);
        
        if ($total_tests > 0) {
            $passed_tests = 0;
            
            foreach ($submissions as $test) {
                if (isset($test['status']) && isset($test['status']['id']) && $test['status']['id'] == 3) {
                    $passed_tests++;
                }
            }
            
            $passing_ratio = $passed_tests / $total_tests;
            
            // Assign rubric grades based on test passing ratio
            // This is a simplified approach; real implementation would be more sophisticated
            foreach ($criteria as $criterion) {
                $levels = $criterion['levels'];
                $sorted_levels = array_values($levels);
                usort($sorted_levels, function($a, $b) {
                    return $b['score'] - $a['score'];
                });
                
                // Determine which level to assign based on passing ratio
                $level_count = count($sorted_levels);
                $level_index = min(floor($passing_ratio * $level_count), $level_count - 1);
                $selected_level = $sorted_levels[$level_index];
                
                $rubric_grade += $selected_level['score'];
            }
        }
    }
    
    return $rubric_grade;
}

/**
 * Apply late submission penalty to a grade
 *
 * @param float $grade The original grade
 * @param object $submission The submission record
 * @param object $devcode The DevCode module instance
 * @return float The adjusted grade after applying late penalty
 */
function devcode_apply_late_penalty($grade, $submission, $devcode) {
    // Check if late penalties are enabled
    if (empty($devcode->cutoffdate) || empty($devcode->duedate) || $devcode->late_penalty <= 0) {
        return $grade;
    }
    
    // Check if submission was late
    if ($submission->timecreated <= $devcode->duedate) {
        return $grade; // Not late
    }
    
    // Check if submission was after cutoff
    if (!empty($devcode->cutoffdate) && $submission->timecreated > $devcode->cutoffdate) {
        // After cutoff date, check the policy
        if ($devcode->late_policy == 'zero') {
            return 0; // Zero grade for submissions after cutoff
        } else if ($devcode->late_policy == 'reject') {
            // This shouldn't happen as submissions should be rejected after cutoff
            return $grade;
        }
    }
    
    // Calculate how late the submission was
    $seconds_late = $submission->timecreated - $devcode->duedate;
    $hours_late = ceil($seconds_late / 3600); // Round up to nearest hour
    
    // Calculate penalty
    $penalty_per_hour = $devcode->late_penalty; // Percentage per hour
    $penalty_percentage = min($penalty_per_hour * $hours_late, 100); // Cap at 100%
    
    // Apply penalty
    $penalty_amount = $grade * ($penalty_percentage / 100);
    $adjusted_grade = $grade - $penalty_amount;
    
    // Ensure grade doesn't go below 0
    return max(0, $adjusted_grade);
} 
 