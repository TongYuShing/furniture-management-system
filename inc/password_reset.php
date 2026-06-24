<?php
/**
 * password_reset.php - Password Reset Helper Functions
 * Stores reset tokens in a JSON file (no DB changes needed)
 */

define('RESET_TOKENS_FILE', __DIR__ . '/../data/reset_tokens.json');
define('RESET_TOKEN_EXPIRY', 3600); // 1 hour

/**
 * Generate a reset token for a given email
 */
function generateResetToken($email) {
    return md5($email . time() . uniqid('reset_', true));
}

/**
 * Store a reset token
 */
function storeResetToken($email, $token) {
    $tokens = loadResetTokens();
    // Remove any existing tokens for this email
    $tokens = array_filter($tokens, function($t) use ($email) {
        return $t['email'] !== $email;
    });
    $tokens[] = [
        'email' => $email,
        'token' => $token,
        'created' => time(),
        'expires' => time() + RESET_TOKEN_EXPIRY
    ];
    saveResetTokens(array_values($tokens));
}

/**
 * Validate a reset token and return the associated email
 */
function validateResetToken($token) {
    $tokens = loadResetTokens();
    foreach ($tokens as $t) {
        if ($t['token'] === $token && $t['expires'] > time()) {
            return $t['email'];
        }
    }
    return false;
}

/**
 * Consume (delete) a reset token after use
 */
function consumeResetToken($token) {
    $tokens = loadResetTokens();
    $tokens = array_filter($tokens, function($t) use ($token) {
        return $t['token'] !== $token;
    });
    saveResetTokens(array_values($tokens));
}

/**
 * Load tokens from JSON file
 */
function loadResetTokens() {
    $dir = dirname(RESET_TOKENS_FILE);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    if (!file_exists(RESET_TOKENS_FILE)) {
        return [];
    }
    $data = file_get_contents(RESET_TOKENS_FILE);
    $tokens = json_decode($data, true);
    return is_array($tokens) ? $tokens : [];
}

/**
 * Save tokens to JSON file
 */
function saveResetTokens($tokens) {
    $dir = dirname(RESET_TOKENS_FILE);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents(RESET_TOKENS_FILE, json_encode($tokens, JSON_PRETTY_PRINT), LOCK_EX);
}

/**
 * Find customer by email
 */
function findCustomerByEmail($conn, $email) {
    $sql = "SELECT CustomerID, CustomerName, Email FROM Customer WHERE Email = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_assoc($result);
}

/**
 * Update customer password directly
 */
function updateCustomerPasswordDirect($conn, $email, $newPassword) {
    $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
    $sql = "UPDATE Customer SET Password = ? WHERE Email = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ss", $hashedPassword, $email);
    return mysqli_stmt_execute($stmt);
}
