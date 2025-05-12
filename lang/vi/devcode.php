<?php
defined('MOODLE_INTERNAL') || die();

$string['modulename'] = 'Lập trình';
$string['modulenameplural'] = 'Lập trình';
$string['modulename_help'] = 'Mô-đun Devcode cho phép giảng viên tạo các bài tập lập trình được chấm điểm tự động.';
$string['pluginname'] = 'Devcode';
$string['pluginadministration'] = 'Lập trình';

// Chuỗi cho biểu mẫu
$string['assignmentname'] = 'Tên bài tập';
$string['description'] = 'Mô tả';
$string['programminglanguage'] = 'Ngôn ngữ lập trình';
$string['languagefixed'] = '(cố định cho bài tập này)';
$string['testcases'] = 'Bộ test';
$string['testcase'] = 'Test case';
$string['numtestcases'] = 'Số lượng test case';
$string['pointspartest'] = 'Điểm cho mỗi test case';
$string['duedate'] = 'Hạn nộp';
$string['submissionsettings'] = 'Cài đặt nộp bài';
$string['allowsubmissionsfromdate'] = 'Cho phép nộp bài từ';
$string['code'] = 'Mã nguồn';
$string['submit'] = 'Nộp mã nguồn';
$string['savegrade'] = 'Lưu điểm';

// Chuỗi phát hiện đạo văn
$string['plagiarismsettings'] = 'Cài đặt phát hiện đạo văn';
$string['enableplagiarism'] = 'Bật phát hiện đạo văn';
$string['enableplagiarismdesc'] = 'Kiểm tra sự tương đồng giữa các bài nộp của sinh viên';
$string['similaritythreshold'] = 'Ngưỡng tương đồng (%)';
$string['similaritythreshold_help'] = 'Đặt phần trăm ngưỡng cho sự tương đồng của mã nguồn. Bài nộp có mức tương đồng vượt quá ngưỡng này sẽ bị đánh dấu là có khả năng đạo văn.';
$string['similaritythresholderror'] = 'Ngưỡng phải là một số từ 1 đến 100';
$string['plagiarismreport'] = 'Báo cáo đạo văn';
$string['similarityscore'] = 'Mức độ tương đồng';
$string['flaggedsubmissions'] = 'Bài nộp bị đánh dấu';
$string['comparesubmissions'] = 'So sánh bài nộp';
$string['codecomparison'] = 'So sánh mã nguồn';
$string['codecomparisoninfo'] = 'Chế độ xem này làm nổi bật các điểm tương đồng giữa hai bài nộp.';
$string['backtoplagiarismreport'] = 'Quay lại báo cáo đạo văn';
$string['plagiarismdetailreport'] = 'Báo cáo chi tiết đạo văn';
$string['submissiondetails'] = 'Chi tiết bài nộp';
$string['similarsubmissions'] = 'Bài nộp tương tự';
$string['nosimilarsubmissionsfound'] = 'Không tìm thấy bài nộp tương tự.';
$string['viewsourcecode'] = 'Xem mã nguồn';
$string['teachernotes'] = 'Ghi chú của giáo viên';
$string['notes'] = 'Ghi chú';
$string['flagasplagiarism'] = 'Đánh dấu là đạo văn';
$string['markaspassed'] = 'Đánh dấu là hợp lệ';
$string['backtoplagiarismlist'] = 'Quay lại danh sách đạo văn';
$string['submissionflaggedasplagiarism'] = 'Bài nộp đã bị đánh dấu là đạo văn';
$string['submissionmarkedaspassed'] = 'Bài nộp đã được đánh dấu là hợp lệ';
$string['filterbyassignment'] = 'Lọc theo bài tập';
$string['allassignments'] = 'Tất cả bài tập';
$string['apply'] = 'Áp dụng';
$string['noplagiarismfound'] = 'Không phát hiện đạo văn';
$string['maxsimilarity'] = 'Mức tương đồng tối đa';
$string['matchescount'] = 'Số lượng trùng khớp';
$string['invalidaction'] = 'Hành động không hợp lệ.';
$string['invalidaccess'] = 'Truy cập không hợp lệ.';
$string['plagiarismnotenabled'] = 'Chức năng phát hiện đạo văn chưa được bật cho bài tập này';
$string['allplagiarismreports'] = 'Tất cả báo cáo đạo văn';
$string['noplagiarismdetected'] = 'Không phát hiện đạo văn.';
$string['assignment'] = 'Bài tập';
$string['submissionid'] = 'Mã bài nộp';

// Chuỗi giao diện xem bài
$string['submitassignment'] = 'Nộp bài';
$string['submitcode'] = 'Nộp mã nguồn';
$string['viewsubmissions'] = 'Xem các bài nộp';
$string['grading'] = 'Chấm điểm';
$string['submissionstatus'] = 'Trạng thái bài nộp';
$string['submissionhistory'] = 'Lịch sử nộp bài';
$string['yoursubmissionhistory'] = 'Lịch sử nộp bài của bạn';
$string['plagiarismdetected'] = 'Phát hiện đạo văn';
$string['testresults'] = 'Kết quả kiểm thử';
$string['feedback'] = 'Phản hồi';
$string['gradingsubmission'] = 'Chấm điểm bài nộp';
$string['submittedcode'] = 'Mã nguồn đã nộp';
$string['submissiondate'] = 'Ngày nộp';
$string['submissionnotallowed'] = 'Không được phép nộp bài vào thời điểm này';
$string['student'] = 'Sinh viên';

// Chuỗi trạng thái
$string['statusnotsubmitted'] = 'Chưa nộp';
$string['statussubmitted'] = 'Đã nộp';
$string['statusgraded'] = 'Đã chấm điểm';
$string['statusoverdue'] = 'Quá hạn';

// Chuỗi test case mới
$string['testcaseinput'] = 'Dữ liệu vào';
$string['testcaseoutput'] = 'Kết quả mong đợi';
$string['testcasepoints'] = 'Điểm';
$string['testcasetimelimit'] = 'Giới hạn thời gian (ms)';
$string['testcasedescription'] = 'Mô tả test case';
$string['visibletostudent'] = 'Hiển thị cho sinh viên';
$string['addmoretestcases'] = 'Thêm test case';
$string['testcasepointserror'] = 'Điểm phải là số dương';
$string['testcasetimelimiterror'] = 'Giới hạn thời gian phải là số dương';
$string['viewtestcases'] = 'Xem test case';
$string['visibletestcases'] = 'Test case ví dụ';
$string['hiddentestcases'] = 'Test case ẩn (chỉ giáo viên thấy)';
$string['notestcasesyet'] = 'Chưa có test case nào được thêm';
$string['exampletestcasesintro'] = 'Các test case ví dụ sau sẽ được dùng để kiểm thử mã nguồn của bạn:';
$string['cannotdeleteallcases'] = 'Bạn không thể xóa tất cả test case. Cần ít nhất một test case.';
$string['confirmdeletetestcase'] = 'Bạn có chắc chắn muốn xóa test case này không?';

// Chuỗi UI mới
$string['exampletestcases'] = 'Test case ví dụ';
$string['input'] = 'Dữ liệu vào';
$string['expectedoutput'] = 'Kết quả mong đợi';
$string['points'] = 'Điểm';

// Chuỗi nộp bài
$string['submissionform'] = 'Biểu mẫu nộp bài';
$string['yourcode'] = 'Mã nguồn của bạn';
$string['codeempty'] = 'Mã nguồn không được để trống';
$string['submissionsuccess'] = 'Bài nộp của bạn đã được lưu thành công';
$string['assignmentclosed'] = 'Bài tập đã đóng';
$string['editsubmission'] = 'Chỉnh sửa bài nộp';
$string['viewallsubmissions'] = 'Xem tất cả bài nộp';
$string['submissionstatus_processing'] = 'Đang xử lý';
$string['submissionstatus_graded'] = 'Đã chấm điểm';
$string['submissionstatus_error'] = 'Lỗi';
$string['submissionstatus_notsubmitted'] = 'Chưa nộp';
$string['submissionstatus_submitted'] = 'Đã nộp lúc {$a}';
$string['submissionstatus_overdue'] = 'Quá hạn';
$string['submissionstatus_notallowed'] = 'Không được phép nộp bài vào thời điểm này';
$string['submissionstatus_completed'] = 'Hoàn thành';
$string['submissionstatus_failed'] = 'Thất bại';
$string['submissionstatus_partial'] = 'Đúng một phần';
$string['submissionstatus_plagiarism'] = 'Có khả năng đạo văn';
$string['submissionstatus_plagiarism_detected'] = 'Có khả năng đạo văn';
$string['submissionsfor'] = 'Bài nộp cho {$a}';
$string['nostudentsyet'] = 'Chưa có sinh viên nào nộp bài';
$string['back'] = 'Quay lại';
$string['allsubmissions'] = 'Tất cả bài nộp';
$string['submissions'] = 'Bài nộp';
$string['grade'] = 'Điểm';
$string['timemodified'] = 'Chỉnh sửa lần cuối';
$string['submissionsmultiple'] = '{$a} bài nộp';
$string['failed_testcase'] = 'Test case #{$a} thất bại';
$string['execution_stopped'] = 'Dừng thực thi do lỗi ở test case #{$a}';
$string['compilation_error'] = 'Lỗi biên dịch';
$string['runtime_error'] = 'Lỗi khi chạy';
$string['time_limit_exceeded'] = 'Vượt quá giới hạn thời gian';
$string['memory_limit_exceeded'] = 'Vượt quá giới hạn bộ nhớ';
$string['wrong_answer'] = 'Kết quả sai';

// Chuỗi trạng thái DevCode cụ thể
$string['accepted'] = 'Đúng hoàn toàn';
$string['partially_accepted'] = 'Đúng một phần';
$string['partially_correct'] = 'Đúng một phần';
$string['time_limit'] = 'Vượt quá thời gian';
$string['memory_limit'] = 'Vượt quá bộ nhớ';
$string['compile_error'] = 'Lỗi biên dịch';
$string['pending'] = 'Đang chờ';

// Chuỗi trạng thái theo submission status_id
$string['submissionstatus_accepted'] = 'Đúng hoàn toàn';
$string['submissionstatus_wrong_answer'] = 'Kết quả sai';
$string['submissionstatus_time_limit'] = 'Vượt quá thời gian';
$string['submissionstatus_memory_limit'] = 'Vượt quá bộ nhớ';
$string['submissionstatus_compile_error'] = 'Lỗi biên dịch';
$string['submissionstatus_runtime_error'] = 'Lỗi khi chạy';
$string['submissionstatus_pending'] = 'Đang chờ';
$string['submissionstatus_processing'] = 'Đang xử lý';
$string['submissionstatus_partially_accepted'] = 'Đúng một phần';

// Chuỗi phản hồi Judge0
$string['allteststpassed'] = 'Tất cả test đều vượt qua!';
$string['someteststpassed'] = '{$a->passed} trên tổng số {$a->total} test đã vượt qua';
$string['noteststpassed'] = 'Không có test nào vượt qua';

// Chuỗi giao diện nộp bài mới
$string['submission'] = 'Bài nộp';
$string['codetab'] = 'Mã nguồn';
$string['filetab'] = 'Tệp';
$string['sourcefile'] = 'Tệp mã nguồn';
$string['fileuploadhelp'] = 'Tải lên tệp mã nguồn của bạn tại đây. Đảm bảo tệp chứa toàn bộ mã giải của bạn.';
$string['fileuploadrequired'] = 'Vui lòng tải lên tệp mã nguồn';
$string['acceptedfiletypes'] = 'Các loại tệp được chấp nhận';
$string['fileuploadinstructions'] = 'Tải lên tệp mã nguồn của bạn. Các loại tệp được chấp nhận: .py, .java, .cpp, .c, .js';
$string['filenotfound'] = 'Không tìm thấy tệp đã tải lên. Vui lòng thử lại.';
$string['emptyfile'] = 'Tệp đã tải lên bị rỗng. Vui lòng kiểm tra lại tệp và thử lại.';
$string['invalidfiletype'] = 'Loại tệp không hợp lệ cho {$a}. Vui lòng tải lên tệp có phần mở rộng đúng.';

// Chuỗi hiển thị kết quả
$string['gradingresults'] = 'Kết quả chấm điểm';
$string['pointsearned'] = 'Điểm đạt được';
$string['testcasespassed'] = 'Test case đã vượt qua';
$string['testcasestats'] = 'Thống kê test case';
$string['testcasespassrate'] = 'Tỷ lệ vượt qua';
$string['allpassed'] = 'Tất cả test case đã vượt qua!';
$string['executiontime'] = 'Thời gian thực thi';
$string['viewdetailedresults'] = 'Xem kết quả chi tiết';
$string['resubmit'] = 'Nộp lại';
$string['submissiontime'] = 'Thời gian nộp';
$string['status'] = 'Trạng thái';
$string['actions'] = 'Hành động';
$string['viewdetails'] = 'Xem chi tiết';

// Trang kết quả chi tiết
$string['submissionresults'] = 'Kết quả bài nộp';
$string['submissioninfo'] = 'Thông tin bài nộp';
$string['detailedresults'] = 'Kết quả kiểm thử chi tiết';
$string['youroutput'] = 'Kết quả của bạn';
$string['result'] = 'Kết quả';
$string['passed'] = 'Đúng';
$string['failed'] = 'Sai';
$string['errormessage'] = 'Thông báo lỗi';
$string['backtocourse'] = 'Quay lại bài tập';
$string['viewsubmission'] = 'Xem bài nộp';

// Chuỗi kết quả thực thi mới
$string['memoryused'] = 'Bộ nhớ đã dùng';
$string['resultaccepted'] = 'Đúng hoàn toàn';
$string['resultwronganswer'] = 'Kết quả sai';
$string['resultcompilationerror'] = 'Lỗi biên dịch';
$string['resulttimelimit'] = 'Vượt quá thời gian';
$string['resultmemorylimit'] = 'Vượt quá bộ nhớ';
$string['resultruntime'] = 'Lỗi khi chạy';
$string['expectedoutput'] = 'Kết quả mong đợi';
$string['actualoutput'] = 'Kết quả của bạn';
$string['compilationoutput'] = 'Kết quả biên dịch';
$string['runtimeerror'] = 'Lỗi khi chạy';

// Tính năng trình soạn thảo
$string['codehint'] = 'Viết mã nguồn của bạn tại đây...';
$string['codelanguage'] = 'Ngôn ngữ lập trình';
$string['autoindent'] = 'Tự động thụt lề';
$string['tabsize'] = 'Kích thước tab';
$string['linenumbers'] = 'Hiển thị số dòng';
$string['wordwrap'] = 'Tự động xuống dòng';
$string['syntaxhighlighting'] = 'Tô màu cú pháp';
$string['darkmode'] = 'Chế độ tối';
$string['editorpreferences'] = 'Tùy chỉnh trình soạn thảo';

// Thông báo API/Backend
$string['apierror'] = 'Lỗi khi kết nối với dịch vụ chấm điểm';
$string['retrying'] = 'Kết nối thất bại, đang thử lại...';
$string['maxretries'] = 'Đã thử lại tối đa, vui lòng thử lại sau';
$string['simulationmode'] = 'Đang chạy ở chế độ mô phỏng (không thực thi mã nguồn thực tế)';
$string['backenderror'] = 'Hệ thống chấm điểm báo lỗi';
$string['submissionqueued'] = 'Bài nộp của bạn đã được đưa vào hàng chờ chấm điểm';

// Trợ giúp và gợi ý
$string['firstsubmissionadvice'] = 'Hãy nộp bài sớm để nhận phản hồi về mã nguồn của bạn';
$string['testcaseadvice'] = 'Đảm bảo mã nguồn của bạn xử lý mọi trường hợp đầu vào';
$string['formattingadvice'] = 'Định dạng kết quả đầu ra chính xác như yêu cầu';
$string['timelimitadvice'] = 'Giải pháp của bạn cần chạy trong giới hạn thời gian';

// Chuỗi UI mới
$string['nodescription'] = 'Không có mô tả cho bài tập này.';
$string['jumptotestcases'] = 'Chuyển đến test case';
$string['codeeditor'] = 'Trình soạn thảo mã nguồn';
$string['sidebar'] = 'Mô tả bài tập';
$string['hideintro'] = 'Ẩn mô tả';
$string['showintro'] = 'Hiện mô tả';
$string['taskdescription'] = 'Mô tả bài tập';

// Chuỗi tự động lưu
$string['autosavedsuccessfully'] = 'Đã tự động lưu';
$string['restoredautosave'] = 'Đã khôi phục từ bản lưu tự động';
$string['restoresavedcode'] = 'Khôi phục phiên bản đã lưu';
$string['localsavedversionexists'] = 'Đã tồn tại một phiên bản đã lưu khác';
$string['secondsago'] = 'giây trước';
$string['minutesago'] = 'phút trước';
$string['hoursago'] = 'giờ trước';
$string['daysago'] = 'ngày trước';

$string['notfound'] = 'Không tìm thấy';

$string['mockresult'] = 'Kết quả mô phỏng';

// Chuỗi cho trạng thái bài nộp
$string['submitted'] = 'Đã nộp';
$string['graded'] = 'Đã chấm điểm';
$string['processing'] = 'Bài nộp của bạn đang được xử lý...';
$string['error'] = 'Lỗi';
$string['failed'] = 'Thất bại';
$string['plagiarism'] = 'Phát hiện đạo văn';
$string['plagiarism_detected'] = 'Có khả năng đạo văn (mức tương đồng: {$a}%)';
$string['plagiarism_detected_notification'] = 'Bài nộp của bạn đã bị đánh dấu có khả năng đạo văn. Giáo viên sẽ xem xét trước khi chấm điểm.';
$string['plagiarism_details'] = 'Xem chi tiết: {$a}';
$string['view_plagiarism_report'] = 'Xem báo cáo đạo văn';

// Hành động đạo văn
$string['graderror'] = 'Đã xảy ra lỗi khi chấm điểm bài nộp';
$string['eventsubmissionflaggedplagiarism'] = 'Bài nộp bị đánh dấu là đạo văn';
$string['eventsubmissionpassedplagiarism'] = 'Bài nộp vượt qua kiểm tra đạo văn';
$string['submissionprocesserror'] = 'Đã xảy ra lỗi khi xử lý bài nộp';
$string['submissionalreadyreviewed'] = 'Bài nộp này đã được xem xét và đánh dấu là "{$a}".';

// Chuỗi trạng thái bổ sung
$string['partial'] = 'Đúng một phần';
$string['completed'] = 'Hoàn thành';

$string['delete'] = 'Xóa';
$string['markfordelete'] = 'Đánh dấu để xóa';
$string['markedfordelete'] = 'Đã đánh dấu để xóa';
$string['deleteconfirm'] = 'Bạn có chắc chắn muốn xóa test case này không?';

$string['checking_plagiarism'] = 'Đang kiểm tra đạo văn...';

// Thông báo lỗi
$string['missingidparam'] = 'Thiếu tham số bắt buộc: {$a}';

$string['systemerror'] = 'Đã xảy ra lỗi hệ thống trong quá trình xử lý. Vui lòng thử lại sau hoặc liên hệ giáo viên nếu vấn đề vẫn tiếp diễn.';
$string['errordetailsstaff'] = 'Chi tiết kỹ thuật lỗi sau chỉ hiển thị cho nhân viên:';

// Cài đặt quản trị cho Judge0 và Dolos
$string['judge0_settings'] = 'Cài đặt Judge0 API';
$string['judge0_settings_desc'] = 'Cấu hình cho dịch vụ thực thi mã nguồn Judge0';
$string['judge0_api_url'] = 'Địa chỉ Judge0 API';
$string['judge0_api_url_desc'] = 'Địa chỉ URL của dịch vụ Judge0 API';
$string['judge0_api_key'] = 'Khóa Judge0 API';
$string['judge0_api_key_desc'] = 'API key để xác thực với dịch vụ Judge0';
$string['judge0_timeout'] = 'Thời gian chờ Judge0';
$string['judge0_timeout_desc'] = 'Thời gian tối đa (giây) chờ phản hồi từ Judge0 API';

$string['dolos_settings'] = 'Cài đặt phát hiện đạo văn Dolos';
$string['dolos_settings_desc'] = 'Cấu hình cho dịch vụ phát hiện đạo văn Dolos';
$string['dolos_api_url'] = 'Địa chỉ Dolos API';
$string['dolos_api_url_desc'] = 'Địa chỉ URL của dịch vụ Dolos API';
$string['dolos_api_key'] = 'Khóa Dolos API';
$string['dolos_api_key_desc'] = 'API key để xác thực với Dolos (nếu cần)';
$string['dolos_timeout'] = 'Thời gian chờ Dolos';
$string['dolos_timeout_desc'] = 'Thời gian tối đa (giây) chờ phản hồi từ Dolos API';

$string['submissionstatus_6'] = 'Đúng một phần';

// Tính năng chạy thử mã nguồn
$string['runcode'] = 'Chạy thử mã nguồn';
$string['customtestinput'] = 'Dữ liệu vào tùy chỉnh';
$string['enterinput'] = 'Nhập dữ liệu kiểm thử của bạn tại đây...';
$string['runningcode'] = 'Đang chạy mã nguồn của bạn...';
$string['runresult'] = 'Kết quả chạy thử';
$string['output'] = 'Kết quả xuất ra';
$string['stderr'] = 'Kết quả lỗi';
$string['compileoutput'] = 'Kết quả biên dịch';
$string['nooutput'] = 'Không có kết quả xuất ra';
$string['noerror'] = 'Không có lỗi';
$string['nocompileoutput'] = 'Không có kết quả biên dịch';
$string['connectionerror'] = 'Không thể kết nối tới máy chủ. Vui lòng thử lại.';
$string['actualoutput'] = 'Kết quả thực tế';
$string['summary'] = 'Tóm tắt';
$string['debug'] = 'Gỡ lỗi';
$string['testcasespassed'] = 'Test case đã vượt qua';

// Tải lên file test case
$string['testcasefile'] = 'Tải lên file test case';
$string['testcasefile_help'] = 'Tải lên file JSON chứa các test case. Mỗi test case cần có các trường: input, output, points, time_limit, description, visible_to_student. Việc này sẽ thêm mới hoặc thay thế các test case hiện có.';
$string['testcasefileformat'] = 'Định dạng file test case';
$string['testcasefileformatdesc'] = 'Tải lên file JSON chứa một mảng các đối tượng test case. Mỗi test case cần có các trường: "input", "output", "points", "time_limit", "description", "visible_to_student".';
$string['testcasefileerror'] = 'Lỗi khi xử lý file test case';
$string['testcasefileempty'] = 'File test case tải lên bị rỗng hoặc không hợp lệ';
$string['testcasefileprocessed'] = '{$a} test case đã được nhập thành công';
$string['testcaseuploadexample'] = 'Ví dụ định dạng: [{"input":"1 2","output":"3","points":10,"time_limit":3000,"description":"Test cộng","visible_to_student":1}]';
$string['testcaseuploadtip'] = 'Mẹo: Bạn có thể tạo test case thủ công trước, sau đó xuất ra để xem định dạng mong muốn';
$string['testcasedefaults'] = 'Giá trị mặc định: points = 10.0, time_limit = 3000ms, visible_to_student = false. Chỉ bắt buộc trường input và output.';
$string['testcaseexport'] = 'Xuất các trường hợp thử nghiệm';
$string['testcaseimport'] = 'Nhập test case';
$string['downloadasjson'] = 'Tải về dưới dạng file {$a}';
$string['downloadastxt'] = 'Tải về dưới dạng file {$a}';
$string['download'] = 'Tải về';

$string['testcaselimit_memory'] = 'Giới hạn bộ nhớ (MB)';
$string['error_invalidmemorylimit'] = 'Giới hạn bộ nhớ không hợp lệ. Phải là một số dương (MB).';

$string['testcasevisible'] = 'Hiển thị cho sinh viên';
$string['testcaseisexample'] = 'Là ví dụ';
$string['testcasememorylimit'] = 'Giới hạn bộ nhớ (MB)';
$string['testcasememorylimiterror'] = 'Giới hạn bộ nhớ phải là một số dương (MB).';
$string['addtestcase'] = 'Thêm trường hợp thử nghiệm';

// Chuỗi bổ sung cho trang báo cáo đạo văn
$string['search'] = 'Tìm kiếm';
$string['filterbyassignment'] = 'Lọc theo bài tập';
$string['allassignments'] = 'Tất cả bài tập';
$string['apply'] = 'Áp dụng';
$string['noplagiarismfound'] = 'Không phát hiện đạo văn';
$string['plagiarismreport'] = 'Báo cáo đạo văn';
$string['submissionid'] = 'Mã bài nộp';
$string['student'] = 'Sinh viên';
$string['assignment'] = 'Bài tập';
$string['submissiondate'] = 'Ngày nộp';
$string['actions'] = 'Hành động';
$string['viewdetails'] = 'Xem chi tiết';
$string['back'] = 'Quay lại';
$string['plagiarismnotenabled'] = 'Chức năng phát hiện đạo văn chưa được bật cho bài tập này';
$string['similaritylevel'] = 'Mức độ tương đồng';
$string['matchedwith'] = 'Trùng khớp với';

// Trạng thái đánh giá đạo văn
$string['reviewed'] = 'Đã xem xét';
$string['pending'] = 'Đang chờ';
$string['manuallyflagged'] = 'Đánh dấu thủ công';
$string['statusreview'] = 'Trạng thái xem xét';

// Mức độ tương đồng
$string['highsimilarity'] = 'Tương đồng cao';
$string['mediumsimilarity'] = 'Tương đồng trung bình';
$string['lowsimilarity'] = 'Tương đồng thấp';
