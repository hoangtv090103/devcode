# Moodle Activity Plugin: DevCode

## Overview

DevCode is a Moodle activity module designed for creating, submitting, and automatically grading programming assignments. It allows instructors to set up coding problems with specific test cases and leverages external services like Judge0 for code execution and grading, and Dolos for plagiarism detection.

## Features

*   **Create Programming Assignments:** Define assignment descriptions, set due dates, and select the programming language.
*   **Test Case Management:** Define multiple test cases with specific inputs, expected outputs, points, execution time limits, and visibility (visible to students or hidden).
*   **Code Submission:** Students can submit their solutions either by typing/pasting code directly into an editor or by uploading a source file.
*   **Automated Grading:** Submissions are sent to a configured Judge0 API instance for execution against the defined test cases. Results (Accepted, Wrong Answer, Time Limit Exceeded, Compilation Error, etc.) are processed, and a score is calculated based on passed test cases and assigned points.
*   **Plagiarism Detection (Optional):** Integrates with Dolos to compare submissions against each other and generate similarity reports. Teachers can review potentially plagiarised submissions.
*   **Detailed Results:** Students and teachers can view detailed results, including overall status, score, feedback, output from each test case, and links to plagiarism reports (if applicable).
*   **Submission Management:** Teachers can view all submissions for an assignment, access individual results, and review plagiarism reports.

## Installation

1.  **Prerequisites:**
    *   A running Moodle instance (Version X.Y or later - *Update with your Moodle version compatibility*).
    *   Access to a running [Judge0 API](https://judge0.com/) instance (either self-hosted or the cloud version). You will need the API endpoint URL and potentially an API key.
    *   (Optional) Access to a running [Dolos](https://dolos.ugent.be/) instance or the Dolos command-line tool installed on the Moodle server if you want to use plagiarism detection.
2.  **Download/Clone:** Place the `devcode` plugin folder into the `/mod/` directory of your Moodle installation.
    ```bash
    cd /path/to/your/moodle/htdocs/mod
    git clone <your-plugin-repo-url> devcode
    # OR copy the devcode folder here
    ```
3.  **Upgrade Moodle Database:** Log in to Moodle as an administrator. Go to `Site administration` > `Notifications`. Moodle will detect the new plugin and prompt you to upgrade the Moodle database. Follow the on-screen instructions.
4.  **Configure Plugin Settings:**
    *   Navigate to `Site administration` > `Plugins` > `Activity modules` > `DevCode`.
    *   Enter your Judge0 API endpoint URL (and API key if required).
    *   (Optional) Configure the path to the Dolos executable or API endpoint if using plagiarism detection.
    *   Save changes.

## Configuration

### Global Settings (Admin)

Located under `Site administration` > `Plugins` > `Activity modules` > `DevCode`.

*   **Judge0 API Endpoint:** The base URL of your Judge0 API instance (e.g., `http://localhost:2358`).
*   **Judge0 API Key:** (Optional) If your Judge0 instance requires authentication.
*   **Dolos Path/Endpoint:** (Optional) Path to the Dolos executable or the API endpoint for plagiarism checks.

### Activity Settings (Teacher)

When adding or editing a DevCode activity within a course:

*   **Name & Description:** Standard Moodle activity settings.
*   **Language:** Select the programming language allowed for submissions.
*   **Due Date:** Optional deadline for submissions.
*   **Enable Plagiarism Detection:** Checkbox to enable Dolos checks for this activity.
*   **Plagiarism Threshold:** (If enabled) The similarity percentage above which submissions are flagged.
*   **Test Cases:** Add, edit, or delete test cases (Input, Output, Points, Time Limit, Visibility).

## Folder Structure

A brief overview of the key directories and files within `mod/devcode/`:

```plaintext
.
├── amd/
│   └── src/            # JavaScript modules (e.g., code editor interactions, tabs).
├── classes/
│   ├── form/           # Form definitions (e.g., submission_form.php).
│   ├── event/          # Event definitions for Moodle's event system.
│   └── task/           # Scheduled task definitions.
│                       # Other core PHP classes...
├── db/
│   ├── install.xml     # Database table definitions for installation.
│   ├── upgrade.php     # Handles database schema changes during upgrades.
│   ├── events.php      # Defines event observers.
│   └── services.php    # Defines web services.
├── includes/           # Potentially reusable PHP code snippets or helper files.
├── lang/
│   └── en/
│       └── mod_devcode.php # English language strings.
│   # Add other language packs (e.g., vi/) as needed.
├── pix/
│   └── icon.svg        # Plugin icon.
│   # Other images...
├── templates/          # Moodle Mustache templates for UI rendering.
├── performance_tests/  # Scripts or definitions for performance testing.
├── .git/               # Git version control directory (usually hidden).
├── .DS_Store           # macOS system file (should be ignored).
├── README.md           # This file: Plugin overview, installation, etc.
├── apilib.php          # Base API interaction logic (potentially).
├── batch_process.php   # Logic for background/bulk processing.
├── batch_processing.php# Logic for background/bulk processing tasks.
├── compare_submissions.php # Compares two submissions (plagiarism view).
├── config.php          # Specific configuration loading for the module (if any).
├── constants.php       # Defines constants used within the plugin.
├── create_assignments.php # Script for bulk assignment creation/setup.
├── dolos_lib.php       # Functions for interacting with Dolos.
├── export_testcases.php# Logic for exporting test cases.
├── gradelib.php        # Functions related to Moodle Gradebook integration.
├── grades_util.php     # Utility functions for grading.
├── judge0_api.php      # Core logic for interacting with the Judge0 API.
├── lib.php             # Main library file with core plugin functions.
├── locallib.php        # Local library for module-specific functions.
├── mod_form.php        # Main form definition for activity creation/editing.
├── plagiarism_action.php # Handles teacher actions (flag/pass) on plagiarism reports.
├── plagiarism_report.php # Displays the list of potential plagiarism cases.
├── plagiarism_report_detail.php # Shows detailed comparison of plagiarised submissions.
├── plagiarismlib.php   # Core logic for managing plagiarism checks and results.
├── settings.php        # Defines the administrative settings page.
├── styles.css          # CSS styles for the plugin.
├── submit.php          # Handles the student submission process.
├── submissions.php     # Teacher's view to list all submissions.
├── version.php         # Contains the plugin version information.
├── view.php            # Main page students see when accessing the activity.
└── view_result.php     # Displays detailed grading results for a submission.
```

