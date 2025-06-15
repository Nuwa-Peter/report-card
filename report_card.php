<?php
session_start();

// Check if data exists, redirect if not
if (!isset($_SESSION['report_data']) || (isset($_GET['action']) && $_GET['action'] == 'view_data_for_report' && empty($_SESSION['report_data']['students']))) {
    $_SESSION['error_message'] = 'No report data found. Please process student data first.';
    header('Location: index.php');
    exit;
}

$reportData = $_SESSION['report_data'];
$students = $reportData['students'];
$classInfo = $reportData['class_info'];
$teacherInitials = $reportData['teacher_initials'];
$generalRemarks = $reportData['general_remarks'];
$gradingDisplayScale = $reportData['grading_display_scale'];
$gradingScalePointsMap = $reportData['grading_scale_points_map'];
$isP4_P7 = $reportData['is_p4_p7'] ?? false;
$isP1_P3 = $reportData['is_p1_p3'] ?? false;
$expectedSubjects = $reportData['expected_subjects_for_class'] ?? [];
$totalStudentsInClass = $reportData['total_students_in_class'] ?? 0;

$subjectDisplayNames = [
    'english' => 'English', 'mtc' => 'Mathematics (MTC)', 'science' => 'Science',
    'sst' => 'Social Studies (SST)', 'kiswahili' => 'Kiswahili',
    're' => 'Religious Education (R.E)', 'lit1' => 'Literacy One',
    'lit2' => 'Literacy Two', 'local_lang' => 'Local Language'
];

$studentToDisplay = null;
if (isset($_SESSION['current_student_for_pdf_key'])) {
    $currentStudentKey = $_SESSION['current_student_for_pdf_key'];
    if (isset($students[$currentStudentKey])) {
        $studentToDisplay = $students[$currentStudentKey];
    }
} elseif (isset($_GET['action']) && $_GET['action'] == 'view_data_for_report' && !empty($students)) {
    $firstStudentKey = array_key_first($students);
    $studentToDisplay = $students[$firstStudentKey];
}

if (!$studentToDisplay && isset($_GET['action']) && $_GET['action'] == 'view_data_for_report') {
     $_SESSION['error_message'] = 'No student data available to display a report.';
     header('Location: index.php');
     exit;
} elseif (!$studentToDisplay && !isset($_SESSION['current_student_for_pdf_key'])) {
    $_SESSION['error_message'] = 'Could not determine which student report to display.';
    header('Location: index.php');
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Report Card - <?php echo htmlspecialchars($studentToDisplay['name'] ?? 'N/A'); ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f4f4f4; font-size: 11pt; }
        .report-card-container { width: 210mm; min-height: 297mm; margin: 10mm auto; padding: 12mm; border: 1px solid #ccc; box-shadow: 0 0 10px rgba(0,0,0,0.1); background-color: white; box-sizing: border-box; }

        .header { text-align: center; margin-bottom: 8mm; }
        .header img { width: 70px; height: 70px; margin-bottom: 3mm; object-fit: contain; }
        .header .school-name { font-size: 16pt; font-weight: bold; color: #003366; margin: 0; }
        .header .school-details { font-size: 9pt; color: #333; margin: 1mm 0; }
        .header .report-title { font-size: 14pt; font-weight: bold; color: #003366; margin-top: 4mm; text-transform: uppercase; }

        .student-info-grid { display: grid; grid-template-columns: auto 1fr auto 1fr; gap: 2mm 4mm; margin-bottom: 4mm; font-size: 10pt;}
        .student-info-grid strong {font-weight: bold;}

        .academic-summary-grid { display: grid; grid-template-columns: auto 1fr auto 1fr; gap: 2mm 4mm; margin-bottom: 6mm; font-size: 10pt; background-color: #f0f0f0; padding: 3mm; border: 1px solid #ddd;}
        .academic-summary-grid strong {font-weight: bold;}
        /* P1-P3 specific summary grid */
        .p1p3-performance-grid { display: grid; grid-template-columns: auto 1fr auto 1fr auto 1fr; gap: 2mm 4mm; margin-bottom: 6mm; font-size: 10pt; background-color: #e6f7ff; padding: 3mm; border: 1px solid #cce0ff;}
        .p1p3-performance-grid strong {font-weight: bold;}


        .results-table { width: 100%; border-collapse: collapse; margin-bottom: 6mm; font-size: 9pt; }
        .results-table th, .results-table td { border: 1px solid #333; padding: 2.5mm 1.5mm; text-align: center; vertical-align: middle; }
        .results-table th { background-color: #e9e9e9; font-weight: bold; }
        .results-table td.subject-name { text-align: left; font-weight: normal; }

        .remarks-section { margin-top: 6mm; font-size: 10pt; }
        .remarks-section .remark-block { margin-bottom: 4mm; padding: 3mm; border: 1px solid #ddd; min-height: 18mm; }
        .remarks-section strong { display: block; margin-bottom: 1mm; font-weight: bold; }
        .remarks-section p { margin: 0 0 2mm 0; line-height: 1.4; }
        .remarks-section .signature-line { margin-top: 8mm; border-top: 1px solid #000; width: 50mm; padding-top:1mm; font-size:9pt; text-align: center; }

        .term-dates { font-size: 9pt; margin-top: 5mm; margin-bottom: 5mm; text-align: center; border-top: 1px dashed #ccc; border-bottom: 1px dashed #ccc; padding: 2mm 0;}

        .grading-scale-section { margin-top: 6mm; font-size: 9pt; }
        .grading-scale-section strong { display: block; margin-bottom: 2mm; font-weight: bold; text-align:center; }
        .grading-scale-table { width: 80%; margin: 0 auto; border-collapse: collapse; }
        .grading-scale-table th, .grading-scale-table td { border: 1px solid #ccc; padding: 1.5mm; text-align: center; }
        .grading-scale-table th { background-color: #f0f0f0; font-weight: bold;}

        .footer { text-align: center; font-size: 10pt; margin-top: 8mm; border-top: 1px solid #003366; padding-top: 3mm; color: #003366; }
        .footer strong {font-weight: bold;}

        .lin-number {font-size: 9pt; text-align: right; margin-bottom: 2mm;}

        @media print {
            body { margin: 0; padding: 0; background-color: #fff; font-size:10pt; }
            .report-card-container { width: 100%; min-height: unset; margin: 0; border: none; box-shadow: none; padding: 10mm; page-break-after: always; }
            .non-printable { display: none; }
        }
    </style>
</head>
<body>

<?php if ($studentToDisplay): ?>
    <div class="report-card-container">
        <div class="header">
            <img src="images/logo.png" alt="School Logo" onerror="this.style.display='none'; document.getElementById('logo-error-msg').style.display='block';">
            <p id="logo-error-msg" style="display:none; color:red;">(Logo not found at images/logo.png)</p>
            <div class="school-name">MARIA OWEMBABAZI PRIMARY SCHOOL</div>
            <div class="school-details">P.O BOX 406, MBARARA</div>
            <div class="school-details">Tel. 0700172858 | Email: houseofnazareth.schools@gmail.com</div>
            <div class="report-title">TERMLY ACADEMIC REPORT</div>
        </div>

        <div class="lin-number"><strong>LIN NO.:</strong></div>

        <div class="student-info-grid">
            <strong>STUDENT'S NAME:</strong> <span><?php echo strtoupper(htmlspecialchars($studentToDisplay['name'])); ?></span>
            <strong>CLASS:</strong> <span><?php echo htmlspecialchars($classInfo['selectedClass']); ?></span>
            <strong>YEAR:</strong> <span><?php echo htmlspecialchars($classInfo['year']); ?></span>
            <strong>TERM:</strong> <span><?php echo htmlspecialchars($classInfo['term']); ?></span>
        </div>

        <?php if ($isP4_P7): ?>
        <div class="academic-summary-grid">
            <strong>AGGREGATE (Core 4):</strong> <span><?php echo htmlspecialchars($studentToDisplay['aggregate_points_p4p7'] ?? 'N/A'); ?></span>
            <strong>DIVISION (Core 4):</strong> <span><?php echo htmlspecialchars($studentToDisplay['division_p4p7'] ?? 'N/A'); ?></span>
        </div>
        <?php elseif ($isP1_P3): ?>
        <div class="p1p3-performance-grid">
            <strong>TOTAL EOT SCORE:</strong> <span><?php echo htmlspecialchars($studentToDisplay['total_eot_p1p3'] ?? 'N/A'); ?></span>
            <strong>AVERAGE EOT SCORE:</strong> <span><?php echo htmlspecialchars($studentToDisplay['average_score_p1p3'] ?? 'N/A'); ?>%</span>
            <strong>POSITION:</strong> <span><?php echo htmlspecialchars($studentToDisplay['position_p1p3'] ?? 'N/A'); ?> out of <?php echo htmlspecialchars($totalStudentsInClass); ?></span>
        </div>
        <?php endif; ?>


        <table class="results-table">
            <thead>
                <tr>
                    <th>SUBJECT</th>
                    <th>B.O.T (100)</th>
                    <th>GRADE</th>
                    <th>M.O.T (100)</th>
                    <th>GRADE</th>
                    <th>E.O.T (100)</th>
                    <th>GRADE</th>
                    <th>REMARKS</th>
                    <th>INITIALS</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($expectedSubjects as $subjectKey): ?>
                    <?php
                        $subjectData = $studentToDisplay['subjects'][$subjectKey] ?? null;
                        $subjDisplayName = $subjectData['subject_display_name'] ?? ($subjectDisplayNames[$subjectKey] ?? ucfirst($subjectKey));
                        $initials = $teacherInitials[$subjectKey] ?? 'N/A';
                    ?>
                    <tr>
                        <td class="subject-name"><?php echo htmlspecialchars($subjDisplayName); ?></td>
                        <td><?php echo htmlspecialchars($subjectData['bot'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($subjectData['bot_grade'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($subjectData['mot'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($subjectData['mot_grade'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($subjectData['eot'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($subjectData['eot_grade'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($subjectData['remarks'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($initials); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="remarks-section">
            <div class="remark-block">
                <strong>Class Teacher's Remarks:</strong>
                <p><?php echo nl2br(htmlspecialchars($generalRemarks['class_teacher'])); ?></p>
                <div class="signature-line">Class Teacher's Signature</div>
            </div>
            <div class="remark-block">
                <strong>Head Teacher's Remarks:</strong>
                <p><?php echo nl2br(htmlspecialchars($generalRemarks['head_teacher'])); ?></p>
                <div class="signature-line">Head Teacher's Signature & Stamp</div>
            </div>
        </div>

        <div class="term-dates">
            This Term Ended On: <strong><?php echo htmlspecialchars(date('d M Y', strtotime($classInfo['termEndDate']))); ?></strong> &nbsp; | &nbsp;
            Next Term Begins On: <strong><?php echo htmlspecialchars(date('d M Y', strtotime($classInfo['nextTermBeginDate']))); ?></strong>
        </div>

        <div class="grading-scale-section">
            <strong>GRADING SCALE</strong> <!-- Title made generic -->
            <table class="grading-scale-table">
                <thead><tr><th>Grade</th><th>Marks</th>
                <?php if($isP4_P7) echo "<th>Points (P4-P7 Core)</th>"; /* Points column only for P4-P7 */ ?>
                </tr></thead>
                <tbody>
                    <?php
                    $scaleForTable = [
                        'D1' => ['range' => '90-100', 'points' => $gradingScalePointsMap['D1'] ?? 1],
                        'D2' => ['range' => '80-89', 'points' => $gradingScalePointsMap['D2'] ?? 2],
                        'C3' => ['range' => '70-79', 'points' => $gradingScalePointsMap['C3'] ?? 3],
                        'C4' => ['range' => '60-69', 'points' => $gradingScalePointsMap['C4'] ?? 4],
                        'C5' => ['range' => '55-59', 'points' => $gradingScalePointsMap['C5'] ?? 5],
                        'C6' => ['range' => '50-54', 'points' => $gradingScalePointsMap['C6'] ?? 6],
                        'P7' => ['range' => '45-49', 'points' => $gradingScalePointsMap['P7'] ?? 7],
                        'P8' => ['range' => '40-44', 'points' => $gradingScalePointsMap['P8'] ?? 8],
                        'F9' => ['range' => '0-39', 'points' => $gradingScalePointsMap['F9'] ?? 9],
                    ];
                    foreach ($scaleForTable as $grade => $details) {
                        echo "<tr><td>".htmlspecialchars($grade)."</td><td>".htmlspecialchars($details['range'])."</td>";
                        if($isP4_P7) echo "<td>".htmlspecialchars($details['points'])."</td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <div class="footer">
            <strong>School Motto:</strong> Good Christian, Good Citizen
        </div>
    </div>

<?php else: ?>
    <div class="container mt-5 non-printable">
        <div class="alert alert-warning" role="alert">Student data not available for display.</div>
        <a href="index.php" class="btn btn-primary">Return to Upload Page</a>
    </div>
<?php endif; ?>
</body>
</html>
