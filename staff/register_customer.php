<?php
/**
 * register_customer.php - Staff Customer Registration Page
 * Allows staff members to create new customer accounts
 * Part I - Function #1: Customer Registration (Staff-side)
 */

require_once '../inc/config.php';
require_once '../inc/session.php';
require_once '../inc/functions.php';

// Check if user is logged in as staff
checkStaffRole();

$message = '';
$messageType = '';
$newCustomerId = null;
$formData = [];

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'customer_name' => $_POST['customer_name'] ?? '',
        'email' => $_POST['email'] ?? '',
        'contact_number' => $_POST['contact_number'] ?? '',
        'address' => $_POST['address'] ?? '',
        'company_name' => $_POST['company_name'] ?? '',
    ];

    $result = registerCustomer($conn, [
        'customer_name' => $_POST['customer_name'] ?? '',
        'password' => $_POST['password'] ?? '',
        'confirm_password' => $_POST['confirm_password'] ?? '',
        'contact_number' => $_POST['contact_number'] ?? '',
        'address' => $_POST['address'] ?? '',
        'email' => $_POST['email'] ?? '',
        'company_name' => $_POST['company_name'] ?? '',
    ]);

    if ($result['success']) {
        $newCustomerId = $result['customer_id'];
        $message = '🎉 Customer account created successfully! New Customer ID: <strong>' . $newCustomerId . '</strong>';
        $messageType = 'success';
        $formData = []; // Clear form on success
    } else {
        $messageType = 'danger';
        // Parse server-side error into per-field errors for inline display
        $fieldErrors = mapErrorToField($result['message']);
        // If error couldn't be mapped to a specific field, show it as a general alert
        if (empty($fieldErrors)) {
            $message = $result['message'];
        }
    }
}

/**
 * Map registerCustomer() error messages to field error IDs.
 * Returns associative array: ['field_id' => 'error message', ...]
 */
function mapErrorToField($errorMsg) {
    $msg = strtolower($errorMsg);
    $map = [];
    if (strpos($msg, 'name must be at least') !== false) {
        $map['customer_name'] = $errorMsg;
    }
    if (strpos($msg, 'valid email') !== false || strpos($msg, 'account with this email') !== false) {
        $map['email'] = $errorMsg;
    }
    if (strpos($msg, 'password must be at least') !== false) {
        $map['password'] = $errorMsg;
    }
    if (strpos($msg, 'passwords do not match') !== false) {
        $map['confirm_password'] = $errorMsg;
    }
    if (strpos($msg, 'contact number must be') !== false) {
        $map['contact_number'] = $errorMsg;
    }
    if (strpos($msg, 'complete address') !== false) {
        $map['address'] = $errorMsg;
    }
    if (strpos($msg, 'all required fields') !== false) {
        // General error — flag all required fields
        $map['customer_name'] = 'This field is required.';
        $map['email'] = 'This field is required.';
        $map['password'] = 'This field is required.';
        $map['confirm_password'] = 'This field is required.';
        $map['contact_number'] = 'This field is required.';
        $map['address'] = 'This field is required.';
    }
    return $map;
}

// Helper to output inline error for a field
function fieldError($fieldId, $fieldErrors) {
    if (isset($fieldErrors[$fieldId])) {
        return 'display: block;';
    }
    return 'display: none;';
}
function fieldErrorMsg($fieldId, $fieldErrors) {
    return $fieldErrors[$fieldId] ?? '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Customer - Staff Panel</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header class="header">
        <div class="container">
            <a href="../index.php" style="text-decoration:none;">
            <div class="logo">
                <h1>Premium Living Furniture</h1>
                <p>Staff Management Portal</p>
            </div>
            </a>
            <div class="user-info">
    <span>👔 Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?> (Staff)</span>
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
            <!-- Sidebar Navigation -->
            <aside class="sidebar">
                <h3>Staff Menu</h3>
                <ul>
                    <li><a href="dashboard.php">📊 Dashboard</a></li>
                    <li><a href="insert_furniture.php">➕ Insert Furniture</a></li>
                    <li><a href="update_furniture.php">✏️ Update Furniture</a></li>
                    <li><a href="insert_material.php">📦 Insert Material</a></li>
                    <li><a href="update_material.php">✏️ Update Material</a></li>
                    <li><a href="manage_customers.php">👥 Manage Customers</a></li>
                    <li><a href="register_customer.php" class="active">👤 Register Customer</a></li>
                    <li><a href="manage_orders.php">📋 Manage Orders</a></li>
                    <li><a href="manage_inquiries.php">📬 Inquiries</a></li>
                    <li><a href="register_staff.php">👔 Register Staff</a></li>
                    <li><a href="analytics.php">📊 Analytics</a></li>
                    <li><a href="generate_report.php">📈 Generate Report</a></li>
                    <li><a href="delete_material.php">🗑️ Delete Material</a></li>
                    <li><a href="delete_furniture.php">🗑️ Delete Furniture</a></li>
                </ul>
            </aside>

            <!-- Main Content -->
            <main class="main-content">
                <h2>Register New Customer</h2>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?>" style="margin-bottom: var(--space-6);">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-8);">
                    <!-- Registration Form -->
                    <div style="background: var(--gray-50); padding: var(--space-6); border-radius: var(--radius-xl); border: 1px solid var(--gray-200);">
                        <h3>👤 New Customer Account</h3>
                        <p style="color: var(--gray-500); font-size: var(--font-size-sm); margin-bottom: var(--space-5);">
                            Create a new customer account. The customer can sign in with their assigned Customer ID and password.
                        </p>
                        <form method="POST" action="" data-validate novalidate>
                            <!-- Personal Information -->
                            <h4 style="margin-bottom: var(--space-4);">Personal Information</h4>

                            <div class="form-group">
                                <label for="customer_name" class="required">Full Name</label>
                                <input type="text" name="customer_name" id="customer_name"
                                       class="form-control"
                                       required
                                       minlength="2"
                                       maxlength="100"
                                       value="<?php echo htmlspecialchars($formData['customer_name'] ?? ''); ?>"
                                       placeholder="Enter full name (e.g., John Smith)"
                                       style="<?php echo isset($fieldErrors['customer_name']) ? 'border-color: var(--danger);' : ''; ?>">
                                <div class="invalid-feedback" id="customer_name_error"
                                     style="color: var(--danger); font-size: var(--font-size-xs); margin-top: var(--space-1); <?php echo fieldError('customer_name', $fieldErrors ?? []); ?>"><?php echo htmlspecialchars(fieldErrorMsg('customer_name', $fieldErrors ?? [])); ?></div>
                            </div>

                            <div class="form-group">
                                <label for="email" class="required">Email Address</label>
                                <input type="email" name="email" id="email"
                                       class="form-control"
                                       required
                                       maxlength="100"
                                       value="<?php echo htmlspecialchars($formData['email'] ?? ''); ?>"
                                       placeholder="customer@email.com"
                                       style="<?php echo isset($fieldErrors['email']) ? 'border-color: var(--danger);' : ''; ?>">
                                <div class="invalid-feedback" id="email_error"
                                     style="color: var(--danger); font-size: var(--font-size-xs); margin-top: var(--space-1); <?php echo fieldError('email', $fieldErrors ?? []); ?>"><?php echo htmlspecialchars(fieldErrorMsg('email', $fieldErrors ?? [])); ?></div>
                            </div>

                            <!-- Password Section -->
                            <h4 style="margin-top: var(--space-5); margin-bottom: var(--space-4);">🔒 Security</h4>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="password" class="required">Password</label>
                                    <input type="password" name="password" id="password"
                                           class="form-control"
                                           required
                                           minlength="6"
                                           placeholder="At least 6 characters"
                                           style="<?php echo isset($fieldErrors['password']) ? 'border-color: var(--danger);' : ''; ?>">
                                    <div class="invalid-feedback" id="password_error"
                                         style="color: var(--danger); font-size: var(--font-size-xs); margin-top: var(--space-1); <?php echo fieldError('password', $fieldErrors ?? []); ?>"><?php echo htmlspecialchars(fieldErrorMsg('password', $fieldErrors ?? [])); ?></div>
                                    <small>Minimum 6 characters.</small>
                                </div>

                                <div class="form-group">
                                    <label for="confirm_password" class="required">Confirm Password</label>
                                    <input type="password" name="confirm_password" id="confirm_password"
                                           class="form-control"
                                           required
                                           placeholder="Re-enter password"
                                           style="<?php echo isset($fieldErrors['confirm_password']) ? 'border-color: var(--danger);' : ''; ?>">
                                    <div class="invalid-feedback" id="confirm_password_error"
                                         style="color: var(--danger); font-size: var(--font-size-xs); margin-top: var(--space-1); <?php echo fieldError('confirm_password', $fieldErrors ?? []); ?>"><?php echo htmlspecialchars(fieldErrorMsg('confirm_password', $fieldErrors ?? [])); ?></div>
                                </div>
                            </div>

                            <!-- Contact Information -->
                            <h4 style="margin-top: var(--space-5); margin-bottom: var(--space-4);">📞 Contact Information</h4>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="contact_number" class="required">Contact Number</label>
                                    <input type="tel" name="contact_number" id="contact_number"
                                           class="form-control"
                                           required
                                           maxlength="20"
                                           value="<?php echo htmlspecialchars($formData['contact_number'] ?? ''); ?>"
                                           placeholder="8-digit HK number (e.g., 91234567)"
                                       style="<?php echo isset($fieldErrors['contact_number']) ? 'border-color: var(--danger);' : ''; ?>">
                                    <div class="invalid-feedback" id="contact_number_error"
                                         style="color: var(--danger); font-size: var(--font-size-xs); margin-top: var(--space-1); <?php echo fieldError('contact_number', $fieldErrors ?? []); ?>"><?php echo htmlspecialchars(fieldErrorMsg('contact_number', $fieldErrors ?? [])); ?></div>
                                    <small>Format: 8 digits only</small>
                                </div>

                                <div class="form-group">
                                    <label for="company_name">Company / Organization</label>
                                    <input type="text" name="company_name" id="company_name"
                                           class="form-control"
                                           maxlength="100"
                                           value="<?php echo htmlspecialchars($formData['company_name'] ?? ''); ?>"
                                           placeholder="Optional">
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
                                          required
                                          minlength="10"
                                          maxlength="500"
                                          rows="3"
                                          placeholder="Full delivery address (e.g., 15 Canton Road, Tsim Sha Tsui, Hong Kong)"
                                          style="<?php echo isset($fieldErrors['address']) ? 'border-color: var(--danger);' : ''; ?>"><?php echo htmlspecialchars($formData['address'] ?? ''); ?></textarea>
                                <div class="invalid-feedback" id="address_error"
                                     style="color: var(--danger); font-size: var(--font-size-xs); margin-top: var(--space-1); <?php echo fieldError('address', $fieldErrors ?? []); ?>"><?php echo htmlspecialchars(fieldErrorMsg('address', $fieldErrors ?? [])); ?></div>
                                <small>This will be the default delivery address for orders.</small>
                            </div>

                            <!-- Submit -->
                            <div class="form-group" style="margin-top: var(--space-7);">
                                <button type="submit" class="btn btn-accent btn-lg btn-block">
                                    ✨ Create Customer Account
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Info Panel -->
                    <div>
                        <h3>📋 About Customer Registration</h3>

                        <div style="background: var(--white); padding: var(--space-5); border-radius: var(--radius-lg); border: 1px solid var(--gray-100); margin-bottom: var(--space-5);">
                            <h4 style="margin-bottom: var(--space-3);">✅ What Happens After Registration?</h4>
                            <ul style="margin-left: var(--space-5); color: var(--gray-600); line-height: 1.8;">
                                <li>A new <strong>Customer ID</strong> will be auto-generated</li>
                                <li>The customer can sign in using this ID and the password you set</li>
                                <li>Password is securely hashed with <strong>bcrypt</strong></li>
                                <li>An email notification will be queued</li>
                                <li>The customer can start placing orders immediately</li>
                            </ul>
                        </div>

                        <div style="background: var(--white); padding: var(--space-5); border-radius: var(--radius-lg); border: 1px solid var(--gray-100); margin-bottom: var(--space-5);">
                            <h4 style="margin-bottom: var(--space-3);">💡 Tips</h4>
                            <ul style="margin-left: var(--space-5); color: var(--gray-600); line-height: 1.8;">
                                <li>Use a temporary password and ask the customer to change it later</li>
                                <li>The contact number must be 8 digits (Hong Kong format)</li>
                                <li>You can set the company name later from Manage Customers</li>
                                <li>After creating the account, share the <strong>Customer ID</strong> with the customer</li>
                            </ul>
                        </div>

                        <?php if ($newCustomerId): ?>
                            <div style="background: var(--success-bg, #d4edda); padding: var(--space-5); border-radius: var(--radius-lg); border: 2px solid var(--success); text-align: center;">
                                <h4 style="color: var(--success); margin-bottom: var(--space-3);">🎉 Account Created!</h4>
                                <p style="font-size: 1.1rem; margin-bottom: var(--space-2);">
                                    New Customer ID:
                                </p>
                                <div style="font-size: 2.5rem; font-weight: 800; color: var(--success); letter-spacing: 2px;">
                                    <?php echo $newCustomerId; ?>
                                </div>
                                <p style="color: var(--gray-500); font-size: var(--font-size-sm); margin-top: var(--space-3);">
                                    Please provide this ID to the customer so they can sign in.
                                </p>
                            </div>
                        <?php endif; ?>

                        <div style="margin-top: var(--space-5); text-align: center;">
                            <a href="manage_customers.php" class="btn btn-outline">
                                👥 View All Customers →
                            </a>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Premium Living Furniture Co. Ltd. All rights reserved.</p>
            <p>Staff Management Portal</p>
        </div>
    </footer>

    <script src="../assets/js/validation.js"></script>
    <script src="../assets/js/password-toggle.js"></script>
    <script>
        // ── Real-time Password Match Validation ─────
        (function() {
            var pw = document.getElementById('password');
            var cpw = document.getElementById('confirm_password');
            if (!pw || !cpw) return;

            function getErrEl(id) { return document.getElementById(id); }

            function showErr(field, errId, msg) {
                field.style.borderColor = '#dc2626';
                field.classList.add('is-invalid');
                var el = getErrEl(errId);
                if (el) { el.textContent = msg; el.style.display = 'block'; }
            }
            function clearErr(field, errId) {
                field.style.borderColor = '';
                field.classList.remove('is-invalid');
                var el = getErrEl(errId);
                if (el) { el.textContent = ''; el.style.display = 'none'; }
            }

            // Confirm match check
            function checkMatch() {
                if (cpw.value && pw.value !== cpw.value) {
                    showErr(cpw, 'confirm_password_error', 'Passwords do not match.');
                } else if (cpw.value && pw.value === cpw.value) {
                    clearErr(cpw, 'confirm_password_error');
                    cpw.style.borderColor = 'var(--success)';
                } else {
                    clearErr(cpw, 'confirm_password_error');
                }
            }
            pw.addEventListener('input', checkMatch);
            cpw.addEventListener('input', checkMatch);

            // Password strength
            pw.addEventListener('input', function() {
                if (this.value.length === 0) {
                    clearErr(this, 'password_error');
                } else if (this.value.length < 6) {
                    showErr(this, 'password_error', 'Password must be at least 6 characters.');
                } else {
                    clearErr(this, 'password_error');
                    this.style.borderColor = 'var(--success)';
                }
            });

            // Clear errors on input
            document.querySelectorAll('input, textarea').forEach(function(input) {
                input.addEventListener('input', function() {
                    clearErr(this, this.id + '_error');
                });
            });
        })();
    </script>
</body>
</html>
