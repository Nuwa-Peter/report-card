<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Optional: Set a flash message for the login page
    // $_SESSION['login_error_message'] = "You must be logged in to access this page.";
    header('Location: login.php');
    exit;
}

// Original PHP code from about.php (like date_default_timezone_set) follows here
date_default_timezone_set('Africa/Kampala'); // Or user's preferred timezone
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About - Report System - Maria Ow'embabazi P/S</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="images/logo.png">
    <style>
        body { background-color: #e0f7fa; /* Matching dashboard theme */ }
        .container.main-content {
            background-color: #fff;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-top: 20px;
            margin-bottom: 30px;
        }
        .about-header {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #dee2e6;
        }
        .about-header img {
            width: 70px; /* Consistent with dashboard sidebar logo size */
            height: 70px;
            border-radius: 50%;
            margin-bottom: 10px;
            border: 2px solid #007bff;
        }
        .about-header h2 {
            color: #0056b3;
            margin-bottom: 0.5rem;
        }
        .about-header .datetime-display {
            font-size: 0.9em;
            color: #6c757d;
        }
        .section-title {
            color: #0056b3;
            margin-top: 2rem;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #007bff;
            display: inline-block;
        }
        .credits {
            margin-top: 2.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #eee;
            font-size: 0.9em;
            color: #555;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-light bg-light sticky-top shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <img src="images/logo.png" alt="Logo" width="30" height="30" class="d-inline-block align-text-top me-2" onerror="this.style.display='none';">
                Maria Ow'embabazi P/S - Report System
            </a>
            <div>
                <a href="index.php" class="btn btn-outline-secondary me-2"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
                <a href="logout.php" class="btn btn-outline-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </nav>

    <div class="container main-content">
        <div class="about-header">
            <img src="images/logo.png" alt="School Logo" onerror="this.style.display='none';">
            <h2>About This Report System</h2>
            <p class="datetime-display">Current Date & Time: <?php echo date("D, d M Y H:i:s"); ?></p>
        </div>

        <section id="school-contacts">
            <h3 class="section-title">School Contacts</h3>
            <p><strong>Maria Ow'embabazi Primary School</strong></p>
            <p>P.O BOX 406, MBARARA</p>
            <p>Tel. 0700172858</p>
            <p>Email: houseofnazareth.schools@gmail.com</p>
            <!-- Add more contact details as needed -->
        </section>

        <section id="about-system">
            <h3 class="section-title">About the Report Card System</h3>
            <p>This system is designed to streamline the generation and management of student academic reports for Maria Ow'embabazi Primary School. It allows for the importation of student scores from Excel files, calculation of termly performance metrics, and generation of printable PDF report cards and summary sheets.</p>
            <p>Key features include:</p>
            <ul>
                <li>Batch processing of student marks from standardized Excel templates.</li>
                <li>Automatic calculation of aggregates, divisions (for P4-P7), averages, and class positions (for P1-P3).</li>
                <li>Automated generation of teacher and headteacher remarks based on performance.</li>
                <li>Persistent storage of processed data in a database for historical access.</li>
                <li>A user-friendly dashboard for navigation and accessing various functionalities.</li>
                <li>Generation of individual and class-wide PDF report cards.</li>
                <li>Class performance summary sheets with visual charts.</li>
            </ul>
        </section>

        <section id="help-guide">
            <h3 class="section-title">Help & Navigation Guide</h3>
            <p><strong>Dashboard:</strong> The main entry point. Use the sidebar to navigate.</p>

            <h5><i class="fas fa-file-excel me-2"></i>Download Marks Entry Template</h5>
            <ol>
                <li>Navigate to "Download Marks Entry Template" from the sidebar.</li>
                <li>Click the download link/button to get the Excel file (`student_marks_template.xlsx`).</li>
                <li>Open the template in Microsoft Excel or a compatible spreadsheet program.</li>
                <li><strong>Crucial Formatting:</strong>
                    <ul>
                        <li>Enter the Subject Name (e.g., "ENGLISH", "MATHEMATICS") exactly in cell A1. This is case-sensitive and must match system subject codes if applicable for some internal logic, or be consistent.</li>
                        <li>Column headers in row 1 should be: Student Name (B1), B.O.T Score (C1), M.O.T Score (D1), E.O.T Score (E1). (Adjust if template is different, this is an example).</li>
                        <li>Student names (starting from cell B2) must be in ALL CAPS.</li>
                        <li>Enter scores for Beginning of Term (B.O.T), Mid of Term (M.O.T), and End of Term (E.O.T) in the respective columns. Scores should be numerical (0-100). Leave blank or use 'N/A' if a score is not available.</li>
                    </ul>
                </li>
                <li>Save the completed file for each subject for the class.</li>
            </ol>

            <h5><i class="fas fa-edit me-2"></i>Marks Entry (Importing Scores)</h5>
            <ol>
                <li>Navigate to "Marks Entry" from the sidebar.</li>
                <li>Select the correct Class, Year, and Term from the dropdown menus.</li>
                <li>Enter the "This Term Ended On" and "Next Term Begins On" dates.</li>
                <li>For each subject taught in the selected class:
                    <ul>
                        <li>Click "Choose File" next to the subject name (e.g., English Results).</li>
                        <li>Select the corresponding completed Excel marks file you prepared for that subject.</li>
                        <li>Enter the Teacher's Initials for that subject (e.g., J.D.).</li>
                    </ul>
                </li>
                <li>Once all subject files and initials are provided for the class configuration, click the "Process & Save Data" button at the bottom.</li>
                <li>The system will validate the files and save the scores. You should see a success or error message.</li>
                <li>If successful, a link to "View Details for Processed Batch ID: X" will appear. Click this to proceed to the next step.</li>
            </ol>

            <h5><i class="fas fa-archive me-2"></i>View Report Archives & Process Data</h5>
            <ol>
                <li>Navigate to "View Report Archives" from the sidebar.</li>
                <li>You can use the filters (Year, Term, Class) to find specific batches or view all. Click "Filter".</li>
                <li>Each processed batch will be listed with its details and action buttons.</li>
                <li>For a batch that has had its marks imported but not yet fully processed:
                    <ul>
                        <li>Click the "<i class="fas fa-cogs"></i> Process/View Data" button. This takes you to the `view_processed_data.php` page.</li>
                        <li>On this page, review the imported scores if needed.</li>
                        <li><strong>Crucially, click the "<i class="fas fa-calculator"></i> Run Calculations & Auto-Remarks" button.</strong> This performs all necessary calculations (averages, positions, aggregates, divisions) and generates automated teacher/headteacher remarks. This step must be completed before generating final reports or summaries.</li>
                        <li>You should see a success message once calculations are done.</li>
                    </ul>
                </li>
                <li>Once calculations are complete for a batch, you can use the other action buttons from "View Report Archives" (or often from `view_processed_data.php` as well):
                    <ul>
                        <li>"<i class="fas fa-file-alt"></i> View PDF": Opens the combined PDF report for all students in the batch in a new browser tab.</li>
                        <li>"<i class="fas fa-file-pdf"></i> Download PDF": Downloads the combined PDF report.</li>
                        <li>"<i class="fas fa-chart-bar"></i> Summary": Takes you to the `summary_sheet.php` for that batch.</li>
                    </ul>
                </li>
            </ol>

            <h5><i class="fas fa-chart-pie me-2"></i>Summary Sheets</h5>
            <ol>
                <li>Navigate to "Summary Sheets" from the sidebar, or click the "Summary" button for a batch from "View Report Archives."</li>
                <li>If navigating directly, select the desired processed batch from the dropdown and click "View Summary."</li>
                <li>The page will display overall class performance, including charts and lists depending on the class level (P1-P3 or P4-P7).</li>
                <li>Ensure calculations have been run for the batch for the summary to be accurate.</li>
            </ol>
            <!-- General Tips integrated or covered above -->
        </section>

        <section id="troubleshooting">
            <h3 class="section-title mt-4">Troubleshooting / Common Issues</h3>
            <ul>
                <li><strong>Error during Excel Upload/Processing:</strong>
                    <ul>
                        <li>Ensure your Excel file strictly follows the template format (Subject Name in A1, specific headers, ALL CAPS student names).</li>
                        <li>Check for any non-numeric values in score columns where numbers are expected.</li>
                        <li>Make sure file is `.xlsx` format.</li>
                    </ul>
                </li>
                <li><strong>Reports/Summaries are empty or show old data:</strong>
                    <ul>
                        <li>Always ensure you have clicked "Run Calculations & Auto-Remarks" for the specific batch after importing marks. This is found via "View Report Archives" -> "Process/View Data".</li>
                    </ul>
                </li>
                <li><strong>PDF Not Generating / Error in PDF:</strong>
                    <ul>
                        <li>Confirm calculations were run.</li>
                        <li>If errors persist, note the error message and contact system support/developer.</li>
                    </ul>
                </li>
                <li><strong>Cannot find a Batch:</strong>
                    <ul>
                        <li>Use the filters in "View Report Archives". If still not found, it may not have been imported, or it was deleted.</li>
                    </ul>
                </li>
            </ul>
        </section>

        <section id="credits" class="credits">
            <h3 class="section-title">Credits</h3>
            <p>This system was designed and developed by:</p>
            <ul>
                <li><strong>Peter Nuwahereza</strong> (nuwapeter2013@gmail.com)
                    <ul><li><em>GitHub: [User to provide GitHub info if desired]</em></li></ul>
                </li>
                <li>with the assistance of <strong>Jules (AI Agent)</strong>.</li>
            </ul>
            <p>We hope this system serves Maria Ow'embabazi Primary School effectively!</p>
        </section>

    </div>

    <footer class="text-center mt-5 mb-3 p-3 non-printable" style="background-color: #f8f9fa;">
        <p>&copy; <?php echo date('Y'); ?> Maria Ow'embabazi Primary School - <i>Good Christian, Good Citizen</i></p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
