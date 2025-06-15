<?php
session_start();

if (!isset($_SESSION['report_data']) || empty($_SESSION['report_data']['students'])) {
    $_SESSION['error_message'] = 'No report data found to generate a summary. Please process student data first.';
    header('Location: index.php');
    exit;
}

$reportData = $_SESSION['report_data'];
$students = $reportData['students'];
$classInfo = $reportData['class_info'];
$isP1_P3 = $reportData['is_p1_p3'] ?? false;
$isP4_P7 = $reportData['is_p4_p7'] ?? false;
$coreSubjectKeysP4_P7 = $reportData['core_subjects_p4p7'] ?? [];
// $p1p3SubjectKeys = $reportData['p1p3_subject_keys'] ?? []; // Not directly needed for summary if iterating students
// $gradingScalePointsMap = $reportData['grading_scale_points_map'] ?? []; // Not directly needed for this summary logic

$subjectDisplayNames = [ /* ... same map as in report_card.php ... */
    'english' => 'English', 'mtc' => 'Mathematics (MTC)', 'science' => 'Science',
    'sst' => 'Social Studies (SST)', 'kiswahili' => 'Kiswahili', // Though Kiswahili not in core P4P7 summary
    're' => 'Religious Education (R.E)', 'lit1' => 'Literacy One',
    'lit2' => 'Literacy Two', 'local_lang' => 'Local Language'
];

// P4-P7 Summary Data Calculation
$divisionSummaryP4P7 = [
    'Division One' => 0, 'Division Two' => 0, 'Division Three' => 0, 'Division Four' => 0,
    'Grade U' => 0, 'Division X' => 0, 'Ungraded' => 0
];
$gradeSummaryP4P7 = []; // Will be [subject_key => [grade => count]]

if ($isP4_P7) {
    foreach ($students as $student) {
        $division = $student['division_p4p7'] ?? 'Ungraded';
        if (isset($divisionSummaryP4P7[$division])) {
            $divisionSummaryP4P7[$division]++;
        } else {
            $divisionSummaryP4P7['Ungraded']++; // Catch any unexpected division values
        }

        foreach ($coreSubjectKeysP4_P7 as $coreSubKey) {
            if (!isset($gradeSummaryP4P7[$coreSubKey])) {
                $gradeSummaryP4P7[$coreSubKey] = ['D1'=>0, 'D2'=>0, 'C3'=>0, 'C4'=>0, 'C5'=>0, 'C6'=>0, 'P7'=>0, 'P8'=>0, 'F9'=>0, 'N/A'=>0];
            }
            $eotGrade = $student['subjects'][$coreSubKey]['eot_grade'] ?? 'N/A';
            if (isset($gradeSummaryP4P7[$coreSubKey][$eotGrade])) {
                $gradeSummaryP4P7[$coreSubKey][$eotGrade]++;
            } else {
                 $gradeSummaryP4P7[$coreSubKey]['N/A']++; // Should not happen if grades are always one of the defined set
            }
        }
    }
}

// P1-P3 Summary Data Calculation
$p1p3StudentList = [];
$totalClassAverageEotP1P3 = 0;
$validStudentsForClassAverageP1P3 = 0;

if ($isP1_P3) {
    $p1p3StudentList = $students; // Already has name, total_eot_p1p3, average_score_p1p3, position_p1p3
    // Sort by position for display
    uasort($p1p3StudentList, function($a, $b) {
        return ($a['position_p1p3'] ?? PHP_INT_MAX) <=> ($b['position_p1p3'] ?? PHP_INT_MAX);
    });

    foreach ($students as $student) {
        if (isset($student['average_score_p1p3']) && is_numeric($student['average_score_p1p3'])) {
            $totalClassAverageEotP1P3 += $student['average_score_p1p3'];
            $validStudentsForClassAverageP1P3++;
        }
    }
    $classAverageP1P3 = ($validStudentsForClassAverageP1P3 > 0) ? round($totalClassAverageEotP1P3 / $validStudentsForClassAverageP1P3, 2) : 0;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Summary Sheet - <?php echo htmlspecialchars($classInfo['selectedClass'] ?? ''); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <style>
        .summary-table { margin-top: 20px; }
        .summary-table th, .summary-table td { text-align: center; vertical-align: middle;}
        .table-responsive { margin-bottom: 30px; }
        h3 { margin-top: 30px; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="text-center mb-4">
            <h2>Class Summary Sheet</h2>
            <h4><?php echo htmlspecialchars($classInfo['selectedClass'] ?? ''); ?> - Term <?php echo htmlspecialchars($classInfo['term'] ?? ''); ?>, <?php echo htmlspecialchars($classInfo['year'] ?? ''); ?></h4>
        </div>

        <?php if ($isP4_P7): ?>
            <h3>P4-P7 Division Summary</h3>
            <div class="table-responsive">
                <table class="table table-bordered table-striped summary-table">
                    <thead class="table-dark">
                        <tr>
                            <?php foreach (array_keys($divisionSummaryP4P7) as $divName): ?>
                                <th><?php echo htmlspecialchars($divName); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <?php foreach ($divisionSummaryP4P7 as $count): ?>
                                <td><?php echo $count; ?></td>
                            <?php endforeach; ?>
                        </tr>
                    </tbody>
                </table>
            </div>

            <h3>P4-P7 Core Subject Grade Summary</h3>
            <?php foreach ($coreSubjectKeysP4_P7 as $coreSubKey): ?>
                <h5><?php echo htmlspecialchars($subjectDisplayNames[$coreSubKey] ?? ucfirst($coreSubKey)); ?></h5>
                <div class="table-responsive">
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
                                <?php endforeach; } ?>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>

        <?php elseif ($isP1_P3): ?>
            <h3>P1-P3 Performance Summary</h3>
            <p><strong>Overall Class Average EOT Score:</strong> <?php echo htmlspecialchars($classAverageP1P3); ?>%</p>
            <div class="table-responsive">
                <table class="table table-striped table-hover summary-table">
                    <thead class="table-primary">
                        <tr>
                            <th>#</th>
                            <th>Student Name</th>
                            <th>Total EOT Score</th>
                            <th>Average EOT Score (%)</th>
                            <th>Position</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $rowNum = 0; foreach ($p1p3StudentList as $studentName => $details): $rowNum++; ?>
                        <tr>
                            <td><?php echo $rowNum; ?></td>
                            <td><?php echo htmlspecialchars($studentName); ?></td>
                            <td><?php echo htmlspecialchars($details['total_eot_p1p3'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($details['average_score_p1p3'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($details['position_p1p3'] ?? 'N/A'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-warning">No specific summary available for the selected class type.</div>
        <?php endif; ?>

        <div class="mt-4 text-center">
            <a href="index.php" class="btn btn-secondary">Back to Upload Page</a>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
