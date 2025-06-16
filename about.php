<?php
session_start();
date_default_timezone_set('Africa/Kampala'); // Or user's preferred timezone
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About - Report System - Maria Owembabazi P/S</title>
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
                Maria Owembabazi P/S - Report System
            </a>
            <a href="index.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
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
            <p><strong>Maria Owembabazi Primary School</strong></p>
            <p>P.O BOX 406, MBARARA</p>
            <p>Tel. 0700172858</p>
            <p>Email: houseofnazareth.schools@gmail.com</p>
            <!-- Add more contact details as needed -->
        </section>

        <section id="about-system">
            <h3 class="section-title">About the Report Card System</h3>
            <p>This system is designed to streamline the generation and management of student academic reports for Maria Owembabazi Primary School. It allows for the importation of student scores from Excel files, calculation of termly performance metrics, and generation of printable PDF report cards and summary sheets.</p>
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
            <ul>
                <li><strong>Download Marks Entry Template:</strong> Get the Excel template to fill in student marks. Ensure student names are in ALL CAPS and subject name is in cell A1 of each subject file.</li>
                <li><strong>Marks Entry:</strong> Upload the completed Excel files (one per subject) for a specific class, year, and term. Fill in all required information. Data is saved to the database.</li>
                <li><strong>View Report Archives:</strong> After data is processed, use this section to find past report batches. You can filter by year, term, or class. From here, for each batch, you can:
                    <ul>
                        <li>Click "Process/View Data": This takes you to a page showing the raw imported scores. On *that* page, click "Run Calculations & Auto-Remarks" to compute all summaries and remarks for the batch. This step is crucial.</li>
                        <li>Once calculations are run, you can then use "View PDF", "Download PDF", or "Summary" for that batch.</li>
                    </ul>
                </li>
                <li><strong>Summary Sheets:</strong> Access overall class performance summaries. Select a batch to view details. Ensure calculations have been run for the batch first.</li>
            </ul>
            <p><strong>General Tips:</strong></p>
            <ul>
                <li>Ensure all Excel files are correctly formatted as per the provided template before uploading.</li>
                <li>Always run calculations for a batch via "View Report Archives" -> "Process/View Data" -> "Run Calculations & Auto-Remarks" before attempting to generate final PDFs or detailed summaries.</li>
                <li>If you encounter any errors, check the messages displayed.</li>
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
            <p>We hope this system serves Maria Owembabazi Primary School effectively!</p>
        </section>

    </div>

    <footer class="text-center mt-5 mb-3 p-3 non-printable" style="background-color: #f8f9fa;">
        <p>&copy; <?php echo date('Y'); ?> Maria Owembabazi Primary School - <i>Good Christian, Good Citizen</i></p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
