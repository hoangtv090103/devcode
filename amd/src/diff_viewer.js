

/**
 * JavaScript for the diff viewer in plagiarism comparison
 *
 * @module     mod_devcode/diff_viewer
 * @copyright  2024 Your Name <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/templates'], function($, Templates) {
    
    /**
     * Initialize the diff viewer
     */
    var init = function() {
        // Check if we're on a comparison page
        if (!$('#diff-viewer').length) {
            return;
        }

        // Get the code from hidden fields
        var code1 = $('#code1').val();
        var code2 = $('#code2').val();

        // Simple diff comparison for highlighting
        var diffLines = compareCodes(code1, code2);
        
        // Render the diff view
        renderDiff(diffLines);
    };

    /**
     * Compare two code strings and return an array of diff lines
     * 
     * @param {string} code1 - First code
     * @param {string} code2 - Second code
     * @return {Array} Array of diff lines with metadata
     */
    var compareCodes = function(code1, code2) {
        var lines1 = code1.split('\n');
        var lines2 = code2.split('\n');
        var result = [];
        
        // Very simple line-by-line diff
        // For a real implementation, you'd want to use a proper diff algorithm
        var max = Math.max(lines1.length, lines2.length);
        
        for (var i = 0; i < max; i++) {
            var line1 = i < lines1.length ? lines1[i] : '';
            var line2 = i < lines2.length ? lines2[i] : '';
            
            if (line1 === line2) {
                // Lines are the same
                result.push({
                    type: 'same',
                    lineNumber1: i + 1,
                    lineNumber2: i + 1,
                    text: line1
                });
            } else {
                // Lines are different
                result.push({
                    type: 'removed',
                    lineNumber1: i + 1,
                    lineNumber2: null,
                    text: line1
                });
                
                result.push({
                    type: 'added',
                    lineNumber1: null,
                    lineNumber2: i + 1,
                    text: line2
                });
            }
        }
        
        return result;
    };
    
    /**
     * Render the diff view
     * 
     * @param {Array} diffLines - Array of diff lines
     */
    var renderDiff = function(diffLines) {
        var $container = $('#diff-viewer');
        var html = '<table class="diff-table">';
        html += '<thead><tr>';
        html += '<th class="line-number">Submission 1</th>';
        html += '<th class="line-number">Submission 2</th>';
        html += '<th class="line-content">Code</th>';
        html += '</tr></thead><tbody>';
        
        diffLines.forEach(function(line) {
            html += '<tr class="diff-line diff-' + line.type + '">';
            
            // Line numbers
            html += '<td class="line-number">' + (line.lineNumber1 || '') + '</td>';
            html += '<td class="line-number">' + (line.lineNumber2 || '') + '</td>';
            
            // Line content
            html += '<td class="line-content">';
            if (line.text === '') {
                html += '&nbsp;';
            } else {
                // Escape HTML
                var escapedText = line.text
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
                
                html += escapedText;
            }
            html += '</td>';
            
            html += '</tr>';
        });
        
        html += '</tbody></table>';
        
        $container.html(html);
        
        // Add CSS for the diff viewer
        $('head').append(`
            <style>
                .diff-table {
                    width: 100%;
                    border-collapse: collapse;
                    font-family: monospace;
                    margin-bottom: 20px;
                }
                .diff-table th, .diff-table td {
                    padding: 5px;
                    border: 1px solid #ddd;
                }
                .diff-table .line-number {
                    width: 50px;
                    text-align: right;
                    color: #666;
                    user-select: none;
                }
                .diff-table .line-content {
                    white-space: pre-wrap;
                    word-break: break-all;
                }
                .diff-same {
                    background-color: #f8f9fa;
                }
                .diff-added {
                    background-color: #e6ffed;
                }
                .diff-removed {
                    background-color: #ffeef0;
                }
            </style>
        `);
    };
    
    return {
        init: init
    };
}); 