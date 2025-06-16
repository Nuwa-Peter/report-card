<?php
// report_card.php - Now acts as a template
// Expected variables to be set by the calling script:
// $studentData (array: from getStudentsWithScoresForBatch for the specific student, merged with their specific calculated eot_grades, eot_remarks etc.)
// $studentSummaryData (array: from getStudentReportSummariesForBatch for the specific student)
// $batchSettingsData (array: from getReportBatchSettings)
// $teacherInitials (array: keyed by subject_code, passed from process_excel via session or from batch settings)
// $isP1_P3 (bool)
// $isP4_P7 (bool)
// $expectedSubjectsForClass (array: list of subject_codes to display)
// $totalStudentsInClass (int: for P1-P3 position display)
// $subjectDisplayNames (array: map of subject_code to display name)
// $gradingScaleForDisplay (array: for P4-P7 horizontal scale)


// Fallback if not included correctly (should not happen in production flow)
if (!isset($studentData) || !isset($studentSummaryData) || !isset($batchSettingsData)) {
    die("Report card template loaded without required data.");
}

// Ensure all keys are at least set to avoid undefined notices, use ?? 'N/A' in HTML
$studentName = strtoupper(htmlspecialchars($studentData['student_name'] ?? 'N/A'));
$linNo = htmlspecialchars($studentData['lin_no'] ?? ''); // LIN can be empty

$className = htmlspecialchars($batchSettingsData['class_name'] ?? 'N/A');
$yearName = htmlspecialchars($batchSettingsData['year_name'] ?? 'N/A');
$termName = htmlspecialchars($batchSettingsData['term_name'] ?? 'N/A');
$termEndDateFormatted = isset($batchSettingsData['term_end_date']) ? htmlspecialchars(date('d F Y', strtotime($batchSettingsData['term_end_date']))) : 'N/A';
$nextTermBeginDateFormatted = isset($batchSettingsData['next_term_begin_date']) ? htmlspecialchars(date('d F Y', strtotime($batchSettingsData['next_term_begin_date']))) : 'N/A';

// Auto remarks
$classTeacherRemark = nl2br(htmlspecialchars($studentSummaryData['auto_classteachers_remark_text'] ?? 'N/A'));
$headTeacherRemark = nl2br(htmlspecialchars($studentSummaryData['auto_headteachers_remark_text'] ?? 'N/A'));

// P4-P7 specific data
$p4p7Aggregate = htmlspecialchars($studentSummaryData['p4p7_aggregate_points'] ?? 'N/A');
$p4p7Division = htmlspecialchars($studentSummaryData['p4p7_division'] ?? 'N/A');

// P1-P3 specific data
$p1p3TotalEOT = htmlspecialchars($studentSummaryData['p1p3_total_eot_score'] ?? 'N/A');
$p1p3AverageEOT = htmlspecialchars($studentSummaryData['p1p3_average_eot_score'] ?? 'N/A');
$p1p3Position = htmlspecialchars($studentSummaryData['p1p3_position_in_class'] ?? 'N/A');
// $totalStudentsInClass is passed directly and used in the HTML

// Define the grading scale for P4-P7 display (Grade & Marks Range)
$gradingScaleForP4P7Display = [
    'D1' => '90-100', 'D2' => '80-89', 'C3' => '70-79', 'C4' => '60-69',
    'C5' => '55-59', 'C6' => '50-54', 'P7' => '45-49', 'P8' => '40-44', 'F9' => '0-39'
];

// This file will now be mostly HTML and inline CSS, with PHP for echoing variables.
// The complex logic for fetching and preparing these variables is moved to the calling script (e.g. generate_pdf.php or a new view_single_report.php)
// which will use the DAL and Calculation Engine.

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Card - <?php echo $studentName; ?></title>
    <link rel="icon" type="image/png" href="images/logo.png">
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f4f4f4; font-size: 10pt; color: #000; /* Pure black text */ }
        .report-card-container {
            width: 210mm; /* A4 width */
            min-height: 290mm; /* A4 height - allowing some margin for error */
            margin: 10mm auto;
            padding: 10mm;
            border: 1px solid #ccc;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            background-color: white;
            box-sizing: border-box;
            position: relative; /* For watermark positioning */
        }
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            opacity: 0.08; /* Washed out */
            z-index: 0;
            pointer-events: none; /* So it doesn't interfere with text selection */
            width: 60%; /* Adjust as needed */
        }

        .header { text-align: center; margin-bottom: 5mm; }
        .header .school-name { font-size: 20pt; font-weight: bold; margin: 0; color: #000; }
        .header .logo-container { margin-top: 2mm; margin-bottom: 2mm; }
        .header .logo-container img { width: 60px; /* Smaller logo */ height: 60px; object-fit: contain; }
        .header .school-details { font-size: 9pt; margin: 0.5mm 0; color: #000; }
        .header .report-title { font-size: 16pt; font-weight: bold; margin-top: 3mm; text-transform: uppercase; color: #000; }

        .student-details-block { margin-bottom: 3mm; }
        .student-info-grid { display: grid; grid-template-columns: auto 1fr auto 1fr; gap: 1.5mm 4mm; font-size: 10pt; margin-bottom:1mm;}
        .student-info-grid strong {font-weight: bold; color: #000;}
        .student-info-grid span {color: #000;}
        .lin-number-display {font-size: 9pt; text-align: left; margin-top: 1mm; color: #000;}
        .lin-number-display strong {font-weight: bold;}


        .academic-summary-grid { display: grid; grid-template-columns: auto 1fr auto 1fr; gap: 1.5mm 4mm; margin-bottom: 4mm; font-size: 10pt; background-color: #f0f0f0; padding: 2mm; border: 1px solid #ddd; color: #000;}
        .academic-summary-grid strong {font-weight: bold;}

        .p1p3-performance-summary { margin-top: 4mm; margin-bottom: 4mm; font-size: 10pt; border: 1px solid #eee; padding: 2mm; background-color: #f9f9f9; color: #000;}
        .p1p3-performance-summary strong { font-weight: bold; }


        .results-table { width: 100%; border-collapse: collapse; margin-bottom: 4mm; font-size: 9pt; }
        .results-table th, .results-table td { border: 1px solid #000; padding: 2mm 1.5mm; text-align: center; vertical-align: middle; color: #000; }
        .results-table th { background-color: #e9e9e9; font-weight: bold; }
        .results-table td.subject-name { text-align: left; font-weight: normal; }

        .remarks-section { margin-top: 4mm; font-size: 10pt; color: #000;}
        .remarks-section .remark-block { margin-bottom: 3mm; padding: 2mm; border: 1px solid #ddd; min-height: 15mm; }
        .remarks-section strong { display: block; margin-bottom: 1mm; font-weight: bold; }
        .remarks-section p { margin: 0 0 1.5mm 0; line-height: 1.3; }
        .remarks-section .signature-line { margin-top: 6mm; border-top: 1px solid #000; width: 50mm; padding-top:1mm; font-size:9pt; text-align: center; }

        .term-dates { font-size: 9pt; margin-top: 4mm; margin-bottom: 4mm; text-align: center; border-top: 1px dashed #ccc; border-bottom: 1px dashed #ccc; padding: 1.5mm 0; color: #000;}
        .term-dates strong {font-weight:bold;}

        .additional-note { font-size: 9pt; margin-top: 4mm; margin-bottom: 4mm; text-align: center; font-style: italic; color: #000; }

        .grading-scale-section-p4p7 { margin-top: 4mm; font-size: 8pt; color: #000; }
        .grading-scale-section-p4p7 strong { display: block; margin-bottom: 1mm; font-weight: bold; text-align:center; }
        .grading-scale-section-p4p7 .scale-container { display: flex; flex-wrap: wrap; justify-content: center; list-style: none; padding:0; margin:0; }
        .grading-scale-section-p4p7 .scale-item { margin: 0.5mm 2mm; white-space:nowrap; }
        .grading-scale-section-p4p7 .scale-item strong {font-weight:bold; display:inline;}


        .footer { text-align: center; font-size: 11pt; margin-top: 6mm; border-top: 1px solid #000; padding-top: 2mm; color: #000; }
        .footer i { font-style: italic; } /* For "Good Christian, Good Citizen" */

        @media print {
            body { margin: 0; padding: 0; background-color: #fff; font-size:10pt; color: #000; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .report-card-container { width: 100%; min-height: unset; margin: 0; border: none; box-shadow: none; padding: 8mm; page-break-after: always; }
            .watermark { opacity: 0.06; width: 50%; } /* Slightly less opaque for print */
            .non-printable { display: none; }
            .academic-summary-grid, .p1p3-performance-summary, .results-table th { background-color: #e9e9e9 !important; } /* Ensure backgrounds print if any */
        }
    </style>
</head>
<body>

    <div class="report-card-container">
        <img src="images/logo.png" class="watermark" alt="School Watermark Logo" onerror="this.style.display='none';">

        <div class="header">
            <div class="school-name"><?php echo htmlspecialchars("MARIA OWEMBABAZI PRIMARY SCHOOL"); ?></div>
            <div class="logo-container">
                <img src="images/logo.png" alt="School Logo" onerror="this.style.display='none';">
            </div>
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
            <strong>DIVISION:</strong> <span><?php echo $p4p7Division; ?></span>
        </div>
        <?php elseif ($isP1_P3): ?>
        <div class="academic-summary-grid"> <!-- Reusing style for consistency, can be unique if needed -->
            <strong>POSITION:</strong> <span><?php echo $p1p3Position; ?> out of <?php echo htmlspecialchars($totalStudentsInClass); ?></span>
            <span></span> <!-- Empty cell for layout -->
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
                    <th>E.O.T (100)</th> <!-- EOT = End Of Term -->
                    <th>GRADE</th>
                    <th>REMARKS</th> <!-- Subject specific remarks from calculation engine -->
                    <th>INITIALS</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($expectedSubjectsForClass as $subjectKey): ?>
                    <?php
                        $subjectPerformance = $studentData['subjects'][$subjectKey] ?? null;
                        $subjDisplayNameFromMap = $subjectDisplayNames[$subjectKey] ?? ucfirst($subjectKey);
                        // Use subject_name_full from DB if available, else from map
                        $subjDisplayName = htmlspecialchars($subjectPerformance['subject_name_full'] ?? $subjDisplayNameFromMap);
                        $initialsForSubj = htmlspecialchars($teacherInitials[$subjectKey] ?? 'N/A');

                        // These are expected to be pre-calculated and passed in $subjectPerformance
                        $bot_grade = htmlspecialchars($subjectPerformance['bot_grade'] ?? 'N/A');
                        $mot_grade = htmlspecialchars($subjectPerformance['mot_grade'] ?? 'N/A');
                        $eot_grade = htmlspecialchars($subjectPerformance['eot_grade'] ?? 'N/A');
                        $eot_remark = htmlspecialchars($subjectPerformance['eot_remark'] ?? 'N/A');
                    ?>
                    <tr>
                        <td class="subject-name"><?php echo $subjDisplayName; ?></td>
                        <td><?php echo htmlspecialchars($subjectPerformance['bot_score'] ?? 'N/A'); ?></td>
                        <td><?php echo $bot_grade; ?></td>
                        <td><?php echo htmlspecialchars($subjectPerformance['mot_score'] ?? 'N/A'); ?></td>
                        <td><?php echo $mot_grade; ?></td>
                        <td><?php echo htmlspecialchars($subjectPerformance['eot_score'] ?? 'N/A'); ?></td>
                        <td><?php echo $eot_grade; ?></td>
                        <td><?php echo $eot_remark; ?></td>
                        <td><?php echo $initialsForSubj; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($isP1_P3): ?>
        <div class="p1p3-performance-summary">
            <strong>Total End of Term Score:</strong> <?php echo $p1p3TotalEOT; ?> &nbsp; | &nbsp;
            <strong>Average End of Term Score:</strong> <?php echo $p1p3AverageEOT; ?>%
        </div>
        <?php endif; ?>

        <div class="remarks-section">
            <div class="remark-block">
                <strong>Class Teacher's Remarks:</strong>
                <p><?php echo $classTeacherRemark; ?></p>
                <div class="signature-line">Class Teacher's Signature</div>
            </div>
            <div class="remark-block">
                <strong>Head Teacher's Remarks:</strong>
                <p><?php echo $headTeacherRemark; ?></p>
                <div class="signature-line">Head Teacher's Signature & Stamp</div>
            </div>
        </div>

        <div class="term-dates">
            This Term Ended On: <strong><?php echo $termEndDateFormatted; ?></strong> &nbsp; | &nbsp;
            Next Term Begins On: <strong><?php echo $nextTermBeginDateFormatted; ?></strong>
        </div>

        <?php if ($isP4_P7): ?>
        <div class="additional-note">
            Additional Note: Please ensure regular attendance and parental support for optimal performance.
        </div>
        <div class="grading-scale-section-p4p7">
            <strong>GRADING SCALE</strong>
            <div class="scale-container">
                <?php foreach ($gradingScaleForP4P7Display as $grade => $range): ?>
                    <span class="scale-item"><strong><?php echo htmlspecialchars($grade); ?>:</strong> <?php echo htmlspecialchars($range); ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php // P1-P3 reports do not have the grading scale table as per new requirement ?>

        <div class="footer">
            <i>Good Christian, Good Citizen</i>
        </div>
    </div>
</body>
</html>
