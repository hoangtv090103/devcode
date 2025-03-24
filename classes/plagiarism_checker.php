<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plagiarism detection for DevCode.
 *
 * @package     mod_devcode
 * @copyright   2024 Your Name <your@email.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Class to handle plagiarism checking
 */
class mod_devcode_plagiarism_checker {
    
    /**
     * Check similarity between two submissions
     * 
     * @param string $code1 First code to compare
     * @param string $code2 Second code to compare
     * @param string $language Programming language
     * @return float Similarity percentage (0-100)
     */
    public static function check_similarity($code1, $code2, $language) {
        // Chuẩn bị code (loại bỏ comment, khoảng trắng dư thừa)
        $cleanCode1 = self::prepare_code($code1, $language);
        $cleanCode2 = self::prepare_code($code2, $language);
        
        // Thực hiện việc so sánh (sử dụng thuật toán longest common subsequence)
        $similarity = self::calculate_similarity($cleanCode1, $cleanCode2);
        
        return $similarity;
    }
    
    /**
     * Prepare code for comparison (remove comments, normalize whitespace)
     * 
     * @param string $code Code to prepare
     * @param string $language Programming language
     * @return string Prepared code
     */
    private static function prepare_code($code, $language) {
        $language = strtolower($language);
        
        // Loại bỏ comment dựa vào ngôn ngữ
        switch ($language) {
            case 'python':
                // Loại bỏ Python comments (# và docstrings)
                $code = preg_replace('/#.*$/m', '', $code);
                $code = preg_replace('/""".*?"""/s', '', $code);
                $code = preg_replace("/'''.*?'''/s", '', $code);
                break;
                
            case 'java':
            case 'c++':
            case 'javascript':
                // Loại bỏ C-style comments (// và /* */)
                $code = preg_replace('!//.*!', '', $code);
                $code = preg_replace('!/\*.*?\*/!s', '', $code);
                break;
        }
        
        // Chuẩn hóa khoảng trắng
        $code = preg_replace('/\s+/', ' ', $code);
        $code = trim($code);
        
        return $code;
    }
    
    /**
     * Calculate similarity between two code strings
     * 
     * @param string $code1 First code
     * @param string $code2 Second code
     * @return float Similarity percentage (0-100)
     */
    private static function calculate_similarity($code1, $code2) {
        if (empty($code1) || empty($code2)) {
            return 0;
        }
        
        // Sử dụng thuật toán Levenshtein distance
        $distance = levenshtein($code1, $code2);
        $maxLength = max(strlen($code1), strlen($code2));
        
        if ($maxLength === 0) {
            return 100; // Cả hai chuỗi đều rỗng => giống nhau 100%
        }
        
        $similarity = (1 - ($distance / $maxLength)) * 100;
        return round($similarity, 2);
    }
    
    /**
     * Check submissions for plagiarism in a devcode activity
     * 
     * @param int $devcodeid The ID of the devcode activity
     * @param float $threshold Similarity threshold (percentage)
     * @return array Array of suspicious submission pairs
     */
    public static function check_devcode_submissions($devcodeid, $threshold = 50) {
        global $DB;
        
        // Lấy thông tin về devcode
        $devcode = $DB->get_record('devcode', array('id' => $devcodeid), '*', MUST_EXIST);
        
        // Lấy tất cả các submission cho bài này
        $submissions = $DB->get_records('devcode_submissions', 
            array('devcodeid' => $devcodeid), 
            'userid, timemodified DESC');
        
        // Tạo mảng chỉ giữ submission mới nhất của mỗi user
        $latestSubmissions = array();
        foreach ($submissions as $submission) {
            if (!isset($latestSubmissions[$submission->userid])) {
                $latestSubmissions[$submission->userid] = $submission;
            }
        }
        
        // Kết quả sẽ chứa các cặp submission nghi vấn
        $results = array();
        
        // So sánh từng cặp submissions
        $users = array_keys($latestSubmissions);
        for ($i = 0; $i < count($users); $i++) {
            for ($j = $i + 1; $j < count($users); $j++) {
                $user1 = $users[$i];
                $user2 = $users[$j];
                
                $submission1 = $latestSubmissions[$user1];
                $submission2 = $latestSubmissions[$user2];
                
                $similarity = self::check_similarity(
                    $submission1->code, 
                    $submission2->code, 
                    $devcode->language
                );
                
                if ($similarity >= $threshold) {
                    $results[] = array(
                        'user1' => $user1,
                        'user2' => $user2,
                        'submission1' => $submission1->id,
                        'submission2' => $submission2->id,
                        'similarity' => $similarity
                    );
                }
            }
        }
        
        return $results;
    }
} 