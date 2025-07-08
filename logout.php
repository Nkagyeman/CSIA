<?php
session_start();

// Check for unsaved changes (server-side)
$has_unsaved_changes = false;

// Example: Check if any forms were being edited
if (isset($_SESSION['form_data'])) {
    $has_unsaved_changes = true;
}

// If unsaved changes exist and this isn't a confirmed logout
if ($has_unsaved_changes && !isset($_POST['confirm_logout'])) {
    // Store in session that we're attempting logout
    $_SESSION['pending_logout'] = true;
    header("Location: confirm_logout.php");
    exit();
}

// Proceed with logout if no unsaved changes or confirmed
logoutUser();

function logoutUser() {
    // Unset all session variables
    $_SESSION = array();

    // Delete session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    // Destroy the session
    session_destroy();

    // Redirect to login page
    header("Location: login.php?logout=1");
    exit();
}
?>