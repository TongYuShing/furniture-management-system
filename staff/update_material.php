<?php
/**
 * update_material.php - Staff Update Material Page
 * Allows staff to edit existing materials (name, quantity, unit, reorder level)
 * Part I - Function #3: Update Material
 */

require_once '../inc/config.php';
require_once '../inc/session.php';
require_once '../inc/functions.php';

// Check if user is logged in as staff
checkStaffRole();

$message = '';
$messageType = '';
$editingMaterial = null;

// Get all materials for the listing
$allMaterials = getAllMaterials($conn);

// Determine mode: editing a specific material or viewing the list
$editId = isset($_GET['edit_id']) ? (int)$_GET['edit_id'] : 0;

// Load material data for editing
if ($editId > 0) {
    $editingMaterial = getMaterialById($conn, $editId);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $materialId = (int)($_POST['material_id'] ?? 0);
    $materialName = sanitizeInput($_POST['material_name'] ?? '');
    $physicalQuantity = (float)($_POST['physical_quantity'] ?? 0);
    $unit = sanitizeInput($_POST['unit'] ?? '');
    $reorderLevel = (float)($_POST['reorder_level'] ?? 0);

    $errors = [];

    // Validate inputs
    if (empty($materialName) || strlen($materialName) < 2) {
        $errors[] = 'Material name must be at least 2 characters.';
    }
    if ($physicalQuantity < 0) {
        $errors[] = 'Physical quantity cannot be negative.';
    }
    if (empty($unit)) {
        $errors[] = 'Unit is required.';
    }
    if ($reorderLevel < 0) {
        $errors[] = 'Reorder level cannot be negative.';
    }

    if (empty($errors)) {
        $result = updateMaterial($conn, $materialId, [
            'material_name' => $materialName,
            'physical_quantity' => $physicalQuantity,
            'unit' => $unit,
            'reorder_level' => $reorderLevel
        ]);

        if ($result['success']) {
            $message = $result['message'];
            $messageType = 'success';
            // Refresh the list
            $allMaterials = getAllMaterials($conn);
            // Clear editing state
            $editingMaterial = null;
            $editId = 0;
        } else {
            $message = $result['message'];
            $messageType = 'danger';
        }
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
    <title>Update Material - Staff Panel</title>
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
                    <li><a href="update_material.php" class="active">✏️ Update Material</a></li>
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

                <!-- Quick Stats -->
                <div style="margin-top: var(--space-6); padding: var(--space-4); background: var(--gray-50); border-radius: var(--radius);">
                    <h4 style="font-size: var(--font-size-xs); text-transform: uppercase; color: var(--gray-500); margin-bottom: var(--space-3);">
                        📦 Inventory Summary
                    </h4>
                    <?php
                        $totalMaterials = count($allMaterials);
                        $lowStockCount = 0;
                        foreach ($allMaterials as $m) {
                            if ($m['PhysicalQuantity'] <= $m['ReorderLevel'] && $m['ReorderLevel'] > 0) {
                                $lowStockCount++;
                            }
                        }
                    ?>
                    <p style="font-size: var(--font-size-sm); margin-bottom: var(--space-2);">
                        Total: <strong><?php echo $totalMaterials; ?></strong> materials
                    </p>
                    <p style="font-size: var(--font-size-sm);">
                        Low stock: <strong style="color: var(--danger);"><?php echo $lowStockCount; ?></strong>
                    </p>
                </div>
            </aside>

            <!-- Main Content -->
            <main class="main-content">
                <h2><?php echo $editingMaterial ? 'Edit Material: ' . htmlspecialchars($editingMaterial['MaterialName']) : 'Update Materials'; ?></h2>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <?php if ($editingMaterial): ?>
                    <!-- ══════════════════════════════════════
                    EDIT FORM
                    ══════════════════════════════════════ -->
                    <div style="margin-bottom: var(--space-4);">
                        <a href="update_material.php" class="btn btn-outline btn-sm">← Back to Material List</a>
                    </div>

                    <form method="POST" action="" data-validate>
                        <input type="hidden" name="material_id" value="<?php echo $editingMaterial['MaterialID']; ?>">

                        <div class="form-row">
                            <div class="form-group">
                                <label for="material_name" class="required">Material Name</label>
                                <input type="text" name="material_name" id="material_name"
                                       class="form-control"
                                       required
                                       minlength="2"
                                       maxlength="100"
                                       value="<?php echo htmlspecialchars($editingMaterial['MaterialName']); ?>">
                                <div class="invalid-feedback" id="material_name_error"
                                     style="color: var(--danger); font-size: var(--font-size-xs); margin-top: var(--space-1); display: none;"></div>
                            </div>

                            <div class="form-group">
                                <label for="unit" class="required">Unit</label>
                                <input type="text" name="unit" id="unit"
                                       class="form-control"
                                       required
                                       maxlength="20"
                                       value="<?php echo htmlspecialchars($editingMaterial['Unit']); ?>"
                                       placeholder="e.g., Board Feet, Square Meters, Pieces">
                                <div class="invalid-feedback" id="unit_error"
                                     style="color: var(--danger); font-size: var(--font-size-xs); margin-top: var(--space-1); display: none;"></div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="physical_quantity" class="required">Physical Quantity</label>
                                <input type="number" name="physical_quantity" id="physical_quantity"
                                       class="form-control"
                                       data-type="number"
                                       required
                                       min="0"
                                       step="0.01"
                                       value="<?php echo number_format($editingMaterial['PhysicalQuantity'], 2, '.', ''); ?>">
                                <div class="invalid-feedback" id="physical_quantity_error"
                                     style="color: var(--danger); font-size: var(--font-size-xs); margin-top: var(--space-1); display: none;"></div>
                                <small>Current: <?php echo number_format($editingMaterial['PhysicalQuantity'], 2); ?> <?php echo htmlspecialchars($editingMaterial['Unit']); ?></small>
                            </div>

                            <div class="form-group">
                                <label for="reorder_level">Reorder Level</label>
                                <input type="number" name="reorder_level" id="reorder_level"
                                       class="form-control"
                                       data-type="number"
                                       min="0"
                                       step="0.01"
                                       value="<?php echo number_format($editingMaterial['ReorderLevel'], 2, '.', ''); ?>">
                                <div class="invalid-feedback" id="reorder_level_error"
                                     style="color: var(--danger); font-size: var(--font-size-xs); margin-top: var(--space-1); display: none;"></div>
                                <small>Alert when stock drops below this level. Current reorder level: <?php echo number_format($editingMaterial['ReorderLevel'], 2); ?></small>
                            </div>
                        </div>

                        <!-- Status indicators -->
                        <div style="padding: var(--space-4); background: var(--gray-50); border-radius: var(--radius); border: 1px solid var(--gray-200); margin-top: var(--space-4);">
                            <strong>📊 Status:</strong>
                            <?php if ($editingMaterial['PhysicalQuantity'] <= 0): ?>
                                <span class="badge badge-danger">Out of Stock</span>
                            <?php elseif ($editingMaterial['PhysicalQuantity'] <= $editingMaterial['ReorderLevel'] && $editingMaterial['ReorderLevel'] > 0): ?>
                                <span class="badge badge-warning">Below Reorder Level</span>
                            <?php else: ?>
                                <span class="badge badge-success">Sufficient Stock</span>
                            <?php endif; ?>
                            &nbsp;|&nbsp;
                            <span style="font-size: var(--font-size-sm); color: var(--gray-500);">
                                Last Updated: <?php echo $editingMaterial['LastUpdated']; ?>
                            </span>
                        </div>

                        <div class="form-group" style="margin-top: var(--space-6); display: flex; gap: var(--space-4);">
                            <button type="submit" class="btn btn-primary btn-lg">💾 Save Changes</button>
                            <a href="update_material.php" class="btn btn-outline btn-lg">Cancel</a>
                        </div>
                    </form>

                <?php else: ?>
                    <!-- ══════════════════════════════════════
                    MATERIAL LIST (DEFAULT VIEW)
                    ══════════════════════════════════════ -->
                    <p style="margin-bottom: var(--space-6);">Click <strong>Edit</strong> on any material to update its name, stock quantity, unit, or reorder level.</p>

                    <?php if (empty($allMaterials)): ?>
                        <div class="alert alert-info">No materials found. <a href="insert_material.php">Add your first material</a>.</div>
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
                                        <th class="sortable">Last Updated</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($allMaterials as $material):
                                        $belowReorder = ($material['ReorderLevel'] > 0 && $material['PhysicalQuantity'] <= $material['ReorderLevel']);
                                        $outOfStock = ($material['PhysicalQuantity'] <= 0);
                                    ?>
                                        <tr>
                                            <td><?php echo $material['MaterialID']; ?></td>
                                            <td><strong><?php echo htmlspecialchars($material['MaterialName']); ?></strong></td>
                                            <td><?php echo number_format($material['PhysicalQuantity'], 2); ?></td>
                                            <td><?php echo htmlspecialchars($material['Unit']); ?></td>
                                            <td><?php echo number_format($material['ReorderLevel'], 2); ?></td>
                                            <td><?php echo $material['LastUpdated']; ?></td>
                                            <td>
                                                <?php if ($outOfStock): ?>
                                                    <span class="badge badge-danger">Out of Stock</span>
                                                <?php elseif ($belowReorder): ?>
                                                    <span class="badge badge-warning">Low Stock</span>
                                                <?php else: ?>
                                                    <span class="badge badge-success">In Stock</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="update_material.php?edit_id=<?php echo $material['MaterialID']; ?>"
                                                   class="btn btn-sm btn-primary">✏️ Edit</a>
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
                                $inStock = count(array_filter($allMaterials, function($m) { return $m['PhysicalQuantity'] > $m['ReorderLevel'] || $m['ReorderLevel'] == 0; }));
                                $lowStock = count(array_filter($allMaterials, function($m) { return $m['PhysicalQuantity'] <= $m['ReorderLevel'] && $m['ReorderLevel'] > 0 && $m['PhysicalQuantity'] > 0; }));
                                $outOfStock = count(array_filter($allMaterials, function($m) { return $m['PhysicalQuantity'] <= 0; }));
                            ?>
                            <?php echo $total; ?> total materials
                            &nbsp;|&nbsp;
                            <span style="color: var(--success);"><?php echo $inStock; ?> in stock</span>
                            &nbsp;|&nbsp;
                            <span style="color: var(--warning);"><?php echo $lowStock; ?> low stock</span>
                            &nbsp;|&nbsp;
                            <span style="color: var(--danger);"><?php echo $outOfStock; ?> out of stock</span>
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

    <script src="../assets/js/validation.js"></script>
    <script>
        // ── Reorder level warning ──────────────────
        const qtyInput = document.getElementById('physical_quantity');
        const reorderInput = document.getElementById('reorder_level');
        const qtyError = document.getElementById('physical_quantity_error');

        function checkReorderWarning() {
            if (!qtyInput || !reorderInput || !qtyError) return;
            const qty = parseFloat(qtyInput.value) || 0;
            const reorder = parseFloat(reorderInput.value) || 0;

            if (qty === 0) {
                qtyError.textContent = '⚠️ Setting quantity to 0 will mark this material as Out of Stock.';
                qtyError.style.display = 'block';
                qtyError.style.color = 'var(--danger)';
                qtyInput.style.borderColor = 'var(--danger)';
            } else if (reorder > 0 && qty <= reorder) {
                qtyError.textContent = '⚠️ Quantity is at or below the reorder level. Consider restocking.';
                qtyError.style.display = 'block';
                qtyError.style.color = 'var(--warning)';
                qtyInput.style.borderColor = 'var(--warning)';
            } else {
                qtyError.style.display = 'none';
                qtyInput.style.borderColor = '';
            }
        }

        qtyInput?.addEventListener('input', checkReorderWarning);
        reorderInput?.addEventListener('input', checkReorderWarning);

        // ── Form validation ────────────────────────
        document.querySelector('form[data-validate]')?.addEventListener('submit', function(e) {
            let hasErrors = false;
            const name = document.getElementById('material_name');
            const unit = document.getElementById('unit');
            const qty = document.getElementById('physical_quantity');

            if (name && name.value.trim().length < 2) {
                showFieldError(name, 'material_name_error', 'Material name must be at least 2 characters.');
                hasErrors = true;
            }
            if (unit && unit.value.trim() === '') {
                showFieldError(unit, 'unit_error', 'Unit is required.');
                hasErrors = true;
            }
            if (qty && parseFloat(qty.value) < 0) {
                showFieldError(qty, 'physical_quantity_error', 'Quantity cannot be negative.');
                hasErrors = true;
            }

            if (hasErrors) {
                e.preventDefault();
                const firstError = document.querySelector('.is-invalid');
                if (firstError) firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });

        function showFieldError(field, errorId, message) {
            const errorEl = document.getElementById(errorId);
            if (errorEl) {
                errorEl.textContent = message;
                errorEl.style.display = 'block';
            }
            field.style.borderColor = 'var(--danger)';
            field.classList.add('is-invalid');
        }

        // Clear errors on input
        document.querySelectorAll('input').forEach(input => {
            input.addEventListener('input', function() {
                this.style.borderColor = '';
                this.classList.remove('is-invalid');
                const errorEl = document.getElementById(this.id + '_error');
                if (errorEl) errorEl.style.display = 'none';
            });
        });
    </script>
</body>
</html>