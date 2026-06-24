<?php
/**
 * contact_us.php - Contact Us Page
 * Displays company contact information, location, and inquiry form
 */

require_once '../inc/config.php';
require_once '../inc/session.php';
require_once '../inc/advanced.php';

$formMsg = '';
$formMsgType = '';
$formData = [];
$loginError = '';

// Handle login form submission (from modal)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action']) && $_POST['form_action'] === 'login') {
    $userId = trim($_POST['user_id'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role = $_POST['role'] ?? 'customer';

    if (empty($userId) || empty($password)) {
        $loginError = 'Please enter both ID and password.';
    } else {
        if ($role === 'customer') {
            if (customerLogin($conn, $userId, $password)) {
                header("Location: dashboard.php");
                exit();
            } else {
                $loginError = 'Invalid Customer ID or Password.';
            }
        } else {
            if (staffLogin($conn, $userId, $password)) {
                header("Location: ../staff/dashboard.php");
                exit();
            } else {
                $loginError = 'Invalid Staff ID or Password.';
            }
        }
    }
}

// Handle inquiry form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['inq_submit'])) {
    $formData = [
        'name'    => trim($_POST['inq_name'] ?? ''),
        'email'   => trim($_POST['inq_email'] ?? ''),
        'subject' => trim($_POST['inq_subject'] ?? ''),
        'message' => trim($_POST['inq_message'] ?? ''),
    ];

    $errors = [];
    if ($formData['name'] === '' || strlen($formData['name']) < 2) {
        $errors[] = 'Please enter your name (at least 2 characters).';
    }
    if ($formData['email'] === '' || !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if ($formData['message'] === '' || strlen($formData['message']) < 10) {
        $errors[] = 'Please enter a message (at least 10 characters).';
    }

    if (empty($errors)) {
        // Save inquiry to database
        $sql = "INSERT INTO ContactInquiries (CustomerName, Email, Subject, Message)
                VALUES (?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'ssss',
            $formData['name'],
            $formData['email'],
            $formData['subject'],
            $formData['message']
        );
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // Queue email notification to company
        $emailBody = "New inquiry from {$formData['name']} ({$formData['email']})\n\n" .
                     "Subject: {$formData['subject']}\n" .
                     "Message:\n{$formData['message']}\n\n" .
                     "— Premium Living Furniture Contact Form";
        queueEmail('info@premiumliving.com', 'Premium Living Team',
                   'New Contact Inquiry: ' . ($formData['subject'] ?: 'General'),
                   $emailBody);

        $formMsg = '✅ Thank you, ' . htmlspecialchars($formData['name']) . '! Your message has been sent. We\'ll get back to you within 1 business day.';
        $formMsgType = 'success';
        $formData = []; // Clear form
    } else {
        $formMsg = implode('<br>', $errors);
        $formMsgType = 'danger';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - Premium Living Furniture</title>
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
                <li><a href="contact_us.php" class="active">📞 Contact</a></li>
                <?php if (isLoggedIn()): ?>
                    <?php if (hasRole('customer')): ?>
                        <li><a href="dashboard.php">📊 Dashboard</a></li>
                    <?php else: ?>
                        <li><a href="../staff/dashboard.php">📊 Dashboard</a></li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
            <div class="navbar-actions">
                <?php if (isLoggedIn()): ?>
                    <span style="font-weight:600;font-size:0.875rem;color:var(--primary);">
                        👋 <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                    </span>
                    <a href="<?php echo hasRole('customer') ? 'logout.php' : '../staff/logout.php'; ?>" class="btn-nav btn-nav-outline">🚪 Logout</a>
                <?php else: ?>
                    <button class="btn-nav btn-nav-outline" onclick="openLoginModal()">🔐 Sign In</button>
                <?php endif; ?>
            </div>
            <button class="navbar-toggle" id="navToggle" aria-label="Toggle menu">
                <span></span><span></span><span></span>
            </button>
        </div>
    </nav>

    <!-- Hero Banner -->
    <section style="background: linear-gradient(135deg, var(--primary) 0%, #2d5a3a 100%); padding: var(--space-10) 0 var(--space-8); color: var(--white); text-align: center;">
        <div class="container">
            <div style="font-size: 3rem; margin-bottom: var(--space-4);">📞</div>
            <h1 style="color: var(--white); font-size: 2.2rem; margin-bottom: var(--space-2);">Get in Touch</h1>
            <p style="color: rgba(255,255,255,0.85); font-size: 1.1rem; max-width: 600px; margin: 0 auto;">
                We'd love to hear from you. Reach out to our team for any inquiries about our furniture, orders, or services.
            </p>
        </div>
    </section>

    <!-- Contact Information -->
    <section class="section" style="background: var(--off-white);">
        <div class="container">
            <div class="section-header" style="margin-bottom: var(--space-8);">
                <span class="section-label">Reach Us</span>
                <h2>Contact Information</h2>
                <p>Multiple ways to connect with the Premium Living team.</p>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: var(--space-6); max-width: 1000px; margin: 0 auto;">
                <!-- Address Card -->
                <div style="background: var(--white); border-radius: var(--radius-xl); padding: var(--space-6); text-align: center; box-shadow: var(--shadow-sm); border: 1px solid var(--gray-100);">
                    <div style="font-size: 2.5rem; margin-bottom: var(--space-3);">📍</div>
                    <h3 style="margin-bottom: var(--space-3);">Our Showroom</h3>
                    <p style="color: var(--gray-600); line-height: 1.7;">
                        15 Canton Road<br>
                        Tsim Sha Tsui, Kowloon<br>
                        Hong Kong
                    </p>
                </div>

                <!-- Phone Card -->
                <div style="background: var(--white); border-radius: var(--radius-xl); padding: var(--space-6); text-align: center; box-shadow: var(--shadow-sm); border: 1px solid var(--gray-100);">
                    <div style="font-size: 2.5rem; margin-bottom: var(--space-3);">📱</div>
                    <h3 style="margin-bottom: var(--space-3);">Call Us</h3>
                    <p style="color: var(--gray-600); line-height: 1.7;">
                        <strong>Sales & Inquiries:</strong><br>
                        +852 9123 4567<br><br>
                        <strong>Customer Support:</strong><br>
                        +852 9234 5678
                    </p>
                </div>

                <!-- Email Card -->
                <div style="background: var(--white); border-radius: var(--radius-xl); padding: var(--space-6); text-align: center; box-shadow: var(--shadow-sm); border: 1px solid var(--gray-100);">
                    <div style="font-size: 2.5rem; margin-bottom: var(--space-3);">✉️</div>
                    <h3 style="margin-bottom: var(--space-3);">Email Us</h3>
                    <p style="color: var(--gray-600); line-height: 1.7;">
                        <strong>General Inquiries:</strong><br>
                        info@premiumliving.com<br><br>
                        <strong>Order Support:</strong><br>
                        orders@premiumliving.com<br><br>
                        <strong>Careers:</strong><br>
                        careers@premiumliving.com
                    </p>
                </div>

                <!-- Business Hours Card -->
                <div style="background: var(--white); border-radius: var(--radius-xl); padding: var(--space-6); text-align: center; box-shadow: var(--shadow-sm); border: 1px solid var(--gray-100);">
                    <div style="font-size: 2.5rem; margin-bottom: var(--space-3);">🕐</div>
                    <h3 style="margin-bottom: var(--space-3);">Business Hours</h3>
                    <table style="margin: 0 auto; color: var(--gray-600); line-height: 1.8; text-align: left;">
                        <tr><td style="padding-right: var(--space-4); font-weight: 600;">Mon – Fri</td><td>10:00 AM – 8:00 PM</td></tr>
                        <tr><td style="padding-right: var(--space-4); font-weight: 600;">Saturday</td><td>10:00 AM – 6:00 PM</td></tr>
                        <tr><td style="padding-right: var(--space-4); font-weight: 600;">Sunday</td><td>11:00 AM – 5:00 PM</td></tr>
                        <tr><td style="padding-right: var(--space-4); font-weight: 600;">Public Holidays</td><td>11:00 AM – 4:00 PM</td></tr>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <!-- How to Find Us -->
    <section class="section" style="background: var(--white);">
        <div class="container">
            <div class="section-header" style="margin-bottom: var(--space-8);">
                <span class="section-label">Visit Us</span>
                <h2>How to Find Us</h2>
                <p>Conveniently located in the heart of Tsim Sha Tsui.</p>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-8); max-width: 1000px; margin: 0 auto; align-items: center;">
                <!-- Map placeholder -->
                <div style="background: var(--gray-100); border-radius: var(--radius-xl); padding: var(--space-10); text-align: center; min-height: 280px; display: flex; flex-direction: column; align-items: center; justify-content: center; border: 2px dashed var(--gray-300);">
                    <div style="font-size: 4rem; margin-bottom: var(--space-3);">🗺️</div>
                    <h4 style="margin-bottom: var(--space-2);">Our Showroom Location</h4>
                    <p style="color: var(--gray-500); font-size: var(--font-size-sm);">
                        15 Canton Road<br>
                        Tsim Sha Tsui, Kowloon<br>
                        Hong Kong
                    </p>
                </div>

                <!-- Directions -->
                <div>
                    <h3 style="margin-bottom: var(--space-4);">🚇 Getting Here</h3>
                    <div style="color: var(--gray-600); line-height: 1.8;">
                        <p style="margin-bottom: var(--space-4);">
                            <strong>By MTR:</strong> Take the Tsuen Wan Line to Tsim Sha Tsui Station.
                            Take Exit A1 and walk 3 minutes along Canton Road.
                        </p>
                        <p style="margin-bottom: var(--space-4);">
                            <strong>By Bus:</strong> Routes 1, 1A, 2, 6, 7, 9 stop at Canton Road.
                            Additional routes 215X, 271, 281A available nearby.
                        </p>
                        <p style="margin-bottom: var(--space-4);">
                            <strong>By Ferry:</strong> Take the Star Ferry from Central or Wan Chai
                            to Tsim Sha Tsui Pier, then a 5-minute walk to Canton Road.
                        </p>
                        <p>
                            <strong>Parking:</strong> Limited parking available. Nearest public car park
                            at Harbour City (3-minute walk).
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Quick Inquiry -->
    <section class="section" style="background: var(--off-white);">
        <div class="container">
            <div class="section-header" style="margin-bottom: var(--space-8);">
                <span class="section-label">Quick Inquiry</span>
                <h2>Send Us a Message</h2>
                <p>Have a question? Fill out the form below and we'll get back to you within 24 hours.</p>
            </div>

            <div style="max-width: 640px; margin: 0 auto;">
                <?php if ($formMsg): ?>
                    <div class="alert alert-<?php echo $formMsgType; ?>" style="margin-bottom: var(--space-5);">
                        <?php echo $formMsg; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" data-validate novalidate style="background: var(--white); border-radius: var(--radius-xl); padding: var(--space-8); box-shadow: var(--shadow-sm); border: 1px solid var(--gray-100);">
                    <input type="hidden" name="inq_submit" value="1">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="inq_name" class="required">Your Name</label>
                            <input type="text" name="inq_name" id="inq_name" class="form-control" required
                                   minlength="2" maxlength="100"
                                   value="<?php echo htmlspecialchars($formData['name'] ?? ''); ?>"
                                   placeholder="Enter your name">
                            <div class="invalid-feedback" id="inq_name_error"
                                 style="color: var(--danger); font-size: var(--font-size-xs); margin-top: var(--space-1); display: none;"></div>
                        </div>
                        <div class="form-group">
                            <label for="inq_email" class="required">Email Address</label>
                            <input type="email" name="inq_email" id="inq_email" class="form-control" required
                                   maxlength="100"
                                   value="<?php echo htmlspecialchars($formData['email'] ?? ''); ?>"
                                   placeholder="yourname@email.com">
                            <div class="invalid-feedback" id="inq_email_error"
                                 style="color: var(--danger); font-size: var(--font-size-xs); margin-top: var(--space-1); display: none;"></div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="inq_subject">Subject</label>
                        <input type="text" name="inq_subject" id="inq_subject" class="form-control"
                               maxlength="200"
                               value="<?php echo htmlspecialchars($formData['subject'] ?? ''); ?>"
                               placeholder="What is this regarding?">
                    </div>

                    <div class="form-group">
                        <label for="inq_message" class="required">Your Message</label>
                        <textarea name="inq_message" id="inq_message" class="form-control"
                                  required
                                  minlength="10"
                                  maxlength="1000"
                                  rows="5"
                                  placeholder="Tell us how we can help you..."><?php echo htmlspecialchars($formData['message'] ?? ''); ?></textarea>
                        <div class="invalid-feedback" id="inq_message_error"
                             style="color: var(--danger); font-size: var(--font-size-xs); margin-top: var(--space-1); display: none;"></div>
                        <small>Maximum 1000 characters.</small>
                    </div>

                    <button type="submit" class="btn btn-accent btn-lg btn-block" style="margin-top: var(--space-4);" id="inqSubmitBtn">
                        ✉️ Send Message
                    </button>

                    <p style="text-align: center; color: var(--gray-400); font-size: var(--font-size-xs); margin-top: var(--space-3);">
                        We'll respond to your inquiry within 1 business day.
                    </p>
                </form>
            </div>
        </div>
    </section>

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

                <?php if ($loginError): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($loginError); ?></div>
                <?php endif; ?>

                <form method="POST" action="contact_us.php" id="loginForm">
                    <input type="hidden" name="form_action" value="login">
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
                        <label for="login_user_id">ID Number</label>
                        <input type="number" name="user_id" id="login_user_id" class="form-control"
                               placeholder="Enter your Customer ID (e.g., 1001)"
                               required autofocus>
                    </div>

                    <div class="form-group">
                        <label for="login_password">Password</label>
                        <input type="password" name="password" id="login_password" class="form-control"
                               placeholder="Enter your password" required>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block btn-lg">🔐 Sign In</button>
                </form>

                <div style="text-align: center; margin-top: var(--space-3);">
                    <a href="forgot_password.php" style="font-size: var(--font-size-sm); color: var(--gray-500);">Forgot your password?</a>
                </div>

                <div style="text-align: center; margin-top: var(--space-4); padding-top: var(--space-4); border-top: 1px solid var(--gray-200);">
                    <p style="color: var(--gray-500); font-size: var(--font-size-sm); margin-bottom: var(--space-2);">
                        Don't have an account?
                    </p>
                    <a href="register.php" class="btn btn-outline-accent btn-block">
                        ✨ Create New Account
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
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
                        <li><a href="../index.php">Home</a></li>
                        <li><a href="../index.php#products">Products</a></li>
                        <li><a href="../index.php#about">About Us</a></li>
                        <li><a href="contact_us.php">Contact</a></li>
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

    <script src="../assets/js/validation.js"></script>
    <script>
        // ── Contact Form Validation ────────────────
        (function() {
            var form = document.querySelector('form[data-validate]');
            if (!form) return;

            function showErr(field, errId, msg) {
                field.style.borderColor = '#dc2626';
                field.classList.add('is-invalid');
                var el = document.getElementById(errId);
                if (el) { el.textContent = msg; el.style.display = 'block'; }
            }
            function clearErr(field, errId) {
                field.style.borderColor = '';
                field.classList.remove('is-invalid');
                var el = document.getElementById(errId);
                if (el) { el.textContent = ''; el.style.display = 'none'; }
            }

            form.addEventListener('submit', function(e) {
                var name = document.getElementById('inq_name');
                var email = document.getElementById('inq_email');
                var msg = document.getElementById('inq_message');
                var hasErrors = false;

                if (name.value.trim().length < 2) {
                    showErr(name, 'inq_name_error', 'Name must be at least 2 characters.');
                    hasErrors = true;
                }
                var emailRegex = /^[^\s@]+@([^\s@]+\.)+[^\s@]+$/;
                if (!emailRegex.test(email.value.trim())) {
                    showErr(email, 'inq_email_error', 'Please enter a valid email address.');
                    hasErrors = true;
                }
                if (msg.value.trim().length < 10) {
                    showErr(msg, 'inq_message_error', 'Message must be at least 10 characters.');
                    hasErrors = true;
                }

                if (hasErrors) {
                    e.preventDefault();
                    var first = document.querySelector('.is-invalid');
                    if (first) first.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            });

            // Clear errors on input
            form.querySelectorAll('input, textarea').forEach(function(input) {
                input.addEventListener('input', function() {
                    clearErr(this, this.id + '_error');
                });
            });
        })();

        // ── Mobile Nav Toggle ──────────────────────
        const navToggle = document.getElementById('navToggle');
        const navLinks = document.getElementById('navLinks');
        navToggle.addEventListener('click', () => navLinks.classList.toggle('open'));
        navLinks.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => navLinks.classList.remove('open'));
        });

        // ── Navbar Scroll Effect ───────────────────
        const navbar = document.getElementById('navbar');
        window.addEventListener('scroll', () => {
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });

        // ── Login Modal ───────────────────────────
        function openLoginModal() {
            document.getElementById('loginModal').classList.add('show');
            switchRole('customer');
            document.getElementById('login_user_id').focus();
        }
        function closeLoginModal() {
            document.getElementById('loginModal').classList.remove('show');
        }
        function switchRole(role) {
            document.getElementById('loginRole').value = role;
            document.querySelectorAll('#roleTabs .role-tab').forEach(tab => {
                tab.classList.remove('active');
                if (tab.getAttribute('data-role') === role) tab.classList.add('active');
            });
            const input = document.getElementById('login_user_id');
            input.placeholder = role === 'customer' ? 'Enter Customer ID (e.g., 1001)' : 'Enter Staff ID (e.g., 1)';
        }

        // Close on outside click
        window.addEventListener('click', (e) => {
            if (e.target === document.getElementById('loginModal')) closeLoginModal();
        });
        // Close on Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeLoginModal();
        });
        // Open if login error
        <?php if ($loginError): ?>
        document.addEventListener('DOMContentLoaded', () => openLoginModal());
        <?php endif; ?>
    </script>
</body>
</html>
