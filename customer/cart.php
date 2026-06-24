<?php
/**
 * cart.php - AJAX Cart Operations
 * Session-based shopping cart for customers
 * Supports customization: color, size, accessories per item
 */

require_once '../inc/config.php';
require_once '../inc/session.php';
require_once '../inc/functions.php';

header('Content-Type: application/json');

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    // ── Add item to cart ─────────────────────
    case 'add':
        if (!isLoggedIn() || !hasRole('customer')) {
            echo json_encode(['success' => false, 'message' => 'Please sign in as a customer.']);
            exit();
        }
        $fid = (int)($_POST['furniture_id'] ?? 0);
        $qty = (int)($_POST['quantity'] ?? 1);
        $color = $_POST['selected_color'] ?? '';
        $size = $_POST['selected_size'] ?? '';
        $accessoriesJson = $_POST['selected_accessories'] ?? '';
        $customizationCost = (float)($_POST['customization_cost'] ?? 0);
        $accessories = $accessoriesJson ? json_decode($accessoriesJson, true) : [];

        if ($fid <= 0 || $qty <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid product or quantity.']);
            exit();
        }

        // Check stock
        $furniture = getFurnitureById($conn, $fid);
        if (!$furniture) {
            echo json_encode(['success' => false, 'message' => 'Product not found.']);
            exit();
        }

        // Generate unique key for this furniture + customization combo
        $cartKey = $fid . '_' . substr(md5($color . '_' . $size . '_' . $accessoriesJson), 0, 8);

        // Sum existing qty for this furniture across all cart entries
        $existingQty = 0;
        foreach ($_SESSION['cart'] as $k => $item) {
            if ($item['furniture_id'] == $fid) {
                $existingQty += $item['quantity'];
            }
        }
        if ($existingQty + $qty > $furniture['StockQuantity']) {
            echo json_encode(['success' => false, 'message' => "Only {$furniture['StockQuantity']} in stock. You already have $existingQty in cart."]);
            exit();
        }

        if (isset($_SESSION['cart'][$cartKey])) {
            $_SESSION['cart'][$cartKey]['quantity'] += $qty;
        } else {
            $_SESSION['cart'][$cartKey] = [
                'furniture_id' => $fid,
                'quantity' => $qty,
                'color' => $color,
                'size' => $size,
                'accessories' => $accessories,
                'customization_cost' => $customizationCost
            ];
        }
        $totalItems = array_sum(array_column($_SESSION['cart'], 'quantity'));

        echo json_encode([
            'success' => true,
            'message' => "{$furniture['FurnitureName']} added to cart!",
            'cart_count' => $totalItems
        ]);
        break;

    // ── Remove item from cart ────────────────
    case 'remove':
        $key = $_POST['cart_key'] ?? '';
        if ($key && isset($_SESSION['cart'][$key])) {
            unset($_SESSION['cart'][$key]);
        }
        echo json_encode(['success' => true, 'cart_count' => array_sum(array_column($_SESSION['cart'], 'quantity'))]);
        break;

    // ── Update quantity ──────────────────────
    case 'update':
        $key = $_POST['cart_key'] ?? '';
        $qty = (int)($_POST['quantity'] ?? 1);

        if ($key && isset($_SESSION['cart'][$key])) {
            $item = $_SESSION['cart'][$key];
            $furniture = getFurnitureById($conn, $item['furniture_id']);
            if ($qty <= 0) {
                unset($_SESSION['cart'][$key]);
            } elseif (!$furniture || $qty <= $furniture['StockQuantity']) {
                $_SESSION['cart'][$key]['quantity'] = $qty;
            }
        }
        echo json_encode(['success' => true, 'cart_count' => array_sum(array_column($_SESSION['cart'], 'quantity'))]);
        break;

    // ── Get cart contents ────────────────────
    case 'get':
        $items = [];
        $subtotal = 0;
        $cartIds = [];
        foreach ($_SESSION['cart'] as $key => $item) {
            $cartIds[] = $item['furniture_id'];
        }

        // Bulk image lookup
        $cartImages = [];
        if (!empty($cartIds)) {
            $idsStr = implode(',', array_map('intval', array_unique($cartIds)));
            $imgSql = "SELECT FurnitureID, ImagePath FROM FurnitureImage WHERE FurnitureID IN ($idsStr) AND IsPrimary = 1";
            $imgResult = mysqli_query($conn, $imgSql);
            while ($img = mysqli_fetch_assoc($imgResult)) {
                $cartImages[$img['FurnitureID']] = $img['ImagePath'];
            }
            if (count($cartImages) < count(array_unique($cartIds))) {
                $imgSql2 = "SELECT FurnitureID, ImagePath FROM FurnitureImage WHERE FurnitureID IN ($idsStr) ORDER BY SortOrder ASC";
                $imgResult2 = mysqli_query($conn, $imgSql2);
                while ($img2 = mysqli_fetch_assoc($imgResult2)) {
                    if (!isset($cartImages[$img2['FurnitureID']])) {
                        $cartImages[$img2['FurnitureID']] = $img2['ImagePath'];
                    }
                }
            }
        }

        foreach ($_SESSION['cart'] as $key => $item) {
            $f = getFurnitureById($conn, $item['furniture_id']);
            if ($f) {
                $lineTotal = ($f['Price'] + $item['customization_cost']) * $item['quantity'];
                $subtotal += $lineTotal;
                $items[] = [
                    'cart_key' => $key,
                    'furniture_id' => $item['furniture_id'],
                    'name' => $f['FurnitureName'],
                    'price' => $f['Price'],
                    'quantity' => $item['quantity'],
                    'color' => $item['color'] ?? '',
                    'size' => $item['size'] ?? '',
                    'accessories' => $item['accessories'] ?? [],
                    'customization_cost' => $item['customization_cost'] ?? 0,
                    'stock' => $f['StockQuantity'],
                    'line_total' => $lineTotal,
                    'image_path' => $cartImages[$item['furniture_id']] ?? null,
                    'category' => $f['Category']
                ];
            }
        }
        echo json_encode([
            'success' => true,
            'items' => $items,
            'subtotal' => $subtotal,
            'item_count' => count($items),
            'total_qty' => array_sum(array_column($_SESSION['cart'], 'quantity'))
        ]);
        break;

    // ── Checkout — place all cart orders ─────
    case 'checkout':
        if (!isLoggedIn() || !hasRole('customer')) {
            echo json_encode(['success' => false, 'message' => 'Please sign in as a customer.']);
            exit();
        }
        if (empty($_SESSION['cart'])) {
            echo json_encode(['success' => false, 'message' => 'Your cart is empty.']);
            exit();
        }

        $paymentMethod = $_POST['payment_method'] ?? '';
        $allowedMethods = ['credit_card', 'bank_transfer', 'cod'];
        if (!in_array($paymentMethod, $allowedMethods)) {
            echo json_encode(['success' => false, 'message' => 'Please select a valid payment method.']);
            exit();
        }

        $customer = getCustomerById($conn, $_SESSION['user_id']);
        $deliveryAddress = $customer['Address'] ?? '';
        $deliveryDate = calculateDeliveryDate(2);
        $results = [];
        $allSuccess = true;

        foreach ($_SESSION['cart'] as $key => $item) {
            $accJson = !empty($item['accessories']) ? json_encode($item['accessories']) : null;
            $result = createOrder(
                $conn,
                $_SESSION['user_id'],
                $item['furniture_id'],
                $item['quantity'],
                $deliveryAddress,
                $deliveryDate,
                $paymentMethod,
                $item['color'] ?? null,
                $item['size'] ?? null,
                $item['accessories'] ?? null,
                $item['customization_cost'] * $item['quantity']
            );
            $results[] = $result;
            if (!$result['success']) {
                $allSuccess = false;
            }
        }

        if ($allSuccess) {
            $_SESSION['cart'] = [];
            echo json_encode([
                'success' => true,
                'message' => 'All items ordered successfully! Redirecting to your orders...',
                'redirect' => 'view_orders.php',
                'orders' => $results
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Some items could not be ordered. Please check stock.',
                'orders' => $results
            ]);
        }
        break;

    // ── Clear cart ───────────────────────────
    case 'clear':
        $_SESSION['cart'] = [];
        echo json_encode(['success' => true, 'cart_count' => 0]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}
