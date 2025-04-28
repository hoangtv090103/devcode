<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * AI service class for DevCode
 *
 * @package     mod_devcode
 * @copyright   2025 Your Name <your.email@example.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_devcode\ai;

defined('MOODLE_INTERNAL') || die();

/**
 * Class that provides AI functionality for DevCode
 */
class service {
    
    /** @var api_client The Gemini API client */
    private $api_client;
    
    /** @var object The DevCode activity instance */
    private $devcode;
    
    /** @var object The submission record */
    private $submission;
    
    /** @var object The user record */
    private $user;
    
    /** @var object The course_module record */
    private $cm;
    
    /**
     * Constructor - initializes dependencies
     *
     * @param object $devcode The DevCode activity record
     * @param object $submission The submission record (optional)
     * @param object $user The user record (optional)
     * @param object $cm The course_module record (optional)
     */
    public function __construct($devcode, $submission = null, $user = null, $cm = null) {
        $this->api_client = new api_client();
        $this->devcode = $devcode;
        $this->submission = $submission;
        $this->user = $user;
        $this->cm = $cm;
        
        global $USER, $DB;
        
        // If user not provided, use current user
        if (empty($this->user)) {
            $this->user = $USER;
        }
        
        // If submission is provided but cm is not, try to get cm
        if ($this->submission && !$this->cm) {
            $this->cm = get_coursemodule_from_instance('devcode', $this->devcode->id, $this->devcode->course);
        }
    }
    
    /**
     * Check if AI is enabled for this activity
     *
     * @return bool Whether AI is enabled
     */
    public function is_enabled() {
        return !empty($this->devcode->ai_enabled) && $this->api_client->is_configured();
    }
    
    /**
     * Get remaining usage limit for a specific AI feature
     *
     * @param string $type The type of AI usage (explain, hint, improve)
     * @return int The number of usages remaining
     */
    public function get_remaining_usage($type) {
        global $DB;
        
        if (!$this->is_enabled()) {
            return 0;
        }
        
        // Determine the limit for this type
        $limit_field = 'ai_' . $type . '_limit';
        if (!isset($this->devcode->$limit_field)) {
            return 0;
        }
        $limit = $this->devcode->$limit_field;
        
        // Count how many times this user has used this type
        $used = $DB->count_records('devcode_ai_usage', [
            'devcodeid' => $this->devcode->id,
            'userid' => $this->user->id,
            'type' => $type
        ]);
        
        return max(0, $limit - $used);
    }
    
    /**
     * Check if the user can use a specific AI feature
     *
     * @param string $type The type of AI usage (explain, hint, improve)
     * @return bool Whether the user can use this feature
     */
    public function can_use($type) {
        return $this->is_enabled() && $this->get_remaining_usage($type) > 0;
    }
    
    /**
     * Record usage of an AI feature
     *
     * @param string $type The type of AI usage (explain, hint, improve)
     * @param string $query The query sent to the AI
     * @param string $response The response from the AI
     * @return bool Success or failure
     */
    private function record_usage($type, $query, $response) {
        global $DB;
        
        $record = new \stdClass();
        $record->devcodeid = $this->devcode->id;
        $record->userid = $this->user->id;
        $record->submissionid = $this->submission ? $this->submission->id : 0;
        $record->type = $type;
        $record->query = $query;
        $record->response = $response;
        $record->timecreated = time();
        
        return $DB->insert_record('devcode_ai_usage', $record) ? true : false;
    }
    
    /**
     * Get error explanation from AI
     *
     * @param string $error_message The error message to explain
     * @return string The AI explanation
     */
    public function get_error_explanation($error_message) {
        // Check if user can use this feature
        if (!$this->can_use('explain')) {
            throw new \moodle_exception('ailimitexceeded', 'mod_devcode');
        }
        
        // Build the prompt
        $prompt = "Bạn là một trợ giảng lập trình thân thiện. Sinh viên gặp lỗi sau: \n";
        $prompt .= $error_message . "\n";
        $prompt .= "với mã nguồn: \n";
        $prompt .= $this->submission->code . "\n";
        $prompt .= "Hãy giải thích lỗi này một cách dễ hiểu cho người mới học, không cung cấp code sửa lỗi.";
        
        // Send to API
        $response = $this->api_client->generate_content($prompt);
        $explanation = $this->api_client->extract_response_text($response);
        
        // Record the usage
        $this->record_usage('explain', $prompt, $explanation);
        
        return $explanation;
    }
    
    /**
     * Get programming hint from AI
     *
     * @return string The AI hint
     */
    public function get_hint() {
        // Check if user can use this feature
        if (!$this->can_use('hint')) {
            throw new \moodle_exception('ailimitexceeded', 'mod_devcode');
        }
        
        // Get assignment description
        $description = strip_tags($this->devcode->intro);
        
        // Build the prompt
        $prompt = "Sinh viên đang làm bài tập: \n";
        $prompt .= $description . "\n\n";
        $prompt .= "với mã nguồn: \n";
        $prompt .= $this->submission->code . "\n\n";
        
        // Add error context if submission failed
        if ($this->submission->status === 'failed' || $this->submission->status === 'error') {
            $error_message = $this->submission->feedback;
            // If feedback is empty, try getting error from test results
            if (empty($error_message)) {
                 global $DB;
                 $test_results = $DB->get_records('devcode_submission_results', array('submissionid' => $this->submission->id));
                 foreach ($test_results as $result) {
                     if (!empty($result->error_message)) {
                         $error_message = $result->error_message;
                         break; // Use the first error found
                     }
                 }
            }
            if (!empty($error_message)) {
                $prompt .= "Lần nộp bài gần nhất của họ gặp lỗi: \n" . $error_message . "\n\n";
            }
        }
        
        $prompt .= "Họ gặp khó khăn. Hãy đưa ra một gợi ý ngắn gọn để định hướng, không cung cấp lời giải trực tiếp.";
        
        // Send to API
        $response = $this->api_client->generate_content($prompt);
        $hint = $this->api_client->extract_response_text($response);
        
        // Record the usage
        $this->record_usage('hint', $prompt, $hint);
        
        return $hint;
    }
    
    /**
     * Get code improvement suggestions from AI
     *
     * @return string The AI improvement suggestions
     */
    public function get_improvement_suggestions() {
        // Check if user can use this feature
        if (!$this->can_use('improve')) {
            throw new \moodle_exception('ailimitexceeded', 'mod_devcode');
        }
        
        // Check if submission is valid for improvement
        if ($this->submission->status != 'graded' && $this->submission->status != '1') {
            throw new \moodle_exception('aiimproveonlyaccepted', 'mod_devcode');
        }
        
        // Build the prompt
        $prompt = "Sinh viên đã hoàn thành bài tập với mã nguồn: \n";
        $prompt .= $this->submission->code . "\n\n";
        $prompt .= "Hãy đề xuất 2-3 cách cải thiện code về hiệu quả, cấu trúc hoặc chuẩn code.";
        
        // Send to API
        $response = $this->api_client->generate_content($prompt);
        $suggestions = $this->api_client->extract_response_text($response);
        
        // Record the usage
        $this->record_usage('improve', $prompt, $suggestions);
        
        return $suggestions;
    }
} 