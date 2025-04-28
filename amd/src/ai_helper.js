/**
 * AI helper module for DevCode
 *
 * @module      mod_devcode/ai_helper
 * @copyright   2025 Your Name <your.email@example.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/ajax', 'core/notification', 'core/templates', 'core/str'], 
function($, Ajax, Notification, Templates, Str) {
    
    /**
     * Module initialization function
     */
    var init = function() {
        // Register event listeners for AI buttons
        $(document).on('click', '.devcode-ai-explain-btn', handleExplainRequest);
        $(document).on('click', '.devcode-ai-hint-btn', handleHintRequest);
        $(document).on('click', '.devcode-ai-improve-btn', handleImproveRequest);
    };
    
    /**
     * Handle error explanation request
     * 
     * @param {Event} event The click event
     */
    var handleExplainRequest = function(event) {
        event.preventDefault();
        
        var cmid = $(this).data('cmid');
        var sid = $(this).data('sid');
        var remaining = $(this).data('remaining');
        
        // Disable button during request
        var $button = $(this);
        $button.prop('disabled', true);
        
        // Show loading indicator
        $button.html('<i class="fa fa-spinner fa-spin"></i> ' + $button.text());
        
        // Send AJAX request
        requestAI('explain', cmid, sid)
            .then(function(response) {
                showAIResponse(response, 'explain');
                updateRemainingCounter($button, response.remaining.explain);
            })
            .catch(function(error) {
                Notification.exception(error);
            })
            .always(function() {
                // Re-enable button and remove spinner
                $button.prop('disabled', false);
                Str.get_string('aiexplain', 'devcode').then(function(text) {
                    $button.text(text);
                });
            });
    };
    
    /**
     * Handle hint request
     * 
     * @param {Event} event The click event
     */
    var handleHintRequest = function(event) {
        event.preventDefault();
        
        var cmid = $(this).data('cmid');
        var sid = $(this).data('sid');
        var remaining = $(this).data('remaining');
        
        // Disable button during request
        var $button = $(this);
        $button.prop('disabled', true);
        
        // Show loading indicator
        $button.html('<i class="fa fa-spinner fa-spin"></i> ' + $button.text());
        
        // Send AJAX request
        requestAI('hint', cmid, sid)
            .then(function(response) {
                showAIResponse(response, 'hint');
                updateRemainingCounter($button, response.remaining.hint);
            })
            .catch(function(error) {
                Notification.exception(error);
            })
            .always(function() {
                // Re-enable button and remove spinner
                $button.prop('disabled', false);
                Str.get_string('aihint', 'devcode').then(function(text) {
                    $button.text(text);
                });
            });
    };
    
    /**
     * Handle improvement request
     * 
     * @param {Event} event The click event
     */
    var handleImproveRequest = function(event) {
        event.preventDefault();
        
        var cmid = $(this).data('cmid');
        var sid = $(this).data('sid');
        var remaining = $(this).data('remaining');
        
        // Disable button during request
        var $button = $(this);
        $button.prop('disabled', true);
        
        // Show loading indicator
        $button.html('<i class="fa fa-spinner fa-spin"></i> ' + $button.text());
        
        // Send AJAX request
        requestAI('improve', cmid, sid)
            .then(function(response) {
                showAIResponse(response, 'improve');
                updateRemainingCounter($button, response.remaining.improve);
            })
            .catch(function(error) {
                Notification.exception(error);
            })
            .always(function() {
                // Re-enable button and remove spinner
                $button.prop('disabled', false);
                Str.get_string('aiimprove', 'devcode').then(function(text) {
                    $button.text(text);
                });
            });
    };
    
    /**
     * Send an AI request via AJAX
     * 
     * @param {string} action The action to perform (explain, hint, improve)
     * @param {number} cmid The course module ID
     * @param {number} sid The submission ID
     * @return {Promise} A promise that resolves with the AI response
     */
    var requestAI = function(action, cmid, sid) {
        return new Promise(function(resolve, reject) {
            $.ajax({
                url: M.cfg.wwwroot + '/mod/devcode/ajax/ai_service.php',
                type: 'POST',
                data: {
                    action: action,
                    cmid: cmid,
                    sid: sid,
                    sesskey: M.cfg.sesskey
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        resolve(response);
                    } else {
                        reject(new Error(response.message || 'Unknown error'));
                    }
                },
                error: function(xhr, status, error) {
                    reject(new Error('AJAX request failed: ' + error));
                }
            });
        });
    };
    
    /**
     * Show AI response in a modal
     * 
     * @param {object} response The AI response
     * @param {string} type The type of response (explain, hint, improve)
     */
    var showAIResponse = function(response, type) {
        // Define modal title based on type
        var title;
        switch (type) {
            case 'explain':
                title = M.util.get_string('aiexplanation', 'devcode');
                break;
            case 'hint':
                title = M.util.get_string('aihintresponse', 'devcode');
                break;
            case 'improve':
                title = M.util.get_string('aisuggestions', 'devcode');
                break;
            default:
                title = M.util.get_string('airesponse', 'devcode');
        }
        
        // Create modal content
        var content = '<div class="devcode-ai-response">';
        content += '<div class="devcode-ai-content">' + response.content.replace(/\n/g, '<br>') + '</div>';
        content += '<div class="devcode-ai-disclaimer">' + M.util.get_string('aidisclaimer', 'devcode') + '</div>';
        content += '</div>';
        
        // Create modal container if it doesn't exist
        if ($('#devcode-ai-modal').length === 0) {
            $('body').append(
                '<div id="devcode-ai-modal" class="modal fade" tabindex="-1" role="dialog">' +
                '<div class="modal-dialog modal-lg" role="document">' +
                '<div class="modal-content">' +
                '<div class="modal-header">' +
                '<h5 class="modal-title"></h5>' +
                '<button type="button" class="close" data-dismiss="modal" aria-label="Close">' +
                '<span aria-hidden="true">&times;</span>' +
                '</button>' +
                '</div>' +
                '<div class="modal-body"></div>' +
                '<div class="modal-footer">' +
                '<button type="button" class="btn btn-secondary" data-dismiss="modal">' + M.util.get_string('aiclose', 'devcode') + '</button>' +
                '</div>' +
                '</div>' +
                '</div>' +
                '</div>'
            );
        }
        
        // Update modal content and show it
        $('#devcode-ai-modal .modal-title').text(title);
        $('#devcode-ai-modal .modal-body').html(content);
        $('#devcode-ai-modal').modal('show');
    };
    
    /**
     * Update the remaining counter for a button
     * 
     * @param {jQuery} $button The button element
     * @param {number} remaining The number of uses remaining
     */
    var updateRemainingCounter = function($button, remaining) {
        var $counter = $button.siblings('.devcode-ai-counter');
        if ($counter.length === 0) {
            $counter = $('<span class="devcode-ai-counter ml-2 badge badge-info"></span>');
            $button.after($counter);
        }
        
        // Update counter text
        Str.get_string('airemaining', 'devcode', remaining).then(function(text) {
            $counter.text(text);
        });
        
        // Disable button if no uses remaining
        if (remaining <= 0) {
            $button.prop('disabled', true);
        }
    };
    
    return {
        init: init
    };
}); 