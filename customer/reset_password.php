<?php
/**
 * reset_password.php - Set New Password (after clicking reset link)
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
$token = $_GET['token'] ?? '';
$email = '';

// Validate token
if (empty($token)) {
    $message = 'Invalid or missing reset token. Please request a new password reset.';
    $messageType = 'danger';
} else {
    $email = validateResetToken($token);
    if (!$email) {
        $message = 'This reset link is invalid or has expired. Please request a new one.';
        $messageType = 'danger';
    }
}

// Handle new password submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $email) {
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    $errors = [];
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }
    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        if (updateCustomerPasswordDirect($conn, $email, $password)) {
            // Consume the token so it can't be reused
            consumeResetToken($token);

            $message = '✅ Password reset successful! You can now sign in with your new password.';
            $messageType = 'success';
            $email = ''; // Hide form after success
        } else {
            $message = 'Failed to update password. Please try again.';
            $messageType = 'danger';
        }
    } else {
        $message = implode('<br>', $errors);
        $messageType = 'danger';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Premium Living Furniture</title>
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
                    <span class="section-label">Set New Password</span>
                    <h2>Reset Your Password</h2>
                    <?php if ($email): ?>
                        <p>Enter your new password for <strong><?php echo htmlspecialchars($email); ?></strong>.</p>
                    <?php endif; ?>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?>" style="margin-bottom: var(--space-6);">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <?php if ($email): ?>
                    <!-- New Password Form -->
                    <div style="background: var(--white); border-radius: var(--radius-2xl); padding: var(--space-8); box-shadow: var(--shadow-lg); border: 1px solid var(--gray-100);">
                        <form method="POST" action="">
                            <div class="login-brand" style="text-align: center; margin-bottom: var(--space-6);">
                                <span style="font-size: 3rem;">🔒</span>
                                <p style="color: var(--gray-500); font-size: var(--font-size-sm);">Choose a strong password you haven't used before.</p>
                            </div>

                            <div class="form-group">
                                <label for="password" class="required">New Password</label>
                                <input type="password" name="password" id="password"
                                       class="form-control"
                                       required
                                       minlength="6"
                                       placeholder="At least 6 characters"
                                       autofocus>
                                <div id="password_error"
                                     style="color: var(--danger); font-size: var(--font-size-xs); margin-top: var(--space-1); display: none;"></div>
                            </div>

                            <div class="form-group">
                                <label for="confirm_password" class="required">Confirm New Password</label>
                                <input type="password" name="confirm_password" id="confirm_password"
                                       class="form-control"
                                       required
                                       placeholder="Re-enter your new password">
                                <div id="confirm_password_error"
                                     style="color: var(--danger); font-size: var(--font-size-xs); margin-top: var(--space-1); display: none;"></div>
                            </div>

                            <button type="submit" class="btn btn-accent btn-block btn-lg">🔒 Reset Password</button>
                        </form>
                    </div>
                <?php endif; ?>

                <div style="text-align: center; margin-top: var(--space-6);">
                    <p style="color: var(--gray-500);">
                        <a href="../index.php">← Back to Sign In</a>
                        <?php if (!$email): ?>
                            &nbsp;|&nbsp;
                            <a href="forgot_password.php">Request New Reset Link</a>
                        <?php endif; ?>
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

    <script src="../assets/js/password-toggle.js"></script>
    <script>
        const navToggle = document.getElementById('navToggle');
        const navLinks = document.getElementById('navLinks');
        navToggle.addEventListener('click', () => navLinks.classList.toggle('open'));

        // Password match validation
        const pw = document.getElementById('password');
        const cpw = document.getElementById('confirm_password');
        const cpwErr = document.getElementById('confirm_password_error');

        pw?.addEventListener('input', checkMatch);
        cpw?.addEventListener('input', checkMatch);

        function checkMatch() {
            if (cpw.value && pw.value !== cpw.value) {
                cpwErr.textContent = 'Passwords do not match.';
                cpwErr.style.display = 'block';
                cpw.style.borderColor = 'var(--danger)';
            } else if (cpw.value && pw.value === cpw.value) {
                cpwErr.style.display = 'none';
                cpw.style.borderColor = 'var(--success)';
            }
        }
    </script>
</body>
</html>