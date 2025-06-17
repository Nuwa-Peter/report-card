<?php
// dal.php - Data Access Layer

if (!file_exists('db_connection.php')) {
    // This is a critical internal error
    die("FATAL ERROR: db_connection.php not found in dal.php. System misconfigured.");
}
require_once 'db_connection.php'; // Ensures $pdo is available

/**
 * Fetches the report batch settings for a given batch ID.
 * @param PDO $pdo PDO database connection object.
 * @param int $reportBatchId The ID of the report batch.
 * @return array|false The batch settings as an associative array, or false if not found.
 */
function getReportBatchSettings(PDO $pdo, int $reportBatchId): array|false {
    $sql = "SELECT
                rbs.*,
                ay.year_name,
                t.term_name,
                c.class_name
            FROM report_batch_settings rbs
            JOIN academic_years ay ON rbs.academic_year_id = ay.id
            JOIN terms t ON rbs.term_id = t.id
            JOIN classes c ON rbs.class_id = c.id
            WHERE rbs.id = :batch_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':batch_id' => $reportBatchId]);
    return $stmt->fetch(); // Default fetch mode is PDO::FETCH_ASSOC from db_connection.php
}

/**
 * Fetches all students and their scores for a given report batch ID.
 * Includes subject names.
 * @param PDO $pdo
 * @param int $reportBatchId
 * @return array Associative array of students, each containing their details and an array of subjects with scores.
 */
function getStudentsWithScoresForBatch(PDO $pdo, int $reportBatchId): array {
    $studentsData = [];
    $sql = "SELECT
                s.id as student_id,
                s.student_name,
                s.lin_no,
                subj.id as subject_id,
                subj.subject_code,
                subj.subject_name_full,
                sc.bot_score,
                sc.mot_score,
                sc.eot_score
                -- We will fetch pre-calculated grades/remarks/points from student_report_summary later
                -- Or, if not storing them in scores table directly:
                -- sc.eot_grade_on_report,
                -- sc.eot_points_on_report,
                -- sc.eot_remark_on_report,
                -- sc.teacher_initials_on_report
            FROM students s
            JOIN scores sc ON s.id = sc.student_id
            JOIN subjects subj ON sc.subject_id = subj.id
            WHERE sc.report_batch_id = :batch_id
            ORDER BY s.student_name ASC, subj.subject_name_full ASC"; // Order for consistent processing

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':batch_id' => $reportBatchId]);

    $results = $stmt->fetchAll();

    foreach ($results as $row) {
        $studentId = $row['student_id'];
        if (!isset($studentsData[$studentId])) {
            $studentsData[$studentId] = [
                'id' => $studentId,
                'student_name' => $row['student_name'], // Already ALL CAPS from DB
                'lin_no' => $row['lin_no'],
                'subjects' => []
                // We will merge summary data (division, rank, etc.) later
            ];
        }
        $studentsData[$studentId]['subjects'][$row['subject_code']] = [ // Use subject_code as key
            'subject_id' => $row['subject_id'],
            'subject_name_full' => $row['subject_name_full'],
            'bot_score' => $row['bot_score'],
            'mot_score' => $row['mot_score'],
            'eot_score' => $row['eot_score']
            // 'eot_grade_on_report' => $row['eot_grade_on_report'], // If fetched from scores table
            // 'eot_points_on_report' => $row['eot_points_on_report'],
            // 'eot_remark_on_report' => $row['eot_remark_on_report'],
            // 'teacher_initials_on_report' => $row['teacher_initials_on_report']
        ];
    }
    return $studentsData; // Keyed by student_id
}


/**
 * Fetches pre-calculated student report summaries for a given batch.
 * @param PDO $pdo
 * @param int $reportBatchId
 * @return array Associative array keyed by student_id.
 */
function getStudentReportSummariesForBatch(PDO $pdo, int $reportBatchId): array {
    $summaries = [];
    $sql = "SELECT * FROM student_report_summary WHERE report_batch_id = :batch_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':batch_id' => $reportBatchId]);
    $results = $stmt->fetchAll();

    foreach($results as $row) {
        $summaries[$row['student_id']] = $row;
    }
    return $summaries;
}


/**
 * Saves or updates a student's report summary data.
 * @param PDO $pdo
 * @param array $summaryData Must include student_id and report_batch_id.
 * @return bool True on success, false on failure.
 */
function saveStudentReportSummary(PDO $pdo, array $summaryData): bool {
    // Ensure required keys are present
    if (!isset($summaryData['student_id']) || !isset($summaryData['report_batch_id'])) {
        // Log error: missing required keys for summary
        error_log("DAL: Missing student_id or report_batch_id in summaryData for saveStudentReportSummary.");
        return false;
    }

    // Check if a summary already exists
    $stmtCheck = $pdo->prepare("SELECT id FROM student_report_summary WHERE student_id = :student_id AND report_batch_id = :report_batch_id");
    $stmtCheck->execute([
        ':student_id' => $summaryData['student_id'],
        ':report_batch_id' => $summaryData['report_batch_id']
    ]);
    $existingId = $stmtCheck->fetchColumn();

    $fields = [
        'student_id', 'report_batch_id',
        'p4p7_aggregate_points', 'p4p7_division',
        'p1p3_total_eot_score', 'p1p3_average_eot_score',
        'p1p3_position_in_class', 'p1p3_total_students_in_class',
        'auto_classteachers_remark_text', 'auto_headteachers_remark_text',
        'p1p3_total_bot_score', 'p1p3_position_total_bot',
        'p1p3_total_mot_score', 'p1p3_position_total_mot',
        'p1p3_position_total_eot',
        // ADDED/VERIFY New fields for P1-P3 overall BOT/MOT averages
        'p1p3_average_bot_score',
        'p1p3_average_mot_score'
    ];

    $dataToSave = [];
    foreach($fields as $field) {
        // Use array_key_exists to allow NULL values to be explicitly set if they are in $summaryData
        if(array_key_exists($field, $summaryData)){
            $dataToSave[$field] = $summaryData[$field];
        } elseif ($field !== 'student_id' && $field !== 'report_batch_id') {
            // For optional fields not present in input, set to NULL to avoid unset errors
            // if they are not nullable in DB with no default, this might need adjustment.
            // Assuming optional fields are nullable.
            $dataToSave[$field] = null;
        }
    }
    // Ensure required keys are still there after filtering
     if (!isset($dataToSave['student_id']) || !isset($dataToSave['report_batch_id'])) {
        error_log("DAL: student_id or report_batch_id became unset after filtering fields for saveStudentReportSummary.");
        return false;
    }


    if ($existingId) { // Update
        $updateParts = [];
        foreach (array_keys($dataToSave) as $key) {
            if ($key !== 'student_id' && $key !== 'report_batch_id' && $key !== 'id') { // Don't include PKs in SET
                $updateParts[] = "`$key` = :$key";
            }
        }
        if (empty($updateParts)) return true; // Nothing to update

        $sql = "UPDATE student_report_summary SET " . implode(', ', $updateParts) .
               " WHERE id = :existing_id";
        $dataToSave['existing_id'] = $existingId; // Add existing ID for the WHERE clause
        $stmt = $pdo->prepare($sql);
    } else { // Insert
        $colsString = implode(', ', array_map(function($col) { return "`$col`"; }, array_keys($dataToSave)));
        $placeholdersString = implode(', ', array_map(function($col) { return ":$col"; }, array_keys($dataToSave)));
        $sql = "INSERT INTO student_report_summary ($colsString) VALUES ($placeholdersString)";
        $stmt = $pdo->prepare($sql);
    }

    try {
        return $stmt->execute($dataToSave);
    } catch (PDOException $e) {
        // Log error: $e->getMessage()
        error_log("DAL Error in saveStudentReportSummary: " . $e->getMessage());
        return false;
    }
}

/**
 * Fetches teacher initials for specific subjects for a given report batch.
 * This is a placeholder, as teacher initials are currently passed from the form via session.
 * If initials were stored per subject per batch in DB, this function would fetch them.
 * For now, it's not strictly needed if using session as planned.
 * @param PDO $pdo
 * @param int $reportBatchId
 * @return array
 */
// function getTeacherInitialsForBatch(PDO $pdo, int $reportBatchId): array {
//     // Example: SELECT subject_id, teacher_initials FROM batch_subject_teachers WHERE report_batch_id = ...
//     return [];
// }

// More functions can be added here as needed, e.g., for fetching data for summary sheets,
// specific student lookups, etc.

/**
 * Fetches a single student's report summary data (from student_report_summary)
 * AND their basic details (name, lin_no from students table) for a given batch.
 * @param PDO $pdo
 * @param int $studentId
 * @param int $reportBatchId
 * @return array|false Associative array of the merged summary and student details, or false if not found.
 */
function getStudentSummaryAndDetailsForReport(PDO $pdo, int $studentId, int $reportBatchId): array|false {
    $sql = "SELECT srs.*, s.student_name, s.lin_no
            FROM student_report_summary srs
            JOIN students s ON srs.student_id = s.id
            WHERE srs.student_id = :student_id AND srs.report_batch_id = :report_batch_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':student_id' => $studentId, ':report_batch_id' => $reportBatchId]);
    return $stmt->fetch(); // Default is PDO::FETCH_ASSOC from db_connection.php
}

/**
 * Fetches all student summaries for a given report batch ID.
 * Includes student name via a JOIN.
 * @param PDO $pdo
 * @param int $reportBatchId
 * @return array Array of student summary records, ordered by student name or position if available.
 */
function getAllStudentSummariesForBatchWithName(PDO $pdo, int $reportBatchId): array {
    $sql = "SELECT srs.*, s.student_name
            FROM student_report_summary srs
            JOIN students s ON srs.student_id = s.id
            WHERE srs.report_batch_id = :report_batch_id
            ORDER BY s.student_name ASC"; // Default order, can be adjusted
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':report_batch_id' => $reportBatchId]);
    return $stmt->fetchAll();
}

/**
 * Fetches all scores (including calculated grades per subject) for all students in a batch.
 * This is more detailed than just the summary and might be needed for grade distribution counts.
 * @param PDO $pdo
 * @param int $reportBatchId
 * @return array Array of score records.
 */
function getAllScoresWithGradesForBatch(PDO $pdo, int $reportBatchId): array {
    $sql = "SELECT sc.*, s.subject_code, s.subject_name_full
            FROM scores sc
            JOIN subjects s ON sc.subject_id = s.id
            WHERE sc.report_batch_id = :report_batch_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':report_batch_id' => $reportBatchId]);
    return $stmt->fetchAll();
}

/**
 * Fetches a list of all processed report batches (class, year, term).
 * @param PDO $pdo
 * @return array List of distinct batches.
 */
function getAllProcessedBatches(PDO $pdo): array {
    $sql = "SELECT DISTINCT rbs.id as batch_id, c.class_name, ay.year_name, t.term_name
            FROM report_batch_settings rbs
            JOIN classes c ON rbs.class_id = c.id
            JOIN academic_years ay ON rbs.academic_year_id = ay.id
            JOIN terms t ON rbs.term_id = t.id
            ORDER BY ay.year_name DESC, t.term_name ASC, c.class_name ASC";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll();
}
?>
