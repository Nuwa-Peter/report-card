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
// Define the remarks score map for getSubjectEOTRemarkUtil - this was missing from the prompt but is needed.
// Using the one from the old process_excel.php for now.
$remarksScoreMap = [90=>'Outstanding', 80=>'Very Good', 70=>'Good', 60=>'Fair', 55=>'Satisfactory', 50=>'Average', 45=>'Pass', 40=>'Low Pass', 0=>'Fail'];


$studentsRawData = getStudentsWithScoresForBatch($pdo, $batch_id); // Keyed by student_id
$processedStudentsSummaryData = [];
// This array will hold the detailed per-subject calculated data for each student,
// which report_card.php expects. This part was missing from previous considerations for run_calculations.
$enrichedStudentDataForReportCard = [];


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
            'p1p3_total_students_in_class' => null,
            'auto_classteachers_remark_text' => null,
            'auto_headteachers_remark_text' => null
        ];

        // Initialize structure for enriched data (for report_card.php)
        $enrichedStudentDataForReportCard[$studentId] = $studentData; // copies name, lin_no, id
        $enrichedStudentDataForReportCard[$studentId]['subjects'] = [];


        // Calculate per-subject grades and remarks first
        foreach ($studentData['subjects'] as $subjectCode => $subjectDetails) {
            $eotScore = $subjectDetails['eot_score'] ?? 'N/A';
            $botScore = $subjectDetails['bot_score'] ?? 'N/A';
            $motScore = $subjectDetails['mot_score'] ?? 'N/A';

            $eotGrade = getGradeFromScoreUtil($eotScore);
            $botGrade = getGradeFromScoreUtil($botScore);
            $motGrade = getGradeFromScoreUtil($motScore);
            $eotRemark = getSubjectEOTRemarkUtil($eotScore, $remarksScoreMap);
            $eotPoints = ($isP4_P7) ? getPointsFromGradeUtil($eotGrade, $gradingScalePointsMap) : null;

            // Store these calculated per-subject details for report_card.php
            $enrichedStudentDataForReportCard[$studentId]['subjects'][$subjectCode] = array_merge(
                $subjectDetails, // carries over raw scores, subject_id, subject_name_full
                [
                    'bot_grade' => $botGrade,
                    'mot_grade' => $motGrade,
                    'eot_grade' => $eotGrade,
                    'eot_remark' => $eotRemark,
                    'eot_points' => $eotPoints // Will be used by calculateP4P7OverallPerformanceUtil
                ]
            );
        }


        if ($isP4_P7) {
            // Pass the subjects array (which now contains eot_points) from the enriched data
            $p4p7_results = calculateP4P7OverallPerformanceUtil($enrichedStudentDataForReportCard[$studentId]['subjects'], $coreSubjectKeysP4_P7);
            $summaryData['p4p7_aggregate_points'] = $p4p7_results['p4p7_aggregate_points'];
            $summaryData['p4p7_division'] = $p4p7_results['p4p7_division'];

            $studentPerformanceInputForRemarks['p4p7_division'] = $summaryData['p4p7_division'];
            $studentPerformanceInputForRemarks['p4p7_aggregate_points'] = $summaryData['p4p7_aggregate_points'];
        } elseif ($isP1_P3) {
            $totalEotScore = 0;
            $subjectsWithEotCount = 0;
            foreach ($p1p3SubjectKeys as $p1p3SubKey) {
                $subjectInfo = $enrichedStudentDataForReportCard[$studentId]['subjects'][$p1p3SubKey] ?? null;
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

        $summaryData['auto_classteachers_remark_text'] = generateClassTeacherRemarkUtil($studentPerformanceInputForRemarks, $isP4_P7);
        $summaryData['auto_headteachers_remark_text'] = generateHeadTeacherRemarkUtil($studentPerformanceInputForRemarks, $isP4_P7);

        $processedStudentsSummaryData[$studentId] = $summaryData;
    }

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
            $previous_score = $score;
        }
    }

    foreach ($processedStudentsSummaryData as $studentId => $summaryDataToSave) {
        if (!saveStudentReportSummary($pdo, $summaryDataToSave)) {
            throw new Exception("Failed to save report summary with remarks for student ID: " . $studentId . ". Check dal.php error logs if any.");
        }
    }

    // Store the fully enriched data (raw scores + per-subject calculated grades/remarks) in session for report_card.php
    // This is a temporary measure until report_card.php is refactored to fetch this directly or via a more specific DAL function.
    // This addresses the data gap identified for report_card.php (Step 10).
    $_SESSION['enriched_students_data_for_batch_' . $batch_id] = $enrichedStudentDataForReportCard;


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
