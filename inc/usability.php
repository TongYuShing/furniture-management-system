<?php
/**
 * usability.php - System Usability Helper Functions
 * Part IV: Breadcrumbs, Remember Me, Session management
 */

require_once 'config.php';

/**
 * Build breadcrumb HTML from an array of [label => url] pairs
 * Last item is rendered as plain text (current page)
 */
function breadcrumbs($crumbs) {
    $html = '<nav class="breadcrumbs" aria-label="Breadcrumb">';
    $html .= '<ol>';
    $items = [];
    foreach ($crumbs as $label => $url) {
        $items[] = ['label' => $label, 'url' => $url];
    }
    $last = array_pop($items);
    foreach ($items as $item) {
        $html .= '<li><a href="' . htmlspecialchars($item['url']) . '">' . $item['label'] . '</a></li>';
        $html .= '<li class="separator">/</li>';
    }
    $html .= '<li class="current" aria-current="page">' . $last['label'] . '</li>';
    $html .= '</ol>';
    $html .= '</nav>';
    return $html;
}

/**
 * Generate a "Remember Me" token for persistent login
 */
function generateRememberToken($userId, $role) {
    $token = bin2hex(random_bytes(32));
    $expiry = time() + (30 * 24 * 3600); // 30 days
    $data = [
        'user_id' => $userId,
        'role' => $role,
        'token' => $token,
        'expires' => $expiry
    ];

    $dir = __DIR__ . '/../data';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $file = $dir . '/remember_tokens.json';

    $tokens = [];
    if (file_exists($file)) {
        $tokens = json_decode(file_get_contents($file), true) ?: [];
    }

    // Remove expired tokens
    $tokens = array_filter($tokens, function($t) { return $t['expires'] > time(); });

    // Remove existing tokens for this user
    $tokens = array_filter($tokens, function($t) use ($userId) {
        return $t['user_id'] != $userId;
    });

    $tokens[] = $data;
    file_put_contents($file, json_encode(array_values($tokens), JSON_PRETTY_PRINT), LOCK_EX);

    return $token;
}

/**
 * Validate a remember-me token and return user info or false
 */
function validateRememberToken($token) {
    $file = __DIR__ . '/../data/remember_tokens.json';
    if (!file_exists($file)) return false;

    $tokens = json_decode(file_get_contents($file), true) ?: [];
    foreach ($tokens as $t) {
        if ($t['token'] === $token && $t['expires'] > time()) {
            return ['user_id' => $t['user_id'], 'role' => $t['role']];
        }
    }
    return false;
}

/**
 * Remove a remember-me token (on logout)
 */
function removeRememberToken($userId) {
    $file = __DIR__ . '/../data/remember_tokens.json';
    if (!file_exists($file)) return;

    $tokens = json_decode(file_get_contents($file), true) ?: [];
    $tokens = array_filter($tokens, function($t) use ($userId) {
        return $t['user_id'] != $userId;
    });
    file_put_contents($file, json_encode(array_values($tokens), JSON_PRETTY_PRINT), LOCK_EX);
}

/**
 * Attempt auto-login via remember-me cookie
 * Call this after session start but before auth checks
 */
function tryRememberMeAutoLogin($conn) {
    if (isLoggedIn()) return; // already logged in

    if (!empty($_COOKIE['remember_token'])) {
        $result = validateRememberToken($_COOKIE['remember_token']);
        if ($result) {
            if ($result['role'] === 'customer') {
                $sql = "SELECT CustomerID, CustomerName, CompanyName FROM Customer WHERE CustomerID = ?";
            } else {
                $sql = "SELECT StaffID, StaffName FROM Staff WHERE StaffID = ?";
            }
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $result['user_id']);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $user = mysqli_fetch_assoc($res);

            if ($user) {
                $_SESSION['user_id'] = $result['user_id'];
                $_SESSION['user_name'] = $user[$result['role'] === 'customer' ? 'CustomerName' : 'StaffName'];
                $_SESSION['user_role'] = $result['role'];

                if ($result['role'] === 'customer') {
                    $_SESSION['company_name'] = $user['CompanyName'] ?? '';
                    $_SESSION['company_name_missing'] = empty($user['CompanyName']);
                }
            }
        }
    }
}

/**
 * Get page title from URL path for breadcrumbs
 */
function getPageTitle($path) {
    $map = [
        'dashboard' => 'Dashboard',
        'insert_furniture' => 'Insert Furniture',
        'update_furniture' => 'Update Furniture',
        'insert_material' => 'Insert Material',
        'update_material' => 'Update Material',
        'delete_material' => 'Delete Material',
        'delete_furniture' => 'Delete Furniture',
        'manage_customers' => 'Manage Customers',
        'manage_orders' => 'Manage Orders',
        'register_staff' => 'Register Staff',
        'analytics' => 'Analytics',
        'generate_report' => 'Generate Report',
        'update_profile' => 'Update Profile',
        'view_orders' => 'My Orders',
        'cart' => 'Shopping Cart',
    ];
    foreach ($map as $key => $title) {
        if (strpos($path, $key) !== false) return $title;
    }
    return 'Page';
}

/**
 * Render the Quick Actions panel for staff dashboard
 */
function quickActionsPanel() {
    $actions = [
        ['url' => 'insert_furniture.php', 'icon' => '➕', 'label' => 'Add Product'],
        ['url' => 'insert_material.php', 'icon' => '📦', 'label' => 'Add Material'],
        ['url' => 'manage_orders.php?status=pending', 'icon' => '⏳', 'label' => 'Pending Orders'],
        ['url' => 'register_staff.php', 'icon' => '👔', 'label' => 'Add Staff'],
        ['url' => 'analytics.php', 'icon' => '📊', 'label' => 'Analytics'],
        ['url' => 'generate_report.php', 'icon' => '📈', 'label' => 'Reports'],
    ];

    $html = '<div class="quick-actions">';
    $html .= '<h4>⚡ Quick Actions</h4>';
    $html .= '<div class="quick-actions-grid">';
    foreach ($actions as $a) {
        $html .= '<a href="' . $a['url'] . '" class="quick-action-btn" title="' . $a['label'] . '">';
        $html .= '<span class="qa-icon">' . $a['icon'] . '</span>';
        $html .= '<span class="qa-label">' . $a['label'] . '</span>';
        $html .= '</a>';
    }
    $html .= '</div></div>';
    return $html;
}
