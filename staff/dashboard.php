<?php
/**
 * dashboard.php - Staff Dashboard
 * Main landing page for staff after login
 */

require_once '../inc/config.php';
require_once '../inc/session.php';
require_once '../inc/functions.php';
require_once '../inc/usability.php';
require_once '../inc/advanced.php';

// Check if user is logged in as staff
checkStaffRole();

$staffId = $_SESSION['user_id'];
$staffName = $_SESSION['user_name'];

// Get statistics
// Total orders count
$sql = "SELECT COUNT(*) as total FROM Orders";
$result = mysqli_query($conn, $sql);
$totalOrders = mysqli_fetch_assoc($result)['total'];

// Pending orders count
$sql = "SELECT COUNT(*) as pending FROM Orders WHERE OrderStatus = 'pending'";
$result = mysqli_query($conn, $sql);
$pendingOrders = mysqli_fetch_assoc($result)['pending'];

// Total furniture products
$sql = "SELECT COUNT(*) as total FROM Furniture";
$result = mysqli_query($conn, $sql);
$totalProducts = mysqli_fetch_assoc($result)['total'];

// Low stock products (less than 5 units)
$sql = "SELECT COUNT(*) as low FROM Furniture WHERE StockQuantity > 0 AND StockQuantity < 5";
$result = mysqli_query($conn, $sql);
$lowStock = mysqli_fetch_assoc($result)['low'];

// Total sales (delivered orders)
$sql = "SELECT SUM(TotalOrderAmount) as total FROM Orders WHERE OrderStatus = 'delivered'";
$result = mysqli_query($conn, $sql);
$totalSales = mysqli_fetch_assoc($result)['total'] ?? 0;

// Recent orders (last 10)
$sql = "SELECT o.*, c.CustomerName, f.FurnitureName 
        FROM Orders o
        INNER JOIN Customer c ON o.CustomerID = c.CustomerID
        INNER JOIN Furniture f ON o.FurnitureID = f.FurnitureID
        ORDER BY o.OrderDate DESC LIMIT 10";
$recentOrders = mysqli_query($conn, $sql);

// Low stock alert products
$sql = "SELECT * FROM Furniture WHERE StockQuantity > 0 AND StockQuantity < 10 ORDER BY StockQuantity ASC LIMIT 5";
$lowStockProducts = mysqli_query($conn, $sql);

// Low stock materials
$sql = "SELECT * FROM Material WHERE ReorderLevel > 0 AND PhysicalQuantity <= ReorderLevel ORDER BY PhysicalQuantity ASC LIMIT 5";
$lowStockMaterials = mysqli_query($conn, $sql);

// Recent customer inquiries
$sql = "SELECT COUNT(*) AS total FROM ContactInquiries";
$inqResult = mysqli_query($conn, $sql);
$totalInquiries = mysqli_fetch_assoc($inqResult)['total'] ?? 0;
$sql = "SELECT * FROM ContactInquiries ORDER BY CreatedAt DESC LIMIT 5";
$recentInquiries = mysqli_query($conn, $sql);

// Monthly sales for chart (last 6 months)
$sql = "SELECT DATE_FORMAT(OrderDate, '%Y-%m') as month, SUM(TotalOrderAmount) as total
        FROM Orders WHERE OrderStatus = 'delivered'
        GROUP BY month ORDER BY month ASC LIMIT 6";
$chartData = mysqli_query($conn, $sql);
$chartMonths = [];
$chartSales = [];
while ($r = mysqli_fetch_assoc($chartData)) {
    $chartMonths[] = $r['month'];
    $chartSales[] = (float)$r['total'];
}

// Display session messages
if (isset($_SESSION['success'])) {
    $successMessage = $_SESSION['success'];
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $errorMessage = $_SESSION['error'];
    unset($_SESSION['error']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - Premium Living Furniture</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                <span>Welcome, <?php echo htmlspecialchars($staffName); ?> (Staff)</span>
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
                    <li><a href="dashboard.php" class="active">📊 Dashboard</a></li>
                    <li><a href="insert_furniture.php">➕ Insert Furniture</a></li>
                    <li><a href="update_furniture.php">✏️ Update Furniture</a></li>
                    <li><a href="insert_material.php">📦 Insert Material</a></li>
                    <li><a href="update_material.php">✏️ Update Material</a></li>
                    <li><a href="manage_customers.php">👥 Manage Customers</a></li>
                    <li><a href="register_customer.php">👤 Register Customer</a></li>
                    <li><a href="manage_orders.php">📋 Manage Orders</a></li>
                    <li><a href="manage_inquiries.php">📬 Inquiries</a></li>
                    <li><a href="register_staff.php">👔 Register Staff</a></li>
                    <li><a href="analytics.php">📊 Analytics</a></li>
                    <li><a href="generate_report.php">📈 Generate Report</a></li>
                    <li><a href="delete_material.php">🗑️ Delete Material</a></li>
                    <li><a href="delete_furniture.php">🗑️ Delete Furniture</a></li>
                </ul>
                <?php $emailCount = getEmailQueueCount(); ?>
                <div style="margin-top: var(--space-4); padding: var(--space-3); background: <?php echo $emailCount > 0 ? 'var(--info-bg)' : 'var(--gray-50)'; ?>; border-radius: var(--radius); font-size: var(--font-size-xs);">
                    <strong>📬 Email Queue:</strong> <?php echo $emailCount; ?> pending
                </div>
            </aside>

            <!-- Main Content -->
            <main class="main-content">
                <h2>Staff Dashboard</h2>

                <?php echo breadcrumbs(['🏠 Home' => '../index.php', '📊 Dashboard' => '#']); ?>

                <?php if (isset($successMessage)): ?>
                    <div class="alert alert-success"><?php echo $successMessage; ?></div>
                <?php endif; ?>
                
                <?php if (isset($errorMessage)): ?>
                    <div class="alert alert-danger"><?php echo $errorMessage; ?></div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="stats-grid" style="grid-template-columns: repeat(6, 1fr);">
                    <div class="stat-card" style="grid-column: span 2;">
                        <h3>Total Orders</h3>
                        <div class="stat-number"><?php echo $totalOrders; ?></div>
                    </div>
                    <div class="stat-card" style="grid-column: span 2;">
                        <h3>Pending Orders</h3>
                        <div class="stat-number"><?php echo $pendingOrders; ?></div>
                    </div>
                    <div class="stat-card" style="grid-column: span 2;">
                        <h3>Total Products</h3>
                        <div class="stat-number"><?php echo $totalProducts; ?></div>
                    </div>
                    <div class="stat-card" style="grid-column: span 3;">
                        <h3>Low Stock Items</h3>
                        <div class="stat-number"><?php echo $lowStock; ?></div>
                    </div>
                    <div class="stat-card" style="grid-column: span 3;">
                        <h3>Total Sales</h3>
                        <div class="stat-number"><?php echo formatCurrency($totalSales); ?></div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div style="margin-bottom: var(--spacing-xl); display: flex; gap: var(--spacing-md); flex-wrap: wrap;">
                    <a href="insert_furniture.php" class="btn btn-primary">➕ Add New Furniture</a>
                    <a href="insert_material.php" class="btn btn-secondary">📦 Add New Material</a>
                    <a href="register_customer.php" class="btn btn-accent">👤 Register Customer</a>
                    <a href="manage_orders.php?status=pending" class="btn btn-warning">⏳ Process Pending Orders</a>
                    <a href="generate_report.php" class="btn btn-info">📊 View Sales Report</a>
                </div>

                <!-- Quick Actions Panel -->
                <?php echo quickActionsPanel(); ?>

                <!-- Low Stock Alert -->
                <?php if (mysqli_num_rows($lowStockProducts) > 0): ?>
                    <div class="alert alert-warning">
                        <strong>⚠️ Low Stock Alert!</strong> The following products are running low:
                        <ul style="margin-top: var(--spacing-sm); margin-left: var(--spacing-lg);">
                            <?php while ($product = mysqli_fetch_assoc($lowStockProducts)): ?>
                                <li><?php echo htmlspecialchars($product['FurnitureName']); ?> - Only <?php echo $product['StockQuantity']; ?> units left</li>
                            <?php endwhile; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- Material Low Stock Alert -->
                <?php if (mysqli_num_rows($lowStockMaterials) > 0): ?>
                    <div class="alert alert-danger" style="margin-bottom: var(--space-6);">
                        <strong>📦 Material Restock Needed!</strong>
                        <ul style="margin-top: var(--space-2); margin-left: var(--space-4);">
                            <?php while ($mat = mysqli_fetch_assoc($lowStockMaterials)): ?>
                                <li><?php echo htmlspecialchars($mat['MaterialName']); ?> — Only <?php echo number_format($mat['PhysicalQuantity'], 2); ?> <?php echo htmlspecialchars($mat['Unit']); ?> remaining (reorder at <?php echo number_format($mat['ReorderLevel'], 2); ?>)</li>
                            <?php endwhile; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- Sales Trend Chart -->
                <?php if (!empty($chartMonths)): ?>
                    <h3>📈 Sales Trend (Last 6 Months)</h3>
                    <canvas id="dashboardSalesChart" style="max-height: 250px; margin-bottom: var(--space-8);"></canvas>
                <?php endif; ?>

                <!-- Recent Orders -->
                <h3>Recent Orders</h3>
                <?php if (mysqli_num_rows($recentOrders) > 0): ?>
                    <div class="table-container">
                        <table data-sortable>
                            <thead>
                                <tr>
                                    <th class="sortable">Order ID</th>
                                    <th class="sortable">Customer</th>
                                    <th class="sortable">Product</th>
                                    <th class="sortable">Quantity</th>
                                    <th class="sortable">Total</th>
                                    <th class="sortable">Order Date</th>
                                    <th class="sortable">Delivery Date</th>
                                    <th class="sortable">Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($order = mysqli_fetch_assoc($recentOrders)): ?>
                                    <tr>
                                        <td><?php echo $order['OrderID']; ?></td>
                                        <td><?php echo htmlspecialchars($order['CustomerName']); ?></td>
                                        <td><?php echo htmlspecialchars($order['FurnitureName']); ?></td>
                                        <td><?php echo $order['OrderQuantity']; ?></td>
                                        <td><?php echo formatCurrency($order['TotalOrderAmount']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($order['OrderDate'])); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($order['DeliveryDate'])); ?></td>
                                        <td><?php echo getOrderStatusBadge($order['OrderStatus']); ?></td>
                                        <td>
                                            <a href="manage_orders.php?order_id=<?php echo $order['OrderID']; ?>" 
                                               class="btn btn-sm btn-info">Manage</a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <a href="manage_orders.php" class="btn btn-outline-primary">View All Orders →</a>
                <?php else: ?>
                    <div class="alert alert-info">No orders found.</div>
                <?php endif; ?>

                <!-- Recent Customer Inquiries -->
                <h3 style="margin-top: var(--space-8);">📬 Recent Customer Inquiries
                    <?php if ($totalInquiries > 0): ?>
                        <span style="background:var(--primary);color:#fff;padding:2px 10px;border-radius:12px;font-size:0.75rem;vertical-align:middle;margin-left:8px;"><?php echo $totalInquiries; ?></span>
                    <?php endif; ?>
                </h3>
                <?php if ($totalInquiries > 0 && mysqli_num_rows($recentInquiries) > 0): ?>
                    <div style="display:flex;flex-direction:column;gap:var(--space-3);">
                        <?php while ($inq = mysqli_fetch_assoc($recentInquiries)): ?>
                            <div style="background:var(--white);border:1px solid var(--gray-200);border-radius:var(--radius-lg);padding:var(--space-4);">
                                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:var(--space-2);margin-bottom:var(--space-2);">
                                    <div>
                                        <strong><?php echo htmlspecialchars($inq['CustomerName']); ?></strong>
                                        <span style="color:var(--gray-400);margin-left:var(--space-3);font-size:0.85rem;">
                                            <?php echo htmlspecialchars($inq['Email']); ?>
                                        </span>
                                        <?php if (!empty($inq['Subject'])): ?>
                                            <span style="display:inline-block;margin-left:var(--space-2);background:var(--primary-surface);color:var(--primary);padding:1px 8px;border-radius:10px;font-size:0.75rem;font-weight:600;">
                                                <?php echo htmlspecialchars($inq['Subject']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <span style="font-size:0.75rem;color:var(--gray-400);white-space:nowrap;">
                                        <?php echo date('d M Y', strtotime($inq['CreatedAt'])); ?>
                                    </span>
                                </div>
                                <p style="color:var(--gray-600);font-size:0.9rem;line-height:1.5;margin:0;">
                                    <?php echo htmlspecialchars(substr($inq['Message'], 0, 150)); ?><?php echo strlen($inq['Message']) > 150 ? '…' : ''; ?>
                                </p>
                            </div>
                        <?php endwhile; ?>
                    </div>
                    <a href="manage_inquiries.php" class="btn btn-outline-primary" style="margin-top:var(--space-4);">📬 View All Inquiries →</a>
                <?php else: ?>
                    <div class="alert alert-info">📭 No customer inquiries yet. Messages from the contact form will appear here.</div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 Premium Living Furniture Co. Ltd. All rights reserved.</p>
            <p>Staff Management Portal</p>
        </div>
    </footer>

    <script src="../assets/js/validation.js"></script>
    <script src="../assets/js/usability.js"></script>
    <?php if (!empty($chartMonths)): ?>
    <script>
        var ctx = document.getElementById('dashboardSalesChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($chartMonths); ?>,
                datasets: [{
                    label: 'Monthly Sales ($)',
                    data: <?php echo json_encode($chartSales); ?>,
                    backgroundColor: 'rgba(26, 60, 42, 0.7)',
                    borderColor: '#1a3c2a',
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) { return '$' + ctx.raw.toFixed(2); }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { callback: function(v) { return '$' + v; } }
                    }
                }
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>