<?php
// session_check.php for web pages

require_once 'session_utils.php';

// Ensure session is started (handle_session_activity_and_timeout will also do this, but good practice)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$timeout_duration = 1800; // 30 minutes in seconds
$session_status = handle_session_activity_and_timeout($timeout_duration);

if ($session_status === 'no_user_id') {
    // Not logged in. Set error message and redirect.
    // The login_error_message might already be set by handle_session_activity_and_timeout if it had to start a new session for it.
    // Ensure it's set if not.
    if (!isset($_SESSION['login_error_message'])) {
         $_SESSION['login_error_message'] = "You must be logged in to access this page.";
    }
    // Prevent redirection loops if somehow login.php includes this.
    if (basename($_SERVER['PHP_SELF']) !== 'login.php') {
        header('Location: login.php');
        exit;
    }
} elseif ($session_status === 'timed_out') {
    // Session timed out. login_error_message is already set by handle_session_activity_and_timeout.
    // Redirect to login page.
    if (basename($_SERVER['PHP_SELF']) !== 'login.php') {
        header('Location: login.php');
        exit;
    }
}
// If $session_status is 'active', the session is fine, last_activity is updated, and script continues.

?>
