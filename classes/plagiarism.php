<?php


/**
 * Plagiarism detection for DevCode
 *
 * @package     mod_devcode

 */

defined('MOODLE_INTERNAL') || die();

/**
 * Class for handling plagiarism detection in DevCode
 */
class mod_devcode_plagiarism {
    /** @var int The threshold percentage for similarity */
    protected $threshold;
    
    /** @var object The devcode instance */
    protected $devcode;

    /**
     * Constructor
     *
     * @param object $devcode The devcode instance
     */
    public function __construct($devcode) {
        $this->devcode = $devcode;
        $this->threshold = isset($devcode->similarity_threshold) ? (int)$devcode->similarity_threshold : 50;
    }

    /**
     * Check submission for plagiarism against other submissions
     *
     * @param int $submissionid The ID of the submission to check
     * @return array Array of results with similarity percentages
     */
    public function check_submission($submissionid) {
        global $DB;
        
        // Get the submission to check
        $submission = $DB->get_record('devcode_submissions', array('id' => $submissionid), '*', MUST_EXIST);
        $submissioncode = $this->normalize_code($submission->code);
        
        // Get all other submissions for this devcode assignment
        $params = array(
            'devcodeid' => $this->devcode->id,
            'id' => $submissionid  // Exclude the current submission
        );
        
        $othersubmissions = $DB->get_records_select(
            'devcode_submissions', 
            'devcodeid = :devcodeid AND id != :id',
            $params
        );
        
        $results = [];
        
        // Compare with each submission
        foreach ($othersubmissions as $other) {
            $othercode = $this->normalize_code($other->code);
            $similarity = $this->calculate_similarity($submissioncode, $othercode);
            
            // Only include results above threshold
            if ($similarity >= $this->threshold) {
                $results[] = array(
                    'submission_id' => $other->id,
                    'user_id' => $other->userid,
                    'similarity' => $similarity,
                    'timestamp' => $other->timemodified
                );
            }
        }
        
        // Sort by similarity (highest first)
        usort($results, function($a, $b) {
            return $b['similarity'] - $a['similarity'];
        });
        
        return $results;
    }
    
    /**
     * Normalize code for comparison (remove comments, whitespace, etc.)
     *
     * @param string $code The code to normalize
     * @return string Normalized code
     */
    protected function normalize_code($code) {
        // Remove comments
        $code = preg_replace('/(\/\/.*|\/\*[\s\S]*?\*\/|#.*)/', '', $code);
        
        // Remove whitespace and convert to lowercase
        $code = preg_replace('/\s+/', '', $code);
        $code = strtolower($code);
        
        return $code;
    }
    
    /**
     * Calculate similarity percentage between two code strings
     *
     * @param string $code1 First code string
     * @param string $code2 Second code string
     * @return int Similarity percentage (0-100)
     */
    protected function calculate_similarity($code1, $code2) {
        // Simple implementation using Levenshtein distance
        $maxLength = max(strlen($code1), strlen($code2));
        if ($maxLength == 0) {
            return 0;
        }
        
        $distance = levenshtein($code1, $code2);
        $similarity = 100 - (($distance / $maxLength) * 100);
        
        return round($similarity);
    }
    
    /**
     * Public wrapper to normalize code for external use
     *
     * @param string $code The code to normalize
     * @return string Normalized code
     */
    public function get_normalized_code($code) {
        return $this->normalize_code($code);
    }
    
    /**
     * Public wrapper to calculate similarity for external use
     *
     * @param string $code1 First code string
     * @param string $code2 Second code string
     * @return int Similarity percentage (0-100)
     */
    public function get_similarity($code1, $code2) {
        return $this->calculate_similarity($code1, $code2);
    }
    
    /**
     * Store plagiarism results
     *
     * @param int $submissionid The submission ID
     * @param array $results Plagiarism check results
     * @return bool Success
     */
    public function store_results($submissionid, $results) {
        global $DB;
        
        // Delete existing results
        $DB->delete_records('devcode_plagiarism', array('submission_id' => $submissionid));
        
        // Insert new results
        foreach ($results as $result) {
            $record = new stdClass();
            $record->submission_id = $submissionid;
            $record->compared_with = $result['submission_id'];
            $record->similarity = $result['similarity'];
            $record->details = isset($result['details']) ? $result['details'] : '';
            $record->timedetected = time();
            
            $DB->insert_record('devcode_plagiarism', $record);
        }
        
        return true;
    }
} 