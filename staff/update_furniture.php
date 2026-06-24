<?php
/**
 * update_furniture.php - Staff Update Furniture Page
 * Allows staff to edit existing furniture products and their material requirements
 * Part I - Function #2: Update Furniture
 */

require_once '../inc/config.php';
require_once '../inc/session.php';
require_once '../inc/functions.php';

// Check if user is logged in as staff
checkStaffRole();

$message = '';
$messageType = '';
$editingFurniture = null;

// Get all materials for selection
$materials = getAllMaterials($conn);

// Get all furniture for the listing
$allFurniture = getAllFurniture($conn);

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

// Determine mode: editing a specific product or viewing the list
$editId = isset($_GET['edit_id']) ? (int)$_GET['edit_id'] : 0;

// Load furniture data for editing
if ($editId > 0) {
    $editingFurniture = getFurnitureById($conn, $editId);
    if ($editingFurniture) {
        // Load existing materials for this furniture
        $editingFurniture['materials'] = getMaterialsByFurnitureId($conn, $editId);
        // Build a lookup map: MaterialID => MaterialQuantity
        $editingFurniture['material_map'] = [];
        foreach ($editingFurniture['materials'] as $m) {
            $editingFurniture['material_map'][$m['MaterialID']] = $m['MaterialQuantity'];
        }
        // Load existing product view images
        $editingFurniture['view_images'] = getFurnitureImages($conn, $editId);
        // Load existing customization options
        $editingFurniture['colors'] = getFurnitureColors($conn, $editId);
        $editingFurniture['sizes'] = getFurnitureSizes($conn, $editId);
        $editingFurniture['accessories'] = getFurnitureAccessories($conn, $editId);
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $furnitureId = (int)($_POST['furniture_id'] ?? 0);
    $furnitureName = sanitizeInput($_POST['furniture_name'] ?? '');
    $furnitureDescription = sanitizeInput($_POST['furniture_description'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $stockQuantity = (int)($_POST['stock_quantity'] ?? 0);
    $category = sanitizeInput($_POST['category'] ?? '');

    // Get selected materials
    $selectedMaterials = [];
    if (isset($_POST['materials']) && is_array($_POST['materials'])) {
        foreach ($_POST['materials'] as $materialId => $materialData) {
            $qty = isset($materialData['quantity']) ? (float)$materialData['quantity'] : 0;
            if ($qty > 0) {
                $selectedMaterials[] = [
                    'id' => (int)$materialId,
                    'quantity' => $qty
                ];
            }
        }
    }

    $errors = [];

    // Validate inputs
    if (empty($furnitureName) || strlen($furnitureName) < 2) {
        $errors[] = 'Furniture name must be at least 2 characters.';
    }
    if (empty($furnitureDescription) || strlen($furnitureDescription) < 10) {
        $errors[] = 'Description must be at least 10 characters.';
    }
    if ($price <= 0) {
        $errors[] = 'Price must be greater than 0.';
    }
    if ($stockQuantity < 0) {
        $errors[] = 'Stock quantity cannot be negative.';
    }
    if (empty($category)) {
        $errors[] = 'Category is required.';
    }
    if (empty($selectedMaterials)) {
        $errors[] = 'At least one material is required.';
    }

    // Handle image upload (optional for updates)
    $imageFile = $_FILES['furniture_image'] ?? null;
    $hasNewImage = $imageFile && $imageFile['error'] === UPLOAD_ERR_OK;

    if ($hasNewImage) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($imageFile['type'], $allowedTypes)) {
            $errors[] = 'Only JPG, PNG, GIF, and WEBP images are allowed.';
        }
        if ($imageFile['size'] > MAX_FILE_SIZE) {
            $errors[] = 'Image size must be less than 2MB.';
        }
    }

    // Handle view image deletions
    $deleteViewIds = isset($_POST['delete_views']) ? array_map('intval', explode(',', $_POST['delete_views'])) : [];

    // Handle customization deletions/additions
    $deleteCustom = isset($_POST['delete_custom']) ? json_decode($_POST['delete_custom'], true) : [];
    $addCustom = isset($_POST['add_custom']) ? json_decode($_POST['add_custom'], true) : [];

    if (empty($errors)) {
        $result = updateFurniture($conn, $furnitureId, [
            'furniture_name' => $furnitureName,
            'furniture_description' => $furnitureDescription,
            'price' => $price,
            'stock_quantity' => $stockQuantity,
            'category' => $category,
            'materials' => $selectedMaterials
        ], $hasNewImage ? $imageFile : null);

        if ($result['success']) {
            // Process view image deletions
            if (!empty($deleteViewIds)) {
                foreach ($deleteViewIds as $imgId) {
                    // Get image path before deleting DB record so we can delete file
                    $checkSql = "SELECT ImagePath FROM FurnitureImage WHERE ImageID = ?";
                    $checkStmt = mysqli_prepare($conn, $checkSql);
                    mysqli_stmt_bind_param($checkStmt, "i", $imgId);
                    mysqli_stmt_execute($checkStmt);
                    $checkResult = mysqli_stmt_get_result($checkStmt);
                    if ($imgRow = mysqli_fetch_assoc($checkResult)) {
                        $filePath = FURNITURE_IMAGE_UPLOAD_PATH . $imgRow['ImagePath'];
                        if (file_exists($filePath)) @unlink($filePath);
                    }
                    mysqli_stmt_close($checkStmt);
                    $delSql = "DELETE FROM FurnitureImage WHERE ImageID = ?";
                    $delStmt = mysqli_prepare($conn, $delSql);
                    mysqli_stmt_bind_param($delStmt, "i", $imgId);
                    mysqli_stmt_execute($delStmt);
                    mysqli_stmt_close($delStmt);
                }
            }

            // Process new view image uploads
            $viewImages = $_FILES['view_images'] ?? [];
            $uploadedViews = 0;
            if (!empty($viewImages['name']) && is_array($viewImages['name'])) {
                $viewEntries = [];
                // Get current max sort order for this product
                $maxSort = 1;
                $existingViews = getFurnitureImages($conn, $furnitureId);
                foreach ($existingViews as $ev) {
                    if ($ev['SortOrder'] > $maxSort) $maxSort = $ev['SortOrder'];
                }
                for ($i = 0; $i < count($viewImages['name']); $i++) {
                    if ($viewImages['error'][$i] === UPLOAD_ERR_OK && !empty($viewImages['name'][$i])) {
                        $ext = pathinfo($viewImages['name'][$i], PATHINFO_EXTENSION);
                        $viewName = time() . '_view' . ($maxSort + $i + 1) . '_' . uniqid() . '.png';
                        $dest = FURNITURE_IMAGE_UPLOAD_PATH . $viewName;
                        if (move_uploaded_file($viewImages['tmp_name'][$i], $dest)) {
                            $viewEntries[] = [
                                'path' => $viewName,
                                'sort_order' => $maxSort + count($viewEntries) + 1,
                                'is_primary' => 0
                            ];
                        }
                    }
                }
                if (!empty($viewEntries)) {
                    insertFurnitureImages($conn, $furnitureId, $viewEntries);
                    $uploadedViews = count($viewEntries);
                }
            }

            // Process customization deletions
            $customChanges = 0;
            if (!empty($deleteCustom)) {
                foreach ($deleteCustom as $dc) {
                    $type = $dc['type'] ?? '';
                    $id = (int)($dc['id'] ?? 0);
                    if ($id > 0) {
                        if ($type === 'color') { $sql = "DELETE FROM FurnitureColor WHERE ColorID = ? AND FurnitureID = ?"; }
                        elseif ($type === 'size') { $sql = "DELETE FROM FurnitureSize WHERE SizeID = ? AND FurnitureID = ?"; }
                        elseif ($type === 'accessory') { $sql = "DELETE FROM FurnitureAccessory WHERE AccessoryID = ? AND FurnitureID = ?"; }
                        else continue;
                        $delStmt = mysqli_prepare($conn, $sql);
                        mysqli_stmt_bind_param($delStmt, "ii", $id, $furnitureId);
                        mysqli_stmt_execute($delStmt);
                        mysqli_stmt_close($delStmt);
                        $customChanges++;
                    }
                }
            }
            // Process customization additions
            if (!empty($addCustom)) {
                foreach ($addCustom as $ac) {
                    $type = $ac['type'] ?? '';
                    $name = trim($ac['name'] ?? '');
                    if (empty($name)) continue;
                    $price = floatval($ac['price'] ?? 0);
                    if ($type === 'color') {
                        $hex = trim($ac['hex'] ?? '#cccccc');
                        insertFurnitureColors($conn, $furnitureId, [['name' => $name, 'hex' => $hex, 'additional_price' => $price, 'sort_order' => 99]]);
                        $customChanges++;
                    } elseif ($type === 'size') {
                        $dim = trim($ac['dim'] ?? '');
                        insertFurnitureSizes($conn, $furnitureId, [['name' => $name, 'dimensions' => $dim, 'additional_price' => $price, 'sort_order' => 99]]);
                        $customChanges++;
                    } elseif ($type === 'accessory') {
                        $desc = trim($ac['desc'] ?? '');
                        insertFurnitureAccessories($conn, $furnitureId, [['name' => $name, 'description' => $desc, 'additional_price' => $price, 'sort_order' => 99]]);
                        $customChanges++;
                    }
                }
            }

            $message = $result['message'];
            if (!empty($deleteViewIds)) $message .= ' (' . count($deleteViewIds) . ' views removed.)';
            if ($uploadedViews > 0) $message .= ' (' . $uploadedViews . ' new views added.)';
            if ($customChanges > 0) $message .= ' (' . $customChanges . ' customization changes.)';
            $messageType = 'success';
            // Refresh the list
            $allFurniture = getAllFurniture($conn);
            // Clear editing state to go back to list view
            $editingFurniture = null;
            $editId = 0;
        } else {
            $message = $result['message'];
            $messageType = 'danger';
            // Reload editing data
            if ($editId > 0) {
                $editingFurniture = getFurnitureById($conn, $editId);
                if ($editingFurniture) {
                    $editingFurniture['materials'] = getMaterialsByFurnitureId($conn, $editId);
                    $editingFurniture['material_map'] = [];
                    foreach ($editingFurniture['materials'] as $m) {
                        $editingFurniture['material_map'][$m['MaterialID']] = $m['MaterialQuantity'];
                    }
                    $editingFurniture['view_images'] = getFurnitureImages($conn, $editId);
                }
            }
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
    <title>Update Furniture - Staff Panel</title>
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
                    <li><a href="update_furniture.php" class="active">✏️ Update Furniture</a></li>
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
            </aside>

            <!-- Main Content -->
            <main class="main-content">
                <h2><?php echo $editingFurniture ? 'Edit: ' . htmlspecialchars($editingFurniture['FurnitureName']) : 'Update Furniture Products'; ?></h2>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <?php if ($editingFurniture): ?>
                    <!-- ══════════════════════════════════════
                    EDIT FORM
                    ══════════════════════════════════════ -->
                    <div style="margin-bottom: var(--space-4);">
                        <a href="update_furniture.php" class="btn btn-outline btn-sm">← Back to Product List</a>
                    </div>

                    <form method="POST" action="" enctype="multipart/form-data" data-validate>
                        <input type="hidden" name="furniture_id" value="<?php echo $editingFurniture['FurnitureID']; ?>">

                        <div class="form-row">
                            <div class="form-group">
                                <label for="furniture_name" class="required">Furniture Name</label>
                                <input type="text" name="furniture_name" id="furniture_name"
                                       class="form-control"
                                       required
                                       minlength="2"
                                       maxlength="100"
                                       value="<?php echo htmlspecialchars($editingFurniture['FurnitureName']); ?>">
                                <div class="invalid-feedback" id="furniture_name_error"
                                     style="color: var(--danger); font-size: var(--font-size-xs); margin-top: var(--space-1); display: none;"></div>
                            </div>

                            <div class="form-group">
                                <label for="category" class="required">Category</label>
                                <select name="category" id="category" class="form-control" required>
                                    <option value="">Select Category</option>
                                    <?php
                                    $categories = ['Desks', 'Chairs', 'Tables', 'Sofas', 'Storage', 'Beds'];
                                    foreach ($categories as $cat):
                                        $sel = ($editingFurniture['Category'] === $cat) ? 'selected' : '';
                                    ?>
                                        <option value="<?php echo $cat; ?>" <?php echo $sel; ?>><?php echo $cat; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback" id="category_error"
                                     style="color: var(--danger); font-size: var(--font-size-xs); margin-top: var(--space-1); display: none;"></div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="furniture_description" class="required">Description</label>
                            <textarea name="furniture_description" id="furniture_description"
                                      class="form-control"
                                      required
                                      minlength="10"
                                      maxlength="1000"
                                      rows="4"><?php echo htmlspecialchars($editingFurniture['FurnitureDescription']); ?></textarea>
                            <div class="invalid-feedback" id="furniture_description_error"
                                 style="color: var(--danger); font-size: var(--font-size-xs); margin-top: var(--space-1); display: none;"></div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="price" class="required">Price ($)</label>
                                <input type="number" name="price" id="price"
                                       class="form-control"
                                       data-type="number"
                                       required
                                       min="0.01"
                                       step="0.01"
                                       value="<?php echo number_format($editingFurniture['Price'], 2, '.', ''); ?>">
                                <div class="invalid-feedback" id="price_error"
                                     style="color: var(--danger); font-size: var(--font-size-xs); margin-top: var(--space-1); display: none;"></div>
                            </div>

                            <div class="form-group">
                                <label for="stock_quantity">Stock Quantity</label>
                                <input type="number" name="stock_quantity" id="stock_quantity"
                                       class="form-control"
                                       data-type="number"
                                       min="0"
                                       step="1"
                                       value="<?php echo $editingFurniture['StockQuantity']; ?>">
                                <div class="invalid-feedback" id="stock_quantity_error"
                                     style="color: var(--danger); font-size: var(--font-size-xs); margin-top: var(--space-1); display: none;"></div>
                                <small>Current stock: <?php echo $editingFurniture['StockQuantity']; ?> units</small>
                            </div>
                        </div>

                        <!-- Current Image + Optional New Image -->
                        <div class="form-group">
                            <label>Current Image</label>
                            <div style="margin-bottom: var(--space-3);">
                                <img src="../assets/images/furniture/<?php echo htmlspecialchars($editingFurniture['FurnitureImage']); ?>"
                                     style="width: 150px; border-radius: var(--radius); border: 2px solid var(--gray-200);"
                                     onerror="this.src='../assets/images/furniture/placeholder.jpg'"
                                     alt="Current image">
                                <p style="font-size: var(--font-size-xs); color: var(--gray-500); margin-top: var(--space-1);">
                                    <?php echo htmlspecialchars($editingFurniture['FurnitureImage']); ?>
                                </p>
                            </div>
                            <label for="furniture_image">Upload New Image <small>(optional — leave empty to keep current)</small></label>
                            <input type="file" name="furniture_image" id="furniture_image"
                                   class="form-control"
                                   accept="image/jpeg,image/png,image/gif,image/webp"
                                   data-preview="image_preview">
                            <div id="image_preview_container" style="margin-top: var(--space-2);">
                                <img id="image_preview" style="max-width: 200px; display: none; border-radius: 8px; border: 2px dashed var(--gray-300);">
                            </div>
                            <small>Accepted: JPG, PNG, GIF, WEBP. Max: 2MB</small>
                        </div>

                        <!-- Existing Product Views -->
                        <?php $views = $editingFurniture['view_images'] ?? []; ?>
                        <div class="form-group">
                            <label>Product Views Gallery <small>(<?php echo count($views); ?> view<?php echo count($views) !== 1 ? 's' : ''; ?>)</small></label>
                            <?php if (!empty($views)): ?>
                                <div style="display:flex;gap:var(--space-3);flex-wrap:wrap;margin-bottom:var(--space-3);">
                                    <?php foreach ($views as $v): ?>
                                        <div style="position:relative;display:inline-block;" id="view-container-<?php echo $v['ImageID']; ?>">
                                            <img src="../assets/images/furniture/<?php echo htmlspecialchars(str_replace(' ', '%20', $v['ImagePath'])); ?>"
                                                 style="width:100px;height:100px;object-fit:cover;border-radius:8px;border:2px solid <?php echo $v['IsPrimary'] ? 'var(--primary)' : 'var(--gray-200)'; ?>;"
                                                 alt="View <?php echo $v['SortOrder']; ?>"
                                                 onerror="this.style.display='none';this.parentElement.style.display='none';">
                                            <?php if (!$v['IsPrimary']): ?>
                                                <button type="button"
                                                        onclick="markForDeletion(<?php echo $v['ImageID']; ?>, this.parentElement)"
                                                        style="position:absolute;top:-6px;right:-6px;background:var(--danger);color:#fff;border:none;width:22px;height:22px;border-radius:50%;cursor:pointer;font-size:0.75rem;line-height:1;"
                                                        title="Remove this view">✕</button>
                                            <?php else: ?>
                                                <span style="position:absolute;bottom:4px;left:4px;background:var(--primary);color:#fff;padding:1px 6px;border-radius:4px;font-size:0.65rem;font-weight:600;">Primary</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p style="font-size:0.85rem;color:var(--gray-500);margin-bottom:var(--space-3);">No additional views yet.</p>
                            <?php endif; ?>
                            <input type="hidden" name="delete_views" id="delete_views_input" value="">
                            <div id="delete-views-notice" style="display:none;color:var(--danger);font-size:0.85rem;margin-bottom:var(--space-2);">
                                ⚠️ <span id="delete-count">0</span> view(s) marked for deletion. Save changes to confirm.
                            </div>

                            <label style="margin-top:var(--space-3);">Add New Views <small>(optional, up to 3)</small></label>
                            <div id="view-images-container">
                                <?php for ($vi = 0; $vi < 3; $vi++): ?>
                                    <div class="view-image-row" style="display:flex;align-items:center;gap:var(--space-3);margin-bottom:var(--space-2);">
                                        <input type="file" name="view_images[]"
                                               class="form-control"
                                               accept="image/jpeg,image/png,image/gif,image/webp"
                                               style="flex:1;"
                                               onchange="previewViewImage(this, <?php echo $vi; ?>)">
                                        <img class="view-preview" style="width:48px;height:48px;object-fit:cover;border-radius:6px;display:none;border:1px solid var(--gray-300);">
                                    </div>
                                <?php endfor; ?>
                            </div>
                            <small>Each file max 2MB. New views will be added to the gallery.</small>
                        </div>

                        <!-- Materials Section -->
                        <h3 style="margin-top: var(--space-8); margin-bottom: var(--space-4);">🧱 Materials Required</h3>
                        <p style="margin-bottom: var(--space-4);">Update the materials and quantities needed to produce this furniture product.</p>

                        <div class="table-container">
                            <table class="materials-table">
                                <thead>
                                    <tr>
                                        <th>Select</th>
                                        <th>Material Name</th>
                                        <th>Available Quantity</th>
                                        <th>Unit</th>
                                        <th>Quantity Required per Unit</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($materials as $material):
                                        $existingQty = $editingFurniture['material_map'][$material['MaterialID']] ?? 0;
                                        $isChecked = $existingQty > 0;
                                    ?>
                                        <tr>
                                            <td style="text-align: center;">
                                                <input type="checkbox"
                                                       class="material-checkbox"
                                                       data-material-id="<?php echo $material['MaterialID']; ?>"
                                                       onchange="toggleMaterialInput(<?php echo $material['MaterialID']; ?>, this.checked)"
                                                       <?php echo $isChecked ? 'checked' : ''; ?>>
                                            </td>
                                            <td><?php echo htmlspecialchars($material['MaterialName']); ?></td>
                                            <td><?php echo number_format($material['PhysicalQuantity'], 2); ?></td>
                                            <td><?php echo htmlspecialchars($material['Unit']); ?></td>
                                            <td>
                                                <input type="hidden" name="materials[<?php echo $material['MaterialID']; ?>][selected]" value="0">
                                                <input type="number"
                                                       name="materials[<?php echo $material['MaterialID']; ?>][quantity]"
                                                       id="material_quantity_<?php echo $material['MaterialID']; ?>"
                                                       class="form-control material-quantity"
                                                       style="width: 120px;"
                                                       min="0.01"
                                                       step="0.01"
                                                       placeholder="Quantity"
                                                       value="<?php echo $isChecked ? number_format($existingQty, 2) : ''; ?>"
                                                       <?php echo !$isChecked ? 'disabled' : ''; ?>>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Customization Options -->
                        <?php $ecolors = $editingFurniture['colors'] ?? []; $esizes = $editingFurniture['sizes'] ?? []; $eaccs = $editingFurniture['accessories'] ?? []; ?>
                        <h3 style="margin-top:var(--space-8);">🎨 Customization Options <small>(colors, sizes, accessories)</small></h3>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-6);">
                            <div>
                                <h4>🎨 Colors (<?php echo count($ecolors); ?>)</h4>
                                <?php foreach ($ecolors as $ec): ?>
                                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;padding:6px 10px;background:var(--gray-50);border-radius:8px;">
                                        <span style="width:20px;height:20px;border-radius:50%;background:<?php echo htmlspecialchars($ec['ColorHex']); ?>;border:1px solid #ddd;flex-shrink:0;"></span>
                                        <span style="flex:1;"><?php echo htmlspecialchars($ec['ColorName']); ?></span>
                                        <?php if ($ec['AdditionalPrice'] > 0): ?><span style="color:var(--accent);font-size:0.85rem;">+$<?php echo number_format($ec['AdditionalPrice'], 2); ?></span><?php endif; ?>
                                        <button type="button" onclick="deleteCustomItem('color',<?php echo $ec['ColorID']; ?>,this.parentElement)" style="background:none;border:none;color:var(--danger);cursor:pointer;font-size:0.9rem;" title="Remove">✕</button>
                                    </div>
                                <?php endforeach; ?>
                                <div style="display:flex;gap:6px;margin-top:6px;">
                                    <input type="text" id="newColorName" placeholder="Color name" class="form-control" style="flex:2;">
                                    <input type="text" id="newColorHex" placeholder="#hex" class="form-control" style="flex:1;max-width:80px;">
                                    <input type="number" id="newColorPrice" placeholder="+$" class="form-control" style="flex:1;max-width:60px;" step="0.01" min="0" value="0">
                                    <button type="button" class="btn btn-sm btn-outline" onclick="addCustomItem('color')">+</button>
                                </div>
                            </div>
                            <div>
                                <h4>📏 Sizes (<?php echo count($esizes); ?>)</h4>
                                <?php foreach ($esizes as $es): ?>
                                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;padding:6px 10px;background:var(--gray-50);border-radius:8px;">
                                        <span style="flex:1;"><strong><?php echo htmlspecialchars($es['SizeName']); ?></strong><?php echo $es['Dimensions'] ? ' <small style=\"color:var(--gray-500);\">(' . htmlspecialchars($es['Dimensions']) . ')</small>' : ''; ?></span>
                                        <?php if ($es['AdditionalPrice'] > 0): ?><span style="color:var(--accent);font-size:0.85rem;">+$<?php echo number_format($es['AdditionalPrice'], 2); ?></span><?php endif; ?>
                                        <button type="button" onclick="deleteCustomItem('size',<?php echo $es['SizeID']; ?>,this.parentElement)" style="background:none;border:none;color:var(--danger);cursor:pointer;font-size:0.9rem;" title="Remove">✕</button>
                                    </div>
                                <?php endforeach; ?>
                                <div style="display:flex;gap:6px;margin-top:6px;">
                                    <input type="text" id="newSizeName" placeholder="Size name" class="form-control" style="flex:2;">
                                    <input type="text" id="newSizeDim" placeholder="Dimensions" class="form-control" style="flex:2;">
                                    <input type="number" id="newSizePrice" placeholder="+$" class="form-control" style="flex:1;max-width:60px;" step="0.01" min="0" value="0">
                                    <button type="button" class="btn btn-sm btn-outline" onclick="addCustomItem('size')">+</button>
                                </div>
                            </div>
                        </div>
                        <div style="margin-top:var(--space-4);">
                            <h4>🔧 Accessories (<?php echo count($eaccs); ?>)</h4>
                            <?php foreach ($eaccs as $ea): ?>
                                <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;padding:6px 10px;background:var(--gray-50);border-radius:8px;">
                                    <span style="flex:1;"><strong><?php echo htmlspecialchars($ea['AccessoryName']); ?></strong><?php echo $ea['Description'] ? ' — <small style=\"color:var(--gray-500);\">' . htmlspecialchars($ea['Description']) . '</small>' : ''; ?></span>
                                    <span style="color:var(--accent);font-weight:600;font-size:0.85rem;">+$<?php echo number_format($ea['AdditionalPrice'], 2); ?></span>
                                    <button type="button" onclick="deleteCustomItem('accessory',<?php echo $ea['AccessoryID']; ?>,this.parentElement)" style="background:none;border:none;color:var(--danger);cursor:pointer;font-size:0.9rem;" title="Remove">✕</button>
                                </div>
                            <?php endforeach; ?>
                            <div style="display:flex;gap:6px;margin-top:6px;">
                                <input type="text" id="newAccName" placeholder="Name" class="form-control" style="flex:2;">
                                <input type="text" id="newAccDesc" placeholder="Description" class="form-control" style="flex:3;">
                                <input type="number" id="newAccPrice" placeholder="+$" class="form-control" style="flex:1;max-width:60px;" step="0.01" min="0" value="0">
                                <button type="button" class="btn btn-sm btn-outline" onclick="addCustomItem('accessory')">+</button>
                            </div>
                        </div>
                        <input type="hidden" name="delete_custom" id="deleteCustomInput" value="">
                        <input type="hidden" name="add_custom" id="addCustomInput" value="">

                        <div class="form-group" style="margin-top: var(--space-6); display: flex; gap: var(--space-4);">
                            <button type="submit" class="btn btn-primary btn-lg">💾 Save Changes</button>
                            <a href="update_furniture.php" class="btn btn-outline btn-lg">Cancel</a>
                        </div>
                    </form>

                <?php else: ?>
                    <!-- ══════════════════════════════════════
                    FURNITURE LIST (DEFAULT VIEW)
                    ══════════════════════════════════════ -->
                    <p style="margin-bottom: var(--space-6);">Click <strong>Edit</strong> on any product to modify its details, price, stock, image, or material requirements.</p>

                    <?php if (empty($allFurniture)): ?>
                        <div class="alert alert-info">No furniture products found. <a href="insert_furniture.php">Add your first product</a>.</div>
                    <?php else: ?>
                        <div class="table-container">
                            <table data-sortable>
                                <thead>
                                    <tr>
                                        <th class="sortable">ID</th>
                                        <th>Image</th>
                                        <th class="sortable">Name</th>
                                        <th class="sortable">Category</th>
                                        <th class="sortable">Price</th>
                                        <th class="sortable">Stock</th>
                                        <th>Status</th>
                                        <th>Materials</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($allFurniture as $furniture):
                                        $matCount = count(getMaterialsByFurnitureId($conn, $furniture['FurnitureID']));
                                        $stockStatus = $furniture['StockQuantity'] <= 0 ? 'sold-out' :
                                                      ($furniture['StockQuantity'] < 5 ? 'low-stock' : 'in-stock');
                                        $statusLabel = $furniture['StockQuantity'] <= 0 ? 'Sold Out' :
                                                      ($furniture['StockQuantity'] < 5 ? 'Low Stock' : 'In Stock');
                                    ?>
                                        <tr>
                                            <td><?php echo $furniture['FurnitureID']; ?></td>
                                            <td>
                                                <?php
                                                $pid = $furniture['FurnitureID'];
                                                $views = $listImages[$pid] ?? [];
                                                $pri = null;
                                                foreach ($views as $v) { if ($v['IsPrimary']) { $pri = $v; break; } }
                                                if (!$pri && !empty($views)) $pri = $views[0];
                                                $listImgSrc = $pri
                                                    ? '../assets/images/furniture/' . str_replace(' ', '%20', $pri['ImagePath'])
                                                    : '../assets/images/furniture/' . htmlspecialchars($furniture['FurnitureImage']);
                                                ?>
                                                <img src="<?php echo $listImgSrc; ?>"
                                                     style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px;"
                                                     onerror="this.src='../assets/images/furniture/placeholder.jpg'"
                                                     alt="<?php echo htmlspecialchars($furniture['FurnitureName']); ?>">
                                            </td>
                                            <td><strong><?php echo htmlspecialchars($furniture['FurnitureName']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($furniture['Category']); ?></td>
                                            <td><?php echo formatCurrency($furniture['Price']); ?></td>
                                            <td><?php echo $furniture['StockQuantity']; ?></td>
                                            <td>
                                                <span class="card-stock <?php echo $stockStatus; ?>"><?php echo $statusLabel; ?></span>
                                            </td>
                                            <td>
                                                <span style="font-size: var(--font-size-xs); color: var(--gray-500);">
                                                    <?php echo $matCount; ?> material<?php echo $matCount !== 1 ? 's' : ''; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="update_furniture.php?edit_id=<?php echo $furniture['FurnitureID']; ?>"
                                                   class="btn btn-sm btn-primary">✏️ Edit</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div style="margin-top: var(--space-6); padding: var(--space-4); background: var(--gray-50); border-radius: var(--radius); border: 1px solid var(--gray-200);">
                            <strong>📊 Summary:</strong> <?php echo count($allFurniture); ?> total products
                            &nbsp;|&nbsp;
                            <?php
                                $soldOut = count(array_filter($allFurniture, function($f) { return $f['StockQuantity'] <= 0; }));
                                $lowStock = count(array_filter($allFurniture, function($f) { return $f['StockQuantity'] > 0 && $f['StockQuantity'] < 5; }));
                                $inStock = count($allFurniture) - $soldOut - $lowStock;
                            ?>
                            <span style="color: var(--success);"><?php echo $inStock; ?> in stock</span>
                            &nbsp;|&nbsp;
                            <span style="color: var(--warning);"><?php echo $lowStock; ?> low stock</span>
                            &nbsp;|&nbsp;
                            <span style="color: var(--danger);"><?php echo $soldOut; ?> sold out</span>
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
        // ── Toggle material quantity input ─────────
        function toggleMaterialInput(materialId, isChecked) {
            const quantityInput = document.getElementById(`material_quantity_${materialId}`);
            if (quantityInput) {
                quantityInput.disabled = !isChecked;
                if (!isChecked) {
                    quantityInput.value = '';
                } else if (!quantityInput.value) {
                    quantityInput.value = '1.00';
                }
            }
        }

        // ── Image preview ──────────────────────────
        document.getElementById('furniture_image')?.addEventListener('change', function(e) {
            const file = e.target.files[0];
            const preview = document.getElementById('image_preview');
            if (file && preview) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    preview.src = event.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        });

        // ── View image management ──────────────────
        let deletedViewIds = [];
        function markForDeletion(imageId, container) {
            if (deletedViewIds.includes(imageId)) return;
            deletedViewIds.push(imageId);
            container.style.opacity = '0.3';
            container.style.pointerEvents = 'none';
            document.getElementById('delete_views_input').value = deletedViewIds.join(',');
            const notice = document.getElementById('delete-views-notice');
            const countEl = document.getElementById('delete-count');
            if (notice && countEl) {
                notice.style.display = 'block';
                countEl.textContent = deletedViewIds.length;
            }
        }

        function previewViewImage(input, index) {
            const row = input.closest('.view-image-row');
            const preview = row ? row.querySelector('.view-preview') : null;
            if (input.files && input.files[0] && preview) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        // ── Real-time stock quantity warning ────────
        document.getElementById('stock_quantity')?.addEventListener('input', function() {
            const val = parseInt(this.value);
            const errorEl = document.getElementById('stock_quantity_error');
            if (val === 0) {
                errorEl.textContent = '⚠️ Setting stock to 0 will mark this product as Sold Out.';
                errorEl.style.display = 'block';
                errorEl.style.color = 'var(--danger)';
                this.style.borderColor = 'var(--danger)';
            } else if (val > 0 && val < 5) {
                errorEl.textContent = '⚠️ Low stock warning (below 5 units).';
                errorEl.style.display = 'block';
                errorEl.style.color = 'var(--warning)';
                this.style.borderColor = 'var(--warning)';
            } else {
                errorEl.style.display = 'none';
                this.style.borderColor = '';
            }
        });

        // ── Customization management ────────────────
        let deleteCustomList = [];
        let addCustomList = [];

        function deleteCustomItem(type, id, el) {
            deleteCustomList.push({type: type, id: id});
            document.getElementById('deleteCustomInput').value = JSON.stringify(deleteCustomList);
            if (el) el.style.opacity = '0.3';
            if (el) el.style.pointerEvents = 'none';
        }

        function addCustomItem(type) {
            let name = '', hex = '', dim = '', desc = '', price = 0;
            if (type === 'color') {
                name = document.getElementById('newColorName').value.trim();
                hex = document.getElementById('newColorHex').value.trim();
                price = parseFloat(document.getElementById('newColorPrice').value) || 0;
                if (!name) return alert('Enter a color name.');
                addCustomList.push({type: type, name: name, hex: hex || '#cccccc', price: price});
                document.getElementById('newColorName').value = '';
                document.getElementById('newColorHex').value = '';
                document.getElementById('newColorPrice').value = '0';
            } else if (type === 'size') {
                name = document.getElementById('newSizeName').value.trim();
                dim = document.getElementById('newSizeDim').value.trim();
                price = parseFloat(document.getElementById('newSizePrice').value) || 0;
                if (!name) return alert('Enter a size name.');
                addCustomList.push({type: type, name: name, dim: dim, price: price});
                document.getElementById('newSizeName').value = '';
                document.getElementById('newSizeDim').value = '';
                document.getElementById('newSizePrice').value = '0';
            } else if (type === 'accessory') {
                name = document.getElementById('newAccName').value.trim();
                desc = document.getElementById('newAccDesc').value.trim();
                price = parseFloat(document.getElementById('newAccPrice').value) || 0;
                if (!name) return alert('Enter an accessory name.');
                addCustomList.push({type: type, name: name, desc: desc, price: price});
                document.getElementById('newAccName').value = '';
                document.getElementById('newAccDesc').value = '';
                document.getElementById('newAccPrice').value = '0';
            }
            document.getElementById('addCustomInput').value = JSON.stringify(addCustomList);
            alert('"' + name + '" will be added when you save. Save changes to apply.');
        }

        // ── At least one material required check ────
        document.querySelector('form[data-validate]')?.addEventListener('submit', function(e) {
            const checkboxes = document.querySelectorAll('.material-checkbox:checked');
            if (checkboxes.length === 0) {
                e.preventDefault();
                alert('Please select at least one material for this furniture product.');
            }
        });
    </script>
</body>
</html>