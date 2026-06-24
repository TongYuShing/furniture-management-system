<?php
/**
 * register.php - Customer & Staff Registration Page
 * Allows new customers and staff to create their own accounts
 * Part I - Function #1: Customer Registration & Part II - Function #4: Staff Registration
 */

require_once '../inc/config.php';
require_once '../inc/session.php';
require_once '../inc/functions.php';

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
$loginError = '';
$formData = []; // Preserve form data on error

// Determine which form was submitted
$formAction = $_POST['form_action'] ?? '';

// Handle login form submission (from the modal)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $formAction === 'login') {
    $userId = isset($_POST['user_id']) ? trim($_POST['user_id']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    $role = isset($_POST['role']) ? $_POST['role'] : 'customer';

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

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $formAction === 'register') {
    $registerRole = $_POST['register_role'] ?? 'customer';

    if ($registerRole === 'staff') {
        // ── Staff Registration ─────────────────────
        $formData = [
            'staff_name' => $_POST['staff_name'] ?? '',
            'email' => $_POST['email'] ?? '',
        ];

        $result = registerStaff($conn, [
            'staff_name' => $_POST['staff_name'] ?? '',
            'password' => $_POST['password'] ?? '',
            'confirm_password' => $_POST['confirm_password'] ?? '',
            'email' => $_POST['email'] ?? '',
            'role' => 'staff', // Self-registered staff always get 'staff' role
        ]);

        if ($result['success']) {
            $staffId = $result['staff_id'];
            // Auto-login after successful registration
            staffLogin($conn, $staffId, $_POST['password']);

            $message = '🎉 Staff account created! Your Staff ID is <strong>' . $staffId . '</strong>. Welcome, ' . htmlspecialchars($result['staff_name'] ?? $_POST['staff_name']) . '!';
            $messageType = 'success';

            // Redirect to staff dashboard after a short delay
            header("Refresh: 2; URL=../staff/dashboard.php");
        } else {
            $message = $result['message'];
            $messageType = 'danger';
        }
    } else {
        // ── Customer Registration ──────────────────
        $formData = [
            'customer_name' => $_POST['customer_name'] ?? '',
            'email' => $_POST['email'] ?? '',
            'contact_number' => $_POST['contact_number'] ?? '',
            'address' => $_POST['address'] ?? '',
            'company_name' => $_POST['company_name'] ?? '',
        ];

        $data = [
            'customer_name' => $_POST['customer_name'] ?? '',
            'password' => $_POST['password'] ?? '',
            'confirm_password' => $_POST['confirm_password'] ?? '',
            'contact_number' => $_POST['contact_number'] ?? '',
            'address' => $_POST['address'] ?? '',
            'email' => $_POST['email'] ?? '',
            'company_name' => $_POST['company_name'] ?? '',
        ];

        $result = registerCustomer($conn, $data);

        if ($result['success']) {
            // Auto-login after successful registration
            $customerId = $result['customer_id'];
            customerLogin($conn, $customerId, $data['password']);

            $message = '🎉 Registration successful! Your Customer ID is <strong>' . $customerId . '</strong>. Welcome, ' . htmlspecialchars($result['customer_name']) . '!';
            $messageType = 'success';

            // Redirect to dashboard after a short delay
            header("Refresh: 2; URL=dashboard.php");
        } else {
            $message = $result['message'];
            $messageType = 'danger';
        }
    }
}

// Determine which tab to show based on form submission or default
$activeRegisterRole = $_POST['register_role'] ?? 'customer';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account - Premium Living Furniture</title>
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
                <button class="btn-nav btn-nav-outline" onclick="openLoginModal()">🔐 Sign In</button>
            </div>
            <button class="navbar-toggle" id="navToggle" aria-label="Toggle menu">
                <span></span><span></span><span></span>
            </button>
        </div>
    </nav>

    <section class="section" style="background: var(--off-white); min-height: calc(100vh - 200px); padding-top: var(--space-10);">
        <div class="container">
            <div style="max-width: 640px; margin: 0 auto;">
                <!-- Page Header -->
                <div class="section-header" style="margin-bottom: var(--space-8);">
                    <span class="section-label">Get Started</span>
                    <h2>Create Your Account</h2>
                    <p>Join Premium Living Furniture — create a customer or staff account to get started.</p>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?>" style="margin-bottom: var(--space-6);">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <!-- Registration Form -->
                <div style="background: var(--white); border-radius: var(--radius-2xl); padding: var(--space-8); box-shadow: var(--shadow-lg); border: 1px solid var(--gray-100);">
                    <form method="POST" action="" data-validate novalidate>
                        <input type="hidden" name="form_action" value="register">
                        <input type="hidden" name="register_role" id="registerRole" value="<?php echo htmlspecialchars($activeRegisterRole); ?>">

                        <!-- Role Tabs -->
                        <div class="role-tabs" id="registerRoleTabs" style="margin-bottom: var(--space-6);">
                            <button type="button" class="role-tab customer <?php echo $activeRegisterRole === 'customer' ? 'active' : ''; ?>" data-role="customer" onclick="switchRegisterRole('customer')">
                                👤 Customer
                            </button>
                            <button type="button" class="role-tab staff <?php echo $activeRegisterRole === 'staff' ? 'active' : ''; ?>" data-role="staff" onclick="switchRegisterRole('staff')">
                                👔 Staff
                            </button>
                        </div>

                        <!-- Personal Information -->
                        <h3 style="margin-bottom: var(--space-5);">👤 Personal Information</h3>

                        <!-- Name Field (shared) -->
                        <div class="form-group register-field register-field-customer" id="nameFieldCustomer">
                            <label for="customer_name" class="required">Full Name</label>
                            <input type="text" name="customer_name" id="customer_name"
                                   class="form-control"
                                   minlength="2"
                                   maxlength="100"
                                   value="<?php echo htmlspecialchars($formData['customer_name'] ?? ''); ?>"
                                   placeholder="Enter your full name (e.g., John Smith)">
                            <div class="invalid-feedback" id="customer_name_error"
                                 style="color: var(--danger); font-size: var(--font-size-xs); margin-top: var(--space-1); display: none;"></div>
                        </div>

                        <div class="form-group register-field register-field-staff" id="nameFieldStaff" style="display: none;">
                            <label for="staff_name" class="required">Full Name</label>
                            <input type="text" name="staff_name" id="staff_name"
                                   class="form-control"
                                   minlength="2"
                                   maxlength="100"
                                   value="<?php echo htmlspecialchars($formData['staff_name'] ?? ''); ?>"
                                   placeholder="Enter your full name (e.g., Jane Doe)">
                            <div class="invalid-feedback" id="staff_name_error"
                                 style="color: var(--danger); font-size: var(--font-size-xs); margin-top: var(--space-1); display: none;"></div>
                        </div>

                        <div class="form-group">
                            <label for="email" class="required">Email Address</label>
                            <input type="email" name="email" id="email"
                                   class="form-control"
                                   data-type="email"
                                   required
                                   maxlength="100"
                                   value="<?php echo htmlspecialchars($formData['email'] ?? ''); ?>"
                                   placeholder="yourname@email.com">
                            <div class="invalid-feedback" id="email_error"
                                 style="color: var(--danger); font-size: var(--font-size-xs); margin-top: var(--space-1); display: none;"></div>
                        </div>

                        <!-- Password Section -->
                        <h3 style="margin-top: var(--space-6); margin-bottom: var(--space-5);">🔒 Security</h3>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="password" class="required">Password</label>
                                <input type="password" name="password" id="password"
                                       class="form-control"
                                       data-type="password"
                                       required
                                       minlength="6"
                                       placeholder="At least 6 characters">
                                <div class="invalid-feedback" id="password_error"
                                     style="color: var(--danger); font-size: var(--font-size-xs); margin-top: var(--space-1); display: none;"></div>
                                <small>Minimum 6 characters. Use a strong password.</small>
                            </div>

                            <div class="form-group">
                                <label for="confirm_password" class="required">Confirm Password</label>
                                <input type="password" name="confirm_password" id="confirm_password"
                                       class="form-control"
                                       required
                                       placeholder="Re-enter your password">
                                <div class="invalid-feedback" id="confirm_password_error"
                                     style="color: var(--danger); font-size: var(--font-size-xs); margin-top: var(--space-1); display: none;"></div>
                            </div>
                        </div>

                        <!-- Customer-only fields -->
                        <div class="register-field register-field-customer" id="customerOnlyFields">
                            <!-- Contact Information -->
                            <h3 style="margin-top: var(--space-6); margin-bottom: var(--space-5);">📞 Contact Information</h3>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="contact_number" class="required">Contact Number</label>
                                    <input type="tel" name="contact_number" id="contact_number"
                                           class="form-control"
                                           data-type="phone"
                                           maxlength="20"
                                           value="<?php echo htmlspecialchars($formData['contact_number'] ?? ''); ?>"
                                           placeholder="8-digit Hong Kong number (e.g., 91234567)">
                                    <div class="invalid-feedback" id="contact_number_error"
                                         style="color: var(--danger); font-size: var(--font-size-xs); margin-top: var(--space-1); display: none;"></div>
                                    <small>Format: 8 digits only</small>
                                </div>

                                <div class="form-group">
                                    <label for="company_name">Company / Organization</label>
                                    <input type="text" name="company_name" id="company_name"
                                           class="form-control"
                                           maxlength="100"
                                           value="<?php echo htmlspecialchars($formData['company_name'] ?? ''); ?>"
                                           placeholder="Optional — your company name">
                                    <div class="invalid-feedback" id="company_name_error"
                                         style="color: var(--danger); font-size: var(--font-size-xs); margin-top: var(--space-1); display: none;"></div>
                                    <small>Optional. Can be set later.</small>
                                </div>
                            </div>

                            <!-- Address -->
                            <div class="form-group">
                                <label for="address" class="required">Delivery Address</label>
                                <textarea name="address" id="address"
                                          class="form-control"
                                          minlength="10"
                                          maxlength="500"
                                          rows="3"
                                          placeholder="Your full delivery address (e.g., 15 Canton Road, Tsim Sha Tsui, Hong Kong)"><?php echo htmlspecialchars($formData['address'] ?? ''); ?></textarea>
                                <div class="invalid-feedback" id="address_error"
                                     style="color: var(--danger); font-size: var(--font-size-xs); margin-top: var(--space-1); display: none;"></div>
                                <small>This will be your default delivery address for orders.</small>
                            </div>
                        </div><!-- /customer-only fields -->

                        <!-- Staff role info -->
                        <div class="register-field register-field-staff" id="staffInfoField" style="display: none; background: var(--gray-50); padding: var(--space-4); border-radius: var(--radius); margin-top: var(--space-4);">
                            <p style="color: var(--gray-600); font-size: var(--font-size-sm); margin: 0;">
                                ℹ️ Your account will be created with the <strong>Staff</strong> role.
                                An administrator can adjust your permissions later if needed.
                            </p>
                        </div>

                        <!-- Submit -->
                        <div class="form-group" style="margin-top: var(--space-8);">
                            <button type="submit" class="btn btn-accent btn-lg btn-block" id="registerSubmitBtn">
                                ✨ Create Customer Account
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Already have an account? -->
                <div style="text-align: center; margin-top: var(--space-6); padding: var(--space-5);">
                    <p style="color: var(--gray-500);">
                        Already have an account?
                        <a href="#" onclick="event.preventDefault(); openLoginModal();" style="font-weight: 600;">
                            Sign in here →
                        </a>
                    </p>
                </div>
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

                <form method="POST" action="register.php" id="loginForm">
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
                               required>
                    </div>

                    <div class="form-group">
                        <label for="login_password">Password</label>
                        <input type="password" name="password" id="login_password" class="form-control"
                               placeholder="Enter your password" required>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block btn-lg">🔐 Sign In</button>
                </form>

                <!-- Forgot Password Link -->
                <div style="text-align: center; margin-top: var(--space-3);">
                    <a href="forgot_password.php" style="font-size: var(--font-size-sm); color: var(--gray-500);">Forgot your password?</a>
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

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> Premium Living Furniture Co. Ltd. All rights reserved. | Centralized Management System</p>
            </div>
        </div>
    </footer>

    <script src="../assets/js/validation.js"></script>
    <script src="../assets/js/password-toggle.js"></script>
    <script>
        // ── Registration Role Switching ────────────
        function switchRegisterRole(role) {
            document.getElementById('registerRole').value = role;

            // Update tab styling
            const tabs = document.querySelectorAll('#registerRoleTabs .role-tab');
            tabs.forEach(tab => {
                tab.classList.remove('active');
                if (tab.getAttribute('data-role') === role) {
                    tab.classList.add('active');
                }
            });

            // Show/hide customer-only fields
            const customerFields = document.querySelectorAll('.register-field-customer');
            const staffFields = document.querySelectorAll('.register-field-staff');
            const customerOnly = document.getElementById('customerOnlyFields');
            const staffInfo = document.getElementById('staffInfoField');
            const submitBtn = document.getElementById('registerSubmitBtn');

            if (role === 'staff') {
                customerFields.forEach(el => el.style.display = 'none');
                staffFields.forEach(el => el.style.display = '');
                if (customerOnly) customerOnly.style.display = 'none';
                if (staffInfo) staffInfo.style.display = '';
                // Make customer-only fields non-required
                document.getElementById('contact_number')?.removeAttribute('required');
                document.getElementById('address')?.removeAttribute('required');
                // Make staff name required
                document.getElementById('staff_name')?.setAttribute('required', 'required');
                document.getElementById('customer_name')?.removeAttribute('required');
                if (submitBtn) submitBtn.textContent = '✨ Create Staff Account';
            } else {
                customerFields.forEach(el => el.style.display = '');
                staffFields.forEach(el => el.style.display = 'none');
                if (customerOnly) customerOnly.style.display = '';
                if (staffInfo) staffInfo.style.display = 'none';
                // Restore customer field requirements
                document.getElementById('contact_number')?.setAttribute('required', 'required');
                document.getElementById('address')?.setAttribute('required', 'required');
                document.getElementById('customer_name')?.setAttribute('required', 'required');
                document.getElementById('staff_name')?.removeAttribute('required');
                if (submitBtn) submitBtn.textContent = '✨ Create Customer Account';
            }
        }

        // Initialize form state on page load
        document.addEventListener('DOMContentLoaded', function() {
            var initialRole = '<?php echo $activeRegisterRole; ?>';
            if (initialRole === 'staff') {
                switchRegisterRole('staff');
            }
        });

        // ── Mobile Nav Toggle ──────────────────────
        const navToggle = document.getElementById('navToggle');
        const navLinks = document.getElementById('navLinks');
        navToggle.addEventListener('click', () => {
            navLinks.classList.toggle('open');
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

        // ── Password Match Validation ──────────────
        const passwordInput = document.getElementById('password');
        const confirmInput = document.getElementById('confirm_password');
        const confirmError = document.getElementById('confirm_password_error');

        function checkPasswordMatch() {
            const password = passwordInput.value;
            const confirm = confirmInput.value;

            if (confirm && password !== confirm) {
                confirmError.textContent = 'Passwords do not match.';
                confirmError.style.display = 'block';
                confirmInput.style.borderColor = 'var(--danger)';
            } else if (confirm && password === confirm) {
                confirmError.style.display = 'none';
                confirmInput.style.borderColor = 'var(--success)';
            } else {
                confirmError.style.display = 'none';
                confirmInput.style.borderColor = '';
            }
        }

        passwordInput.addEventListener('input', checkPasswordMatch);
        confirmInput.addEventListener('input', checkPasswordMatch);

        // ── Real-time Password Strength Indicator ──
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            const errorEl = document.getElementById('password_error');

            if (password.length === 0) {
                errorEl.style.display = 'none';
                this.style.borderColor = '';
            } else if (password.length < 6) {
                errorEl.textContent = 'Password must be at least 6 characters.';
                errorEl.style.display = 'block';
                this.style.borderColor = 'var(--danger)';
            } else if (password.length < 8) {
                errorEl.textContent = 'Password strength: Fair (consider using 8+ characters).';
                errorEl.style.display = 'block';
                errorEl.style.color = 'var(--warning)';
                this.style.borderColor = 'var(--warning)';
            } else {
                errorEl.textContent = 'Password strength: Good ✓';
                errorEl.style.display = 'block';
                errorEl.style.color = 'var(--success)';
                this.style.borderColor = 'var(--success)';
            }
        });

        // ── Form Submission Validation ──────────────
        const form = document.querySelector('form[data-validate]');
        form.addEventListener('submit', function(e) {
            let hasErrors = false;
            const registerRole = document.getElementById('registerRole').value;
            const isStaff = (registerRole === 'staff');

            // Validate name (use the visible one)
            if (isStaff) {
                const name = document.getElementById('staff_name');
                if (name.value.trim().length < 2) {
                    showFieldError(name, 'staff_name_error', 'Name must be at least 2 characters.');
                    hasErrors = true;
                }
            } else {
                const name = document.getElementById('customer_name');
                if (name.value.trim().length < 2) {
                    showFieldError(name, 'customer_name_error', 'Name must be at least 2 characters.');
                    hasErrors = true;
                }
            }

            // Validate email (shared)
            const email = document.getElementById('email');
            const emailRegex = /^[^\s@]+@([^\s@]+\.)+[^\s@]+$/;
            if (!emailRegex.test(email.value.trim())) {
                showFieldError(email, 'email_error', 'Please enter a valid email address.');
                hasErrors = true;
            }

            // Validate password (shared)
            const password = document.getElementById('password');
            if (password.value.length < 6) {
                showFieldError(password, 'password_error', 'Password must be at least 6 characters.');
                hasErrors = true;
            }

            // Validate password confirmation (shared)
            const confirm = document.getElementById('confirm_password');
            if (password.value !== confirm.value) {
                showFieldError(confirm, 'confirm_password_error', 'Passwords do not match.');
                hasErrors = true;
            }

            // Customer-only validations
            if (!isStaff) {
                // Validate contact number
                const phone = document.getElementById('contact_number');
                const phoneRegex = /^[0-9]{8}$/;
                if (!phoneRegex.test(phone.value.trim())) {
                    showFieldError(phone, 'contact_number_error', 'Contact number must be 8 digits.');
                    hasErrors = true;
                }

                // Validate address
                const address = document.getElementById('address');
                if (address.value.trim().length < 10) {
                    showFieldError(address, 'address_error', 'Please provide a complete address (at least 10 characters).');
                    hasErrors = true;
                }
            }

            if (hasErrors) {
                e.preventDefault();
                // Scroll to first error
                const firstError = document.querySelector('.is-invalid');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });

        function showFieldError(field, errorId, message) {
            const errorEl = document.getElementById(errorId);
            if (errorEl) {
                errorEl.textContent = message;
                errorEl.style.display = 'block';
            }
            field.style.borderColor = 'var(--danger)';
            field.classList.add('is-invalid');
        }

        // Clear error on input
        document.querySelectorAll('input, textarea').forEach(input => {
            input.addEventListener('input', function() {
                this.style.borderColor = '';
                this.classList.remove('is-invalid');
                const errorId = this.id + '_error';
                const errorEl = document.getElementById(errorId);
                if (errorEl) {
                    errorEl.style.display = 'none';
                }
            });
        });

        // ── Login Modal ───────────────────────────
        function openLoginModal() {
            document.getElementById('loginModal').classList.add('show');
            // Reset to customer tab
            switchRole('customer');
            document.getElementById('login_user_id').focus();
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
            const userIdInput = document.getElementById('login_user_id');
            if (role === 'customer') {
                userIdInput.placeholder = 'Enter Customer ID (e.g., 1001)';
            } else {
                userIdInput.placeholder = 'Enter Staff ID (e.g., 1)';
            }
        }

        // ── Close modals on outside click ──────────
        window.addEventListener('click', (e) => {
            if (e.target === document.getElementById('loginModal')) closeLoginModal();
        });

        // ── Close modals on Escape key ─────────────
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeLoginModal();
            }
        });

        // ── Open login modal if there was an error ──
        <?php if ($loginError): ?>
        document.addEventListener('DOMContentLoaded', () => {
            openLoginModal();
        });
        <?php endif; ?>
    </script>
</body>
</html>