<?php
/**
 * logout.php - Staff Logout Handler
 */

require_once '../inc/config.php';
require_once '../inc/usability.php';

// Clear Remember Me cookie
if (isset($_SESSION['user_id'])) {
    removeRememberToken($_SESSION['user_id']);
}
setcookie('remember_token', '', time() - 3600, '/');

// Use the proper logout function from config.php
logout();

// Redirect to login page using absolute URL from config
header("Location: " . BASE_URL . "index.php");
exit();
?>