<?php

namespace mod_devcode\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/devcode/lib.php');
require_once($CFG->dirroot . '/mod/devcode/locallib.php');
require_once($CFG->dirroot . '/mod/devcode/plagiarismlib.php');
require_once($CFG->dirroot . '/mod/devcode/judge0_api.php'); // Include if grading logic is there
require_once($CFG->dirroot . '/mod/devcode/constants.php');
require_once($CFG->dirroot . '/lib/moodlelib.php');

/**
 * Task to process a single DevCode submission asynchronously.
 */
class process_single_submission_task extends \core\task\adhoc_task {

    /**
     * Execute the task.
     */
    public function execute() {
        global $DB, $CFG;

        mtrace('Starting single submission processing task...');

        $submissionid = $this->get_custom_data()['submissionid'] ?? 0;

        if (empty($submissionid)) {
            mtrace('Error: Submission ID not provided in task custom data.');
            return;
        }

        mtrace('Processing submission ID: ' . $submissionid);

        $submission = $DB->get_record('devcode_submissions', ['id' => $submissionid]);

        if (!$submission) {
            mtrace('Error: Submission not found: ' . $submissionid);
            return;
        }

        // Ensure the submission is in the correct state to be processed
        if ($submission->status !== DEVCODE_STATUS_SUBMITTED) {
            mtrace('Skipping submission ID ' . $submissionid . ': Status is ' . $submission->status . ', expected ' . DEVCODE_STATUS_SUBMITTED);
            return;
        }

        $devcode = $DB->get_record('devcode', ['id' => $submission->devcodeid], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('devcode', $devcode->id, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        // Update status to processing
        mtrace('Updating submission status to processing for ID: ' . $submissionid);
        $submission->status = DEVCODE_STATUS_PROCESSING;
        $submission->timemodified = time();
        $DB->update_record('devcode_submissions', $submission);

        // Extend time limit for processing in background task
        @set_time_limit(300); // 5 minutes, suppress error if fails
        raise_memory_limit(MEMORY_EXTRA);

        try {
            // 1. Plagiarism Check (if enabled)
            $plagiarism_detected = false;
            if (!empty($devcode->enable_plagiarism)) {
                mtrace('Starting plagiarism check for submission: ' . $submission->id);
                // Ensure plagiarismlib functions update the DB or return necessary info
                $plagiarism_detected = devcode_check_plagiarism($submission->id);
                // Reload submission data as plagiarism check might update status/feedback
                $submission = $DB->get_record('devcode_submissions', ['id' => $submissionid]);
                mtrace('Plagiarism check finished. Detected: ' . ($plagiarism_detected ? 'Yes' : 'No') . '. Status: ' . $submission->status);
            }

            // 2. Grading (if not flagged for plagiarism)
            // Check the status after plagiarism check again
            if ($submission->status !== 'plagiarism' && $submission->status !== 'plagiarism_detected') {
                mtrace('Proceeding with grading for submission: ' . $submission->id);
                $testcases = $DB->get_records('devcode_testcases', array('devcodeid' => $devcode->id), 'id ASC');
                $language_id = (int)$submission->language_id;

                // Call the main grading function (ensure it updates the DB)
                // Assuming devcode_grade_submission in lib.php handles everything
                $grading_success = devcode_grade_submission($submission, $testcases, $language_id, $devcode);

                if ($grading_success) {
                    mtrace('Grading successful for submission: ' . $submission->id);
                } else {
                    // The grading function should have updated the status to error internally
                    mtrace('Grading failed for submission: ' . $submission->id . '. Status should be updated by grading function.');
                     // Optionally, force an error status if grading function failed to update
                     $currentsub = $DB->get_record('devcode_submissions', ['id' => $submissionid]);
                     if ($currentsub->status == DEVCODE_STATUS_PROCESSING) { // Check if status is still processing
                         $currentsub->status = DEVCODE_STATUS_ERROR;
                         $currentsub->feedback = get_string('error_grading_failed', 'mod_devcode');
                         $currentsub->timemodified = time();
                         $DB->update_record('devcode_submissions', $currentsub);
                         mtrace('Forced status to error as grading function failed to update it.');
                     }
                }
            } else {
                 mtrace('Skipping grading due to plagiarism status for submission: ' . $submission->id);
            }

            mtrace('Processing finished for submission: ' . $submissionid);

        } catch (\Exception $e) {
            mtrace('Error processing submission ID ' . $submissionid . ': ' . $e->getMessage() . '\n' . $e->getTraceAsString());
            // Update submission status to error
            $submission = $DB->get_record('devcode_submissions', ['id' => $submissionid]); // Reload fresh record
            if ($submission) {
                 $submission->status = DEVCODE_STATUS_ERROR;
                 $submission->feedback = get_string('error_processing_failed', 'mod_devcode') . ': ' . $e->getMessage();
                 $submission->timemodified = time();
                 $DB->update_record('devcode_submissions', $submission);
                 // Also update gradebook in case of error?
                 // devcode_grade_item_update($devcode); // Might set grade to 0?
            }
        }
    }

    /**
     * Get a descriptive name for this task (shown in admin UI).
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_process_single_submission', 'mod_devcode');
    }
} 