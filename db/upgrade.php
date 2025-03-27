<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_devcode_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2025032205) {
        // Define table devcode_plagiarism
        $table = new xmldb_table('devcode_plagiarism');

        // Add fields
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('devcodeid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('submission1_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('submission2_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('similarity_score', XMLDB_TYPE_FLOAT, '5', null, XMLDB_NOTNULL, null, null);
        $table->add_field('flagged', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('reviewed', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('details', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);    
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Add keys
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('devcodeid', XMLDB_KEY_FOREIGN, ['devcodeid'], 'devcode', ['id']);
        $table->add_key('submission1_id', XMLDB_KEY_FOREIGN, ['submission1_id'], 'devcode_submissions', ['id']);
        $table->add_key('submission2_id', XMLDB_KEY_FOREIGN, ['submission2_id'], 'devcode_submissions', ['id']);

        // Add indexes
        $table->add_index('submissions_pair', XMLDB_INDEX_UNIQUE, ['submission1_id', 'submission2_id']);

        // Create the table if it doesn't exist
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table devcode_student_stats
        $table = new xmldb_table('devcode_student_stats');

        // Add fields
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('assignments_completed', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('total_assignments', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('average_score', XMLDB_TYPE_FLOAT, '5', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('total_attempts', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('last_submission_time', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Add keys
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);

        // Add indexes
        $table->add_index('courseid_userid', XMLDB_INDEX_UNIQUE, ['courseid', 'userid']);

        // Create the table if it doesn't exist
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Update version
        upgrade_mod_savepoint(true, 2025032205, 'devcode');
    }

    // Add the details field to devcode_plagiarism table if it doesn't exist
    if ($oldversion < 20250327009) {
        $table = new xmldb_table('devcode_plagiarism');
        
        // Define the field
        $field = new xmldb_field('details', XMLDB_TYPE_TEXT, 'medium', null, XMLDB_NOTNULL, null, null, 'reviewed');
        
        // Add the field if it doesn't exist
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        // Update version
        upgrade_mod_savepoint(true, 20250327009, 'devcode');
    }
    
    // Add the plagiarism_url field to devcode_submissions table
    if ($oldversion < 20250327011) {
        $table = new xmldb_table('devcode_submissions');
        
        // Define the field
        $field = new xmldb_field('plagiarism_url', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'total_tests');
        
        // Add the field if it doesn't exist
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        // Update version
        upgrade_mod_savepoint(true, 20250327011, 'devcode');
    }

    return true;
} 