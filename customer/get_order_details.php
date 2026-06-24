<?php
/**
 * get_order_details.php - AJAX endpoint to get detailed order information
 * Includes customer info and material usage
 */

require_once '../inc/config.php';
require_once '../inc/session.php';
require_once '../inc/functions.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn() || !hasRole('customer')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit();
}

$orderId = (int)$_GET['id'];
$customerId = $_SESSION['user_id'];

// Verify order belongs to this customer
$sql = "SELECT o.*, f.FurnitureName, f.FurnitureImage, f.Price 
        FROM Orders o
        INNER JOIN Furniture f ON o.FurnitureID = f.FurnitureID
        WHERE o.OrderID = ? AND o.CustomerID = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $orderId, $customerId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$order = mysqli_fetch_assoc($result);

if (!$order) {
    echo json_encode(['success' => false, 'message' => 'Order not found']);
    exit();
}

// Get customer details
$sql = "SELECT CustomerName, ContactNumber, Email, Address FROM Customer WHERE CustomerID = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $customerId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$customer = mysqli_fetch_assoc($result);

// Get product view image
$imgSql = "SELECT ImagePath FROM FurnitureImage WHERE FurnitureID = ? AND IsPrimary = 1 ORDER BY SortOrder ASC LIMIT 1";
$imgStmt = mysqli_prepare($conn, $imgSql);
mysqli_stmt_bind_param($imgStmt, "i", $order['FurnitureID']);
mysqli_stmt_execute($imgStmt);
$imgResult = mysqli_stmt_get_result($imgStmt);
$primaryImg = mysqli_fetch_assoc($imgResult);
if (!$primaryImg) {
    // Fallback to first available image
    $imgSql2 = "SELECT ImagePath FROM FurnitureImage WHERE FurnitureID = ? ORDER BY SortOrder ASC LIMIT 1";
    $imgStmt2 = mysqli_prepare($conn, $imgSql2);
    mysqli_stmt_bind_param($imgStmt2, "i", $order['FurnitureID']);
    mysqli_stmt_execute($imgStmt2);
    $imgResult2 = mysqli_stmt_get_result($imgStmt2);
    $primaryImg = mysqli_fetch_assoc($imgResult2);
}

// Get materials used in this order
$sql = "SELECT m.MaterialID, m.MaterialName, m.Unit, fm.MaterialQuantity, m.PhysicalQuantity
        FROM Furniture_Material fm
        INNER JOIN Material m ON fm.MaterialID = m.MaterialID
        WHERE fm.FurnitureID = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $order['FurnitureID']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$materials = [];
while ($row = mysqli_fetch_assoc($result)) {
    $materials[] = $row;
}

echo json_encode([
    'success' => true,
    'order' => $order,
    'customer' => $customer,
    'materials' => $materials,
    'image_path' => $primaryImg['ImagePath'] ?? null
]);
?>