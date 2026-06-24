<?php
/**
 * manage_orders.php - Staff Manage Orders Page
 * Allows staff to view orders, update status (accepted/rejected), and modify order quantity
 */

require_once '../inc/config.php';
require_once '../inc/session.php';
require_once '../inc/functions.php';

// Check if user is logged in as staff
checkStaffRole();

$message = '';
$messageType = '';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $orderId = (int)$_POST['order_id'];
    
    if ($_POST['action'] === 'update_status') {
        $newStatus = $_POST['status'];
        
        if (updateOrderStatus($conn, $orderId, $newStatus)) {
            $message = "Order #$orderId status updated to " . ucfirst($newStatus);
            $messageType = 'success';
        } else {
            $message = "Failed to update order status.";
            $messageType = 'danger';
        }
    } elseif ($_POST['action'] === 'update_quantity') {
        $newQuantity = (int)$_POST['quantity'];
        
        if ($newQuantity <= 0) {
            $message = "Quantity must be greater than 0.";
            $messageType = 'danger';
        } elseif (updateOrderQuantity($conn, $orderId, $newQuantity)) {
            $message = "Order #$orderId quantity updated successfully.";
            $messageType = 'success';
        } else {
            $message = "Failed to update quantity. Insufficient stock?";
            $messageType = 'danger';
        }
    } elseif ($_POST['action'] === 'update_payment_status') {
        $paymentStatus = $_POST['payment_status'] ?? '';
        if (in_array($paymentStatus, ['paid', 'refunded'])) {
            $sql = "UPDATE Orders SET PaymentStatus = ? WHERE OrderID = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "si", $paymentStatus, $orderId);
            if (mysqli_stmt_execute($stmt)) {
                $message = "Order #$orderId payment status updated to " . ucfirst($paymentStatus);
                $messageType = 'success';
            } else {
                $message = "Failed to update payment status.";
                $messageType = 'danger';
            }
        } else {
            $message = "Invalid payment status.";
            $messageType = 'danger';
        }
    } elseif ($_POST['action'] === 'bulk_update_status' && !empty($_POST['order_ids'])) {
        $newStatus = $_POST['status'];
        $ids = array_map('intval', explode(',', $_POST['order_ids']));
        $successCount = 0;
        foreach ($ids as $oid) {
            if (updateOrderStatus($conn, $oid, $newStatus)) {
                $successCount++;
            }
        }
        $message = "Updated $successCount order(s) to " . ucfirst($newStatus) . ".";
        $messageType = 'success';
    }
}

// Get filter parameters
$statusFilter = $_GET['status'] ?? 'all';
$searchTerm = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;

// Get all orders (for stats and search filtering)
$allOrders = getAllOrders($conn, $statusFilter);

// Fetch product view images for all orders
$orderImages = [];
if (!empty($allOrders)) {
    $oids = array_unique(array_column($allOrders, 'FurnitureID'));
    $oidsStr = implode(',', array_map('intval', $oids));
    $imgSql = "SELECT FurnitureID, ImagePath, IsPrimary FROM FurnitureImage WHERE FurnitureID IN ($oidsStr) ORDER BY SortOrder ASC";
    $imgResult = mysqli_query($conn, $imgSql);
    while ($img = mysqli_fetch_assoc($imgResult)) {
        if (!isset($orderImages[$img['FurnitureID']])) {
            $orderImages[$img['FurnitureID']] = [];
        }
        $orderImages[$img['FurnitureID']][] = $img;
    }
}

// Apply search filter
if (!empty($searchTerm)) {
    $allOrders = array_filter($allOrders, function($order) use ($searchTerm) {
        return stripos($order['CustomerName'], $searchTerm) !== false ||
               stripos($order['FurnitureName'], $searchTerm) !== false ||
               $order['OrderID'] == $searchTerm;
    });
}

// Re-index array after filtering
$allOrders = array_values($allOrders);

// Get statistics (from full filtered set)
$pendingCount = count(array_filter($allOrders, function($o) { return $o['OrderStatus'] === 'pending'; }));
$acceptedCount = count(array_filter($allOrders, function($o) { return $o['OrderStatus'] === 'accepted'; }));
$rejectedCount = count(array_filter($allOrders, function($o) { return $o['OrderStatus'] === 'rejected'; }));
$deliveredCount = count(array_filter($allOrders, function($o) { return $o['OrderStatus'] === 'delivered'; }));

// Apply pagination
$totalOrders = count($allOrders);
$pagination = paginateParams($totalOrders, $page, $perPage);
$orders = array_slice($allOrders, $pagination['offset'], $perPage);
$currentPage = $pagination['current_page'];
$totalPages = $pagination['total_pages'];

// Build pagination base URL
$paginationBaseUrl = 'manage_orders.php?page={page}';
if ($statusFilter !== 'all') $paginationBaseUrl .= '&status=' . urlencode($statusFilter);
if ($searchTerm) $paginationBaseUrl .= '&search=' . urlencode($searchTerm);

// Get selected order details for modal
$selectedOrder = null;
if (isset($_GET['order_id']) && is_numeric($_GET['order_id'])) {
    $orderId = (int)$_GET['order_id'];
    foreach ($allOrders as $order) {
        if ($order['OrderID'] == $orderId) {
            $selectedOrder = $order;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders - Staff Panel</title>
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
                    <li><a href="manage_orders.php" class="active">📋 Manage Orders</a></li>
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
                <h2>Manage Orders</h2>
                
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Statistics -->
                <div class="stats-grid">
                    <div class="stat-card" style="background-color: #ffc107;">
                        <h3>Pending</h3>
                        <div class="stat-number"><?php echo $pendingCount; ?></div>
                    </div>
                    <div class="stat-card" style="background-color: #28a745;">
                        <h3>Accepted</h3>
                        <div class="stat-number"><?php echo $acceptedCount; ?></div>
                    </div>
                    <div class="stat-card" style="background-color: #dc3545;">
                        <h3>Rejected</h3>
                        <div class="stat-number"><?php echo $rejectedCount; ?></div>
                    </div>
                    <div class="stat-card" style="background-color: #17a2b8;">
                        <h3>Delivered</h3>
                        <div class="stat-number"><?php echo $deliveredCount; ?></div>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="sort-controls">
                    <div style="display: flex; gap: var(--spacing-md); flex-wrap: wrap; width: 100%;">
                        <div>
                            <label>Filter by Status:</label>
                            <select id="statusFilter" onchange="updateFilter()">
                                <option value="all" <?php echo $statusFilter == 'all' ? 'selected' : ''; ?>>All Orders</option>
                                <option value="pending" <?php echo $statusFilter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="accepted" <?php echo $statusFilter == 'accepted' ? 'selected' : ''; ?>>Accepted</option>
                                <option value="rejected" <?php echo $statusFilter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                <option value="delivered" <?php echo $statusFilter == 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                            </select>
                        </div>
                        
                        <div>
                            <label>Search:</label>
                            <input type="text" id="searchInput" class="form-control" 
                                   value="<?php echo htmlspecialchars($searchTerm); ?>"
                                   placeholder="Order ID, Customer, Product..."
                                   style="width: 250px;">
                        </div>
                    </div>
                </div>
                
                <!-- Bulk Actions Bar -->
                <?php if (!empty($orders)): ?>
                    <div style="display: flex; gap: var(--space-3); align-items: center; margin-bottom: var(--space-4); flex-wrap: wrap;" id="bulkActions" class="no-print">
                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)" style="width: 18px; height: 18px; cursor: pointer;" title="Select All">
                        <label for="selectAll" style="font-size: var(--font-size-sm); font-weight: 600; cursor: pointer;">Select All</label>
                        <span id="selectedCount" style="font-size: var(--font-size-sm); color: var(--gray-500); display: none;">0 selected</span>
                        <form method="POST" id="bulkForm" style="display: flex; gap: var(--space-2);" onsubmit="return prepareBulkSubmit()">
                            <input type="hidden" name="action" value="bulk_update_status">
                            <input type="hidden" name="order_ids" id="bulkOrderIds" value="">
                            <input type="hidden" name="status" id="bulkStatus" value="">
                            <button type="button" class="btn btn-sm btn-success" onclick="submitBulk('accepted')" style="display: none;" id="btnBulkAccept">✓ Accept Selected</button>
                            <button type="button" class="btn btn-sm btn-danger" onclick="submitBulk('rejected')" style="display: none;" id="btnBulkReject">✗ Reject Selected</button>
                            <button type="button" class="btn btn-sm btn-info" onclick="submitBulk('delivered')" style="display: none;" id="btnBulkDeliver">🚚 Mark Delivered</button>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- Orders Table -->
                <?php if (empty($orders)): ?>
                    <div class="alert alert-info">No orders found.</div>
                <?php else: ?>
                    <div class="table-container">
                        <table data-sortable>
                            <thead>
                                <tr>
                                    <th style="width: 30px;"><input type="checkbox" id="selectAllHeader" onchange="toggleSelectAll(this)" style="width: 16px; height: 16px;"></th>
                                    <th class="sortable">Order ID</th>
                                    <th class="sortable">Order Date</th>
                                    <th>Product</th>
                                    <th>Image</th>
                                    <th class="sortable">Quantity</th>
                                    <th class="sortable">Total Amount</th>
                                    <th>Customer</th>
                                    <th>Contact</th>
                                    <th>Delivery Address</th>
                                    <th>Delivery Date</th>
                                    <th class="sortable">Status</th>
                                    <th>Payment</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order):
                                    $ofid = $order['FurnitureID'];
                                    $oviews = $orderImages[$ofid] ?? [];
                                    $opri = null;
                                    foreach ($oviews as $ov) { if ($ov['IsPrimary']) { $opri = $ov; break; } }
                                    if (!$opri && !empty($oviews)) $opri = $oviews[0];
                                    $oimgSrc = $opri
                                        ? '../assets/images/furniture/' . str_replace(' ', '%20', $opri['ImagePath'])
                                        : '../assets/images/furniture/' . htmlspecialchars($order['FurnitureImage']);
                                ?>
                                    <tr>
                                        <td><input type="checkbox" class="order-checkbox" value="<?php echo $order['OrderID']; ?>" onchange="updateBulkBar()" style="width: 16px; height: 16px;"></td>
                                        <td><?php echo $order['OrderID']; ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($order['OrderDate'])); ?></td>
                                        <td><?php echo htmlspecialchars($order['FurnitureName']); ?></td>
                                        <td>
                                            <img src="<?php echo $oimgSrc; ?>"
                                                 style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px;"
                                                 onerror="this.src='../assets/images/furniture/placeholder.jpg'">
                                        </td>
                                        <td><?php echo $order['OrderQuantity']; ?></td>
                                        <td><?php echo formatCurrency($order['TotalOrderAmount']); ?></td>
                                        <td><?php echo htmlspecialchars($order['CustomerName']); ?></td>
                                        <td><?php echo htmlspecialchars($order['ContactNumber']); ?></td>
                                        <td><?php echo htmlspecialchars(substr($order['DeliveryAddress'], 0, 30)) . '...'; ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($order['DeliveryDate'])); ?></td>
                                        <td><?php echo getOrderStatusBadge($order['OrderStatus']); ?></td>
                                        <td style="white-space:nowrap;font-size:0.85rem;">
                                            <?php echo isset($order['PaymentMethod']) ? getPaymentMethodLabel($order['PaymentMethod']) : '<span style="color:var(--gray-400);">—</span>'; ?>
                                            <?php echo isset($order['PaymentStatus']) ? getPaymentStatusBadge($order['PaymentStatus']) : ''; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-info" onclick="openOrderModal(<?php echo $order['OrderID']; ?>)">
                                                Manage
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: var(--space-6); flex-wrap: wrap; gap: var(--space-4);">
                            <span style="font-size: var(--font-size-sm); color: var(--gray-500);">
                                Showing <?php echo $pagination['offset'] + 1; ?>–<?php echo min($currentPage * $perPage, $totalOrders); ?> of <?php echo $totalOrders; ?> orders
                            </span>
                            <?php echo buildPagination($currentPage, $totalPages, $paginationBaseUrl); ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Manage Order Modal -->
    <div id="orderModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h3>Manage Order</h3>
                <button class="modal-close" onclick="closeOrderModal()">&times;</button>
            </div>
            <div class="modal-body" id="orderModalContent">
                <!-- Loaded dynamically -->
            </div>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 Premium Living Furniture Co. Ltd. All rights reserved.</p>
        </div>
    </footer>

    <script src="../assets/js/validation.js"></script>
    <script>
        let currentOrderId = null;
        
        function updateFilter() {
            const status = document.getElementById('statusFilter').value;
            const search = document.getElementById('searchInput').value;
            window.location.href = `manage_orders.php?status=${status}&search=${encodeURIComponent(search)}`;
        }
        
        document.getElementById('searchInput')?.addEventListener('keyup', function(e) {
            if (e.key === 'Enter') {
                updateFilter();
            }
        });
        
        function openOrderModal(orderId) {
            currentOrderId = orderId;
            
            // Fetch order details via AJAX
            fetch(`get_order_details_staff.php?id=${orderId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('orderModalContent').innerHTML = `
                            <form method="POST" action="">
                                <input type="hidden" name="order_id" value="${data.order.OrderID}">
                                
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                                    <div><strong>Order ID:</strong> ${data.order.OrderID}</div>
                                    <div><strong>Order Date:</strong> ${formatDate(data.order.OrderDate)}</div>
                                    <div><strong>Product:</strong> ${escapeHtml(data.order.FurnitureName)}</div>
                                    <div><strong>Unit Price:</strong> ${formatCurrency(data.order.Price)}</div>
                                    <div><strong>Customer:</strong> ${escapeHtml(data.customer.CustomerName)}</div>
                                    <div><strong>Contact:</strong> ${escapeHtml(data.customer.ContactNumber)}</div>
                                    <div><strong>Delivery Address:</strong> ${escapeHtml(data.order.DeliveryAddress)}</div>
                                    <div><strong>Delivery Date:</strong> ${formatDate(data.order.DeliveryDate)}</div>
                                </div>

                                ${(data.order.SelectedColor || data.order.SelectedSize || data.order.SelectedAccessories) ? `
                                <h4>🎨 Customization</h4>
                                <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:15px;">
                                    ${data.order.SelectedColor ? '<span style="background:var(--gray-100);padding:6px 14px;border-radius:20px;">🎨 ' + escapeHtml(data.order.SelectedColor) + '</span>' : ''}
                                    ${data.order.SelectedSize ? '<span style="background:var(--gray-100);padding:6px 14px;border-radius:20px;">📏 ' + escapeHtml(data.order.SelectedSize) + '</span>' : ''}
                                    ${data.order.SelectedAccessories ? (() => { try { const a = JSON.parse(data.order.SelectedAccessories); return a.map(acc => '<span style="background:#fff3cd;padding:6px 14px;border-radius:20px;">🔧 ' + escapeHtml(acc.name) + ' (+$' + parseFloat(acc.price).toFixed(2) + ')</span>').join(''); } catch(e) { return ''; } })() : ''}
                                    ${parseFloat(data.order.CustomizationCost || 0) > 0 ? '<span style="color:var(--accent);font-weight:600;margin-left:8px;">Extra: ' + formatCurrency(data.order.CustomizationCost) + '</span>' : ''}
                                </div>
                                ` : ''}

                                <h4>Payment Information</h4>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 20px;">
                                    <div><strong>Method:</strong> ${getPaymentLabel(data.order.PaymentMethod)}</div>
                                    <div><strong>Status:</strong> ${getPaymentStatusBadgeHTML(data.order.PaymentStatus)}</div>
                                    ${(data.order.PaymentMethod === 'bank_transfer' && data.order.PaymentStatus === 'unpaid')
                                        ? `<div style="grid-column: 1 / -1; margin-top: 8px;">
                                            <button type="submit" name="action" value="update_payment_status" class="btn btn-success btn-sm"
                                                    onclick="this.form.payment_status.value='paid'">
                                                ✅ Mark as Paid
                                            </button>
                                            <input type="hidden" name="payment_status" value="">
                                        </div>`
                                        : ''}
                                </div>

                                <h4>Update Quantity</h4>
                                <div class="form-group">
                                    <label for="quantity_${data.order.OrderID}">Order Quantity (Current: ${data.order.OrderQuantity})</label>
                                    <div style="display: flex; gap: 10px;">
                                        <input type="number" name="quantity" id="quantity_${data.order.OrderID}" 
                                               class="form-control" style="width: 150px;"
                                               value="${data.order.OrderQuantity}" min="1">
                                        <button type="submit" name="action" value="update_quantity" class="btn btn-primary">Update Quantity</button>
                                    </div>
                                </div>
                                
                                <h4>Update Status</h4>
                                <div class="form-group">
                                    <label>Current Status: ${getStatusBadge(data.order.OrderStatus)}</label>
                                    <div style="display: flex; gap: 10px; margin-top: 10px;">
                                        <button type="submit" name="action" value="update_status" 
                                                class="btn btn-success" ${data.order.OrderStatus === 'accepted' ? 'disabled' : ''}
                                                onclick="this.form.status.value='accepted'">
                                            ✓ Accept Order
                                        </button>
                                        <button type="submit" name="action" value="update_status" 
                                                class="btn btn-danger" ${data.order.OrderStatus === 'rejected' ? 'disabled' : ''}
                                                onclick="this.form.status.value='rejected'">
                                            ✗ Reject Order
                                        </button>
                                        <input type="hidden" name="status" value="">
                                    </div>
                                </div>
                                
                                <h4>Materials Required for This Order</h4>
                                <div class="table-container">
                                    <table style="width: 100%;">
                                        <thead style="background-color: var(--gray-lighter);">
                                            <tr>
                                                <th>Material Name</th>
                                                <th>Required per Unit</th>
                                                <th>Total Required</th>
                                                <th>Available Quantity</th>
                                                <th>Unit</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${data.materials.map(m => `
                                                <tr>
                                                    <td>${escapeHtml(m.MaterialName)}</td>
                                                    <td>${m.MaterialQuantity}</td>
                                                    <td>${m.MaterialQuantity * data.order.OrderQuantity}</td>
                                                    <td>${m.PhysicalQuantity}</td>
                                                    <td>${escapeHtml(m.Unit)}</td>
                                                    <td>
                                                        ${(m.PhysicalQuantity >= m.MaterialQuantity * data.order.OrderQuantity) 
                                                            ? '<span class="badge badge-success">Sufficient</span>' 
                                                            : '<span class="badge badge-danger">Insufficient</span>'}
                                                    </td>
                                                </tr>
                                            `).join('')}
                                        </tbody>
                                    </table>
                                </div>
                            </form>
                        `;
                        document.getElementById('orderModal').classList.add('show');
                    } else {
                        showToast('Failed to load order details', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Failed to load order details', 'error');
                });
        }
        
        function closeOrderModal() {
            document.getElementById('orderModal').classList.remove('show');
        }

        // ── Bulk Operations ───────────────────────
        function toggleSelectAll(el) {
            document.querySelectorAll('.order-checkbox').forEach(function(cb) {
                cb.checked = el.checked;
            });
            document.getElementById('selectAll').checked = el.checked;
            updateBulkBar();
        }

        function updateBulkBar() {
            var checked = document.querySelectorAll('.order-checkbox:checked');
            var count = checked.length;
            var countEl = document.getElementById('selectedCount');
            var btnAccept = document.getElementById('btnBulkAccept');
            var btnReject = document.getElementById('btnBulkReject');
            var btnDeliver = document.getElementById('btnBulkDeliver');

            if (count > 0) {
                countEl.style.display = 'inline';
                countEl.textContent = count + ' selected';
                btnAccept.style.display = 'inline-flex';
                btnReject.style.display = 'inline-flex';
                btnDeliver.style.display = 'inline-flex';
            } else {
                countEl.style.display = 'none';
                btnAccept.style.display = 'none';
                btnReject.style.display = 'none';
                btnDeliver.style.display = 'none';
            }

            // Sync header checkbox
            var all = document.querySelectorAll('.order-checkbox');
            document.getElementById('selectAll').checked = all.length > 0 && checked.length === all.length;
            document.getElementById('selectAllHeader').checked = document.getElementById('selectAll').checked;
        }

        function submitBulk(status) {
            var ids = [];
            document.querySelectorAll('.order-checkbox:checked').forEach(function(cb) {
                ids.push(cb.value);
            });
            if (ids.length === 0) return;
            if (!confirm('Are you sure you want to ' + status + ' ' + ids.length + ' order(s)?')) return;
            document.getElementById('bulkOrderIds').value = ids.join(',');
            document.getElementById('bulkStatus').value = status;
            document.getElementById('bulkForm').submit();
        }
        
        function formatDate(dateString) {
            if (!dateString) return '';
            const parts = dateString.split('-');
            return `${parts[2]}/${parts[1]}/${parts[0]}`;
        }
        
        function formatCurrency(amount) {
            return '$' + parseFloat(amount).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }
        
        function getStatusBadge(status) {
            const badges = {
                'pending': '<span style="background-color: #ffc107; color: #000; padding: 4px 8px; border-radius: 4px;">Pending</span>',
                'accepted': '<span style="background-color: #28a745; color: #fff; padding: 4px 8px; border-radius: 4px;">Accepted</span>',
                'rejected': '<span style="background-color: #dc3545; color: #fff; padding: 4px 8px; border-radius: 4px;">Rejected</span>',
                'delivered': '<span style="background-color: #17a2b8; color: #fff; padding: 4px 8px; border-radius: 4px;">Delivered</span>'
            };
            return badges[status] || '<span style="background-color: #6c757d; color: #fff; padding: 4px 8px; border-radius: 4px;">Unknown</span>';
        }

        function getPaymentLabel(method) {
            const labels = {
                'credit_card': '💳 Credit Card',
                'bank_transfer': '🏦 Bank Transfer',
                'cod': '💵 Cash on Delivery'
            };
            return labels[method] || (method || '—');
        }

        function getPaymentStatusBadgeHTML(status) {
            const badges = {
                'unpaid': '<span style="background-color: #ffc107; color: #000; padding: 4px 8px; border-radius: 4px;">⏳ Unpaid</span>',
                'paid': '<span style="background-color: #28a745; color: #fff; padding: 4px 8px; border-radius: 4px;">✅ Paid</span>',
                'refunded': '<span style="background-color: #17a2b8; color: #fff; padding: 4px 8px; border-radius: 4px;">↩️ Refunded</span>'
            };
            return badges[status] || (status || '—');
        }
        
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function showToast(message, type) {
            if (typeof window.showToast === 'function') {
                window.showToast(message, type);
            } else {
                alert(message);
            }
        }
        
        window.onclick = function(event) {
            const modal = document.getElementById('orderModal');
            if (event.target === modal) {
                closeOrderModal();
            }
        }
    </script>
</body>
</html>