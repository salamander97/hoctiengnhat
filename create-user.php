<?php
// create-user.php - Script tạo user mới
require_once 'php/config.php';

// Xử lý tạo user
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $display_name = sanitizeInput($_POST['display_name'] ?? '');
    
    $errors = [];
    
    // Validate input
    if (empty($username)) {
        $errors[] = 'Tên đăng nhập không được để trống';
    } elseif (strlen($username) < 3) {
        $errors[] = 'Tên đăng nhập phải có ít nhất 3 ký tự';
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email không hợp lệ';
    }
    
    if (empty($password)) {
        $errors[] = 'Mật khẩu không được để trống';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Mật khẩu phải có ít nhất 6 ký tự';
    }
    
    if (empty($display_name)) {
        $display_name = $username;
    }
    
    // Nếu không có lỗi, tạo user
    if (empty($errors)) {
        try {
            $db = Database::getInstance();
            
            // Kiểm tra username đã tồn tại chưa
            $existing = $db->fetchOne(
                "SELECT id FROM users WHERE username = ? OR email = ?",
                [$username, $email]
            );
            
            if ($existing) {
                $errors[] = 'Tên đăng nhập hoặc email đã tồn tại';
            } else {
                // Tạo password hash
                $password_hash = hashPassword($password);
                
                // Insert user mới
                $user_id = $db->query(
                    "INSERT INTO users (username, email, password_hash, display_name, is_active, created_at) 
                     VALUES (?, ?, ?, ?, true, NOW()) RETURNING id",
                    [$username, $email, $password_hash, $display_name]
                )->fetchColumn();
                
                // Tạo progress record cho user mới
                $db->query(
                    "INSERT INTO user_progress (user_id, created_at) VALUES (?, NOW())",
                    [$user_id]
                );
                
                $success = "Tạo tài khoản thành công! Username: $username, Password: $password";
            }
        } catch (Exception $e) {
            $errors[] = 'Có lỗi xảy ra: ' . $e->getMessage();
        }
    }
}

// Lấy danh sách users hiện tại
try {
    $db = Database::getInstance();
    $users = $db->fetchAll(
        "SELECT id, username, email, display_name, is_active, created_at, last_login 
         FROM users ORDER BY created_at DESC"
    );
} catch (Exception $e) {
    $users = [];
    $db_error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔧 Quản lý User - Admin Panel</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', sans-serif;
        }
        .admin-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            margin: 20px auto;
            max-width: 1000px;
        }
        .admin-header {
            background: linear-gradient(135deg, #ff9a8b 0%, #ffecd2 100%);
            color: white;
            padding: 30px;
            border-radius: 20px 20px 0 0;
            text-align: center;
        }
        .form-section {
            background: linear-gradient(135deg, #a8e6cf 0%, #dcedc1 100%);
            padding: 30px;
            border-radius: 15px;
            margin: 20px;
        }
        .user-table {
            margin: 20px;
        }
        .btn-create {
            background: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%);
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 600;
        }
        .btn-create:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(86, 171, 47, 0.3);
            color: white;
        }
        .user-badge {
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.8em;
            font-weight: 600;
        }
        .user-active {
            background: #d4edda;
            color: #155724;
        }
        .user-inactive {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <h1>🔧 Quản lý User - Admin Panel</h1>
            <p>Tạo và quản lý tài khoản người dùng</p>
        </div>

        <!-- Form tạo user mới -->
        <div class="form-section">
            <h3>👤 Tạo tài khoản mới</h3>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="row g-3">
                <div class="col-md-6">
                    <label for="username" class="form-label">Tên đăng nhập *</label>
                    <input type="text" class="form-control" id="username" name="username" 
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                    <div class="form-text">Ít nhất 3 ký tự, không có khoảng trắng</div>
                </div>
                
                <div class="col-md-6">
                    <label for="email" class="form-label">Email *</label>
                    <input type="email" class="form-control" id="email" name="email" 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                </div>
                
                <div class="col-md-6">
                    <label for="password" class="form-label">Mật khẩu *</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                    <div class="form-text">Ít nhất 6 ký tự</div>
                </div>
                
                <div class="col-md-6">
                    <label for="display_name" class="form-label">Tên hiển thị</label>
                    <input type="text" class="form-control" id="display_name" name="display_name" 
                           value="<?php echo htmlspecialchars($_POST['display_name'] ?? ''); ?>">
                    <div class="form-text">Để trống sẽ dùng tên đăng nhập</div>
                </div>
                
                <div class="col-12">
                    <button type="submit" class="btn btn-create">
                        ➕ Tạo tài khoản
                    </button>
                </div>
            </form>
        </div>

        <!-- Danh sách users -->
        <div class="user-table">
            <h3>📋 Danh sách tài khoản</h3>
            
            <?php if (!empty($db_error)): ?>
                <div class="alert alert-danger">
                    Lỗi kết nối database: <?php echo htmlspecialchars($db_error); ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Tên đăng nhập</th>
                                <th>Email</th>
                                <th>Tên hiển thị</th>
                                <th>Trạng thái</th>
                                <th>Ngày tạo</th>
                                <th>Đăng nhập cuối</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted">
                                        Chưa có tài khoản nào
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo $user['id']; ?></td>
                                        <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($user['display_name'] ?? 'N/A'); ?></td>
                                        <td>
                                            <span class="user-badge <?php echo $user['is_active'] ? 'user-active' : 'user-inactive'; ?>">
                                                <?php echo $user['is_active'] ? '✅ Hoạt động' : '❌ Khóa'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            if ($user['created_at']) {
                                                echo date('d/m/Y H:i', strtotime($user['created_at']));
                                            } else {
                                                echo 'N/A';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                            if ($user['last_login']) {
                                                echo date('d/m/Y H:i', strtotime($user['last_login']));
                                            } else {
                                                echo 'Chưa đăng nhập';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Quick Actions -->
        <div class="form-section">
            <h4>⚡ Quick Actions</h4>
            <div class="row g-3">
                <div class="col-md-4">
                    <button class="btn btn-outline-primary w-100" onclick="createSampleUsers()">
                        👥 Tạo 5 user mẫu
                    </button>
                </div>
                <div class="col-md-4">
                    <a href="index.html" class="btn btn-outline-success w-100">
                        🏠 Về trang chủ
                    </a>
                </div>
                <div class="col-md-4">
                    <button class="btn btn-outline-danger w-100" onclick="resetPasswords()">
                        🔄 Reset tất cả mật khẩu thành 'password'
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        function createSampleUsers() {
            if (confirm('Tạo 5 user mẫu với mật khẩu "password"?')) {
                // Tạo form ẩn và submit
                const sampleUsers = [
                    {username: 'student1', email: 'student1@example.com', display_name: 'Học viên 1'},
                    {username: 'student2', email: 'student2@example.com', display_name: 'Học viên 2'},
                    {username: 'teacher', email: 'teacher@example.com', display_name: 'Giáo viên'},
                    {username: 'manager', email: 'manager@example.com', display_name: 'Quản lý'},
                    {username: 'guest', email: 'guest@example.com', display_name: 'Khách'}
                ];
                
                // Tạo users một cách tuần tự
                createUsersSequentially(sampleUsers, 0);
            }
        }
        
        function createUsersSequentially(users, index) {
            if (index >= users.length) {
                alert('Đã tạo xong tất cả user mẫu!');
                location.reload();
                return;
            }
            
            const user = users[index];
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            ['username', 'email', 'display_name'].forEach(field => {
                const input = document.createElement('input');
                input.name = field;
                input.value = user[field];
                form.appendChild(input);
            });
            
            const passwordInput = document.createElement('input');
            passwordInput.name = 'password';
            passwordInput.value = 'password';
            form.appendChild(passwordInput);
            
            document.body.appendChild(form);
            form.submit();
        }
        
        function resetPasswords() {
            if (confirm('Reset tất cả mật khẩu về "password"? Hành động này không thể hoàn tác!')) {
                // Gửi request đến API reset password
                fetch('php/admin-actions.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({action: 'reset_all_passwords'})
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Đã reset tất cả mật khẩu thành "password"');
                    } else {
                        alert('Lỗi: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Có lỗi xảy ra');
                });
            }
        }
    </script>
</body>
</html>
