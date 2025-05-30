<?php
// php/auth.php - API xử lý đăng nhập và xác thực

require_once 'config.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'login':
        handleLogin();
        break;
    case 'logout':
        handleLogout();
        break;
    case 'check':
        checkSession();
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'Action không hợp lệ']);
}

function handleLogin() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['success' => false, 'message' => 'Method không hợp lệ']);
    }
    
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validate input
    if (empty($username) || empty($password)) {
        jsonResponse(['success' => false, 'message' => 'Vui lòng nhập đầy đủ thông tin']);
    }
    
    try {
        $db = Database::getInstance();
        
        // Tìm user trong database
        $user = $db->fetchOne(
            "SELECT id, username, password_hash, display_name, email, is_active FROM users WHERE username = ? OR email = ?",
            [$username, $username]
        );
        
        if (!$user) {
            jsonResponse(['success' => false, 'message' => 'Tài khoản không tồn tại']);
        }
        
        if (!$user['is_active']) {
            jsonResponse(['success' => false, 'message' => 'Tài khoản đã bị khóa']);
        }
        
        // Verify password
        if (!verifyPassword($password, $user['password_hash'])) {
            jsonResponse(['success' => false, 'message' => 'Mật khẩu không đúng']);
        }
        
        // Đăng nhập thành công
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        
        // Cập nhật last_login
        $db->query(
            "UPDATE users SET last_login = NOW() WHERE id = ?",
            [$user['id']]
        );
        
        // Trả về thông tin user (không bao gồm password)
        unset($user['password_hash']);
        
        jsonResponse([
            'success' => true,
            'message' => 'Đăng nhập thành công',
            'user' => $user
        ]);
        
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Có lỗi xảy ra, vui lòng thử lại']);
    }
}

function handleLogout() {
    logout();
    jsonResponse(['success' => true, 'message' => 'Đăng xuất thành công']);
}

function checkSession() {
    if (isLoggedIn()) {
        $user = getCurrentUser();
        if ($user) {
            jsonResponse([
                'success' => true,
                'user' => $user,
                'logged_in' => true
            ]);
        }
    }
    
    jsonResponse([
        'success' => false,
        'logged_in' => false,
        'message' => 'Chưa đăng nhập'
    ]);
}
?>
