<?php
/**
 * wishlist_actions.php - AJAX handler for wishlist and reviews
 */

require_once '../inc/config.php';
require_once '../inc/session.php';
require_once '../inc/advanced.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !hasRole('customer')) {
    echo json_encode(['success' => false, 'message' => 'Please sign in.']);
    exit();
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'add_wishlist':
        $fid = (int)($_POST['furniture_id'] ?? 0);
        echo json_encode(addToWishlist($fid));
        break;

    case 'remove_wishlist':
        $fid = (int)($_POST['furniture_id'] ?? 0);
        echo json_encode(removeFromWishlist($fid));
        break;

    case 'get_wishlist':
        initWishlist();
        echo json_encode(['success' => true, 'ids' => $_SESSION['wishlist'] ?? [], 'count' => getWishlistCount()]);
        break;

    case 'add_review':
        $fid = (int)($_POST['furniture_id'] ?? 0);
        $rating = (int)($_POST['rating'] ?? 5);
        $comment = trim($_POST['comment'] ?? '');
        if (empty($comment)) {
            echo json_encode(['success' => false, 'message' => 'Please write a comment.']);
            break;
        }
        $result = addReview($_SESSION['user_id'], $_SESSION['user_name'], $fid, $rating, $comment);
        echo json_encode($result);
        break;

    case 'get_reviews':
        $fid = (int)($_GET['furniture_id'] ?? 0);
        $reviews = getProductReviews($fid);
        echo json_encode([
            'success' => true,
            'reviews' => $reviews,
            'average' => getAverageRating($fid),
            'stars' => getStarRating(getAverageRating($fid)),
            'count' => count($reviews)
        ]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}
