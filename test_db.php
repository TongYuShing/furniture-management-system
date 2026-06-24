<?php
echo "<h1>Database Connection Test</h1>";

// Test 1: Check if MySQL is reachable
echo "<h3>Test 1: MySQL Connection</h3>";
$hostname = "127.0.0.1";
$port = 3306;

$connection = @fsockopen($hostname, $port, $errno, $errstr, 5);
if ($connection) {
    echo "✅ MySQL is running on port 3306<br>";
    fclose($connection);
} else {
    echo "❌ MySQL is NOT running on port 3306: $errstr<br>";
    echo "💡 Please start MySQL in XAMPP Control Panel<br>";
}

// Test 2: Try database connection
echo "<h3>Test 2: Database Connection</h3>";
$conn = @mysqli_connect("127.0.0.1", "root", "", "projectDB");

if ($conn) {
    echo "✅ Successfully connected to database 'projectDB'<br>";
    mysqli_close($conn);
} else {
    echo "❌ Failed to connect: " . mysqli_connect_error() . "<br>";
    
    // Try to connect without database
    $conn2 = @mysqli_connect("127.0.0.1", "root", "");
    if ($conn2) {
        echo "✅ Connected to MySQL server but database 'projectDB' may not exist<br>";
        echo "💡 Please import CreateProjectDB.sql into phpMyAdmin<br>";
        mysqli_close($conn2);
    } else {
        echo "❌ Cannot connect to MySQL server at all<br>";
        echo "💡 Please ensure MySQL is started in XAMPP Control Panel<br>";
    }
}

// Test 3: PHP MySQL extensions
echo "<h3>Test 3: PHP Extensions</h3>";
if (extension_loaded('mysqli')) {
    echo "✅ MySQLi extension is loaded<br>";
} else {
    echo "❌ MySQLi extension is NOT loaded<br>";
}

if (extension_loaded('pdo_mysql')) {
    echo "✅ PDO MySQL extension is loaded<br>";
} else {
    echo "⚠️ PDO MySQL extension is NOT loaded (optional)<br>";
}
?>