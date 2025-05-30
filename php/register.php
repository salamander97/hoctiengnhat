<?php
// php/register.php - API xử lý đăng ký user mới

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method không hợp lệ']);
}

$action = $_POST['action'] ?? '';

if ($action !== 'register') {
    jsonResponse(['success' => false, 'message' => 'Action không hợp lệ']);
}

// Lấy dữ liệu từ form
$username = sanitizeInput($_POST['username'] ?? '');
$email = sanitizeInput($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';
$display_name = sanitizeInput($_POST['display_name'] ?? '');

// Validation
$errors = [];

// Validate username
if (empty($username)) {
    $errors[] = 'Tên đăng nhập không được để trống';
} elseif (strlen($username) < 3) {
    $errors[] = 'Tên đăng nhập phải có ít nhất 3 ký tự';
} elseif (strlen($username) > 50) {
    $errors[] = 'Tên đăng nhập không được quá 50 ký tự';
} elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
    $errors[] = 'Tên đăng nhập chỉ được chứa chữ cái, số và dấu gạch dưới';
}

// Validate email
if (empty($email)) {
    $errors[] = 'Email không được để trống';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Email không hợp lệ';
} elseif (strlen($email) > 100) {
    $errors[] = 'Email không được quá 100 ký tự';
}

// Validate password
if (empty($password)) {
    $errors[] = 'Mật khẩu không được để trống';
} elseif (strlen($password) < 6) {
    $errors[] = 'Mật khẩu phải có ít nhất 6 ký tự';
} elseif (strlen($password) > 255) {
    $errors[] = 'Mật khẩu không được quá 255 ký tự';
}

// Validate confirm password
if ($password !== $confirm_password) {
    $errors[] = 'Mật khẩu xác nhận không khớp';
}

// Validate display name
if (empty($display_name)) {
    $display_name = $username; // Sử dụng username nếu không có display name
} elseif (strlen($display_name) > 100) {
    $errors[] = 'Tên hiển thị không được quá 100 ký tự';
}

// Nếu có lỗi validation, trả về
if (!empty($errors)) {
    jsonResponse([
        'success' => false, 
        'message' => implode('<br>', $errors)
    ]);
}

try {
    $db = Database::getInstance();
    
    // Kiểm tra username và email đã tồn tại chưa
    $existing = $db->fetchOne(
        "SELECT username, email FROM users WHERE username = ? OR email = ?",
        [$username, $email]
    );
    
    if ($existing) {
        if ($existing['username'] === $username) {
            jsonResponse(['success' => false, 'message' => 'Tên đăng nhập đã tồn tại']);
        } else {
            jsonResponse(['success' => false, 'message' => 'Email đã được sử dụng']);
        }
    }
    
    // Tạo password hash
    $password_hash = hashPassword($password);
    
    // Insert user mới
    $stmt = $db->query(
        "INSERT INTO users (username, email, password_hash, display_name, is_active, created_at) 
         VALUES (?, ?, ?, ?, true, NOW()) RETURNING id",
        [$username, $email, $password_hash, $display_name]
    );
    
    $user_id = $stmt->fetchColumn();
    
    if (!$user_id) {
        throw new Exception('Không thể tạo tài khoản');
    }
    
    // Tạo progress record cho user mới
    $db->query(
        "INSERT INTO user_progress (user_id, created_at, last_updated) VALUES (?, NOW(), NOW())",
        [$user_id]
    );
    
    // Log hoạt động đăng ký
    $db->query(
        "INSERT INTO user_activities (user_id, activity_type, score, total_questions, created_at) 
         VALUES (?, 'account_created', 0, 0, NOW())",
        [$user_id]
    );
    
    jsonResponse([
        'success' => true,
        'message' => 'Tạo tài khoản thành công!',
        'username' => $username,
        'email' => $email,
        'display_name' => $display_name,
        'user_id' => $user_id
    ]);
    
} catch (Exception $e) {
    error_log("Registration error: " . $e->getMessage());
    
    // Trả về lỗi generic để không expose system info
    jsonResponse([
        'success' => false, 
        'message' => 'Có lỗi xảy ra khi tạo tài khoản. Vui lòng thử lại sau.'
    ]);
}
?>
