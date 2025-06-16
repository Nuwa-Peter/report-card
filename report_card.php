<?php
// report_card.php - Template for generating student report cards
// EXPECTS variables: $pdo, $batch_id, $student_id, $currentStudentEnrichedData,
// $teacherInitials, $subjectDisplayNames,
// $gradingScaleForP4P7Display, $expectedSubjectKeysForClass

// Ensure critical DAL functions are available
if (!function_exists('getReportBatchSettings') || !function_exists('getStudentSummaryAndDetailsForReport')) {
    if (file_exists('dal.php')) {
        require_once 'dal.php';
    } else {
        die('FATAL ERROR: dal.php is missing and critical for report_card.php template.');
    }
}

// --- Primary Data Fetching (caller should ensure these IDs are valid) ---
if (!isset($pdo) || !($pdo instanceof PDO)) { die("Template Error: Valid PDO object (\$pdo) not provided."); }
if (!isset($batch_id) || !filter_var($batch_id, FILTER_VALIDATE_INT) || $batch_id <= 0) { die("Template Error: Valid batch_id not provided."); }
if (!isset($student_id) || !filter_var($student_id, FILTER_VALIDATE_INT) || $student_id <= 0) { die("Template Error: Valid student_id not provided."); }
if (!isset($currentStudentEnrichedData) || !is_array($currentStudentEnrichedData)) { die("Template Error: currentStudentEnrichedData not provided."); }
if (!isset($expectedSubjectKeysForClass) || !is_array($expectedSubjectKeysForClass)) { die("Template Error: expectedSubjectKeysForClass not provided."); }

$batchSettingsData = getReportBatchSettings($pdo, $batch_id);
$studentSummaryData = getStudentSummaryAndDetailsForReport($pdo, $student_id, $batch_id);

if (!$batchSettingsData || !$studentSummaryData) {
    $errorMsg = "Essential data missing after DB fetch for report card (Batch ID: $batch_id, Student ID: $student_id). ";
    if (!$batchSettingsData) $errorMsg .= "Batch settings not found. ";
    if (!$studentSummaryData) $errorMsg .= "Student overall summary not found. Ensure run_calculations.php was successful. ";
    die($errorMsg);
}

// --- Prepare variables for the template ---
$studentName = strtoupper(htmlspecialchars($studentSummaryData['student_name'] ?? 'N/A'));
$linNo = htmlspecialchars($studentSummaryData['lin_no'] ?? '');
$className = htmlspecialchars($batchSettingsData['class_name'] ?? 'N/A');
$yearName = htmlspecialchars($batchSettingsData['year_name'] ?? 'N/A');
$termName = htmlspecialchars($batchSettingsData['term_name'] ?? 'N/A');
$termEndDateFormatted = isset($batchSettingsData['term_end_date']) ? htmlspecialchars(date('d F Y', strtotime($batchSettingsData['term_end_date']))) : 'N/A';
$nextTermBeginDateFormatted = isset($batchSettingsData['next_term_begin_date']) ? htmlspecialchars(date('d F Y', strtotime($batchSettingsData['next_term_begin_date']))) : 'N/A';
$classTeacherRemark = nl2br(htmlspecialchars($studentSummaryData['auto_classteachers_remark_text'] ?? 'N/A'));
$headTeacherRemark = nl2br(htmlspecialchars($studentSummaryData['auto_headteachers_remark_text'] ?? 'N/A'));

$isP1_P3 = in_array($className, ['P1', 'P2', 'P3']);
$isP4_P7 = in_array($className, ['P4', 'P5', 'P6', 'P7']);

$p4p7Aggregate = htmlspecialchars($studentSummaryData['p4p7_aggregate_points'] ?? 'N/A');
$p4p7Division = htmlspecialchars($studentSummaryData['p4p7_division'] ?? 'N/A'); // Will be Roman numeral

$p1p3TotalEOT = htmlspecialchars($studentSummaryData['p1p3_total_eot_score'] ?? 'N/A');
$p1p3AverageEOT = htmlspecialchars($studentSummaryData['p1p3_average_eot_score'] ?? 'N/A');
$p1p3Position = htmlspecialchars($studentSummaryData['p1p3_position_in_class'] ?? 'N/A');
$totalStudentsInClassForP1P3 = htmlspecialchars($studentSummaryData['p1p3_total_students_in_class'] ?? 0);

$subjectsToDisplayInTable = $currentStudentEnrichedData['subjects'] ?? [];

$subjectDisplayNames = $subjectDisplayNames ?? [
    'english' => 'English', 'mtc' => 'Mathematics (MTC)', 'science' => 'Science',
    'sst' => 'Social Studies (SST)', 'kiswahili' => 'Kiswahili',
    're' => 'Religious Education (R.E)', 'lit1' => 'Literacy I',
    'lit2' => 'Literacy II', 'local_lang' => 'Local Language'
];
$gradingScaleForP4P7Display = $gradingScaleForP4P7Display ?? [
    'D1' => '90-100', 'D2' => '80-89', 'C3' => '70-79', 'C4' => '60-69',
    'C5' => '55-59', 'C6' => '50-54', 'P7' => '45-49', 'P8' => '40-44', 'F9' => '0-39'
];
$teacherInitials = $teacherInitials ?? ($_SESSION['current_teacher_initials'] ?? []);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Card - <?php echo $studentName; ?></title>
    <link rel="icon" type="image/png" href="images/logo.png">
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; margin: 0; padding: 0; background-color: #f0f0f0; font-size: 9.5pt; /* Slightly reduced base font */ color: #000; }
        .report-card-container { width: 190mm; min-height: 275mm; /* Adjusted min-height */ margin: 10mm auto; padding: 10mm; border: 1px solid #ccc; box-shadow: 0 0 8px rgba(0,0,0,0.1); background-color: white; position: relative; box-sizing: border-box; }
        .watermark { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); opacity: 0.06; /* Slightly more visible if needed */ z-index: 0; pointer-events: none; width: 150mm; /* Adjusted width */ }

        .header { text-align: center; margin-bottom: 3.5mm; /* Reduced margin */ }
        .header .school-name { font-size: 20pt; font-weight: bold; margin: 0; color: #000; letter-spacing: 0.5px; } /* Bigger School Name */
        .header .logo-container { margin-top: 1.5mm; margin-bottom: 1.5mm; }
        .header .logo-container img { width: 50px; /* Smaller Logo */ height: 50px; object-fit: contain; }
        .header .school-details { font-size: 8pt; margin: 0.25mm 0; color: #000; } /* Reduced font and margin */
        .header .report-title { font-size: 16pt; font-weight: bold; margin-top: 2mm; text-transform: uppercase; color: #000; letter-spacing: 1px; } /* Bigger Report Title */

        .student-details-block { margin-bottom: 2.5mm; } /* Reduced margin */
        .student-info-grid { display: grid; grid-template-columns: auto 1fr auto 1fr; gap: 1mm 3mm; /* Reduced gap */ font-size: 9pt; margin-bottom:0.5mm;}
        .student-info-grid strong {font-weight: bold; color: #000;}
        .student-info-grid span {color: #000;}
        .lin-number-display {font-size: 8.5pt; text-align: left; margin-top: 0.5mm; color: #000;} /* LIN below student block */
        .lin-number-display strong {font-weight: bold;}

        .academic-summary-grid { display: grid; grid-template-columns: auto 1fr auto 1fr; gap: 1mm 3mm; margin-bottom: 2.5mm; font-size: 9pt; background-color: #f0f0f0; padding: 1.5mm; border: 1px solid #ddd; color: #000;}
        .academic-summary-grid strong {font-weight: bold;}

        .results-table { width: 100%; border-collapse: collapse; margin-bottom: 2.5mm; font-size: 8pt; } /* Reduced font size */
        .results-table th, .results-table td { border: 1px solid #000; padding: 1.5mm 1mm; /* Reduced padding */ text-align: center; vertical-align: middle; color: #000; }
        .results-table th { background-color: #e9e9e9; font-weight: bold; }
        .results-table td.subject-name { text-align: left; font-weight: normal; }

        .p1p3-performance-summary-after-table { margin-top: 2.5mm; margin-bottom: 2.5mm; font-size: 9pt; border: 1px solid #eaeaea; padding: 1.5mm; background-color: #f9f9f9; color: #000; text-align:center; }
        .p1p3-performance-summary-after-table strong { font-weight: bold; }

        .remarks-section { margin-top: 2.5mm; font-size: 9pt; color: #000;} /* Reduced font size */
        .remarks-section .remark-block { margin-bottom: 2mm; padding: 1.5mm; border: 1px solid #ddd; min-height: 15mm; /* Reduced min-height */ }
        .remarks-section strong { display: block; margin-bottom: 0.5mm; font-weight: bold; }
        .remarks-section p { margin: 0 0 1mm 0; line-height: 1.25; } /* Reduced line height */
        .remarks-section .signature-line { margin-top: 4mm; border-top: 1px solid #000; width: 45mm; padding-top:0.5mm; font-size:8pt; text-align: center; } /* Reduced font and margin */

        .term-dates { font-size: 8pt; margin-top: 2.5mm; margin-bottom: 2.5mm; text-align: center; border-top: 1px dashed #ccc; border-bottom: 1px dashed #ccc; padding: 1mm 0; color: #000;}
        .term-dates strong {font-weight:bold;}

        .additional-note-p4p7 { font-size: 8pt; margin-top: 2.5mm; margin-bottom: 2.5mm; text-align: center; font-style: italic; color: #000; }

        .grading-scale-section-p4p7 { margin-top: 2.5mm; font-size: 7.5pt; color: #000; text-align:center; } /* Centered container */
        .grading-scale-section-p4p7 strong { display: block; margin-bottom: 1mm; font-weight: bold; }
        .grading-scale-section-p4p7 .scale-container { display: inline-block; text-align:left; padding: 0; margin:0;} /* Allows centering of this block */
        .grading-scale-section-p4p7 .scale-item { display: inline-block; margin: 0.5mm 1.2mm; white-space:nowrap; border: 1px solid #eee; padding: 0.5mm 1mm; border-radius: 3px;}
        .grading-scale-section-p4p7 .scale-item strong {font-weight:bold; display:inline;}

        .footer { text-align: center; font-size: 9.5pt; margin-top: 4mm; border-top: 1px solid #000; padding-top: 1.5mm; color: #000; } /* Reduced font size */
        .footer i { font-style: italic; font-size:10pt; }

        @media print {
            body { margin: 0; padding: 0; background-color: #fff; font-size:9pt; /* Base print font */ color: #000; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .report-card-container { width: 100%; min-height: unset; margin: 0; border: none; box-shadow: none; padding: 7mm; /* Reduced padding for print */ page-break-after: always; }
            .watermark { opacity: 0.05;}
            .non-printable { display: none; }
            .academic-summary-grid, .p1p3-performance-summary-after-table, .results-table th { background-color: #e9e9e9 !important; }
            .grading-scale-section-p4p7 .scale-item { border: 1px solid #ccc !important; }
        }
    </style>
</head>
<body>
    <div class="report-card-container">
        <img src="images/logo.png" class="watermark" alt="School Watermark Logo" onerror="this.style.display='none';">
        <div class="header">
            <div class="school-name"><?php echo htmlspecialchars("MARIA OWEMBABAZI PRIMARY SCHOOL"); ?></div>
            <div class="logo-container"><img src="images/logo.png" alt="School Logo" onerror="this.style.display='none';"></div>
            <div class="school-details">P.O BOX 406, MBARARA</div>
            <div class="school-details">Tel. 0700172858 | Email: houseofnazareth.schools@gmail.com</div>
            <div class="report-title">TERMLY ACADEMIC REPORT</div>
        </div>
        <div class="student-details-block">
            <div class="student-info-grid">
                <strong>STUDENT'S NAME:</strong> <span><?php echo $studentName; ?></span>
                <strong>CLASS:</strong> <span><?php echo $className; ?></span>
                <strong>YEAR:</strong> <span><?php echo $yearName; ?></span>
                <strong>TERM:</strong> <span><?php echo $termName; ?></span>
            </div>
            <div class="lin-number-display"><strong>LIN NO.:</strong> <?php echo $linNo; ?></div>
        </div>

        <?php if ($isP4_P7): ?>
        <div class="academic-summary-grid">
            <strong>AGGREGATE:</strong> <span><?php echo $p4p7Aggregate; ?></span>
            <strong>DIVISION:</strong> <span><?php echo $p4p7Division; ?></span> <!-- Will display Roman numeral -->
        </div>
        <?php elseif ($isP1_P3): ?>
        <div class="academic-summary-grid">
            <strong>POSITION:</strong> <span><?php echo $p1p3Position; ?> out of <?php echo $totalStudentsInClassForP1P3; ?></span>
            <span></span>
        </div>
        <?php endif; ?>

        <table class="results-table">
            <thead>
                <tr>
                    <th>SUBJECT</th>
                    <th>B.O.T (100)</th>
                    <?php if ($isP4_P7): ?><th>GRADE</th><?php endif; ?>
                    <th>M.O.T (100)</th>
                    <?php if ($isP4_P7): ?><th>GRADE</th><?php endif; ?>
                    <th>END OF TERM (100)</th>
                    <?php if ($isP4_P7): ?><th>GRADE</th><?php endif; ?>
                    <th>REMARKS</th>
                    <th>INITIALS</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($expectedSubjectKeysForClass as $subjectKey): // Iterate using defined keys from calling script ?>
                    <?php
                        $subjectPerformance = $subjectsToDisplayInTable[$subjectKey] ?? null;
                        $subjDisplayName = htmlspecialchars(
                            $subjectPerformance['subject_name_full'] ??
                            ($subjectDisplayNames[$subjectKey] ?? ucfirst($subjectKey))
                        );
                        $initialsForSubj = htmlspecialchars($teacherInitials[$subjectKey] ?? 'N/A');

                        $bot_grade = htmlspecialchars($subjectPerformance['bot_grade'] ?? 'N/A');
                        $mot_grade = htmlspecialchars($subjectPerformance['mot_grade'] ?? 'N/A');
                        $eot_grade = htmlspecialchars($subjectPerformance['eot_grade'] ?? 'N/A');
                        $eot_remark = htmlspecialchars($subjectPerformance['eot_remark'] ?? 'N/A');
                    ?>
                    <tr>
                        <td class="subject-name"><?php echo $subjDisplayName; ?></td>
                        <td><?php echo htmlspecialchars($subjectPerformance['bot_score'] ?? 'N/A'); ?></td>
                        <?php if ($isP4_P7): ?><td><?php echo $bot_grade; ?></td><?php endif; ?>
                        <td><?php echo htmlspecialchars($subjectPerformance['mot_score'] ?? 'N/A'); ?></td>
                        <?php if ($isP4_P7): ?><td><?php echo $mot_grade; ?></td><?php endif; ?>
                        <td><?php echo htmlspecialchars($subjectPerformance['eot_score'] ?? 'N/A'); ?></td>
                        <?php if ($isP4_P7): ?><td><?php echo $eot_grade; ?></td><?php endif; ?>
                        <td><?php echo $eot_remark; ?></td>
                        <td><?php echo $initialsForSubj; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($isP1_P3): ?>
        <div class="p1p3-performance-summary-after-table">
            <strong>Total End of Term Score:</strong> <?php echo $p1p3TotalEOT; ?> &nbsp; &nbsp; | &nbsp; &nbsp;
            <strong>Average End of Term Score:</strong> <?php echo $p1p3AverageEOT; ?>%
        </div>
        <?php endif; ?>

        <div class="remarks-section">
            <div class="remark-block"><strong>Class Teacher's Remarks:</strong><p><?php echo $classTeacherRemark; ?></p><div class="signature-line">Class Teacher's Signature</div></div>
            <div class="remark-block"><strong>Head Teacher's Remarks:</strong><p><?php echo $headTeacherRemark; ?></p><div class="signature-line">Head Teacher's Signature & Stamp</div></div>
        </div>
        <div class="term-dates">
            This Term Ended On: <strong><?php echo $termEndDateFormatted; ?></strong> &nbsp; | &nbsp;
            Next Term Begins On: <strong><?php echo $nextTermBeginDateFormatted; ?></strong>
        </div>
        <?php if ($isP4_P7): ?>
        <div class="additional-note-p4p7">Additional Note: Please ensure regular attendance and parental support for optimal performance.</div>
        <div class="grading-scale-section-p4p7">
            <strong>GRADING SCALE</strong>
            <div class="scale-container">
                <?php foreach ($gradingScaleForP4P7Display as $grade => $range): ?>
                    <span class="scale-item"><strong><?php echo htmlspecialchars($grade); ?>:</strong> <?php echo htmlspecialchars($range); ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        <div class="footer"><i>Good Christian, Good Citizen</i></div>
    </div>
</body>
</html>
