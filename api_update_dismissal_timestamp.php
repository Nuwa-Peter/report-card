<?php
require_once 'session_utils.php'; // Provides session utility functions

header('Content-Type: application/json');

$session_status = handle_session_activity_and_timeout(); // Default 30 min timeout

if ($session_status === 'no_user_id') {
    http_response_code(401); // Unauthorized
    echo json_encode(['success' => false, 'error' => 'Authentication required. Please log in.']);
    exit;
} elseif ($session_status === 'timed_out') {
    http_response_code(401); // Unauthorized - session timed out
    echo json_encode(['success' => false, 'error' => 'Session timed out. Please log in again.']);
    exit;
}

// Session is active, now check role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    http_response_code(403); // Forbidden
    echo json_encode(['success' => false, 'error' => 'Access denied. Superadmin privileges required.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit;
}

require_once 'db_connection.php'; // Provides $pdo
require_once 'dal.php';         // Provides DAL functions

$userId = $_SESSION['user_id'];
$timestamp = $_POST['timestamp'] ?? null;

if (empty($timestamp)) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'error' => 'Timestamp not provided.']);
    exit;
}

// Validate timestamp format (basic validation, can be more robust)
// Expected format from JS: YYYY-MM-DD HH:MM:SS
if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $timestamp)) {
    // Attempt to reformat if it's ISO with T and Z/ms
    try {
        $date = new DateTime($timestamp);
        $timestamp = $date->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'error' => 'Invalid timestamp format. Expected YYYY-MM-DD HH:MM:SS or valid ISO.']);
        exit;
    }
}


try {
    if (updateUserLastDismissedAdminActivityTimestamp($pdo, $userId, $timestamp)) {
        echo json_encode(['success' => true, 'message' => 'Dismissal timestamp updated.']);
    } else {
        http_response_code(500); // Internal Server Error
        echo json_encode(['success' => false, 'error' => 'Failed to update dismissal timestamp in database.']);
    }
} catch (Exception $e) {
    error_log("API Error in api_update_dismissal_timestamp.php: " . $e->getMessage());
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'error' => 'An internal error occurred.']);
}
exit;
?>
