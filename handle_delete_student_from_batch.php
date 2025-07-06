<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_message'] = "You must be logged in to perform this action.";
    header('Location: login.php');
    exit;
}

require_once 'db_connection.php'; // Provides $pdo
require_once 'dal.php';         // Provides DAL functions

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    $_SESSION['error_message'] = "Invalid request method for this action.";
    header('Location: index.php');
    exit;
}

$student_id = filter_input(INPUT_GET, 'student_id', FILTER_VALIDATE_INT);
$batch_id = filter_input(INPUT_GET, 'batch_id', FILTER_VALIDATE_INT);

if (!$student_id || !$batch_id) {
    $_SESSION['error_message'] = "Invalid Student ID or Batch ID provided for deletion.";
    // Try to redirect back to a specific batch if batch_id is somewhat valid, else to index
    $redirect_url = $batch_id ? 'view_processed_data.php?batch_id=' . $batch_id : 'index.php';
    header('Location: ' . $redirect_url);
    exit;
}

// Fetch student name for logging before deletion
$stmtStudentName = $pdo->prepare("SELECT student_name FROM students WHERE id = :student_id");
$stmtStudentName->execute([':student_id' => $student_id]);
$studentName = $stmtStudentName->fetchColumn();
if (!$studentName) {
    // Student record doesn't exist, this is unusual if we're trying to delete them from a batch.
    // Scores might exist if student was deleted from students table but not scores (bad state).
    // For safety, we can still proceed to delete scores/summary if they exist for this student_id.
    $studentName = "ID " . $student_id; // Fallback name for log
    error_log("Attempting to delete student (ID: $student_id) from batch $batch_id, but student not found in students table.");
}


try {
    $pdo->beginTransaction();

    // 1. Delete scores for the student in this batch
    $stmtDeleteScores = $pdo->prepare("DELETE FROM scores WHERE student_id = :student_id AND report_batch_id = :batch_id");
    $stmtDeleteScores->execute([':student_id' => $student_id, ':batch_id' => $batch_id]);
    $scoresDeletedCount = $stmtDeleteScores->rowCount();

    // 2. Delete summary record for the student in this batch
    $stmtDeleteSummary = $pdo->prepare("DELETE FROM student_report_summary WHERE student_id = :student_id AND report_batch_id = :batch_id");
    $stmtDeleteSummary->execute([':student_id' => $student_id, ':batch_id' => $batch_id]);
    $summaryDeletedCount = $stmtDeleteSummary->rowCount();

    $pdo->commit();

    if ($scoresDeletedCount > 0 || $summaryDeletedCount > 0) {
        $_SESSION['success_message'] = "Student '" . htmlspecialchars($studentName) . "' (ID: $student_id) has been removed from batch ID $batch_id (Scores deleted: $scoresDeletedCount, Summary entries deleted: $summaryDeletedCount).";
        // Log activity
        logActivity(
            $pdo,
            $_SESSION['user_id'],
            $_SESSION['username'],
            'STUDENT_REMOVED_FROM_BATCH',
            "Removed student '" . htmlspecialchars($studentName) . "' (ID: $student_id) from batch ID $batch_id. Scores deleted: $scoresDeletedCount, Summaries deleted: $summaryDeletedCount.",
            'student',
            $student_id,
            null
        );
        $_SESSION['batch_data_changed_for_calc'][$batch_id] = true; // Data changed, flag for recalc
    } else {
        $_SESSION['info_message'] = "No scores or summary data found for student '" . htmlspecialchars($studentName) . "' (ID: $student_id) in batch ID $batch_id. No changes made.";
    }

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error deleting student from batch: " . $e->getMessage());
    $_SESSION['error_message'] = "A database error occurred while trying to remove the student from the batch: " . $e->getMessage();
} catch (Exception $e) {
     if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("General error deleting student from batch: " . $e->getMessage());
    $_SESSION['error_message'] = "An error occurred: " . $e->getMessage();
}

header('Location: view_processed_data.php?batch_id=' . $batch_id);
exit;
?>
