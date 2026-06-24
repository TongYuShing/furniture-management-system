<?php
session_start();
session_destroy();
echo "<h1>Session Destroyed!</h1>";
echo "<p>Session ID before destroy: " . session_id() . "</p>";
session_start();
echo "<p>New Session ID: " . session_id() . "</p>";
echo '<a href="test_logout.php">Go back to test</a>';
?>