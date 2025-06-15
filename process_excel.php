<?php
// ... (session_start, autoloader, helper functions - unchanged from previous version of process_excel.php)
// The existing helper functions (getGradeFromScore, getRemarkFromScore, getPointsFromGrade) are still useful
// as P1-P3 reports will also show individual subject grades/remarks, even if not used for ranking.
// getDivisionAndStatus_P4_P7 is specific to P4-P7 and won't be used for P1-P3.

session_start();

if (!file_exists('vendor/autoload.php')) {
    $_SESSION['error_message'] = 'Composer dependencies not installed. Please run "composer install".';
    header('Location: index.php');
    exit;
}
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// --- HELPER FUNCTIONS (getGradeFromScore, getRemarkFromScore, getPointsFromGrade - assume they are here and correct) ---
function getGradeFromScore($score) {
    if ($score === null || $score === '' || $score === 'N/A' || !is_numeric($score)) return 'N/A';
    $score = floatval($score);
    if ($score > 100 || $score < 0) return 'N/A';
    if ($score >= 90) return 'D1';
    if ($score >= 80) return 'D2';
    if ($score >= 70) return 'C3';
    if ($score >= 60) return 'C4';
    if ($score >= 55) return 'C5';
    if ($score >= 50) return 'C6';
    if ($score >= 45) return 'P7';
    if ($score >= 40) return 'P8';
    return 'F9';
}

function getRemarkFromScore($score, $remarksMap) {
    if ($score === null || $score === '' || $score === 'N/A' || !is_numeric($score)) return 'N/A';
    $score = floatval($score);
    if ($score < 0 || $score > 100) return 'N/A';
    krsort($remarksMap);
    foreach ($remarksMap as $minScore => $remark) {
        if ($score >= $minScore) return $remark;
    }
    return 'Fail';
}

function getPointsFromGrade($grade, $gradingScalePoints) {
    return isset($gradingScalePoints[$grade]) ? $gradingScalePoints[$grade] : 0;
}

function getDivisionAndStatus_P4_P7($studentSubjectsData, $coreSubjectKeysP4_P7, $gradingScalePointsMap) {
    $aggregatePoints = 0;
    $coreEOTMissingCount = 0;
    $validCoreEOTScoresExist = false;

    foreach ($coreSubjectKeysP4_P7 as $coreSubKey) {
        $subjectInfo = $studentSubjectsData[$coreSubKey] ?? null;
        if ($subjectInfo && isset($subjectInfo['eot']) && $subjectInfo['eot'] !== 'N/A' && is_numeric($subjectInfo['eot'])) {
            $validCoreEOTScoresExist = true;
            $eotGrade = getGradeFromScore($subjectInfo['eot']);
            $aggregatePoints += getPointsFromGrade($eotGrade, $gradingScalePointsMap);
        } else {
            $coreEOTMissingCount++;
        }
    }

    if (!$validCoreEOTScoresExist && $coreEOTMissingCount === count($coreSubjectKeysP4_P7)) {
        return ['division' => 'Division X', 'aggregate_points' => 0];
    }

    if ($aggregatePoints >= 35 && $aggregatePoints <= 36) return ['division' => 'Grade U', 'aggregate_points' => $aggregatePoints];
    if ($aggregatePoints >= 30 && $aggregatePoints <= 34) return ['division' => 'Division Four', 'aggregate_points' => $aggregatePoints];
    if ($aggregatePoints >= 24 && $aggregatePoints <= 29) return ['division' => 'Division Three', 'aggregate_points' => $aggregatePoints];
    if ($aggregatePoints >= 13 && $aggregatePoints <= 23) return ['division' => 'Division Two', 'aggregate_points' => $aggregatePoints];
    if ($aggregatePoints >= 4 && $aggregatePoints <= 12) return ['division' => 'Division One', 'aggregate_points' => $aggregatePoints];

    return ['division' => 'Ungraded', 'aggregate_points' => $aggregatePoints];
}


// --- Main Processing Logic (largely same as before until P1-P3 specific calculations) ---
$_SESSION['report_data'] = null;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = 'Invalid request method.';
    header('Location: index.php');
    exit;
}

$selectedClass = htmlspecialchars($_POST['class_selection'] ?? '');
$year = htmlspecialchars($_POST['year'] ?? '');
$term = htmlspecialchars($_POST['term'] ?? '');
$termEndDate = htmlspecialchars($_POST['term_end_date'] ?? '');
$nextTermBeginDate = htmlspecialchars($_POST['next_term_begin_date'] ?? '');

$classInfo = compact('selectedClass', 'year', 'term', 'termEndDate', 'nextTermBeginDate');

$teacherInitials = [];
if (isset($_POST['teacher_initials']) && is_array($_POST['teacher_initials'])) {
    foreach ($_POST['teacher_initials'] as $subject => $initial) {
        $teacherInitials[htmlspecialchars($subject)] = htmlspecialchars($initial);
    }
}
$generalRemarks = [
    'class_teacher' => htmlspecialchars($_POST['class_teacher_remarks'] ?? ''),
    'head_teacher' => htmlspecialchars($_POST['head_teacher_remarks'] ?? ''),
];

if (empty($selectedClass) || empty($year) || empty($term) || empty($termEndDate) || empty($nextTermBeginDate)) {
    $_SESSION['error_message'] = 'Class, Year, Term, Term End Date, and Next Term Begin Date are required.';
    header('Location: index.php');
    exit;
}

$expectedSubjectKeys = [];
$requiredSubjectKeys = [];
$coreSubjectKeysP4_P7 = [];
$p1p3SubjectKeys = []; // Define P1-P3 subject keys

$isP4_P7 = in_array($selectedClass, ['P4', 'P5', 'P6', 'P7']);
$isP1_P3 = in_array($selectedClass, ['P1', 'P2', 'P3']);

if ($isP4_P7) {
    $expectedSubjectKeys = ['english', 'mtc', 'science', 'sst', 'kiswahili'];
    $requiredSubjectKeys = ['english', 'mtc', 'science', 'sst'];
    $coreSubjectKeysP4_P7 = ['english', 'mtc', 'science', 'sst'];
} elseif ($isP1_P3) {
    $p1p3SubjectKeys = ['english', 'mtc', 're', 'lit1', 'lit2', 'local_lang'];
    $expectedSubjectKeys = $p1p3SubjectKeys;
    $requiredSubjectKeys = $p1p3SubjectKeys;
} else {
    $_SESSION['error_message'] = 'Invalid class selected: ' . $selectedClass;
    header('Location: index.php');
    exit;
}

$studentsData = [];
$uploadedFiles = $_FILES['subject_files'];

// Check required files
foreach ($requiredSubjectKeys as $reqKey) {
    if (!isset($uploadedFiles['tmp_name'][$reqKey]) || $uploadedFiles['error'][$reqKey] !== UPLOAD_ERR_OK) {
        $_SESSION['error_message'] = ucfirst(str_replace('_', ' ', $reqKey)) . " file is required for class " . $selectedClass . ". Error: " . ($uploadedFiles['error'][$reqKey] ?? 'Unknown');
        header('Location: index.php');
        exit;
    }
}

$gradingScalePointsMap = ['D1'=>1, 'D2'=>2, 'C3'=>3, 'C4'=>4, 'C5'=>5, 'C6'=>6, 'P7'=>7, 'P8'=>8, 'F9'=>9, 'N/A'=>0];
$remarksScoreMap = [90=>'Outstanding', 80=>'Very Good', 70=>'Good', 60=>'Fair', 55=>'Satisfactory', 50=>'Average', 45=>'Pass', 40=>'Low Pass', 0=>'Fail'];

foreach ($expectedSubjectKeys as $subjectInternalKey) {
    // Handle optional Kiswahili for P4-P7
    if ($isP4_P7 && $subjectInternalKey === 'kiswahili' && (!isset($uploadedFiles['tmp_name'][$subjectInternalKey]) || $uploadedFiles['error'][$subjectInternalKey] === UPLOAD_ERR_NO_FILE)) {
        continue;
    }
    if (!isset($uploadedFiles['tmp_name'][$subjectInternalKey]) || $uploadedFiles['error'][$subjectInternalKey] !== UPLOAD_ERR_OK) {
        // Should have been caught by required check if it was required. This handles if logic changes or for truly optional subjects.
        continue;
    }

    $filePath = $uploadedFiles['tmp_name'][$subjectInternalKey];
    try {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $subjectNameFromFileCell = $sheet->getCellByColumnAndRow(1, 1)->getValue();
        $subjectDisplayName = !empty($subjectNameFromFileCell) ? trim(strval($subjectNameFromFileCell)) : ucfirst($subjectInternalKey);
        $highestRow = $sheet->getHighestDataRow();

        if ($highestRow < 3) continue;

        for ($row = 3; $row <= $highestRow; $row++) {
            $studentName = trim(strval($sheet->getCell('A' . $row)->getValue()));
            if (empty($studentName)) continue;

            if (!isset($studentsData[$studentName])) {
                $studentsData[$studentName] = ['name' => $studentName, 'subjects' => []];
            }

            $botScoreRaw = $sheet->getCell('B' . $row)->getValue();
            $motScoreRaw = $sheet->getCell('C' . $row)->getValue();
            $eotScoreRaw = $sheet->getCell('D' . $row)->getValue();

            $botScore = ($botScoreRaw !== null && (string)$botScoreRaw !== '' && is_numeric($botScoreRaw)) ? floatval($botScoreRaw) : 'N/A';
            $motScore = ($motScoreRaw !== null && (string)$motScoreRaw !== '' && is_numeric($motScoreRaw)) ? floatval($motScoreRaw) : 'N/A';
            $eotScore = ($eotScoreRaw !== null && (string)$eotScoreRaw !== '' && is_numeric($eotScoreRaw)) ? floatval($eotScoreRaw) : 'N/A';

            $eotGrade = getGradeFromScore($eotScore);

            $studentsData[$studentName]['subjects'][$subjectInternalKey] = [
                'subject_display_name' => $subjectDisplayName,
                'bot' => $botScore, 'bot_grade' => getGradeFromScore($botScore),
                'mot' => $motScore, 'mot_grade' => getGradeFromScore($motScore),
                'eot' => $eotScore, 'eot_grade' => $eotGrade,
                'eot_points' => ($isP4_P7) ? getPointsFromGrade($eotGrade, $gradingScalePointsMap) : null, // Points only relevant for P4-P7 aggregates
                'remarks' => getRemarkFromScore($eotScore, $remarksScoreMap),
            ];
        }
    } catch (\Exception $e) {
        $_SESSION['error_message'] = "Error processing " . ucfirst($subjectInternalKey) . " file: " . $e->getMessage();
        header('Location: index.php');
        exit;
    }
}

if (empty($studentsData)) {
    $_SESSION['error_message'] = 'No student data processed. Check Excel files and class selection.';
    header('Location: index.php');
    exit;
}

// Post-processing
$totalStudentsInClass = count($studentsData);

foreach ($studentsData as $studentName => &$studentDetails) {
    // Ensure all expected subjects exist for structure, fill with N/A if missing
    foreach ($expectedSubjectKeys as $expSubKey) {
        if (!isset($studentDetails['subjects'][$expSubKey])) {
            $studentDetails['subjects'][$expSubKey] = [
                'subject_display_name' => ucfirst($expSubKey),
                'bot' => 'N/A', 'bot_grade' => 'N/A', 'mot' => 'N/A', 'mot_grade' => 'N/A',
                'eot' => 'N/A', 'eot_grade' => 'N/A',
                'eot_points' => ($isP4_P7) ? 0 : null,
                'remarks' => 'N/A',
            ];
        }
    }

    if ($isP4_P7) {
        $divisionResult = getDivisionAndStatus_P4_P7($studentDetails['subjects'], $coreSubjectKeysP4_P7, $gradingScalePointsMap);
        $studentDetails['aggregate_points_p4p7'] = $divisionResult['aggregate_points'];
        $studentDetails['division_p4p7'] = $divisionResult['division'];
    } elseif ($isP1_P3) {
        $totalEotScore_P1P3 = 0;
        $subjectsWithEot_P1P3 = 0;
        foreach ($p1p3SubjectKeys as $p1p3SubKey) {
            $eotScore = $studentDetails['subjects'][$p1p3SubKey]['eot'] ?? 'N/A';
            if ($eotScore !== 'N/A' && is_numeric($eotScore)) {
                $totalEotScore_P1P3 += floatval($eotScore);
                $subjectsWithEot_P1P3++;
            }
        }
        $studentDetails['total_eot_p1p3'] = $totalEotScore_P1P3;
        $studentDetails['average_score_p1p3'] = ($subjectsWithEot_P1P3 > 0) ? round($totalEotScore_P1P3 / $subjectsWithEot_P1P3, 2) : 0;
        // Placeholder for rank, will be calculated next
        $studentDetails['position_p1p3'] = 0;
    }
}
unset($studentDetails);


// Calculate P1-P3 Ranks if applicable
if ($isP1_P3 && !empty($studentsData)) {
    $studentAverages = [];
    foreach ($studentsData as $studentName => $details) {
        $studentAverages[$studentName] = $details['average_score_p1p3'] ?? 0;
    }
    arsort($studentAverages, SORT_NUMERIC); // Sort by average score, descending

    $rank_display = 0;
    $previous_score = -1;
    $students_processed_for_rank = 0;

    foreach ($studentAverages as $studentName => $score) {
        $students_processed_for_rank++;
        if ($score != $previous_score) {
            $rank_display = $students_processed_for_rank;
        }
        $studentsData[$studentName]['position_p1p3'] = $rank_display;
        $previous_score = $score;
    }
}


$_SESSION['report_data'] = [
    'students' => $studentsData,
    'class_info' => $classInfo,
    'teacher_initials' => $teacherInitials,
    'general_remarks' => $generalRemarks,
    'grading_scale_points_map' => $gradingScalePointsMap,
    'remarks_score_map' => $remarksScoreMap,
    'grading_display_scale' => ['D1 (90-100)', 'D2 (80-89)', 'C3 (70-79)', 'C4 (60-69)', 'C5 (55-59)', 'C6 (50-54)', 'P7 (45-49)', 'P8 (40-44)', 'F9 (0-39)'],
    'is_p4_p7' => $isP4_P7,
    'is_p1_p3' => $isP1_P3,
    'core_subjects_p4p7' => $isP4_P7 ? $coreSubjectKeysP4_P7 : [],
    'p1p3_subject_keys' => $isP1_P3 ? $p1p3SubjectKeys : [],
    'expected_subjects_for_class' => $expectedSubjectKeys,
    'total_students_in_class' => $totalStudentsInClass
];

$_SESSION['success_message'] = 'Data processed successfully for class ' . $selectedClass . '. Ready to generate reports.';
header('Location: index.php');
exit;
?>
