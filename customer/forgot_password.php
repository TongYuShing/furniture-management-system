<?php
/**
 * forgot_password.php - Password Reset Request Page
 * Part II - Function: Forgot Password / Reset Flow
 */

require_once '../inc/config.php';
require_once '../inc/session.php';
require_once '../inc/password_reset.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    if (hasRole('customer')) {
        header("Location: dashboard.php");
        exit();
    } else {
        header("Location: ../staff/dashboard.php");
        exit();
    }
}

$message = '';
$messageType = '';
$resetLink = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $messageType = 'danger';
    } else {
        $customer = findCustomerByEmail($conn, $email);

        if ($customer) {
            // Generate and store reset token
            $token = generateResetToken($email);
            storeResetToken($email, $token);

            // In production, this would send an email. For demo, display the link.
            $resetLink = BASE_URL . 'customer/reset_password.php?token=' . urlencode($token);
            $message = 'A password reset link has been generated for <strong>' . htmlspecialchars($customer['CustomerName']) . '</strong>.';
            $messageType = 'success';
        } else {
            // Don't reveal whether email exists (security best practice)
            $message = 'If an account with that email exists, a reset link has been sent.';
            $messageType = 'info';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Premium Living Furniture</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar" id="navbar">
        <div class="navbar-inner">
            <a href="../index.php" class="navbar-brand">
                <div class="brand-icon">🪑</div>
                <div class="brand-text">
                    <h2>Premium Living</h2>
                    <span>Furniture Co.</span>
                </div>
            </a>
            <ul class="navbar-links" id="navLinks">
                <li><a href="../index.php">🏠 Home</a></li>
                <li><a href="../index.php#products">🛍️ Products</a></li>
                <li><a href="../index.php#about">ℹ️ About</a></li>
                <li><a href="contact_us.php">📞 Contact</a></li>
            </ul>
            <div class="navbar-actions">
                <a href="../index.php" class="btn-nav btn-nav-outline">🔐 Sign In</a>
            </div>
            <button class="navbar-toggle" id="navToggle" aria-label="Toggle menu">
                <span></span><span></span><span></span>
            </button>
        </div>
    </nav>

    <section class="section" style="background: var(--off-white); min-height: calc(100vh - 200px); padding-top: var(--space-10);">
        <div class="container">
            <div style="max-width: 480px; margin: 0 auto;">
                <div class="section-header" style="margin-bottom: var(--space-8);">
                    <span class="section-label">Account Recovery</span>
                    <h2>Forgot Your Password?</h2>
                    <p>Enter your email address and we'll help you reset your password.</p>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?>" style="margin-bottom: var(--space-6);">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <?php if ($resetLink): ?>
                    <!-- Demo: Show reset link (in production this would be emailed) -->
                    <div style="background: var(--white); border-radius: var(--radius-xl); padding: var(--space-6); box-shadow: var(--shadow-lg); border: 2px solid var(--success); margin-bottom: var(--space-6);">
                        <h4 style="color: var(--success); margin-bottom: var(--space-3);">📧 Demo Mode — Reset Link</h4>
                        <p style="font-size: var(--font-size-sm); color: var(--gray-500); margin-bottom: var(--space-4);">
                            In production, this link would be emailed. For demo purposes, use the link below:
                        </p>
                        <div style="background: var(--gray-50); padding: var(--space-4); border-radius: var(--radius); word-break: break-all; font-size: var(--font-size-sm); margin-bottom: var(--space-4);">
                            <a href="<?php echo htmlspecialchars($resetLink); ?>" style="color: var(--primary); font-weight: 600;">
                                <?php echo htmlspecialchars($resetLink); ?>
                            </a>
                        </div>
                        <p style="font-size: var(--font-size-xs); color: var(--gray-400);">
                            ⏰ This link expires in 1 hour.
                        </p>
                    </div>
                <?php endif; ?>

                <div style="background: var(--white); border-radius: var(--radius-2xl); padding: var(--space-8); box-shadow: var(--shadow-lg); border: 1px solid var(--gray-100);">
                    <form method="POST" action="">
                        <div class="login-brand" style="text-align: center; margin-bottom: var(--space-6);">
                            <span style="font-size: 3rem;">🔑</span>
                            <p style="color: var(--gray-500); font-size: var(--font-size-sm);">Enter the email address associated with your account.</p>
                        </div>

                        <div class="form-group">
                            <label for="email" class="required">Email Address</label>
                            <input type="email" name="email" id="email"
                                   class="form-control"
                                   required
                                   placeholder="yourname@email.com"
                                   autofocus>
                        </div>

                        <button type="submit" class="btn btn-primary btn-block btn-lg">📧 Send Reset Link</button>
                    </form>
                </div>

                <div style="text-align: center; margin-top: var(--space-6);">
                    <p style="color: var(--gray-500);">
                        <a href="../index.php">← Back to Sign In</a>
                        &nbsp;|&nbsp;
                        <a href="register.php">Create New Account</a>
                    </p>
                </div>
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

    <script>
        const navToggle = document.getElementById('navToggle');
        const navLinks = document.getElementById('navLinks');
        navToggle.addEventListener('click', () => navLinks.classList.toggle('open'));
    </script>
</body>
</html>