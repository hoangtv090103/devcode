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
 * Gemini API client for DevCode AI functionality
 *
 * @package     mod_devcode
 * @copyright   2025 Your Name <your.email@example.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_devcode\ai;

defined('MOODLE_INTERNAL') || die();

use \curl;
use \moodle_exception;

/**
 * Class for handling communication with the Gemini API
 */
class api_client {
    
    /** @var string The base API URL */
    private $api_url = 'https://generativelanguage.googleapis.com/v1/models/gemini-1.5-pro:generateContent';
    
    /** @var string The Gemini API key */
    private $api_key;
    
    /**
     * Constructor - retrieves API key from Moodle config
     */
    public function __construct() {
        global $CFG;
        $this->api_key = \get_config('mod_devcode', 'gemini_api_key');
    }
    
    /**
     * Check if the API key is set
     *
     * @return bool Whether the API key is configured
     */
    public function is_configured() {
        return !empty($this->api_key);
    }
    
    /**
     * Send a prompt to Gemini API and get a response
     *
     * @param string $prompt The prompt to send to the API
     * @return object|false The API response or false on failure
     */
    public function generate_content($prompt) {
        if (!$this->is_configured()) {
            throw new moodle_exception('apikeymissing', 'mod_devcode');
        }
        
        // Prepare the request body
        $request_data = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.4,
                'maxOutputTokens' => 1024
            ]
        ];
        
        // Prepare the API URL with key
        $url = $this->api_url . '?key=' . $this->api_key;
        
        // Set up cURL request
        $curl = new curl();
        $curl->setHeader('Content-Type: application/json');
        $options = [
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_TIMEOUT' => 30
        ];
        
        // Execute the request
        $response = $curl->post($url, json_encode($request_data), $options);
        
        // Check for errors
        if ($curl->get_errno()) {
            throw new moodle_exception('apirequesterror', 'mod_devcode', '', $curl->error);
        }
        
        // Parse the response
        $result = json_decode($response);
        
        // Handle API error response
        if (isset($result->error)) {
            throw new moodle_exception('apiresponseerror', 'mod_devcode', '', 
                $result->error->message . ' (Code: ' . $result->error->code . ')');
        }
        
        return $result;
    }
    
    /**
     * Extract text from Gemini API response
     *
     * @param object $response The raw API response
     * @return string The extracted text
     */
    public function extract_response_text($response) {
        if (empty($response) || empty($response->candidates) || empty($response->candidates[0]->content)) {
            return \get_string('emptyresponse', 'mod_devcode');
        }
        
        $text = '';
        foreach ($response->candidates[0]->content->parts as $part) {
            if (isset($part->text)) {
                $text .= $part->text;
            }
        }
        
        return $text;
    }
} 