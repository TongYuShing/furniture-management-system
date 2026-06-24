<?php
/**
 * session.php - Session and Authentication Management
 * Handles login validation and role-based access control
 */

require_once 'config.php';

// Ensure session is started only once
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
function checkLogin() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
        $_SESSION['error'] = "Please login to access this page.";
        header("Location: " . BASE_URL . "index.php");
        exit();
    }
}

// Check if user has staff role
function checkStaffRole() {
    checkLogin();
    if ($_SESSION['user_role'] !== 'staff') {
        $_SESSION['error'] = "Access denied. Staff privileges required.";
        header("Location: " . BASE_URL . "customer/dashboard.php");
        exit();
    }
}

// Check if user has customer role
function checkCustomerRole() {
    checkLogin();
    if ($_SESSION['user_role'] !== 'customer') {
        $_SESSION['error'] = "Access denied. Customer privileges required.";
        header("Location: " . BASE_URL . "staff/dashboard.php");
        exit();
    }
}

// Customer login function
function customerLogin($conn, $customerId, $password) {
    $sql = "SELECT CustomerID, CustomerName, CompanyName, Password FROM Customer WHERE CustomerID = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $customerId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($row = mysqli_fetch_assoc($result)) {
        $authenticated = false;
        $needsUpgrade = false;

        // Try password_verify() first (bcrypt for new registrations)
        if (password_verify($password, $row['Password'])) {
            $authenticated = true;
        }
        // Fallback to md5 for legacy passwords
        elseif ($row['Password'] === md5($password)) {
            $authenticated = true;
            $needsUpgrade = true;
        }

        if ($authenticated) {
            // Auto-upgrade legacy md5 hash to bcrypt
            if ($needsUpgrade) {
                $newHash = password_hash($password, PASSWORD_BCRYPT);
                $updateSql = "UPDATE Customer SET Password = ? WHERE CustomerID = ?";
                $updateStmt = mysqli_prepare($conn, $updateSql);
                mysqli_stmt_bind_param($updateStmt, "si", $newHash, $row['CustomerID']);
                mysqli_stmt_execute($updateStmt);
                mysqli_stmt_close($updateStmt);
            }

            $_SESSION['user_id'] = $row['CustomerID'];
            $_SESSION['user_name'] = $row['CustomerName'];
            $_SESSION['user_role'] = 'customer';

            // Check if customer has a company name set
            if (empty($row['CompanyName'])) {
                $_SESSION['company_name_missing'] = true;
            } else {
                $_SESSION['company_name'] = $row['CompanyName'];
                $_SESSION['company_name_missing'] = false;
            }

            return true;
        }
    }
    return false;
}

// Check if customer needs to complete company name
function needsCompanyName() {
    return isset($_SESSION['company_name_missing']) && $_SESSION['company_name_missing'] === true;
}

// Staff login function
function staffLogin($conn, $staffId, $password) {
    $sql = "SELECT StaffID, StaffName, Password FROM Staff WHERE StaffID = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $staffId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($row = mysqli_fetch_assoc($result)) {
        $authenticated = false;
        $needsUpgrade = false;

        // Try password_verify() first (bcrypt for new accounts)
        if (password_verify($password, $row['Password'])) {
            $authenticated = true;
        }
        // Fallback to md5 for legacy passwords
        elseif ($row['Password'] === md5($password)) {
            $authenticated = true;
            $needsUpgrade = true;
        }

        if ($authenticated) {
            // Auto-upgrade legacy md5 hash to bcrypt
            if ($needsUpgrade) {
                $newHash = password_hash($password, PASSWORD_BCRYPT);
                $updateSql = "UPDATE Staff SET Password = ? WHERE StaffID = ?";
                $updateStmt = mysqli_prepare($conn, $updateSql);
                mysqli_stmt_bind_param($updateStmt, "si", $newHash, $row['StaffID']);
                mysqli_stmt_execute($updateStmt);
                mysqli_stmt_close($updateStmt);
            }

            $_SESSION['user_id'] = $row['StaffID'];
            $_SESSION['user_name'] = $row['StaffName'];
            $_SESSION['user_role'] = 'staff';
            return true;
        }
    }
    return false;
}

// Get current user info
function getCurrentUserInfo($conn) {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
        return null;
    }
    
    if ($_SESSION['user_role'] === 'customer') {
        $sql = "SELECT CustomerID as id, CustomerName as name, Email, ContactNumber, Address FROM Customer WHERE CustomerID = ?";
    } else {
        $sql = "SELECT StaffID as id, StaffName as name, Email FROM Staff WHERE StaffID = ?";
    }
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    return mysqli_fetch_assoc($result);
}
?>