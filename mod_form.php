<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->dirroot . '/mod/devcode/lib.php');

/**
 * Module instance settings form
 */
class mod_devcode_mod_form extends moodleform_mod
{

    /**
     * Definition of the form
     */
    public function definition()
    {
        global $CFG, $DB;

        $mform = $this->_form;

        // General section
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Assignment name
        $mform->addElement('text', 'name', get_string('assignmentname', 'devcode'), array('size' => '64'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        // Description - Use standard intro elements instead of manual editor
        $this->standard_intro_elements(get_string('description', 'devcode'));

        // Programming language selection
        $languages = devcode_get_supported_languages();
        $mform->addElement('select', 'language', get_string('programminglanguage', 'devcode'), $languages);
        $mform->setDefault('language', '71');

        // Due date
        $mform->addElement('date_time_selector', 'duedate', get_string('duedate', 'devcode'), array('optional' => true));

        // Submission settings
        $mform->addElement('header', 'submissionsettings', get_string('submissionsettings', 'devcode'));

        // Allow submissions from date
        $mform->addElement('date_time_selector', 'allowsubmissionsfromdate', get_string('allowsubmissionsfromdate', 'devcode'), array('optional' => true));

        // Plagiarism detection settings
        $mform->addElement('header', 'plagiarismsettings', get_string('plagiarismsettings', 'devcode'));
        
        // Enable plagiarism detection checkbox
        $mform->addElement('advcheckbox', 'enable_plagiarism', get_string('enableplagiarism', 'devcode'), 
            get_string('enableplagiarismdesc', 'devcode'), array(), array(0, 1));
        $mform->setDefault('enable_plagiarism', 0);
        
        // Similarity threshold
        $mform->addElement('text', 'similarity_threshold', get_string('similaritythreshold', 'devcode'), 
            array('size' => 3));
        $mform->setType('similarity_threshold', PARAM_INT);
        $mform->addRule('similarity_threshold', get_string('similaritythresholderror', 'devcode'), 'numeric', null, 'client');
        $mform->setDefault('similarity_threshold', 80);
        $mform->disabledIf('similarity_threshold', 'enable_plagiarism', 'neq', 1);
        $mform->addHelpButton('similarity_threshold', 'similaritythreshold', 'devcode');

        // Test cases section
        $mform->addElement('header', 'testcasessection', get_string('testcases', 'devcode'));

        // Add dynamic test cases repeater elements
        $testcaserepeat = 2; // Default number of test cases
        if (isset($this->_instance) && $this->_instance) {
            // If editing an existing instance, get the number of test cases
            $testcases = $DB->get_records('devcode_testcases', array('devcodeid' => $this->_instance));
            $testcaserepeat = count($testcases) > 0 ? count($testcases) : 5;
        }

        $repeatarray = array();

        // Test case input
        $repeatarray[] = $mform->createElement(
            'textarea',
            'testcase_input',
            get_string('testcaseinput', 'devcode'),
            array('rows' => 3, 'cols' => 50)
        );

        // Test case output
        $repeatarray[] = $mform->createElement(
            'textarea',
            'testcase_output',
            get_string('testcaseoutput', 'devcode'),
            array('rows' => 3, 'cols' => 50)
        );

        // Test case points
        $repeatarray[] = $mform->createElement(
            'text',
            'testcase_points',
            get_string('testcasepoints', 'devcode'),
            array('size' => 5)
        );

        // Time limit (ms)
        $repeatarray[] = $mform->createElement(
            'text',
            'testcase_time_limit',
            get_string('testcasetimelimit', 'devcode'),
            array('size' => 5)
        );

        // Visible to student
        $repeatarray[] = $mform->createElement(
            'advcheckbox',
            'testcase_visible',
            get_string('visibletostudent', 'devcode'),
            '',
            array('group' => 1),
            array(0, 1)
        );

        // Add separator between test cases
        $repeatarray[] = $mform->createElement('html', '<hr>');

        // Set types for the fields
        $repeateloptions = array();
        $repeateloptions['testcase_input']['type'] = PARAM_RAW;
        $repeateloptions['testcase_output']['type'] = PARAM_RAW;
        $repeateloptions['testcase_points']['type'] = PARAM_FLOAT;
        $repeateloptions['testcase_time_limit']['type'] = PARAM_INT;
        $repeateloptions['testcase_visible']['type'] = PARAM_INT;

        // Set default values
        $repeateloptions['testcase_points']['default'] = 10.0;
        $repeateloptions['testcase_time_limit']['default'] = 3000;
        $repeateloptions['testcase_visible']['default'] = 0;

        $this->repeat_elements(
            $repeatarray,
            $testcaserepeat,
            $repeateloptions,
            'testcase_repeats',
            'testcase_add',
            1,
            get_string('addmoretestcases', 'devcode'),
            true
        );

        // Add standard elements
        $this->standard_grading_coursemodule_elements();
        $this->standard_coursemodule_elements();

        // Add action buttons
        $this->add_action_buttons();
    }

    /**
     * Fill in data for existing instance
     *
     * @param array $default_values
     */
    public function data_preprocessing(&$default_values)
    {
        global $DB;

        if (isset($this->_instance) && $this->_instance) {
            // Load existing test cases
            $testcases = $DB->get_records('devcode_testcases', array('devcodeid' => $this->_instance), 'id ASC');
            $testcasecount = 0;

            foreach ($testcases as $testcase) {
                $default_values['testcase_input'][$testcasecount] = $testcase->input;
                $default_values['testcase_output'][$testcasecount] = $testcase->output;
                $default_values['testcase_points'][$testcasecount] = $testcase->points;
                $default_values['testcase_time_limit'][$testcasecount] = $testcase->time_limit;
                $default_values['testcase_visible'][$testcasecount] = $testcase->visible_to_student;
                $testcasecount++;
            }
            
            // Load plagiarism detection settings
            if (isset($default_values['enable_plagiarism'])) {
                // If enable_plagiarism is already set, don't override it
                // This happens when the form is submitted with validation errors
            } else {
                $devcode = $DB->get_record('devcode', array('id' => $this->_instance));
                if ($devcode) {
                    $default_values['enable_plagiarism'] = $devcode->enable_plagiarism;
                    $default_values['similarity_threshold'] = $devcode->similarity_threshold;
                }
            }
        }
    }

    /**
     * Validation of the form
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    function validation($data, $files)
    {
        $errors = parent::validation($data, $files);

        // Validate test case data
        if (isset($data['testcase_points'])) {
            foreach ($data['testcase_points'] as $key => $points) {
                if (!is_numeric($points) || $points <= 0) {
                    $errors["testcase_points[$key]"] = get_string('testcasepointserror', 'devcode');
                }
            }
        }

        if (isset($data['testcase_time_limit'])) {
            foreach ($data['testcase_time_limit'] as $key => $time_limit) {
                if (!is_numeric($time_limit) || $time_limit <= 0) {
                    $errors["testcase_time_limit[$key]"] = get_string('testcasetimelimiterror', 'devcode');
                }
            }
        }
        
        // Validate plagiarism settings
        if (!empty($data['enable_plagiarism']) && isset($data['similarity_threshold'])) {
            $threshold = $data['similarity_threshold'];
            if (!is_numeric($threshold) || $threshold < 1 || $threshold > 100) {
                $errors['similarity_threshold'] = get_string('similaritythresholderror', 'devcode');
            }
        }

        return $errors;
    }
}
