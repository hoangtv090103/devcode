# Plugin Hoạt động Moodle: DevCode

## Tổng quan

DevCode là một module hoạt động của Moodle được thiết kế để tạo, nộp và chấm điểm tự động các bài tập lập trình. Nó cho phép giảng viên thiết lập các bài toán lập trình với các bộ dữ liệu kiểm thử (test cases) cụ thể và tận dụng các dịch vụ bên ngoài như Judge0 để thực thi và chấm điểm mã nguồn, và Dolos để phát hiện đạo văn.

## Tính năng

*   **Tạo Bài tập Lập trình:** Xác định mô tả bài tập, đặt hạn nộp và chọn ngôn ngữ lập trình.
*   **Quản lý Test Case:** Định nghĩa nhiều test case với đầu vào, đầu ra mong đợi, điểm, giới hạn thời gian thực thi và khả năng hiển thị (hiển thị cho sinh viên hoặc ẩn).
*   **Nộp bài:** Sinh viên có thể nộp bài giải của mình bằng cách gõ/dán mã trực tiếp vào trình soạn thảo hoặc bằng cách tải lên tệp mã nguồn.
*   **Chấm điểm Tự động:** Bài nộp được gửi đến một phiên bản Judge0 API đã được cấu hình để thực thi dựa trên các test case đã định nghĩa. Kết quả (Accepted, Wrong Answer, Time Limit Exceeded, Compilation Error, v.v.) được xử lý và điểm số được tính toán dựa trên các test case đã vượt qua và điểm được giao.
*   **Phát hiện Đạo văn (Tùy chọn):** Tích hợp với Dolos để so sánh các bài nộp với nhau và tạo báo cáo tương đồng. Giảng viên có thể xem xét các bài nộp có khả năng đạo văn.
*   **Kết quả Chi tiết:** Sinh viên và giảng viên có thể xem kết quả chi tiết, bao gồm trạng thái tổng thể, điểm số, phản hồi, đầu ra từ mỗi test case và liên kết đến báo cáo đạo văn (nếu có).
*   **Quản lý Bài nộp:** Giảng viên có thể xem tất cả các bài nộp cho một hoạt động, truy cập kết quả cá nhân và xem xét báo cáo đạo văn.

## Cài đặt

1.  **Điều kiện tiên quyết:**
    *   Một phiên bản Moodle đang chạy (Phiên bản X.Y trở lên - *Cập nhật với khả năng tương thích phiên bản Moodle của bạn*).
    *   Truy cập vào một phiên bản [Judge0 API](https://judge0.com/) đang chạy (tự host hoặc phiên bản đám mây). Bạn sẽ cần URL điểm cuối API và có thể cần khóa API.
    *   (Tùy chọn) Truy cập vào một phiên bản [Dolos](https://dolos.ugent.be/) đang chạy hoặc công cụ dòng lệnh Dolos được cài đặt trên máy chủ Moodle nếu bạn muốn sử dụng tính năng phát hiện đạo văn.
2.  **Tải về/Clone:** Đặt thư mục plugin `devcode` vào thư mục `/mod/` của cài đặt Moodle của bạn.
    ```bash
    cd /đường/dẫn/đến/moodle/htdocs/mod
    git clone <url-repo-plugin-cua-ban> devcode
    # HOẶC sao chép thư mục devcode vào đây
    ```
3.  **Cấu hình Khóa API:**
    *   Sao chép `config.example.php` thành `config.php` trong thư mục plugin.
    *   Chỉnh sửa `config.php` và thay thế các giá trị mẫu (`YOUR_JUDGE0_API_KEY_HERE` và `YOUR_DOLOS_API_KEY_HERE`) bằng khóa API thực tế của bạn.
    *   Lưu ý: `config.php` được loại trừ khỏi kiểm soát phiên bản để tránh làm lộ khóa API của bạn.
4.  **Nâng cấp Cơ sở dữ liệu Moodle:** Đăng nhập vào Moodle với tư cách quản trị viên. Đi tới `Quản trị trang` > `Thông báo`. Moodle sẽ phát hiện plugin mới và yêu cầu bạn nâng cấp cơ sở dữ liệu Moodle. Làm theo hướng dẫn trên màn hình.
5.  **Cấu hình Cài đặt Plugin:**
    *   Điều hướng đến `Quản trị trang` > `Plugin` > `Các module hoạt động` > `DevCode`.
    *   Nhập URL điểm cuối Judge0 API của bạn (và khóa API nếu cần).
    *   (Tùy chọn) Cấu hình đường dẫn đến tệp thực thi Dolos hoặc điểm cuối API nếu sử dụng tính năng phát hiện đạo văn.
    *   Lưu thay đổi.

## Cấu hình

### Cài đặt Chung (Quản trị viên)

Nằm dưới `Quản trị trang` > `Plugin` > `Các module hoạt động` > `DevCode`.

*   **Điểm cuối Judge0 API:** URL cơ sở của phiên bản Judge0 API của bạn (ví dụ: `http://localhost:2358`).
*   **Khóa Judge0 API:** (Tùy chọn) Nếu phiên bản Judge0 của bạn yêu cầu xác thực.
*   **Đường dẫn/Điểm cuối Dolos:** (Tùy chọn) Đường dẫn đến tệp thực thi Dolos hoặc điểm cuối API để kiểm tra đạo văn.

### Cài đặt Hoạt động (Giảng viên)

Khi thêm hoặc chỉnh sửa một hoạt động DevCode trong một khóa học:

*   **Tên & Mô tả:** Cài đặt hoạt động Moodle tiêu chuẩn.
*   **Ngôn ngữ:** Chọn ngôn ngữ lập trình được phép cho bài nộp.
*   **Hạn nộp:** Hạn chót tùy chọn cho bài nộp.
*   **Bật Phát hiện Đạo văn:** Hộp kiểm để bật kiểm tra Dolos cho hoạt động này.
*   **Ngưỡng Đạo văn:** (Nếu được bật) Tỷ lệ phần trăm tương đồng mà các bài nộp bị gắn cờ.
*   **Test Cases:** Thêm, sửa hoặc xóa các test case (Đầu vào, Đầu ra, Điểm, Giới hạn Thời gian, Khả năng hiển thị).

## Cấu trúc thư mục

Tổng quan ngắn gọn về các thư mục và tệp chính trong `mod/devcode/`:

```plaintext
.
├── amd/
│   └── src/            # Các module JavaScript (ví dụ: tương tác trình soạn thảo mã, các tab).
├── classes/
│   ├── form/           # Các định nghĩa Form (ví dụ: submission_form.php).
│   ├── event/          # Các định nghĩa Sự kiện cho hệ thống sự kiện của Moodle.
│   └── task/           # Các định nghĩa Tác vụ định kỳ.
│                       # Các lớp PHP cốt lõi khác...
├── db/
│   ├── install.xml     # Định nghĩa bảng cơ sở dữ liệu cho việc cài đặt.
│   ├── upgrade.php     # Xử lý thay đổi lược đồ cơ sở dữ liệu trong quá trình nâng cấp.
│   ├── events.php      # Định nghĩa các trình quan sát sự kiện.
│   └── services.php    # Định nghĩa các dịch vụ web.
├── includes/           # Các đoạn mã PHP hoặc tệp trợ giúp có thể tái sử dụng.
├── lang/
│   └── en/
│       └── mod_devcode.php # Chuỗi ngôn ngữ tiếng Anh.
│   # Thêm các gói ngôn ngữ khác (ví dụ: vi/) nếu cần.
├── pix/
│   └── icon.svg        # Biểu tượng plugin.
│   # Các hình ảnh khác...
├── templates/          # Các mẫu Moodle Mustache để hiển thị giao diện người dùng.
├── performance_tests/  # Các script hoặc định nghĩa để kiểm tra hiệu năng.
├── .git/               # Thư mục kiểm soát phiên bản Git (thường bị ẩn).
├── .DS_Store           # Tệp hệ thống macOS (nên bỏ qua).
├── README.md           # Tệp này: Tổng quan plugin, cài đặt, v.v.
├── apilib.php          # Logic tương tác API cơ sở (có thể có).
├── batch_process.php   # Logic cho xử lý nền/hàng loạt.
├── batch_processing.php# Logic cho các tác vụ xử lý nền/hàng loạt.
├── compare_submissions.php # So sánh hai bài nộp (xem đạo văn).
├── config.php          # Tải cấu hình cụ thể cho module (nếu có).
├── constants.php       # Định nghĩa các hằng số được sử dụng trong plugin.
├── create_assignments.php # Script để tạo/thiết lập bài tập hàng loạt.
├── dolos_lib.php       # Các hàm để tương tác cụ thể với Dolos.
├── export_testcases.php# Logic để xuất các test case.
├── gradelib.php        # Các hàm liên quan đến tích hợp Sổ điểm Moodle.
├── grades_util.php     # Các hàm tiện ích cho việc chấm điểm.
├── judge0_api.php      # Logic cốt lõi để tương tác với Judge0 API.
├── lib.php             # Tệp thư viện chính chứa các hàm plugin cốt lõi.
├── locallib.php        # Thư viện cục bộ cho các hàm dành riêng cho module.
├── mod_form.php        # Định nghĩa form chính để tạo/chỉnh sửa hoạt động.
├── plagiarism_action.php # Xử lý các hành động của giảng viên (gắn cờ/bỏ qua) trên báo cáo đạo văn.
├── plagiarism_report.php # Hiển thị danh sách các trường hợp đạo văn tiềm ẩn.
├── plagiarism_report_detail.php # Hiển thị so sánh chi tiết các bài nộp đạo văn.
├── plagiarismlib.php   # Logic cốt lõi để quản lý kiểm tra và kết quả đạo văn.
├── settings.php        # Định nghĩa trang cài đặt quản trị cho plugin.
├── styles.css          # Các kiểu CSS cho plugin.
├── submit.php          # Xử lý quy trình nộp bài của sinh viên.
├── submissions.php     # Chế độ xem của giảng viên để liệt kê tất cả các bài nộp.
├── version.php         # Chứa thông tin phiên bản plugin.
├── view.php            # Trang chính sinh viên thấy khi truy cập hoạt động.
└── view_result.php     # Hiển thị kết quả chấm điểm chi tiết cho một bài nộp.
```

## Giấy phép

Plugin này được cấp phép theo [GNU GPL v3 hoặc mới hơn](https://www.gnu.org/copyleft/gpl.html).

## Đóng góp

(*Tùy chọn: Thêm hướng dẫn tại đây nếu bạn dự định để người khác đóng góp.*)
*   Tuân theo hướng dẫn viết mã của Moodle.
*   Đảm bảo mã vượt qua PHP Lint và Moodle Code Checker (`phpcs`).
*   Cung cấp thông điệp commit rõ ràng.
*   Gửi pull request đến kho lưu trữ.
