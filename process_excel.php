<?php
session_start();
// Ensure vendor/autoload.php exists
if (!file_exists('vendor/autoload.php')) {
    $_SESSION['error_message'] = 'Composer dependencies not installed. Please run "composer install".';
    header('Location: index.php');
    exit;
}
require 'vendor/autoload.php';

// Ensure db_connection.php exists
if (!file_exists('db_connection.php')) {
    // This is a critical internal error, should not happen if files are deployed correctly.
    $_SESSION['error_message'] = 'Database connection file missing. Please contact administrator.';
    header('Location: index.php');
    exit;
}
require 'db_connection.php'; // Provides $pdo and findOrCreateLookup function

use PhpOffice\PhpSpreadsheet\IOFactory;

// Clear previous messages
$_SESSION['error_message'] = null;
$_SESSION['success_message'] = null;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = 'Invalid request method.';
    header('Location: index.php');
    exit;
}

// --- Form Data Retrieval ---
$selectedClassValue = htmlspecialchars($_POST['class_selection'] ?? '');
$yearValue = htmlspecialchars($_POST['year'] ?? '');
$termValue = htmlspecialchars($_POST['term'] ?? '');
$termEndDate = htmlspecialchars($_POST['term_end_date'] ?? '');
$nextTermBeginDate = htmlspecialchars($_POST['next_term_begin_date'] ?? '');
$teacherInitialsFromForm = $_POST['teacher_initials'] ?? [];

if (empty($selectedClassValue) || empty($yearValue) || empty($termValue) || empty($termEndDate) || empty($nextTermBeginDate)) {
    $_SESSION['error_message'] = 'Class, Year, Term, Term End Date, and Next Term Begin Date are required.';
    header('Location: index.php');
    exit;
}

// Determine expected and required subjects based on class
$isP4_P7 = in_array($selectedClassValue, ['P4', 'P5', 'P6', 'P7']);
$isP1_P3 = in_array($selectedClassValue, ['P1', 'P2', 'P3']);
$expectedSubjectInternalKeys = [];
$requiredSubjectInternalKeys = [];

if ($isP4_P7) {
    $expectedSubjectInternalKeys = ['english', 'mtc', 'science', 'sst', 'kiswahili'];
    $requiredSubjectInternalKeys = ['english', 'mtc', 'science', 'sst'];
} elseif ($isP1_P3) {
    $expectedSubjectInternalKeys = ['english', 'mtc', 're', 'lit1', 'lit2', 'local_lang'];
    $requiredSubjectInternalKeys = $expectedSubjectInternalKeys;
} else {
    $_SESSION['error_message'] = 'Invalid class selected: ' . $selectedClassValue;
    header('Location: index.php');
    exit;
}

$uploadedFiles = $_FILES['subject_files'];
foreach ($requiredSubjectInternalKeys as $reqKey) {
    if (!isset($uploadedFiles['tmp_name'][$reqKey]) || $uploadedFiles['error'][$reqKey] !== UPLOAD_ERR_OK) {
        $_SESSION['error_message'] = ucfirst(str_replace('_', ' ', $reqKey)) . " file is required for class " . $selectedClassValue . ". Error code: " .($uploadedFiles['error'][$reqKey] ?? 'N/A');
        header('Location: index.php');
        exit;
    }
}

$pdo->beginTransaction();
try {
    $academicYearId = findOrCreateLookup($pdo, 'academic_years', 'year_name', $yearValue);
    $termId = findOrCreateLookup($pdo, 'terms', 'term_name', $termValue);
    $classId = findOrCreateLookup($pdo, 'classes', 'class_name', $selectedClassValue);

    $stmtBatch = $pdo->prepare("SELECT id FROM report_batch_settings WHERE academic_year_id = :year_id AND term_id = :term_id AND class_id = :class_id");
    $stmtBatch->execute([':year_id' => $academicYearId, ':term_id' => $termId, ':class_id' => $classId]);
    $reportBatchId = $stmtBatch->fetchColumn();

    if ($reportBatchId) { // Batch exists, update it
        $stmtUpdateBatch = $pdo->prepare("UPDATE report_batch_settings SET term_end_date = :term_end, next_term_begin_date = :next_term_begin, import_date = CURRENT_TIMESTAMP WHERE id = :id");
        $stmtUpdateBatch->execute([
            ':term_end' => $termEndDate,
            ':next_term_begin' => $nextTermBeginDate,
            ':id' => $reportBatchId
        ]);
        // Optionally, consider deleting old scores for this batch before re-importing
        $stmtDeleteOldScores = $pdo->prepare("DELETE FROM scores WHERE report_batch_id = :batch_id");
        $stmtDeleteOldScores->execute([':batch_id' => $reportBatchId]);
        // Also delete old student_report_summary for this batch
        $stmtDeleteOldSummaries = $pdo->prepare("DELETE FROM student_report_summary WHERE report_batch_id = :batch_id");
        $stmtDeleteOldSummaries->execute([':batch_id' => $reportBatchId]);


    } else { // Batch does not exist, insert it
        $stmtInsertBatch = $pdo->prepare("INSERT INTO report_batch_settings (academic_year_id, term_id, class_id, term_end_date, next_term_begin_date) VALUES (:year_id, :term_id, :class_id, :term_end, :next_term_begin)");
        $stmtInsertBatch->execute([
            ':year_id' => $academicYearId,
            ':term_id' => $termId,
            ':class_id' => $classId,
            ':term_end' => $termEndDate,
            ':next_term_begin' => $nextTermBeginDate
        ]);
        $reportBatchId = $pdo->lastInsertId();
    }

    if (!$reportBatchId) {
        throw new Exception("Could not create or retrieve report batch ID.");
    }

    foreach ($expectedSubjectInternalKeys as $subjectInternalKey) {
        if (!isset($uploadedFiles['tmp_name'][$subjectInternalKey]) || $uploadedFiles['error'][$subjectInternalKey] !== UPLOAD_ERR_OK) {
            if (in_array($subjectInternalKey, $requiredSubjectInternalKeys)) {
                throw new Exception("Required file for " . $subjectInternalKey . " missing unexpectedly during processing loop.");
            }
            continue;
        }

        $filePath = $uploadedFiles['tmp_name'][$subjectInternalKey];
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();

        $subjectNameFromFile = trim(strval($sheet->getCell('A1')->getValue()));
        if (empty($subjectNameFromFile)) {
            throw new Exception("Subject name missing in Cell A1 for uploaded file keyed as '" . $subjectInternalKey . "'. Please ensure A1 contains the subject name (e.g., ENGLISH).");
        }

        // Use subject_code for internal key, subject_name_full for display name from file
        $subjectId = findOrCreateLookup($pdo, 'subjects', 'subject_code', $subjectInternalKey, ['subject_name_full' => $subjectNameFromFile]);

        $headerBOT = trim(strval($sheet->getCell('B1')->getValue()));
        $headerMOT = trim(strval($sheet->getCell('C1')->getValue()));
        $headerEOT = trim(strval($sheet->getCell('D1')->getValue()));
        if (strtoupper($headerBOT) !== 'BOT' || strtoupper($headerMOT) !== 'MOT' || strtoupper($headerEOT) !== 'EOT') {
            throw new Exception("Invalid headers in Excel file for '" . $subjectNameFromFile . "'. Expected 'BOT', 'MOT', 'EOT' in cells B1, C1, D1 respectively.");
        }

        $highestRow = $sheet->getHighestDataRow();
        if ($highestRow < 2) { // Row 1 is headers/subj name, so data starts at row 2
             $_SESSION['error_message'] = "Warning: No student data found in file for " . $subjectNameFromFile . " (File keyed as " . $subjectInternalKey . "). It might be empty or incorrectly formatted after row 1.";
             // This is a warning, not a fatal error for this file, allow other files to process.
             continue;
        }


        for ($row = 2; $row <= $highestRow; $row++) { // Data rows start from 2
            $studentNameRaw = trim(strval($sheet->getCell('A' . $row)->getValue()));
            if (empty($studentNameRaw)) continue;
            $studentNameAllCaps = strtoupper($studentNameRaw);

            $stmtStudent = $pdo->prepare("SELECT id FROM students WHERE student_name = :name");
            $stmtStudent->execute([':name' => $studentNameAllCaps]);
            $studentId = $stmtStudent->fetchColumn();
            if (!$studentId) {
                $stmtInsertStudent = $pdo->prepare("INSERT INTO students (student_name, current_class_id) VALUES (:name, :class_id)");
                $stmtInsertStudent->execute([':name' => $studentNameAllCaps, ':class_id' => $classId]); // Associate with current batch's class
                $studentId = $pdo->lastInsertId();
            } else {
                $stmtUpdateStudentClass = $pdo->prepare("UPDATE students SET current_class_id = :class_id WHERE id = :id AND (current_class_id IS NULL OR current_class_id != :class_id)");
                $stmtUpdateStudentClass->execute([':class_id' => $classId, ':id' => $studentId]);
            }

            $botScore = $sheet->getCell('B' . $row)->getValue();
            $motScore = $sheet->getCell('C' . $row)->getValue();
            $eotScore = $sheet->getCell('D' . $row)->getValue();

            $sqlScore = "INSERT INTO scores (student_id, subject_id, report_batch_id, bot_score, mot_score, eot_score)
                         VALUES (:student_id, :subject_id, :report_batch_id, :bot, :mot, :eot)
                         ON DUPLICATE KEY UPDATE
                            bot_score = VALUES(bot_score),
                            mot_score = VALUES(mot_score),
                            eot_score = VALUES(eot_score)";
            $stmtScore = $pdo->prepare($sqlScore);
            $stmtScore->execute([
                ':student_id' => $studentId,
                ':subject_id' => $subjectId,
                ':report_batch_id' => $reportBatchId,
                ':bot' => (is_numeric($botScore) ? (float)$botScore : null),
                ':mot' => (is_numeric($motScore) ? (float)$motScore : null),
                ':eot' => (is_numeric($eotScore) ? (float)$eotScore : null)
            ]);
        }
    } // End foreach subject file

    $pdo->commit();
    $_SESSION['success_message'] = 'Data imported successfully for class ' . $selectedClassValue . ' (Batch ID: ' . $reportBatchId . '). Ready to generate reports.';
    $_SESSION['current_teacher_initials'] = $teacherInitialsFromForm;
    $_SESSION['last_processed_batch_id'] = $reportBatchId;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['error_message'] = "Database error during import: " . $e->getMessage() . " (Line: " . $e->getLine() . ")";
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['error_message'] = "Processing error during import: " . $e->getMessage() . " (Line: " . $e->getLine() . ")";
}

header('Location: data_entry.php');
exit;
?>
