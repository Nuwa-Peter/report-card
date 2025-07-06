<?php
session_start();
error_log("PROCESS_EXCEL_SCRIPT_STARTED: Script execution begun at " . date('Y-m-d H:i:s')); // Log script start
date_default_timezone_set('Africa/Kampala');

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

// Helper function to create a sorted key for pairs (used in fuzzy matching)
if (!function_exists('sorted_array_values_for_key')) {
    function sorted_array_values_for_key(array $array): array {
        sort($array, SORT_STRING);
        return $array;
    }
}

$_SESSION['error_message'] = null;
$_SESSION['success_message'] = null;
// For original duplicate checking (student name exists in DB with different LIN/ID)
$_SESSION['potential_duplicates_found'] = [];
$_SESSION['flagged_duplicates_this_run'] = [];
// For new consistency checks
$_SESSION['missing_students_warnings'] = [];
$_SESSION['fuzzy_match_warnings'] = [];
$_SESSION['processed_for_fuzzy_check'] = []; // Stores names from current file for fuzzy check

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
$expectedSubjectInternalKeys = []; // These are the internal codes like 'mtc', 'lit1'
$requiredSubjectInternalKeys = [];

// Define a mapping from human-readable sheet names to internal subject codes
$sheetNameToInternalCodeMap = [
    'english' => 'english', // Case-insensitive matching for sheet names
    'maths' => 'mtc',
    'literacy one' => 'lit1',
    'literacy two' => 'lit2',
    'local language' => 'local_lang',
    'religious education' => 're',
    'science' => 'science',
    'sst' => 'sst',
    'kiswahili' => 'kiswahili'
];

if ($isP4_P7) {
    // For P4-P7, subjects are English, Maths, Science, SST, Kiswahili (optional)
    // Internal codes: english, mtc, science, sst, kiswahili
    $expectedSubjectInternalKeys = ['english', 'mtc', 'science', 'sst', 'kiswahili'];
    $requiredSubjectInternalKeys = ['english', 'mtc', 'science', 'sst']; // Kiswahili is optional
} elseif ($isP1_P3) {
    // For P1-P3, subjects are English, Maths, RE, Lit1, Lit2, Local Language
    // Internal codes: english, mtc, re, lit1, lit2, local_lang
    $expectedSubjectInternalKeys = ['english', 'mtc', 're', 'lit1', 'lit2', 'local_lang'];
    $requiredSubjectInternalKeys = $expectedSubjectInternalKeys; // All are required for P1-P3
} else {
    if (headers_sent()) { die('Invalid class selection and headers already sent.'); }
    $_SESSION['error_message'] = 'Invalid class selected: ' . htmlspecialchars($selectedClassValue);
    header('Location: data_entry.php');
    exit;
}

// --- New: Handle single file upload ---
if (!isset($_FILES['marks_excel_file']) || $_FILES['marks_excel_file']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['error_message'] = 'Marks Excel file is required. Error code: ' . ($_FILES['marks_excel_file']['error'] ?? 'N/A');
    header('Location: data_entry.php');
    exit;
}
$uploadedFilePath = $_FILES['marks_excel_file']['tmp_name'];
// --- End New: Handle single file upload ---

$pdo->beginTransaction();
try {
    $spreadsheet = IOFactory::load($uploadedFilePath); // Load the entire workbook

    // Validate required sheets are present
    $presentSheetNames = $spreadsheet->getSheetNames();
    $presentInternalSubjectKeys = [];
    foreach ($presentSheetNames as $sheetName) {
        $normalizedSheetName = strtolower(trim($sheetName));
        if (isset($sheetNameToInternalCodeMap[$normalizedSheetName])) {
            $presentInternalSubjectKeys[] = $sheetNameToInternalCodeMap[$normalizedSheetName];
        }
    }

    foreach ($requiredSubjectInternalKeys as $reqKey) {
        if (!in_array($reqKey, $presentInternalSubjectKeys)) {
            $_SESSION['error_message'] = "Required subject sheet for '" . ucfirst(str_replace('_', ' ', $reqKey)) . "' is missing from the uploaded Excel file for class " . htmlspecialchars($selectedClassValue) . ".";
            header('Location: data_entry.php');
            exit;
        }
    }
    // --- End Sheet Validation ---

    // --- START PHASE 1: Pre-processing for consistency checks ---
    $allStudentsDataFromFile = []; // Key: 'NAME_LIN', Value: ['name_raw', 'name_caps', 'lin', 'sheets_present' => [], 'first_occurrence' => ['sheet', 'row']]
    $excelSheetNames = $spreadsheet->getSheetNames(); // Get all sheet names from the Excel file

    // Map human-readable expected subject keys to their display names for warnings
    $subjectKeyToDisplayNameMap = [];
    foreach ($sheetNameToInternalCodeMap as $hrName => $internalCode) {
        if (in_array($internalCode, $expectedSubjectInternalKeys)) {
            // Attempt to get full name from DB for better display
            $stmtFullSubjName = $pdo->prepare("SELECT subject_name_full FROM subjects WHERE subject_code = :code");
            $stmtFullSubjName->execute([':code' => $internalCode]);
            $dbSubjName = $stmtFullSubjName->fetchColumn();
            $subjectKeyToDisplayNameMap[$internalCode] = $dbSubjName ?: ucfirst(str_replace('_', ' ', $internalCode));
        }
    }


    foreach ($excelSheetNames as $sheetNameFromFile) {
        $normalizedSheetName = strtolower(trim($sheetNameFromFile));
        if (!isset($sheetNameToInternalCodeMap[$normalizedSheetName])) {
            continue; // Skip sheets not in our map (e.g., "Instructions")
        }
        $subjectInternalKeyForSheet = $sheetNameToInternalCodeMap[$normalizedSheetName];

        // Only process sheets that are expected for this class level
        if (!in_array($subjectInternalKeyForSheet, $expectedSubjectInternalKeys)) {
            continue;
        }

        $currentSheetObject = $spreadsheet->getSheetByName($sheetNameFromFile);
        $headerLIN_pre = trim(strtoupper(strval($currentSheetObject->getCell('A1')->getValue())));
        $headerName_pre = trim(strtoupper(strval($currentSheetObject->getCell('B1')->getValue())));
        // Basic header check for pre-processing; more stringent check later
        if ($headerLIN_pre !== 'LIN' || !in_array($headerName_pre, ['NAMES/NAME', 'NAMES', 'NAME'])) {
            error_log("PRE-CHECK: Skipping sheet '$sheetNameFromFile' due to invalid headers for pre-processing.");
            continue;
        }

        $highestRow = $currentSheetObject->getHighestDataRow();
        for ($row = 2; $row <= $highestRow; $row++) {
            $linValueRaw = trim(strval($currentSheetObject->getCell('A' . $row)->getValue()));
            $studentNameRaw = trim(strval($currentSheetObject->getCell('B' . $row)->getValue()));

            if (empty($studentNameRaw)) continue; // Skip if no student name

            $studentNameAllCaps = strtoupper($studentNameRaw);
            $linToUse = !empty($linValueRaw) ? $linValueRaw : null;
            $studentIdentifierKey = $studentNameAllCaps . '_' . ($linToUse ?: 'NO_LIN');

            if (!isset($allStudentsDataFromFile[$studentIdentifierKey])) {
                $allStudentsDataFromFile[$studentIdentifierKey] = [
                    'name_raw' => $studentNameRaw, // Keep original casing for display if needed
                    'name_caps' => $studentNameAllCaps,
                    'lin' => $linToUse,
                    'sheets_present' => [],
                    'first_occurrence' => ['sheet' => $sheetNameFromFile, 'row' => $row]
                ];
            }
            if (!in_array($subjectInternalKeyForSheet, $allStudentsDataFromFile[$studentIdentifierKey]['sheets_present'])) {
                $allStudentsDataFromFile[$studentIdentifierKey]['sheets_present'][] = $subjectInternalKeyForSheet;
            }
        }
    }
    // --- END PHASE 1 ---

    // --- START "Missing Students Across Sheets" Detection ---
    // Use $requiredSubjectInternalKeys for this check, as these are critical for positioning.
    // Kiswahili is optional for P4-P7, so it's in $expectedSubjectInternalKeys but might not be in $requiredSubjectInternalKeys.
    // If a subject is optional, a student missing from it isn't necessarily an error to flag this way.
    // The definition of $requiredSubjectInternalKeys already correctly handles this.

    $studentsMissingFromSheets = [];
    foreach ($allStudentsDataFromFile as $studentIdKey => $studentData) {
        $missingFrom = [];
        foreach ($requiredSubjectInternalKeys as $requiredKey) {
            if (!in_array($requiredKey, $studentData['sheets_present'])) {
                $missingFrom[] = $subjectKeyToDisplayNameMap[$requiredKey] ?? ucfirst(str_replace('_', ' ', $requiredKey));
            }
        }
        if (!empty($missingFrom)) {
            $studentsMissingFromSheets[] = [
                'name_raw' => $studentData['name_raw'],
                'lin' => $studentData['lin'],
                'missing_from_sheets' => $missingFrom,
                'first_occurrence' => $studentData['first_occurrence'] // For context
            ];
        }
    }
    if (!empty($studentsMissingFromSheets)) {
        $_SESSION['missing_students_warnings'] = $studentsMissingFromSheets;
        error_log("PROCESS_EXCEL: Missing students from sheets warnings generated: " . count($studentsMissingFromSheets));
    }
    // --- END "Missing Students Across Sheets" Detection ---

    // --- START Fuzzy Name Matching (within the same Excel file) ---
    $uniqueStudentNamesFromFile = array_values($allStudentsDataFromFile); // Get a numerically indexed array of student data objects
    $fuzzyMatchPairs = []; // To avoid duplicate pair reporting (e.g. A vs B and B vs A)

    for ($i = 0; $i < count($uniqueStudentNamesFromFile); $i++) {
        for ($j = $i + 1; $j < count($uniqueStudentNamesFromFile); $j++) {
            $student1Data = $uniqueStudentNamesFromFile[$i];
            $student2Data = $uniqueStudentNamesFromFile[$j];

            // Use name_caps for Levenshtein comparison
            $name1 = $student1Data['name_caps'];
            $name2 = $student2Data['name_caps'];

            if ($name1 === $name2) continue; // Skip if names are identical (already handled by unique key in $allStudentsDataFromFile if LINs are same)

            $distance = levenshtein($name1, $name2);

            // Define a threshold for "similar" - e.g., 1 or 2.
            // Consider name length? Shorter names with distance 2 might be very different.
            // For now, simple threshold.
            $similarityThreshold = 2;
            // Prevent flagging very short names if the distance is a significant portion of their length
            // e.g. "IVY" vs "IVAN" is distance 1, but might be okay. "AN" vs "AX" is distance 1.
            // Let's add a minimum length for names to be considered for fuzzy matching if distance is > 1
            $minNameLengthForBroaderFuzzy = 4;

            if ($distance > 0 && $distance <= $similarityThreshold) {
                if ($distance === 1 || (strlen($name1) >= $minNameLengthForBroaderFuzzy && strlen($name2) >= $minNameLengthForBroaderFuzzy)) {
                    // Create a sorted key for the pair to avoid duplicates like (A,B) and (B,A)
                    $pairKey = implode('__VS__', sorted_array_values_for_key([$student1Data['name_caps'].'_'.$student1Data['lin'], $student2Data['name_caps'].'_'.$student2Data['lin']]));

                    if (!isset($fuzzyMatchPairs[$pairKey])) {
                         $_SESSION['fuzzy_match_warnings'][] = [
                            'student1_name_raw' => $student1Data['name_raw'],
                            'student1_lin' => $student1Data['lin'],
                            'student1_occurrence' => $student1Data['first_occurrence'],
                            'student2_name_raw' => $student2Data['name_raw'],
                            'student2_lin' => $student2Data['lin'],
                            'student2_occurrence' => $student2Data['first_occurrence'],
                            'levenshtein_distance' => $distance
                        ];
                        $fuzzyMatchPairs[$pairKey] = true; // Mark this pair as reported
                    }
                }
            }
        }
    }
    if (!empty($_SESSION['fuzzy_match_warnings'])) {
        error_log("PROCESS_EXCEL: Fuzzy match warnings generated: " . count($_SESSION['fuzzy_match_warnings']));
    }

    // Helper function to create a sorted key for pairs (used above)
    if (!function_exists('sorted_array_values_for_key')) {
        function sorted_array_values_for_key(array $array): array {
            sort($array, SORT_STRING);
            return $array;
        }
    }
    // --- END Fuzzy Name Matching ---

    $academicYearId = findOrCreateLookup($pdo, 'academic_years', 'year_name', $yearValue);
    $termId = findOrCreateLookup($pdo, 'terms', 'term_name', $termValue);
    $classId = findOrCreateLookup($pdo, 'classes', 'class_name', $selectedClassValue);

    // **** NEW LOGGING FOR BATCH IDENTIFICATION ****
    error_log("PROCESS_EXCEL_BATCH_CHECK: Input Values - YearValue: '$yearValue', TermValue: '$termValue', ClassSelection: '$selectedClassValue'");
    error_log("PROCESS_EXCEL_BATCH_CHECK: Resolved IDs - AcademicYearID: $academicYearId, TermID: $termId, ClassID: $classId");
    // *********************************************

    $stmtBatch = $pdo->prepare("SELECT id FROM report_batch_settings WHERE academic_year_id = :year_id AND term_id = :term_id AND class_id = :class_id");
    $stmtBatch->execute([':year_id' => $academicYearId, ':term_id' => $termId, ':class_id' => $classId]);
    $reportBatchId = $stmtBatch->fetchColumn();

    // **** NEW LOGGING FOR BATCH RESULT ****
    error_log("PROCESS_EXCEL_BATCH_RESULT: Found reportBatchId: " . ($reportBatchId ?: 'NONE_FOUND'));
    // **************************************

    if ($reportBatchId) {
        error_log("PROCESS_EXCEL_BATCH_ACTION: Existing batch found (ID: $reportBatchId). Deleting old scores/summaries."); // Log action for existing batch
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

    // Iterate through each sheet in the loaded workbook
    foreach ($spreadsheet->getSheetNames() as $sheetName) {
        $normalizedSheetName = strtolower(trim($sheetName));

        // Skip the "Instructions" sheet or any other non-subject sheets
        if ($normalizedSheetName === 'instructions' || !isset($sheetNameToInternalCodeMap[$normalizedSheetName])) {
            continue;
        }

        $subjectInternalKey = $sheetNameToInternalCodeMap[$normalizedSheetName];
        $currentSheetObject = $spreadsheet->getSheetByName($sheetName); // Get sheet by its original name

        // Check if this subject is expected for the selected class
        if (!in_array($subjectInternalKey, $expectedSubjectInternalKeys)) {
            // This sheet might be for a different class level or an extra sheet.
            // If it's not Kiswahili for P4-P7 (which is optional), we can log a warning or ignore.
            // If it's Kiswahili and class is P1-P3, it's unexpected.
            if ($subjectInternalKey === 'kiswahili' && $isP1_P3) {
                 error_log("Warning: Kiswahili sheet found for class $selectedClassValue, but it's not expected. Skipping.");
                 continue;
            }
            // For other unexpected sheets, just skip.
            if ($subjectInternalKey !== 'kiswahili' || !$isP4_P7) { // if not (kiswahili AND p4-p7)
                 error_log("Warning: Sheet '$sheetName' (maps to '$subjectInternalKey') is not expected for class $selectedClassValue. Skipping.");
                 continue;
            }
        }


        // Fetch or create subject ID
        // Use the original sheet name for user-facing messages if needed, but internal key for DB.
        $subjectDisplayNameForError = $sheetName; // Or derive from internal key if preferred for consistency

        // Get the full subject name from DB or construct it
        $stmtSubjectName = $pdo->prepare("SELECT subject_name_full FROM subjects WHERE subject_code = :code");
        $stmtSubjectName->execute([':code' => $subjectInternalKey]);
        $dbSubjectNameFull = $stmtSubjectName->fetchColumn();

        $subjectNameForLookup = $dbSubjectNameFull ?: ucfirst(str_replace('_', ' ', $subjectInternalKey));


        $subjectId = findOrCreateLookup($pdo, 'subjects', 'subject_code', $subjectInternalKey, ['subject_name_full' => $subjectNameForLookup]);

        // **** NEW: Log Subject ID Lookup ****
        error_log("PROCESS_EXCEL_SUBJECT_LOOKUP: Sheet: '$sheetName', SubjectInternalKey: '$subjectInternalKey', SubjectNameForLookup: '$subjectNameForLookup', Found/Created SubjectID: " . ($subjectId ?: 'INVALID_OR_FALSE'));
        if (!$subjectId) {
            // Log a more specific error if subjectId is not valid, as this will cause issues.
            error_log("PROCESS_EXCEL_SUBJECT_ERROR: CRITICAL - Failed to obtain a valid subject ID for sheet: '$sheetName' (Internal Key: '$subjectInternalKey'). Score insertions for this sheet will likely fail or use an invalid subject_id. Please check subjects table and subject name mapping.");
            // Original exception:
            throw new Exception("Failed to find or create subject ID for: " . htmlspecialchars($subjectDisplayNameForError) . " (using internal code: " . htmlspecialchars($subjectInternalKey) . ")");
        }
        // ***********************************

        // Headers are LIN, Names/Name, BOT, MOT, EOT in row 1
        $headerLIN = trim(strtoupper(strval($currentSheetObject->getCell('A1')->getValue())));
        $headerName = trim(strtoupper(strval($currentSheetObject->getCell('B1')->getValue())));
        $headerBOT = trim(strtoupper(strval($currentSheetObject->getCell('C1')->getValue())));
        $headerMOT = trim(strtoupper(strval($currentSheetObject->getCell('D1')->getValue())));
        $headerEOT = trim(strtoupper(strval($currentSheetObject->getCell('E1')->getValue())));

        // Allow 'NAMES/NAME' (from user's error) in addition to 'NAMES' or 'NAME' for the second column header.
        // $headerName is already uppercased at this point.
        if ($headerLIN !== 'LIN' || !in_array($headerName, ['NAMES/NAME', 'NAMES', 'NAME']) || $headerBOT !== 'BOT' || $headerMOT !== 'MOT' || $headerEOT !== 'EOT') {
            throw new Exception("Invalid headers in sheet '" . htmlspecialchars($sheetName) . "'. Expected A1='LIN', B1='Names/Name' (or 'Names' or 'Name'), C1='BOT', D1='MOT', E1='EOT'. Found: $headerLIN, $headerName, $headerBOT, $headerMOT, $headerEOT");
        }

        $highestRow = $currentSheetObject->getHighestDataRow();
        $startRow = 2; // Data starts from row 2

        if ($highestRow < $startRow) {
            error_log("Warning: No student data rows found in sheet '" . htmlspecialchars($sheetName) . "'. Sheet might be empty after row 1.");
            // If this subject is required, this might be an issue.
            // For now, just continue. The earlier check for required sheets handles missing sheets.
            // This handles empty-but-present sheets.
            if (in_array($subjectInternalKey, $requiredSubjectInternalKeys) && ($subjectInternalKey !== 'kiswahili' || !$isP4_P7) ) {
                 // Kiswahili is optional for P4-P7, so an empty sheet is fine.
                 // For other required subjects, an empty sheet could be an error or warning.
                 // Let's throw an exception if a *required* sheet is empty to alert the user.
                 // Allow Kiswahili for P4-P7 to be empty.
                 if (!($subjectInternalKey === 'kiswahili' && $isP4_P7)) {
                    throw new Exception("Required subject sheet '" . htmlspecialchars($sheetName) . "' is present but contains no student data.");
                 }
            }
            continue;
        }

        for ($row = $startRow; $row <= $highestRow; $row++) {
            $linValue = trim(strval($currentSheetObject->getCell('A' . $row)->getValue()));
            $studentNameRaw = trim(strval($currentSheetObject->getCell('B' . $row)->getValue()));

            if (empty($studentNameRaw)) continue; // Skip if no student name
            $studentNameAllCaps = strtoupper($studentNameRaw);
            $linToStore = !empty($linValue) ? $linValue : null;

            $studentId = null;
            $studentInfo = null;

            // Try to find student by LIN first, as it should be unique
            if ($linToStore) {
                $stmtStudentByLin = $pdo->prepare("SELECT id, student_name, lin_no FROM students WHERE lin_no = :lin_no");
                $stmtStudentByLin->execute([':lin_no' => $linToStore]);
                $studentInfo = $stmtStudentByLin->fetch(PDO::FETCH_ASSOC);
                if ($studentInfo) {
                    $studentId = $studentInfo['id'];
                    // LIN matches. Check if name also matches or if student name needs update.
                    if ($studentInfo['student_name'] !== $studentNameAllCaps) {
                        // Update student name if it's different for the same LIN
                        $stmtUpdateName = $pdo->prepare("UPDATE students SET student_name = :name, current_class_id = :class_id WHERE id = :id");
                        $stmtUpdateName->execute([':name' => $studentNameAllCaps, ':class_id' => $classId, ':id' => $studentId]);
                    } else {
                        // LIN and Name match, just update class if needed
                        $stmtUpdateClass = $pdo->prepare("UPDATE students SET current_class_id = :class_id WHERE id = :id");
                        $stmtUpdateClass->execute([':class_id' => $classId, ':id' => $studentId]);
                    }
                }
            }

            // If not found by LIN, try by name
            if (!$studentId) {
                $stmtStudentByName = $pdo->prepare("SELECT id, student_name, lin_no FROM students WHERE student_name = :name");
                $stmtStudentByName->execute([':name' => $studentNameAllCaps]);
                $studentInfo = $stmtStudentByName->fetch(PDO::FETCH_ASSOC);
                if ($studentInfo) {
                    $studentId = $studentInfo['id'];
                    // Student found by name. Now, handle LIN.
                    if ($linToStore) {
                        if ($studentInfo['lin_no'] !== $linToStore) {
                            // LIN in sheet is different from DB LIN for this student.
                            // Check if the new LIN from sheet is already used by *another* student.
                            $stmtCheckLinConflict = $pdo->prepare("SELECT id FROM students WHERE lin_no = :lin_no AND id != :current_student_id");
                            $stmtCheckLinConflict->execute([':lin_no' => $linToStore, ':current_student_id' => $studentId]);
                            if ($stmtCheckLinConflict->fetchColumn()) {
                                // LIN conflict! The LIN from the sheet is already assigned to a different student.
                                throw new Exception("Data conflict in sheet '" . htmlspecialchars($sheetName) . "', row " . $row . " for student '" . htmlspecialchars($studentNameRaw) . "': The LIN '" . htmlspecialchars($linToStore) . "' is already assigned to another student in the database. Please correct the Excel file.");
                            }
                            // No conflict, safe to update LIN for this student.
                            $stmtUpdateLinAndClass = $pdo->prepare("UPDATE students SET lin_no = :lin_no, current_class_id = :class_id WHERE id = :id");
                            $stmtUpdateLinAndClass->execute([':lin_no' => $linToStore, ':class_id' => $classId, ':id' => $studentId]);
                        } else {
                            // LIN in sheet is same as DB LIN, just update class
                            $stmtUpdateClassOnly = $pdo->prepare("UPDATE students SET current_class_id = :class_id WHERE id = :id");
                            $stmtUpdateClassOnly->execute([':class_id' => $classId, ':id' => $studentId]);
                        }
                    } else { // No LIN in sheet, but student found by name. Just update class.
                        $stmtUpdateClassOnly = $pdo->prepare("UPDATE students SET current_class_id = :class_id WHERE id = :id");
                        $stmtUpdateClassOnly->execute([':class_id' => $classId, ':id' => $studentId]);
                    }
                }
            }

            // If still no studentId, then insert new student
            if (!$studentId) {
                // Before inserting, if a LIN is provided, ensure it's not already in the database AT ALL
                // (This check is implicitly covered by unique constraint if insert fails, but explicit check gives better error before attempting insert)
                if ($linToStore) {
                    $stmtCheckLinExists = $pdo->prepare("SELECT id FROM students WHERE lin_no = :lin_no");
                    $stmtCheckLinExists->execute([':lin_no' => $linToStore]);
                    if ($stmtCheckLinExists->fetchColumn()) {
                         throw new Exception("Data conflict in sheet '" . htmlspecialchars($sheetName) . "', row " . $row . " for new student '" . htmlspecialchars($studentNameRaw) . "': The LIN '" . htmlspecialchars($linToStore) . "' is already assigned to another student in the database. Cannot create new student with this LIN.");
                    }
                }

                // **** NEW: Log before inserting new student ****
                error_log("PROCESS_EXCEL: Creating NEW student. Sheet: '" . $sheetName . "', Row: " . $row . ", LIN: '" . ($linToStore ?? 'N/A') . "', Name (Raw): '" . $studentNameRaw . "', Name (Processed): '" . $studentNameAllCaps . "'");
                // **********************************************

                $stmtInsertStudent = $pdo->prepare("INSERT INTO students (student_name, current_class_id, lin_no) VALUES (:name, :class_id, :lin_no)");
                $stmtInsertStudent->execute([':name' => $studentNameAllCaps, ':class_id' => $classId, ':lin_no' => $linToStore]);
                $studentId = $pdo->lastInsertId();
            }

            // ---- START DUPLICATE DETECTION LOGIC ----
            if ($studentId && !empty($studentNameAllCaps)) {
                $stmtCheckDuplicates = $pdo->prepare(
                    "SELECT id, student_name, lin_no FROM students
                     WHERE student_name = :name_to_check AND id != :current_student_id"
                );
                $stmtCheckDuplicates->execute([
                    ':name_to_check' => $studentNameAllCaps,
                    ':current_student_id' => $studentId
                ]);
                $existingSameNameStudents = $stmtCheckDuplicates->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($existingSameNameStudents)) {
                    // Check if this specific student (by ID and current LIN) has already been flagged in this session for THIS BATCH processing
                    // This helps avoid multiple notifications for the same student if they appear in multiple subject sheets
                    // We use a composite key for uniqueness within the session's flagged duplicates list
                    $flagKey = $studentId . "_" . (is_null($linToStore) ? 'NULL_LIN' : $linToStore);

                    $alreadyFlaggedThisSession = false;
                    if(isset($_SESSION['flagged_duplicates_this_run']) && isset($_SESSION['flagged_duplicates_this_run'][$flagKey])) {
                        $alreadyFlaggedThisSession = true;
                    }

                    if (!$alreadyFlaggedThisSession) {
                        $duplicateDetails = [
                            'processed_student_id' => $studentId, // ID of the student just processed/created
                            'processed_student_name' => $studentNameAllCaps,
                            'processed_student_lin' => $linToStore, // LIN from the sheet for this student
                            'sheet_row' => $row, // We can record the first occurrence
                            'sheet_name' => $sheetName, // We can record the first occurrence
                            'matches' => []
                        ];
                        foreach ($existingSameNameStudents as $existingStudent) {
                            $duplicateDetails['matches'][] = [
                                'db_student_id' => $existingStudent['id'],
                                'db_student_name' => $existingStudent['student_name'], // Should be same as $studentNameAllCaps
                                'db_lin_no' => $existingStudent['lin_no']
                            ];
                        }
                        $_SESSION['potential_duplicates_found'][] = $duplicateDetails;
                        // Mark as flagged for this specific studentId-LIN combination for this run
                        $_SESSION['flagged_duplicates_this_run'][$flagKey] = true;
                        error_log("PROCESS_EXCEL: Potential duplicate flagged for student ID $studentId ($studentNameAllCaps), Row $row, Sheet '$sheetName'. Matches found: " . count($existingSameNameStudents));
                    }
                }
            }
            // ---- END DUPLICATE DETECTION LOGIC ----

            // Get scores
            $botScore = $currentSheetObject->getCell('C' . $row)->getValue();
            $motScore = $currentSheetObject->getCell('D' . $row)->getValue();
            $eotScore = $currentSheetObject->getCell('E' . $row)->getValue();

            // Insert/Update scores
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
    } // End of loop through sheets

    $pdo->commit();
    $_SESSION['success_message'] = 'Data imported successfully from the Excel file for class ' . htmlspecialchars($selectedClassValue) . ' (Batch ID: ' . htmlspecialchars($reportBatchId) . '). Ready for next steps (calculations).';
    $_SESSION['current_teacher_initials'] = $teacherInitialsFromForm;
    $_SESSION['last_processed_batch_id'] = $reportBatchId;
    $_SESSION['batch_data_changed_for_calc'][$reportBatchId] = true; // Set flag for this batch

    // Log successful import
    if (!function_exists('logActivity')) { // Ensure dal.php was required if not already
        require_once 'dal.php';
    }
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
