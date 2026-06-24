<?php
/**
 * update_profile.php - Customer Profile Update Page
 * Allows customer to update: Password, Contact Number, Address only
 */

require_once '../inc/config.php';
require_once '../inc/session.php';
require_once '../inc/functions.php';

// Check if user is logged in as customer
checkCustomerRole();

$customerId = $_SESSION['user_id'];
$customer = getCustomerById($conn, $customerId);

$message = '';
$messageType = '';

// Display info message if redirected for missing company name
if (isset($_SESSION['info'])) {
    $message = $_SESSION['info'];
    $messageType = 'info';
    unset($_SESSION['info']);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $companyName = sanitizeInput($_POST['company_name'] ?? '');
    $contactNumber = sanitizeInput($_POST['contact_number'] ?? '');
    $address = sanitizeInput($_POST['address'] ?? '');

    $errors = [];

    // Validate password (only update if provided)
    if (!empty($password)) {
        if (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters.';
        }
        if ($password !== $confirmPassword) {
            $errors[] = 'Passwords do not match.';
        }
    }
    
    // Validate contact number (Hong Kong format)
    if (!preg_match('/^[0-9]{8}$/', $contactNumber)) {
        $errors[] = 'Contact number must be 8 digits.';
    }
    
    // Validate address
    if (empty($address)) {
        $errors[] = 'Address is required.';
    } elseif (strlen($address) < 10) {
        $errors[] = 'Please provide a complete address.';
    }
    
    if (empty($errors)) {
        // If password is empty, keep existing password
        if (empty($password)) {
            // Only update contact number, address, and company name
            $sql = "UPDATE Customer SET ContactNumber = ?, Address = ?, CompanyName = ? WHERE CustomerID = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "sssi", $contactNumber, $address, $companyName, $customerId);
        } else {
            // Update all fields including password
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $sql = "UPDATE Customer SET Password = ?, ContactNumber = ?, Address = ?, CompanyName = ? WHERE CustomerID = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ssssi", $hashedPassword, $contactNumber, $address, $companyName, $customerId);
        }

        if (mysqli_stmt_execute($stmt)) {
            $message = 'Profile updated successfully!';
            $messageType = 'success';

            // Clear the company name missing flag
            unset($_SESSION['company_name_missing']);
            $_SESSION['company_name'] = $companyName;

            // Refresh customer data
            $customer = getCustomerById($conn, $customerId);
            $_SESSION['user_name'] = $customer['CustomerName'];
        } else {
            $message = 'Failed to update profile: ' . mysqli_error($conn);
            $messageType = 'danger';
        }
        mysqli_stmt_close($stmt);
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
    <title>Update Profile - Premium Living Furniture</title>
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
    </header>

    <div class="container">
        <div class="dashboard-layout">
            <!-- Sidebar Navigation -->
            <aside class="sidebar">
                <h3>Customer Menu</h3>
                <ul>
                    <li><a href="dashboard.php">🏠 Dashboard</a></li>
                    <li><a href="view_orders.php">📋 My Orders</a></li>
                    <li><a href="wishlist.php">❤️ Wishlist</a></li>
                    <li><a href="update_profile.php" class="active">👤 Update Profile</a></li>
                </ul>
                
                <div style="margin-top: var(--spacing-xl); padding: var(--spacing-md); background-color: var(--gray-lighter); border-radius: var(--border-radius);">
                    <h4>Profile Info</h4>
                    <p><strong>Customer ID:</strong> <?php echo $customer['CustomerID']; ?></p>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($customer['CustomerName']); ?></p>
                    <p><strong>Company:</strong> <?php echo !empty($customer['CompanyName']) ? htmlspecialchars($customer['CompanyName']) : '<em style="color:var(--danger);">Not set</em>'; ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($customer['Email']); ?></p>
                    <small>Note: Name and Email cannot be changed. Contact support for changes.</small>
                </div>
            </aside>

            <!-- Main Content -->
            <main class="main-content">
                <h2>Update Your Profile</h2>
                <p>You can update your password, contact number, and delivery address below.</p>
                
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" data-validate>
                    <!-- Password Section (Optional) -->
                    <h3>Change Password</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="password">New Password</label>
                            <input type="password" name="password" id="password" 
                                   class="form-control"
                                   data-type="password"
                                   minlength="6"
                                   placeholder="Leave blank to keep current password">
                            <div class="invalid-feedback" id="password_error"></div>
                            <small>Minimum 6 characters</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" name="confirm_password" id="confirm_password" 
                                   class="form-control"
                                   data-type="password"
                                   placeholder="Re-enter new password">
                            <div class="invalid-feedback" id="confirm_password_error"></div>
                        </div>
                    </div>
                    
                    <hr style="margin: var(--spacing-lg) 0;">

                    <!-- Company Information -->
                    <h3>Company Information</h3>
                    <div class="form-group">
                        <label for="company_name">Company / Organization Name <small>(optional)</small></label>
                        <input type="text" name="company_name" id="company_name"
                               class="form-control"
                               value="<?php echo htmlspecialchars($customer['CompanyName'] ?? ''); ?>"
                               placeholder="Enter your company or organization name (optional)">
                        <div class="invalid-feedback" id="company_name_error"></div>
                        <small>Optional — leave blank if not applicable.</small>
                    </div>

                    <hr style="margin: var(--spacing-lg) 0;">

                    <!-- Contact Information -->
                    <h3>Contact Information</h3>
                    <div class="form-group">
                        <label for="contact_number" class="required">Contact Number</label>
                        <input type="tel" name="contact_number" id="contact_number" 
                               class="form-control"
                               data-type="phone"
                               required
                               value="<?php echo htmlspecialchars($customer['ContactNumber']); ?>"
                               placeholder="8-digit Hong Kong number">
                        <div class="invalid-feedback" id="contact_number_error"></div>
                        <small>Format: 8 digits only (e.g., 91234567)</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="address" class="required">Delivery Address</label>
                        <textarea name="address" id="address" 
                                  class="form-control"
                                  required
                                  maxlength="500"
                                  rows="4"
                                  placeholder="Your full delivery address"><?php echo htmlspecialchars($customer['Address']); ?></textarea>
                        <div class="invalid-feedback" id="address_error"></div>
                    </div>
                    
                    <div class="form-group" style="margin-top: var(--spacing-xl);">
                        <button type="submit" class="btn btn-primary">Update Profile</button>
                        <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
                
                <!-- Warning Section -->
                <div class="alert alert-warning" style="margin-top: var(--spacing-xl);">
                    <strong>⚠️ Important Notes:</strong>
                    <ul style="margin-top: var(--spacing-sm); margin-left: var(--spacing-lg);">
                        <li>Your Customer ID and Name cannot be changed.</li>
                        <li>Email changes require contacting customer support.</li>
                        <li>Updated contact number will be used for order communications.</li>
                        <li>Updated address will be pre-filled in future orders.</li>
                    </ul>
                </div>
            </main>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 Premium Living Furniture Co. Ltd. All rights reserved.</p>
        </div>
    </footer>

    <script src="../assets/js/validation.js"></script>
    <script src="../assets/js/password-toggle.js"></script>
    <script>
        // Additional validation for password confirmation
        document.getElementById('confirm_password')?.addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirm = this.value;
            const errorElement = document.getElementById('confirm_password_error');
            
            if (password !== confirm) {
                errorElement.innerHTML = 'Passwords do not match.';
                errorElement.style.display = 'block';
                this.classList.add('is-invalid');
            } else {
                errorElement.style.display = 'none';
                this.classList.remove('is-invalid');
            }
        });
    </script>
</body>
</html>