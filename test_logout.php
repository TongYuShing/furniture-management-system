<?php
echo "<h1>Logout Test</h1>";

// Start session
session_start();

// Set test session variable
$_SESSION['test'] = 'test value';
$_SESSION['user_id'] = 1001;
$_SESSION['user_name'] = 'Test User';
$_SESSION['user_role'] = 'customer';

echo "<p>Session created with ID: " . session_id() . "</p>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo '<a href="test_logout_destroy.php">Click here to destroy session</a>';
?>