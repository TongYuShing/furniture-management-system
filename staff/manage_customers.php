<?php
/**
 * manage_customers.php - Staff Customer Management Page
 * View, search, and manage customer accounts
 * Part I - Function #5: Customer Management
 */

require_once '../inc/config.php';
require_once '../inc/session.php';
require_once '../inc/functions.php';

// Check if user is logged in as staff
checkStaffRole();

$message = '';
$messageType = '';

// Get filter parameters
$search = $_GET['search'] ?? '';
$sortColumn = $_GET['sort'] ?? 'CustomerName';
$sortOrder = $_GET['order'] ?? 'ASC';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;

// Handle customer deletion
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    $customerId = (int)$_GET['delete_id'];
    $customerData = getCustomerById($conn, $customerId);
    $customerName = $customerData ? $customerData['CustomerName'] : 'Unknown';

    $result = deleteCustomer($conn, $customerId);
    if ($result['success']) {
        $message = $result['message'];
        $messageType = 'success';
    } else {
        $message = $result['message'];
        $messageType = 'danger';
    }
}

// Get total count for stats (without LIMIT)
$allForStats = getAllCustomers($conn, $search, $sortColumn, $sortOrder);
$totalCustomers = count($allForStats);
$totalWithOrders = count(array_filter($allForStats, function($c) { return $c['OrderCount'] > 0; }));
$newThisMonth = count(array_filter($allForStats, function($c) {
    return strtotime($c['RegistrationDate']) > strtotime('-30 days');
}));

// Get paginated customers
$pagination = paginateParams($totalCustomers, $page, $perPage);
$customers = getAllCustomers($conn, $search, $sortColumn, $sortOrder, $perPage, $pagination['offset']);
$currentPage = $pagination['current_page'];
$totalPages = $pagination['total_pages'];

// Build pagination base URL (preserve search/sort params)
$paginationBaseUrl = 'manage_customers.php?page={page}';
if ($search) $paginationBaseUrl .= '&search=' . urlencode($search);
if ($sortColumn !== 'CustomerName') $paginationBaseUrl .= '&sort=' . urlencode($sortColumn);
if ($sortOrder !== 'ASC') $paginationBaseUrl .= '&order=' . urlencode($sortOrder);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Customers - Staff Panel</title>
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
                    <li><a href="manage_customers.php" class="active">👥 Manage Customers</a></li>
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
                <h2>Manage Customers</h2>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <!-- Quick Actions -->
                <div style="margin-bottom: var(--space-6); display: flex; gap: var(--space-3); flex-wrap: wrap;">
                    <a href="register_customer.php" class="btn btn-accent">✨ Register New Customer</a>
                </div>

                <!-- Statistics Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>Total Customers</h3>
                        <div class="stat-number"><?php echo $totalCustomers; ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>With Orders</h3>
                        <div class="stat-number"><?php echo $totalWithOrders; ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>New (30 Days)</h3>
                        <div class="stat-number"><?php echo $newThisMonth; ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Without Orders</h3>
                        <div class="stat-number"><?php echo $totalCustomers - $totalWithOrders; ?></div>
                    </div>
                </div>

                <!-- Search & Sort Controls -->
                <div class="sort-controls">
                    <form method="GET" action="" style="display: flex; gap: var(--space-4); flex-wrap: wrap; width: 100%; align-items: flex-end;">
                        <div>
                            <label>Sort by:</label>
                            <select name="sort" onchange="this.form.submit()">
                                <option value="CustomerID" <?php echo $sortColumn == 'CustomerID' ? 'selected' : ''; ?>>Customer ID</option>
                                <option value="CustomerName" <?php echo $sortColumn == 'CustomerName' ? 'selected' : ''; ?>>Name</option>
                                <option value="CompanyName" <?php echo $sortColumn == 'CompanyName' ? 'selected' : ''; ?>>Company</option>
                                <option value="Email" <?php echo $sortColumn == 'Email' ? 'selected' : ''; ?>>Email</option>
                                <option value="RegistrationDate" <?php echo $sortColumn == 'RegistrationDate' ? 'selected' : ''; ?>>Registration Date</option>
                            </select>
                        </div>
                        <div>
                            <label>Order:</label>
                            <select name="order" onchange="this.form.submit()">
                                <option value="ASC" <?php echo $sortOrder == 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                                <option value="DESC" <?php echo $sortOrder == 'DESC' ? 'selected' : ''; ?>>Descending</option>
                            </select>
                        </div>
                        <div style="flex: 1;">
                            <label>Search:</label>
                            <input type="text" name="search" class="form-control"
                                   value="<?php echo htmlspecialchars($search); ?>"
                                   placeholder="Search by name, email, phone, or ID...">
                        </div>
                        <div>
                            <button type="submit" class="btn btn-primary">🔍 Search</button>
                            <a href="manage_customers.php" class="btn btn-outline">Reset</a>
                        </div>
                    </form>
                </div>

                <!-- Customers Table -->
                <?php if (empty($customers)): ?>
                    <div class="alert alert-info">
                        <?php echo $search ? 'No customers match your search.' : 'No customers found.'; ?>
                    </div>
                <?php else: ?>
                    <p style="margin-bottom: var(--space-4); font-size: var(--font-size-sm); color: var(--gray-500);">
                        Showing <strong><?php echo $totalCustomers; ?></strong> customer(s)
                        <?php if ($search): ?> matching "<strong><?php echo htmlspecialchars($search); ?></strong>"<?php endif; ?>
                    </p>
                    <div class="table-container">
                        <table data-sortable>
                            <thead>
                                <tr>
                                    <th class="sortable">ID</th>
                                    <th class="sortable">Name</th>
                                    <th>Company</th>
                                    <th>Email</th>
                                    <th>Contact</th>
                                    <th>Address</th>
                                    <th class="sortable">Registered</th>
                                    <th>Orders</th>
                                    <th>Total Spent</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($customers as $customer):
                                    $canDelete = ($customer['OrderCount'] == 0);
                                ?>
                                    <tr>
                                        <td><?php echo $customer['CustomerID']; ?></td>
                                        <td><strong><?php echo htmlspecialchars($customer['CustomerName']); ?></strong></td>
                                        <td><?php echo !empty($customer['CompanyName']) ? htmlspecialchars($customer['CompanyName']) : '<span style="color: var(--gray-400);">—</span>'; ?></td>
                                        <td><?php echo htmlspecialchars($customer['Email']); ?></td>
                                        <td><?php echo htmlspecialchars($customer['ContactNumber']); ?></td>
                                        <td style="max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($customer['Address']); ?>">
                                            <?php echo htmlspecialchars(substr($customer['Address'], 0, 30)) . (strlen($customer['Address']) > 30 ? '...' : ''); ?>
                                        </td>
                                        <td><?php echo date('d/m/Y', strtotime($customer['RegistrationDate'])); ?></td>
                                        <td>
                                            <?php if ($customer['OrderCount'] > 0): ?>
                                                <span class="badge badge-info"><?php echo $customer['OrderCount']; ?></span>
                                            <?php else: ?>
                                                <span style="color: var(--gray-400);">0</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($customer['TotalSpent'] > 0): ?>
                                                <strong style="color: var(--success);"><?php echo formatCurrency($customer['TotalSpent']); ?></strong>
                                            <?php else: ?>
                                                <span style="color: var(--gray-400);">$0.00</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="white-space: nowrap;">
                                            <button class="btn btn-sm btn-info" onclick="viewCustomer(<?php echo $customer['CustomerID']; ?>)">
                                                👁 View
                                            </button>
                                            <?php if ($canDelete): ?>
                                                <button class="btn btn-sm btn-danger"
                                                        onclick="confirmDeleteCustomer(<?php echo $customer['CustomerID']; ?>, '<?php echo htmlspecialchars(addslashes($customer['CustomerName'])); ?>')">
                                                    🗑️
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-secondary" disabled title="Cannot delete: has orders">🔒</button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: var(--space-6); flex-wrap: wrap; gap: var(--space-4);">
                        <span style="font-size: var(--font-size-sm); color: var(--gray-500);">
                            Showing <?php echo (($currentPage - 1) * $perPage) + 1; ?>–<?php echo min($currentPage * $perPage, $totalCustomers); ?> of <?php echo $totalCustomers; ?> customers
                        </span>
                        <?php echo buildPagination($currentPage, $totalPages, $paginationBaseUrl); ?>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Customer Details Modal -->
    <div id="customerModal" class="modal">
        <div class="modal-content" style="max-width: 750px;">
            <div class="modal-header">
                <h3>Customer Details</h3>
                <button class="modal-close" onclick="closeCustomerModal()">&times;</button>
            </div>
            <div class="modal-body" id="customerModalBody">
                <p style="text-align:center; color: var(--gray-500);">Loading...</p>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Premium Living Furniture Co. Ltd. All rights reserved.</p>
        </div>
    </footer>

    <script src="../assets/js/validation.js"></script>
    <script>
        // ── View Customer Details ─────────────────
        function viewCustomer(customerId) {
            const modal = document.getElementById('customerModal');
            const body = document.getElementById('customerModalBody');
            modal.classList.add('show');
            body.innerHTML = '<p style="text-align:center; color: var(--gray-500);"><span class="spinner"></span> Loading customer details...</p>';

            // Fetch customer details + orders via AJAX
            fetch(`get_customer_details.php?id=${customerId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const c = data.customer;
                        const orders = data.orders || [];
                        let html = `
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 20px;">
                                <div><strong>Customer ID:</strong> ${c.CustomerID}</div>
                                <div><strong>Name:</strong> ${escapeHtml(c.CustomerName)}</div>
                                <div><strong>Company:</strong> ${c.CompanyName ? escapeHtml(c.CompanyName) : '<em>Not set</em>'}</div>
                                <div><strong>Email:</strong> ${escapeHtml(c.Email)}</div>
                                <div><strong>Contact:</strong> ${escapeHtml(c.ContactNumber)}</div>
                                <div><strong>Registered:</strong> ${formatDate(c.RegistrationDate)}</div>
                            </div>
                            <div style="margin-bottom: 16px;">
                                <strong>Address:</strong><br>${escapeHtml(c.Address)}
                            </div>

                            <h4>📋 Order History (${orders.length} orders)</h4>
                        `;

                        if (orders.length === 0) {
                            html += '<p style="color: var(--gray-500);">No orders yet.</p>';
                        } else {
                            html += `
                                <div class="table-container">
                                    <table style="width:100%;">
                                        <thead>
                                            <tr>
                                                <th>Order ID</th>
                                                <th>Date</th>
                                                <th>Product</th>
                                                <th>Qty</th>
                                                <th>Total</th>
                                                <th>Status</th>
                                                <th>Payment</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${orders.map(o => `
                                                <tr>
                                                    <td>${o.OrderID}</td>
                                                    <td>${formatDate(o.OrderDate)}</td>
                                                    <td>${escapeHtml(o.FurnitureName)}</td>
                                                    <td>${o.OrderQuantity}</td>
                                                    <td>$${parseFloat(o.TotalOrderAmount).toFixed(2)}</td>
                                                    <td>${getStatusBadge(o.OrderStatus)}</td>
                                                    <td style="font-size:0.8rem;">
                                                        ${getPaymentLabel(o.PaymentMethod)}
                                                        ${getPaymentStatusBadgeHTML(o.PaymentStatus)}
                                                    </td>
                                                </tr>
                                            `).join('')}
                                        </tbody>
                                    </table>
                                </div>
                            `;
                        }
                        body.innerHTML = html;
                    } else {
                        body.innerHTML = '<p style="color: var(--danger);">Failed to load customer details.</p>';
                    }
                })
                .catch(() => {
                    body.innerHTML = '<p style="color: var(--danger);">Error loading customer details.</p>';
                });
        }

        function closeCustomerModal() {
            document.getElementById('customerModal').classList.remove('show');
        }

        // ── Delete Customer Confirmation ──────────
        function confirmDeleteCustomer(customerId, customerName) {
            closeConfirmDeleteModal();
            const modalHtml = `
                <div id="deleteConfirmModal" class="modal show" style="display: flex;">
                    <div class="modal-content" style="max-width: 440px;">
                        <div class="modal-header">
                            <h3>Confirm Delete Customer</h3>
                            <button class="modal-close" onclick="closeConfirmDeleteModal()">&times;</button>
                        </div>
                        <div class="modal-body">
                            <p>Are you sure you want to delete <strong>${escapeHtml(customerName)}</strong>?</p>
                            <div class="alert alert-warning" style="margin-top: var(--space-4);">
                                <strong>⚠️ Warning:</strong>
                                <ul style="margin-top: var(--space-2); margin-left: var(--space-4);">
                                    <li>This will permanently remove this customer account.</li>
                                    <li>This action cannot be undone.</li>
                                    <li>Only customers without orders can be deleted.</li>
                                </ul>
                            </div>
                        </div>
                        <div style="display: flex; gap: var(--space-3); justify-content: flex-end; padding: 0 var(--space-6) var(--space-6);">
                            <button class="btn btn-outline" onclick="closeConfirmDeleteModal()">Cancel</button>
                            <a href="manage_customers.php?delete_id=${customerId}" class="btn btn-danger">Yes, Delete</a>
                        </div>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', modalHtml);
        }

        function closeConfirmDeleteModal() {
            const modal = document.getElementById('deleteConfirmModal');
            if (modal) modal.remove();
        }

        // ── Helper functions ─────────────────────
        function getStatusBadge(status) {
            const map = {
                'pending': '<span class="badge badge-warning">Pending</span>',
                'accepted': '<span class="badge badge-success">Accepted</span>',
                'rejected': '<span class="badge badge-danger">Rejected</span>',
                'delivered': '<span class="badge badge-info">Delivered</span>'
            };
            return map[status] || status;
        }

        function getPaymentLabel(method) {
            const map = {
                'credit_card': '💳 CC',
                'bank_transfer': '🏦 Bank',
                'cod': '💵 COD'
            };
            return map[method] || (method || '—');
        }

        function getPaymentStatusBadgeHTML(status) {
            const map = {
                'unpaid': ' <span class="badge badge-warning">Unpaid</span>',
                'paid': ' <span class="badge badge-success">Paid</span>',
                'refunded': ' <span class="badge badge-info">Refunded</span>'
            };
            return map[status] || '';
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function formatDate(dateString) {
            if (!dateString) return '';
            const parts = dateString.split('-');
            return `${parts[2]}/${parts[1]}/${parts[0]}`;
        }

        // ── Close modals on outside click ──────────
        window.addEventListener('click', (e) => {
            if (e.target === document.getElementById('customerModal')) closeCustomerModal();
            if (e.target === document.getElementById('deleteConfirmModal')) closeConfirmDeleteModal();
        });

        // ── Close modals on Escape ─────────────────
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeCustomerModal();
                closeConfirmDeleteModal();
            }
        });
    </script>
</body>
</html>