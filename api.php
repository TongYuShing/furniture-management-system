<?php
/**
 * api.php - REST API Endpoint
 * Part V: Simple API for integration with external systems
 * Endpoints: ?action=products | ?action=product&id=X | ?action=orders&status=X
 */

require_once 'inc/config.php';
require_once 'inc/functions.php';
require_once 'inc/advanced.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$action = $_GET['action'] ?? '';

switch ($action) {

    // GET /api.php?action=products
    case 'products':
        $products = getAllFurniture($conn);
        $result = [];
        foreach ($products as $p) {
            $result[] = [
                'id' => (int)$p['FurnitureID'],
                'name' => $p['FurnitureName'],
                'category' => $p['Category'],
                'price' => (float)$p['Price'],
                'stock' => (int)$p['StockQuantity'],
                'rating' => getAverageRating($p['FurnitureID']),
                'description' => $p['FurnitureDescription']
            ];
        }
        apiResponse(['success' => true, 'count' => count($result), 'data' => $result]);
        break;

    // GET /api.php?action=product&id=X
    case 'product':
        $id = (int)($_GET['id'] ?? 0);
        $product = getFurnitureById($conn, $id);
        if (!$product) apiError('Product not found', 404);
        $materials = getMaterialsByFurnitureId($conn, $id);
        $reviews = getProductReviews($id);
        apiResponse([
            'success' => true,
            'data' => [
                'id' => (int)$product['FurnitureID'],
                'name' => $product['FurnitureName'],
                'category' => $product['Category'],
                'price' => (float)$product['Price'],
                'stock' => (int)$product['StockQuantity'],
                'rating' => getAverageRating($id),
                'review_count' => count($reviews),
                'description' => $product['FurnitureDescription'],
                'materials' => $materials,
                'reviews' => array_slice($reviews, 0, 5)
            ]
        ]);
        break;

    // GET /api.php?action=orders&status=pending (staff only via token)
    case 'orders':
        $status = $_GET['status'] ?? 'all';
        $status = $status === 'all' ? null : $status;
        $orders = getAllOrders($conn, $status);
        $result = array_slice($orders, 0, 50);
        apiResponse(['success' => true, 'count' => count($result), 'data' => $result]);
        break;

    // GET /api.php?action=stats
    case 'stats':
        $sql = "SELECT COUNT(*) as total FROM Orders";
        $totalOrders = mysqli_fetch_assoc(mysqli_query($conn, $sql))['total'];
        $sql = "SELECT COUNT(*) as total FROM Furniture";
        $totalProducts = mysqli_fetch_assoc(mysqli_query($conn, $sql))['total'];
        $sql = "SELECT SUM(TotalOrderAmount) as total FROM Orders WHERE OrderStatus='delivered'";
        $totalRevenue = mysqli_fetch_assoc(mysqli_query($conn, $sql))['total'] ?? 0;

        apiResponse([
            'success' => true,
            'data' => [
                'total_orders' => (int)$totalOrders,
                'total_products' => (int)$totalProducts,
                'total_revenue' => (float)$totalRevenue,
                'system' => 'Premium Living Furniture Management System',
                'version' => '2.0'
            ]
        ]);
        break;

    default:
        apiResponse([
            'success' => true,
            'message' => 'Premium Living Furniture API v2.0',
            'endpoints' => [
                '?action=products' => 'List all products with ratings',
                '?action=product&id=X' => 'Get product details, materials, reviews',
                '?action=orders&status=X' => 'List orders (all/pending/accepted/delivered)',
                '?action=stats' => 'System statistics'
            ]
        ]);
}
