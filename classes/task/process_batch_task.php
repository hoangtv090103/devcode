<?php


/**
 * An adhoc task for processing batches of submissions.
 *
 * @package    mod_devcode
 * @copyright  2024 Your Name <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_devcode\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/devcode/batch_process.php');

/**
 * Class process_batch_task
 *
 * Processes a batch of devcode submissions asynchronously
 */
class process_batch_task extends \core\task\adhoc_task {

    /**
     * Execute the task
     */
    public function execute() {
        global $DB;
        
        mtrace('Starting DevCode batch processing task');
        
        $data = $this->get_custom_data();
        
        if (empty($data) || !isset($data->devcodeid)) {
            mtrace('Error: No devcode ID provided for batch processing');
            return;
        }
        
        mtrace('Processing submissions for DevCode ID: ' . $data->devcodeid);
        
        // Get the DevCode instance
        $devcode = $DB->get_record('devcode', ['id' => $data->devcodeid]);
        if (!$devcode) {
            mtrace('Error: DevCode instance not found with ID: ' . $data->devcodeid);
            return;
        }
        
        // Query to get submissions to process
        $params = ['devcodeid' => $data->devcodeid, 'status' => 'submitted'];
        
        // Filter by user IDs if provided
        $user_sql = '';
        if (!empty($data->userids) && is_array($data->userids)) {
            list($usersql, $userparams) = $DB->get_in_or_equal($data->userids, SQL_PARAMS_NAMED);
            $user_sql = " AND userid $usersql";
            $params = array_merge($params, $userparams);
        }
        
        // Get submissions
        $submissions = $DB->get_records_select(
            'devcode_submissions', 
            "devcodeid = :devcodeid AND status = :status" . $user_sql,
            $params
        );
        
        if (empty($submissions)) {
            mtrace('No submissions found for processing');
            return;
        }
        
        mtrace('Found ' . count($submissions) . ' submissions to process');
        
        // Process submissions in chunks to avoid timeout
        $result = devcode_process_in_chunks($submissions, $devcode, 10);
        
        mtrace('Batch processing completed: ' . $result['processed'] . ' processed, ' . 
               $result['errors'] . ' errors');
    }
} 
 