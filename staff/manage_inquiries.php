<?php
/**
 * manage_inquiries.php - Staff View Customer Inquiries
 * Displays all contact form submissions from customers
 */

require_once '../inc/config.php';
require_once '../inc/session.php';
require_once '../inc/functions.php';
require_once '../inc/advanced.php';

checkStaffRole();

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $deleteId = (int)$_POST['delete_id'];
    $sql = "DELETE FROM ContactInquiries WHERE InquiryID = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $deleteId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 15;

$countSql = "SELECT COUNT(*) AS total FROM ContactInquiries";
$countResult = mysqli_query($conn, $countSql);
$totalInquiries = mysqli_fetch_assoc($countResult)['total'];
$pagination = paginateParams($totalInquiries, $page, $perPage);

$sql = "SELECT * FROM ContactInquiries ORDER BY CreatedAt DESC LIMIT " . (int)$perPage . " OFFSET " . (int)$pagination['offset'];
$result = mysqli_query($conn, $sql);
$inquiries = [];
while ($row = mysqli_fetch_assoc($result)) {
    $inquiries[] = $row;
}

// Unread count (all inquiries are "unread" since we don't have a read flag yet)
$unreadCount = $totalInquiries;

$paginationBaseUrl = 'manage_inquiries.php?page={page}';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Inquiries - Staff Panel</title>
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
                    <li><a href="manage_inquiries.php" class="active">📬 Inquiries</a></li>
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

            <main class="main-content">
                <h2>📬 Customer Inquiries</h2>
                <p style="color: var(--gray-500); margin-bottom: var(--space-6);">
                    Messages submitted by customers through the Contact Us form.
                    <strong style="color: var(--primary);"><?php echo $unreadCount; ?> total</strong> inquiries received.
                </p>

                <?php if (empty($inquiries)): ?>
                    <div class="alert alert-info" style="text-align:center;padding:var(--space-10);">
                        <p style="font-size:1.2rem;">📭 No inquiries received yet.</p>
                        <p style="color:var(--gray-500);">Customer messages from the contact form will appear here.</p>
                    </div>
                <?php else: ?>
                    <div style="display:flex;flex-direction:column;gap:var(--space-4);">
                        <?php foreach ($inquiries as $inq): ?>
                            <div style="background:var(--white);border:1px solid var(--gray-200);border-radius:var(--radius-lg);padding:var(--space-5);transition:box-shadow 0.2s;"
                                 onmouseover="this.style.boxShadow='var(--shadow-md)'"
                                 onmouseout="this.style.boxShadow='var(--shadow-sm)'">
                                <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:var(--space-3);margin-bottom:var(--space-3);">
                                    <div>
                                        <strong style="font-size:1.05rem;"><?php echo htmlspecialchars($inq['CustomerName']); ?></strong>
                                        <span style="color:var(--gray-400);margin-left:var(--space-3);font-size:0.85rem;">
                                            <?php echo htmlspecialchars($inq['Email']); ?>
                                        </span>
                                        <?php if (!empty($inq['Subject'])): ?>
                                            <span style="display:inline-block;margin-left:var(--space-3);background:var(--primary-surface);color:var(--primary);padding:2px 10px;border-radius:12px;font-size:0.8rem;font-weight:600;">
                                                <?php echo htmlspecialchars($inq['Subject']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="display:flex;align-items:center;gap:var(--space-3);">
                                        <span style="font-size:0.8rem;color:var(--gray-400);white-space:nowrap;">
                                            🕐 <?php echo date('d M Y, h:i A', strtotime($inq['CreatedAt'])); ?>
                                        </span>
                                        <form method="POST" action="" onsubmit="return confirm('Delete this inquiry?');" style="margin:0;">
                                            <input type="hidden" name="delete_id" value="<?php echo $inq['InquiryID']; ?>">
                                            <button type="submit" class="btn btn-sm" style="background:var(--gray-100);color:var(--danger);border:1px solid var(--gray-300);padding:4px 10px;font-size:0.75rem;border-radius:6px;cursor:pointer;" title="Delete inquiry">🗑️</button>
                                        </form>
                                    </div>
                                </div>
                                <div style="background:var(--gray-50);border-radius:var(--radius);padding:var(--space-4);color:var(--gray-700);line-height:1.7;white-space:pre-wrap;">
                                    <?php echo htmlspecialchars($inq['Message']); ?>
                                </div>
                                <div style="margin-top:var(--space-3);display:flex;gap:var(--space-3);">
                                    <a href="mailto:<?php echo htmlspecialchars($inq['Email']); ?>?subject=Re: <?php echo urlencode($inq['Subject'] ?? 'Your Inquiry'); ?>"
                                       class="btn btn-sm btn-primary" style="font-size:0.8rem;">✉️ Reply via Email</a>
                                    <a href="register_customer.php"
                                       class="btn btn-sm btn-outline" style="font-size:0.8rem;">👤 Register as Customer</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($pagination['total_pages'] > 1): ?>
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:var(--space-6);flex-wrap:wrap;gap:var(--space-4);">
                            <span style="font-size:var(--font-size-sm);color:var(--gray-500);">
                                Showing <?php echo $pagination['offset'] + 1; ?>–<?php echo min($pagination['current_page'] * $perPage, $totalInquiries); ?> of <?php echo $totalInquiries; ?> inquiries
                            </span>
                            <?php echo buildPagination($pagination['current_page'], $pagination['total_pages'], $paginationBaseUrl); ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Premium Living Furniture Co. Ltd. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>
