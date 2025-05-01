/**
 * JavaScript for loading CodeMirror editor in a way that avoids version conflicts
 * 
 * @module     mod_devcode/code_editor_loader
 * @copyright  2024 DevCode Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery'], function($) {

    // Track if CodeMirror is already loaded to avoid duplicate loading
    let codemirrorInstance = null;
    let debugMode = false;

    /**
     * Enable debug logging
     */
    const enableDebug = function() {
        debugMode = true;
    };

    /**
     * Debug log function
     */
    const debugLog = function(message, data) {
        if (debugMode) {
            console.log(`[CodeMirror Loader] ${message}`, data);
        }
    };

    /**
     * Load CodeMirror only once and cache the instance
     */
    const loadCodeMirror = async function() {
        if (codemirrorInstance) {
            debugLog('Using cached CodeMirror instance');
            return codemirrorInstance;
        }
        
        debugLog('Loading CodeMirror...');
        
        try {
            // Add CSP-compatible loading indicator
            const loadingIndicator = $('<div class="text-center p-3" id="code-editor-loading">' +
                                      '<span class="spinner-border text-primary" role="status"></span>' +
                                      '<p>Loading code editor...</p></div>');
            $('#id_code').before(loadingIndicator);
            
            // Use a single bundle approach to avoid instanceof check issues
            const script = document.createElement('script');
            script.type = 'module';
            script.textContent = `
                import * as CodeMirror from 'https://cdn.jsdelivr.net/npm/codemirror@6/dist/index.js';
                window.CodeMirrorBundle = CodeMirror;
            `;
            
            document.head.appendChild(script);
            
            // Wait for the bundle to be loaded
            let attempts = 0;
            while (!window.CodeMirrorBundle && attempts < 30) {
                await new Promise(resolve => setTimeout(resolve, 100));
                attempts++;
            }
            
            if (!window.CodeMirrorBundle) {
                throw new Error('Failed to load CodeMirror bundle after 3 seconds');
            }
            
            // Load languages directly using the same CodeMirror instance
            codemirrorInstance = window.CodeMirrorBundle;
            
            // Remove loading indicator
            $('#code-editor-loading').remove();
            
            debugLog('CodeMirror loaded successfully');
            return codemirrorInstance;
        } catch (error) {
            debugLog('Error loading CodeMirror', error);
            $('#code-editor-loading').html('<div class="alert alert-danger">' +
                                          'Failed to load code editor. Please refresh the page.' +
                                          '</div>');
            throw error;
        }
    };

    /**
     * Load language support for the specified language
     */
    const loadLanguageSupport = async function(language) {
        if (!language) {
            return null;
        }
        
        language = language.toLowerCase();
        debugLog('Loading language support for', language);
        
        try {
            if (language.includes('python')) {
                const module = await import('https://cdn.jsdelivr.net/npm/@codemirror/lang-python@6.1.3/+esm');
                return module.python();
            } else if (language.includes('java')) {
                const module = await import('https://cdn.jsdelivr.net/npm/@codemirror/lang-java@6.0.1/+esm');
                return module.java();
            } else if (language.includes('cpp') || language.includes('c++')) {
                const module = await import('https://cdn.jsdelivr.net/npm/@codemirror/lang-cpp@6.0.2/+esm');
                return module.cpp();
            } else if (language.includes('c') && !language.includes('c++') && !language.includes('c#')) {
                const module = await import('https://cdn.jsdelivr.net/npm/@codemirror/lang-cpp@6.0.2/+esm');
                return module.c();
            } else if (language.includes('javascript') || language.includes('js')) {
                const module = await import('https://cdn.jsdelivr.net/npm/@codemirror/lang-javascript@6.2.1/+esm');
                return module.javascript();
            }
        } catch (error) {
            debugLog('Error loading language support', error);
            // Return null instead of throwing to gracefully degrade
        }
        
        return null;
    };

    return {
        loadCodeMirror,
        loadLanguageSupport,
        enableDebug
    };
}); 