<?php
/**
 * place_order.php - AJAX endpoint to create an order from the product modal
 */

require_once '../inc/config.php';
require_once '../inc/session.php';
require_once '../inc/functions.php';

header('Content-Type: application/json');

// Must be logged in as customer
if (!isLoggedIn() || !hasRole('customer')) {
    echo json_encode(['success' => false, 'message' => 'Please sign in as a customer to place an order.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

$furnitureId = isset($_POST['furniture_id']) ? (int)$_POST['furniture_id'] : 0;
$quantity    = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

if ($furnitureId <= 0 || $quantity <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product or quantity.']);
    exit();
}

// Get customer info for default address
$customer = getCustomerById($conn, $_SESSION['user_id']);
$deliveryAddress = $customer['Address'] ?? '';
$deliveryDate = calculateDeliveryDate(2); // Min 2 business days, skip weekends

$result = createOrder($conn, $_SESSION['user_id'], $furnitureId, $quantity, $deliveryAddress, $deliveryDate);

echo json_encode($result);
