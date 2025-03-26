<?php
defined('MOODLE_INTERNAL') || die();

$string['modulename'] = 'Lập trình';
$string['modulenameplural'] = 'Lập trình';
$string['modulename_help'] = 'Module Lập trình cho phép giảng viên tạo các bài tập lập trình được chấm điểm tự động.';
$string['pluginname'] = 'Lập trình';
$string['pluginadministration'] = 'Quản lý Lập trình';

// Form strings
$string['assignmentname'] = 'Tên';
$string['description'] = 'Mô tả';
$string['programminglanguage'] = 'Ngôn ngữ lập trình';
$string['languagefixed'] = '(cố định cho bài tập này)';
$string['testcases'] = 'Bộ test';
$string['numtestcases'] = 'Số lượng bộ test';
$string['pointspartest'] = 'Điểm cho mỗi bộ test';
$string['duedate'] = 'Hạn nộp';
$string['submissionsettings'] = 'Cài đặt nộp bài';
$string['allowsubmissionsfromdate'] = 'Cho phép nộp bài từ';
$string['code'] = 'Mã nguồn';
$string['submit'] = 'Nộp mã nguồn';
$string['savegrade'] = 'Lưu điểm';

// Plagiarism detection strings
$string['plagiarismsettings'] = 'Cài đặt phát hiện đạo văn';
$string['enableplagiarism'] = 'Bật phát hiện đạo văn';
$string['enableplagiarismdesc'] = 'Kiểm tra độ tương đồng mã nguồn giữa các bài nộp của sinh viên';
$string['similaritythreshold'] = 'Ngưỡng tương đồng (%)';
$string['similaritythreshold_help'] = 'Đặt ngưỡng phần trăm cho độ tương đồng mã nguồn. Các bài nộp có độ tương đồng cao hơn ngưỡng này sẽ được đánh dấu là có khả năng đạo văn.';
$string['similaritythresholderror'] = 'Ngưỡng phải là một số từ 1 đến 100';
$string['plagiarismreport'] = 'Báo cáo đạo văn';
$string['similarityscore'] = 'Điểm tương đồng';
$string['flaggedsubmissions'] = 'Bài nộp bị đánh dấu';

// View strings
$string['submitassignment'] = 'Nộp bài';
$string['submitcode'] = 'Nộp mã nguồn';
$string['viewsubmissions'] = 'Xem các bài nộp';
$string['grading'] = 'Chấm điểm';
$string['submissionstatus'] = 'Trạng thái nộp bài';
$string['submissionhistory'] = 'Lịch sử nộp bài';
$string['testresults'] = 'Kết quả kiểm thử';
$string['feedback'] = 'Phản hồi';
$string['gradingsubmission'] = 'Chấm điểm bài nộp';
$string['submittedcode'] = 'Mã nguồn đã nộp';
$string['submissiondate'] = 'Ngày nộp';
$string['submissionnotallowed'] = 'Không được phép nộp bài vào lúc này';
$string['student'] = 'Sinh viên';

// Status strings
$string['statusnotsubmitted'] = 'Chưa nộp';
$string['statussubmitted'] = 'Đã nộp';
$string['statusgraded'] = 'Đã chấm';
$string['statusoverdue'] = 'Quá hạn';

// New test case strings
$string['testcaseinput'] = 'Đầu vào';
$string['testcaseoutput'] = 'Đầu ra mong đợi';
$string['testcasepoints'] = 'Điểm';
$string['testcasetimelimit'] = 'Giới hạn thời gian (ms)';
$string['visibletostudent'] = 'Hiển thị cho sinh viên';
$string['addmoretestcases'] = 'Thêm bộ test';
$string['testcasepointserror'] = 'Điểm phải là số dương';
$string['testcasetimelimiterror'] = 'Giới hạn thời gian phải là số dương';
$string['viewtestcases'] = 'Xem các bộ test';
$string['visibletestcases'] = 'Bộ test mẫu';
$string['hiddentestcases'] = 'Bộ test ẩn (chỉ hiển thị cho giảng viên)';
$string['notestcasesyet'] = 'Chưa có bộ test nào được thêm';
$string['exampletestcasesintro'] = 'Các bộ test mẫu sau đây sẽ được sử dụng để kiểm tra mã nguồn của bạn:';

// New strings for improved UI
$string['exampletestcases'] = 'Bộ test mẫu';
$string['input'] = 'Đầu vào';
$string['expectedoutput'] = 'Đầu ra mong đợi';
$string['points'] = 'Điểm';

// Submission strings
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
$string['submissionstatus_submitted'] = 'Đã nộp vào {$a}';
$string['submissionstatus_overdue'] = 'Quá hạn';
$string['submissionstatus_notallowed'] = 'Không được phép nộp bài vào lúc này';
$string['submissionstatus_completed'] = 'Hoàn thành';
$string['submissionstatus_failed'] = 'Thất bại';
$string['submissionstatus_partial'] = 'Đúng một phần';
$string['submissionsfor'] = 'Bài nộp cho {$a}';
$string['nostudentsyet'] = 'Chưa có sinh viên nộp bài';
$string['back'] = 'Quay lại';
$string['allsubmissions'] = 'Tất cả bài nộp';
$string['submissions'] = 'Bài nộp';
$string['grade'] = 'Điểm số';
$string['timemodified'] = 'Lần sửa cuối';
$string['submissionsmultiple'] = '{$a} bài nộp';
$string['failed_testcase'] = 'Bộ test #{$a} thất bại';
$string['execution_stopped'] = 'Thực thi dừng do lỗi trong bộ test #{$a}';
$string['compilation_error'] = 'Lỗi biên dịch';
$string['runtime_error'] = 'Lỗi thực thi';
$string['time_limit_exceeded'] = 'Vượt quá giới hạn thời gian';
$string['memory_limit_exceeded'] = 'Vượt quá giới hạn bộ nhớ';
$string['wrong_answer'] = 'Đáp án sai';

// New submission interface strings
$string['submission'] = 'Bài nộp';
$string['codetab'] = 'Mã nguồn';
$string['filetab'] = 'Tệp';
$string['sourcefile'] = 'Tệp mã nguồn';
$string['fileuploadinstructions'] = 'Tải lên tệp mã nguồn của bạn. Các loại tệp được chấp nhận: .py, .java, .cpp, .c, .js';
$string['filenotfound'] = 'Không tìm thấy tệp đã tải lên. Vui lòng thử lại.';

// Results display strings
$string['gradingresults'] = 'Kết quả chấm điểm';
$string['pointsearned'] = 'Điểm đạt được';
$string['testcasespassed'] = 'Bộ test đã vượt qua';
$string['testcasestats'] = 'Thống kê bộ test';
$string['testcasespassrate'] = 'Tỷ lệ vượt qua';
$string['allpassed'] = 'Tất cả các bộ test đều vượt qua!';
$string['executiontime'] = 'Thời gian thực thi';
$string['viewdetailedresults'] = 'Xem kết quả chi tiết';
$string['resubmit'] = 'Nộp lại';
$string['submissiontime'] = 'Thời gian nộp';
$string['status'] = 'Trạng thái';
$string['actions'] = 'Hành động';
$string['viewdetails'] = 'Xem chi tiết';

// Detailed results page
$string['submissionresults'] = 'Kết quả bài nộp';
$string['submissioninfo'] = 'Thông tin bài nộp';
$string['detailedresults'] = 'Kết quả kiểm thử chi tiết';
$string['youroutput'] = 'Đầu ra của bạn';
$string['result'] = 'Kết quả';
$string['passed'] = 'Đạt';
$string['failed'] = 'Thất bại';
$string['errormessage'] = 'Thông báo lỗi';
$string['backtocourse'] = 'Quay lại bài tập';
$string['viewsubmission'] = 'Xem bài nộp';

// New strings for execution results
$string['memoryused'] = 'Bộ nhớ sử dụng';
$string['resultaccepted'] = 'Chấp nhận';
$string['resultwronganswer'] = 'Đáp án sai';
$string['resultcompilationerror'] = 'Lỗi biên dịch';
$string['resulttimelimit'] = 'Vượt quá giới hạn thời gian';
$string['resultmemorylimit'] = 'Vượt quá giới hạn bộ nhớ';
$string['resultruntime'] = 'Lỗi thực thi';
$string['expectedoutput'] = 'Đầu ra mong đợi';
$string['actualoutput'] = 'Đầu ra của bạn';
$string['compilationoutput'] = 'Đầu ra trình biên dịch';
$string['runtimeerror'] = 'Lỗi thực thi';

// Editor features
$string['codehint'] = 'Viết mã nguồn của bạn tại đây...';
$string['codelanguage'] = 'Ngôn ngữ lập trình';
$string['autoindent'] = 'Tự động thụt lề';
$string['tabsize'] = 'Kích thước tab';
$string['linenumbers'] = 'Hiển thị số dòng';
$string['wordwrap'] = 'Ngắt dòng';
$string['syntaxhighlighting'] = 'Tô màu cú pháp';
$string['darkmode'] = 'Chế độ tối';
$string['editorpreferences'] = 'Tùy chọn trình soạn thảo';

// API/Backend messages
$string['apierror'] = 'Lỗi kết nối với dịch vụ chấm điểm';
$string['retrying'] = 'Kết nối thất bại, đang thử lại...';
$string['maxretries'] = 'Đã đạt số lần thử lại tối đa, vui lòng thử lại sau';
$string['simulationmode'] = 'Đang chạy ở chế độ mô phỏng (không thực thi mã nguồn thực tế)';
$string['backenderror'] = 'Hệ thống chấm điểm báo cáo lỗi';
$string['submissionqueued'] = 'Bài nộp của bạn đã được đưa vào hàng đợi chấm điểm';

// Contextual help and hints
$string['firstsubmissionadvice'] = 'Hãy thử nộp sớm để nhận phản hồi về mã nguồn của bạn';
$string['testcaseadvice'] = 'Đảm bảo mã nguồn của bạn xử lý tất cả các đầu vào có thể';
$string['formattingadvice'] = 'Định dạng đầu ra chính xác như được chỉ định trong đầu ra mong đợi';
$string['timelimitadvice'] = 'Giải pháp của bạn nên chạy trong giới hạn thời gian';

// New UI strings
$string['nodescription'] = 'Không có mô tả cho bài tập này.';
$string['jumptotestcases'] = 'Chuyển đến bộ test';
$string['codeeditor'] = 'Trình soạn thảo mã nguồn';
$string['sidebar'] = 'Mô tả nhiệm vụ';
$string['hideintro'] = 'Ẩn mô tả';
$string['showintro'] = 'Hiện mô tả';
$string['taskdescription'] = 'Mô tả nhiệm vụ';

// Autosave strings
$string['autosavedsuccessfully'] = 'Đã tự động lưu';
$string['restoredautosave'] = 'Đã khôi phục từ bản lưu tự động';
$string['restoresavedcode'] = 'Khôi phục phiên bản đã lưu';
$string['localsavedversionexists'] = 'Tồn tại một phiên bản đã lưu khác';
$string['secondsago'] = 'giây trước';
$string['minutesago'] = 'phút trước';
$string['hoursago'] = 'giờ trước';
$string['daysago'] = 'ngày trước';

$string['notfound'] = 'Không tìm thấy';
?> 