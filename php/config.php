<?php
// php/config.php - Cấu hình kết nối cơ sở dữ liệu

// Cấu hình database
define('DB_HOST', 'localhost');
define('DB_PORT', '5432');
define('DB_NAME', 'japanese_learning');
define('DB_USER', 'trunghieu');
define('DB_PASS', 'Trunghieu97');

// Cấu hình session
ini_set('session.cookie_lifetime', 7200); // 2 giờ
ini_set('session.gc_maxlifetime', 7200);
session_start();

// Cấu hình timezone
date_default_timezone_set('Asia/Tokyo');

// Class Database để kết nối PostgreSQL
class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch(PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die(json_encode(['success' => false, 'message' => 'Kết nối cơ sở dữ liệu thất bại']));
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    // Hàm helper để execute query
    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch(PDOException $e) {
            error_log("Query failed: " . $e->getMessage());
            throw $e;
        }
    }
    
    // Hàm helper để fetch single row
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    // Hàm helper để fetch multiple rows
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    // Hàm helper để insert và return ID
    public function insert($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $this->connection->lastInsertId();
    }
}

// Hàm utility để trả về JSON response
function jsonResponse($data) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Hàm validate input
function sanitizeInput($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

// Hàm hash password (sử dụng PHP password_hash)
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Hàm verify password
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Hàm kiểm tra đăng nhập
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Hàm lấy thông tin user hiện tại
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    $db = Database::getInstance();
    return $db->fetchOne(
        "SELECT id, username, display_name, email, created_at, last_login FROM users WHERE id = ?",
        [$_SESSION['user_id']]
    );
}

// Hàm logout
function logout() {
    session_destroy();
    session_start();
}

// CORS headers (nếu cần)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}
?>
