<?php
session_start();
require_once 'db_connection.php';
require_once 'dal.php';
require_once 'calculation_utils.php';

if (!isset($_GET['batch_id']) || !filter_var($_GET['batch_id'], FILTER_VALIDATE_INT)) {
    $_SESSION['error_message'] = "Invalid or missing batch ID for calculations.";
    header('Location: index.php');
    exit;
}
$batch_id = (int)$_GET['batch_id'];

$batchSettings = getReportBatchSettings($pdo, $batch_id);
if (!$batchSettings) {
    $_SESSION['error_message'] = "Could not find batch settings for Batch ID: " . htmlspecialchars($batch_id);
    header('Location: index.php');
    exit;
}

$isP4_P7 = in_array($batchSettings['class_name'], ['P4', 'P5', 'P6', 'P7']);
$isP1_P3 = in_array($batchSettings['class_name'], ['P1', 'P2', 'P3']);

// Define subject keys based on class type for calculations
$coreSubjectKeysP4_P7 = [];
$p1p3SubjectKeys = [];
if ($isP4_P7) {
    $coreSubjectKeysP4_P7 = ['english', 'mtc', 'science', 'sst'];
} elseif ($isP1_P3) {
    $p1p3SubjectKeys = ['english', 'mtc', 're', 'lit1', 'lit2', 'local_lang'];
}

$gradingScalePointsMap = ['D1'=>1, 'D2'=>2, 'C3'=>3, 'C4'=>4, 'C5'=>5, 'C6'=>6, 'P7'=>7, 'P8'=>8, 'F9'=>9, 'N/A'=>0];

$studentsRawData = getStudentsWithScoresForBatch($pdo, $batch_id); // Keyed by student_id
$processedStudentsSummaryData = [];

$pdo->beginTransaction();
try {
    foreach ($studentsRawData as $studentId => $studentData) {
        $studentPerformanceInputForRemarks = [];
        $summaryData = [
            'student_id' => $studentId,
            'report_batch_id' => $batch_id,
            'p4p7_aggregate_points' => null,
            'p4p7_division' => null,
            'p1p3_total_eot_score' => null,
            'p1p3_average_eot_score' => null,
            'p1p3_position_in_class' => null,
            'p1p3_total_students_in_class' => null, // Will be set later for P1-P3
            'auto_classteachers_remark_text' => null,
            'auto_headteachers_remark_text' => null
        ];

        if ($isP4_P7) {
            // StudentData['subjects'] is keyed by subject_code and contains 'eot_score'
            $p4p7_results = calculateDivisionAndAggregateP4P7Util($studentData['subjects'], $coreSubjectKeysP4_P7, $gradingScalePointsMap);
            $summaryData['p4p7_aggregate_points'] = $p4p7_results['p4p7_aggregate_points'];
            $summaryData['p4p7_division'] = $p4p7_results['p4p7_division'];

            $studentPerformanceInputForRemarks['p4p7_division'] = $summaryData['p4p7_division'];
            $studentPerformanceInputForRemarks['p4p7_aggregate_points'] = $summaryData['p4p7_aggregate_points'];
        } elseif ($isP1_P3) {
            $totalEotScore = 0;
            $subjectsWithEotCount = 0;
            foreach ($p1p3SubjectKeys as $p1p3SubKey) {
                // Ensure subject exists for student before trying to access eot_score
                $subjectInfo = $studentData['subjects'][$p1p3SubKey] ?? null;
                if ($subjectInfo && isset($subjectInfo['eot_score']) && $subjectInfo['eot_score'] !== 'N/A' && is_numeric($subjectInfo['eot_score'])) {
                    $totalEotScore += floatval($subjectInfo['eot_score']);
                    $subjectsWithEotCount++;
                }
            }
            $summaryData['p1p3_total_eot_score'] = $totalEotScore;
            $averageScoreP1P3 = ($subjectsWithEotCount > 0) ? round($totalEotScore / $subjectsWithEotCount, 2) : 0;
            $summaryData['p1p3_average_eot_score'] = $averageScoreP1P3;

            $studentPerformanceInputForRemarks['average_score_p1p3'] = $averageScoreP1P3;
        }

        // Generate remarks using the utility functions
        $summaryData['auto_classteachers_remark_text'] = generateClassTeacherRemarkUtil($studentPerformanceInputForRemarks, $isP4_P7);
        $summaryData['auto_headteachers_remark_text'] = generateHeadTeacherRemarkUtil($studentPerformanceInputForRemarks, $isP4_P7);

        $processedStudentsSummaryData[$studentId] = $summaryData;
    }

    // Calculate P1-P3 Ranks if applicable (after all averages are calculated)
    if ($isP1_P3 && !empty($processedStudentsSummaryData)) {
        $studentAverages = [];
        foreach ($processedStudentsSummaryData as $studId => $details) {
            $studentAverages[$studId] = $details['p1p3_average_eot_score'] ?? 0;
        }
        arsort($studentAverages, SORT_NUMERIC);

        $rank_display = 0;
        $previous_score = -1;
        $students_processed_for_rank = 0;
        $totalStudentsInClass = count($studentAverages);

        foreach ($studentAverages as $studId => $score) {
            $students_processed_for_rank++;
            if ($score != $previous_score) {
                $rank_display = $students_processed_for_rank;
            }
            $processedStudentsSummaryData[$studId]['p1p3_position_in_class'] = $rank_display;
            $processedStudentsSummaryData[$studId]['p1p3_total_students_in_class'] = $totalStudentsInClass;

            // Optional: Re-generate P1-P3 remarks if they should also consider final rank.
            // Current remark logic for P1-P3 only uses average. If rank is needed for remarks:
            // $currentStudentPerformanceForRankedRemark = $processedStudentsSummaryData[$studId]; // This now includes rank & total students
            // $processedStudentsSummaryData[$studId]['auto_classteachers_remark_text'] = generateClassTeacherRemarkUtil($currentStudentPerformanceForRankedRemark, false);
            // $processedStudentsSummaryData[$studId]['auto_headteachers_remark_text'] = generateHeadTeacherRemarkUtil($currentStudentPerformanceForRankedRemark, false);

            $previous_score = $score;
        }
    }

    // Save all summaries to database
    foreach ($processedStudentsSummaryData as $studentId => $summaryDataToSave) {
        if (!saveStudentReportSummary($pdo, $summaryDataToSave)) {
            throw new Exception("Failed to save report summary with remarks for student ID: " . $studentId . ". Check dal.php error logs if any.");
        }
    }

    $pdo->commit();
    $_SESSION['success_message'] = "Calculations, summaries, and automatic remarks generated successfully for Batch ID: " . htmlspecialchars($batch_id);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['error_message'] = "Error during calculations/remarks for Batch ID " . htmlspecialchars($batch_id) . ": " . $e->getMessage() . " (File: " . basename($e->getFile()) . ", Line: " . $e->getLine() .")";
}

header('Location: view_processed_data.php?batch_id=' . $batch_id);
exit;
?>
