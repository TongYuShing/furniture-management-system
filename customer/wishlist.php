<?php
/**
 * wishlist.php - Customer Wishlist
 * Part V: Save products for later purchase
 */

require_once '../inc/config.php';
require_once '../inc/session.php';
require_once '../inc/functions.php';
require_once '../inc/advanced.php';

checkCustomerRole();
initWishlist();

$message = '';
$messageType = '';

// Handle remove action
if (isset($_GET['remove']) && is_numeric($_GET['remove'])) {
    $result = removeFromWishlist((int)$_GET['remove']);
    if ($result['success']) {
        $message = 'Item removed from wishlist.';
        $messageType = 'info';
    }
}

// Handle add to cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $fid = (int)$_POST['furniture_id'];
    if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
    $currentQty = $_SESSION['cart'][$fid] ?? 0;
    $_SESSION['cart'][$fid] = $currentQty + 1;
    removeFromWishlist($fid);
    $message = 'Item moved to cart!';
    $messageType = 'success';
}

$wishlistProducts = getWishlistProducts($conn);
$wishlistCount = getWishlistCount();

// Fetch product view images for all wishlist items
$wishlistImages = [];
if (!empty($wishlistProducts)) {
    $wids = array_column($wishlistProducts, 'FurnitureID');
    $widsStr = implode(',', array_map('intval', $wids));
    $imgSql = "SELECT FurnitureID, ImagePath, IsPrimary FROM FurnitureImage WHERE FurnitureID IN ($widsStr) ORDER BY SortOrder ASC";
    $imgResult = mysqli_query($conn, $imgSql);
    while ($img = mysqli_fetch_assoc($imgResult)) {
        if (!isset($wishlistImages[$img['FurnitureID']])) {
            $wishlistImages[$img['FurnitureID']] = [];
        }
        $wishlistImages[$img['FurnitureID']][] = $img;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Wishlist - Premium Living Furniture</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header class="header">
        <div class="container">
            <a href="../index.php" style="text-decoration:none;">
                <div class="logo">
                    <h1>Premium Living Furniture</h1>
                    <p>Quality Furniture for Your Home</p>
                </div>
            </a>
            <div class="user-info">
    <span>👋 Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</span>
    <div class="header-buttons">
        <a href="../index.php" class="btn-dashboard">🏠 Back to Home</a>
        <a href="dashboard.php" class="btn-dashboard">📊 Dashboard</a>
        <a href="logout.php" class="btn-logout">🚪 Logout</a>
    </div>
</div>
        </div>
    </header>

    <div class="container">
        <div class="dashboard-layout">
            <aside class="sidebar">
                <h3>Customer Menu</h3>
                <ul>
                    <li><a href="dashboard.php">🏠 Dashboard</a></li>
                    <li><a href="view_orders.php">📋 My Orders</a></li>
                    <li><a href="wishlist.php" class="active">❤️ Wishlist</a></li>
                    <li><a href="update_profile.php">👤 Update Profile</a></li>
                </ul>
                <div style="margin-top: var(--space-4); padding: var(--space-4); background: var(--gray-50); border-radius: var(--radius);">
                    <p style="font-size: var(--font-size-sm);"><strong>❤️ <?php echo $wishlistCount; ?></strong> item(s) saved</p>
                </div>
            </aside>

            <main class="main-content">
                <h2>❤️ My Wishlist</h2>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?>"><?php echo $message; ?></div>
                <?php endif; ?>

                <?php if (empty($wishlistProducts)): ?>
                    <div class="alert alert-info" style="text-align: center; padding: var(--space-10);">
                        <p style="font-size: 1.5rem; margin-bottom: var(--space-4);">❤️</p>
                        <p style="font-size: 1.1rem;">Your wishlist is empty.</p>
                        <p style="color: var(--gray-500); margin-top: var(--space-2);">Browse products and click the heart to save them for later.</p>
                        <a href="../index.php#products" class="btn btn-primary" style="margin-top: var(--space-4);">🛍️ Browse Products</a>
                    </div>
                <?php else: ?>
                    <div class="card-grid" style="grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));">
                        <?php foreach ($wishlistProducts as $product):
                            $pid = $product['FurnitureID'];
                            $views = $wishlistImages[$pid] ?? [];
                            $primaryImg = null;
                            foreach ($views as $v) {
                                if ($v['IsPrimary']) { $primaryImg = $v; break; }
                            }
                            if (!$primaryImg && !empty($views)) $primaryImg = $views[0];
                            $imgSrc = $primaryImg
                                ? 'assets/images/furniture/' . str_replace(' ', '%20', $primaryImg['ImagePath'])
                                : '';
                            $fallbackIcon = ['Desks' => '🪑', 'Chairs' => '💺', 'Tables' => '🍽️', 'Storage' => '📚', 'Sofas' => '🛋️', 'Beds' => '🛏️'][$product['Category']] ?? '🪑';
                        ?>
                            <div class="card">
                                <div class="card-image-wrap">
                                    <?php if ($imgSrc): ?>
                                        <img class="furniture-img" src="../<?php echo $imgSrc; ?>" alt="<?php echo htmlspecialchars($product['FurnitureName']); ?>" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                                        <span class="furniture-img-fallback" style="display:none;font-size:4rem;"><?php echo $fallbackIcon; ?></span>
                                    <?php else: ?>
                                        <span class="furniture-img" style="font-size: 4rem;"><?php echo $fallbackIcon; ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="card-body">
                                    <span class="card-category"><?php echo htmlspecialchars($product['Category']); ?></span>
                                    <h3 class="card-title"><?php echo htmlspecialchars($product['FurnitureName']); ?></h3>
                                    <div class="card-footer">
                                        <span class="card-price"><?php echo formatCurrency($product['Price']); ?></span>
                                        <span class="card-stock <?php echo $product['StockQuantity'] > 0 ? 'in-stock' : 'sold-out'; ?>">
                                            <?php echo $product['StockQuantity'] > 0 ? 'In Stock' : 'Sold Out'; ?>
                                        </span>
                                    </div>
                                    <div class="card-actions" style="gap: var(--space-2);">
                                        <?php if ($product['StockQuantity'] > 0): ?>
                                            <form method="POST" style="flex: 1;">
                                                <input type="hidden" name="furniture_id" value="<?php echo $product['FurnitureID']; ?>">
                                                <button type="submit" name="add_to_cart" value="1" class="btn btn-accent btn-block">🛒 Add to Cart</button>
                                            </form>
                                        <?php endif; ?>
                                        <a href="wishlist.php?remove=<?php echo $product['FurnitureID']; ?>" class="btn btn-outline btn-sm" style="padding: var(--space-2) var(--space-3);">🗑️</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Premium Living Furniture Co. Ltd. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>