/**
 * JavaScript for code editor functionality
 * 
 * @module     mod_devcode/code_editor
 */
define(['jquery', 'mod_devcode/code_editor_loader'], function($, EditorLoader) {
    
    /**
     * Initialize code editor functionality
     */
    var init = function() {
        const textareaElement = document.getElementById('id_code');
        
        // If the textarea doesn't exist, exit early
        if (!textareaElement) {
            return;
        }
        
        const runButton = document.getElementById('run-code-btn');
        const language = document.querySelector("input[name='language']").value;
        
        // Create a wrapper div for our editor
        const editorWrapperElement = document.createElement('div');
        editorWrapperElement.className = 'codemirror-wrapper';
        textareaElement.parentNode.insertBefore(editorWrapperElement, textareaElement);
        textareaElement.style.display = 'none';
        
        // Load the editor only when needed to avoid resource waste
        initializeEditor(textareaElement, editorWrapperElement, language, runButton);
    };
    
    /**
     * Initialize the CodeMirror editor with safe loading
     */
    const initializeEditor = async function(textareaElement, editorWrapperElement, language, runButton) {
        try {
            // Enable debug mode for troubleshooting
            EditorLoader.enableDebug();
            
            // Load CodeMirror and language support
            const CM = await EditorLoader.loadCodeMirror();
            const languageSupport = await EditorLoader.loadLanguageSupport(language);
            
            // Add styling 
            const style = document.createElement('style');
            style.textContent = `
                .codemirror-wrapper {
                    border: 1px solid #ced4da;
                    border-radius: 0.25rem;
                    height: 400px;
                    overflow: hidden;
                }
                .codemirror-wrapper .cm-editor {
                    height: 100%;
                }
                .codemirror-wrapper .cm-scroller {
                    font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
                    font-size: 14px;
                    line-height: 1.6;
                }
            `;
            document.head.appendChild(style);
            
            // Create editor with basic extensions
            const editor = new CM.EditorView({
                state: CM.EditorState.create({
                    doc: textareaElement.value,
                    extensions: [
                        // Basic functionality
                        CM.lineNumbers(),
                        CM.highlightActiveLine(),
                        CM.history(),
                        CM.bracketMatching(),
                        CM.indentOnInput(),
                        
                        // Language support if available
                        languageSupport,
                        
                        // Set up listeners to sync textarea content
                        CM.EditorView.updateListener.of(update => {
                            if (update.docChanged) {
                                textareaElement.value = update.state.doc.toString();
                                
                                // Trigger events for Moodle's form handling
                                const event = new Event('input', { bubbles: true });
                                textareaElement.dispatchEvent(event);
                            }
                        })
                    ]
                }),
                parent: editorWrapperElement
            });
            
            // Listen for tab switching event to focus the editor
            document.addEventListener('focus-editor', function() {
                editor.focus();
            });
            
            // Save content to textarea when form is submitted
            const form = textareaElement.closest('form');
            if (form) {
                form.addEventListener('submit', function() {
                    textareaElement.value = editor.state.doc.toString();
                });
            }
            
            // Handle run button click
            if (runButton) {
                runButton.addEventListener('click', function() {
                    // Update textarea with current content before running
                    textareaElement.value = editor.state.doc.toString();
                });
            }
            
            // Focus editor after a short delay
            setTimeout(() => editor.focus(), 100);
            
        } catch (error) {
            console.error("Failed to initialize CodeMirror editor:", error);
            
            // Fallback to basic textarea
            textareaElement.style.display = 'block';
            editorWrapperElement.style.display = 'none';
            
            // Show error message
            const errorMessage = document.createElement('div');
            errorMessage.className = 'alert alert-warning';
            errorMessage.textContent = 'Could not load the code editor. Using basic textarea instead.';
            textareaElement.parentNode.insertBefore(errorMessage, textareaElement);
            
            // Basic tab handling as fallback
            textareaElement.addEventListener('keydown', function(e) {
                if (e.key === 'Tab') {
                    e.preventDefault();
                    
                    // Insert tab at cursor position
                    const start = this.selectionStart;
                    const end = this.selectionEnd;
                    
                    this.value = this.value.substring(0, start) + '    ' + this.value.substring(end);
                    
                    // Set cursor position after the inserted tab
                    this.selectionStart = this.selectionEnd = start + 4;
                }
            });
        }
    };
    
    return {
        init: init
    };
}); 