<?php
session_start();
require_once 'db_connection.php'; // Provides $pdo

// Redirect to login if accessed directly without POST or if already logged in
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}
if (isset($_SESSION['user_id'])) {
    header('Location: index.php'); // Already logged in
    exit;
}

$username = trim($_POST['username'] ?? ''); // Trimmed
$password = trim($_POST['password'] ?? ''); // Trimmed

if (empty($username) || empty($password)) {
    $_SESSION['login_error_message'] = 'Username and password are required.';
    header('Location: login.php');
    exit;
}

try {
    error_log("DEBUG: Attempting user lookup for username/email: " . $username);
    // Changed to positional placeholders
    $sql = "SELECT id, username, password_hash, role, email, phone_number FROM users WHERE username = ? OR email = ? LIMIT 1";
    $stmt = $pdo->prepare($sql);
    // Execute with an array of values for positional placeholders
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    error_log("DEBUG: User lookup completed. User found: " . ($user ? "Yes (" . $user['username'] . ")" : "No"));

    // Phone number lookup has been removed.

    if ($user && password_verify($password, $user['password_hash'])) {
        error_log("DEBUG: Password verified for user: " . $user['username'] . ". Attempting to regenerate session ID.");
        session_regenerate_id(true); // Regenerate session ID for security
        error_log("DEBUG: Session ID regenerated.");

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role']; // Store role if you have role-based access control
        $_SESSION['last_activity'] = time(); // Set last activity time
        error_log("DEBUG: Session variables set for user: " . $user['username']);

        error_log("DEBUG: Attempting to log activity for user: " . $user['username']);
        require_once 'dal.php'; // Ensure DAL is available
        logActivity(
            $pdo,
            $user['id'],
            $user['username'],
            'USER_LOGIN',
            "User '" . $user['username'] . "' logged in successfully."
        );
        error_log("DEBUG: Activity logged for user: " . $user['username']);

        error_log("DEBUG: Redirecting to index.php for user: " . $user['username']);
        header('Location: index.php');
        exit;
    } else {
        error_log("DEBUG: Invalid credentials for username: " . $username . ". User found in DB: " . ($user ? "Yes" : "No") . ", Password verify result: " . ($user ? (password_verify($password, $user['password_hash']) ? 'Pass' : 'Fail') : "N/A"));
        $_SESSION['login_error_message'] = 'Invalid username or password.';
        header('Location: login.php');
        exit;
    }

} catch (PDOException $e) {
    error_log("Login Error: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
    $_SESSION['login_error_message'] = 'An error occurred during login. Please try again later.';
    // Ensure no further output before header redirect
    if (!headers_sent()) {
        header('Location: login.php');
    } else {
        // Fallback if headers already sent, though error_log is the primary debug path
        echo "A critical database error occurred. Please check server logs. Redirecting...";
        echo "<script>window.location.href='login.php';</script>"; // JS redirect as a last resort
    }
    exit;
}
?>
