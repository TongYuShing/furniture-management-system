<?php
require_once 'inc/config.php';
require_once 'inc/session.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found | Premium Living Furniture</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <nav class="navbar" id="navbar">
        <div class="navbar-inner">
            <a href="index.php" class="navbar-brand">
                <div class="brand-icon">🪑</div>
                <div class="brand-text"><h2>Premium Living</h2><span>Furniture Co.</span></div>
            </a>
            <div class="navbar-actions">
                <a href="index.php" class="btn-nav btn-nav-primary">🏠 Back to Home</a>
            </div>
        </div>
    </nav>

    <section class="section" style="text-align: center; min-height: 60vh; display: flex; align-items: center; justify-content: center;">
        <div style="max-width: 500px;">
            <div style="font-size: 8rem; margin-bottom: var(--space-4);">🔍</div>
            <h1 style="font-size: 4rem; color: var(--primary); margin-bottom: var(--space-4);">404</h1>
            <h2 style="margin-bottom: var(--space-4);">Page Not Found</h2>
            <p style="color: var(--gray-500); margin-bottom: var(--space-8);">
                The page you're looking for doesn't exist or has been moved.
                <br>Let's get you back on track.
            </p>
            <div style="display: flex; gap: var(--space-4); justify-content: center; flex-wrap: wrap;">
                <a href="index.php" class="btn btn-primary btn-lg">🏠 Go Home</a>
                <?php if (isLoggedIn()): ?>
                    <?php if (hasRole('customer')): ?>
                        <a href="customer/dashboard.php" class="btn btn-outline btn-lg">📊 My Dashboard</a>
                    <?php else: ?>
                        <a href="staff/dashboard.php" class="btn btn-outline btn-lg">📊 Staff Dashboard</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="container">
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> Premium Living Furniture Co. Ltd. All rights reserved.</p>
            </div>
        </div>
    </footer>
</body>
</html>