<?php
session_start();
require_once 'db_connection.php'; // Provides $pdo
require_once 'dal.php';         // Provides DAL functions

if (!isset($_GET['batch_id']) || !filter_var($_GET['batch_id'], FILTER_VALIDATE_INT)) {
    $_SESSION['error_message'] = "Invalid or missing batch ID.";
    header('Location: index.php'); // Or dashboard.php
    exit;
}
$batch_id = (int)$_GET['batch_id'];

$batchSettings = getReportBatchSettings($pdo, $batch_id);
if (!$batchSettings) {
    $_SESSION['error_message'] = "Could not find details for Batch ID: " . htmlspecialchars($batch_id);
    header('Location: index.php'); // Or dashboard.php
    exit;
}

// Fetch students and their raw scores for this batch
// getStudentsWithScoresForBatch returns students keyed by student_id, each with a 'subjects' array keyed by subject_code
$studentsWithScores = getStudentsWithScoresForBatch($pdo, $batch_id);

// Get a list of all unique subjects present in this batch for table headers
$uniqueSubjectCodesInBatch = [];
if (!empty($studentsWithScores)) {
    // Sort students by name for consistent display
    uasort($studentsWithScores, function($a, $b) {
        return strcmp($a['student_name'], $b['student_name']);
    });

    foreach ($studentsWithScores as $student) {
        if (!empty($student['subjects'])) {
            foreach ($student['subjects'] as $subjectCode => $details) {
                if (!isset($uniqueSubjectCodesInBatch[$subjectCode])) {
                    $uniqueSubjectCodesInBatch[$subjectCode] = $details['subject_name_full'];
                }
            }
        }
    }
    ksort($uniqueSubjectCodesInBatch); // Sort by subject code for consistent column order
}

// This map is for fallback if subject_name_full is not in the DB for some reason,
// or for consistent display if needed. The primary source should be $details['subject_name_full'].
$subjectDisplayNames = [
    'english' => 'English', 'mtc' => 'Mathematics (MTC)', 'science' => 'Science',
    'sst' => 'Social Studies (SST)', 'kiswahili' => 'Kiswahili',
    're' => 'Religious Education (R.E)', 'lit1' => 'Literacy I',
    'lit2' => 'Literacy II', 'local_lang' => 'Local Language'
];


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Processed Batch Data - <?php echo htmlspecialchars($batchSettings['class_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="images/logo.png">
    <link href="css/style.css" rel="stylesheet">
    <style>
        body { background-color: #e0f7fa; }
        .container.main-content {
            background-color: #fff;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-top: 20px;
            margin-bottom: 20px;
        }
        .table th, .table td { vertical-align: middle; text-align:center;}
        .table .student-name-col { text-align:left; }
        .sticky-top { top:0; z-index: 1020;} /* Ensure navbar is on top */
    </style>
</head>
<body>
    <nav class="navbar navbar-light bg-light sticky-top shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <img src="images/logo.png" alt="Logo" width="30" height="30" class="d-inline-block align-text-top me-2" onerror="this.style.display='none';">
                Maria Owembabazi P/S - Report System
            </a>
            <div>
                <a href="index.php" class="btn btn-outline-primary me-2"><i class="fas fa-plus-circle"></i> Import Another Batch</a>
                <a href="dashboard.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
            </div>
        </div>
    </nav>

    <div class="container main-content">
        <h2 class="mb-3">Processed Data for Batch ID: <?php echo htmlspecialchars($batch_id); ?></h2>

        <?php
        if (isset($_SESSION['error_message']) && !empty($_SESSION['error_message'])) {
            echo '<div class="alert alert-danger" role="alert">' . htmlspecialchars($_SESSION['error_message']) . '</div>';
            unset($_SESSION['error_message']);
        }
        if (isset($_SESSION['success_message']) && !empty($_SESSION['success_message'])) {
            echo '<div class="alert alert-success" role="alert">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
            unset($_SESSION['success_message']);
        }
        ?>

        <div class="card mb-4">
            <div class="card-header">
                <strong class="fs-5">Batch Details</strong>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Class:</strong> <?php echo htmlspecialchars($batchSettings['class_name']); ?></p>
                        <p><strong>Academic Year:</strong> <?php echo htmlspecialchars($batchSettings['year_name']); ?></p>
                        <p><strong>Term:</strong> <?php echo htmlspecialchars($batchSettings['term_name']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Term Ended On:</strong> <?php echo htmlspecialchars(date('d M Y', strtotime($batchSettings['term_end_date']))); ?></p>
                        <p><strong>Next Term Begins On:</strong> <?php echo htmlspecialchars(date('d M Y', strtotime($batchSettings['next_term_begin_date']))); ?></p>
                        <p><strong>Import Date:</strong> <?php echo htmlspecialchars(date('d M Y H:i:s', strtotime($batchSettings['import_date']))); ?></p>
                    </div>
                </div>
            </div>
            <div class="card-footer text-center">
                <a href="run_calculations.php?batch_id=<?php echo $batch_id; ?>" class="btn btn-warning me-2"><i class="fas fa-calculator"></i> Calculate Summaries & Auto-Remarks</a>
                <a href="generate_pdf.php?batch_id=<?php echo $batch_id; ?>" class="btn btn-danger me-2" target="_blank"><i class="fas fa-file-pdf"></i> Generate Full Class PDF Report</a>
                <a href="summary_sheet.php?batch_id=<?php echo $batch_id; ?>" class="btn btn-success me-2" target="_blank"><i class="fas fa-chart-bar"></i> View Class Summary Sheet</a>
            </div>
        </div>

        <h3 class="mt-4 mb-3">Student Raw Scores</h3>
        <?php if (!empty($studentsWithScores)): ?>
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover">
                    <thead class="table-light">
                        <tr>
                            <th rowspan="2" style="vertical-align:middle;">#</th>
                            <th rowspan="2" style="vertical-align:middle;" class="student-name-col">Student Name</th>
                            <th rowspan="2" style="vertical-align:middle;">LIN NO.</th>
                            <?php foreach ($uniqueSubjectCodesInBatch as $subjectCode => $subjectFullName): ?>
                                <th colspan="3" class="text-center"><?php echo htmlspecialchars($subjectFullName); ?></th>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <?php foreach ($uniqueSubjectCodesInBatch as $subjectCode => $subjectFullName): ?>
                                <th>BOT</th>
                                <th>MOT</th>
                                <th>EOT</th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $count = 0; foreach ($studentsWithScores as $studentId => $studentData): $count++; ?>
                            <tr>
                                <td><?php echo $count; ?></td>
                                <td class="student-name-col"><?php echo htmlspecialchars($studentData['student_name']); ?></td>
                                <td><?php echo htmlspecialchars($studentData['lin_no'] ?? 'N/A'); ?></td>
                                <?php foreach ($uniqueSubjectCodesInBatch as $subjectCode => $subjectFullName): ?>
                                    <?php
                                        $scores = $studentData['subjects'][$subjectCode] ?? null;
                                    ?>
                                    <td><?php echo htmlspecialchars($scores['bot_score'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($scores['mot_score'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($scores['eot_score'] ?? '-'); ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">No student scores found for this batch. The import might have been empty or encountered issues with specific files. Please verify the uploaded files for this batch.</div>
        <?php endif; ?>
    </div>

    <footer class="text-center mt-4 mb-3 p-3 bg-light">
        <p>&copy; <?php echo date('Y'); ?> Maria Owembabazi Primary School - <i>Good Christian, Good Citizen</i></p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
