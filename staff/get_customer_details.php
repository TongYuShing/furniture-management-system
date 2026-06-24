<?php
/**
 * get_customer_details.php - AJAX endpoint for staff to view customer details
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
    echo json_encode(['success' => false, 'message' => 'Invalid customer ID']);
    exit();
}

$customerId = (int)$_GET['id'];

// Get customer details
$customer = getCustomerById($conn, $customerId);
if (!$customer) {
    echo json_encode(['success' => false, 'message' => 'Customer not found']);
    exit();
}

// Get customer's orders
$orders = getOrdersByCustomerId($conn, $customerId, 'OrderDate', 'DESC');

echo json_encode([
    'success' => true,
    'customer' => $customer,
    'orders' => $orders
]);
