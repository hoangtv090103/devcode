/**
 * JavaScript for managing test cases in the devcode module.
 *
 * @module     mod_devcode/testcase_manager
 * @copyright  2024 Your Name <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/str'], function($, Str) {
    
    /**
     * Initialize the testcase management functions
     */
    var init = function() {
        // Get required strings 
        var stringPromise = Str.get_string('confirmdeletetestcase', 'devcode');
        
        // Function to update test case numbers
        function updateTestCaseNumbers() {
            $('.testcase-container:visible').each(function(index) {
                $(this).find('.testcase-number').text(index + 1);
            });
        }
        
        // Handle test case deletion
        $(document).on('click', '.testcase-delete-btn', function(e) {
            e.preventDefault();
            console.log('Delete button clicked');
            
            var button = $(this);
            var testcaseContainer = button.closest('.testcase-container');
            var testcaseIdInput = testcaseContainer.find('input[name^="testcase_id"]');
            
            // Use the string promise to get the confirmation text
            stringPromise.then(function(confirmText) {
                if (!confirm(confirmText)) {
                    return;
                }
                
                if (testcaseIdInput.length > 0) {
                    var testcaseId = testcaseIdInput.val();
                    console.log('Testcase ID:', testcaseId);
                    
                    if (testcaseId && testcaseId !== '0') {
                        // Set the hidden field value to 1
                        var deleteField = $('input[name="testcase_delete_' + testcaseId + '"]');
                        if (deleteField.length) {
                            deleteField.val('1');
                            console.log('Marked testcase ID ' + testcaseId + ' for deletion: ' + 
                                'Field name=' + deleteField.attr('name') + ', Value=' + deleteField.val());
                        } else {
                            console.log('Hidden field for testcase ID ' + testcaseId + ' not found');
                            
                            // Create new hidden field if not found
                            var newField = $('<input>').attr({
                                type: 'hidden',
                                name: 'testcase_delete_' + testcaseId,
                                value: '1'
                            });
                            newField.appendTo('#testcase-deletion-fields');
                            console.log('Created new hidden field for testcase ID ' + testcaseId + ': ' + 
                                'Field name=' + newField.attr('name') + ', Value=' + newField.val());
                            
                            // Also add to the form
                            $('form').append(newField.clone());
                        }
                    }
                }
                
                // Hide the test case container
                testcaseContainer.hide();
                testcaseContainer.addClass('testcase-deleted');
                
                // Update numbering
                updateTestCaseNumbers();
            }).catch(function(error) {
                console.error('Error getting string:', error);
                // Fallback to hardcoded string if there's an error
                if (!confirm('Are you sure you want to delete this test case?')) {
                    return;
                }
                
                // Rest of the deletion logic (duplicate of above)
                if (testcaseIdInput.length > 0) {
                    var testcaseId = testcaseIdInput.val();
                    if (testcaseId && testcaseId !== '0') {
                        var deleteField = $('input[name="testcase_delete_' + testcaseId + '"]');
                        if (deleteField.length) {
                            deleteField.val('1');
                        } else {
                            var newField = $('<input>').attr({
                                type: 'hidden',
                                name: 'testcase_delete_' + testcaseId,
                                value: '1'
                            });
                            newField.appendTo('#testcase-deletion-fields');
                            $('form').append(newField.clone());
                        }
                    }
                }
                
                testcaseContainer.hide();
                testcaseContainer.addClass('testcase-deleted');
                updateTestCaseNumbers();
            });
        });
        
        // Ensure form includes all deletion fields before submit
        $('form').on('submit', function() {
            console.log('Form is being submitted');
            // Find all marked elements and ensure they're in the form
            $('#testcase-deletion-fields input').each(function() {
                var name = $(this).attr('name');
                var value = $(this).val();
                console.log('Processing deletion field: ' + name + '=' + value);
                
                // Check if the field already exists in the form
                var existing = $('form input[name="' + name + '"]');
                if (existing.length === 0) {
                    // Add it to the form
                    $('<input>').attr({
                        type: 'hidden',
                        name: name,
                        value: value
                    }).appendTo('form');
                    console.log('Added missing field to form: ' + name + '=' + value);
                }
            });
            return true;
        });
        
        // Initial setup
        $(document).ready(function() {
            console.log('Document ready - Testcase Manager initialized');
            updateTestCaseNumbers();
            
            // Re-initialize after adding new test case
            $('#fitem_id_testcase_add input[type="submit"]').on('click', function() {
                setTimeout(updateTestCaseNumbers, 100);
            });
        });
    };

    return {
        init: init
    };
}); 