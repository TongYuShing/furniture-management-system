<?php
/**
 * view_orders.php - Customer Order Records Page
 * Displays all orders with sorting by multiple columns
 */

require_once '../inc/config.php';
require_once '../inc/session.php';
require_once '../inc/functions.php';

// Check if user is logged in as customer
checkCustomerRole();

$customerId = $_SESSION['user_id'];

// Get sorting parameters (as required: ascending/descending by at least TWO columns)
$sortColumn = $_GET['sort'] ?? 'OrderDate';
$sortOrder = $_GET['order'] ?? 'DESC';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;

// Get all orders for this customer with sorting
$allOrders = getOrdersByCustomerId($conn, $customerId, $sortColumn, $sortOrder);

// Fetch product view images for all ordered products
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

// Get single order details if specified
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

// Handle filter by status
$statusFilter = $_GET['status'] ?? 'all';
if ($statusFilter !== 'all') {
    $allOrders = array_filter($allOrders, function($order) use ($statusFilter) {
        return $order['OrderStatus'] === $statusFilter;
    });
}
$allOrders = array_values($allOrders);

// Date range filter (server-side)
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
if (!empty($startDate) || !empty($endDate)) {
    $allOrders = array_filter($allOrders, function($order) use ($startDate, $endDate) {
        $orderDate = $order['OrderDate'];
        if (!empty($startDate) && $orderDate < $startDate) return false;
        if (!empty($endDate) && $orderDate > $endDate) return false;
        return true;
    });
    $allOrders = array_values($allOrders);
}

// Calculate order statistics (from full filtered set)
$totalOrders = count($allOrders);
$totalSpent = array_sum(array_column($allOrders, 'TotalOrderAmount'));
$pendingOrders = count(array_filter($allOrders, function($o) { return $o['OrderStatus'] === 'pending'; }));
$deliveredOrders = count(array_filter($allOrders, function($o) { return $o['OrderStatus'] === 'delivered'; }));

// Apply pagination
$pagination = paginateParams($totalOrders, $page, $perPage);
$orders = array_slice($allOrders, $pagination['offset'], $perPage);
$currentPage = $pagination['current_page'];
$totalPages = $pagination['total_pages'];

// Build pagination base URL
$paginationBaseUrl = 'view_orders.php?page={page}';
if ($sortColumn !== 'OrderDate') $paginationBaseUrl .= '&sort=' . urlencode($sortColumn);
if ($sortOrder !== 'DESC') $paginationBaseUrl .= '&order=' . urlencode($sortOrder);
if ($statusFilter !== 'all') $paginationBaseUrl .= '&status=' . urlencode($statusFilter);
if ($startDate) $paginationBaseUrl .= '&start_date=' . urlencode($startDate);
if ($endDate) $paginationBaseUrl .= '&end_date=' . urlencode($endDate);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - Premium Living Furniture</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header class="header">
        <div class="container">
            <a href="../index.php" style="text-decoration:none;">
                <div class="logo">
                    <h1>Premium Living Furniture</h1>
                    <p>Quality Furniture for Your Home</p>
                </div>
            </a>
            <div class="user-info">
    <span>👋 Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</span>
    <div class="header-buttons">
        <a href="../index.php" class="btn-dashboard">🏠 Back to Home</a>
        <a href="dashboard.php" class="btn-dashboard">📊 Dashboard</a>
        <a href="logout.php" class="btn-logout">🚪 Logout</a>
    </div>
        </div>
    </header>

    <div class="container">
        <div class="dashboard-layout">
            <!-- Sidebar Navigation -->
            <aside class="sidebar">
                <h3>Customer Menu</h3>
                <ul>
                    <li><a href="dashboard.php">🏠 Dashboard</a></li>
                    <li><a href="view_orders.php" class="active">📋 My Orders</a></li>
                    <li><a href="wishlist.php">❤️ Wishlist</a></li>
                    <li><a href="update_profile.php">👤 Update Profile</a></li>
                </ul>
            </aside>

            <!-- Main Content -->
            <main class="main-content">
                <h2>My Order Records</h2>
                
                <!-- Order Statistics -->
                <div class="stats-grid" style="grid-template-columns: 1fr 1fr;">
                    <div class="stat-card">
                        <h3>Total Orders</h3>
                        <div class="stat-number"><?php echo $totalOrders; ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Total Spent</h3>
                        <div class="stat-number"><?php echo formatCurrency($totalSpent); ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Pending</h3>
                        <div class="stat-number"><?php echo $pendingOrders; ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Delivered</h3>
                        <div class="stat-number"><?php echo $deliveredOrders; ?></div>
                    </div>
                </div>

                <!-- Filter and Sort Controls -->
                <div class="sort-controls">
                    <div style="display: flex; gap: var(--spacing-md); flex-wrap: wrap; width: 100%;">
                        <div>
                            <label>Sort by:</label>
                            <select id="sortColumn" onchange="updateSortAndFilter()">
                                <option value="OrderID" <?php echo $sortColumn == 'OrderID' ? 'selected' : ''; ?>>Order ID</option>
                                <option value="OrderDate" <?php echo $sortColumn == 'OrderDate' ? 'selected' : ''; ?>>Order Date</option>
                                <option value="OrderQuantity" <?php echo $sortColumn == 'OrderQuantity' ? 'selected' : ''; ?>>Quantity</option>
                                <option value="TotalOrderAmount" <?php echo $sortColumn == 'TotalOrderAmount' ? 'selected' : ''; ?>>Total Amount</option>
                                <option value="DeliveryDate" <?php echo $sortColumn == 'DeliveryDate' ? 'selected' : ''; ?>>Delivery Date</option>
                                <option value="OrderStatus" <?php echo $sortColumn == 'OrderStatus' ? 'selected' : ''; ?>>Status</option>
                            </select>
                        </div>
                        
                        <div>
                            <label>Order:</label>
                            <select id="sortOrder" onchange="updateSortAndFilter()">
                                <option value="ASC" <?php echo $sortOrder == 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                                <option value="DESC" <?php echo $sortOrder == 'DESC' ? 'selected' : ''; ?>>Descending</option>
                            </select>
                        </div>
                        
                        <div>
                            <label>Filter by Status:</label>
                            <select id="statusFilter" onchange="updateSortAndFilter()">
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
                                   placeholder="Search orders..." style="width: 200px;">
                        </div>
                        <div>
                            <label>From:</label>
                            <input type="date" id="startDate" class="form-control"
                                   value="<?php echo htmlspecialchars($startDate); ?>"
                                   onchange="updateSortAndFilter()" style="width: 150px;">
                        </div>
                        <div>
                            <label>To:</label>
                            <input type="date" id="endDate" class="form-control"
                                   value="<?php echo htmlspecialchars($endDate); ?>"
                                   onchange="updateSortAndFilter()" style="width: 150px;">
                        </div>
                    </div>
                </div>

                <!-- Orders Table -->
                <?php if (empty($orders)): ?>
                    <div class="alert alert-info">
                        You haven't placed any orders yet. 
                        <a href="../index.php#products">Start shopping now!</a>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table class="orders-table" data-sortable>
                            <thead>
                                <tr>
                                    <th class="sortable" data-column="OrderID">Order ID</th>
                                    <th class="sortable" data-column="OrderDate">Order Date</th>
                                    <th>Product</th>
                                    <th class="sortable" data-column="OrderQuantity">Quantity</th>
                                    <th class="sortable" data-column="TotalOrderAmount">Total Amount</th>
                                    <th class="sortable" data-column="DeliveryDate">Delivery Date</th>
                                    <th class="sortable" data-column="OrderStatus">Status</th>
                                    <th>Payment</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                    <tr id="order-row-<?php echo $order['OrderID']; ?>">
                                        <td><?php echo $order['OrderID']; ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($order['OrderDate'])); ?></td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                <?php
                                                $ofid = $order['FurnitureID'];
                                                $oviews = $orderImages[$ofid] ?? [];
                                                $opri = null;
                                                foreach ($oviews as $ov) { if ($ov['IsPrimary']) { $opri = $ov; break; } }
                                                if (!$opri && !empty($oviews)) $opri = $oviews[0];
                                                $oimgSrc = $opri
                                                    ? '../assets/images/furniture/' . str_replace(' ', '%20', $opri['ImagePath'])
                                                    : '../assets/images/furniture/' . htmlspecialchars($order['FurnitureImage']);
                                                ?>
                                                <img src="<?php echo $oimgSrc; ?>"
                                                     alt="<?php echo htmlspecialchars($order['FurnitureName']); ?>"
                                                     style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px;"
                                                     onerror="this.src='../assets/images/furniture/placeholder.jpg'">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($order['FurnitureName']); ?></strong>
                                                    <?php if (!empty($order['SelectedColor']) || !empty($order['SelectedSize']) || !empty($order['SelectedAccessories'])): ?>
                                                        <div style="margin-top:3px;display:flex;flex-wrap:wrap;gap:3px;">
                                                            <?php if (!empty($order['SelectedColor'])): ?>
                                                                <span style="font-size:0.7rem;background:var(--gray-100);padding:1px 6px;border-radius:8px;">🎨 <?php echo htmlspecialchars($order['SelectedColor']); ?></span>
                                                            <?php endif; ?>
                                                            <?php if (!empty($order['SelectedSize'])): ?>
                                                                <span style="font-size:0.7rem;background:var(--gray-100);padding:1px 6px;border-radius:8px;">📏 <?php echo htmlspecialchars($order['SelectedSize']); ?></span>
                                                            <?php endif; ?>
                                                            <?php
                                                            if (!empty($order['SelectedAccessories'])):
                                                                $accs = json_decode($order['SelectedAccessories'], true);
                                                                if (is_array($accs)):
                                                                    foreach ($accs as $a):
                                                            ?>
                                                                <span style="font-size:0.7rem;background:#fff3cd;padding:1px 6px;border-radius:8px;">🔧 <?php echo htmlspecialchars($a['name']); ?></span>
                                                            <?php endforeach; endif; endif; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo $order['OrderQuantity']; ?></td>
                                        <td><?php echo formatCurrency($order['TotalOrderAmount']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($order['DeliveryDate'])); ?></td>
                                        <td><?php echo getOrderStatusBadge($order['OrderStatus']); ?></td>
                                        <td style="white-space:nowrap;font-size:0.85rem;">
                                            <?php echo isset($order['PaymentMethod']) ? getPaymentMethodLabel($order['PaymentMethod']) : '<span style="color:var(--gray-400);">—</span>'; ?>
                                            <?php echo isset($order['PaymentStatus']) ? getPaymentStatusBadge($order['PaymentStatus']) : ''; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-info" onclick="viewOrderDetails(<?php echo $order['OrderID']; ?>)">
                                                👁 View
                                            </button>
                                            <?php if ($order['OrderStatus'] === 'pending'): ?>
                                                <button class="btn btn-sm btn-danger"
                                                        onclick="confirmCancelOrder(<?php echo $order['OrderID']; ?>, '<?php echo htmlspecialchars(addslashes($order['FurnitureName'])); ?>')">
                                                    ❌ Cancel
                                                </button>
                                            <?php endif; ?>
                                            <a href="invoice.php?id=<?php echo $order['OrderID']; ?>" class="btn btn-sm btn-outline" target="_blank" title="View Invoice">🧾</a>
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

                <!-- Export Button -->
                <?php if ($totalOrders > 0): ?>
                    <div style="margin-top: var(--space-6); display: flex; gap: var(--space-4);">
                        <button class="btn btn-sm btn-outline" onclick="exportOrdersCSV()">📥 Export to CSV</button>
                        <button class="btn btn-sm btn-outline" onclick="window.print()">🖨️ Print Orders</button>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Order Details Modal -->
    <div id="orderDetailsModal" class="modal">
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header">
                <h3>Order Details</h3>
                <button class="modal-close" onclick="closeOrderDetailsModal()">&times;</button>
            </div>
            <div class="modal-body" id="orderDetailsContent">
                <!-- Loaded dynamically via AJAX -->
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
        function updateSortAndFilter() {
            const sortColumn = document.getElementById('sortColumn').value;
            const sortOrder = document.getElementById('sortOrder').value;
            const statusFilter = document.getElementById('statusFilter').value;
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            let url = `view_orders.php?sort=${sortColumn}&order=${sortOrder}&status=${statusFilter}`;
            if (startDate) url += `&start_date=${encodeURIComponent(startDate)}`;
            if (endDate) url += `&end_date=${encodeURIComponent(endDate)}`;
            window.location.href = url;
        }
        
        // Live search functionality
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('keyup', function() {
                const searchTerm = this.value.toLowerCase();
                const rows = document.querySelectorAll('.orders-table tbody tr');
                
                rows.forEach(row => {
                    const text = row.innerText.toLowerCase();
                    row.style.display = text.includes(searchTerm) ? '' : 'none';
                });
            });
        }
        
        function viewOrderDetails(orderId) {
            // Fetch order details via AJAX
            fetch(`get_order_details.php?id=${orderId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let modalImgHTML = '';
                        if (data.image_path) {
                            const modalImgSrc = '../assets/images/furniture/' + data.image_path.replace(/ /g, '%20');
                            modalImgHTML = `<div style="text-align:center;margin-bottom:12px;"><img src="${modalImgSrc}" alt="${escapeHtml(data.order.FurnitureName)}" style="width:100%;max-width:300px;max-height:200px;object-fit:cover;border-radius:8px;border:1px solid var(--gray-200);" onerror="this.style.display='none';"></div>`;
                        }
                        document.getElementById('orderDetailsContent').innerHTML = `
                            <div style="display: grid; gap: 15px;">
                                ${modalImgHTML}
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                    <div><strong>Order ID:</strong> ${data.order.OrderID}</div>
                                    <div><strong>Order Date:</strong> ${formatDate(data.order.OrderDate)}</div>
                                    <div><strong>Product:</strong> ${escapeHtml(data.order.FurnitureName)}</div>
                                    <div><strong>Quantity:</strong> ${data.order.OrderQuantity}</div>
                                    <div><strong>Unit Price:</strong> ${formatCurrency(data.order.Price)}</div>
                                    <div><strong>Total Amount:</strong> ${formatCurrency(data.order.TotalOrderAmount)}</div>
                                    <div><strong>Delivery Address:</strong> ${escapeHtml(data.order.DeliveryAddress)}</div>
                                    <div><strong>Delivery Date:</strong> ${formatDate(data.order.DeliveryDate)}</div>
                                    <div><strong>Status:</strong> ${getStatusBadge(data.order.OrderStatus)}</div>
                                    <div><strong>Payment Method:</strong> ${getPaymentLabel(data.order.PaymentMethod)}</div>
                                    <div><strong>Payment Status:</strong> ${getPaymentStatusBadgeHTML(data.order.PaymentStatus)}</div>
                                </div>

                                ${(data.order.SelectedColor || data.order.SelectedSize || data.order.SelectedAccessories) ? `
                                <h4>🎨 Customization</h4>
                                <div style="display:flex;flex-wrap:wrap;gap:8px;">
                                    ${data.order.SelectedColor ? '<span style="background:var(--gray-100);padding:6px 14px;border-radius:20px;font-size:0.9rem;">🎨 ' + escapeHtml(data.order.SelectedColor) + '</span>' : ''}
                                    ${data.order.SelectedSize ? '<span style="background:var(--gray-100);padding:6px 14px;border-radius:20px;font-size:0.9rem;">📏 ' + escapeHtml(data.order.SelectedSize) + '</span>' : ''}
                                    ${data.order.SelectedAccessories ? (() => { try { const a = JSON.parse(data.order.SelectedAccessories); return a.map(acc => '<span style="background:#fff3cd;padding:6px 14px;border-radius:20px;font-size:0.9rem;">🔧 ' + escapeHtml(acc.name) + ' (+$' + parseFloat(acc.price).toFixed(2) + ')</span>').join(''); } catch(e) { return ''; } })() : ''}
                                </div>
                                ` : ''}

                                <h4>📊 Order Progress</h4>
                                <div style="display: flex; align-items: center; gap: 0; margin: 16px 0; padding: 12px 0;">
                                    ${getOrderTimeline(data.order.OrderStatus)}
                                </div>

                                <h4>Customer Information</h4>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                    <div><strong>Name:</strong> ${escapeHtml(data.customer.CustomerName)}</div>
                                    <div><strong>Contact Number:</strong> ${escapeHtml(data.customer.ContactNumber)}</div>
                                    <div><strong>Email:</strong> ${escapeHtml(data.customer.Email)}</div>
                                    <div><strong>Address:</strong> ${escapeHtml(data.customer.Address)}</div>
                                </div>
                                
                                <h4>Materials Used</h4>
                                <table style="width: 100%; border-collapse: collapse;">
                                    <thead style="background-color: var(--gray-lighter);">
                                        <tr>
                                            <th style="padding: 8px; text-align: left;">Material Name</th>
                                            <th style="padding: 8px; text-align: left;">Quantity Used</th>
                                            <th style="padding: 8px; text-align: left;">Unit</th>
                                            <th style="padding: 8px; text-align: left;">Available Stock</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${data.materials.map(m => `
                                            <tr>
                                                <td style="padding: 8px;">${escapeHtml(m.MaterialName)}</td>
                                                <td style="padding: 8px;">${m.MaterialQuantity * data.order.OrderQuantity}</td>
                                                <td style="padding: 8px;">${escapeHtml(m.Unit)}</td>
                                                <td style="padding: 8px;">${m.PhysicalQuantity} ${escapeHtml(m.Unit)}</td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        `;
                        document.getElementById('orderDetailsModal').classList.add('show');
                    } else {
                        showToast('Failed to load order details', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Failed to load order details', 'error');
                });
        }
        
        function confirmCancelOrder(orderId, productName) {
            window.validation.showConfirmModal(
                `Are you sure you want to <strong>cancel</strong> your order for <strong>"${productName}"</strong>?<br><br>
                 <span style="color:var(--danger);">⚠️ This action cannot be undone. Stock will be restored.</span>`,
                () => {
                    window.location.href = `delete_order.php?id=${orderId}`;
                }
            );
        }
        
        function closeOrderDetailsModal() {
            document.getElementById('orderDetailsModal').classList.remove('show');
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

        function getOrderTimeline(status) {
            const steps = ['pending', 'accepted', 'delivered'];
            const labels = ['Pending', 'Accepted', 'Delivered'];
            const icons = ['📋', '✅', '🚚'];
            const currentIdx = steps.indexOf(status);
            let html = '';
            steps.forEach(function(s, i) {
                const isComplete = i <= currentIdx && currentIdx >= 0;
                const isCurrent = i === currentIdx;
                html += '<div style="flex:1;text-align:center;position:relative;">' +
                    '<div style="width:40px;height:40px;border-radius:50%;margin:0 auto 4px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;' +
                    (isComplete ? 'background:var(--success);color:#fff;' : 'background:var(--gray-200);') +
                    (isCurrent ? 'box-shadow:0 0 0 4px rgba(5,150,105,0.3);' : '') +
                    '">' + (isComplete ? '✓' : icons[i]) + '</div>' +
                    '<div style="font-size:0.75rem;font-weight:' + (isCurrent ? '700' : '500') + ';color:' + (isComplete ? 'var(--success)' : 'var(--gray-400)') + ';">' + labels[i] + '</div>' +
                    '</div>';
                if (i < steps.length - 1) {
                    html += '<div style="flex:0.5;height:3px;background:' + (i < currentIdx ? 'var(--success)' : 'var(--gray-200)') + ';margin-top:-20px;"></div>';
                }
            });
            // Add rejected state
            if (status === 'rejected') {
                html = '<div style="text-align:center;width:100%;padding:12px;background:var(--danger-bg);border-radius:8px;color:var(--danger);font-weight:600;">❌ Order Rejected</div>';
            }
            return html;
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

        function exportOrdersCSV() {
            const table = document.querySelector('.orders-table');
            if (!table) return;
            const rows = table.querySelectorAll('tbody tr');
            let csv = 'Order ID,Date,Product,Quantity,Total,Delivery Date,Status,Payment Method,Payment Status\n';
            rows.forEach(row => {
                if (row.style.display === 'none') return;
                const cells = row.querySelectorAll('td');
                const orderId = cells[0].innerText.trim();
                const date = cells[1].innerText.trim();
                const product = cells[2].innerText.replace(/\n/g, ' ').trim();
                const qty = cells[3].innerText.trim();
                const total = cells[4].innerText.replace('$', '').trim();
                const delivery = cells[5].innerText.trim();
                const status = cells[6].innerText.trim();
                // Parse payment info from cell 7 (has badges)
                const paymentCell = cells[7] ? cells[7].innerText.trim().replace(/\n/g, ' ') : '';
                csv += `"${orderId}","${date}","${product}","${qty}","${total}","${delivery}","${status}","${paymentCell}"\n`;
            });
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'my_orders.csv';
            a.click();
            URL.revokeObjectURL(url);
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('orderDetailsModal');
            if (event.target === modal) {
                closeOrderDetailsModal();
            }
        }
    </script>
</body>
</html>