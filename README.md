# DevCode - Moodle Coding Assignment Module

DevCode is a Moodle activity module that allows instructors to create coding assignments that can be automatically graded using predefined test cases. The module supports multiple programming languages, plagiarism detection, student dashboards with performance statistics, and a course leaderboard.

## Features

### For Teachers
- Create coding assignments with detailed instructions
- Define test cases with inputs, expected outputs, and weights
- Support for multiple programming languages (Python, Java, C++, JavaScript, PHP)
- Set submission limits and due dates
- View student submissions and detailed grading reports
- Plagiarism detection to identify similar code between submissions
- Teacher dashboard with class statistics and student performance metrics
- Identify top performers and at-risk students

### For Students
- Submit code via a built-in editor or file upload
- Immediate feedback with test case results
- See which test cases passed or failed
- Track progress through the student dashboard
- View performance statistics and submission history
- Compare performance with peers via the leaderboard (if enabled)

## Installation

1. Download the module
2. Extract the contents to the `mod/devcode` directory of your Moodle installation
3. Visit the admin notifications page to complete the installation
4. Configure the module settings as needed

## Usage

### Creating a DevCode Assignment

1. Turn editing on in your course
2. Click "Add an activity or resource" and select "DevCode"
3. Fill in the assignment details:
   - Name and description
   - Programming language
   - Due date and submission limits
   - Plagiarism detection settings (optional)
   - Leaderboard settings (optional)
4. Add test cases with:
   - Input data
   - Expected output
   - Weight (importance in grading)
   - Visibility (hidden or visible to students)
5. Save the assignment

### Submitting an Assignment (Student View)

1. Navigate to the DevCode assignment
2. Read the instructions and examples
3. Write your code in the editor or upload a file
4. Submit your solution
5. View the results of the test cases
6. Make additional submissions if allowed

### Viewing Dashboards

#### Student Dashboard
- Access via "View Dashboard" link on assignment page
- Shows performance across all DevCode assignments in the course
- Displays statistics, charts, and recent submissions

#### Teacher Dashboard
- Access via "Teacher Dashboard" link on assignment page
- Shows class statistics, submission rates, and score distributions
- Identifies students who may need additional help

### Plagiarism Detection

- When enabled, automatically checks for code similarity between submissions
- Submissions above the similarity threshold are flagged for review
- Teachers can view comparison reports with highlighted similarities
- Side-by-side and line-by-line comparison views available

### Leaderboard

- When enabled, ranks students based on assignment performance
- Can be anonymous to protect student privacy
- Various ranking metrics available (best score, completion time, etc.)

## Requirements

- Moodle 4.1 or higher
- PHP 7.4 or higher
- Appropriate execution environment for the programming languages used

## License

This plugin is licensed under the [GNU GPL v3 or later](http://www.gnu.org/copyleft/gpl.html)

## Credits

Developed by [Your Name/Organization]

## Support

For support or to report issues, please visit [your support URL or contact information]# devcode-plugin
