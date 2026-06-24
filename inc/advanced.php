<?php
/**
 * advanced.php - Integration & Expansion Functions
 * Part V: Wishlist, Reviews, Email Queue, API helpers
 */

require_once 'config.php';

// ═══════════════════════════════════════════════
// WISHLIST (Session-based, like cart)
// ═══════════════════════════════════════════════

function initWishlist() {
    if (!isset($_SESSION['wishlist'])) {
        $_SESSION['wishlist'] = [];
    }
}

function addToWishlist($furnitureId) {
    initWishlist();
    if (!in_array($furnitureId, $_SESSION['wishlist'])) {
        $_SESSION['wishlist'][] = (int)$furnitureId;
        return ['success' => true, 'message' => 'Added to wishlist!', 'count' => count($_SESSION['wishlist'])];
    }
    return ['success' => false, 'message' => 'Already in your wishlist.'];
}

function removeFromWishlist($furnitureId) {
    initWishlist();
    $_SESSION['wishlist'] = array_filter($_SESSION['wishlist'], function($id) use ($furnitureId) {
        return $id != $furnitureId;
    });
    $_SESSION['wishlist'] = array_values($_SESSION['wishlist']);
    return ['success' => true, 'count' => count($_SESSION['wishlist'])];
}

function getWishlistCount() {
    return count($_SESSION['wishlist'] ?? []);
}

function getWishlistProducts($conn) {
    initWishlist();
    if (empty($_SESSION['wishlist'])) return [];
    $ids = implode(',', array_map('intval', $_SESSION['wishlist']));
    $sql = "SELECT * FROM Furniture WHERE FurnitureID IN ($ids)";
    $result = mysqli_query($conn, $sql);
    $products = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $products[] = $row;
    }
    return $products;
}

// ═══════════════════════════════════════════════
// PRODUCT REVIEWS (JSON file storage)
// ═══════════════════════════════════════════════

define('REVIEWS_FILE', __DIR__ . '/../data/reviews.json');

function loadReviews() {
    if (!file_exists(REVIEWS_FILE)) return [];
    $data = file_get_contents(REVIEWS_FILE);
    return json_decode($data, true) ?: [];
}

function saveReviews($reviews) {
    $dir = dirname(REVIEWS_FILE);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents(REVIEWS_FILE, json_encode($reviews, JSON_PRETTY_PRINT), LOCK_EX);
}

function addReview($customerId, $customerName, $furnitureId, $rating, $comment) {
    $reviews = loadReviews();
    // Check if customer already reviewed this product
    foreach ($reviews as $r) {
        if ($r['customer_id'] == $customerId && $r['furniture_id'] == $furnitureId) {
            return ['success' => false, 'message' => 'You have already reviewed this product.'];
        }
    }
    $reviews[] = [
        'review_id' => count($reviews) + 1,
        'customer_id' => (int)$customerId,
        'customer_name' => $customerName,
        'furniture_id' => (int)$furnitureId,
        'rating' => min(5, max(1, (int)$rating)),
        'comment' => substr(trim($comment), 0, 500),
        'created_at' => date('Y-m-d H:i:s')
    ];
    saveReviews($reviews);
    return ['success' => true, 'message' => 'Review submitted! Thank you for your feedback.'];
}

function getProductReviews($furnitureId) {
    $reviews = loadReviews();
    $productReviews = array_filter($reviews, function($r) use ($furnitureId) {
        return $r['furniture_id'] == $furnitureId;
    });
    $productReviews = array_values($productReviews);
    // Sort newest first
    usort($productReviews, function($a, $b) { return strtotime($b['created_at']) - strtotime($a['created_at']); });
    return $productReviews;
}

function getAverageRating($furnitureId) {
    $reviews = getProductReviews($furnitureId);
    if (empty($reviews)) return 0;
    return round(array_sum(array_column($reviews, 'rating')) / count($reviews), 1);
}

function getStarRating($rating) {
    $html = '';
    for ($i = 1; $i <= 5; $i++) {
        $html .= $i <= round($rating) ? '⭐' : '☆';
    }
    return $html;
}

// ═══════════════════════════════════════════════
// EMAIL NOTIFICATION QUEUE (Simulated, JSON)
// ═══════════════════════════════════════════════

define('EMAIL_QUEUE_FILE', __DIR__ . '/../data/email_queue.json');

function loadEmailQueue() {
    if (!file_exists(EMAIL_QUEUE_FILE)) return [];
    return json_decode(file_get_contents(EMAIL_QUEUE_FILE), true) ?: [];
}

function saveEmailQueue($queue) {
    $dir = dirname(EMAIL_QUEUE_FILE);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents(EMAIL_QUEUE_FILE, json_encode($queue, JSON_PRETTY_PRINT), LOCK_EX);
}

function queueEmail($toEmail, $toName, $subject, $body) {
    $queue = loadEmailQueue();
    $queue[] = [
        'id' => count($queue) + 1,
        'to_email' => $toEmail,
        'to_name' => $toName,
        'subject' => $subject,
        'body' => $body,
        'status' => 'queued',
        'created_at' => date('Y-m-d H:i:s')
    ];
    saveEmailQueue($queue);
}

function queueOrderConfirmation($conn, $orderId) {
    $sql = "SELECT o.*, c.CustomerName, c.Email, f.FurnitureName
            FROM Orders o
            INNER JOIN Customer c ON o.CustomerID = c.CustomerID
            INNER JOIN Furniture f ON o.FurnitureID = f.FurnitureID
            WHERE o.OrderID = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $orderId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $order = mysqli_fetch_assoc($result);

    if ($order) {
        $subject = "Order #{$orderId} Confirmed - Premium Living Furniture";
        $body = "Dear {$order['CustomerName']},\n\n" .
                "Your order #{$orderId} for {$order['FurnitureName']} (Qty: {$order['OrderQuantity']}) " .
                "totaling \${$order['TotalOrderAmount']} has been confirmed.\n\n" .
                "Delivery Date: {$order['DeliveryDate']}\n" .
                "Address: {$order['DeliveryAddress']}\n\n" .
                "Thank you for shopping with Premium Living Furniture!";
        queueEmail($order['Email'], $order['CustomerName'], $subject, $body);
    }
}

function queueStatusNotification($conn, $orderId, $newStatus) {
    $sql = "SELECT o.*, c.CustomerName, c.Email, f.FurnitureName
            FROM Orders o
            INNER JOIN Customer c ON o.CustomerID = c.CustomerID
            INNER JOIN Furniture f ON o.FurnitureID = f.FurnitureID
            WHERE o.OrderID = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $orderId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $order = mysqli_fetch_assoc($result);

    if ($order) {
        $statusLabels = ['accepted' => 'Accepted', 'rejected' => 'Rejected', 'delivered' => 'Delivered'];
        $label = $statusLabels[$newStatus] ?? ucfirst($newStatus);
        $subject = "Order #{$orderId} {$label} - Premium Living Furniture";
        $body = "Dear {$order['CustomerName']},\n\n" .
                "Your order #{$orderId} ({$order['FurnitureName']}) has been {$label}.\n\n" .
                "Thank you for shopping with Premium Living Furniture!";
        queueEmail($order['Email'], $order['CustomerName'], $subject, $body);
    }
}

function getEmailQueueCount() {
    return count(loadEmailQueue());
}

// ═══════════════════════════════════════════════
// SIMPLE REST API HELPER
// ═══════════════════════════════════════════════

function apiResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit();
}

function apiError($message, $statusCode = 400) {
    apiResponse(['success' => false, 'error' => $message], $statusCode);
}
