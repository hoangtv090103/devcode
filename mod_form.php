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
        $mform->addElement(
            'advcheckbox',
            'enable_plagiarism',
            get_string('enableplagiarism', 'devcode'),
            get_string('enableplagiarismdesc', 'devcode'),
            array(),
            array(0, 1)
        );
        $mform->setDefault('enable_plagiarism', 0);

        // Similarity threshold
        $mform->addElement(
            'text',
            'similarity_threshold',
            get_string('similaritythreshold', 'devcode'),
            array('size' => 3)
        );
        $mform->setType('similarity_threshold', PARAM_INT);
        $mform->addRule('similarity_threshold', get_string('similaritythresholderror', 'devcode'), 'numeric', null, 'client');
        $mform->setDefault('similarity_threshold', 80);
        $mform->disabledIf('similarity_threshold', 'enable_plagiarism', 'neq', 1);
        $mform->addHelpButton('similarity_threshold', 'similaritythreshold', 'devcode');

        // Test cases section
        $mform->addElement('header', 'testcasessection', get_string('testcases', 'devcode'));

        // Export button for existing assignments
        if (isset($this->_instance) && $this->_instance) {
            $export_url = new moodle_url('/mod/devcode/export_testcases.php', array('id' => $this->_cm->id));
            $export_button = html_writer::link(
                $export_url,
                get_string('testcaseexport', 'devcode'),
                array('class' => 'btn btn-secondary', 'target' => '_blank')
            );
            $mform->addElement('html', '<div class="form-group row">
                <div class="col-md-3"></div>
                <div class="col-md-9">' . $export_button . '</div>
            </div>');
        }

        // File manager for uploading test case file
        $mform->addElement(
            'filemanager',
            'testcasefile',
            get_string('testcasefile', 'devcode'),
            null,
            array(
                'subdirs' => 0,
                'maxbytes' => $CFG->maxbytes,
                'maxfiles' => 1,
                'accepted_types' => array('.json', '.txt')
            )
        );
        $mform->addHelpButton('testcasefile', 'testcasefile', 'devcode');

        // Example format
        $mform->addElement('html', '<div class="form-group">
            <div class="col-md-3"></div>
            <div class="col-md-9">
                <small class="text-muted">' . get_string('testcaseuploadexample', 'devcode') . '</small><br>
                <small class="text-muted">' . get_string('testcaseuploadtip', 'devcode') . '</small><br>
                <small class="text-muted">' . get_string('testcasedefaults', 'devcode') . '</small>
            </div>
        </div>');

        // Add dynamic test cases repeater elements
        $testcaserepeat = 2; // Default number of test cases
        if (isset($this->_instance) && $this->_instance) {
            // If editing an existing instance, get the number of test cases
            $testcases = $DB->get_records('devcode_testcases', array('devcodeid' => $this->_instance));
            $testcaserepeat = count($testcases) > 0 ? count($testcases) : 5;
        }

        $repeatarray = array();

        // Tạo container cho từng test case
        $repeatarray[] = $mform->createElement('html', '<div class="testcase-container">');

        // Test case title with delete button
        $repeatarray[] = $mform->createElement('html', '<div class="testcase-header">
            <h4>' . get_string('testcase', 'devcode') . ' <span class="testcase-number"></span></h4>
        </div>');
        
        // Test case ID - hidden field
        $repeatarray[] = $mform->createElement(
            'hidden',
            'testcase_id',
            '0'
        );

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

        // Test case description
        $repeatarray[] = $mform->createElement(
            'textarea',
            'testcase_description',
            get_string('testcasedescription', 'devcode'),
            array('rows' => 3, 'cols' => 50)
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

        // Delete button
        $repeatarray[] = $mform->createElement('html', '<div class="testcase-delete-container">
            <div class="testcase-delete-label">' . get_string('delete', 'devcode') . '</div>
        ');
        
        $repeatarray[] = $mform->createElement(
            'checkbox',
            'testcase_delete', 
            '', 
            '<span class="testcase-delete-text">' . get_string('markfordelete', 'devcode') . '</span>'
        );
        
        $repeatarray[] = $mform->createElement('html', '</div>');

        // Add separator between test cases
        $repeatarray[] = $mform->createElement('html', '<hr>');

        // Đóng container
        $repeatarray[] = $mform->createElement('html', '</div>');


        // Set types for the fields
        $repeateloptions = array();
        $repeateloptions['testcase_input']['type'] = PARAM_RAW;
        $repeateloptions['testcase_output']['type'] = PARAM_RAW;
        $repeateloptions['testcase_points']['type'] = PARAM_FLOAT;
        $repeateloptions['testcase_time_limit']['type'] = PARAM_INT;
        $repeateloptions['testcase_visible']['type'] = PARAM_INT;
        $repeateloptions['testcase_id']['type'] = PARAM_INT;
        $repeateloptions['testcase_delete']['type'] = PARAM_BOOL;
        $repeateloptions['testcase_description']['type'] = PARAM_RAW;

        // Set default values
        // $repeateloptions['testcase_points']['default'] = 10.0; // Remove default
        // $repeateloptions['testcase_time_limit']['default'] = 3000; // Remove default
        // $repeateloptions['testcase_visible']['default'] = 0; // Remove default
        $repeateloptions['testcase_delete']['default'] = 0;

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

        // Add JavaScript for test case numbering
        global $PAGE;
        $js = "
        require(['jquery'], function($) {
            function updateTestCaseNumbers() {
                $('.testcase-container').each(function(index) {
                    $(this).find('.testcase-number').text(index + 1);
                });
            }
            
            // Handle checkbox change for deletion
            $(document).on('change', 'input[name^=\"testcase_delete\"]', function() {
                var checkbox = $(this);
                var container = checkbox.closest('.testcase-container');
                
                if (checkbox.is(':checked')) {
                    container.addClass('marked-for-deletion');
                    
                    // Add visual confirmation
                    if (!container.find('.deletion-marker').length) {
                        var marker = $('<div class=\"deletion-marker\"><div class=\"deletion-icon\"></div><div class=\"deletion-text\">" . get_string('markedfordelete', 'devcode') . "</div></div>');
                        container.prepend(marker);
                        
                        // Animate the marker
                        setTimeout(function() {
                            marker.addClass('visible');
                        }, 10);
                    }
                } else {
                    container.removeClass('marked-for-deletion');
                    container.find('.deletion-marker').removeClass('visible');
                    
                    // Remove the marker after animation
                    setTimeout(function() {
                        container.find('.deletion-marker').remove();
                    }, 300);
                }
            });
            
            $(document).ready(function() {
                console.log('Document ready - Testcase Manager initialized');
                updateTestCaseNumbers();
                
                // Re-initialize after adding new test case
                $('#fitem_id_testcase_add input[type=\"submit\"]').on('click', function() {
                    setTimeout(updateTestCaseNumbers, 100);
                });
                
                // Check if any testcases are already marked for deletion (e.g. after validation error)
                $('input[name^=\"testcase_delete\"]:checked').each(function() {
                    $(this).trigger('change');
                });
            });
        });";
        
        $PAGE->requires->js_amd_inline($js);
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
            // Prepare file manager for test case file uploads
            $draftitemid = file_get_submitted_draft_itemid('testcasefile');
            file_prepare_draft_area($draftitemid, $this->context->id, 'mod_devcode', 'testcasefile', 0,
                array('subdirs' => 0, 'maxbytes' => $this->course->maxbytes, 'maxfiles' => 1));
            $default_values['testcasefile'] = $draftitemid;
            
            // Load existing test cases
            $testcases = $DB->get_records('devcode_testcases', array('devcodeid' => $this->_instance), 'id ASC');
            $testcasecount = 0;

            foreach ($testcases as $testcase) {
                $default_values['testcase_input'][$testcasecount] = $testcase->input;
                $default_values['testcase_output'][$testcasecount] = $testcase->output;
                $default_values['testcase_points'][$testcasecount] = $testcase->points;
                $default_values['testcase_time_limit'][$testcasecount] = $testcase->time_limit;
                $default_values['testcase_visible'][$testcasecount] = $testcase->visible_to_student;
                $default_values['testcase_description'][$testcasecount] = $testcase->description;
                $default_values['testcase_id'][$testcasecount] = $testcase->id; // Store the ID for tracking
                $default_values['testcase_delete'][$testcasecount] = 0; // Not marked for deletion
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
        global $DB, $USER;
        $errors = parent::validation($data, $files);

        // Validate test cases for deletion
        if (isset($data['testcase_delete']) && is_array($data['testcase_delete'])) {
            foreach ($data['testcase_delete'] as $key => $value) {
                if ($value != 0 && $value != 1) {
                    $errors["testcase_delete[$key]"] = 'Invalid value for test case deletion field';
                }
            }
        }

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

        // Validate test case file (if uploaded)
        if (!empty($data['testcasefile'])) {
            $fs = get_file_storage();
            $usercontext = context_user::instance($USER->id);
            $draftfiles = $fs->get_area_files($usercontext->id, 'user', 'draft', $data['testcasefile'], 'id', false);
            
            if (!empty($draftfiles)) {
                $file = reset($draftfiles);
                $filename = $file->get_filename();
                $extension = pathinfo($filename, PATHINFO_EXTENSION);
                
                // Check file type
                if ($extension !== 'json' && $extension !== 'txt') {
                    $errors['testcasefile'] = get_string('invalidfiletype', 'devcode', $filename);
                } else {
                    // Check file content (for JSON validity)
                    $content = $file->get_content();
                    if (empty($content)) {
                        $errors['testcasefile'] = get_string('emptyfile', 'devcode');
                    } else if ($extension === 'json') {
                        // Validate JSON structure
                        $json_data = json_decode($content, true);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            $errors['testcasefile'] = get_string('testcasefileerror', 'devcode') . ': ' . json_last_error_msg();
                        } else if (!is_array($json_data)) {
                            $errors['testcasefile'] = get_string('testcasefileerror', 'devcode') . ': ' . get_string('testcasefileformatdesc', 'devcode');
                        }
                    }
                }
            }
        }

        return $errors;
    }
}
