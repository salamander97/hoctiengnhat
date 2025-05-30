<?php
// php/check-username.php - API kiểm tra username có tồn tại không

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['success' => false, 'message' => 'Method không hợp lệ']);
}

$username = sanitizeInput($_GET['username'] ?? '');

if (empty($username)) {
    jsonResponse(['exists' => false]);
}

if (strlen($username) < 3 || !preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
    jsonResponse(['exists' => false, 'valid' => false]);
}

try {
    $db = Database::getInstance();
    
    $existing = $db->fetchOne(
        "SELECT id FROM users WHERE username = ?",
        [$username]
    );
    
    jsonResponse([
        'exists' => ($existing !== false),
        'valid' => true
    ]);
    
} catch (Exception $e) {
    error_log("Check username error: " . $e->getMessage());
    jsonResponse(['exists' => false, 'error' => true]);
}
?>
