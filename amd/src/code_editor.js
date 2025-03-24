/**
 * JavaScript for code editor functionality
 * 
 * @module     mod_devcode/code_editor
 * @copyright  2024 Your Name <your@email.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery'], function($) {
    /**
     * Initialize code editor functionality
     */
    var init = function() {
        // Bắt sự kiện Tab trong textarea để thụt đầu dòng thay vì chuyển focus
        $('.code-editor').on('keydown', function(e) {
            if (e.keyCode === 9) { // Tab key
                e.preventDefault();
                
                // Thêm khoảng cách tab (4 dấu cách)
                var start = this.selectionStart;
                var end = this.selectionEnd;
                
                // Thay thế vị trí con trỏ bằng tab
                this.value = this.value.substring(0, start) + '    ' + this.value.substring(end);
                
                // Đặt lại vị trí con trỏ
                this.selectionStart = this.selectionEnd = start + 4;
            }
        });
        
        // Bắt sự kiện khi người dùng paste code
        $('.code-editor').on('paste', function() {
            // Sử dụng setTimeout để đảm bảo nội dung đã được dán vào
            setTimeout(function() {
                // Đảm bảo textarea tự động mở rộng với nội dung
                $('.code-editor').trigger('input');
            }, 100);
        });
        
        // Tự động điều chỉnh chiều cao của textarea dựa trên nội dung
        $('.code-editor').on('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });
        
        // Kích hoạt events lần đầu để điều chỉnh kích thước
        $('.code-editor').trigger('input');
    };

    return {
        init: init
    };
}); 