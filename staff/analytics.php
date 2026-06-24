<?php
/**
 * analytics.php - Business Analytics & Decision Support Dashboard
 * Part III: Data and Analysis Functions
 */

require_once '../inc/config.php';
require_once '../inc/session.php';
require_once '../inc/functions.php';

checkStaffRole();

// ── KPI Comparisons ─────────────────────────
$salesComparison = getMonthlyComparison($conn,
    "SELECT SUM(TotalOrderAmount) as total FROM Orders WHERE OrderStatus='delivered' AND DATE_FORMAT(OrderDate,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m')",
    "SELECT SUM(TotalOrderAmount) as total FROM Orders WHERE OrderStatus='delivered' AND DATE_FORMAT(OrderDate,'%Y-%m')=DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH),'%Y-%m')"
);
$orderComparison = getMonthlyComparison($conn,
    "SELECT COUNT(*) as total FROM Orders WHERE DATE_FORMAT(OrderDate,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m')",
    "SELECT COUNT(*) as total FROM Orders WHERE DATE_FORMAT(OrderDate,'%Y-%m')=DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH),'%Y-%m')"
);
$customerComparison = getMonthlyComparison($conn,
    "SELECT COUNT(*) as total FROM Customer WHERE DATE_FORMAT(RegistrationDate,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m')",
    "SELECT COUNT(*) as total FROM Customer WHERE DATE_FORMAT(RegistrationDate,'%Y-%m')=DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH),'%Y-%m')"
);

// ── Data for Charts & Tables ────────────────
$topProducts = getTopProductsByRevenue($conn, 5);
$topCustomers = getTopCustomersBySpend($conn, 5);
$orderDistribution = getOrderStatusDistribution($conn);
$monthlyTrend = getMonthlyRevenueTrend($conn, 6);
$inventoryHealth = getInventoryHealth($conn);
$revenueByCategory = getRevenueByCategory($conn);

// ── Overall Totals ──────────────────────────
$totalRevenue = array_sum(array_column($monthlyTrend, 'revenue'));
$totalOrders = array_sum(array_column($monthlyTrend, 'order_count'));
$criticalMaterials = count(array_filter($inventoryHealth, function($m) { return $m['status'] === 'critical' || $m['status'] === 'low'; }));

// ── Trend indicator HTML helper ─────────────
function trendBadge($trend, $percent) {
    $arrow = $trend === 'up' ? '↑' : '↓';
    $color = $trend === 'up' ? 'var(--success)' : 'var(--danger)';
    return '<span style="color:' . $color . '; font-weight:700;">' . $arrow . ' ' . abs($percent) . '%</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Analytics - Staff Panel</title>
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
                    <li><a href="register_staff.php">👔 Register Staff</a></li>
                    <li><a href="analytics.php" class="active">📊 Analytics</a></li>
                    <li><a href="generate_report.php">📈 Generate Report</a></li>
                    <li><a href="delete_material.php">🗑️ Delete Material</a></li>
                    <li><a href="delete_furniture.php">🗑️ Delete Furniture</a></li>
                </ul>
            </aside>

            <main class="main-content">
                <h2>📊 Business Analytics & Decision Support</h2>
                <p style="color: var(--gray-500); margin-bottom: var(--space-6);">
                    Data-driven insights to support business decisions. Compare month-over-month performance, identify top performers, and monitor inventory health.
                </p>

                <!-- ═══ KPI CARDS with MoM Trends ═══ -->
                <div class="stats-grid" style="grid-template-columns: repeat(3, 1fr);">
                    <div class="stat-card" style="background: linear-gradient(135deg, #1a3c2a, #2d5a3f);">
                        <h3>Revenue (This Month)</h3>
                        <div class="stat-number"><?php echo formatCurrency($salesComparison['current']); ?></div>
                        <div style="font-size: var(--font-size-sm); margin-top: var(--space-2);">
                            vs last month: <?php echo trendBadge($salesComparison['trend'], $salesComparison['percent_change']); ?>
                        </div>
                    </div>
                    <div class="stat-card" style="background: linear-gradient(135deg, #6b3a2a, #8b5a3e);">
                        <h3>Orders (This Month)</h3>
                        <div class="stat-number"><?php echo (int)$orderComparison['current']; ?></div>
                        <div style="font-size: var(--font-size-sm); margin-top: var(--space-2);">
                            vs last month: <?php echo trendBadge($orderComparison['trend'], $orderComparison['percent_change']); ?>
                        </div>
                    </div>
                    <div class="stat-card" style="background: linear-gradient(135deg, #c8963e, #a07828);">
                        <h3>New Customers (This Month)</h3>
                        <div class="stat-number"><?php echo (int)$customerComparison['current']; ?></div>
                        <div style="font-size: var(--font-size-sm); margin-top: var(--space-2);">
                            vs last month: <?php echo trendBadge($customerComparison['trend'], $customerComparison['percent_change']); ?>
                        </div>
                    </div>
                </div>

                <!-- ═══ Additional KPIs ═══ -->
                <div class="stats-grid" style="grid-template-columns: repeat(4, 1fr); margin-top: var(--space-4);">
                    <div class="stat-card" style="background: var(--white); color: var(--gray-800); border: 2px solid var(--gray-200);">
                        <h3 style="color: var(--gray-500);">All-Time Revenue</h3>
                        <div class="stat-number" style="color: var(--primary);"><?php echo formatCurrency($totalRevenue); ?></div>
                    </div>
                    <div class="stat-card" style="background: var(--white); color: var(--gray-800); border: 2px solid var(--gray-200);">
                        <h3 style="color: var(--gray-500);">Total Orders</h3>
                        <div class="stat-number" style="color: var(--primary);"><?php echo $totalOrders; ?></div>
                    </div>
                    <div class="stat-card" style="background: var(--white); color: var(--gray-800); border: 2px solid var(--gray-200);">
                        <h3 style="color: var(--gray-500);">Avg Order Value</h3>
                        <div class="stat-number" style="color: var(--primary);"><?php echo $totalOrders > 0 ? formatCurrency($totalRevenue / $totalOrders) : '$0.00'; ?></div>
                    </div>
                    <div class="stat-card" style="background: <?php echo $criticalMaterials > 0 ? 'var(--danger-bg)' : 'var(--success-bg)'; ?>; color: var(--gray-800); border: 2px solid <?php echo $criticalMaterials > 0 ? '#fecaca' : '#a7f3d0'; ?>;">
                        <h3 style="color: <?php echo $criticalMaterials > 0 ? 'var(--danger)' : 'var(--success)'; ?>;">Materials at Risk</h3>
                        <div class="stat-number" style="color: <?php echo $criticalMaterials > 0 ? 'var(--danger)' : 'var(--success)'; ?>;"><?php echo $criticalMaterials; ?></div>
                    </div>
                </div>

                <!-- ═══ CHARTS ROW ═══ -->
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: var(--space-6); margin-top: var(--space-8);">
                    <!-- Revenue Trend Chart -->
                    <div style="background: var(--white); padding: var(--space-6); border-radius: var(--radius-xl); border: 1px solid var(--gray-100);">
                        <h3>📈 Monthly Revenue Trend</h3>
                        <canvas id="revenueTrendChart" style="max-height: 280px;"></canvas>
                    </div>

                    <!-- Order Status Distribution -->
                    <div style="background: var(--white); padding: var(--space-6); border-radius: var(--radius-xl); border: 1px solid var(--gray-100);">
                        <h3>📋 Order Status</h3>
                        <canvas id="orderStatusChart" style="max-height: 240px;"></canvas>
                        <div style="margin-top: var(--space-4); display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-2); font-size: var(--font-size-xs);">
                            <?php foreach ($orderDistribution as $status => $count): ?>
                                <div>
                                    <span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 4px; background: <?php
                                        echo match($status) {
                                            'delivered' => '#059669', 'accepted' => '#0284c7',
                                            'pending' => '#d97706', 'rejected' => '#dc2626',
                                            default => '#6b7280'
                                        };
                                    ?>;"></span>
                                    <?php echo ucfirst($status); ?>: <strong><?php echo $count; ?></strong>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- ═══ TABLES ROW ═══ -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-6); margin-top: var(--space-8);">
                    <!-- Top Products -->
                    <div style="background: var(--white); padding: var(--space-6); border-radius: var(--radius-xl); border: 1px solid var(--gray-100);">
                        <h3>🏆 Top Products by Revenue</h3>
                        <div class="table-container">
                            <table>
                                <thead><tr><th>#</th><th>Product</th><th>Sold</th><th>Revenue</th></tr></thead>
                                <tbody>
                                    <?php $rank = 1; foreach ($topProducts as $p): ?>
                                        <tr>
                                            <td><strong>#<?php echo $rank++; ?></strong></td>
                                            <td><?php echo htmlspecialchars($p['FurnitureName']); ?></td>
                                            <td><?php echo $p['total_sold']; ?></td>
                                            <td><strong><?php echo formatCurrency($p['total_revenue']); ?></strong></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($topProducts)): ?>
                                        <tr><td colspan="4" style="color: var(--gray-400);">No sales data yet.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Top Customers -->
                    <div style="background: var(--white); padding: var(--space-6); border-radius: var(--radius-xl); border: 1px solid var(--gray-100);">
                        <h3>💎 Top Customers by Spend</h3>
                        <div class="table-container">
                            <table>
                                <thead><tr><th>#</th><th>Customer</th><th>Orders</th><th>Total Spent</th></tr></thead>
                                <tbody>
                                    <?php $rank = 1; foreach ($topCustomers as $c): ?>
                                        <tr>
                                            <td><strong>#<?php echo $rank++; ?></strong></td>
                                            <td><?php echo htmlspecialchars($c['CustomerName']); ?></td>
                                            <td><?php echo $c['order_count']; ?></td>
                                            <td><strong style="color: var(--success);"><?php echo formatCurrency($c['total_spent']); ?></strong></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($topCustomers)): ?>
                                        <tr><td colspan="4" style="color: var(--gray-400);">No customer data yet.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ═══ REVENUE BY CATEGORY ═══ -->
                <?php if (!empty($revenueByCategory)): ?>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-6); margin-top: var(--space-8);">
                        <div style="background: var(--white); padding: var(--space-6); border-radius: var(--radius-xl); border: 1px solid var(--gray-100);">
                            <h3>📂 Revenue by Category</h3>
                            <canvas id="categoryChart" style="max-height: 260px;"></canvas>
                        </div>

                        <!-- Inventory Health -->
                        <div style="background: var(--white); padding: var(--space-6); border-radius: var(--radius-xl); border: 1px solid var(--gray-100);">
                            <h3>📦 Inventory Health</h3>
                            <div class="table-container" style="max-height: 280px; overflow-y: auto;">
                                <table>
                                    <thead><tr><th>Material</th><th>Qty</th><th>Reorder</th><th>Status</th></tr></thead>
                                    <tbody>
                                        <?php foreach (array_slice($inventoryHealth, 0, 10) as $m):
                                            $statusBadge = match($m['status']) {
                                                'critical' => '<span class="badge badge-danger">Critical</span>',
                                                'low' => '<span class="badge badge-warning">Low</span>',
                                                'warning' => '<span class="badge badge-info">Watch</span>',
                                                default => '<span class="badge badge-success">Healthy</span>'
                                            };
                                        ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($m['MaterialName']); ?></td>
                                                <td><?php echo number_format($m['PhysicalQuantity'], 2); ?> <?php echo htmlspecialchars($m['Unit']); ?></td>
                                                <td><?php echo number_format($m['ReorderLevel'], 2); ?></td>
                                                <td><?php echo $statusBadge; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- ═══ DECISION RECOMMENDATIONS ═══ -->
                <div style="background: var(--primary-surface); padding: var(--space-6); border-radius: var(--radius-xl); border: 2px solid var(--primary); margin-top: var(--space-8);">
                    <h3>💡 AI-Powered Recommendations</h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4); margin-top: var(--space-4);">
                        <?php
                        $recommendations = [];

                        // Revenue trend recommendation
                        if ($salesComparison['trend'] === 'down' && $salesComparison['percent_change'] < -10) {
                            $recommendations[] = ['⚠️', 'Revenue is declining. Consider running a promotional campaign or reviewing pricing strategy.'];
                        } elseif ($salesComparison['trend'] === 'up') {
                            $recommendations[] = ['✅', 'Revenue is growing! Consider expanding inventory for top-selling categories.'];
                        }

                        // Inventory recommendations
                        if ($criticalMaterials > 0) {
                            $recommendations[] = ['📦', '<strong>' . $criticalMaterials . ' materials</strong> need immediate restocking. Check inventory health table below.'];
                        }

                        // Top product insight
                        if (!empty($topProducts)) {
                            $topName = $topProducts[0]['FurnitureName'];
                            $topRev = formatCurrency($topProducts[0]['total_revenue']);
                            $recommendations[] = ['🏆', '<strong>' . htmlspecialchars($topName) . '</strong> is your best seller (' . $topRev . '). Ensure adequate stock and consider featuring it prominently.'];
                        }

                        // Customer acquisition
                        if ($customerComparison['trend'] === 'down') {
                            $recommendations[] = ['👥', 'New customer registrations are down. Consider referral incentives or targeted marketing.'];
                        }

                        // Default if empty
                        if (empty($recommendations)) {
                            $recommendations[] = ['📊', 'Continue monitoring metrics. More historical data will enable richer insights.'];
                        }

                        foreach ($recommendations as $rec):
                        ?>
                            <div style="background: var(--white); padding: var(--space-4); border-radius: var(--radius); border: 1px solid var(--gray-200);">
                                <span style="font-size: 1.2rem;"><?php echo $rec[0]; ?></span>
                                <span style="font-size: var(--font-size-sm);"><?php echo $rec[1]; ?></span>
                            </div>
                        <?php endforeach; ?>
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

    <script src="../assets/js/validation.js"></script>
    <script>
        // ── Revenue Trend Line Chart ──────────────
        <?php
        $trendMonths = array_column($monthlyTrend, 'month');
        $trendRevenue = array_map('floatval', array_column($monthlyTrend, 'revenue'));
        ?>
        new Chart(document.getElementById('revenueTrendChart'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode($trendMonths); ?>,
                datasets: [{
                    label: 'Revenue ($)',
                    data: <?php echo json_encode($trendRevenue); ?>,
                    borderColor: '#1a3c2a',
                    backgroundColor: 'rgba(26, 60, 42, 0.08)',
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#c8963e',
                    pointBorderColor: '#c8963e',
                    pointRadius: 5,
                    pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: function(c) { return '$' + parseFloat(c.raw).toFixed(2); } } }
                },
                scales: {
                    y: { beginAtZero: true, ticks: { callback: function(v) { return '$' + v; } } }
                }
            }
        });

        // ── Order Status Doughnut Chart ───────────
        <?php
        $statusLabels = array_keys($orderDistribution);
        $statusCounts = array_values($orderDistribution);
        ?>
        new Chart(document.getElementById('orderStatusChart'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($statusLabels); ?>,
                datasets: [{
                    data: <?php echo json_encode($statusCounts); ?>,
                    backgroundColor: ['#059669', '#0284c7', '#d97706', '#dc2626', '#6b7280'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false }
                }
            }
        });

        // ── Category Bar Chart ────────────────────
        <?php
        $catLabels = array_column($revenueByCategory, 'Category');
        $catRevenue = array_map('floatval', array_column($revenueByCategory, 'total_revenue'));
        ?>
        <?php if (!empty($catLabels)): ?>
        new Chart(document.getElementById('categoryChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($catLabels); ?>,
                datasets: [{
                    label: 'Revenue ($)',
                    data: <?php echo json_encode($catRevenue); ?>,
                    backgroundColor: ['#1a3c2a', '#2d5a3f', '#c8963e', '#6b3a2a', '#8b5a3e', '#059669'],
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: function(c) { return '$' + parseFloat(c.raw).toFixed(2); } } }
                },
                scales: {
                    y: { beginAtZero: true, ticks: { callback: function(v) { return '$' + v; } } }
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>