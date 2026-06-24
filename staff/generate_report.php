<?php
/**
 * generate_report.php - Staff Report Page
 * Displays statistics for orders including total quantity and sales amount per item
 */

require_once '../inc/config.php';
require_once '../inc/session.php';
require_once '../inc/functions.php';

// Check if user is logged in as staff
checkStaffRole();

// Get sales report from view
$salesReport = getSalesReport($conn);

// Get material report
$materialReport = getMaterialReport($conn);

// Get monthly sales summary
$sql = "SELECT 
            DATE_FORMAT(OrderDate, '%Y-%m') as month,
            COUNT(*) as order_count,
            SUM(TotalOrderAmount) as total_sales
        FROM Orders 
        WHERE OrderStatus = 'delivered'
        GROUP BY DATE_FORMAT(OrderDate, '%Y-%m')
        ORDER BY month DESC
        LIMIT 6";
$monthlySales = mysqli_query($conn, $sql);

// Get top selling products
$sql = "SELECT 
            f.FurnitureName,
            f.FurnitureImage,
            SUM(o.OrderQuantity) as total_quantity,
            SUM(o.TotalOrderAmount) as total_sales
        FROM Orders o
        INNER JOIN Furniture f ON o.FurnitureID = f.FurnitureID
        WHERE o.OrderStatus = 'delivered'
        GROUP BY f.FurnitureID
        ORDER BY total_sales DESC
        LIMIT 5";
$topProducts = mysqli_query($conn, $sql);

// Fetch product view images for all products
$reportImages = [];
$imgSql = "SELECT FurnitureID, ImagePath, IsPrimary FROM FurnitureImage ORDER BY SortOrder ASC";
$imgResult = mysqli_query($conn, $imgSql);
while ($img = mysqli_fetch_assoc($imgResult)) {
    if (!isset($reportImages[$img['FurnitureID']])) {
        $reportImages[$img['FurnitureID']] = [];
    }
    $reportImages[$img['FurnitureID']][] = $img;
}

// Calculate overall totals
$totalOrders = count($salesReport);
$totalRevenue = array_sum(array_column($salesReport, 'TotalSalesAmount'));
$totalItems = array_sum(array_column($salesReport, 'TotalNumberForOrderItem'));

// Get filter parameters
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';

if ($startDate && $endDate) {
    $sql = "SELECT * FROM SalesReportView 
            WHERE OrderDate BETWEEN ? AND ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ss", $startDate, $endDate);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $filteredReport = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $filteredReport[] = $row;
    }
    $salesReport = $filteredReport;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Report - Staff Panel</title>
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
                    <li><a href="register_customer.php">👤 Register Customer</a></li>
                    <li><a href="manage_orders.php">📋 Manage Orders</a></li>
                    <li><a href="manage_inquiries.php">📬 Inquiries</a></li>
                    <li><a href="register_staff.php">👔 Register Staff</a></li>
                    <li><a href="analytics.php">📊 Analytics</a></li>
                    <li><a href="generate_report.php" class="active">📈 Generate Report</a></li>
                    <li><a href="delete_material.php">🗑️ Delete Material</a></li>
                    <li><a href="delete_furniture.php">🗑️ Delete Furniture</a></li>
                </ul>
            </aside>

            <!-- Main Content -->
            <main class="main-content">
                <h2>Sales & Inventory Report</h2>
                
                <!-- Summary Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>Total Orders</h3>
                        <div class="stat-number"><?php echo $totalOrders; ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Total Items Sold</h3>
                        <div class="stat-number"><?php echo $totalItems; ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Total Revenue</h3>
                        <div class="stat-number"><?php echo formatCurrency($totalRevenue); ?></div>
                    </div>
                </div>
                
                <!-- Date Filter -->
                <div class="sort-controls">
                    <form method="GET" action="" style="display: flex; gap: var(--spacing-md); align-items: flex-end; flex-wrap: wrap;">
                        <div class="form-group">
                            <label>Start Date</label>
                            <input type="date" name="start_date" class="form-control" value="<?php echo $startDate; ?>">
                        </div>
                        <div class="form-group">
                            <label>End Date</label>
                            <input type="date" name="end_date" class="form-control" value="<?php echo $endDate; ?>">
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">Filter</button>
                            <a href="generate_report.php" class="btn btn-secondary">Reset</a>
                        </div>
                    </form>
                </div>
                
                <!-- Monthly Sales Chart -->
                <h3>Monthly Sales Trend</h3>
                <canvas id="salesChart" style="max-height: 300px; margin-bottom: var(--spacing-xl);"></canvas>
                
                <!-- Top Selling Products -->
                <h3>Top Selling Products</h3>
                <div class="card-grid" style="grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));">
                    <?php
                    // Fetch top products into array for image lookup
                    $topProductsArr = [];
                    while ($p = mysqli_fetch_assoc($topProducts)) { $topProductsArr[] = $p; }
                    foreach ($topProductsArr as $product):
                        // Get furniture ID from sales report for image lookup
                        $rpid = 0;
                        foreach ($salesReport as $sr) {
                            if ($sr['FurnitureName'] === $product['FurnitureName']) { $rpid = $sr['FurnitureID']; break; }
                        }
                        $rviews = $reportImages[$rpid] ?? [];
                        $rpri = null;
                        foreach ($rviews as $rv) { if ($rv['IsPrimary']) { $rpri = $rv; break; } }
                        if (!$rpri && !empty($rviews)) $rpri = $rviews[0];
                        $rimgSrc = $rpri
                            ? '../assets/images/furniture/' . str_replace(' ', '%20', $rpri['ImagePath'])
                            : '../assets/images/furniture/' . htmlspecialchars($product['FurnitureImage']);
                    ?>
                        <div class="card">
                            <img src="<?php echo $rimgSrc; ?>"
                                 class="card-image"
                                 onerror="this.src='../assets/images/furniture/placeholder.jpg'">
                            <div class="card-content">
                                <h4><?php echo htmlspecialchars($product['FurnitureName']); ?></h4>
                                <div>Quantity Sold: <strong><?php echo $product['total_quantity']; ?></strong></div>
                                <div>Total Sales: <strong><?php echo formatCurrency($product['total_sales']); ?></strong></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Detailed Sales Report Table -->
                <h3>Detailed Sales Report</h3>
                <p>Required information: Order ID, Furniture Name, Furniture Image, Total number for each order item, Total sales amount ($) for each order item</p>
                
                <?php if (empty($salesReport)): ?>
                    <div class="alert alert-info">No sales data available for the selected period.</div>
                <?php else: ?>
                    <div class="table-container">
                        <table data-sortable>
                            <thead>
                                <tr>
                                    <th class="sortable">Order ID</th>
                                    <th>Furniture Image</th>
                                    <th class="sortable">Furniture Name</th>
                                    <th class="sortable">Total Number for Order Item</th>
                                    <th class="sortable">Total Sales Amount ($)</th>
                                    <th class="sortable">Price per Unit</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($salesReport as $item):
                                    $sfid = $item['FurnitureID'];
                                    $sviews = $reportImages[$sfid] ?? [];
                                    $spri = null;
                                    foreach ($sviews as $sv) { if ($sv['IsPrimary']) { $spri = $sv; break; } }
                                    if (!$spri && !empty($sviews)) $spri = $sviews[0];
                                    $simgSrc = $spri
                                        ? '../assets/images/furniture/' . str_replace(' ', '%20', $spri['ImagePath'])
                                        : '../assets/images/furniture/' . htmlspecialchars($item['FurnitureImage']);
                                ?>
                                    <tr>
                                        <td><?php echo $item['OrderID']; ?></td>
                                        <td>
                                            <img src="<?php echo $simgSrc; ?>"
                                                 style="width: 60px; height: 60px; object-fit: cover; border-radius: 5px;"
                                                 onerror="this.src='../assets/images/furniture/placeholder.jpg'">
                                        </td>
                                        <td><?php echo htmlspecialchars($item['FurnitureName']); ?></td>
                                        <td><?php echo $item['TotalNumberForOrderItem']; ?></td>
                                        <td><strong><?php echo formatCurrency($item['TotalSalesAmount']); ?></strong></td>
                                        <td><?php echo formatCurrency($item['TotalSalesAmount'] / $item['TotalNumberForOrderItem']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot style="background-color: var(--gray-lighter); font-weight: bold;">
                                <tr>
                                    <td colspan="3" style="text-align: right;">Total:</td>
                                    <td><?php echo array_sum(array_column($salesReport, 'TotalNumberForOrderItem')); ?></td>
                                    <td><?php echo formatCurrency(array_sum(array_column($salesReport, 'TotalSalesAmount'))); ?></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
                
                <!-- Material Inventory Report -->
                <h3 style="margin-top: var(--spacing-xl);">Material Inventory Status</h3>
                <div class="table-container">
                    <table data-sortable>
                        <thead>
                            <tr>
                                <th class="sortable">Material ID</th>
                                <th class="sortable">Material Name</th>
                                <th class="sortable">Available Quantity</th>
                                <th>Unit</th>
                                <th class="sortable">Reserved Quantity</th>
                                <th class="sortable">Remaining Quantity</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($materialReport as $material): ?>
                                <tr>
                                    <td><?php echo $material['MaterialID']; ?></td>
                                    <td><?php echo htmlspecialchars($material['MaterialName']); ?></td>
                                    <td><?php echo number_format($material['AvailableQuantity'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($material['Unit']); ?></td>
                                    <td><?php echo number_format($material['ReservedQuantity'], 2); ?></td>
                                    <td><?php echo number_format($material['RemainingQuantity'], 2); ?></td>
                                    <td>
                                        <?php if ($material['RemainingQuantity'] <= 0): ?>
                                            <span class="badge badge-danger">Out of Stock</span>
                                        <?php elseif ($material['RemainingQuantity'] < 100): ?>
                                            <span class="badge badge-warning">Low Stock</span>
                                        <?php else: ?>
                                            <span class="badge badge-success">Sufficient</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Export Buttons -->
                <div style="margin-top: var(--spacing-xl); display: flex; gap: var(--spacing-md);">
                    <button onclick="exportToCSV()" class="btn btn-primary">📊 Export to CSV</button>
                    <button onclick="window.print()" class="btn btn-secondary">🖨️ Print Report</button>
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
    <script>
        // Monthly sales chart
        const monthlyData = <?php 
            $months = [];
            $sales = [];
            while ($row = mysqli_fetch_assoc($monthlySales)) {
                $months[] = $row['month'];
                $sales[] = $row['total_sales'];
            }
            echo json_encode(['months' => array_reverse($months), 'sales' => array_reverse($sales)]);
        ?>;
        
        const ctx = document.getElementById('salesChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: monthlyData.months,
                datasets: [{
                    label: 'Monthly Sales ($)',
                    data: monthlyData.sales,
                    borderColor: '#2c5f2d',
                    backgroundColor: 'rgba(44, 95, 45, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return '$' + context.raw.toFixed(2);
                            }
                        }
                    }
                }
            }
        });
        
        function exportToCSV() {
            const table = document.querySelector('table');
            const rows = table.querySelectorAll('tr');
            let csv = [];
            
            rows.forEach(row => {
                const cells = row.querySelectorAll('td, th');
                const rowData = Array.from(cells).map(cell => {
                    // Handle images - just get alt text or placeholder
                    const img = cell.querySelector('img');
                    if (img) {
                        return img.alt || 'Image';
                    }
                    return cell.innerText.trim();
                });
                csv.push(rowData.join(','));
            });
            
            const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'sales_report.csv';
            a.click();
            URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>