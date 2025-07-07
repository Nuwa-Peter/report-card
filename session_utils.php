<?php
// session_utils.php

function handle_session_activity_and_timeout(int $timeout_duration = 1800): string {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['user_id'])) {
        return 'no_user_id'; // Not logged in at all
    }

    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
        // Session has expired.
        $username_for_log = $_SESSION['username'] ?? 'UnknownUser'; // Get username for logging

        // Unset all session variables
        $_SESSION = array();

        // Destroy the session cookie and session data
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();

        // Start a new session briefly to pass the error message for web pages
        session_start();
        $_SESSION['login_error_message'] = "Your session has expired due to inactivity. Please log in again.";

        // Logging for timeout can be added here if a global $pdo is available or passed
        // logActivity($pdo, null, $username_for_log, 'SESSION_TIMEOUT', "User session for '$username_for_log' timed out.");

        return 'timed_out'; // Specific status for timeout
    }

    // If session is valid and not timed out
    $_SESSION['last_activity'] = time();
    return 'active'; // Session is active
}

?>
