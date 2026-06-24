<?php
/**
 * get_order_details_staff.php - AJAX endpoint for staff order details
 */

require_once '../inc/config.php';
require_once '../inc/session.php';
require_once '../inc/functions.php';

header('Content-Type: application/json');

// Check if user is logged in as staff
if (!isLoggedIn() || !hasRole('staff')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit();
}

$orderId = (int)$_GET['id'];

// Get order details
$sql = "SELECT o.*, f.FurnitureName, f.FurnitureImage, f.Price 
        FROM Orders o
        INNER JOIN Furniture f ON o.FurnitureID = f.FurnitureID
        WHERE o.OrderID = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $orderId);
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
mysqli_stmt_bind_param($stmt, "i", $order['CustomerID']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$customer = mysqli_fetch_assoc($result);

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
    'materials' => $materials
]);
?>