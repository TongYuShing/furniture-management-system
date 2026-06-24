<?php
/**
 * register_staff.php - Staff Registration Page
 * Allows administrators to create new staff accounts
 * Part II - Function #4: Staff Registration
 */

require_once '../inc/config.php';
require_once '../inc/session.php';
require_once '../inc/functions.php';

checkStaffRole();

$message = '';
$messageType = '';
$formData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'staff_name' => $_POST['staff_name'] ?? '',
        'email' => $_POST['email'] ?? '',
        'role' => $_POST['role'] ?? 'staff',
    ];

    $result = registerStaff($conn, [
        'staff_name' => $_POST['staff_name'] ?? '',
        'password' => $_POST['password'] ?? '',
        'confirm_password' => $_POST['confirm_password'] ?? '',
        'email' => $_POST['email'] ?? '',
        'role' => $_POST['role'] ?? 'staff',
    ]);

    if ($result['success']) {
        $message = $result['message'] . ' Staff ID: <strong>' . $result['staff_id'] . '</strong>';
        $messageType = 'success';
        $formData = [];
    } else {
        $message = $result['message'];
        $messageType = 'danger';
    }
}

// Get all staff for listing
$sql = "SELECT * FROM Staff ORDER BY StaffID ASC";
$allStaff = mysqli_query($conn, $sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Staff - Staff Panel</title>
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
            <aside class="sidebar">
                <h3>Staff Menu</h3>
                <ul>
                    <li><a href="dashboard.php">📊 Dashboard</a></li>
                    <li><a href="insert_furniture.php">➕ Insert Furniture</a></li>
                    <li><a href="update_furniture.php">✏️ Update Furniture</a></li>
                    <li><a href="insert_material.php">📦 Insert Material</a></li>
                    <li><a href="update_material.php">✏️ Update Material</a></li>
                    <li><a href="manage_customers.php">👥 Manage Customers</a></li>
                    <li><a href="register_customer.php">👤 Register Customer</a></li>
                    <li><a href="manage_orders.php">📋 Manage Orders</a></li>
                    <li><a href="manage_inquiries.php">📬 Inquiries</a></li>
                    <li><a href="register_staff.php" class="active">👔 Register Staff</a></li>
                    <li><a href="analytics.php">📊 Analytics</a></li>
                    <li><a href="generate_report.php">📈 Generate Report</a></li>
                    <li><a href="delete_material.php">🗑️ Delete Material</a></li>
                    <li><a href="delete_furniture.php">🗑️ Delete Furniture</a></li>
                </ul>
            </aside>

            <main class="main-content">
                <h2>Register New Staff Account</h2>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?>"><?php echo $message; ?></div>
                <?php endif; ?>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-8);">
                    <!-- Registration Form -->
                    <div style="background: var(--gray-50); padding: var(--space-6); border-radius: var(--radius-xl); border: 1px solid var(--gray-200);">
                        <h3>➕ New Staff Account</h3>
                        <form method="POST" action="">
                            <div class="form-group">
                                <label for="staff_name" class="required">Full Name</label>
                                <input type="text" name="staff_name" id="staff_name" class="form-control" required
                                       value="<?php echo htmlspecialchars($formData['staff_name'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="email" class="required">Email</label>
                                <input type="email" name="email" id="email" class="form-control" required
                                       value="<?php echo htmlspecialchars($formData['email'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="role">Role</label>
                                <select name="role" id="role" class="form-control">
                                    <option value="staff" <?php echo ($formData['role'] ?? '') === 'staff' ? 'selected' : ''; ?>>Staff</option>
                                    <option value="manager" <?php echo ($formData['role'] ?? '') === 'manager' ? 'selected' : ''; ?>>Manager</option>
                                    <option value="administrator" <?php echo ($formData['role'] ?? '') === 'administrator' ? 'selected' : ''; ?>>Administrator</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="password" class="required">Password</label>
                                <input type="password" name="password" id="password" class="form-control" required minlength="6">
                            </div>
                            <div class="form-group">
                                <label for="confirm_password" class="required">Confirm Password</label>
                                <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-primary btn-block">➕ Create Staff Account</button>
                        </form>
                    </div>

                    <!-- Existing Staff List -->
                    <div>
                        <h3>👥 Current Staff</h3>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Hired</th></tr>
                                </thead>
                                <tbody>
                                    <?php while ($s = mysqli_fetch_assoc($allStaff)): ?>
                                        <tr>
                                            <td><?php echo $s['StaffID']; ?></td>
                                            <td><?php echo htmlspecialchars($s['StaffName']); ?></td>
                                            <td><?php echo htmlspecialchars($s['Email']); ?></td>
                                            <td><span class="badge badge-info"><?php echo htmlspecialchars($s['Role']); ?></span></td>
                                            <td><?php echo $s['HireDate']; ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Premium Living Furniture Co. Ltd. All rights reserved.</p>
        </div>
    </footer>

    <script src="../assets/js/password-toggle.js"></script>
</body>
</html>