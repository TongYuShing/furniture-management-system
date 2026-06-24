<?php
/**
 * delete_furniture.php - Staff Delete Furniture Page
 * Allows staff to delete furniture products only if no existing orders
 * A confirmation message is displayed before deletion
 */

require_once '../inc/config.php';
require_once '../inc/session.php';
require_once '../inc/functions.php';

// Check if user is logged in as staff
checkStaffRole();

$message = '';
$messageType = '';

// Handle deletion (after confirmation)
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    $furnitureId = (int)$_GET['delete_id'];
    
    // Get furniture name for message
    $furnitureData = getFurnitureById($conn, $furnitureId);
    $furnitureName = $furnitureData ? $furnitureData['FurnitureName'] : 'Unknown';
    
    // Double-check if can delete
    if (canDeleteFurniture($conn, $furnitureId)) {
        $result = deleteFurniture($conn, $furnitureId);
        if ($result['success']) {
            $message = "Furniture product \"" . htmlspecialchars($furnitureName) . "\" has been successfully deleted.";
            $messageType = 'success';
        } else {
            $message = $result['message'];
            $messageType = 'danger';
        }
    } else {
        $message = "Cannot delete \"" . htmlspecialchars($furnitureName) . "\" because it has existing orders.";
        $messageType = 'danger';
    }
}

// Get all furniture products
$furniture = getAllFurniture($conn);

// Fetch product view images for the list
$listImages = [];
$imgSql = "SELECT FurnitureID, ImagePath, IsPrimary FROM FurnitureImage ORDER BY SortOrder ASC";
$imgResult = mysqli_query($conn, $imgSql);
while ($img = mysqli_fetch_assoc($imgResult)) {
    if (!isset($listImages[$img['FurnitureID']])) {
        $listImages[$img['FurnitureID']] = [];
    }
    $listImages[$img['FurnitureID']][] = $img;
}

// Check which can be deleted
foreach ($furniture as &$item) {
    $item['can_delete'] = canDeleteFurniture($conn, $item['FurnitureID']);
    
    // Check if there are any orders for this furniture
    $sql = "SELECT COUNT(*) as order_count FROM Orders WHERE FurnitureID = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $item['FurnitureID']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $orderData = mysqli_fetch_assoc($result);
    $item['order_count'] = $orderData['order_count'];
    mysqli_stmt_close($stmt);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Furniture - Staff Panel</title>
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
                    <li><a href="register_customer.php">👤 Register Customer</a></li>
                    <li><a href="manage_orders.php">📋 Manage Orders</a></li>
                    <li><a href="manage_inquiries.php">📬 Inquiries</a></li>
                    <li><a href="register_staff.php">👔 Register Staff</a></li>
                    <li><a href="analytics.php">📊 Analytics</a></li>
                    <li><a href="generate_report.php">📈 Generate Report</a></li>
                    <li><a href="delete_material.php">🗑️ Delete Material</a></li>
                    <li><a href="delete_furniture.php" class="active">🗑️ Delete Furniture</a></li>
                </ul>
                
                <div class="alert alert-info" style="margin-top: var(--spacing-lg);">
                    <strong>📋 Deletion Rules:</strong>
                    <ul style="margin-top: var(--spacing-sm); margin-left: var(--spacing-lg);">
                        <li>Furniture products can only be deleted if they have <strong>no existing orders</strong>.</li>
                        <li>A confirmation message will appear before deletion.</li>
                        <li>This action cannot be undone.</li>
                    </ul>
                </div>
            </aside>

            <!-- Main Content -->
            <main class="main-content">
                <h2>Delete Furniture Product</h2>
                
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (empty($furniture)): ?>
                    <div class="alert alert-info">No furniture products found.</div>
                <?php else: ?>
                    <div class="table-container">
                        <table data-sortable>
                            <thead>
                                <tr>
                                    <th class="sortable">ID</th>
                                    <th>Image</th>
                                    <th class="sortable">Furniture Name</th>
                                    <th class="sortable">Category</th>
                                    <th class="sortable">Price</th>
                                    <th class="sortable">Stock</th>
                                    <th>Existing Orders</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($furniture as $item):
                                    $pid = $item['FurnitureID'];
                                    $views = $listImages[$pid] ?? [];
                                    $pri = null;
                                    foreach ($views as $v) { if ($v['IsPrimary']) { $pri = $v; break; } }
                                    if (!$pri && !empty($views)) $pri = $views[0];
                                    $listImgSrc = $pri
                                        ? '../assets/images/furniture/' . str_replace(' ', '%20', $pri['ImagePath'])
                                        : '../assets/images/furniture/' . htmlspecialchars($item['FurnitureImage']);
                                ?>
                                    <tr>
                                        <td><?php echo $item['FurnitureID']; ?></td>
                                        <td>
                                            <img src="<?php echo $listImgSrc; ?>"
                                                 style="width: 60px; height: 60px; object-fit: cover; border-radius: 5px;"
                                                 onerror="this.src='../assets/images/furniture/placeholder.jpg'"
                                                 alt="<?php echo htmlspecialchars($item['FurnitureName']); ?>">
                                        </td>
                                        <td><?php echo htmlspecialchars($item['FurnitureName']); ?></td>
                                        <td><?php echo htmlspecialchars($item['Category']); ?></td>
                                        <td><?php echo formatCurrency($item['Price']); ?></td>
                                        <td><?php echo $item['StockQuantity']; ?></td>
                                        <td>
                                            <?php if ($item['order_count'] > 0): ?>
                                                <span class="badge badge-danger"><?php echo $item['order_count']; ?> order(s)</span>
                                            <?php else: ?>
                                                <span class="badge badge-success">0 orders</span>
                                            <?php endif; ?>
                                         </td>
                                        <td>
                                            <?php if ($item['is_sold_out']): ?>
                                                <span class="badge badge-warning">Sold Out</span>
                                            <?php elseif ($item['StockQuantity'] < 5): ?>
                                                <span class="badge badge-warning">Low Stock</span>
                                            <?php else: ?>
                                                <span class="badge badge-success">Active</span>
                                            <?php endif; ?>
                                         </td>
                                        <td>
                                            <?php if ($item['can_delete']): ?>
                                                <button class="btn btn-danger btn-sm" 
                                                        onclick="confirmDelete(<?php echo $item['FurnitureID']; ?>, '<?php echo htmlspecialchars(addslashes($item['FurnitureName'])); ?>')">
                                                    🗑️ Delete
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-secondary btn-sm" disabled 
                                                        title="Cannot delete: Has existing orders">
                                                    🔒 Cannot Delete
                                                </button>
                                            <?php endif; ?>
                                         </td>
                                     </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Summary -->
                    <div class="alert alert-secondary" style="margin-top: var(--spacing-lg);">
                        <strong>Summary:</strong>
                        <?php 
                            $deletableCount = count(array_filter($furniture, function($f) { return $f['can_delete']; }));
                            $nonDeletableCount = count($furniture) - $deletableCount;
                        ?>
                        <ul style="margin-top: var(--spacing-sm); margin-left: var(--spacing-lg);">
                            <li>Total furniture products: <strong><?php echo count($furniture); ?></strong></li>
                            <li>Can be deleted: <strong class="text-success"><?php echo $deletableCount; ?></strong></li>
                            <li>Cannot be deleted (has orders): <strong class="text-danger"><?php echo $nonDeletableCount; ?></strong></li>
                        </ul>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 Premium Living Furniture Co. Ltd. All rights reserved.</p>
        </div>
    </footer>

    <script>
        /**
         * Confirm delete with modal dialog
         * @param {number} furnitureId - ID of furniture to delete
         * @param {string} furnitureName - Name of furniture
         */
        function confirmDelete(furnitureId, furnitureName) {
            // Create custom confirmation modal
            const modalHtml = `
                <div id="deleteConfirmModal" class="modal show" style="display: flex;">
                    <div class="modal-content" style="max-width: 450px;">
                        <div class="modal-header">
                            <h3>Confirm Deletion</h3>
                            <button class="modal-close" onclick="closeConfirmModal()">&times;</button>
                        </div>
                        <div class="modal-body">
                            <p>Are you sure you want to delete <strong>${escapeHtml(furnitureName)}</strong>?</p>
                            <div class="alert alert-warning" style="margin-top: var(--spacing-md);">
                                <strong>⚠️ Warning:</strong>
                                <ul style="margin-top: var(--spacing-sm); margin-left: var(--spacing-lg);">
                                    <li>This action cannot be undone.</li>
                                    <li>All associated material relationships will be removed.</li>
                                    <li>The product will be permanently deleted from the system.</li>
                                </ul>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-secondary" onclick="closeConfirmModal()">Cancel</button>
                            <a href="delete_furniture.php?delete_id=${furnitureId}" class="btn btn-danger">Yes, Delete</a>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove existing modal if present
            closeConfirmModal();
            
            // Add modal to body
            document.body.insertAdjacentHTML('beforeend', modalHtml);
        }
        
        /**
         * Close confirmation modal
         */
        function closeConfirmModal() {
            const existingModal = document.getElementById('deleteConfirmModal');
            if (existingModal) {
                existingModal.remove();
            }
        }
        
        /**
         * Escape HTML to prevent XSS
         */
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Close modal when clicking outside
        document.addEventListener('click', function(event) {
            const modal = document.getElementById('deleteConfirmModal');
            if (modal && event.target === modal) {
                closeConfirmModal();
            }
        });
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeConfirmModal();
            }
        });
    </script>
</body>
</html>