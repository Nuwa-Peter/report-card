<?php
session_start();
error_log("DELETE_BATCH_SCRIPT_STARTED: Execution begun at " . date('Y-m-d H:i:s'));
require_once 'db_connection.php'; // Provides $pdo
// dal.php is not strictly needed for simple deletes if we write SQL directly,
// but good practice if we had delete functions there. For now, direct SQL.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("DELETE_BATCH_ERROR: Invalid request method. Method: " . $_SERVER['REQUEST_METHOD']);
    $_SESSION['error_message'] = 'Invalid request method for deleting batch.';
    header('Location: view_report_archives.php');
    exit;
}

if (!isset($_POST['batch_id']) || !filter_var($_POST['batch_id'], FILTER_VALIDATE_INT) || $_POST['batch_id'] <= 0) {
    error_log("DELETE_BATCH_ERROR: Invalid or missing Batch ID. POST data: " . print_r($_POST, true));
    $_SESSION['error_message'] = 'Invalid or missing Batch ID for deletion.';
    header('Location: view_report_archives.php');
    exit;
}

$batch_id_to_delete = (int)$_POST['batch_id'];
error_log("DELETE_BATCH_INFO: Attempting to delete batch_id: " . $batch_id_to_delete);

// Optional: Add a check here to see if the batch_id actually exists
// in report_batch_settings before attempting deletions, to provide a more specific error.
// For now, we assume if it's passed, an attempt should be made.

try {
    error_log("DELETE_BATCH_INFO: Beginning transaction for batch_id: " . $batch_id_to_delete);
    $pdo->beginTransaction();

    // 1. Delete from scores table
    $stmt_scores = $pdo->prepare("DELETE FROM scores WHERE report_batch_id = :batch_id");
    $stmt_scores->execute([':batch_id' => $batch_id_to_delete]);
    $scores_deleted_count = $stmt_scores->rowCount();
    error_log("DELETE_BATCH_INFO: Scores deleted for batch_id: $batch_id_to_delete. Count: $scores_deleted_count");

    // 2. Delete from student_report_summary table
    $stmt_summary = $pdo->prepare("DELETE FROM student_report_summary WHERE report_batch_id = :batch_id");
    $stmt_summary->execute([':batch_id' => $batch_id_to_delete]);
    $summaries_deleted_count = $stmt_summary->rowCount();
    error_log("DELETE_BATCH_INFO: Summaries deleted for batch_id: $batch_id_to_delete. Count: $summaries_deleted_count");

    // 3. Delete from report_batch_settings table
    $stmt_settings = $pdo->prepare("DELETE FROM report_batch_settings WHERE id = :batch_id");
    $stmt_settings->execute([':batch_id' => $batch_id_to_delete]);
    $settings_deleted_count = $stmt_settings->rowCount();
    error_log("DELETE_BATCH_INFO: Settings deleted for batch_id: $batch_id_to_delete. Count: $settings_deleted_count");

    $pdo->commit();
    error_log("DELETE_BATCH_INFO: Transaction committed for batch_id: " . $batch_id_to_delete);

    if ($settings_deleted_count > 0) {
        $_SESSION['success_message'] = "Batch ID " . htmlspecialchars($batch_id_to_delete) . " and its associated data ("
                                     . $scores_deleted_count . " scores, "
                                     . $summaries_deleted_count . " summaries) were successfully deleted.";
    } else {
        // This case means the batch_id might not have existed in report_batch_settings,
        // though related scores/summaries might have been cleaned if they somehow existed without a parent setting.
        $_SESSION['warning_message'] = "Batch ID " . htmlspecialchars($batch_id_to_delete) . " was not found in settings, but any orphaned related data was attempted to be cleaned.";
        error_log("DELETE_BATCH_WARNING: Batch ID $batch_id_to_delete not found in report_batch_settings or already deleted. Scores deleted: $scores_deleted_count, Summaries deleted: $summaries_deleted_count");
    }

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
        error_log("DELETE_BATCH_PDO_EXCEPTION: Transaction rolled back for batch_id: $batch_id_to_delete. Error: " . $e->getMessage());
    } else {
        error_log("DELETE_BATCH_PDO_EXCEPTION: (No active transaction) For batch_id: $batch_id_to_delete. Error: " . $e->getMessage());
    }
    $_SESSION['error_message'] = "A database error occurred while trying to delete batch data. Details: " . $e->getMessage();
} catch (Exception $e) { // Catch other general exceptions
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
        error_log("DELETE_BATCH_EXCEPTION: Transaction rolled back for batch_id: $batch_id_to_delete. Error: " . $e->getMessage());
    } else {
        error_log("DELETE_BATCH_EXCEPTION: (No active transaction) For batch_id: $batch_id_to_delete. Error: " . $e->getMessage());
    }
    $_SESSION['error_message'] = "An error occurred: " . $e->getMessage();
}

error_log("DELETE_BATCH_SCRIPT_ENDED: For batch_id: " . ($batch_id_to_delete ?? 'UNKNOWN') . ". Redirecting to view_report_archives.php");
header('Location: view_report_archives.php');
exit;
?>
