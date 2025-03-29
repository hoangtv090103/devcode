<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Submission form for DevCode
 *
 * @package     mod_devcode
 * @copyright   2024 Your Name <your@email.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
        global $DB, $USER;

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

        // Nút submit và cancel
        $this->add_action_buttons(true, get_string('submitcode', 'devcode'));

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
