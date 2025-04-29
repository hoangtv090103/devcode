/**
 * JavaScript for code editor functionality
 * 
 * @module     mod_devcode/code_editor
 * @copyright  2024 Your Name <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery'], function($) {
    
    // Import CodeMirror packages using AMD dynamic imports
    const loadCodeMirror = async function() {
        // Basic packages
        const { EditorState, StateField, StateEffect } = await import('https://cdn.jsdelivr.net/npm/@codemirror/state@6.3.3/+esm');
        const { EditorView, lineNumbers, highlightActiveLine, keymap } = await import('https://cdn.jsdelivr.net/npm/@codemirror/view@6.23.0/+esm');
        const { defaultKeymap, indentWithTab, history, historyKeymap } = await import('https://cdn.jsdelivr.net/npm/@codemirror/commands@6.3.0/+esm');
        const { indentOnInput, syntaxHighlighting, defaultHighlightStyle, bracketMatching, foldGutter } = await import('https://cdn.jsdelivr.net/npm/@codemirror/language@6.9.3/+esm');
        const { searchKeymap } = await import('https://cdn.jsdelivr.net/npm/@codemirror/search@6.5.5/+esm');
        const { closeBrackets, closeBracketsKeymap } = await import('https://cdn.jsdelivr.net/npm/@codemirror/autocomplete@6.11.1/+esm');
        const { lintKeymap } = await import('https://cdn.jsdelivr.net/npm/@codemirror/lint@6.4.2/+esm');
        
        // Language packages based on language ID
        const getLanguageSupport = async function(language) {
            language = language.toLowerCase();
            
            if (language.includes('python')) {
                const { python } = await import('https://cdn.jsdelivr.net/npm/@codemirror/lang-python@6.1.3/+esm');
                return python();
            } else if (language.includes('java')) {
                const { java } = await import('https://cdn.jsdelivr.net/npm/@codemirror/lang-java@6.0.1/+esm');
                return java();
            } else if (language.includes('cpp') || language.includes('c++')) {
                const { cpp } = await import('https://cdn.jsdelivr.net/npm/@codemirror/lang-cpp@6.0.2/+esm');
                return cpp();
            } else if (language.includes('javascript') || language.includes('js')) {
                const { javascript } = await import('https://cdn.jsdelivr.net/npm/@codemirror/lang-javascript@6.2.1/+esm');
                return javascript();
            } else if (language.includes('c#')) {
                const { javascript } = await import('https://cdn.jsdelivr.net/npm/@codemirror/lang-javascript@6.2.1/+esm');
                return javascript(); // Fallback for C# since there's no direct support
            } else if (language.includes('c')) {
                const { c } = await import('https://cdn.jsdelivr.net/npm/@codemirror/lang-cpp@6.0.2/+esm');
                return c();
            } else {
                return null; // Fallback to no language support
            }
        };
        
        // Theme - One Dark
        const { oneDark } = await import('https://cdn.jsdelivr.net/npm/@codemirror/theme-one-dark@6.1.2/+esm');
        
        return {
            EditorState,
            EditorView,
            StateField,
            StateEffect,
            keymap,
            lineNumbers,
            highlightActiveLine,
            defaultKeymap,
            indentWithTab,
            history,
            historyKeymap,
            searchKeymap,
            closeBracketsKeymap,
            lintKeymap,
            indentOnInput,
            syntaxHighlighting,
            defaultHighlightStyle,
            bracketMatching,
            foldGutter,
            closeBrackets,
            oneDark,
            getLanguageSupport
        };
    };
    
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
        
        // Initialize CodeMirror editor
        loadCodeMirror().then(async (CM) => {
            // Get language support
            const languageSupport = await CM.getLanguageSupport(language);
            
            // Custom extension to sync textarea content with CodeMirror
            const syncTextarea = CM.StateEffect.define();
            
            const textareaSync = CM.StateField.define({
                create() {
                    return textareaElement.value;
                },
                update(value, tr) {
                    for (let effect of tr.effects) {
                        if (effect.is(syncTextarea)) {
                            return effect.value;
                        }
                    }
                    return value;
                }
            });
            
            // Create the editor with all our extensions
            const editor = new CM.EditorView({
                state: CM.EditorState.create({
                    doc: textareaElement.value,
                    extensions: [
                        // Basic functionality
                        CM.lineNumbers(),
                        CM.highlightActiveLine(),
                        CM.history(),
                        CM.bracketMatching(),
                        CM.foldGutter(),
                        CM.indentOnInput(),
                        CM.syntaxHighlighting(CM.defaultHighlightStyle),
                        CM.closeBrackets(),
                        
                        // Language support if available
                        languageSupport,
                        
                        // Keymaps for various functionality
                        CM.keymap.of([
                            ...CM.defaultKeymap,
                            ...CM.historyKeymap,
                            ...CM.searchKeymap,
                            ...CM.closeBracketsKeymap,
                            ...CM.lintKeymap,
                            CM.indentWithTab
                        ]),
                        
                        // Dark theme
                        CM.oneDark,
                        
                        // Update the hidden textarea on changes
                        textareaSync,
                        CM.EditorView.updateListener.of(update => {
                            if (update.docChanged) {
                                textareaElement.value = update.state.doc.toString();
                                
                                // Trigger localStorage save via original autosave mechanism
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
            
            // Listen for window resize events to update editor layout
            window.addEventListener('resize', function() {
                // Optional: Add specific handling for resize if needed
                // For example, updating the editor size based on new container dimensions
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
            
            // Add custom CSS to improve the editor appearance
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
                .codemirror-wrapper .cm-activeLine {
                    background-color: rgba(255, 255, 255, 0.07);
                }
                .codemirror-wrapper .cm-matchingBracket {
                    background-color: rgba(50, 150, 255, 0.3);
                    outline: 1px solid rgba(50, 150, 255, 0.6);
                }
                .codemirror-wrapper .cm-selectionMatch {
                    background-color: rgba(100, 100, 100, 0.3);
                }
                .codemirror-wrapper .cm-foldPlaceholder {
                    background-color: rgba(100, 100, 100, 0.3);
                    border: none;
                    color: #ddd;
                }
                .cm-focused {
                    outline: none !important;
                }
            `;
            document.head.appendChild(style);
            
            // Focus editor on initial load
            setTimeout(() => editor.focus(), 100);
            
        }).catch(error => {
            console.error("Failed to load CodeMirror:", error);
            // Fallback to basic textarea
            textareaElement.style.display = 'block';
            
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
        });
    };

    return {
        init: init
    };
}); 