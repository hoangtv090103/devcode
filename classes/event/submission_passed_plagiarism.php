<?php


/**
 * The submission_passed_plagiarism event class
 *
 * @package     mod_devcode
 * @copyright   2024 Your Name <your@email.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_devcode\event;

defined('MOODLE_INTERNAL') || die();

use moodle_url;

/**
 * The submission_passed_plagiarism event class
 *
 * @package     mod_devcode
 * @copyright   2024 Your Name <your@email.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submission_passed_plagiarism extends \core\event\base {

    /**
     * Init method.
     */
    protected function init() {
        $this->data['crud'] = 'u'; // update
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'devcode_submissions';
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventsubmissionpassedplagiarism', 'mod_devcode');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' marked the submission with id '$this->objectid' as not plagiarized ".
               "in the devcode assignment with id '{$this->other['devcodeid']}'.";
    }

    /**
     * Get URL related to the action.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/devcode/plagiarism_report.php', array(
            'id' => $this->contextinstanceid,
            'sid' => $this->objectid
        ));
    }
} 