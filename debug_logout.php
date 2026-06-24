<?php
// Debug logout
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Step 1: Starting logout process...<br>";

session_start();
echo "Step 2: Session started. Session ID: " . session_id() . "<br>";

echo "Step 3: Session data before clearing:<br>";
print_r($_SESSION);

$_SESSION = array();
echo "<br>Step 4: Session array cleared<br>";

if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
    echo "Step 5: Session cookie deleted<br>";
}

session_destroy();
echo "Step 6: Session destroyed<br>";

echo "Step 7: Redirecting to login...<br>";
header("Refresh: 2; URL=../index.php");
echo "You will be redirected in 2 seconds...";
exit();
?>