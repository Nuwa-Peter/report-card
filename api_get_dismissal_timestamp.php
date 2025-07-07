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

require_once 'db_connection.php'; // Provides $pdo
require_once 'dal.php';         // Provides DAL functions

$userId = $_SESSION['user_id'];

try {
    $timestamp = getUserLastDismissedAdminActivityTimestamp($pdo, $userId);
    echo json_encode(['success' => true, 'timestamp' => $timestamp]);
} catch (Exception $e) {
    error_log("API Error in api_get_dismissal_timestamp.php: " . $e->getMessage());
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'error' => 'An internal error occurred while fetching dismissal timestamp.']);
}
exit;
?>
