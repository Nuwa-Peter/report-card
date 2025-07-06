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

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    $_SESSION['login_error_message'] = 'Username and password are required.';
    header('Location: login.php');
    exit;
}

try {
    // Attempt to fetch user by username or email first
    $stmt = $pdo->prepare("SELECT id, username, password_hash, role, email, phone_number FROM users WHERE username = :login_identifier OR email = :login_identifier LIMIT 1");
    $stmt->execute([':login_identifier' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // If not found by username/email, and if the input might be a phone number, try by phone number
    // Basic check: is it numeric and within a reasonable length for a phone number?
    // This is a loose check; more sophisticated phone number validation could be added.
    if (!$user && is_numeric($username) && strlen($username) >= 7 && strlen($username) <= 15) {
        $stmt = $pdo->prepare("SELECT id, username, password_hash, role, email, phone_number FROM users WHERE phone_number = :phone LIMIT 1");
        $stmt->execute([':phone' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if ($user && password_verify($password, $user['password_hash'])) {
        // Password is correct, start session
        session_regenerate_id(true); // Regenerate session ID for security

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role']; // Store role if you have role-based access control

        // Log activity
        require_once 'dal.php'; // Ensure DAL is available
        logActivity(
            $pdo,
            $user['id'],
            $user['username'],
            'USER_LOGIN',
            "User '" . $user['username'] . "' logged in successfully."
        );

        // Redirect to dashboard or intended page
        header('Location: index.php');
        exit;
    } else {
        // Invalid credentials
        $_SESSION['login_error_message'] = 'Invalid username or password.';
        header('Location: login.php');
        exit;
    }

} catch (PDOException $e) {
    // Log error and set a generic error message for the user
    error_log("Login Error: " . $e->getMessage());
    $_SESSION['login_error_message'] = 'An error occurred during login. Please try again later.';
    header('Location: login.php');
    exit;
}
?>
