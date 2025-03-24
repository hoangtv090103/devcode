<?php
defined('MOODLE_INTERNAL') || die();

class mod_devcode_renderer extends plugin_renderer_base {
    /**
     * Renders the submission status
     *
     * @param string $status The submission status
     * @return string HTML to output.
     */
    public function submission_status($status) {
        $statusclass = 'status-' . $status;
        $statuslabel = get_string('status' . $status, 'mod_devcode');
        
        return html_writer::tag('span', $statuslabel, array('class' => $statusclass));
    }
    
    /**
     * Renders the code editor
     *
     * @param string $code The code to display
     * @return string HTML to output.
     */
    public function code_editor($code) {
        return html_writer::tag('pre', htmlspecialchars($code), array('class' => 'monospaced'));
    }
    
    /**
     * Renders the submission form
     *
     * @param moodleform $mform The form to render
     * @return string HTML to output.
     */
    public function submission_form($mform) {
        return $mform->render();
    }
    
    /**
     * Renders the grading form
     *
     * @param moodleform $mform The form to render
     * @return string HTML to output.
     */
    public function grading_form($mform) {
        return $mform->render();
    }
    
    /**
     * Renders the submissions table
     *
     * @param array $submissions Array of submission objects
     * @return string HTML to output.
     */
    public function submissions_table($submissions) {
        $table = new html_table();
        $table->head = array(
            get_string('student'),
            get_string('submissionstatus', 'mod_devcode'),
            get_string('grade'),
            get_string('submissiondate'),
            get_string('actions')
        );
        
        foreach ($submissions as $submission) {
            $actions = new action_menu();
            $actions->add(new action_menu_link(
                new moodle_url('/mod/devcode/grade.php', array('submissionid' => $submission->id)),
                new pix_icon('i/edit', 'Grade'),
                get_string('grade')
            ));
            
            $table->data[] = array(
                fullname($submission->user),
                $this->submission_status($submission->status),
                $submission->grade ? $submission->grade : '-',
                userdate($submission->timecreated),
                $this->render($actions)
            );
        }
        
        return html_writer::table($table);
    }
} 