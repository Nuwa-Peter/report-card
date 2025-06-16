<?php
session_start();
require_once 'db_connection.php';
require_once 'dal.php';
// calculation_utils.php is not directly used here, as calculations are assumed done by run_calculations.php

$batch_id = null;
$batchSettings = null;
$studentsSummaries = []; // Holds data from student_report_summary
$allScoresForBatch = []; // Placeholder - for detailed grade distribution if fetched from scores table

$allProcessedBatches = getAllProcessedBatches($pdo);

if (isset($_GET['batch_id']) && filter_var($_GET['batch_id'], FILTER_VALIDATE_INT)) {
    $batch_id = (int)$_GET['batch_id'];
    $batchSettings = getReportBatchSettings($pdo, $batch_id);
    if (!$batchSettings) {
        $_SESSION['error_message'] = "Summary Sheet: Could not find details for Batch ID: " . htmlspecialchars($batch_id);
        // Redirect to selection if batch not found, or show error on page
        header('Location: summary_sheet.php');
        exit;
    }
    $studentsSummaries = getAllStudentSummariesForBatchWithName($pdo, $batch_id);
    // For P4-P7 grade distribution, run_calculations.php stores enriched data in session.
    // This is an interim solution. Ideally, this data would be queried from the 'scores' table
    // once run_calculations.php updates it with bot_grade, mot_grade, eot_grade.
}

$isP1_P3 = false;
$isP4_P7 = false;
$coreSubjectKeysP4_P7 = [];
$p1p3SubjectKeys = [];

if ($batchSettings) {
    $isP1_P3 = in_array($batchSettings['class_name'], ['P1', 'P2', 'P3']);
    $isP4_P7 = in_array($batchSettings['class_name'], ['P4', 'P5', 'P6', 'P7']);
    if ($isP4_P7) {
        $coreSubjectKeysP4_P7 = ['english', 'mtc', 'science', 'sst'];
    }
    // p1p3SubjectKeys definition not strictly needed here as we iterate student summaries
}

$subjectDisplayNames = [
    'english' => 'English', 'mtc' => 'Mathematics (MTC)', 'science' => 'Science',
    'sst' => 'Social Studies (SST)', 'kiswahili' => 'Kiswahili',
    're' => 'Religious Education (R.E)', 'lit1' => 'Literacy I',
    'lit2' => 'Literacy II', 'local_lang' => 'Local Language'
];

// P4-P7 Summary Data Calculation (if batch is selected)
// UPDATED KEYS to match Roman numeral/letter output
$divisionSummaryP4P7 = [
    'I' => 0, 'II' => 0, 'III' => 0, 'IV' => 0,
    'U' => 0, 'X' => 0, 'Ungraded' => 0
];
$gradeSummaryP4P7 = []; // [subject_code => [grade => count]]

if ($isP4_P7 && $batch_id) {
    foreach ($studentsSummaries as $student) {
        // $student['p4p7_division'] will now be 'I', 'II', 'U', 'X' etc. from the database
        $division = $student['p4p7_division'] ?? 'Ungraded';
        if (array_key_exists($division, $divisionSummaryP4P7)) { // Use array_key_exists for safety
            $divisionSummaryP4P7[$division]++;
        } else {
            $divisionSummaryP4P7['Ungraded']++;
        }
    }

    // P4-P7 Grade Distribution (using session data as interim)
    $enrichedStudentDataForBatch = $_SESSION['enriched_students_data_for_batch_' . $batch_id] ?? [];
    if (!empty($enrichedStudentDataForBatch) && !empty($coreSubjectKeysP4_P7)) {
        foreach ($coreSubjectKeysP4_P7 as $coreSubKey) {
            $gradeSummaryP4P7[$coreSubKey] = ['D1'=>0, 'D2'=>0, 'C3'=>0, 'C4'=>0, 'C5'=>0, 'C6'=>0, 'P7'=>0, 'P8'=>0, 'F9'=>0, 'N/A'=>0];
            foreach ($enrichedStudentDataForBatch as $studentId => $studentEnriched) {
                 // Check if subject exists for student to avoid undefined index
                 $eotGrade = $studentEnriched['subjects'][$coreSubKey]['eot_grade'] ?? 'N/A';
                 if(isset($gradeSummaryP4P7[$coreSubKey][$eotGrade])) {
                    $gradeSummaryP4P7[$coreSubKey][$eotGrade]++;
                 } else {
                    $gradeSummaryP4P7[$coreSubKey]['N/A']++;
                 }
            }
        }
    }
}

// P1-P3 Summary Data Calculation (if batch is selected)
$p1p3StudentListForDisplay = [];
$classAverageP1P3 = 0;
if ($isP1_P3 && $batch_id) {
    $p1p3StudentListForDisplay = $studentsSummaries;
    uasort($p1p3StudentListForDisplay, function($a, $b) {
        return ($a['p1p3_position_in_class'] ?? PHP_INT_MAX) <=> ($b['p1p3_position_in_class'] ?? PHP_INT_MAX);
    });
    $totalClassAverageEotP1P3 = 0;
    $validStudentsForClassAverageP1P3 = 0;
    foreach ($studentsSummaries as $student) {
        if (isset($student['p1p3_average_eot_score']) && is_numeric($student['p1p3_average_eot_score'])) {
            $totalClassAverageEotP1P3 += $student['p1p3_average_eot_score'];
            $validStudentsForClassAverageP1P3++;
        }
    }
    $classAverageP1P3 = ($validStudentsForClassAverageP1P3 > 0) ? round($totalClassAverageEotP1P3 / $validStudentsForClassAverageP1P3, 2) : 0;
}

// For Chart Labels - map internal keys to more descriptive labels
$divisionChartLabels = [
    'I' => 'Division I', 'II' => 'Division II', 'III' => 'Division III', 'IV' => 'Division IV',
    'U' => 'Grade U', 'X' => 'Division X', 'Ungraded' => 'Ungraded'
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Summary Sheet<?php if ($batchSettings) echo " - " . htmlspecialchars($batchSettings['class_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="images/logo.png">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background-color: #e0f7fa; }
        .container.main-content { background-color: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-top: 20px; }
        .summary-table th, .summary-table td { text-align: center; vertical-align: middle;}
        .table-responsive { margin-bottom: 2rem; }
        h3, h4, h5 { margin-top: 1.5rem; color: #0056b3; }
        .print-button-container { margin-top: 20px; margin-bottom: 20px; text-align: right; }
        @media print {
            body { background-color: #fff; } .non-printable { display: none !important; }
            .container.main-content {box-shadow:none; border:none; margin-top:0; padding:5mm;}
            .table th, .table td {font-size: 9pt;} h3,h4,h5 {font-size: 12pt; margin-top:1rem;}
            canvas {max-width:100% !important; height:auto !important;}
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-light bg-light sticky-top shadow-sm non-printable">
        <!-- ... Navbar content (unchanged) ... -->
         <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <img src="images/logo.png" alt="Logo" width="30" height="30" class="d-inline-block align-text-top me-2" onerror="this.style.display='none';">
                Maria Owembabazi P/S - Report System
            </a>
            <a href="index.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </div>
    </nav>

    <div class="container main-content">
        <div class="text-center mb-4">
            <h2>Class Performance Summary</h2>
            <?php if ($batchSettings): ?>
                <h4><?php echo htmlspecialchars($batchSettings['class_name']); ?> - Term <?php echo htmlspecialchars($batchSettings['term_name']); ?>, <?php echo htmlspecialchars($batchSettings['year_name']); ?></h4>
            <?php endif; ?>
        </div>

        <div class="non-printable">
            <!-- ... Batch selection form (unchanged) ... -->
            <form method="GET" action="summary_sheet.php" class="row g-3 align-items-end mb-4">
                <div class="col-md-4">
                    <label for="batch_id_select" class="form-label">Select Processed Batch:</label>
                    <select name="batch_id" id="batch_id_select" class="form-select" required>
                        <option value="">-- Select a Batch --</option>
                        <?php foreach ($allProcessedBatches as $batchOption): ?>
                            <option value="<?php echo $batchOption['batch_id']; ?>" <?php if ($batch_id == $batchOption['batch_id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($batchOption['class_name'] . " - " . $batchOption['year_name'] . " Term " . $batchOption['term_name'] . " (ID: " . $batchOption['batch_id'] . ")"); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-eye"></i> View Summary</button>
                </div>
            </form>
             <hr>
        </div>
        <!-- ... Session messages (unchanged) ... -->

        <?php if ($batch_id && $batchSettings): // Only display if a batch is selected and found ?>
            <div class="print-button-container non-printable">
                <button onclick="window.print();" class="btn btn-info"><i class="fas fa-print"></i> Print Summary</button>
            </div>

            <?php if ($isP4_P7): ?>
                <h3>Division Summary</h3> <!-- Removed (P4-P7) from title for cleaner look -->
                <div class="row">
                    <div class="col-md-6 table-responsive">
                        <table class="table table-bordered table-sm summary-table">
                            <thead class="table-dark"><tr><th colspan="2">Division Performance</th></tr></thead>
                            <tbody>
                                <?php // UPDATED to use $divisionChartLabels for display
                                foreach ($divisionSummaryP4P7 as $divKey => $count):
                                    $displayLabel = $divisionChartLabels[$divKey] ?? $divKey; // Use descriptive label
                                ?>
                                    <tr><td><?php echo htmlspecialchars($displayLabel); ?></td><td><?php echo $count; ?></td></tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <canvas id="p4p7DivisionChart"></canvas>
                    </div>
                </div>
                <!-- ... P4-P7 Grade Distribution section (largely unchanged, uses D1-F9 grades) ... -->
                 <h3>Subject Grade Distribution</h3>
                <?php if (!empty($gradeSummaryP4P7)): ?>
                    <?php foreach ($coreSubjectKeysP4_P7 as $coreSubKey): ?>
                         <?php $subjectDisplayName = htmlspecialchars($subjectDisplayNames[$coreSubKey] ?? ucfirst($coreSubKey)); ?>
                        <h5><?php echo $subjectDisplayName; ?></h5>
                        <div class="row">
                            <div class="col-md-7 table-responsive">
                                <table class="table table-sm table-bordered summary-table">
                                    <thead class="table-light">
                                        <tr>
                                            <?php if(isset($gradeSummaryP4P7[$coreSubKey])) { foreach (array_keys($gradeSummaryP4P7[$coreSubKey]) as $grade): ?>
                                                <th><?php echo htmlspecialchars($grade); ?></th>
                                            <?php endforeach; } ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <?php if(isset($gradeSummaryP4P7[$coreSubKey])) { foreach ($gradeSummaryP4P7[$coreSubKey] as $count): ?>
                                                <td><?php echo $count; ?></td>
                                            <?php endforeach; } else { echo "<td colspan='9'>No grade data.</td>";} ?>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="col-md-5">
                                <canvas id="chart_<?php echo $coreSubKey; ?>"></canvas>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted">Per-subject grade distribution data not available. Ensure calculations ran and data is in session.</p>
                <?php endif; ?>

            <?php elseif ($isP1_P3): ?>
                <!-- ... P1-P3 summary table and chart canvas (unchanged) ... -->
                 <h3>Performance Summary (P1-P3)</h3>
                <p><strong>Overall Class Average End of Term Score:</strong> <?php echo htmlspecialchars($classAverageP1P3); ?>%</p>
                <div class="row">
                    <div class="col-md-7 table-responsive">
                        <table class="table table-striped table-hover summary-table">
                            <thead class="table-primary">
                                <tr><th>#</th><th>Student Name</th><th>Total EOT Score</th><th>Average EOT Score (%)</th><th>Position</th></tr>
                            </thead>
                            <tbody>
                                <?php $rowNum = 0; foreach ($p1p3StudentListForDisplay as $student): $rowNum++; ?>
                                <tr>
                                    <td><?php echo $rowNum; ?></td>
                                    <td><?php echo htmlspecialchars($student['student_name']); ?></td>
                                    <td><?php echo htmlspecialchars($student['p1p3_total_eot_score'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($student['p1p3_average_eot_score'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($student['p1p3_position_in_class'] ?? 'N/A'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="col-md-5">
                         <canvas id="p1p3AverageDistributionChart"></canvas>
                         <p class="text-center small mt-2">Distribution of Average Scores</p>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <!-- ... select batch message (unchanged) ... -->
        <?php endif; ?>
    </div>
    <!-- ... footer ... -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    <?php if ($batch_id && $batchSettings && $isP4_P7): ?>
    const divCtx = document.getElementById('p4p7DivisionChart');
    if (divCtx) {
        let rawDivisionKeys = <?php echo json_encode(array_keys($divisionSummaryP4P7)); ?>;
        let divisionData = <?php echo json_encode(array_values($divisionSummaryP4P7)); ?>;
        let descriptiveDivisionLabelsMap = <?php echo json_encode($divisionChartLabels); ?>; // PHP map

        let filteredDisplayLabels = [];
        let filteredData = [];
        rawDivisionKeys.forEach((key, index) => {
            if (divisionData[index] > 0) {
                filteredDisplayLabels.push(descriptiveDivisionLabelsMap[key] || key); // Use descriptive label
                filteredData.push(divisionData[index]);
            }
        });

        if (filteredData.length > 0) {
            new Chart(divCtx, {
                type: 'pie',
                data: {
                    labels: filteredDisplayLabels, // UPDATED to use descriptive labels
                    datasets: [{
                        label: 'Division Distribution',
                        data: filteredData,
                        backgroundColor: ['#28a745', '#17a2b8', '#ffc107', '#fd7e14', '#6f42c1', '#dc3545', '#adb5bd'],
                        hoverOffset: 4
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'top' } } }
            });
        }
    }
    // ... P4-P7 Grade Chart JS (unchanged, uses D1-F9 which is fine) ...
    <?php if (!empty($gradeSummaryP4P7)): ?>
        <?php foreach ($coreSubjectKeysP4_P7 as $coreSubKey): ?>
            <?php if (isset($gradeSummaryP4P7[$coreSubKey])):
                $grades = array_keys($gradeSummaryP4P7[$coreSubKey]);
                $counts = array_values($gradeSummaryP4P7[$coreSubKey]);
                $filteredGrades = []; $filteredCounts = [];
                foreach($grades as $idx => $gradeKey) {
                    if($counts[$idx] > 0) { $filteredGrades[] = $gradeKey; $filteredCounts[] = $counts[$idx];}
                }
            ?>
            const gradeCtx_<?php echo $coreSubKey; ?> = document.getElementById('chart_<?php echo $coreSubKey; ?>');
            if (gradeCtx_<?php echo $coreSubKey; ?> && <?php echo json_encode(!empty($filteredGrades)); ?>) {
                new Chart(gradeCtx_<?php echo $coreSubKey; ?>, { /* ... chart config ... */
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode($filteredGrades); ?>,
                        datasets: [{
                            label: 'Grade Distribution for <?php echo htmlspecialchars($subjectDisplayNames[$coreSubKey] ?? ucfirst($coreSubKey)); ?>',
                            data: <?php echo json_encode($filteredCounts); ?>,
                            backgroundColor: 'rgba(0, 123, 255, 0.5)',
                            borderColor: 'rgba(0, 123, 255, 1)',
                            borderWidth: 1
                        }]
                    }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
                });
            }
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
    <?php elseif ($batch_id && $batchSettings && $isP1_P3 && !empty($p1p3StudentListForDisplay)): ?>
    // ... P1-P3 Chart JS (unchanged) ...
    const p1p3AvgCtx = document.getElementById('p1p3AverageDistributionChart');
    if (p1p3AvgCtx) {
        const averages = <?php echo json_encode(array_column($p1p3StudentListForDisplay, 'p1p3_average_eot_score')); ?>;
        let scoreRanges = {'0-39':0, '40-49':0, '50-59':0, '60-69':0, '70-79':0, '80-89':0, '90-100':0};
        averages.forEach(avg => {
            if(avg === null || avg === 'N/A') return;
            let numericAvg = parseFloat(avg);
            if(numericAvg >= 90) scoreRanges['90-100']++;
            else if(numericAvg >= 80) scoreRanges['80-89']++;
            else if(numericAvg >= 70) scoreRanges['70-79']++;
            else if(numericAvg >= 60) scoreRanges['60-69']++;
            else if(numericAvg >= 50) scoreRanges['50-59']++;
            else if(numericAvg >= 40) scoreRanges['40-49']++;
            else scoreRanges['0-39']++;
        });
        if (Object.values(scoreRanges).some(count => count > 0)) { // Only create chart if there's data
            new Chart(p1p3AvgCtx, {
                type: 'bar',
                data: {
                    labels: Object.keys(scoreRanges),
                    datasets: [{
                        label: 'Average Score Distribution',
                        data: Object.values(scoreRanges),
                        backgroundColor: 'rgba(75, 192, 192, 0.5)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 1
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
            });
        }
    }
    <?php endif; ?>
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body></html>
