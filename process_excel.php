<?php
session_start();
// Ensure vendor/autoload.php exists
if (!file_exists('vendor/autoload.php')) {
    if (headers_sent()) { die('CRITICAL ERROR: Headers already sent. Composer autoload missing.'); }
    $_SESSION['error_message'] = 'Composer dependencies not installed. Please run "composer install".';
    // Assuming index.php is the dashboard, data_entry.php is the form page for uploads
    header('Location: index.php');
    exit;
}
require 'vendor/autoload.php';

// Ensure db_connection.php exists
if (!file_exists('db_connection.php')) {
    if (headers_sent()) { die('CRITICAL ERROR: Headers already sent. DB connection file missing.'); }
    $_SESSION['error_message'] = 'Database connection file missing. Please contact administrator.';
    header('Location: index.php');
    exit;
}
require 'db_connection.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$_SESSION['error_message'] = null;
$_SESSION['success_message'] = null;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (headers_sent()) { die('Invalid request method and headers already sent.'); }
    $_SESSION['error_message'] = 'Invalid request method.';
    header('Location: data_entry.php');
    exit;
}

$selectedClassValue = htmlspecialchars($_POST['class_selection'] ?? '');
$yearValue = htmlspecialchars($_POST['year'] ?? '');
$termValue = htmlspecialchars($_POST['term'] ?? '');
$termEndDate = htmlspecialchars($_POST['term_end_date'] ?? '');
$nextTermBeginDate = htmlspecialchars($_POST['next_term_begin_date'] ?? '');
$teacherInitialsFromForm = $_POST['teacher_initials'] ?? [];

if (empty($selectedClassValue) || empty($yearValue) || empty($termValue) || empty($termEndDate) || empty($nextTermBeginDate)) {
    if (headers_sent()) { die('Required form fields missing and headers already sent.'); }
    $_SESSION['error_message'] = 'Class, Year, Term, Term End Date, and Next Term Begin Date are required.';
    header('Location: data_entry.php');
    exit;
}

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
    if (headers_sent()) { die('Invalid class selection and headers already sent.'); }
    $_SESSION['error_message'] = 'Invalid class selected: ' . htmlspecialchars($selectedClassValue);
    header('Location: data_entry.php');
    exit;
}

$uploadedFiles = $_FILES['subject_files'];
foreach ($requiredSubjectInternalKeys as $reqKey) {
    if (!isset($uploadedFiles['tmp_name'][$reqKey]) || $uploadedFiles['error'][$reqKey] !== UPLOAD_ERR_OK) {
        if (headers_sent()) { die('Required file missing for ' . htmlspecialchars($reqKey) . ' and headers already sent.'); }
        $_SESSION['error_message'] = ucfirst(str_replace('_', ' ', $reqKey)) . " file is required for class " . htmlspecialchars($selectedClassValue) . ". Error code: " .($uploadedFiles['error'][$reqKey] ?? 'N/A');
        header('Location: data_entry.php');
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

    if ($reportBatchId) {
        $stmtUpdateBatch = $pdo->prepare("UPDATE report_batch_settings SET term_end_date = :term_end, next_term_begin_date = :next_term_begin, import_date = CURRENT_TIMESTAMP WHERE id = :id");
        $stmtUpdateBatch->execute([':term_end' => $termEndDate, ':next_term_begin' => $nextTermBeginDate, ':id' => $reportBatchId]);
        $stmtDeleteOldScores = $pdo->prepare("DELETE FROM scores WHERE report_batch_id = :batch_id");
        $stmtDeleteOldScores->execute([':batch_id' => $reportBatchId]);
        $stmtDeleteOldSummaries = $pdo->prepare("DELETE FROM student_report_summary WHERE report_batch_id = :batch_id");
        $stmtDeleteOldSummaries->execute([':batch_id' => $reportBatchId]);
    } else {
        $stmtInsertBatch = $pdo->prepare("INSERT INTO report_batch_settings (academic_year_id, term_id, class_id, term_end_date, next_term_begin_date) VALUES (:year_id, :term_id, :class_id, :term_end, :next_term_begin)");
        $stmtInsertBatch->execute([':year_id' => $academicYearId, ':term_id' => $termId, ':class_id' => $classId, ':term_end' => $termEndDate, ':next_term_begin' => $nextTermBeginDate]);
        $reportBatchId = $pdo->lastInsertId();
    }

    if (!$reportBatchId) {
        throw new Exception("Could not create or retrieve report batch ID.");
    }

    foreach ($expectedSubjectInternalKeys as $subjectInternalKey) {
        if (!isset($uploadedFiles['tmp_name'][$subjectInternalKey]) || $uploadedFiles['error'][$subjectInternalKey] !== UPLOAD_ERR_OK) {
            if ($subjectInternalKey === 'kiswahili' && $isP4_P7) {
                continue;
            }
            if (in_array($subjectInternalKey, $requiredSubjectInternalKeys)) {
                 throw new Exception("Required file for " . htmlspecialchars($subjectInternalKey) . " was not available during detailed processing.");
            }
            continue;
        }

        $filePath = $uploadedFiles['tmp_name'][$subjectInternalKey];
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();

        // Subject name is no longer read from the Excel sheet.
        // It's derived from $subjectInternalKey (from the form upload context).
        $stmtSubjectName = $pdo->prepare("SELECT subject_name_full FROM subjects WHERE subject_code = :code");
        $stmtSubjectName->execute([':code' => $subjectInternalKey]);
        $subjectNameForFile = $stmtSubjectName->fetchColumn();

        if (!$subjectNameForFile) {
            // If subject_name_full is not found in DB, construct a fallback name or throw error.
            // Using subject_code as fallback might be acceptable if subject_name_full is optional in DB.
            $subjectNameForFile = ucfirst(str_replace('_', ' ', $subjectInternalKey)); // Fallback name
            // Optionally, throw an exception if a full name is strictly required:
            // throw new Exception("Could not determine full subject name for internal key: " . htmlspecialchars($subjectInternalKey));
        }

        // Ensure subjectId is found or created.
        // The 'subject_name_full' passed to findOrCreateLookup will be the one derived from DB or the fallback.
        $subjectId = findOrCreateLookup($pdo, 'subjects', 'subject_code', $subjectInternalKey, ['subject_name_full' => $subjectNameForFile]);
        if (!$subjectId) {
            throw new Exception("Failed to find or create subject ID for: " . htmlspecialchars($subjectNameForFile));
        }

        // Headers are LIN, Names/Name, BOT, MOT, EOT in row 1
        $headerLIN = trim(strtoupper(strval($sheet->getCell('A1')->getValue())));
        $headerName = trim(strtoupper(strval($sheet->getCell('B1')->getValue()))); // Handles "Names" or "Name"
        $headerBOT = trim(strtoupper(strval($sheet->getCell('C1')->getValue())));
        $headerMOT = trim(strtoupper(strval($sheet->getCell('D1')->getValue())));
        $headerEOT = trim(strtoupper(strval($sheet->getCell('E1')->getValue())));

        // Validate headers
        if ($headerLIN !== 'LIN' || !in_array($headerName, ['NAMES', 'NAME']) || $headerBOT !== 'BOT' || $headerMOT !== 'MOT' || $headerEOT !== 'EOT') {
            throw new Exception("Invalid headers in Excel file for subject '" . htmlspecialchars($subjectNameForFile) . "'. Expected A1='LIN', B1='Names/Name', C1='BOT', D1='MOT', E1='EOT'. Found: $headerLIN, $headerName, $headerBOT, $headerMOT, $headerEOT");
        }

        $highestRow = $sheet->getHighestDataRow();
        // Data now always starts from row 2, as the instructional row 2 has been removed from the template.
        $startRow = 2;

        if ($highestRow < $startRow) { // Check if there's any data after the header row
             error_log("Warning: No student data rows found in file for " . htmlspecialchars($subjectNameForFile) . " (File for " . htmlspecialchars($subjectInternalKey) . "). Sheet might be empty after row 1.");
             continue; // Skip this file if no data rows
        }

        for ($row = $startRow; $row <= $highestRow; $row++) {
            $linValue = trim(strval($sheet->getCell('A' . $row)->getValue()));
            $studentNameRaw = trim(strval($sheet->getCell('B' . $row)->getValue()));

            if (empty($studentNameRaw)) continue; // Skip if no student name
            $studentNameAllCaps = strtoupper($studentNameRaw);

            // Handle LIN: if empty, store as NULL or an empty string based on DB schema.
            // For consistency, let's use NULL if the schema allows, or empty string if not.
            // Assuming 'lin_no' column in 'students' table can be NULL.
            $linToStore = !empty($linValue) ? $linValue : null;

            $stmtStudent = $pdo->prepare("SELECT id FROM students WHERE student_name = :name");
            $stmtStudent->execute([':name' => $studentNameAllCaps]);
            $studentId = $stmtStudent->fetchColumn();

            if (!$studentId) {
                $stmtInsertStudent = $pdo->prepare("INSERT INTO students (student_name, current_class_id, lin_no) VALUES (:name, :class_id, :lin_no)");
                $stmtInsertStudent->execute([':name' => $studentNameAllCaps, ':class_id' => $classId, ':lin_no' => $linToStore]);
                $studentId = $pdo->lastInsertId();
            } else {
                // Update existing student's class and LIN (if provided or changed)
                // Ensure lin_no is only updated if a new value is provided or if it's different.
                // If $linToStore is null and DB has a value, we might not want to overwrite.
                // However, if Excel explicitly clears it, it should be cleared in DB.
                // The current logic: $linToStore will be NULL if Excel cell is empty.
                // This means an empty LIN in Excel will set lin_no to NULL in DB.
                $sqlUpdateStudent = "UPDATE students
                                     SET current_class_id = :class_id, lin_no = :lin_no
                                     WHERE id = :id";
                $stmtUpdateStudent = $pdo->prepare($sqlUpdateStudent);
                $stmtUpdateStudent->execute([
                    ':class_id' => $classId,
                    ':lin_no' => $linToStore,
                    ':id' => $studentId
                ]);
            }

            $botScore = $sheet->getCell('C' . $row)->getValue(); // BOT is now in column C
            $motScore = $sheet->getCell('D' . $row)->getValue(); // MOT is now in column D
            $eotScore = $sheet->getCell('E' . $row)->getValue(); // EOT is now in column E

            $sqlScore = "INSERT INTO scores (student_id, subject_id, report_batch_id, bot_score, mot_score, eot_score)
                         VALUES (:student_id, :subject_id, :report_batch_id, :bot, :mot, :eot)
                         ON DUPLICATE KEY UPDATE
                            bot_score = VALUES(bot_score),
                            mot_score = VALUES(mot_score),
                            eot_score = VALUES(eot_score)";
            $stmtScore = $pdo->prepare($sqlScore);

            $paramsForExecute = [
                ':student_id' => $studentId,
                ':subject_id' => $subjectId,
                ':report_batch_id' => $reportBatchId,
                ':bot' => (is_numeric($botScore) ? (float)$botScore : null),
                ':mot' => (is_numeric($motScore) ? (float)$motScore : null),
                ':eot' => (is_numeric($eotScore) ? (float)$eotScore : null)
            ];

            // Standard execution. If HY093 occurs here, the main catch block will handle it.
            // For specific debugging of this HY093, the user would re-insert the echo/exit debug block here.
            $stmtScore->execute($paramsForExecute);
        }
    }

    $pdo->commit();
    $_SESSION['success_message'] = 'Data imported successfully for class ' . htmlspecialchars($selectedClassValue) . ' (Batch ID: ' . htmlspecialchars($reportBatchId) . '). Ready for next steps (calculations).';
    $_SESSION['current_teacher_initials'] = $teacherInitialsFromForm; // Persist for report card generation
    $_SESSION['last_processed_batch_id'] = $reportBatchId; // For linking to view_processed_data

    // Log successful import
    require_once 'dal.php'; // Ensure DAL is available for logActivity
    $logDescription = "Imported data for class " . htmlspecialchars($selectedClassValue) .
                      ", term " . htmlspecialchars($termValue) .
                      ", year " . htmlspecialchars($yearValue) .
                      " (Batch ID: " . htmlspecialchars($reportBatchId) . ").";
    logActivity(
        $pdo,
        $_SESSION['user_id'] ?? null, // user_id from session
        $_SESSION['username'] ?? 'System', // username from session or 'System'
        'BATCH_DATA_IMPORTED',
        $logDescription,
        'batch',
        $reportBatchId,
        null // No specific user to notify for this, it's a general log
    );

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    // For production, use session messages.
    $_SESSION['error_message'] = "Database error during import: " . htmlspecialchars($e->getMessage()) . " (Details logged or check code at Line: " . $e->getLine() . " in " . basename($e->getFile()) . ")";
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['error_message'] = "Processing error during import: " . htmlspecialchars($e->getMessage()) . " (Details logged or check code at Line: " . $e->getLine() . " in " . basename($e->getFile()) . ")";
}

// Redirect back to data_entry.php for normal operation
header('Location: data_entry.php');
exit;
?>
