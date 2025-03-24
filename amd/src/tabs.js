/**
 * JavaScript for submission form tabs
 * 
 * @module     mod_devcode/tabs
 * @copyright  2024 Your Name <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery'], function($) {
    /**
     * Initialize tab functionality
     */
    var init = function() {
        // Handle tab switching
        $('.tab-btn').on('click', function() {
            var targetTab = $(this).data('tab');
            
            // Update active tab button
            $('.tab-btn').removeClass('active');
            $(this).addClass('active');
            
            // Hide all tab panes and show the target one
            $('.tab-pane').removeClass('active');
            $('#' + targetTab).addClass('active');
            
            // Add/remove classes for conditional styling
            if (targetTab === 'code-tab') {
                $('.responsive-layout').addClass('code-tab-active').removeClass('file-tab-active');
            } else {
                $('.responsive-layout').addClass('file-tab-active').removeClass('code-tab-active');
            }
        });
        
        // Handle sidebar toggle
        $('#toggle-sidebar').on('click', function() {
            $('.responsive-layout').toggleClass('sidebar-visible');
            
            // Update toggle button text
            var $toggleText = $(this).find('.toggle-text');
            if ($('.responsive-layout').hasClass('sidebar-visible')) {
                $toggleText.text(M.util.get_string('hideintro', 'devcode'));
            } else {
                $toggleText.text(M.util.get_string('showintro', 'devcode'));
            }
        });
        
        // Set initial state - code tab is active by default
        $('.responsive-layout').addClass('code-tab-active');
    };

    return {
        init: init
    };
}); 