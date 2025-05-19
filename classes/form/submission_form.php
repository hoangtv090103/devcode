<?php


/**
 * Submission form for DevCode
 *
 * @package     mod_devcode

 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/repository/lib.php'); // Required for FILE_INTERNAL constant

use \core\output\html_writer;

/**
 * Submission form class
 */
class mod_devcode_submission_form extends moodleform
{

    /**
     * Define the form
     */
    protected function definition()
    {
        global $DB, $USER, $CFG;

        $mform = $this->_form;
        $cmid = $this->_customdata['cmid'];
        $devcode = $this->_customdata['devcode'];
        $submission = $this->_customdata['submission'];

        // Lấy thông tin test cases hiển thị cho học viên
        $visible_testcases = $DB->get_records(
            'devcode_testcases',
            array('devcodeid' => $devcode->id, 'visible_to_student' => 1),
            'id ASC'
        );

        // Get test cases HTML for reuse
        $testcases_html = '';
        if (!empty($visible_testcases)) {
            $testcases_html .= '<h3 id="sidebar-test-cases">' . get_string('exampletestcases', 'devcode') . '</h3>';

            $testcases_html .= '<div class="sidebar-testcases">';
            $testcases_html .= html_writer::start_tag('table', array('class' => 'sidebar-testcases-table'));
            $testcases_html .= html_writer::start_tag('thead');
            $testcases_html .= html_writer::start_tag('tr');
            $testcases_html .= html_writer::tag('th', get_string('input', 'devcode'));
            $testcases_html .= html_writer::tag('th', get_string('expectedoutput', 'devcode'));
            $testcases_html .= html_writer::tag('th', get_string('points', 'devcode'));
            $testcases_html .= html_writer::end_tag('tr');
            $testcases_html .= html_writer::end_tag('thead');

            $testcases_html .= html_writer::start_tag('tbody');
            foreach ($visible_testcases as $testcase) {
                $testcases_html .= html_writer::start_tag('tr');
                $testcases_html .= html_writer::tag('td', s($testcase->input), array('class' => 'testcase-input'));
                $testcases_html .= html_writer::tag('td', s($testcase->output), array('class' => 'testcase-output'));
                $testcases_html .= html_writer::tag('td', $testcase->points, array('class' => 'testcase-points'));
                $testcases_html .= html_writer::end_tag('tr');
            }
            $testcases_html .= html_writer::end_tag('tbody');
            $testcases_html .= html_writer::end_tag('table');
            $testcases_html .= '</div>';
        }



        // Hiển thị các test case mẫu trước phần nộp bài
        if (!empty($visible_testcases)) {
            // Only show test cases in the file tab, not in the code tab where we have the sidebar
            $mform->addElement('html', '<div class="example-testcases-container file-tab-only">
                <h3>' . get_string('visibletestcases', 'devcode') . '</h3>
                <p>' . get_string('exampletestcasesintro', 'devcode') . '</p>
                <div class="example-testcases-table-wrapper">');

            $table = html_writer::start_tag('table', array('class' => 'example-testcases-table generaltable'));
            $table .= html_writer::start_tag('thead');
            $table .= html_writer::start_tag('tr');
            $table .= html_writer::tag('th', get_string('testcaseinput', 'devcode'));
            $table .= html_writer::tag('th', get_string('testcaseoutput', 'devcode'));
            $table .= html_writer::tag('th', get_string('testcasepoints', 'devcode'));
            $table .= html_writer::tag('th', get_string('testcasetimelimit', 'devcode'));
            $table .= html_writer::end_tag('tr');
            $table .= html_writer::end_tag('thead');

            $table .= html_writer::start_tag('tbody');
            foreach ($visible_testcases as $testcase) {
                $table .= html_writer::start_tag('tr');
                $table .= html_writer::tag('td', s($testcase->input), array('class' => 'testcase-input'));
                $table .= html_writer::tag('td', s($testcase->output), array('class' => 'testcase-output'));
                $table .= html_writer::tag('td', $testcase->points, array('class' => 'testcase-points'));
                $table .= html_writer::tag('td', $testcase->time_limit . ' ms', array('class' => 'testcase-timelimit'));
                $table .= html_writer::end_tag('tr');
            }
            $table .= html_writer::end_tag('tbody');
            $table .= html_writer::end_tag('table');

            $mform->addElement('html', $table . '</div></div>');
        }

        $mform->addElement('html', '<h2>' . get_string('submission', 'devcode') . '</h2>');
        $mform->addElement('html', '<hr class="devcode-assignment-divider">');

        // Tạo layout 2 panel
        $mform->addElement('html', '
        <div class="responsive-layout">
            <!-- Sidebar đề bài -->
            <div class="sidebar-intro" id="task-sidebar">
                <div class="sidebar-toggle-btn">
                    <button type="button" id="toggle-sidebar" aria-label="' . get_string('taskdescription', 'devcode') . '">
                        <i class="fa fa-book" aria-hidden="true"></i> <span class="toggle-text">' . get_string('hideintro', 'devcode') . '</span>
                    </button>
                </div>
                <div class="sidebar-content">
                    <div class="sidebar-section task-description-section">
                        <h3>' . get_string('description', 'devcode') . '</h3>
                        <div class="sidebar-intro-content task-description-content">' .
            (!empty($devcode->intro) ? format_text($devcode->intro, $devcode->introformat) : get_string('nodescription', 'devcode'))
            . '</div>
                    </div>
                    
                    <div class="sidebar-section test-cases-section">
                        ' . $testcases_html . '
                    </div>
                </div>
            </div>
            
            <!-- Code editor -->
            <div class="main-content">
                <div class="submission-tabs">
                    <div class="tab-navigation">
                        <button type="button" class="tab-btn active" data-tab="code-tab" id="code-tab-btn">' . get_string('codetab', 'devcode') . '</button>
                        <button type="button" class="tab-btn" data-tab="file-tab" id="file-tab-btn">' . get_string('filetab', 'devcode') . '</button>
                    </div>
                    <div class="tab-content">
                        <div id="code-tab" class="tab-pane active">
                            <div class="code-editor-container custom-editor-wrapper">
                                <div class="editor-with-line-numbers">
                                    <div id="line-numbers" class="line-numbers"></div>
                                    <div class="code-editor-content">');

        // Tùy chọn editor tùy theo ngôn ngữ lập trình
        $attributes = array(
            'rows' => '20',
            'cols' => '100',
            'class' => 'code-editor',
            'wrap' => 'off',
            'style' => 'font-family: monospace; tab-size: 4;'
        );

        // Thêm data-language để hỗ trợ syntax highlighting bằng JavaScript sau này
        $attributes['data-language'] = strtolower($devcode->language);

        // Create attributes string for the textarea
        $attr_string = '';
        foreach ($attributes as $key => $value) {
            $attr_string .= $key . '="' . s($value) . '" ';
        }

        // Lấy giá trị mặc định cho textarea
        $default_value = '';
        if ($submission && !empty($submission->code)) {
            $default_value = s($submission->code);
        } else {
            $default_value = s($this->get_default_code_template($devcode->language));
        }

        // Thay thế Moodle textarea bằng custom HTML textarea
        $mform->addElement('html', '<textarea id="id_code" name="code" ' . $attr_string . '>' . $default_value . '</textarea>');

        // Thiết lập kiểu dữ liệu
        $mform->setType('code', PARAM_RAW);

        // Thêm rule cho vấn đề validation - custom rule
        $mform->addElement('html', '<div id="id_error_code" class="error" style="display: none;">' . get_string('codeempty', 'devcode') . '</div>');

        // Thêm nút Run ở góc dưới bên phải của editor
        $mform->addElement('html', '<div class="run-button-container">
            <button type="button" id="run-code-btn" class="btn btn-primary run-code-btn">
                <i class="fa fa-play-circle"></i> ' . get_string('runcode', 'devcode') . '
            </button>
        </div>');

        // Đóng tab code và mở tab file
        $mform->addElement('html', '
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="file-tab" class="tab-pane">
                        <div class="file-upload-container">
                            <div class="file-upload-icon"><i class="fa fa-upload"></i></div>
                            <p class="file-upload-help">' . get_string('fileuploadhelp', 'devcode') . '</p>
                            <div class="file-upload-accepted-types">
                                ' . $this->get_accepted_file_extensions_html($devcode->language) . '
                            </div>
                            <div class="file-picker-container">');

        // Thêm trường upload file trong file-tab
        $filemanager_options = array(
            'maxbytes' => 1048576, // 1MB
            'maxfiles' => 1,
            'accepted_types' => $this->get_accepted_file_extensions($devcode->language),
            'subdirs' => 0
        );

        $mform->addElement('filepicker', 'sourcefile', get_string('sourcefile', 'devcode'), null, $filemanager_options);
        $mform->addElement('html', '
                            </div>
                        </div>
                    </div>
                    <div class="code-autosave-info">
                        <div id="autosave-status" class="autosave-status"></div>
                        <button type="button" id="restore-saved-code" class="btn btn-link restore-saved-code" style="display: none;">
                            ' . get_string('restoresavedcode', 'devcode') . '
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Tab switching
    const tabBtns = document.querySelectorAll(".tab-btn");
    const tabPanes = document.querySelectorAll(".tab-pane");
    const taskSidebar = document.getElementById("task-sidebar");
    const codeTabBtn = document.getElementById("code-tab-btn");
    const fileTabBtn = document.getElementById("file-tab-btn");
    const layout = document.querySelector(".responsive-layout");
    const codeEditor = document.getElementById("id_code");
    const lineNumbers = document.getElementById("line-numbers");
    const autosaveStatus = document.getElementById("autosave-status");
    const restoreSavedCodeBtn = document.getElementById("restore-saved-code");
    
    // Định danh duy nhất cho code của từng bài tập
    const cmid = document.querySelector("input[name=\'id\']").value;
    const storageName = "devcode_autosave_" + cmid;
    let savedCodeVersion = "";
    let lastSaveTime = null;
    let autosaveTimer = null;
    
    // Hiển thị sidebar mặc định cho tab code
    layout.classList.add("code-tab-active");
    
    // Khôi phục code đã lưu nếu có
    function initializeCodeEditor() {
        const savedData = localStorage.getItem(storageName);
        
        if (savedData) {
            try {
                const parsedData = JSON.parse(savedData);
                
                // Kiểm tra xem có dữ liệu hợp lệ trong localStorage không
                if (!(parsedData && parsedData.code && parsedData.timestamp)) {
                    return;
                }

                // Kiểm tra xem mã code hiện tại có nội dung không
                if (!codeEditor.value.trim() || codeEditor.value === getDefaultTemplate()) {
                    // Sử dụng code đã lưu nếu không có code hiện tại hoặc là code mẫu
                    codeEditor.value = parsedData.code;
                    showRestoredMessage(new Date(parsedData.timestamp));
                } else if (codeEditor.value !== parsedData.code) {
                    // Nếu code hiện tại khác với code đã lưu, hiển thị nút khôi phục
                    savedCodeVersion = parsedData.code;
                    lastSaveTime = new Date(parsedData.timestamp);
                    restoreSavedCodeBtn.style.display = "inline-block";
                    autosaveStatus.innerHTML = \'<i class="fa fa-info-circle"></i> \' + 
                                 \'' . get_string('localsavedversionexists', 'devcode') . '\';
                    autosaveStatus.className = "autosave-status autosave-exists";
                }
            } catch (e) {
                console.error("Error parsing saved code data", e);
            }
        }
    }
    
    // Hiển thị thông báo đã khôi phục mã code
    function showRestoredMessage(timestamp) {
        autosaveStatus.innerHTML = \'<i class="fa fa-check-circle"></i> \' + 
                                 \'' . get_string('restoredautosave', 'devcode') . ': \' + 
                                 formatTimeAgo(timestamp);
        autosaveStatus.className = "autosave-status autosave-restored";
    }
    
    // Hiển thị thông báo đã lưu mã code
    function showSavedMessage(timestamp) {
        autosaveStatus.innerHTML = \'<i class="fa fa-check-circle"></i> \' + 
                                 \'' . get_string('autosavedsuccessfully', 'devcode') . ': \' + 
                                 formatTimeAgo(timestamp);
        autosaveStatus.className = "autosave-status autosave-saved";
        
        // Ẩn thông báo sau 3 giây
        setTimeout(function() {
            autosaveStatus.className = "autosave-status";
        }, 3000);
    }
    
    // Format thời gian thành "x phút trước"
    function formatTimeAgo(timestamp) {
        const now = new Date();
        const diffInSeconds = Math.floor((now - timestamp) / 1000);
        
        if (diffInSeconds < 60) {
            return diffInSeconds + " ' . get_string('secondsago', 'devcode') . '";
        } else if (diffInSeconds < 3600) {
            return Math.floor(diffInSeconds / 60) + " ' . get_string('minutesago', 'devcode') . '";
        } else if (diffInSeconds < 86400) {
            return Math.floor(diffInSeconds / 3600) + " ' . get_string('hoursago', 'devcode') . '";
        } else {
            return Math.floor(diffInSeconds / 86400) + " ' . get_string('daysago', 'devcode') . '";
        }
    }
    
    // Lấy mẫu code mặc định
    function getDefaultTemplate() {
        // Đây là hàm cần update nếu logic template trong PHP thay đổi
        const language = document.querySelector("input[name=\'language\']").value.toLowerCase();
        
        if (language.includes("python")) {
            return "# Viết code Python của bạn ở đây\n\ndef main():\n    # Code chính của bạn\n    print(\"Hello, World!\")\n\nif __name__ == \"__main__\":\n    main()";
        } else if (language.includes("java")) {
            return "public class Solution {\n    public static void main(String[] args) {\n        // Viết code Java của bạn ở đây\n        System.out.println(\"Hello, World!\");\n    }\n}";
        } else if (language.includes("cpp") || language.includes("c++")) {
            return "#include <iostream>\n\nusing namespace std;\n\nint main() {\n    // Viết code C++ của bạn ở đây\n    cout << \"Hello, World!\" << endl;\n    return 0;\n}";
        } else if (language.includes("javascript") || language.includes("js")) {
            return "// Viết code JavaScript của bạn ở đây\n\nfunction main() {\n    console.log(\"Hello, World!\");\n}\n\nmain();";
        } else {
            return "// Viết code của bạn ở đây\n";
        }
    }
    
    // Lưu code vào localStorage
    function saveCodeToLocalStorage() {
        const code = codeEditor.value;
        const now = new Date();
        
        // Lưu code và thời gian lưu
        const dataToSave = {
            code: code,
            timestamp: now.toISOString()
        };
        
        localStorage.setItem(storageName, JSON.stringify(dataToSave));
        lastSaveTime = now;
        showSavedMessage(now);
    }
    
    // Khởi tạo chức năng tự động lưu
    function setupAutosave() {
        // Lưu code khi người dùng thay đổi nội dung sau 1 giây không nhập
        codeEditor.addEventListener("input", function() {
            // Xóa timer hiện tại nếu có
            if (autosaveTimer) {
                clearTimeout(autosaveTimer);
            }
            
            // Đặt timer mới để lưu sau 1 giây
            autosaveTimer = setTimeout(function() {
                saveCodeToLocalStorage();
            }, 1000);
        });
        
        // Lưu code khi người dùng rời khỏi trang
        window.addEventListener("beforeunload", function() {
            saveCodeToLocalStorage();
        });
        
        // Khôi phục phiên bản đã lưu
        restoreSavedCodeBtn.addEventListener("click", function() {
            if (savedCodeVersion) {
                codeEditor.value = savedCodeVersion;
                showRestoredMessage(lastSaveTime);
                restoreSavedCodeBtn.style.display = "none";
            }
        });
    }
    
    // Khởi tạo chức năng autosave
    initializeCodeEditor();
    setupAutosave();
    
    // Add click handlers to toggle tab visibility and make the file uploader visible
    tabBtns.forEach(btn => {
        btn.addEventListener("click", function() {
            const tabId = this.getAttribute("data-tab");
            
            // Remove active class from all buttons and panes
            tabBtns.forEach(b => b.classList.remove("active"));
            tabPanes.forEach(p => p.classList.remove("active"));
            
            // Add active class to current button and pane
            this.classList.add("active");
            document.getElementById(tabId).classList.add("active");
            
            // Update hidden field based on selected tab
            if (tabId === "code-tab") {
                document.querySelector("input[name=\'submission_method\']").value = "code";
                // Update layout classes for CSS styling
                layout.classList.add("code-tab-active");
                layout.classList.remove("file-tab-active");
                // Show code related elements
                if (autosaveStatus) autosaveStatus.style.display = "block";
                if (restoreSavedCodeBtn) restoreSavedCodeBtn.style.display = savedCodeVersion ? "inline-block" : "none";
            } else {
                document.querySelector("input[name=\'submission_method\']").value = "file";
                // Update layout classes for CSS styling
                layout.classList.remove("code-tab-active");
                layout.classList.add("file-tab-active");
                // Hide code related elements
                if (autosaveStatus) autosaveStatus.style.display = "none";
                if (restoreSavedCodeBtn) restoreSavedCodeBtn.style.display = "none";
            }
        });
    });
    
    // Toggle sidebar on small screens
    const toggleBtn = document.getElementById("toggle-sidebar");
    const sidebarContent = document.querySelector(".sidebar-content");
    const toggleText = document.querySelector(".toggle-text");
    
    if (toggleBtn) {
        toggleBtn.addEventListener("click", function() {
            layout.classList.toggle("sidebar-visible");
            if (layout.classList.contains("sidebar-visible")) {
                toggleText.textContent = "' . get_string('hideintro', 'devcode') . '";
            } else {
                toggleText.textContent = "' . get_string('showintro', 'devcode') . '";
            }
        });
    }
    
    // Initial state - show sidebar on desktop, hide on mobile
    function handleResize() {
        if (window.innerWidth < 768) {
            layout.classList.remove("sidebar-visible");
        } else if (codeTabBtn.classList.contains("active")) {
            layout.classList.add("sidebar-visible");
        }
    }
    
    // Initial call and listen for resize
    handleResize();
    window.addEventListener("resize", handleResize);
    
    // Update line numbers function
    function updateLineNumbers() {
        if (!codeEditor || !lineNumbers) return;
        
        // Clear the line numbers container
        lineNumbers.innerHTML = "";
        
        // Get the lines of code and count
        const lines = codeEditor.value.split("\\n");
        const lineCount = lines.length;
        
        // Add line numbers
        for (let i = 1; i <= lineCount; i++) {
            const lineNumber = document.createElement("div");
            lineNumber.textContent = i;
            lineNumbers.appendChild(lineNumber);
        }
        
        // Sync scroll position
        lineNumbers.scrollTop = codeEditor.scrollTop;
        
        // Update line numbers height to match the editor height
        lineNumbers.style.height = codeEditor.clientHeight + "px";
    }
    
    // Initialize line numbers on page load
    updateLineNumbers();
    
    // Update line numbers when typing or scrolling
    if (codeEditor) {
        codeEditor.addEventListener("input", updateLineNumbers);
        codeEditor.addEventListener("scroll", function() {
            if (lineNumbers) {
                lineNumbers.scrollTop = this.scrollTop;
            }
        });
        
        // Update line numbers when textarea is resized
        codeEditor.addEventListener("mouseup", updateLineNumbers);
        window.addEventListener("resize", updateLineNumbers);
        
        // Prevent the line numbers from being scrolled directly by the user
        if (lineNumbers) {
            lineNumbers.addEventListener("wheel", function(e) {
                e.preventDefault();
                // Forward the wheel event to the code editor
                codeEditor.scrollTop += e.deltaY;
            });
        }
    }
    
    // Xử lý sự kiện submit form
    const form = document.querySelector("form.mform");
    
    if (form) {
        form.addEventListener("submit", function(e) {
            // Get current active tab
            const activeTab = document.querySelector(".tab-btn.active").getAttribute("data-tab");
            
            if (activeTab === "code-tab") {
                // Validate code submission
            const codeEditor = document.getElementById("id_code");
            const errorElement = document.getElementById("id_error_code");
            
            if (codeEditor) {
                    // Kiểm tra nếu code rỗng thì ngăn form submit và hiển thị lỗi
                    if (codeEditor.value.trim() === "") {
                        e.preventDefault();
                        if (errorElement) {
                            errorElement.textContent = "' . get_string('codeempty', 'devcode') . '";
                            errorElement.style.display = "block";
                        }
                        return false;
                    } else if (errorElement) {
                        errorElement.style.display = "none";
                    }
                }
            } else if (activeTab === "file-tab") {
                // Validate file submission
                const fileInput = document.querySelector("input[name=\'sourcefile\']");
                if (!fileInput || fileInput.value === "") {
                    e.preventDefault();
                    alert("' . get_string('fileuploadrequired', 'devcode') . '");
                    return false;
                }
            }
            
            // Disable nút submit để tránh click nhiều lần
            const submitButton = form.querySelector("input[type=\'submit\']");
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.value = "' . get_string('processing', 'devcode') . '";
            }
        });
    }
});
</script>
');

        // Cần thêm các nút
        $buttonarray = array();
        $buttonarray[] = $mform->createElement('submit', 'submitbutton', get_string('submit', 'devcode'));
        $buttonarray[] = $mform->createElement('cancel');
        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);

        // Thêm container cho kết quả chạy thử
        $mform->addElement('html', '
        <div class="run-results-container" style="display: none;">
            <div class="run-results-header">
                <h3>' . get_string('runresult', 'devcode') . '</h3>
                <div class="run-results-actions">
                    <label class="debug-toggle">
                        <input type="checkbox" id="debug-mode-toggle"> ' . get_string('debug', 'devcode') . '
                    </label>
                    <button type="button" class="close-results-btn" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            </div>
            <div id="run-results-content"></div>
            <div id="debug-output" class="debug-output" style="display: none;"></div>
        </div>');

        // Thêm script xử lý nút Run
        $mform->addElement('html', '
        <script>
        document.addEventListener("DOMContentLoaded", function() {
            const runButton = document.getElementById("run-code-btn");
            const codeEditor = document.getElementById("id_code");
            const resultsContainer = document.querySelector(".run-results-container");
            const resultsContent = document.getElementById("run-results-content");
            const closeResultsBtn = document.querySelector(".close-results-btn");
            const debugToggle = document.getElementById("debug-mode-toggle");
            const debugOutput = document.getElementById("debug-output");
            
            // Debug mode state
            let debugMode = false;
            
            // Toggle debug mode when checkbox is clicked
            debugToggle.addEventListener("change", function() {
                debugMode = this.checked;
                debugOutput.style.display = debugMode ? "block" : "none";
                console.log("Debug mode:", debugMode);
            });
            
            // Lấy thông tin các test case từ trang
            function getVisibleTestCases() {
                const testcases = [];
                const testcaseRows = document.querySelectorAll(".sidebar-testcases-table tbody tr, .testcases-table tbody tr");
                
                testcaseRows.forEach((row, index) => {
                    const inputCell = row.querySelector(".testcase-input");
                    const outputCell = row.querySelector(".testcase-output");
                    const pointsCell = row.querySelector(".testcase-points");
                    
                    if (inputCell && outputCell) {
                        testcases.push({
                            id: index + 1,
                            input: inputCell.textContent.trim(),
                            expected_output: outputCell.textContent.trim(),
                            points: pointsCell ? parseFloat(pointsCell.textContent.trim()) : 1
                        });
                    }
                });
                
                console.log("Found testcases:", testcases);
                return testcases;
            }
            
            // Đóng kết quả chạy khi nhấn nút đóng
            closeResultsBtn.addEventListener("click", function() {
                resultsContainer.style.display = "none";
            });
            
            // Xử lý khi nhấn nút Run
            runButton.addEventListener("click", function() {
                const code = codeEditor.value;
                
                if (!code.trim()) {
                    alert("' . get_string('codeempty', 'devcode') . '");
                    return;
                }
                
                // Lấy các test case
                const testcases = getVisibleTestCases();
                
                if (testcases.length === 0) {
                    console.warn("No test cases found, running with empty input");
                    // Nếu không có test case, chạy với đầu vào trống
                    runCodeWithInput(code, "");
                    return;
                }
                
                // Hiển thị loading
                resultsContainer.style.display = "block";
                resultsContent.innerHTML = `
                    <div class="run-loading">
                        <div class="spinner-border text-primary" role="status">
                            <span class="sr-only">Loading...</span>
                        </div>
                        <p>' . get_string('runningcode', 'devcode') . '</p>
                    </div>
                `;
                
                // Chạy code với các test case
                runCodeWithTestCases(code, testcases);
            });
            
            // Hàm chạy code với input cụ thể
            function runCodeWithInput(code, input) {
                const formData = new FormData();
                formData.append("cmid", ' . $this->_customdata['cmid'] . ');
                formData.append("code", code);
                formData.append("input", input);
                
                // Add debug parameter if debug mode is enabled
                if (debugMode) {
                    formData.append("debug", 1);
                }
                
                console.log("Running code with input:", input.substring(0, 100) + (input.length > 100 ? "..." : ""));
                
                fetch("' . $CFG->wwwroot . '/mod/devcode/ajax/run_code.php", {
                    method: "POST",
                    body: formData,
                    credentials: "same-origin"
                })
                .then(response => response.json())
                .then(data => {
                    console.log("Run result:", data);
                    displaySingleResult(data, input);
                    
                    // Display debug information if available and debug mode is on
                    if (debugMode && data.debug) {
                        debugOutput.innerHTML = `<pre>${JSON.stringify(data.debug, null, 2)}</pre>`;
                        debugOutput.style.display = "block";
                    }
                })
                .catch(error => {
                    console.error("Error running code:", error);
                    resultsContent.innerHTML = `
                        <div class="alert alert-danger">
                            <strong>' . get_string('error', 'devcode') . ':</strong> ' . get_string('connectionerror', 'devcode') . '
                        </div>
                    `;
                });
            }
            
            // Hàm chạy code với nhiều test case
            function runCodeWithTestCases(code, testcases) {
                const results = [];
                let completedTests = 0;
                
                // Hiển thị bảng trạng thái cho các test case
                let tableHTML = `
                    <table class="generaltable run-testcases-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>' . get_string('input', 'devcode') . '</th>
                                <th>' . get_string('expectedoutput', 'devcode') . '</th>
                                <th>' . get_string('actualoutput', 'devcode') . '</th>
                                <th>' . get_string('status', 'devcode') . '</th>
                            </tr>
                        </thead>
                        <tbody>
                `;
                
                testcases.forEach((testcase, index) => {
                    tableHTML += `
                        <tr id="testcase-row-${index}">
                            <td>${index + 1}</td>
                            <td class="testcase-input">${testcase.input}</td>
                            <td class="testcase-output">${testcase.expected_output}</td>
                            <td class="testcase-actual" id="actual-${index}">
                                <div class="spinner-border spinner-border-sm" role="status">
                                    <span class="sr-only">Loading...</span>
                                </div>
                            </td>
                            <td class="testcase-status" id="status-${index}">
                                <span class="badge badge-secondary">Pending</span>
                            </td>
                        </tr>
                    `;
                });
                
                tableHTML += `
                        </tbody>
                    </table>
                    <div class="run-summary" id="run-summary"></div>
                `;
                
                resultsContent.innerHTML = tableHTML;
                
                // Clear debug output
                debugOutput.innerHTML = "";
                let allDebugData = [];
                
                // Chạy từng test case
                testcases.forEach((testcase, index) => {
                    const formData = new FormData();
                    formData.append("cmid", ' . $this->_customdata['cmid'] . ');
                    formData.append("code", code);
                    formData.append("input", testcase.input);
                    
                    // Add debug parameter if debug mode is enabled
                    if (debugMode) {
                        formData.append("debug", 1);
                    }
                    
                    console.log(`Running test case ${index+1} with input:`, testcase.input.substring(0, 50) + (testcase.input.length > 50 ? "..." : ""));
                    
                    fetch("' . $CFG->wwwroot . '/mod/devcode/ajax/run_code.php", {
                        method: "POST",
                        body: formData,
                        credentials: "same-origin"
                    })
                    .then(response => response.json())
                    .then(data => {
                        console.log(`Test case ${index+1} result:`, data);
                        
                        // Collect debug data if available
                        if (debugMode && data.debug) {
                            allDebugData.push({
                                testcase: index + 1,
                                debug: data.debug
                            });
                            
                            // Update debug output
                            debugOutput.innerHTML = `<pre>${JSON.stringify(allDebugData, null, 2)}</pre>`;
                            debugOutput.style.display = "block";
                        }
                        
                        // Cập nhật kết quả cho test case này
                        updateTestCaseResult(data, index, testcase);
                        
                        // Theo dõi số lượng test đã hoàn thành
                        completedTests++;
                        if (completedTests === testcases.length) {
                            // Tất cả các test case đã chạy xong
                            showSummary();
                        }
                    })
                    .catch(error => {
                        console.error(`Error in test case ${index+1}:`, error);
                        
                        // Cập nhật lỗi cho test case này
                        document.getElementById(`actual-${index}`).innerHTML = "Error: " + error.message;
                        document.getElementById(`status-${index}`).innerHTML = `
                            <span class="badge badge-danger">Error</span>
                        `;
                        
                        // Theo dõi số lượng test đã hoàn thành
                        completedTests++;
                        if (completedTests === testcases.length) {
                            // Tất cả các test case đã chạy xong
                            showSummary();
                        }
                    });
                });
            }
            
            // Cập nhật kết quả cho từng test case
            function updateTestCaseResult(data, index, testcase) {
                const actualCell = document.getElementById(`actual-${index}`);
                const statusCell = document.getElementById(`status-${index}`);
                const row = document.getElementById(`testcase-row-${index}`);
                
                if (data.status === "error") {
                    // Hiển thị lỗi
                    actualCell.innerHTML = `<span class="text-danger">${data.message}</span>`;
                    statusCell.innerHTML = `<span class="badge badge-danger">Error</span>`;
                    row.classList.add("test-error");
                } else {
                    // Hiển thị kết quả thực tế
                    const stdout = data.stdout || "";
                    actualCell.textContent = stdout.trim();
                    
                    // So sánh với kết quả mong đợi
                    const expected = testcase.expected_output.trim();
                    const actual = stdout.trim();
                    
                    if (expected === actual) {
                        // Kết quả đúng
                        statusCell.innerHTML = `<span class="badge badge-success">Pass</span>`;
                        row.classList.add("test-pass");
                    } else {
                        // Kết quả sai
                        statusCell.innerHTML = `<span class="badge badge-danger">Fail</span>`;
                        row.classList.add("test-fail");
                    }
                }
            }
            
            // Hiển thị tóm tắt kết quả
            function showSummary() {
                const passedTests = document.querySelectorAll(".test-pass").length;
                const totalTests = document.querySelectorAll(".run-testcases-table tbody tr").length;
                
                const summaryDiv = document.getElementById("run-summary");
                summaryDiv.innerHTML = `
                    <div class="alert ${passedTests === totalTests ? "alert-success" : "alert-warning"}">
                        <strong>' . get_string('summary', 'devcode') . ':</strong> 
                        ${passedTests} / ${totalTests} ' . get_string('testcasespassed', 'devcode') . '
                    </div>
                `;
            }
            
            // Hiển thị một kết quả duy nhất
            function displaySingleResult(data, input) {
                if (data.status === "error") {
                    resultsContent.innerHTML = `
                        <div class="alert alert-danger">
                            <strong>' . get_string('error', 'devcode') . ':</strong> ${data.message}
                        </div>
                    `;
                } else {
                    // Tạo tab UI để hiển thị output, stderr và thông tin khác
                    resultsContent.innerHTML = `
                        <div class="run-result-header">
                            <div class="run-result-info">
                                <span class="badge badge-info">
                                    <i class="fa fa-clock-o"></i> ${data.execution_time.toFixed(3)} s
                                </span>
                                <span class="badge badge-secondary">
                                    <i class="fa fa-memory"></i> ${Math.round(data.memory_used / 1024)} MB
                                </span>
                                <span class="badge ${data.status_id === 3 ? "badge-success" : "badge-warning"}">
                                    ${data.status_description}
                                </span>
                            </div>
                        </div>
                        <div class="run-result-tabs">
                            <ul class="nav nav-tabs" role="tablist">
                                <li class="nav-item">
                                    <a class="nav-link active" data-toggle="tab" href="#output-tab" role="tab">
                                        ' . get_string('output', 'devcode') . '
                                    </a>
                                </li>
                                <li class="nav-item ${!data.stderr ? "d-none" : ""}">
                                    <a class="nav-link" data-toggle="tab" href="#stderr-tab" role="tab">
                                        ' . get_string('stderr', 'devcode') . '
                                    </a>
                                </li>
                                <li class="nav-item ${!data.compile_output ? "d-none" : ""}">
                                    <a class="nav-link" data-toggle="tab" href="#compile-tab" role="tab">
                                        ' . get_string('compileoutput', 'devcode') . '
                                    </a>
                                </li>
                            </ul>
                            <div class="tab-content">
                                <div class="tab-pane fade show active" id="output-tab" role="tabpanel">
                                    <pre class="run-result-output">${data.stdout || "' . get_string('nooutput', 'devcode') . '"}</pre>
                                </div>
                                <div class="tab-pane fade" id="stderr-tab" role="tabpanel">
                                    <pre class="run-result-stderr">${data.stderr || "' . get_string('noerror', 'devcode') . '"}</pre>
                                </div>
                                <div class="tab-pane fade" id="compile-tab" role="tabpanel">
                                    <pre class="run-result-compile">${data.compile_output || "' . get_string('nocompileoutput', 'devcode') . '"}</pre>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    // Initialize Bootstrap tabs
                    const tabs = resultsContent.querySelectorAll(".nav-link");
                    tabs.forEach(tab => {
                        tab.addEventListener("click", function(e) {
                            e.preventDefault();
                            
                            // Hide all tab panes
                            const tabPanes = resultsContent.querySelectorAll(".tab-pane");
                            tabPanes.forEach(pane => {
                                pane.classList.remove("show", "active");
                            });
                            
                            // Remove active class from all tabs
                            tabs.forEach(t => {
                                t.classList.remove("active");
                            });
                            
                            // Activate clicked tab
                            this.classList.add("active");
                            
                            // Show corresponding tab pane
                            const target = this.getAttribute("href").substring(1);
                            const targetPane = resultsContent.querySelector("#" + target);
                            if (targetPane) {
                                targetPane.classList.add("show", "active");
                            }
                        });
                    });
                }
            }
        });
        </script>
        <style>
        .run-button-container {
            position: absolute;
            bottom: 15px;
            right: 15px;
            z-index: 100;
        }
        
        .run-code-btn {
            padding: 8px 16px;
            border-radius: 4px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
        }
        
        .run-code-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        
        .code-editor-container {
            position: relative;
            padding-bottom: 40px;
        }
        
        .run-results-container {
            margin-top: 20px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color: #f9f9f9;
            padding: 15px;
            position: relative;
        }
        
        .run-results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
        
        .run-results-header h3 {
            margin: 0;
            font-size: 1.2rem;
        }
        
        .run-results-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .debug-toggle {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.85rem;
            margin-right: 10px;
            cursor: pointer;
        }
        
        .debug-toggle input {
            margin: 0;
        }
        
        .debug-output {
            margin-top: 15px;
            padding: 15px;
            background-color: #f0f0f0;
            border-radius: 4px;
            border: 1px solid #ddd;
            max-height: 300px;
            overflow: auto;
        }
        
        .debug-output pre {
            margin: 0;
            font-size: 0.85rem;
            white-space: pre-wrap;
        }
        
        .close-results-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            line-height: 1;
            cursor: pointer;
            padding: 0;
            opacity: 0.5;
            transition: opacity 0.3s;
        }
        
        .close-results-btn:hover {
            opacity: 1;
        }
        
        .run-testcases-table {
            width: 100%;
            margin-bottom: 20px;
            border-collapse: collapse;
        }
        
        .run-testcases-table th, 
        .run-testcases-table td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        
        .run-testcases-table th {
            background-color: #f2f2f2;
            font-weight: 600;
        }
        
        .run-testcases-table .test-pass {
            background-color: rgba(40, 167, 69, 0.1);
        }
        
        .run-testcases-table .test-fail {
            background-color: rgba(220, 53, 69, 0.1);
        }
        
        .run-testcases-table .test-error {
            background-color: rgba(255, 193, 7, 0.1);
        }
        
        .testcase-input, .testcase-output, .testcase-actual {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .testcase-input:hover, .testcase-output:hover, .testcase-actual:hover {
            white-space: normal;
            overflow: visible;
            background-color: #fff;
            position: relative;
            z-index: 1;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        
        .badge {
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.85rem;
        }
        
        .badge-success {
            background-color: #28a745;
            color: #fff;
        }
        
        .badge-danger {
            background-color: #dc3545;
            color: #fff;
        }
        
        .badge-warning {
            background-color: #ffc107;
            color: #212529;
        }
        
        .badge-info {
            background-color: #17a2b8;
            color: #fff;
        }
        
        .badge-secondary {
            background-color: #6c757d;
            color: #fff;
        }
        
        .run-summary {
            margin-top: 20px;
        }
        
        .run-loading {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .spinner-border {
            display: inline-block;
            width: 2rem;
            height: 2rem;
            vertical-align: text-bottom;
            border: 0.25em solid currentColor;
            border-right-color: transparent;
            border-radius: 50%;
            animation: spinner-border .75s linear infinite;
        }
        
        .spinner-border-sm {
            width: 1rem;
            height: 1rem;
            border-width: 0.2em;
        }
        
        @keyframes spinner-border {
            to { transform: rotate(360deg); }
        }
        
        .run-result-tabs {
            margin-top: 10px;
        }
        
        .nav-tabs {
            border-bottom: 1px solid #dee2e6;
            display: flex;
            list-style: none;
            padding-left: 0;
            margin-bottom: 0;
        }
        
        .nav-item {
            margin-bottom: -1px;
        }
        
        .nav-link {
            border: 1px solid transparent;
            border-top-left-radius: 0.25rem;
            border-top-right-radius: 0.25rem;
            display: block;
            padding: 0.5rem 1rem;
            text-decoration: none;
            color: #495057;
        }
        
        .nav-link.active {
            color: #495057;
            background-color: #fff;
            border-color: #dee2e6 #dee2e6 #fff;
        }
        
        .tab-content {
            border: 1px solid #dee2e6;
            border-top: none;
            border-radius: 0 0 4px 4px;
            padding: 15px;
            background-color: #fff;
        }
        
        .tab-pane {
            display: none;
        }
        
        .tab-pane.show.active {
            display: block;
        }
        
        pre {
            margin: 0;
            white-space: pre-wrap;
            word-wrap: break-word;
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .d-none {
            display: none !important;
        }
        
        /* Fix any duplicate buttons */
        .nop-ma-nguon-container {
            display: none;
        }
        </style>
        ');

        // Thêm các trường ẩn cần thiết
        $mform->addElement('hidden', 'id', $cmid);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'action', 'submit');
        $mform->setType('action', PARAM_ALPHA);

        // Thêm trường ẩn cho ngôn ngữ
        $mform->addElement('hidden', 'language', $devcode->language);
        $mform->setType('language', PARAM_TEXT);

        // Thêm trường ẩn để theo dõi phương thức nộp bài (code hoặc file)
        $mform->addElement('hidden', 'submission_method', 'code');
        $mform->setType('submission_method', PARAM_ALPHA);
    }

    /**
     * Validation
     * 
     * @param array $data
     * @param array $files
     * @return array
     */
    function validation($data, $files)
    {
        $errors = parent::validation($data, $files);

        // Kiểm tra xem code có rỗng không
        if (isset($data['code']) && empty(trim($data['code']))) {
            $errors['code'] = get_string('codeempty', 'devcode');
        }

        return $errors;
    }

    /**
     * Tạo mẫu code mặc định dựa vào ngôn ngữ
     * 
     * @param string $language
     * @return string
     */
    private function get_default_code_template($language)
    {
        $language = strtolower($language);

        switch ($language) {
            case 'python':
            case 'python 3':
            case 'python 3.8.1':
                return "# Viết code Python của bạn ở đây\n\ndef main():\n    # Code chính của bạn\n    print(\"Hello, World!\")\n\nif __name__ == \"__main__\":\n    main()";

            case 'java':
                return "public class Solution {\n    public static void main(String[] args) {\n        // Viết code Java của bạn ở đây\n        System.out.println(\"Hello, World!\");\n    }\n}";

            case 'c++':
            case 'cpp':
                return "#include <iostream>\n\nusing namespace std;\n\nint main() {\n    // Viết code C++ của bạn ở đây\n    cout << \"Hello, World!\" << endl;\n    return 0;\n}";

            case 'javascript':
            case 'js':
                return "// Viết code JavaScript của bạn ở đây\n\nfunction main() {\n    console.log(\"Hello, World!\");\n}\n\nmain();";

            default:
                return "// Viết code của bạn ở đây\n";
        }
    }

    /**
     * Lấy danh sách các định dạng file được chấp nhận dựa vào ngôn ngữ
     * 
     * @param string $language
     * @return array
     */
    private function get_accepted_file_extensions($language)
    {
        $language = devcode_get_language_by_id($language);
        $language = strtolower($language);

        if (str_contains($language, 'python')) {
            return array('.py');
        } else if (str_contains($language, 'java')) {
            return array('.java');
        } else if (str_contains($language, 'c++')) {
            return array('.cpp', '.cc', '.cxx', '.c++', '.h', '.hpp');
        } else if (str_contains($language, 'c')) {
            return array('.c', '.h');
        } else if (str_contains($language, 'javascript')) {
            return array('.js');
        } else {
            return array('.py', '.java', '.cpp', '.c', '.js', '.cc', '.cxx', '.c++', '.h', '.hpp');
        }
    }

    /**
     * Tạo HTML hiển thị các định dạng file được chấp nhận
     * 
     * @param string $language
     * @return string
     */
    private function get_accepted_file_extensions_html($language)
    {
        $language = strtolower($language);
        $html = '<div class="accepted-file-types">';
        $html .= '<strong>' . get_string('acceptedfiletypes', 'devcode') . ':</strong> ';

        $extensions = $this->get_accepted_file_extensions($language);
        $extension_labels = array();

        foreach ($extensions as $ext) {
            $extension_labels[] = '<span class="file-extension">' . substr($ext, 1) . '</span>';
        }

        $html .= implode(', ', $extension_labels);
        $html .= '</div>';

        return $html;
    }
}
