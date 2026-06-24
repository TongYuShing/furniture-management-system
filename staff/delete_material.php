<?php
/**
 * delete_material.php - Staff Delete Material Page
 * Allows staff to delete materials only if not used by any furniture product
 * Part I - Function #4: Delete Material
 */

require_once '../inc/config.php';
require_once '../inc/session.php';
require_once '../inc/functions.php';

// Check if user is logged in as staff
checkStaffRole();

$message = '';
$messageType = '';

// Handle deletion
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    $materialId = (int)$_GET['delete_id'];

    // Get material name for the message
    $materialData = getMaterialById($conn, $materialId);
    $materialName = $materialData ? $materialData['MaterialName'] : 'Unknown';

    $result = deleteMaterial($conn, $materialId);
    if ($result['success']) {
        $message = $result['message'];
        $messageType = 'success';
    } else {
        $message = $result['message'];
        $messageType = 'danger';
    }
}

// Get all materials
$allMaterials = getAllMaterials($conn);

// Check which can be deleted and count usage
foreach ($allMaterials as &$item) {
    $item['can_delete'] = canDeleteMaterial($conn, $item['MaterialID']);

    // Get count of furniture products using this material
    $sql = "SELECT COUNT(*) as usage_count, GROUP_CONCAT(f.FurnitureName SEPARATOR ', ') as products
            FROM Furniture_Material fm
            INNER JOIN Furniture f ON fm.FurnitureID = f.FurnitureID
            WHERE fm.MaterialID = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $item['MaterialID']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $usageData = mysqli_fetch_assoc($result);
    $item['usage_count'] = $usageData['usage_count'] ?? 0;
    $item['used_in'] = $usageData['products'] ?? '';
    mysqli_stmt_close($stmt);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Material - Staff Panel</title>
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
                    <li><a href="delete_material.php" class="active">🗑️ Delete Material</a></li>
                    <li><a href="delete_furniture.php">🗑️ Delete Furniture</a></li>
                </ul>

                <div class="alert alert-info" style="margin-top: var(--space-4);">
                    <strong>📋 Deletion Rules:</strong>
                    <ul style="margin-top: var(--space-2); margin-left: var(--space-4);">
                        <li>Materials can only be deleted if <strong>no furniture products</strong> use them.</li>
                        <li>Remove the material from all furniture first before deleting.</li>
                        <li>A confirmation message will appear before deletion.</li>
                        <li>This action cannot be undone.</li>
                    </ul>
                </div>
            </aside>

            <!-- Main Content -->
            <main class="main-content">
                <h2>Delete Material</h2>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <?php if (empty($allMaterials)): ?>
                    <div class="alert alert-info">No materials found in inventory.</div>
                <?php else: ?>
                    <div class="table-container">
                        <table data-sortable>
                            <thead>
                                <tr>
                                    <th class="sortable">ID</th>
                                    <th class="sortable">Material Name</th>
                                    <th class="sortable">Quantity</th>
                                    <th class="sortable">Unit</th>
                                    <th class="sortable">Reorder Level</th>
                                    <th>Used In</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allMaterials as $material): ?>
                                    <tr>
                                        <td><?php echo $material['MaterialID']; ?></td>
                                        <td><strong><?php echo htmlspecialchars($material['MaterialName']); ?></strong></td>
                                        <td><?php echo number_format($material['PhysicalQuantity'], 2); ?></td>
                                        <td><?php echo htmlspecialchars($material['Unit']); ?></td>
                                        <td><?php echo number_format($material['ReorderLevel'], 2); ?></td>
                                        <td>
                                            <?php if ($material['usage_count'] > 0): ?>
                                                <span style="font-size: var(--font-size-xs);" title="<?php echo htmlspecialchars($material['used_in']); ?>">
                                                    <?php echo $material['usage_count']; ?> product(s):
                                                    <span style="color: var(--gray-500);"><?php echo htmlspecialchars($material['used_in']); ?></span>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-success">Not used</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($material['PhysicalQuantity'] <= 0): ?>
                                                <span class="badge badge-danger">Out of Stock</span>
                                            <?php elseif ($material['PhysicalQuantity'] <= $material['ReorderLevel'] && $material['ReorderLevel'] > 0): ?>
                                                <span class="badge badge-warning">Low Stock</span>
                                            <?php else: ?>
                                                <span class="badge badge-success">In Stock</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($material['can_delete']): ?>
                                                <button class="btn btn-danger btn-sm"
                                                        onclick="confirmDelete(<?php echo $material['MaterialID']; ?>, '<?php echo htmlspecialchars(addslashes($material['MaterialName'])); ?>')">
                                                    🗑️ Delete
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-secondary btn-sm" disabled
                                                        title="Cannot delete: In use by furniture products">
                                                    🔒 In Use
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Summary -->
                    <div style="margin-top: var(--space-6); padding: var(--space-4); background: var(--gray-50); border-radius: var(--radius); border: 1px solid var(--gray-200);">
                        <strong>📊 Summary:</strong>
                        <?php
                            $total = count($allMaterials);
                            $deletable = count(array_filter($allMaterials, function($m) { return $m['can_delete']; }));
                            $inUse = $total - $deletable;
                        ?>
                        <?php echo $total; ?> total materials
                        &nbsp;|&nbsp;
                        <span style="color: var(--success);"><?php echo $deletable; ?> can be deleted</span>
                        &nbsp;|&nbsp;
                        <span style="color: var(--danger);"><?php echo $inUse; ?> in use (cannot delete)</span>
                    </div>
                <?php endif; ?>
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
        /**
         * Show confirmation modal before deletion
         */
        function confirmDelete(materialId, materialName) {
            // Remove existing modal if present
            closeConfirmModal();

            const modalHtml = `
                <div id="deleteConfirmModal" class="modal show" style="display: flex;">
                    <div class="modal-content" style="max-width: 460px;">
                        <div class="modal-header">
                            <h3>Confirm Deletion</h3>
                            <button class="modal-close" onclick="closeConfirmModal()">&times;</button>
                        </div>
                        <div class="modal-body">
                            <p>Are you sure you want to delete <strong>${escapeHtml(materialName)}</strong>?</p>
                            <div class="alert alert-warning" style="margin-top: var(--space-4);">
                                <strong>⚠️ Warning:</strong>
                                <ul style="margin-top: var(--space-2); margin-left: var(--space-4);">
                                    <li>This material will be permanently removed from the inventory.</li>
                                    <li>This action cannot be undone.</li>
                                    <li>Make sure no furniture products depend on this material.</li>
                                </ul>
                            </div>
                        </div>
                        <div class="modal-footer" style="display: flex; gap: var(--space-3); margin-top: var(--space-5); justify-content: flex-end;">
                            <button class="btn btn-outline" onclick="closeConfirmModal()">Cancel</button>
                            <a href="delete_material.php?delete_id=${materialId}" class="btn btn-danger">Yes, Delete</a>
                        </div>
                    </div>
                </div>
            `;

            document.body.insertAdjacentHTML('beforeend', modalHtml);
        }

        function closeConfirmModal() {
            const existingModal = document.getElementById('deleteConfirmModal');
            if (existingModal) {
                existingModal.remove();
            }
        }

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