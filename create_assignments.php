<?php
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');

// Current time for timestamps
$now = time();

// Course ID (assuming the Python course already exists)
$courseid = 2; // Make sure this matches your Python course ID

// Create 5 programming assignments
$assignments = [
    [
        'name' => 'Tìm số nguyên tố',
        'intro' => '<p>Viết chương trình kiểm tra một số có phải là số nguyên tố hay không.</p>
                    <p>Số nguyên tố là số tự nhiên lớn hơn 1 và chỉ có đúng hai ước số là 1 và chính nó.</p>',
        'introformat' => 1,
        'language' => 'python',
        'num_testcases' => 4,
        'points_per_test' => 5,
        'total_points' => 20,
        'enable_plagiarism' => 1,
        'similarity_threshold' => 80,
        'enable_leaderboard' => 1,
        'leaderboard_size' => 10,
        'leaderboard_anonymous' => 0,
        'allowed_attempts' => 0,
        'duedate' => $now + 14 * 24 * 3600, // 2 weeks from now
        'allowsubmissionsfromdate' => $now - 24 * 3600, // 1 day ago
        'testcases' => [
            ['input' => '7', 'output' => 'Yes', 'points' => 5, 'time_limit' => 1000, 'visible_to_student' => 1, 'description' => 'Kiểm tra số nguyên tố nhỏ'],
            ['input' => '4', 'output' => 'No', 'points' => 5, 'time_limit' => 1000, 'visible_to_student' => 1, 'description' => 'Kiểm tra số không phải nguyên tố'],
            ['input' => '1', 'output' => 'No', 'points' => 5, 'time_limit' => 1000, 'visible_to_student' => 0, 'description' => 'Số đặc biệt (1 không phải số nguyên tố)'],
            ['input' => '97', 'output' => 'Yes', 'points' => 5, 'time_limit' => 1000, 'visible_to_student' => 0, 'description' => 'Kiểm tra số nguyên tố lớn'],
        ]
    ],
    [
        'name' => 'Tổng dãy số',
        'intro' => '<p>Viết chương trình tính tổng của một dãy số nguyên.</p>
                    <p>Dòng đầu tiên là số lượng phần tử của dãy.</p>
                    <p>Dòng thứ hai là các phần tử của dãy, cách nhau bởi dấu cách.</p>',
        'introformat' => 1,
        'language' => 'python',
        'num_testcases' => 5,
        'points_per_test' => 4,
        'total_points' => 20,
        'enable_plagiarism' => 1,
        'similarity_threshold' => 85,
        'enable_leaderboard' => 1,
        'leaderboard_size' => 5,
        'leaderboard_anonymous' => 0,
        'allowed_attempts' => 0,
        'duedate' => $now + 10 * 24 * 3600, // 10 days from now
        'allowsubmissionsfromdate' => $now,
        'testcases' => [
            ['input' => "3\n1 2 3", 'output' => '6', 'points' => 4, 'time_limit' => 1000, 'visible_to_student' => 1, 'description' => 'Tổng dãy số nhỏ'],
            ['input' => "5\n1 2 3 4 5", 'output' => '15', 'points' => 4, 'time_limit' => 1000, 'visible_to_student' => 1, 'description' => 'Tổng 5 số đầu tiên'],
            ['input' => "1\n100", 'output' => '100', 'points' => 4, 'time_limit' => 1000, 'visible_to_student' => 0, 'description' => 'Dãy chỉ có một số'],
            ['input' => "10\n1 2 3 4 5 6 7 8 9 10", 'output' => '55', 'points' => 4, 'time_limit' => 1000, 'visible_to_student' => 0, 'description' => 'Tổng 10 số đầu tiên'],
            ['input' => "7\n-3 -2 -1 0 1 2 3", 'output' => '0', 'points' => 4, 'time_limit' => 1000, 'visible_to_student' => 0, 'description' => 'Tổng dãy có số âm'],
        ]
    ],
    [
        'name' => 'Dãy Fibonacci',
        'intro' => '<p>Viết chương trình in ra số Fibonacci thứ n.</p>
                    <p>Dãy Fibonacci bắt đầu từ 0 và 1, mỗi số tiếp theo bằng tổng hai số trước đó.</p>
                    <p>Ví dụ: 0, 1, 1, 2, 3, 5, 8, 13, 21, 34, ...</p>',
        'introformat' => 1,
        'language' => 'python',
        'num_testcases' => 5,
        'points_per_test' => 5,
        'total_points' => 25,
        'enable_plagiarism' => 1,
        'similarity_threshold' => 90,
        'enable_leaderboard' => 1,
        'leaderboard_size' => 10,
        'leaderboard_anonymous' => 0,
        'allowed_attempts' => 0,
        'duedate' => $now + 16 * 24 * 3600, // 16 days from now
        'allowsubmissionsfromdate' => $now,
        'testcases' => [
            ['input' => '0', 'output' => '0', 'points' => 5, 'time_limit' => 1000, 'visible_to_student' => 1, 'description' => 'Số Fibonacci thứ 0'],
            ['input' => '1', 'output' => '1', 'points' => 5, 'time_limit' => 1000, 'visible_to_student' => 1, 'description' => 'Số Fibonacci thứ 1'],
            ['input' => '5', 'output' => '5', 'points' => 5, 'time_limit' => 1000, 'visible_to_student' => 1, 'description' => 'Số Fibonacci thứ 5'],
            ['input' => '10', 'output' => '55', 'points' => 5, 'time_limit' => 1000, 'visible_to_student' => 0, 'description' => 'Số Fibonacci thứ 10'],
            ['input' => '20', 'output' => '6765', 'points' => 5, 'time_limit' => 3000, 'visible_to_student' => 0, 'description' => 'Số Fibonacci thứ 20'],
        ]
    ],
    [
        'name' => 'Sắp xếp mảng',
        'intro' => '<p>Viết chương trình sắp xếp một mảng các số nguyên theo thứ tự tăng dần.</p>
                    <p>Dòng đầu tiên là số lượng phần tử của mảng.</p>
                    <p>Dòng thứ hai là các phần tử của mảng, cách nhau bởi dấu cách.</p>',
        'introformat' => 1,
        'language' => 'python',
        'num_testcases' => 3,
        'points_per_test' => 10,
        'total_points' => 30,
        'enable_plagiarism' => 1,
        'similarity_threshold' => 85,
        'enable_leaderboard' => 1,
        'leaderboard_size' => 5,
        'leaderboard_anonymous' => 0,
        'allowed_attempts' => 0,
        'duedate' => $now + 21 * 24 * 3600, // 3 weeks from now
        'allowsubmissionsfromdate' => $now + 7 * 24 * 3600, // 1 week from now
        'testcases' => [
            ['input' => "5\n5 3 1 4 2", 'output' => '1 2 3 4 5', 'points' => 10, 'time_limit' => 1000, 'visible_to_student' => 1, 'description' => 'Sắp xếp mảng 5 phần tử'],
            ['input' => "3\n3 2 1", 'output' => '1 2 3', 'points' => 10, 'time_limit' => 1000, 'visible_to_student' => 1, 'description' => 'Sắp xếp mảng 3 phần tử'],
            ['input' => "10\n10 9 8 7 6 5 4 3 2 1", 'output' => '1 2 3 4 5 6 7 8 9 10', 'points' => 10, 'time_limit' => 2000, 'visible_to_student' => 0, 'description' => 'Sắp xếp mảng ngược'],
        ]
    ],
    [
        'name' => 'Đếm từ trong chuỗi',
        'intro' => '<p>Viết chương trình đếm số từ trong một chuỗi.</p>
                    <p>Các từ được phân cách bởi dấu cách, dấu chấm, dấu phẩy, dấu chấm phẩy, dấu hai chấm, dấu chấm hỏi, dấu chấm than.</p>',
        'introformat' => 1,
        'language' => 'python',
        'num_testcases' => 4,
        'points_per_test' => 5,
        'total_points' => 20,
        'enable_plagiarism' => 1,
        'similarity_threshold' => 80,
        'enable_leaderboard' => 1,
        'leaderboard_size' => 10,
        'leaderboard_anonymous' => 0,
        'allowed_attempts' => 0,
        'duedate' => $now + 28 * 24 * 3600, // 4 weeks from now
        'allowsubmissionsfromdate' => $now + 14 * 24 * 3600, // 2 weeks from now
        'testcases' => [
            ['input' => 'Hello world', 'output' => '2', 'points' => 5, 'time_limit' => 1000, 'visible_to_student' => 1, 'description' => 'Đếm từ trong chuỗi đơn giản'],
            ['input' => 'This is a test.', 'output' => '4', 'points' => 5, 'time_limit' => 1000, 'visible_to_student' => 1, 'description' => 'Chuỗi có dấu chấm'],
            ['input' => 'Hello, world! How are you?', 'output' => '5', 'points' => 5, 'time_limit' => 1000, 'visible_to_student' => 0, 'description' => 'Chuỗi có dấu phẩy và dấu chấm hỏi'],
            ['input' => 'One,two;three:four.five', 'output' => '5', 'points' => 5, 'time_limit' => 1000, 'visible_to_student' => 0, 'description' => 'Chuỗi với nhiều ký tự phân cách'],
        ]
    ],
];

// Student user IDs - assuming these already exist
$student_ids = [3, 4, 5, 6, 7]; // Replace with actual student IDs
$created_assignments = [];

try {
    // Create the assignments
    foreach ($assignments as $assignment_data) {
        $testcases = $assignment_data['testcases'];
        unset($assignment_data['testcases']);
        
        $assignment_data['course'] = $courseid;
        $assignment_data['timecreated'] = $now;
        $assignment_data['timemodified'] = $now;
        
        $assignment_id = $DB->insert_record('devcode', $assignment_data);
        echo "Created assignment: {$assignment_data['name']} with ID: $assignment_id\n";
        
        // Add test cases
        foreach ($testcases as $testcase) {
            $testcase['devcodeid'] = $assignment_id;
            $testcase['timecreated'] = $now;
            $testcase['timemodified'] = $now;
            
            $testcase_id = $DB->insert_record('devcode_testcases', $testcase);
            echo "  Added test case ID: $testcase_id\n";
        }
        
        $created_assignments[] = $assignment_id;
    }

    // Sample code solutions for each assignment
    $solutions = [
        // Tìm số nguyên tố
        'def is_prime(n):
    if n <= 1:
        return False
    if n <= 3:
        return True
    if n % 2 == 0 or n % 3 == 0:
        return False
    i = 5
    while i * i <= n:
        if n % i == 0 or n % (i + 2) == 0:
            return False
        i += 6
    return True

num = int(input())
if is_prime(num):
    print("Yes")
else:
    print("No")',
        
        // Tổng dãy số
        'n = int(input())
numbers = list(map(int, input().split()))
print(sum(numbers))',
        
        // Dãy Fibonacci
        'def fibonacci(n):
    if n == 0:
        return 0
    elif n == 1:
        return 1
    else:
        a, b = 0, 1
        for i in range(2, n+1):
            a, b = b, a + b
        return b

n = int(input())
print(fibonacci(n))',
        
        // Sắp xếp mảng
        'n = int(input())
arr = list(map(int, input().split()))
arr.sort()
print(" ".join(map(str, arr)))',
        
        // Đếm từ trong chuỗi
        'import re
text = input()
words = re.split(r"[\\s,.;:!?]+", text)
words = [word for word in words if word]
print(len(words))'
    ];

    // Incorrect solutions for each assignment
    $wrong_solutions = [
        // Tìm số nguyên tố (wrong)
        'def is_prime(n):
    if n <= 1:
        return False
    for i in range(2, n):
        if n % i == 0:
            return False
    return True

num = int(input())
if is_prime(num):
    print("Yes")
else:
    print("No")',
        
        // Tổng dãy số (wrong)
        'n = int(input())
numbers = list(map(int, input().split()))
print(sum(numbers) + 1)',  // Wrong output
        
        // Dãy Fibonacci (infinite loop)
        'def fibonacci(n):
    if n == 0:
        return 0
    elif n == 1:
        return 1
    else:
        return fibonacci(n-1) + fibonacci(n-2)  # Will timeout for large n

n = int(input())
print(fibonacci(n))',
        
        // Sắp xếp mảng (wrong)
        'n = int(input())
arr = list(map(int, input().split()))
# Forgot to sort!
print(" ".join(map(str, arr)))',
        
        // Đếm từ trong chuỗi (wrong)
        'text = input()
words = text.split()  # Only splits by whitespace, ignoring other separators
print(len(words))'
    ];

    // Compile error solutions
    $compile_error_solutions = [
        // Tìm số nguyên tố (compile error)
        'def is_prime(n):
    if n <= 1
        return False  # Missing colon
    if n <= 3:
        return True
    i = 5
    while i * i <= n:
        if n % i == 0 or n % (i + 2) == 0:
            return False
        i += 6
    return True

num = int(input())
if is_prime(num):
    print("Yes")
else:
    print("No")',
        
        // Other assignments with compile errors
        'n = int(input())
numbers = list(map(int, input().split()))
print(sum(numbers)',  // Missing closing parenthesis
        
        'def fibonacci(n):
    if n = 0:  # Should be ==
        return 0
    elif n == 1:
        return 1
    else:
        return fibonacci(n-1) + fibonacci(n-2)

n = int(input())
print(fibonacci(n))',
        
        'n = int(input))  # Missing parenthesis
arr = list(map(int, input().split()))
arr.sort()
print(" ".join(map(str, arr)))',
        
        'import re
text = input()
words = re.split(r"[\\s,.;:!?]+", text)
words = [word for word in words if word
print(len(words))'  // Missing closing bracket
    ];

    // Plagiarized solutions (slightly modified correct solutions)
    $plagiarized_solutions = [
        // Tìm số nguyên tố (plagiarized)
        'def is_prime(num):
    if num <= 1:
        return False
    if num <= 3:
        return True
    if num % 2 == 0 or num % 3 == 0:
        return False
    i = 5
    while i * i <= num:
        if num % i == 0 or num % (i + 2) == 0:
            return False
        i += 6
    return True

n = int(input())
if is_prime(n):
    print("Yes")
else:
    print("No")',
        
        // Other plagiarized solutions
        'count = int(input())
nums = list(map(int, input().split()))
print(sum(nums))',
        
        'def fib(n):
    if n == 0:
        return 0
    elif n == 1:
        return 1
    else:
        a, b = 0, 1
        for i in range(2, n+1):
            a, b = b, a + b
        return b

n = int(input())
print(fib(n))',
        
        'count = int(input())
array = list(map(int, input().split()))
array.sort()
print(" ".join(map(str, array)))',
        
        'import re
s = input()
words = re.split(r"[\\s,.;:!?]+", s)
words = [w for w in words if w]
print(len(words))'
    ];

    // Status types
    $status_types = ['accepted', 'wrong_answer', 'time_limit', 'compile_error', 'plagiarism'];

    // Create submissions for each student for each assignment
    foreach ($created_assignments as $idx => $assignment_id) {
        // Get the test cases for this assignment
        $testcases = $DB->get_records('devcode_testcases', ['devcodeid' => $assignment_id]);
        $total_tests = count($testcases);
        
        foreach ($student_ids as $student_idx => $user_id) {
            // Determine submission status based on student index
            $status = $status_types[$student_idx % count($status_types)];
            
            // Select the appropriate solution based on the status
            switch ($status) {
                case 'accepted':
                    $code = $solutions[$idx];
                    $passed_tests = $total_tests;
                    $score = 100;
                    break;
                case 'wrong_answer':
                    $code = $wrong_solutions[$idx];
                    $passed_tests = max(1, round($total_tests / 3));
                    $score = round(($passed_tests / $total_tests) * 100);
                    break;
                case 'time_limit':
                    $code = $solutions[$idx]; // Use correct solution but will be marked as time limit exceeded
                    $passed_tests = 0;
                    $score = 0;
                    break;
                case 'compile_error':
                    $code = $compile_error_solutions[$idx];
                    $passed_tests = 0;
                    $score = 0;
                    break;
                case 'plagiarism':
                    $code = $plagiarized_solutions[$idx];
                    $passed_tests = $total_tests; // Would have passed if not for plagiarism
                    $score = 0; // Zero score due to plagiarism
                    break;
            }
            
            // Create submission record
            $submission_data = [
                'devcodeid' => $assignment_id,
                'userid' => $user_id,
                'code' => $code,
                'language' => 'python',
                'status' => $status,
                'score' => $score,
                'passed_tests' => $passed_tests,
                'total_tests' => $total_tests,
                'feedback' => 'Automated feedback for ' . $status . ' submission',
                'timecreated' => $now - rand(0, 5 * 24 * 3600), // Random time in the last 5 days
                'timemodified' => $now - rand(0, 5 * 24 * 3600)
            ];
            
            $submission_id = $DB->insert_record('devcode_submissions', $submission_data);
            echo "Created submission for student ID: $user_id on assignment ID: $assignment_id with status: $status\n";
            
            // Create test case results
            foreach ($testcases as $testcase) {
                // Determine if this test case passed based on the status and passed_tests count
                $passed = 0;
                $error_message = '';
                $output = '';
                
                switch ($status) {
                    case 'accepted':
                        $passed = 1;
                        $output = $testcase->output;
                        break;
                    case 'wrong_answer':
                        // Some test cases pass, others fail
                        if (rand(0, $total_tests) < $passed_tests) {
                            $passed = 1;
                            $output = $testcase->output;
                        } else {
                            $passed = 0;
                            $output = $testcase->output . ' (wrong)';
                        }
                        break;
                    case 'time_limit':
                        $passed = 0;
                        $error_message = 'Time limit exceeded: execution took longer than ' . $testcase->time_limit . 'ms';
                        break;
                    case 'compile_error':
                        $passed = 0;
                        $error_message = 'SyntaxError: invalid syntax';
                        break;
                    case 'plagiarism':
                        $passed = 1; // Would have passed if not for plagiarism
                        $output = $testcase->output;
                        break;
                }
                
                $result_data = [
                    'submissionid' => $submission_id,
                    'testcaseid' => $testcase->id,
                    'passed' => $passed,
                    'output' => $output,
                    'error_message' => $error_message,
                    'execution_time' => $status === 'time_limit' ? $testcase->time_limit + 500 : rand(10, $testcase->time_limit - 100),
                    'memory_used' => rand(1000, 5000),
                    'timecreated' => $now
                ];
                
                $result_id = $DB->insert_record('devcode_submission_results', $result_data);
                echo "  Created test result ID: $result_id for test case ID: {$testcase->id}\n";
            }
            
            // Create plagiarism records if status is 'plagiarism'
            if ($status === 'plagiarism') {
                // Find another student who has a correct solution
                $other_student_id = $user_id;
                foreach ($student_ids as $other_id) {
                    if ($other_id != $user_id) {
                        $other_student_id = $other_id;
                        break;
                    }
                }
                
                // Find that student's submission for this assignment
                $other_submission = $DB->get_record('devcode_submissions', [
                    'devcodeid' => $assignment_id,
                    'userid' => $other_student_id
                ]);
                
                if ($other_submission) {
                    // Create plagiarism record
                    $plagiarism_data = [
                        'devcodeid' => $assignment_id,
                        'submission1_id' => $submission_id,
                        'submission2_id' => $other_submission->id,
                        'similarity_score' => 95.5, // High similarity
                        'flagged' => 1,
                        'reviewed' => 0,
                        'details' => 'Automatic plagiarism detection',
                        'timecreated' => $now,
                        'timemodified' => $now
                    ];
                    
                    $plagiarism_id = $DB->insert_record('devcode_plagiarism', $plagiarism_data);
                    echo "  Created plagiarism record ID: $plagiarism_id between submissions $submission_id and {$other_submission->id}\n";
                }
            }
        }
    }

    echo "\nCompleted creating 5 assignments with test cases and student submissions.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " (Line: " . $e->getLine() . ")\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
} 