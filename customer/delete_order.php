<?php
/**
 * delete_order.php - Cancel Order Handler
 * Cancels an order and restores stock and material quantities
 * Customer can cancel any pending order at any time
 */

require_once '../inc/config.php';
require_once '../inc/session.php';
require_once '../inc/functions.php';

// Check if user is logged in as customer
checkCustomerRole();

$customerId = $_SESSION['user_id'];

// Get order ID from URL
$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($orderId <= 0) {
    $_SESSION['error'] = 'Invalid order ID.';
    redirect('view_orders.php');
    exit();
}

// Verify order belongs to this customer and get order details
$sql = "SELECT o.*, f.StockQuantity, f.FurnitureName 
        FROM Orders o
        INNER JOIN Furniture f ON o.FurnitureID = f.FurnitureID
        WHERE o.OrderID = ? AND o.CustomerID = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $orderId, $customerId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$order = mysqli_fetch_assoc($result);

if (!$order) {
    $_SESSION['error'] = 'Order not found or does not belong to you.';
    redirect('view_orders.php');
    exit();
}

// Check if order status is pending — only pending orders can be cancelled
if ($order['OrderStatus'] !== 'pending') {
    $_SESSION['error'] = 'Only pending orders can be cancelled. This order is already ' . $order['OrderStatus'] . '.';
    redirect('view_orders.php');
    exit();
}

// Start transaction to ensure data consistency
mysqli_begin_transaction($conn);

try {
    // First, get all materials used in this order to update their quantities
    $sql = "SELECT fm.MaterialID, fm.MaterialQuantity 
            FROM Furniture_Material fm
            WHERE fm.FurnitureID = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $order['FurnitureID']);
    mysqli_stmt_execute($stmt);
    $materialsResult = mysqli_stmt_get_result($stmt);
    
    // Update material quantities (restore used materials)
    while ($material = mysqli_fetch_assoc($materialsResult)) {
        $materialQuantityToRestore = $material['MaterialQuantity'] * $order['OrderQuantity'];
        
        $updateSql = "UPDATE Material 
                      SET PhysicalQuantity = PhysicalQuantity + ? 
                      WHERE MaterialID = ?";
        $updateStmt = mysqli_prepare($conn, $updateSql);
        mysqli_stmt_bind_param($updateStmt, "di", $materialQuantityToRestore, $material['MaterialID']);
        mysqli_stmt_execute($updateStmt);
        mysqli_stmt_close($updateStmt);
    }
    
    // Update furniture stock (restore quantity)
    $updateStockSql = "UPDATE Furniture SET StockQuantity = StockQuantity + ? WHERE FurnitureID = ?";
    $updateStockStmt = mysqli_prepare($conn, $updateStockSql);
    mysqli_stmt_bind_param($updateStockStmt, "ii", $order['OrderQuantity'], $order['FurnitureID']);
    mysqli_stmt_execute($updateStockStmt);
    mysqli_stmt_close($updateStockStmt);
    
    // Delete the order
    $deleteSql = "DELETE FROM Orders WHERE OrderID = ?";
    $deleteStmt = mysqli_prepare($conn, $deleteSql);
    mysqli_stmt_bind_param($deleteStmt, "i", $orderId);
    mysqli_stmt_execute($deleteStmt);
    mysqli_stmt_close($deleteStmt);
    
    // Commit transaction
    mysqli_commit($conn);
    
    $_SESSION['success'] = 'Order #' . $orderId . ' for "' . htmlspecialchars($order['FurnitureName']) . '" has been successfully cancelled. Stock has been restored.';
    
} catch (Exception $e) {
    // Rollback transaction on error
    mysqli_rollback($conn);
    $_SESSION['error'] = 'Failed to delete order: ' . $e->getMessage();
}

redirect('view_orders.php');
?>