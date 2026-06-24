<?php
/**
 * dashboard.php - Customer Dashboard
 * Main landing page for customers after login
 */

// Display session messages
if (isset($_SESSION['success'])) {
    $successMessage = $_SESSION['success'];
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $errorMessage = $_SESSION['error'];
    unset($_SESSION['error']);
}

require_once '../inc/config.php';
require_once '../inc/session.php';
require_once '../inc/functions.php';

// Check if user is logged in as customer
checkCustomerRole();

$customerId = $_SESSION['user_id'];
$customerName = $_SESSION['user_name'];

// Get customer info
$customer = getCustomerById($conn, $customerId);

// Get recent orders (last 5)
$recentOrders = getOrdersByCustomerId($conn, $customerId, 'OrderDate', 'DESC');
$recentOrders = array_slice($recentOrders, 0, 5);

// Get total orders count and total spent
$sql = "SELECT COUNT(*) as order_count, SUM(TotalOrderAmount) as total_spent
        FROM Orders WHERE CustomerID = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $customerId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$stats = mysqli_fetch_assoc($result);
$totalOrders = $stats['order_count'] ?? 0;
$totalSpent = $stats['total_spent'] ?? 0;

// Get pending orders count
$sql = "SELECT COUNT(*) as pending_count FROM Orders WHERE CustomerID = ? AND OrderStatus = 'pending'";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $customerId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$pendingStats = mysqli_fetch_assoc($result);
$pendingOrders = $pendingStats['pending_count'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard - Premium Living Furniture</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="logo">
                <h1>Premium Living Furniture</h1>
                <p>Quality Furniture for Your Home</p>
            </div>
            <div class="user-info">
                <span>Welcome, <?php echo htmlspecialchars($customerName); ?>!</span>
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
                <h3>Customer Menu</h3>
                <ul>
                    <li><a href="dashboard.php" class="active">🏠 Dashboard</a></li>
                    <li><a href="view_orders.php">📋 My Orders</a></li>
                    <li><a href="wishlist.php">❤️ Wishlist</a></li>
                    <li><a href="update_profile.php">👤 Update Profile</a></li>
                </ul>
            </aside>

            <!-- Main Content -->
            <main class="main-content">
                <h2>Welcome Back, <?php echo htmlspecialchars($customerName); ?>!</h2>
                
                <!-- Statistics Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>Total Orders</h3>
                        <div class="stat-number"><?php echo $totalOrders; ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Pending Orders</h3>
                        <div class="stat-number"><?php echo $pendingOrders; ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Total Spent</h3>
                        <div class="stat-number"><?php echo formatCurrency($totalSpent); ?></div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div style="margin-bottom: var(--spacing-xl);">
                    <a href="../index.php#products" class="btn btn-primary btn-lg">🛒 Start Shopping</a>
                    <a href="view_orders.php" class="btn btn-secondary btn-lg">📋 View My Orders</a>
                </div>

                <!-- Recent Orders -->
                <h3>Recent Orders</h3>
                <?php if (empty($recentOrders)): ?>
                    <div class="alert alert-info">
                        You haven't placed any orders yet. 
                        <a href="../index.php#products">Start shopping now!</a>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Product</th>
                                    <th>Quantity</th>
                                    <th>Total Amount</th>
                                    <th>Order Date</th>
                                    <th>Delivery Date</th>
                                    <th>Status</th>
                                    <th>Payment</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentOrders as $order): ?>
                                    <tr>
                                        <td><?php echo $order['OrderID']; ?></td>
                                        <td><?php echo htmlspecialchars($order['FurnitureName']); ?></td>
                                        <td><?php echo $order['OrderQuantity']; ?></td>
                                        <td><?php echo formatCurrency($order['TotalOrderAmount']); ?></td>
                                        <td><?php echo $order['OrderDate']; ?></td>
                                        <td><?php echo $order['DeliveryDate']; ?></td>
                                        <td><?php echo getOrderStatusBadge($order['OrderStatus']); ?></td>
                                        <td style="white-space:nowrap;font-size:0.85rem;">
                                            <?php echo isset($order['PaymentMethod']) ? getPaymentMethodLabel($order['PaymentMethod']) : '<span style="color:var(--gray-400);">—</span>'; ?>
                                            <?php echo isset($order['PaymentStatus']) ? getPaymentStatusBadge($order['PaymentStatus']) : ''; ?>
                                        </td>
                                        <td>
                                            <a href="view_orders.php?order_id=<?php echo $order['OrderID']; ?>"
                                               class="btn btn-sm btn-info">View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <a href="view_orders.php" class="btn btn-outline-primary">View All Orders →</a>
                <?php endif; ?>

                <!-- Customer Info Summary -->
                <h3>Your Profile</h3>
                <div class="info-card" style="background-color: var(--gray-lighter); padding: var(--spacing-lg); border-radius: var(--border-radius);">
                    <p><strong>Customer ID:</strong> <?php echo $customer['CustomerID']; ?></p>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($customer['CustomerName']); ?></p>
                    <p><strong>Company:</strong> <?php echo !empty($customer['CompanyName']) ? htmlspecialchars($customer['CompanyName']) : '<em style="color:var(--danger);">Not set — <a href="update_profile.php">Add now</a></em>'; ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($customer['Email']); ?></p>
                    <p><strong>Contact Number:</strong> <?php echo htmlspecialchars($customer['ContactNumber']); ?></p>
                    <p><strong>Address:</strong> <?php echo htmlspecialchars($customer['Address']); ?></p>
                    <a href="update_profile.php" class="btn btn-sm btn-primary">Update Profile</a>
                </div>
            </main>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 Premium Living Furniture Co. Ltd. All rights reserved.</p>
            <p>Centralized Management System</p>
        </div>
    </footer>
</body>
</html>