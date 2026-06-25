<?php
/**
 * config.php - Database Configuration File
 */

// Database connection parameters
$hostname = "127.0.0.1";
$database = "projectDB";
$username = "root";
$password = "";

// Create database connection using mysqli
$conn = mysqli_connect($hostname, $username, $password, $database);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set charset to UTF-8
mysqli_set_charset($conn, "utf8");

// Set timezone
date_default_timezone_set('Asia/Hong_Kong');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// IMPORTANT: Update this to match your folder structure
define('BASE_URL', 'http://localhost/ITP4523M/furniture-management-system/');
define('BASE_PATH', dirname(__DIR__) . '/');

// Define upload directories
define('FURNITURE_IMAGE_UPLOAD_PATH', BASE_PATH . 'assets/images/furniture/');
define('MAX_FILE_SIZE', 2 * 1024 * 1024);

// Rest of your functions...
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function redirect($url) {
    header("Location: " . $url);
    exit();
}

function displayError($message) {
    return '<div class="alert alert-danger">' . htmlspecialchars($message) . '</div>';
}

function displaySuccess($message) {
    return '<div class="alert alert-success">' . htmlspecialchars($message) . '</div>';
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

function hasRole($role) {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}

function logout() {
    $_SESSION = array();
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    session_destroy();
}

function getCustomerName($conn, $customerId) {
    $sql = "SELECT CustomerName FROM Customer WHERE CustomerID = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $customerId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $customerName);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    return $customerName ?? 'Unknown';
}

function getFurnitureName($conn, $furnitureId) {
    $sql = "SELECT FurnitureName FROM Furniture WHERE FurnitureID = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $furnitureId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $furnitureName);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    return $furnitureName ?? 'Unknown';
}

function updateStockQuantity($conn, $furnitureId, $quantityChange) {
    $sql = "UPDATE Furniture SET StockQuantity = StockQuantity + ? WHERE FurnitureID = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $quantityChange, $furnitureId);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $result;
}

function getMaterialAvailability($conn, $materialId) {
    $sql = "SELECT PhysicalQuantity FROM Material WHERE MaterialID = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $materialId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $quantity);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    return $quantity ?? 0;
}

function canDeleteOrder($deliveryDate) {
    // Customer can cancel any pending order at any time
    // This function is kept for backward compatibility
    return true;
}

function formatCurrency($amount) {
    return '$' . number_format($amount, 2);
}

function getOrderStatusBadge($status) {
    $badges = [
        'pending' => '<span class="badge badge-warning">Pending</span>',
        'accepted' => '<span class="badge badge-success">Accepted</span>',
        'rejected' => '<span class="badge badge-danger">Rejected</span>',
        'delivered' => '<span class="badge badge-info">Delivered</span>'
    ];
    return $badges[$status] ?? '<span class="badge badge-secondary">Unknown</span>';
}

function getPaymentMethodLabel($method) {
    $labels = [
        'credit_card' => '💳 Credit Card',
        'bank_transfer' => '🏦 Bank Transfer',
        'cod' => '💵 Cash on Delivery'
    ];
    return $labels[$method] ?? '❓ Unknown';
}

function getPaymentStatusBadge($status) {
    $badges = [
        'unpaid' => '<span class="badge badge-warning">⏳ Unpaid</span>',
        'paid' => '<span class="badge badge-success">✅ Paid</span>',
        'refunded' => '<span class="badge badge-info">↩️ Refunded</span>'
    ];
    return $badges[$status] ?? '<span class="badge badge-secondary">Unknown</span>';
}
?>