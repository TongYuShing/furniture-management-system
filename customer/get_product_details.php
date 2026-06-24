<?php
/**
 * get_product_details.php - AJAX endpoint to get product details
 */

require_once '../inc/config.php';
require_once '../inc/session.php';
require_once '../inc/functions.php';

header('Content-Type: application/json');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
    exit();
}

$furnitureId = (int)$_GET['id'];

$product = getFurnitureById($conn, $furnitureId);
if (!$product) {
    echo json_encode(['success' => false, 'message' => 'Product not found']);
    exit();
}

$materials = getMaterialsByFurnitureId($conn, $furnitureId);
$images = getFurnitureImages($conn, $furnitureId);
$colors = getFurnitureColors($conn, $furnitureId);
$sizes = getFurnitureSizes($conn, $furnitureId);
$accessories = getFurnitureAccessories($conn, $furnitureId);

echo json_encode([
    'success' => true,
    'product' => $product,
    'materials' => $materials,
    'images' => $images,
    'colors' => $colors,
    'sizes' => $sizes,
    'accessories' => $accessories
]);
?>