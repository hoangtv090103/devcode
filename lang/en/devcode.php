<?php
defined('MOODLE_INTERNAL') || die();

$string['modulename'] = 'Code';
$string['modulenameplural'] = 'Code';
$string['modulename_help'] = 'The Code module allows instructors to create programming assignments that are automatically graded.';
$string['pluginname'] = 'Code';
$string['pluginadministration'] = 'Code administration';

// Form strings
$string['assignmentname'] = 'Name';
$string['description'] = 'Description';
$string['programminglanguage'] = 'Programming language';
$string['languagefixed'] = '(fixed for this assignment)';
$string['testcases'] = 'Test cases';
$string['testcase'] = 'Test case';
$string['numtestcases'] = 'Number of Test Cases';
$string['pointspartest'] = 'Points per Test Case';
$string['duedate'] = 'Due Date';
$string['submissionsettings'] = 'Submission Settings';
$string['allowsubmissionsfromdate'] = 'Allow Submissions From';
$string['code'] = 'Code';
$string['submit'] = 'Submit Code';
$string['savegrade'] = 'Save Grade';

// Plagiarism detection strings
$string['plagiarismsettings'] = 'Plagiarism Detection Settings';
$string['enableplagiarism'] = 'Enable plagiarism detection';
$string['enableplagiarismdesc'] = 'Check for code similarity between student submissions';
$string['similaritythreshold'] = 'Similarity threshold (%)';
$string['similaritythreshold_help'] = 'Set the percentage threshold for code similarity. Submissions with similarity above this threshold will be flagged as potential plagiarism.';
$string['similaritythresholderror'] = 'Threshold must be a number between 1 and 100';
$string['plagiarismreport'] = 'Plagiarism Report';
$string['similarityscore'] = 'Similarity Score';
$string['flaggedsubmissions'] = 'Flagged Submissions';
$string['comparesubmissions'] = 'Compare Submissions';
$string['codecomparison'] = 'Code Comparison';
$string['codecomparisoninfo'] = 'This view highlights similarities between the two submissions.';
$string['backtoplagiarismreport'] = 'Back to Plagiarism Report';
$string['plagiarismdetailreport'] = 'Plagiarism Detail Report';
$string['submissiondetails'] = 'Submission Details';
$string['similarsubmissions'] = 'Similar Submissions';
$string['nosimilarsubmissionsfound'] = 'No similar submissions found.';
$string['viewsourcecode'] = 'View Source Code';
$string['teachernotes'] = 'Teacher Notes';
$string['notes'] = 'Notes';
$string['flagasplagiarism'] = 'Flag as Plagiarism';
$string['markaspassed'] = 'Mark as Passed';
$string['backtoplagiarismlist'] = 'Back to Plagiarism List';
$string['submissionflaggedasplagiarism'] = 'Submission has been flagged as potential plagiarism.';
$string['submissionmarkedaspassed'] = 'Submission has been marked as passed.';
$string['filterbyassignment'] = 'Filter by assignment';
$string['allassignments'] = 'All assignments';
$string['apply'] = 'Apply';
$string['noplagiarismfound'] = 'No plagiarism detected.';
$string['maxsimilarity'] = 'Max Similarity';
$string['matchescount'] = 'Matches Count';
$string['invalidaction'] = 'Invalid action.';
$string['invalidaccess'] = 'Invalid access.';
$string['plagiarismnotenabled'] = 'Plagiarism detection is not enabled for this assignment.';
$string['allplagiarismreports'] = 'All Plagiarism Reports';
$string['noplagiarismdetected'] = 'No plagiarism detected.';
$string['assignment'] = 'Assignment';
$string['submissionid'] = 'Submission ID';

// View strings
$string['submitassignment'] = 'Submit';
$string['submitcode'] = 'Submit Code';
$string['viewsubmissions'] = 'View Submissions';
$string['grading'] = 'Grading';
$string['submissionstatus'] = 'Submission Status';
$string['submissionhistory'] = 'Submission History';
$string['testresults'] = 'Test Results';
$string['feedback'] = 'Feedback';
$string['gradingsubmission'] = 'Grading Submission';
$string['submittedcode'] = 'Submitted Code';
$string['submissiondate'] = 'Submission Date';
$string['submissionnotallowed'] = 'Submission is not allowed at this time';
$string['student'] = 'Student';

// Status strings
$string['statusnotsubmitted'] = 'Not Submitted';
$string['statussubmitted'] = 'Submitted';
$string['statusgraded'] = 'Graded';
$string['statusoverdue'] = 'Overdue';

// New test case strings
$string['testcaseinput'] = 'Input';
$string['testcaseoutput'] = 'Expected output';
$string['testcasepoints'] = 'Points';
$string['testcasetimelimit'] = 'Time limit (ms)';
$string['visibletostudent'] = 'Visible to student';
$string['addmoretestcases'] = 'Add more test cases';
$string['testcasepointserror'] = 'Points must be a positive number';
$string['testcasetimelimiterror'] = 'Time limit must be a positive number';
$string['viewtestcases'] = 'View test cases';
$string['visibletestcases'] = 'Example test cases';
$string['hiddentestcases'] = 'Hidden test cases (only visible to instructors)';
$string['notestcasesyet'] = 'No test cases have been added yet';
$string['exampletestcasesintro'] = 'The following example test cases will be used to test your code:';
$string['cannotdeleteallcases'] = 'You cannot delete all test cases. At least one test case is required.';
$string['confirmdeletetestcase'] = 'Are you sure you want to delete this test case?';

// New strings for improved UI
$string['exampletestcases'] = 'Example test cases';
$string['input'] = 'Input';
$string['expectedoutput'] = 'Expected output';
$string['points'] = 'Points';

// Submission strings
$string['submissionform'] = 'Submission form';
$string['yourcode'] = 'Your code';
$string['codeempty'] = 'Code cannot be empty';
$string['submissionsuccess'] = 'Your submission has been saved successfully';
$string['assignmentclosed'] = 'The assignment is now closed';
$string['editsubmission'] = 'Edit submission';
$string['viewallsubmissions'] = 'View all submissions';
$string['submissionstatus_processing'] = 'Processing';
$string['submissionstatus_graded'] = 'Graded';
$string['submissionstatus_error'] = 'Error';
$string['submissionstatus_notsubmitted'] = 'Not submitted';
$string['submissionstatus_submitted'] = 'Submitted on {$a}';
$string['submissionstatus_overdue'] = 'Overdue';
$string['submissionstatus_notallowed'] = 'Submission is not allowed at this time';
$string['submissionstatus_completed'] = 'Completed';
$string['submissionstatus_failed'] = 'Failed';
$string['submissionstatus_partial'] = 'Partially correct';
$string['submissionstatus_plagiarism'] = 'Plagiarism';
$string['submissionstatus_plagiarism_detected'] = 'Potential plagiarism detected';
$string['submissionsfor'] = 'Submissions for {$a}';
$string['nostudentsyet'] = 'No students have submitted yet';
$string['back'] = 'Back';
$string['allsubmissions'] = 'All submissions';
$string['submissions'] = 'Submissions';
$string['grade'] = 'Grade';
$string['timemodified'] = 'Last modified';
$string['submissionsmultiple'] = '{$a} submissions';
$string['failed_testcase'] = 'Test case #{$a} failed';
$string['execution_stopped'] = 'Execution stopped due to error in test case #{$a}';
$string['compilation_error'] = 'Compilation error';
$string['runtime_error'] = 'Runtime error';
$string['time_limit_exceeded'] = 'Time limit exceeded';
$string['memory_limit_exceeded'] = 'Memory limit exceeded';
$string['wrong_answer'] = 'Wrong answer';

// New submission interface strings
$string['submission'] = 'Submission';
$string['codetab'] = 'Code';
$string['filetab'] = 'File';
$string['sourcefile'] = 'Source code file';
$string['fileuploadhelp'] = 'Upload your source code file here. Make sure it contains all your solution code.';
$string['fileuploadrequired'] = 'Please upload a source code file';
$string['acceptedfiletypes'] = 'Accepted file types';
$string['fileuploadinstructions'] = 'Upload your source code file. Accepted file types: .py, .java, .cpp, .c, .js';
$string['filenotfound'] = 'The uploaded file could not be found. Please try again.';
$string['emptyfile'] = 'The uploaded file is empty. Please check your file and try again.';
$string['invalidfiletype'] = 'Invalid file type for {$a}. Please upload a file with the correct extension.';

// Results display strings
$string['gradingresults'] = 'Grading results';
$string['pointsearned'] = 'Points earned';
$string['testcasespassed'] = 'Test cases passed';
$string['testcasestats'] = 'Test case statistics';
$string['testcasespassrate'] = 'Pass rate';
$string['allpassed'] = 'All test cases passed!';
$string['executiontime'] = 'Execution time';
$string['viewdetailedresults'] = 'View detailed results';
$string['resubmit'] = 'Submit again';
$string['submissiontime'] = 'Submission time';
$string['status'] = 'Status';
$string['actions'] = 'Actions';
$string['viewdetails'] = 'View details';

// Detailed results page
$string['submissionresults'] = 'Submission results';
$string['submissioninfo'] = 'Submission information';
$string['detailedresults'] = 'Detailed test results';
$string['youroutput'] = 'Your output';
$string['result'] = 'Result';
$string['passed'] = 'Passed';
$string['failed'] = 'Failed';
$string['errormessage'] = 'Error message';
$string['backtocourse'] = 'Back to assignment';
$string['viewsubmission'] = 'View submission';

// New strings for execution results
$string['memoryused'] = 'Memory used';
$string['resultaccepted'] = 'Accepted';
$string['resultwronganswer'] = 'Wrong Answer';
$string['resultcompilationerror'] = 'Compilation Error';
$string['resulttimelimit'] = 'Time Limit Exceeded';
$string['resultmemorylimit'] = 'Memory Limit Exceeded';
$string['resultruntime'] = 'Runtime Error';
$string['expectedoutput'] = 'Expected output';
$string['actualoutput'] = 'Your output';
$string['compilationoutput'] = 'Compiler output';
$string['runtimeerror'] = 'Runtime error';

// Editor features
$string['codehint'] = 'Write your code here...';
$string['codelanguage'] = 'Programming language';
$string['autoindent'] = 'Auto-indent';
$string['tabsize'] = 'Tab size';
$string['linenumbers'] = 'Show line numbers';
$string['wordwrap'] = 'Word wrap';
$string['syntaxhighlighting'] = 'Syntax highlighting';
$string['darkmode'] = 'Dark mode';
$string['editorpreferences'] = 'Editor preferences';

// API/Backend messages
$string['apierror'] = 'Error communicating with grading service';
$string['retrying'] = 'Connection failed, retrying...';
$string['maxretries'] = 'Maximum retries reached, please try again later';
$string['simulationmode'] = 'Running in simulation mode (no actual code execution)';
$string['backenderror'] = 'The grading backend reported an error';
$string['submissionqueued'] = 'Your submission has been queued for grading';

// Contextual help and hints
$string['firstsubmissionadvice'] = 'Try submitting early to get feedback on your code';
$string['testcaseadvice'] = 'Make sure your code handles all possible inputs';
$string['formattingadvice'] = 'Format your output exactly as specified in the expected output';
$string['timelimitadvice'] = 'Your solution should run within the time limit';

// New UI strings
$string['nodescription'] = 'No description available for this assignment.';
$string['jumptotestcases'] = 'Jump to test cases';
$string['codeeditor'] = 'Code Editor';
$string['sidebar'] = 'Task Description';
$string['hideintro'] = 'Hide description';
$string['showintro'] = 'Show description';
$string['taskdescription'] = 'Task Description';

// Autosave strings
$string['autosavedsuccessfully'] = 'Autosaved';
$string['restoredautosave'] = 'Restored from autosave';
$string['restoresavedcode'] = 'Restore saved version';
$string['localsavedversionexists'] = 'A different saved version exists';
$string['secondsago'] = 'seconds ago';
$string['minutesago'] = 'minutes ago';
$string['hoursago'] = 'hours ago';
$string['daysago'] = 'days ago';

$string['notfound'] = 'Not found';

$string['mockresult'] = 'Mock result';

// Chuỗi cho trạng thái bài nộp
$string['submitted'] = 'Submitted';
$string['graded'] = 'Graded';
$string['processing'] = 'Your submission is being processed...';
$string['error'] = 'Error';
$string['failed'] = 'Failed';
$string['plagiarism'] = 'Plagiarism detected';
$string['plagiarism_detected'] = 'Plagiarism detected. Similarity score: {$a}%';
$string['plagiarism_details'] = 'View details: {$a}';
$string['view_plagiarism_report'] = 'View plagiarism report';

?> 