<?php
/**
 * index.php - Premium Living Furniture Homepage
 * Public-facing homepage with product showcase and login
 */

require_once 'inc/config.php';
require_once 'inc/session.php';
require_once 'inc/usability.php';

// Try auto-login via Remember Me cookie
tryRememberMeAutoLogin($conn);

// If already logged in, redirect to appropriate dashboard
// (but only if they're trying to access index.php directly with a ?login flag, or POSTing login)
// For regular browsing, let them stay on the homepage
$redirectToDashboard = false;
if (isLoggedIn() && isset($_GET['redirect']) && $_GET['redirect'] === 'dashboard') {
    if (hasRole('customer')) {
        header("Location: customer/dashboard.php");
        exit();
    } else {
        header("Location: staff/dashboard.php");
        exit();
    }
}

$error = '';
$success = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = isset($_POST['user_id']) ? trim($_POST['user_id']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    $role = isset($_POST['role']) ? $_POST['role'] : 'customer';

    if (empty($userId) || empty($password)) {
        $error = 'Please enter both ID and password.';
    } else {
        if ($role === 'customer') {
            if (customerLogin($conn, $userId, $password)) {
                // Set Remember Me cookie if requested
                if (!empty($_POST['remember_me'])) {
                    $token = generateRememberToken($_SESSION['user_id'], 'customer');
                    setcookie('remember_token', $token, time() + (30 * 24 * 3600), '/', '', false, true);
                }
                header("Location: index.php");
                exit();
            } else {
                $error = 'Invalid Customer ID or Password.';
            }
        } else {
            if (staffLogin($conn, $userId, $password)) {
                if (!empty($_POST['remember_me'])) {
                    $token = generateRememberToken($_SESSION['user_id'], 'staff');
                    setcookie('remember_token', $token, time() + (30 * 24 * 3600), '/', '', false, true);
                }
                header("Location: staff/dashboard.php");
                exit();
            } else {
                $error = 'Invalid Staff ID or Password.';
            }
        }
    }
}

// ── Live Stats for Hero Section ─────────────────
$sql = "SELECT COUNT(*) AS total FROM Customer";
$totalCustomers = mysqli_fetch_assoc(mysqli_query($conn, $sql))['total'];

$sql = "SELECT COUNT(*) AS total FROM Furniture";
$totalCollections = mysqli_fetch_assoc(mysqli_query($conn, $sql))['total'];

$sql = "SELECT COUNT(*) AS total FROM Material";
$totalMaterials = mysqli_fetch_assoc(mysqli_query($conn, $sql))['total'];

// Get all distinct categories (unfiltered, for the dropdown)
$sql = "SELECT DISTINCT Category FROM Furniture ORDER BY Category ASC";
$catResult = mysqli_query($conn, $sql);
$allCategories = [];
while ($catRow = mysqli_fetch_assoc($catResult)) {
    $allCategories[] = $catRow['Category'];
}

// Fetch all furniture products for display
$sql = "SELECT * FROM Furniture ORDER BY FurnitureName ASC";
$result = mysqli_query($conn, $sql);
$allFurnitureProducts = [];
while ($row = mysqli_fetch_assoc($result)) {
    $row['is_sold_out'] = ($row['StockQuantity'] <= 0);
    $row['is_low_stock'] = ($row['StockQuantity'] > 0 && $row['StockQuantity'] < 5);
    $allFurnitureProducts[] = $row;
}

// Fetch all product view images — build lookup by FurnitureID
$productImages = [];
$imgSql = "SELECT FurnitureID, ImagePath, IsPrimary FROM FurnitureImage ORDER BY SortOrder ASC";
$imgResult = mysqli_query($conn, $imgSql);
while ($img = mysqli_fetch_assoc($imgResult)) {
    if (!isset($productImages[$img['FurnitureID']])) {
        $productImages[$img['FurnitureID']] = [];
    }
    $productImages[$img['FurnitureID']][] = $img;
}

// Server-side search across ALL products
$searchTerm = $_GET['search'] ?? '';
if (!empty($searchTerm)) {
    $searchLower = strtolower(trim($searchTerm));
    $allFurnitureProducts = array_filter($allFurnitureProducts, function($product) use ($searchLower) {
        return strpos(strtolower($product['FurnitureName']), $searchLower) !== false ||
               strpos(strtolower($product['FurnitureDescription']), $searchLower) !== false ||
               strpos(strtolower($product['Category']), $searchLower) !== false;
    });
    $allFurnitureProducts = array_values($allFurnitureProducts);
}

// Server-side category filter across ALL products
$categoryFilter = $_GET['category'] ?? 'all';
if ($categoryFilter !== 'all') {
    $allFurnitureProducts = array_filter($allFurnitureProducts, function($product) use ($categoryFilter) {
        return $product['Category'] === $categoryFilter;
    });
    $allFurnitureProducts = array_values($allFurnitureProducts);
}

// Pagination for product grid
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 8;
$totalProducts = count($allFurnitureProducts);
$totalPages = max(1, (int)ceil($totalProducts / $perPage));
$currentPage = max(1, min($page, $totalPages));
$offset = ($currentPage - 1) * $perPage;
$furnitureProducts = array_slice($allFurnitureProducts, $offset, $perPage);

// Build pagination URLs (preserve search + category)
$paginationBaseUrl = 'index.php?page={page}';
if ($searchTerm) $paginationBaseUrl .= '&search=' . urlencode($searchTerm);
if ($categoryFilter !== 'all') $paginationBaseUrl .= '&category=' . urlencode($categoryFilter);

// Category-to-emoji mapping for placeholder images
$categoryIcons = [
    'Desks' => '🪑',
    'Chairs' => '💺',
    'Tables' => '🍽️',
    'Storage' => '📚',
    'Sofas' => '🛋️',
    'Beds' => '🛏️',
];

// Product-specific icons
$productIcons = [
    101 => '🪑', // Executive Oak Desk
    102 => '💺', // Leather Recliner Chair
    103 => '🍽️', // Walnut Dining Table
    104 => '📚', // Modern Bookshelf
    105 => '🛋️', // Grey Fabric Sofa
    106 => '☕', // Glass Coffee Table
    107 => '💺', // Ergonomic Office Chair
    108 => '🛏️', // Solid Wood Bed Frame
];

function getProductIcon($productId, $category, $icons, $categoryIcons) {
    if (isset($icons[$productId])) {
        return $icons[$productId];
    }
    return $categoryIcons[$category] ?? '🪑';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Premium Living Furniture — Quality Furniture for Your Home</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <!-- ============================================
    TOP NAVIGATION BAR
    ============================================ -->
    <nav class="navbar" id="navbar">
        <div class="navbar-inner">
            <!-- Brand -->
            <a href="index.php" class="navbar-brand">
                <div class="brand-icon">🪑</div>
                <div class="brand-text">
                    <h2>Premium Living</h2>
                    <span>Furniture Co.</span>
                </div>
            </a>

            <!-- Nav Links -->
            <ul class="navbar-links" id="navLinks">
                <li><a href="#home" class="active">🏠 Home</a></li>
                <li><a href="#products">🛍️ Products</a></li>
                <li><a href="#about">ℹ️ About</a></li>
                <li><a href="customer/contact_us.php">📞 Contact</a></li>
                <?php if (isLoggedIn()): ?>
                    <?php if (hasRole('customer')): ?>
                        <li><a href="customer/dashboard.php" class="btn-nav btn-nav-primary" style="margin-left:8px;">📊 Dashboard</a></li>
                    <?php else: ?>
                        <li><a href="staff/dashboard.php" class="btn-nav btn-nav-primary" style="margin-left:8px;">📊 Dashboard</a></li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>

            <!-- Actions -->
            <div class="navbar-actions">
                <?php if (isLoggedIn()): ?>
                    <span style="font-weight:600;font-size:0.875rem;color:var(--primary);">
                        👋 <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                    </span>
                    <a href="<?php echo hasRole('customer') ? 'customer/logout.php' : 'staff/logout.php'; ?>" class="btn-nav btn-nav-outline">🚪 Logout</a>
                    <?php if (hasRole('customer')): ?>
                        <a href="#" class="btn-nav btn-nav-accent" onclick="event.preventDefault(); openCartModal();" id="cartBtn">🛒 Cart (<span id="cartCount"><?php echo array_sum(array_column($_SESSION['cart'] ?? [], 'quantity')); ?></span>)</a>
                    <?php endif; ?>
                <?php else: ?>
                    <button class="btn-nav btn-nav-outline" onclick="openLoginModal()">🔐 Sign In</button>
                    <button class="btn-nav btn-nav-accent" onclick="openLoginModal()">✨ Get Started</button>
                <?php endif; ?>
            </div>

            <!-- Mobile Toggle -->
            <button class="navbar-toggle" id="navToggle" aria-label="Toggle menu">
                <span></span><span></span><span></span>
            </button>
        </div>
    </nav>

    <!-- ============================================
    HERO SECTION
    ============================================ -->
    <section class="hero" id="home">
        <div class="hero-pattern"></div>
        <div class="hero-content">
            <div class="hero-text">
                <h1>Crafting <span class="highlight">Timeless</span> Furniture for Your Home</h1>
                <p>
                    Premium quality furniture crafted with the finest materials.
                    From elegant dining tables to comfortable sofas — transform your
                    living space with pieces that last a lifetime.
                </p>
                <div class="hero-buttons">
                    <a href="#products" class="btn btn-accent btn-lg">🛍️ Explore Collection</a>
                    <?php if (!isLoggedIn()): ?>
                        <button class="btn btn-white btn-lg" onclick="openLoginModal()">🔐 Sign In to Order</button>
                    <?php else: ?>
                        <a href="customer/dashboard.php" class="btn btn-white btn-lg">📊 My Dashboard</a>
                    <?php endif; ?>
                </div>
                <div class="hero-stats">
                    <div class="hero-stat">
                        <div class="number"><?php echo $totalCustomers; ?>+</div>
                        <div class="label">Happy Customers</div>
                    </div>
                    <div class="hero-stat">
                        <div class="number"><?php echo $totalCollections; ?></div>
                        <div class="label">Collections</div>
                    </div>
                    <div class="hero-stat">
                        <div class="number"><?php echo $totalMaterials; ?></div>
                        <div class="label">Premium Materials</div>
                    </div>
                </div>
            </div>
            <div class="hero-visual">
                <div class="hero-card">
                    <span class="hero-icon">🛋️</span>
                    <h3>New Collection</h3>
                    <p>Discover our latest arrivals — modern designs with classic craftsmanship</p>
                    <a href="#products" class="btn btn-accent btn-sm">View All →</a>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================================
    PRODUCTS SECTION
    ============================================ -->
    <section class="section" id="products" style="background: var(--off-white);">
        <div class="container">
            <div class="section-header">
                <span class="section-label">Our Collection</span>
                <h2>Handcrafted Furniture</h2>
                <p>Browse our complete range of premium furniture, each piece crafted with care and attention to detail.</p>
            </div>

            <!-- Products Toolbar -->
            <div class="products-toolbar">
                <div class="products-count">
                    Showing <strong><?php echo count($furnitureProducts); ?></strong> of <?php echo $totalProducts; ?> products
                    <?php if ($searchTerm): ?>
                        <span style="font-size: var(--font-size-xs); color: var(--gray-500);">
                            matching "<strong><?php echo htmlspecialchars($searchTerm); ?></strong>"
                            &nbsp;<a href="index.php#products" style="color: var(--danger);">✕ clear</a>
                        </span>
                    <?php endif; ?>
                </div>
                <form method="GET" action="index.php#products" style="display: flex; align-items: center; gap: var(--space-2);" onsubmit="document.getElementById('searchProducts').value = document.getElementById('searchProducts').value.trim(); if(!document.getElementById('searchProducts').value) { window.location='index.php#products'; return false; }">
                    <div class="products-search">
                        <label for="searchProducts">🔍</label>
                        <input type="text" name="search" id="searchProducts" class="form-control"
                               placeholder="Search all furniture..."
                               value="<?php echo htmlspecialchars($searchTerm); ?>"
                               style="width: 220px; padding: var(--space-2) var(--space-4); font-size: var(--font-size-sm);">
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary" style="font-size: var(--font-size-xs);">Search</button>
                </form>
                <div class="products-sort">
                    <label for="sortCategory">Filter:</label>
                    <select id="sortCategory" onchange="window.location.href='index.php?category=' + encodeURIComponent(this.value) + '<?php echo $searchTerm ? '&search=' . urlencode($searchTerm) : ''; ?>#products'">
                        <option value="all">All Categories</option>
                        <?php
                        foreach ($allCategories as $cat):
                            $sel = (($_GET['category'] ?? 'all') === $cat) ? 'selected' : '';
                        ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($cat); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label for="sortBy">Sort:</label>
                    <select id="sortBy" onchange="sortProducts()">
                        <option value="name-asc">Name A-Z</option>
                        <option value="name-desc">Name Z-A</option>
                        <option value="price-asc">Price: Low to High</option>
                        <option value="price-desc">Price: High to Low</option>
                    </select>
                </div>
            </div>

            <!-- Product Cards Grid -->
            <div class="card-grid" id="productGrid">
                <?php foreach ($furnitureProducts as $product): ?>
                    <?php
                        $icon = getProductIcon($product['FurnitureID'], $product['Category'], $productIcons, $categoryIcons);
                        $pid = $product['FurnitureID'];
                        $views = $productImages[$pid] ?? [];
                        $primaryImg = null;
                        foreach ($views as $v) {
                            if ($v['IsPrimary']) { $primaryImg = $v; break; }
                        }
                        if (!$primaryImg && !empty($views)) $primaryImg = $views[0];
                        $imgSrc = $primaryImg
                            ? 'assets/images/furniture/' . str_replace(' ', '%20', $primaryImg['ImagePath'])
                            : '';
                        $stockClass = $product['is_sold_out'] ? 'sold-out' : ($product['is_low_stock'] ? 'low-stock' : 'in-stock');
                        $stockLabel = $product['is_sold_out'] ? 'Sold Out' : ($product['is_low_stock'] ? 'Only ' . $product['StockQuantity'] . ' left' : 'In Stock: ' . $product['StockQuantity']);
                    ?>
                    <div class="card" data-category="<?php echo htmlspecialchars($product['Category']); ?>" data-name="<?php echo htmlspecialchars($product['FurnitureName']); ?>" data-description="<?php echo htmlspecialchars($product['FurnitureDescription']); ?>" data-price="<?php echo $product['Price']; ?>" onclick="openProductModal(<?php echo $product['FurnitureID']; ?>)" style="cursor:pointer;" title="Click to view details">
                        <?php if ($product['is_sold_out']): ?>
                            <span class="card-badge sold-out">Sold Out</span>
                        <?php elseif ($product['CreatedDate'] && strtotime($product['CreatedDate']) > strtotime('-30 days')): ?>
                            <span class="card-badge new">New</span>
                        <?php endif; ?>

                        <div class="card-image-wrap">
                            <?php if ($imgSrc): ?>
                                <img class="furniture-img" src="<?php echo $imgSrc; ?>" alt="<?php echo htmlspecialchars($product['FurnitureName']); ?>" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                                <span class="furniture-img-fallback" style="display:none;"><?php echo $icon; ?></span>
                            <?php else: ?>
                                <span class="furniture-img"><?php echo $icon; ?></span>
                            <?php endif; ?>
                            <div class="img-overlay"></div>
                            <button class="quick-view" onclick="event.stopPropagation(); openProductModal(<?php echo $product['FurnitureID']; ?>)" title="Quick View">🔍</button>
                            <?php if (isLoggedIn() && hasRole('customer')): ?>
                                <button class="wishlist-btn" id="wl-btn-<?php echo $product['FurnitureID']; ?>" onclick="event.stopPropagation(); toggleWishlist(<?php echo $product['FurnitureID']; ?>, this)" title="Add to Wishlist" style="position:absolute;top:var(--space-4);right:var(--space-4);background:var(--white);border:none;width:36px;height:36px;border-radius:50%;cursor:pointer;font-size:1.1rem;box-shadow:var(--shadow-md);z-index:3;display:flex;align-items:center;justify-content:center;">🤍</button>
                            <?php endif; ?>
                        </div>

                        <div class="card-body">
                            <span class="card-category"><?php echo htmlspecialchars($product['Category']); ?></span>
                            <h3 class="card-title"><?php echo htmlspecialchars($product['FurnitureName']); ?></h3>
                            <p class="card-description">
                                <?php echo htmlspecialchars(substr($product['FurnitureDescription'], 0, 100)); ?>...
                            </p>
                            <div class="card-footer">
                                <span class="card-price">$<?php echo number_format($product['Price'], 2); ?></span>
                                <span class="card-stock <?php echo $stockClass; ?>"><?php echo $stockLabel; ?></span>
                            </div>
                            <div class="card-actions">
                                <?php if ($product['is_sold_out']): ?>
                                    <button class="btn btn-block" disabled>🔒 Out of Stock</button>
                                <?php elseif (isLoggedIn() && hasRole('customer')): ?>
                                    <a href="#products" class="btn btn-primary" style="flex:1;">🛒 Order Now</a>
                                    <button class="btn btn-outline" onclick="event.stopPropagation(); openProductModal(<?php echo $product['FurnitureID']; ?>)">Details</button>
                                <?php else: ?>
                                    <button class="btn btn-primary btn-block" onclick="event.stopPropagation(); openLoginModal()">🔐 Sign In to Order</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (empty($furnitureProducts)): ?>
                <div class="alert alert-info" style="text-align:center;padding:var(--space-10);">
                    <p style="font-size:1.2rem;">📦 No products available at the moment. Please check back soon!</p>
                </div>
            <?php endif; ?>

            <!-- Product Pagination -->
            <?php if ($totalPages > 1): ?>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: var(--space-8); flex-wrap: wrap; gap: var(--space-4);">
                    <span style="font-size: var(--font-size-sm); color: var(--gray-500);">
                        Showing <?php echo $offset + 1; ?>–<?php echo min($currentPage * $perPage, $totalProducts); ?> of <?php echo $totalProducts; ?> products
                    </span>
                    <div class="pagination">
                        <?php if ($currentPage > 1): ?>
                            <a href="<?php echo str_replace('{page}', $currentPage - 1, $paginationBaseUrl); ?>#products">← Prev</a>
                        <?php else: ?>
                            <span class="disabled">← Prev</span>
                        <?php endif; ?>

                        <?php
                        $startPage = max(1, $currentPage - 2);
                        $endPage = min($totalPages, $currentPage + 2);
                        if ($startPage > 1): ?>
                            <a href="<?php echo str_replace('{page}', 1, $paginationBaseUrl); ?>#products">1</a>
                            <?php if ($startPage > 2): ?><span>...</span><?php endif; ?>
                        <?php endif; ?>

                        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <?php if ($i == $currentPage): ?>
                                <span class="active"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="<?php echo str_replace('{page}', $i, $paginationBaseUrl); ?>#products"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($endPage < $totalPages): ?>
                            <?php if ($endPage < $totalPages - 1): ?><span>...</span><?php endif; ?>
                            <a href="<?php echo str_replace('{page}', $totalPages, $paginationBaseUrl); ?>#products"><?php echo $totalPages; ?></a>
                        <?php endif; ?>

                        <?php if ($currentPage < $totalPages): ?>
                            <a href="<?php echo str_replace('{page}', $currentPage + 1, $paginationBaseUrl); ?>#products">Next →</a>
                        <?php else: ?>
                            <span class="disabled">Next →</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ============================================
    FEATURES / WHY CHOOSE US
    ============================================ -->
    <section class="section" id="about">
        <div class="container">
            <div class="section-header">
                <span class="section-label">Why Choose Us</span>
                <h2>The Premium Living Difference</h2>
                <p>We combine traditional craftsmanship with modern design to create furniture that elevates your home.</p>
            </div>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">🌳</div>
                    <h3>Premium Materials</h3>
                    <p>Solid oak, walnut veneer, genuine leather, and tempered glass — only the finest materials make the cut.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🔨</div>
                    <h3>Expert Craftsmanship</h3>
                    <p>Each piece is meticulously crafted by skilled artisans with decades of experience in furniture making.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🚚</div>
                    <h3>White-Glove Delivery</h3>
                    <p>Professional delivery and setup at your doorstep. We handle everything so you don't have to lift a finger.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🛡️</div>
                    <h3>5-Year Warranty</h3>
                    <p>Every piece comes with our comprehensive warranty. We stand behind the quality of our furniture.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================================
    ABOUT SECTION
    ============================================ -->
    <section class="section" style="background: var(--white);">
        <div class="container">
            <div class="about-grid">
                <div class="about-visual">
                    🏠
                </div>
                <div class="about-text">
                    <span class="section-label">About Us</span>
                    <h2>Heritage of Excellence Since 1998</h2>
                    <p>
                        Premium Living Furniture Co. has been Hong Kong's trusted name in quality furniture
                        for over 25 years. What started as a small workshop in Kowloon has grown into one of
                        the city's most respected furniture brands.
                    </p>
                    <p>
                        We believe furniture is more than just functional — it's an expression of your
                        personal style and a foundation for the memories you'll create at home.
                    </p>
                    <div class="about-stats">
                        <div class="about-stat">
                            <div class="num">25+</div>
                            <div class="lbl">Years Experience</div>
                        </div>
                        <div class="about-stat">
                            <div class="num">500+</div>
                            <div class="lbl">Designs Created</div>
                        </div>
                        <div class="about-stat">
                            <div class="num">50+</div>
                            <div class="lbl">Skilled Artisans</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================================
    CTA SECTION
    ============================================ -->
    <section class="cta-section" id="contact">
        <div class="container">
            <div class="cta-content">
                <h2>Ready to Transform Your Space?</h2>
                <p>
                    Browse our collection, place your order, and let us bring premium furniture
                    directly to your home with our signature white-glove delivery service.
                </p>
                <?php if (isLoggedIn() && hasRole('customer')): ?>
                    <a href="#products" class="btn btn-accent btn-lg">🛒 Start Shopping Now</a>
                <?php else: ?>
                    <button class="btn btn-accent btn-lg" onclick="openLoginModal()">✨ Get Started Today</button>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- ============================================
    FOOTER
    ============================================ -->
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-brand">
                    <div class="footer-logo">
                        <div class="fl-icon">🪑</div>
                        <h3>Premium Living</h3>
                    </div>
                    <p>
                        Hong Kong's premier furniture brand since 1998. Quality craftsmanship,
                        premium materials, and timeless designs for every home.
                    </p>
                </div>
                <div>
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="#home">Home</a></li>
                        <li><a href="#products">Products</a></li>
                        <li><a href="#about">About Us</a></li>
                        <li><a href="customer/contact_us.php">Contact</a></li>
                    </ul>
                </div>
                <div>
                    <h4>Categories</h4>
                    <ul>
                        <li><a href="#products" onclick="filterByCategory('all')">All Categories</a></li>
                        <?php foreach ($allCategories as $cat): ?>
                            <li><a href="#products" onclick="filterByCategory('<?php echo htmlspecialchars($cat, ENT_QUOTES); ?>')"><?php echo htmlspecialchars($cat); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div>
                    <h4>Contact Us</h4>
                    <ul>
                        <li>📍 15 Canton Road, Tsim Sha Tsui</li>
                        <li>📞 852-9123-4567</li>
                        <li>✉️ info@premiumliving.com</li>
                        <li>🕐 Mon-Fri: 10AM–8PM</li>
                        <li>🕐 Sat: 10AM–6PM</li>
                        <li>🕐 Sun: 11AM–5PM</li>
                        <li>🕐 PH: 11AM–4PM</li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> Premium Living Furniture Co. Ltd. All rights reserved. | Centralized Management System</p>
            </div>
        </div>
    </footer>

    <!-- ============================================
    LOGIN MODAL
    ============================================ -->
    <div class="modal" id="loginModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Welcome Back</h2>
                <button class="modal-close" onclick="closeLoginModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="login-brand">
                    <span class="lb-icon">🪑</span>
                    <p>Sign in to your Premium Living account</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST" action="index.php#loginModal" id="loginForm">
                    <!-- Role Tabs -->
                    <div class="role-tabs" id="roleTabs">
                        <button type="button" class="role-tab customer active" data-role="customer" onclick="switchRole('customer')">
                            👤 Customer
                        </button>
                        <button type="button" class="role-tab staff" data-role="staff" onclick="switchRole('staff')">
                            👔 Staff
                        </button>
                    </div>
                    <input type="hidden" name="role" id="loginRole" value="customer">

                    <div class="form-group">
                        <label for="user_id">ID Number</label>
                        <input type="number" name="user_id" id="user_id" class="form-control"
                               placeholder="Enter your Customer ID (e.g., 1001)"
                               required autofocus>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" name="password" id="password" class="form-control"
                               placeholder="Enter your password" required>
                    </div>

                    <div class="form-group" style="display: flex; align-items: center; gap: var(--space-2);">
                        <input type="checkbox" name="remember_me" id="remember_me" value="1" style="width: 16px; height: 16px;">
                        <label for="remember_me" style="margin-bottom: 0; font-weight: 500; cursor: pointer;">Remember me for 30 days</label>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block btn-lg">🔐 Sign In</button>
                </form>

                <!-- Forgot Password + Register Links -->
                <div style="text-align: center; margin-top: var(--space-3);">
                    <a href="customer/forgot_password.php" style="font-size: var(--font-size-sm); color: var(--gray-500);">Forgot your password?</a>
                </div>

                <!-- Register Link -->
                <div style="text-align: center; margin-top: var(--space-4); padding-top: var(--space-4); border-top: 1px solid var(--gray-200);">
                    <p style="color: var(--gray-500); font-size: var(--font-size-sm); margin-bottom: var(--space-2);">
                        Don't have an account?
                    </p>
                    <a href="customer/register.php" class="btn btn-outline-accent btn-block">
                        ✨ Create New Account
                    </a>
                </div>

                <!-- Test Credentials -->
                <div class="test-creds">
                    <h4>📋 Test Credentials</h4>
                    <div class="cred-row">
                        <span><span class="cred-badge customer">Customer</span></span>
                        <span>ID: <strong>1001-1005</strong></span>
                        <span>PW: <strong>password123</strong></span>
                    </div>
                    <div class="cred-row">
                        <span><span class="cred-badge customer">Customer</span></span>
                        <span>ID: <strong>1002</strong></span>
                        <span>PW: <strong>password456</strong></span>
                    </div>
                    <div class="cred-row">
                        <span><span class="cred-badge staff">Staff</span></span>
                        <span>ID: <strong>1</strong></span>
                        <span>PW: <strong>admin123</strong></span>
                    </div>
                    <div class="cred-row">
                        <span><span class="cred-badge staff">Staff</span></span>
                        <span>ID: <strong>2</strong></span>
                        <span>PW: <strong>staff456</strong></span>
                    </div>
                    <div class="cred-row">
                        <span><span class="cred-badge staff">Staff</span></span>
                        <span>ID: <strong>3</strong></span>
                        <span>PW: <strong>inventory789</strong></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cart Modal -->
    <div class="modal" id="cartModal">
        <div class="modal-content" style="max-width: 650px;">
            <div class="modal-header">
                <h2>🛒 Your Shopping Cart</h2>
                <button class="modal-close" onclick="closeCartModal()">&times;</button>
            </div>
            <div class="modal-body" id="cartBody">
                <p style="text-align:center;color:var(--gray-500);">Loading cart...</p>
            </div>
        </div>
    </div>

    <!-- Product Details Modal -->
    <div class="modal" id="productModal">
        <div class="modal-content" style="max-width: 560px;">
            <div class="modal-header">
                <h2 id="pmTitle">Product Details</h2>
                <button class="modal-close" onclick="closeProductModal()">&times;</button>
            </div>
            <div class="modal-body" id="pmBody">
                <p style="text-align:center;color:var(--gray-500);">Loading...</p>
            </div>
        </div>
    </div>

    <!-- Back to Top Button -->
    <button class="back-to-top" id="backToTop" onclick="scrollToTop()" title="Back to top">↑</button>

    <!-- ============================================
    JAVASCRIPT
    ============================================ -->
    <script src="assets/js/usability.js"></script>
    <script src="assets/js/password-toggle.js"></script>
    <script>
        // ── Mobile Nav Toggle ──────────────────────
        const navToggle = document.getElementById('navToggle');
        const navLinks = document.getElementById('navLinks');

        navToggle.addEventListener('click', () => {
            navLinks.classList.toggle('open');
        });

        // Close mobile nav when clicking a link
        navLinks.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => {
                navLinks.classList.remove('open');
            });
        });

        // ── Navbar Scroll Effect ──────────────────
        const navbar = document.getElementById('navbar');
        window.addEventListener('scroll', () => {
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }

            // Active nav link based on scroll position
            const sections = ['home', 'products', 'about', 'contact'];
            let current = 'home';
            sections.forEach(id => {
                const el = document.getElementById(id);
                if (el && window.scrollY >= el.offsetTop - 100) {
                    current = id;
                }
            });
            navLinks.querySelectorAll('a').forEach(a => {
                a.classList.remove('active');
                if (a.getAttribute('href') === '#' + current) {
                    a.classList.add('active');
                }
            });
        });

        // ── Back to Top ───────────────────────────
        const backToTop = document.getElementById('backToTop');
        window.addEventListener('scroll', () => {
            if (window.scrollY > 500) {
                backToTop.classList.add('visible');
            } else {
                backToTop.classList.remove('visible');
            }
        });

        function scrollToTop() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // ── Login Modal ───────────────────────────
        function openLoginModal() {
            document.getElementById('loginModal').classList.add('show');
            // Reset to customer tab
            switchRole('customer');
            document.getElementById('user_id').focus();
        }

        function closeLoginModal() {
            document.getElementById('loginModal').classList.remove('show');
        }

        function switchRole(role) {
            document.getElementById('loginRole').value = role;
            const tabs = document.querySelectorAll('.role-tab');
            tabs.forEach(tab => {
                tab.classList.remove('active');
                if (tab.getAttribute('data-role') === role) {
                    tab.classList.add('active');
                }
            });
            const userIdInput = document.getElementById('user_id');
            if (role === 'customer') {
                userIdInput.placeholder = 'Enter Customer ID (e.g., 1001)';
            } else {
                userIdInput.placeholder = 'Enter Staff ID (e.g., 1)';
            }
        }

        // ── Product Modal ─────────────────────────
        let currentProductData = null; // holds fetched product for cart use

        function openProductModal(productId) {
            const modal = document.getElementById('productModal');
            modal.classList.add('show');
            document.getElementById('pmBody').innerHTML = '<p style="text-align:center;color:var(--gray-500);">Loading...</p>';

            fetch('customer/get_product_details.php?id=' + productId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        currentProductData = data;
                        const p = data.product;
                        const isLoggedIn = <?php echo isLoggedIn() && hasRole('customer') ? 'true' : 'false'; ?>;
                        const maxStock = parseInt(p.StockQuantity);
                        document.getElementById('pmTitle').textContent = p.FurnitureName;
                        const baseImgPath = 'assets/images/furniture/';

                        // Build image gallery or fallback to emoji
                        let imageGalleryHTML = '';
                        if (data.images && data.images.length > 0) {
                            const mainImg = data.images.find(img => img.IsPrimary == 1) || data.images[0];
                            const mainSrc = baseImgPath + mainImg.ImagePath.replace(/ /g, '%20');
                            imageGalleryHTML = `
                                <div class="product-gallery">
                                    <div class="main-image">
                                        <img id="pmMainImage" src="${mainSrc}" alt="${p.FurnitureName}" onerror="this.style.display='none';document.getElementById('pmGalleryFallback').style.display='flex';">
                                        <span id="pmGalleryFallback" class="gallery-fallback" style="display:none;font-size:5rem;">${getCategoryIcon(p.Category)}</span>
                                    </div>
                                    <div class="thumbnails">
                                        ${data.images.map((img, idx) => {
                                            const thumbSrc = baseImgPath + img.ImagePath.replace(/ /g, '%20');
                                            const activeClass = (img.IsPrimary == 1 || (!data.images.some(i => i.IsPrimary == 1) && idx === 0)) ? ' active' : '';
                                            return `<button class="thumb${activeClass}" onclick="event.stopPropagation(); switchGalleryImage('${thumbSrc}', this)" title="View ${idx + 1}"><img src="${thumbSrc}" alt="View ${idx + 1}" onerror="this.parentElement.style.display='none';"></button>`;
                                        }).join('')}
                                    </div>
                                </div>`;
                        } else {
                            const icon = getCategoryIcon(p.Category);
                            imageGalleryHTML = `<div style="text-align:center;font-size:5rem;margin-bottom:0.5rem;">${icon}</div>`;
                        }

                        let html = `
                            <div id="pmMessage"></div>
                            ${imageGalleryHTML}
                            <h4 style="text-align:center;font-size:1.2rem;margin:12px 0;">${p.FurnitureName}</h4>
                            <p style="margin-bottom:8px;"><strong>📋 Description:</strong><br>${p.FurnitureDescription}</p>
                            <p style="margin-bottom:8px;"><strong>📂 Category:</strong> ${p.Category}</p>
                            <p style="margin-bottom:8px;"><strong>💰 Price:</strong> <span style="color:var(--primary);font-size:1.4rem;font-weight:700;" id="pmPrice">$${parseFloat(p.Price).toFixed(2)}</span></p>
                            <p style="margin-bottom:8px;"><strong>📦 Stock:</strong> ${p.StockQuantity > 0 ? '<span style="color:var(--success);">' + p.StockQuantity + ' units available</span>' : '<span style="color:var(--danger);">SOLD OUT</span>'}</p>
                        `;
                        // Color Selector
                        if (data.colors && data.colors.length > 0) {
                            html += '<div style="margin-top:12px;"><strong>🎨 Color:</strong></div>';
                            html += '<div class="custom-options" id="pmColors">';
                            data.colors.forEach((c, ci) => {
                                const priceLabel = parseFloat(c.AdditionalPrice) > 0 ? ' <small style="color:var(--accent);">+$' + parseFloat(c.AdditionalPrice).toFixed(2) + '</small>' : '';
                                html += `<button class="custom-option color-option${ci === 0 ? ' selected' : ''}" data-color="${c.ColorName}" data-color-price="${c.AdditionalPrice}" onclick="selectColor(this, event)" style="display:inline-flex;align-items:center;gap:8px;padding:8px 14px;border:2px solid var(--gray-200);border-radius:20px;background:var(--white);cursor:pointer;font-size:0.85rem;transition:all 0.2s;">
                                    <span style="display:inline-block;width:18px;height:18px;border-radius:50%;background:${c.ColorHex};border:1px solid #ddd;flex-shrink:0;"></span>
                                    ${c.ColorName}${priceLabel}
                                </button>`;
                            });
                            html += '</div>';
                        }
                        // Size Selector
                        if (data.sizes && data.sizes.length > 0) {
                            html += '<div style="margin-top:12px;"><strong>📏 Size:</strong></div>';
                            html += '<div class="custom-options" id="pmSizes">';
                            data.sizes.forEach((s, si) => {
                                const dimLabel = s.Dimensions ? ' <small style="color:var(--gray-500);">(' + s.Dimensions + ')</small>' : '';
                                const priceLabel = parseFloat(s.AdditionalPrice) > 0 ? ' <small style="color:var(--accent);">+$' + parseFloat(s.AdditionalPrice).toFixed(2) + '</small>' : '';
                                html += `<button class="custom-option size-option${si === 0 ? ' selected' : ''}" data-size="${s.SizeName}" data-size-price="${s.AdditionalPrice}" onclick="selectSize(this, event)" style="display:inline-flex;align-items:center;gap:8px;padding:8px 14px;border:2px solid var(--gray-200);border-radius:20px;background:var(--white);cursor:pointer;font-size:0.85rem;transition:all 0.2s;">
                                    ${s.SizeName}${dimLabel}${priceLabel}
                                </button>`;
                            });
                            html += '</div>';
                        }
                        // Accessories
                        if (data.accessories && data.accessories.length > 0) {
                            html += '<div style="margin-top:12px;"><strong>🔧 Accessories:</strong></div>';
                            html += '<div id="pmAccessories" style="display:flex;flex-direction:column;gap:6px;">';
                            data.accessories.forEach(a => {
                                html += `<label class="accessory-option" style="display:flex;align-items:center;gap:10px;padding:8px 12px;border:1px solid var(--gray-200);border-radius:10px;cursor:pointer;font-size:0.85rem;transition:all 0.2s;">
                                    <input type="checkbox" data-accessory-id="${a.AccessoryID}" data-accessory-name="${a.AccessoryName}" data-accessory-price="${a.AdditionalPrice}" onchange="updatePmTotal()" style="width:16px;height:16px;accent-color:var(--primary);cursor:pointer;">
                                    <div style="flex:1;"><strong>${a.AccessoryName}</strong>${a.Description ? '<br><small style=\"color:var(--gray-500);\">' + a.Description + '</small>' : ''}</div>
                                    <span style="color:var(--accent);font-weight:600;white-space:nowrap;">+$${parseFloat(a.AdditionalPrice).toFixed(2)}</span>
                                </label>`;
                            });
                            html += '</div>';
                        }
                        // Materials
                        if (data.materials && data.materials.length > 0) {
                            html += '<p style="margin-top:10px;"><strong>🧱 Materials Used:</strong></p><ul style="margin-left:1.5rem;margin-top:4px;">';
                            data.materials.forEach(m => {
                                html += `<li>${m.MaterialName}: ${m.MaterialQuantity} ${m.Unit}</li>`;
                            });
                            html += '</ul>';
                        }
                        // Quantity + Add to Cart
                        if (maxStock > 0) {
                            html += `
                                <div style="margin-top:20px;padding-top:16px;border-top:2px solid var(--gray-100);">
                                    <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
                                        <strong>🔢 Quantity:</strong>
                                        <button onclick="changeQty(-1)" style="width:36px;height:36px;border:2px solid var(--gray-300);border-radius:50%;background:var(--white);font-size:1.2rem;cursor:pointer;font-weight:700;line-height:1;">−</button>
                                        <input type="number" id="pmQty" value="1" min="1" max="${maxStock}" onchange="updatePmTotal()" style="width:60px;text-align:center;padding:6px;border:2px solid var(--gray-300);border-radius:8px;font-size:1rem;font-weight:600;">
                                        <button onclick="changeQty(1)" style="width:36px;height:36px;border:2px solid var(--gray-300);border-radius:50%;background:var(--white);font-size:1.2rem;cursor:pointer;font-weight:700;line-height:1;">+</button>
                                        <span style="margin-left:auto;font-weight:700;color:var(--primary);font-size:1.2rem;" id="pmTotal">$${parseFloat(p.Price).toFixed(2)}</span>
                                    </div>
                                    ${isLoggedIn
                                        ? `<button class="btn btn-accent btn-block btn-lg" onclick="addToCart(${p.FurnitureID}, ${maxStock})" id="pmOrderBtn">🛒 Add to Cart</button>`
                                        : `<button class="btn btn-accent btn-block btn-lg" onclick="closeProductModal(); openLoginModal();">🔐 Sign In to Order</button>`
                                    }
                                </div>`;
                        }
                        document.getElementById('pmBody').innerHTML = html;
                    } else {
                        document.getElementById('pmBody').innerHTML = '<p style="text-align:center;color:var(--danger);">Failed to load product details.</p>';
                    }
                })
                .catch(() => {
                    document.getElementById('pmBody').innerHTML = '<p style="text-align:center;color:var(--danger);">Error loading details.</p>';
                });
        }

        function changeQty(delta) {
            const input = document.getElementById('pmQty');
            if (!input) return;
            let val = parseInt(input.value) + delta;
            const max = parseInt(input.max);
            if (val < 1) val = 1;
            if (val > max) val = max;
            input.value = val;
            updatePmTotal();
        }

        function getCustomizationCost() {
            let extra = 0;
            const selColor = document.querySelector('.color-option.selected');
            if (selColor) extra += parseFloat(selColor.getAttribute('data-color-price')) || 0;
            const selSize = document.querySelector('.size-option.selected');
            if (selSize) extra += parseFloat(selSize.getAttribute('data-size-price')) || 0;
            document.querySelectorAll('#pmAccessories input[type=checkbox]:checked').forEach(cb => {
                extra += parseFloat(cb.getAttribute('data-accessory-price')) || 0;
            });
            return extra;
        }

        function updatePmTotal() {
            const input = document.getElementById('pmQty');
            const totalEl = document.getElementById('pmTotal');
            if (!input || !totalEl || !currentProductData) return;
            const price = parseFloat(currentProductData.product.Price);
            const qty = parseInt(input.value) || 1;
            const extra = getCustomizationCost();
            totalEl.textContent = '$' + ((price + extra) * qty).toFixed(2);
        }

        function selectColor(btn, ev) {
            if (ev) ev.stopPropagation();
            document.querySelectorAll('.color-option').forEach(b => b.classList.remove('selected'));
            btn.classList.add('selected');
            updatePmTotal();
        }

        function selectSize(btn, ev) {
            if (ev) ev.stopPropagation();
            document.querySelectorAll('.size-option').forEach(b => b.classList.remove('selected'));
            btn.classList.add('selected');
            updatePmTotal();
        }

        function addToCart(furnitureId, maxStock) {
            const qtyInput = document.getElementById('pmQty');
            const qty = parseInt(qtyInput?.value) || 1;
            const btn = document.getElementById('pmOrderBtn');
            const msgEl = document.getElementById('pmMessage');

            if (qty > maxStock) {
                msgEl.innerHTML = '<div class="alert alert-danger">Quantity exceeds available stock (' + maxStock + ').</div>';
                return;
            }

            if (btn) { btn.disabled = true; btn.textContent = '⏳ Adding...'; }

            // Gather customization selections
            const selColor = document.querySelector('.color-option.selected');
            const selSize = document.querySelector('.size-option.selected');
            const selAccessories = [];
            document.querySelectorAll('#pmAccessories input[type=checkbox]:checked').forEach(cb => {
                selAccessories.push({
                    id: cb.getAttribute('data-accessory-id'),
                    name: cb.getAttribute('data-accessory-name'),
                    price: cb.getAttribute('data-accessory-price')
                });
            });

            const formData = new FormData();
            formData.append('action', 'add');
            formData.append('furniture_id', furnitureId);
            formData.append('quantity', qty);
            if (selColor) formData.append('selected_color', selColor.getAttribute('data-color'));
            if (selSize) formData.append('selected_size', selSize.getAttribute('data-size'));
            if (selAccessories.length > 0) formData.append('selected_accessories', JSON.stringify(selAccessories));
            formData.append('customization_cost', getCustomizationCost());

            fetch('customer/cart.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(result => {
                    if (result.success) {
                        msgEl.innerHTML = '<div class="alert alert-success">✅ ' + result.message + '</div>';
                        if (btn) { btn.textContent = '✅ Added!'; btn.style.background = 'var(--success)'; }
                        updateCartBadge(result.cart_count);
                        setTimeout(() => { closeProductModal(); }, 1000);
                    } else {
                        msgEl.innerHTML = '<div class="alert alert-danger">❌ ' + result.message + '</div>';
                        if (btn) { btn.disabled = false; btn.textContent = '🛒 Add to Cart'; }
                    }
                })
                .catch(() => {
                    msgEl.innerHTML = '<div class="alert alert-danger">❌ Network error. Please try again.</div>';
                    if (btn) { btn.disabled = false; btn.textContent = '🛒 Add to Cart'; }
                });
        }

        function updateCartBadge(count) {
            const el = document.getElementById('cartCount');
            if (el) el.textContent = count;
        }

        // ── Cart Modal ────────────────────────────
        function openCartModal() {
            const modal = document.getElementById('cartModal');
            const body = document.getElementById('cartBody');
            modal.classList.add('show');
            body.innerHTML = '<p style="text-align:center;color:var(--gray-500);">Loading cart...</p>';

            fetch('customer/cart.php?action=get')
                .then(r => r.json())
                .then(data => {
                    if (!data.items || data.items.length === 0) {
                        body.innerHTML = '<p style="text-align:center;padding:40px;font-size:1.2rem;">🛒 Your cart is empty.<br><br><a href="#products" class="btn btn-primary" onclick="closeCartModal()">Browse Products</a></p>';
                        return;
                    }
                    let html = '';
                    data.items.forEach(item => {
                        let cartImgHTML = '';
                        if (item.image_path) {
                            const cartImgSrc = 'assets/images/furniture/' + item.image_path.replace(/ /g, '%20');
                            cartImgHTML = `<img src="${cartImgSrc}" style="width:50px;height:50px;object-fit:cover;border-radius:6px;flex-shrink:0;" alt="${item.name}" onerror="this.style.display='none';this.nextElementSibling.style.display='inline';">`;
                            cartImgHTML += `<span style="font-size:2.5rem;min-width:50px;text-align:center;display:none;">${getCategoryIcon(item.category)}</span>`;
                        } else {
                            cartImgHTML = `<span style="font-size:2.5rem;min-width:50px;text-align:center;">${getCategoryIcon(item.category)}</span>`;
                        }
                        // Build customization label
                        let customLabel = '';
                        if (item.color) customLabel += '<span style="display:inline-block;margin-right:6px;background:var(--gray-100);padding:1px 8px;border-radius:10px;font-size:0.75rem;">🎨 ' + item.color + '</span>';
                        if (item.size) customLabel += '<span style="display:inline-block;margin-right:6px;background:var(--gray-100);padding:1px 8px;border-radius:10px;font-size:0.75rem;">📏 ' + item.size + '</span>';
                        if (item.accessories && item.accessories.length > 0) {
                            item.accessories.forEach(a => {
                                customLabel += '<span style="display:inline-block;margin-right:6px;background:var(--warning-bg,#fff3cd);padding:1px 8px;border-radius:10px;font-size:0.75rem;">🔧 ' + a.name + '</span>';
                            });
                        }
                        html += `
                            <div style="display:flex;align-items:center;gap:16px;padding:12px 0;border-bottom:1px solid var(--gray-100);">
                                ${cartImgHTML}
                                <div style="flex:1;">
                                    <strong>${item.name}</strong>
                                    <div style="color:var(--gray-500);font-size:0.85rem;">$${parseFloat(item.price).toFixed(2)} each</div>
                                    ${customLabel ? '<div style="margin-top:3px;">' + customLabel + '</div>' : ''}
                                </div>
                                <div style="display:flex;align-items:center;gap:6px;">
                                    <button onclick="cartUpdateQty('${item.cart_key}', ${item.quantity - 1})" style="width:28px;height:28px;border-radius:50%;border:1px solid var(--gray-300);cursor:pointer;background:var(--white);">-</button>
                                    <span style="min-width:30px;text-align:center;font-weight:600;">${item.quantity}</span>
                                    <button onclick="cartUpdateQty('${item.cart_key}', ${item.quantity + 1})" style="width:28px;height:28px;border-radius:50%;border:1px solid var(--gray-300);cursor:pointer;background:var(--white);">+</button>
                                </div>
                                <strong style="min-width:80px;text-align:right;">$${item.line_total.toFixed(2)}</strong>
                                <button onclick="cartRemove('${item.cart_key}')" style="background:none;border:none;cursor:pointer;font-size:1.2rem;color:var(--danger);" title="Remove">🗑️</button>
                            </div>`;
                    });
                    html += `
                        <div style="display:flex;justify-content:space-between;align-items:center;padding:16px 0;font-size:1.3rem;font-weight:700;">
                            <span>Total:</span>
                            <span style="color:var(--primary);">$${data.subtotal.toFixed(2)}</span>
                        </div>
                        <div id="cartMsg"></div>
                        <div style="display:flex;gap:12px;">
                            <button class="btn btn-outline" onclick="cartClear()" style="flex:1;">🗑️ Clear Cart</button>
                            <button class="btn btn-accent btn-lg" onclick="showPaymentSelection(${data.subtotal.toFixed(2)})" style="flex:2;">💰 Buy Now — Place All Orders</button>
                        </div>
                    `;
                    body.innerHTML = html;
                })
                .catch(() => { body.innerHTML = '<p style="text-align:center;color:var(--danger);">Error loading cart.</p>'; });
        }

        function closeCartModal() {
            document.getElementById('cartModal').classList.remove('show');
        }

        function cartUpdateQty(cartKey, qty) {
            const formData = new FormData();
            formData.append('action', 'update');
            formData.append('cart_key', cartKey);
            formData.append('quantity', qty);
            fetch('customer/cart.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => { updateCartBadge(data.cart_count); openCartModal(); });
        }

        function cartRemove(cartKey) {
            const formData = new FormData();
            formData.append('action', 'remove');
            formData.append('cart_key', cartKey);
            fetch('customer/cart.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => { updateCartBadge(data.cart_count); openCartModal(); });
        }

        function cartClear() {
            fetch('customer/cart.php?action=clear')
                .then(r => r.json())
                .then(data => { updateCartBadge(0); openCartModal(); });
        }

        // Cart view state — store the last fetched cart data
        let cartDataCache = null;

        function showPaymentSelection(subtotal) {
            const body = document.getElementById('cartBody');
            body.innerHTML = `
                <div style="margin-bottom:20px;">
                    <button class="btn btn-outline btn-sm" onclick="showCartView()" style="font-size:0.85rem;">← Back to Cart</button>
                </div>
                <h3 style="text-align:center;margin-bottom:6px;">💳 Select Payment Method</h3>
                <p style="text-align:center;color:var(--gray-500);margin-bottom:20px;">
                    Order Total: <strong style="color:var(--primary);font-size:1.2rem;">$${parseFloat(subtotal).toFixed(2)}</strong>
                </p>
                <div id="paymentMsg"></div>

                <div style="display:flex;flex-direction:column;gap:12px;margin-bottom:12px;">
                    <label class="payment-option" id="payOptCC" onclick="selectPayment('credit_card')" style="display:flex;align-items:center;gap:14px;padding:14px 18px;border:3px solid var(--gray-200);border-radius:12px;cursor:pointer;transition:all 0.2s;">
                        <input type="radio" name="payment_method" value="credit_card" style="width:18px;height:18px;accent-color:var(--primary);">
                        <span style="font-size:2rem;">💳</span>
                        <div style="flex:1;">
                            <strong style="display:block;font-size:1.05rem;">Credit Card</strong>
                            <span style="font-size:0.8rem;color:var(--gray-500);">Pay now — instant processing</span>
                        </div>
                        <span style="background:var(--success);color:#fff;padding:2px 10px;border-radius:12px;font-size:0.75rem;font-weight:600;">Instant</span>
                    </label>

                    <!-- Credit Card Details (shown only when credit_card selected) -->
                    <div id="creditCardFields" style="display:none; background:var(--gray-50); padding:var(--space-5); border-radius:var(--radius-lg); border:2px solid var(--primary); margin-top:var(--space-1);">
                        <h4 style="margin-bottom:var(--space-4);">💳 Credit Card Details</h4>
                        <div id="ccErrors" style="margin-bottom:var(--space-3);"></div>
                        <div class="form-group">
                            <label for="cc_number" class="required">Card Number</label>
                            <input type="text" id="cc_number" class="form-control" placeholder="1234 5678 9012 3456" maxlength="19" inputmode="numeric" autocomplete="cc-number">
                            <div class="invalid-feedback" id="cc_number_error" style="color:var(--danger);font-size:var(--font-size-xs);margin-top:2px;display:none;"></div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="cc_expiry" class="required">Expiry Date</label>
                                <input type="text" id="cc_expiry" class="form-control" placeholder="MM/YY" maxlength="5" inputmode="numeric" autocomplete="cc-exp">
                                <div class="invalid-feedback" id="cc_expiry_error" style="color:var(--danger);font-size:var(--font-size-xs);margin-top:2px;display:none;"></div>
                            </div>
                            <div class="form-group">
                                <label for="cc_cvv" class="required">CVV</label>
                                <input type="text" id="cc_cvv" class="form-control" placeholder="123" maxlength="4" inputmode="numeric" autocomplete="cc-csc">
                                <div class="invalid-feedback" id="cc_cvv_error" style="color:var(--danger);font-size:var(--font-size-xs);margin-top:2px;display:none;"></div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="cc_name" class="required">Cardholder Name</label>
                            <input type="text" id="cc_name" class="form-control" placeholder="Name as it appears on card" maxlength="100" autocomplete="cc-name">
                            <div class="invalid-feedback" id="cc_name_error" style="color:var(--danger);font-size:var(--font-size-xs);margin-top:2px;display:none;"></div>
                        </div>
                    </div>

                    <label class="payment-option" id="payOptBT" onclick="selectPayment('bank_transfer')" style="display:flex;align-items:center;gap:14px;padding:14px 18px;border:3px solid var(--gray-200);border-radius:12px;cursor:pointer;transition:all 0.2s;">
                        <input type="radio" name="payment_method" value="bank_transfer" style="width:18px;height:18px;accent-color:var(--primary);">
                        <span style="font-size:2rem;">🏦</span>
                        <div style="flex:1;">
                            <strong style="display:block;font-size:1.05rem;">Bank Transfer</strong>
                            <span style="font-size:0.8rem;color:var(--gray-500);">Pay via bank transfer — staff confirms receipt</span>
                        </div>
                        <span style="background:var(--warning);color:#000;padding:2px 10px;border-radius:12px;font-size:0.75rem;font-weight:600;">Manual</span>
                    </label>

                    <!-- Bank Transfer Details (shown only when bank_transfer selected) -->
                    <div id="bankTransferFields" style="display:none; background:var(--gray-50); padding:var(--space-5); border-radius:var(--radius-lg); border:2px solid var(--warning); margin-top:var(--space-1);">
                        <h4 style="margin-bottom:var(--space-3);">🏦 Bank Account Details</h4>
                        <p style="color:var(--gray-500);font-size:var(--font-size-sm);margin-bottom:var(--space-4);">
                            Enter your bank account details for payment verification. Our staff will confirm once payment is received.
                        </p>
                        <div id="btErrors" style="margin-bottom:var(--space-3);"></div>
                        <div class="form-group">
                            <label for="bt_bank_name" class="required">Bank Name</label>
                            <select id="bt_bank_name" class="form-control">
                                <option value="">— Select your bank —</option>
                                <option value="HSBC">HSBC</option>
                                <option value="Hang Seng Bank">Hang Seng Bank</option>
                                <option value="Bank of China (Hong Kong)">Bank of China (Hong Kong)</option>
                                <option value="Standard Chartered">Standard Chartered</option>
                                <option value="Bank of East Asia">Bank of East Asia</option>
                                <option value="Citibank">Citibank</option>
                                <option value="DBS Bank">DBS Bank</option>
                                <option value="Other">Other</option>
                            </select>
                            <div class="invalid-feedback" id="bt_bank_name_error" style="color:var(--danger);font-size:var(--font-size-xs);margin-top:2px;display:none;"></div>
                        </div>
                        <div class="form-group">
                            <label for="bt_account_name" class="required">Account Holder Name</label>
                            <input type="text" id="bt_account_name" class="form-control" placeholder="Name on the bank account" maxlength="100">
                            <div class="invalid-feedback" id="bt_account_name_error" style="color:var(--danger);font-size:var(--font-size-xs);margin-top:2px;display:none;"></div>
                        </div>
                        <div class="form-group">
                            <label for="bt_account_number" class="required">Account Number</label>
                            <input type="text" id="bt_account_number" class="form-control" placeholder="Your bank account number" maxlength="20" inputmode="numeric">
                            <div class="invalid-feedback" id="bt_account_number_error" style="color:var(--danger);font-size:var(--font-size-xs);margin-top:2px;display:none;"></div>
                        </div>
                    </div>

                    <label class="payment-option" id="payOptCOD" onclick="selectPayment('cod')" style="display:flex;align-items:center;gap:14px;padding:14px 18px;border:3px solid var(--gray-200);border-radius:12px;cursor:pointer;transition:all 0.2s;">
                        <input type="radio" name="payment_method" value="cod" style="width:18px;height:18px;accent-color:var(--primary);">
                        <span style="font-size:2rem;">💵</span>
                        <div style="flex:1;">
                            <strong style="display:block;font-size:1.05rem;">Cash on Delivery</strong>
                            <span style="font-size:0.8rem;color:var(--gray-500);">Pay when your order arrives</span>
                        </div>
                        <span style="background:var(--gray-400);color:#fff;padding:2px 10px;border-radius:12px;font-size:0.75rem;font-weight:600;">On Arrival</span>
                    </label>
                </div>

                <button class="btn btn-accent btn-block btn-lg" onclick="cartCheckout()" id="confirmPaymentBtn" disabled style="opacity:0.5;cursor:not-allowed;">
                    🔒 Confirm & Place Order
                </button>
            `;
        }

        let selectedPaymentMethod = null;

        function selectPayment(method) {
            selectedPaymentMethod = method;
            // Update visual selection for all options
            document.querySelectorAll('.payment-option').forEach(opt => {
                opt.style.borderColor = 'var(--gray-200)';
                opt.style.background = 'var(--white)';
            });
            const optMap = { credit_card: 'payOptCC', bank_transfer: 'payOptBT', cod: 'payOptCOD' };
            const selectedEl = document.getElementById(optMap[method]);
            if (selectedEl) {
                selectedEl.style.borderColor = 'var(--primary)';
                selectedEl.style.background = 'var(--primary-light, #e8f4fd)';
                selectedEl.querySelector('input[type=radio]').checked = true;
            }
            // Show/hide payment-specific fields
            const ccFields = document.getElementById('creditCardFields');
            const btFields = document.getElementById('bankTransferFields');
            if (ccFields) ccFields.style.display = (method === 'credit_card') ? '' : 'none';
            if (btFields) btFields.style.display = (method === 'bank_transfer') ? '' : 'none';

            // Enable the confirm button
            const btn = document.getElementById('confirmPaymentBtn');
            if (btn) {
                btn.disabled = false;
                btn.style.opacity = '1';
                btn.style.cursor = 'pointer';
            }
            // Update button text
            if (btn && method === 'credit_card') {
                btn.textContent = '💳 Pay Now';
            } else if (btn && method === 'bank_transfer') {
                btn.textContent = '🏦 Confirm Bank Transfer';
            } else if (btn) {
                btn.textContent = '🔒 Confirm & Place Order';
            }
        }

        function showCartView() {
            selectedPaymentMethod = null;
            openCartModal();
        }

        function cartCheckout() {
            // If no payment method selected yet, show payment selection
            if (!selectedPaymentMethod) {
                const msgEl = document.getElementById('paymentMsg');
                if (msgEl) {
                    msgEl.innerHTML = '<div class="alert alert-danger">⚠️ Please select a payment method first.</div>';
                }
                return;
            }

            // Validate payment-specific fields
            if (selectedPaymentMethod === 'credit_card') {
                if (!validateCreditCard()) return;
            }
            if (selectedPaymentMethod === 'bank_transfer') {
                if (!validateBankTransfer()) return;
            }

            const btn = document.getElementById('confirmPaymentBtn');
            if (btn) { btn.disabled = true; btn.textContent = '⏳ Processing your order...'; }

            const msgEl = document.getElementById('paymentMsg') || document.getElementById('cartMsg');
            if (msgEl) msgEl.innerHTML = '<div class="alert alert-info">⏳ Processing your order...</div>';

            // Build checkout payload
            const payload = new URLSearchParams({ action: 'checkout', payment_method: selectedPaymentMethod });
            if (selectedPaymentMethod === 'credit_card') {
                payload.append('cc_number', document.getElementById('cc_number').value.replace(/\s/g, ''));
                payload.append('cc_expiry', document.getElementById('cc_expiry').value);
                payload.append('cc_cvv', document.getElementById('cc_cvv').value);
                payload.append('cc_name', document.getElementById('cc_name').value);
            }
            if (selectedPaymentMethod === 'bank_transfer') {
                payload.append('bt_bank_name', document.getElementById('bt_bank_name').value);
                payload.append('bt_account_name', document.getElementById('bt_account_name').value);
                payload.append('bt_account_number', document.getElementById('bt_account_number').value);
            }

            fetch('customer/cart.php', {
                method: 'POST',
                body: payload
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        if (msgEl) msgEl.innerHTML = '<div class="alert alert-success">✅ ' + data.message + '</div>';
                        updateCartBadge(0);
                        setTimeout(() => { window.location.href = 'customer/view_orders.php'; }, 1200);
                    } else {
                        if (msgEl) msgEl.innerHTML = '<div class="alert alert-danger">❌ ' + data.message + '</div>';
                        if (btn) { btn.disabled = false; btn.textContent = '🔒 Confirm & Place Order'; }
                    }
                })
                .catch(() => {
                    if (msgEl) msgEl.innerHTML = '<div class="alert alert-danger">❌ Network error. Please try again.</div>';
                    if (btn) { btn.disabled = false; btn.textContent = '🔒 Confirm & Place Order'; }
                });
        }

        // ── Credit Card Validation ───────────────────
        function validateCreditCard() {
            const ccNumber = document.getElementById('cc_number');
            const ccExpiry = document.getElementById('cc_expiry');
            const ccCvv = document.getElementById('cc_cvv');
            const ccName = document.getElementById('cc_name');
            const errEl = document.getElementById('ccErrors');
            let valid = true;

            // Clear previous errors
            [ccNumber, ccExpiry, ccCvv, ccName].forEach(f => {
                f.style.borderColor = ''; f.classList.remove('is-invalid');
            });
            ['cc_number_error','cc_expiry_error','cc_cvv_error','cc_name_error'].forEach(id => {
                const el = document.getElementById(id);
                if (el) { el.textContent = ''; el.style.display = 'none'; }
            });
            if (errEl) errEl.innerHTML = '';

            function showCcErr(field, errId, msg) {
                field.style.borderColor = '#dc2626';
                field.classList.add('is-invalid');
                const el = document.getElementById(errId);
                if (el) { el.textContent = msg; el.style.display = 'block'; }
                valid = false;
            }

            // Card number: 16 digits
            const numRaw = ccNumber.value.replace(/\s/g, '');
            if (!/^\d{16}$/.test(numRaw)) {
                showCcErr(ccNumber, 'cc_number_error', 'Enter a valid 16-digit card number.');
            }

            // Expiry: MM/YY format, not expired
            const expiryMatch = ccExpiry.value.match(/^(0[1-9]|1[0-2])\/(\d{2})$/);
            if (!expiryMatch) {
                showCcErr(ccExpiry, 'cc_expiry_error', 'Enter a valid expiry date (MM/YY).');
            } else {
                const expMonth = parseInt(expiryMatch[1]);
                const expYear = parseInt('20' + expiryMatch[2]);
                const now = new Date();
                const expDate = new Date(expYear, expMonth, 0); // last day of month
                if (expDate < new Date(now.getFullYear(), now.getMonth(), 1)) {
                    showCcErr(ccExpiry, 'cc_expiry_error', 'Card has expired.');
                }
            }

            // CVV: 3-4 digits
            if (!/^\d{3,4}$/.test(ccCvv.value)) {
                showCcErr(ccCvv, 'cc_cvv_error', 'Enter a valid CVV (3-4 digits).');
            }

            // Cardholder name
            if (ccName.value.trim().length < 2) {
                showCcErr(ccName, 'cc_name_error', 'Enter the cardholder name.');
            }

            return valid;
        }

        // ── Bank Transfer Validation ──────────────────
        function validateBankTransfer() {
            const bankName = document.getElementById('bt_bank_name');
            const acctName = document.getElementById('bt_account_name');
            const acctNum = document.getElementById('bt_account_number');
            const errEl = document.getElementById('btErrors');
            let valid = true;

            [bankName, acctName, acctNum].forEach(f => {
                f.style.borderColor = ''; f.classList.remove('is-invalid');
            });
            ['bt_bank_name_error','bt_account_name_error','bt_account_number_error'].forEach(id => {
                const el = document.getElementById(id);
                if (el) { el.textContent = ''; el.style.display = 'none'; }
            });
            if (errEl) errEl.innerHTML = '';

            function showBtErr(field, errId, msg) {
                field.style.borderColor = '#dc2626';
                field.classList.add('is-invalid');
                const el = document.getElementById(errId);
                if (el) { el.textContent = msg; el.style.display = 'block'; }
                valid = false;
            }

            if (!bankName.value) {
                showBtErr(bankName, 'bt_bank_name_error', 'Please select your bank.');
            }
            if (acctName.value.trim().length < 2) {
                showBtErr(acctName, 'bt_account_name_error', 'Enter the account holder name.');
            }
            if (!/^\d{6,20}$/.test(acctNum.value.replace(/\s|-/g, ''))) {
                showBtErr(acctNum, 'bt_account_number_error', 'Enter a valid account number (6-20 digits).');
            }

            return valid;
        }

        // ── Credit Card Number Formatting ────────────
        document.addEventListener('input', function(e) {
            if (e.target.id === 'cc_number') {
                var val = e.target.value.replace(/\s/g, '').replace(/\D/g, '');
                if (val.length > 16) val = val.slice(0, 16);
                e.target.value = val.replace(/(.{4})/g, '$1 ').trim();
            }
            if (e.target.id === 'cc_expiry') {
                var val = e.target.value.replace(/\D/g, '');
                if (val.length > 4) val = val.slice(0, 4);
                if (val.length >= 3) val = val.slice(0, 2) + '/' + val.slice(2);
                e.target.value = val;
            }
            if (e.target.id === 'cc_cvv') {
                e.target.value = e.target.value.replace(/\D/g, '').slice(0, 4);
            }
        });

        function closeProductModal() {
            document.getElementById('productModal').classList.remove('show');
            currentProductData = null;
        }

        function switchGalleryImage(src, btn) {
            // Update main image
            const mainImg = document.getElementById('pmMainImage');
            const fallback = document.getElementById('pmGalleryFallback');
            if (mainImg) {
                mainImg.src = src;
                mainImg.style.display = '';
                if (fallback) fallback.style.display = 'none';
            }
            // Update active thumbnail
            document.querySelectorAll('.product-gallery .thumb').forEach(t => t.classList.remove('active'));
            if (btn) btn.classList.add('active');
        }

        function getCategoryIcon(category) {
            const icons = {
                'Desks': '🪑',
                'Chairs': '💺',
                'Tables': '🍽️',
                'Storage': '📚',
                'Sofas': '🛋️',
                'Beds': '🛏️',
            };
            return icons[category] || '🪑';
        }

        function getColorSwatch(colorName) {
            const c = colorName.toLowerCase().trim();
            if (c.includes('walnut') || c.includes('dark oak')) return 'background:#5c3a21;';
            if (c.includes('cherry') || c.includes('mahogany')) return 'background:#6b2020;';
            if (c.includes('brown')) return 'background:#8b5a2b;';
            if (c.includes('charcoal') || c.includes('matte black') || c.includes('black')) return 'background:#2d2d2d;';
            if (c.includes('cream') || c.includes('beige')) return 'background:#f5e6d3;';
            if (c.includes('light oak') || c.includes('pine')) return 'background:#d4a853;';
            if (c.includes('espresso')) return 'background:#3c1e0d;';
            if (c.includes('white') || c.includes('wash')) return 'background:#f8f8f8;';
            if (c.includes('grey') || c.includes('gray') || c.includes('mesh')) return 'background:#9e9e9e;';
            if (c.includes('navy') || c.includes('blue')) return 'background:#1a3a5c;';
            if (c.includes('silver')) return 'background:#c0c0c0;';
            if (c.includes('gold')) return 'background:#d4a853;';
            if (c.includes('frosted')) return 'background:#e8e8e8;';
            if (c.includes('clear glass')) return 'background:linear-gradient(135deg, #e0f0f8, #c8dce8);';
            return 'background:#cccccc;';
        }

        // ── Product Filtering & Sorting ────────────
        // Category filter now server-side — navigate with preserved search
        function filterByCategory(category) {
            var url = 'index.php?category=' + encodeURIComponent(category);
            <?php if ($searchTerm): ?>
            url += '&search=' + encodeURIComponent('<?php echo addslashes($searchTerm); ?>');
            <?php endif; ?>
            url += '#products';
            window.location.href = url;
        }

        // Client-side sort (within current page only)
        function sortProducts() {
            const sortBy = document.getElementById('sortBy').value;
            const grid = document.getElementById('productGrid');
            const cards = Array.from(grid.querySelectorAll('.card'));

            cards.sort((a, b) => {
                const nameA = a.getAttribute('data-name');
                const nameB = b.getAttribute('data-name');
                const priceA = parseFloat(a.getAttribute('data-price'));
                const priceB = parseFloat(b.getAttribute('data-price'));

                switch (sortBy) {
                    case 'name-asc': return nameA.localeCompare(nameB);
                    case 'name-desc': return nameB.localeCompare(nameA);
                    case 'price-asc': return priceA - priceB;
                    case 'price-desc': return priceB - priceA;
                    default: return 0;
                }
            });

            cards.forEach(card => grid.appendChild(card));
        }

        // ── Close modals on outside click ──────────
        window.addEventListener('click', (e) => {
            if (e.target === document.getElementById('loginModal')) closeLoginModal();
            if (e.target === document.getElementById('productModal')) closeProductModal();
            if (e.target === document.getElementById('cartModal')) closeCartModal();
        });

        // ── Wishlist Toggle ─────────────────────────
        function toggleWishlist(fid, btn) {
            var isAdding = btn.textContent.trim() === '🤍';
            var action = isAdding ? 'add_wishlist' : 'remove_wishlist';
            fetch('customer/wishlist_actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=' + action + '&furniture_id=' + fid
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    btn.textContent = isAdding ? '❤️' : '🤍';
                    if (typeof showToast === 'function') {
                        showToast(isAdding ? 'Added to wishlist! ❤️' : 'Removed from wishlist.', 'info');
                    }
                }
            });
        }

        // Load wishlist state on page load
        <?php if (isLoggedIn() && hasRole('customer')): ?>
        document.addEventListener('DOMContentLoaded', function() {
            fetch('customer/wishlist_actions.php?action=get_wishlist')
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.ids) {
                        data.ids.forEach(function(id) {
                            var btn = document.getElementById('wl-btn-' + id);
                            if (btn) btn.textContent = '❤️';
                        });
                    }
                });
        });
        <?php endif; ?>

        // ── Close modals on Escape key ─────────────
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeLoginModal();
                closeProductModal();
                closeCartModal();
            }
        });

        // ── Open login modal if there was an error ──
        <?php if ($error): ?>
        document.addEventListener('DOMContentLoaded', () => {
            openLoginModal();
        });
        <?php endif; ?>
    </script>
</body>
</html>