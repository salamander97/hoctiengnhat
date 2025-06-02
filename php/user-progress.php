<?php
// php/user-progress.php - API quản lý tiến độ học tập (FIXED VERSION)

require_once 'config.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Kiểm tra đăng nhập
if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Vui lòng đăng nhập']);
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get':
        getUserProgress();
        break;
    case 'save':
        saveUserProgress();
        break;
    case 'update':
        updateUserProgress();
        break;
    case 'stats':
        getUserStats();
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'Action không hợp lệ']);
}

function getUserProgress() {
    try {
        $db = Database::getInstance();
        $userId = $_SESSION['user_id'];
        
        // Lấy tiến độ của user
        $progress = $db->fetchOne(
            "SELECT hiragana_score, hiragana_total, katakana_score, katakana_total, 
                    numbers_score, numbers_total, vocabulary_n5_score, vocabulary_n5_total,
                    vocabulary_n4_score, vocabulary_n4_total, vocabulary_n3_score, vocabulary_n3_total,
                    last_updated
             FROM user_progress WHERE user_id = ?",
            [$userId]
        );
        
        if (!$progress) {
            // Tạo record mới nếu chưa có
            $db->query(
                "INSERT INTO user_progress (user_id, created_at, last_updated) VALUES (?, NOW(), NOW())",
                [$userId]
            );
            
            $progress = [
                'hiragana_score' => 0, 'hiragana_total' => 0,
                'katakana_score' => 0, 'katakana_total' => 0,
                'numbers_score' => 0, 'numbers_total' => 0,
                'vocabulary_n5_score' => 0, 'vocabulary_n5_total' => 0,
                'vocabulary_n4_score' => 0, 'vocabulary_n4_total' => 0,
                'vocabulary_n3_score' => 0, 'vocabulary_n3_total' => 0,
                'last_updated' => date('Y-m-d H:i:s')
            ];
        }
        
        // Tính phần trăm tiến độ
        $progressData = [
            'hiragana' => calculatePercentage($progress['hiragana_score'], $progress['hiragana_total']),
            'katakana' => calculatePercentage($progress['katakana_score'], $progress['katakana_total']),
            'numbers' => calculatePercentage($progress['numbers_score'], $progress['numbers_total']),
            'vocabulary_n5' => calculatePercentage($progress['vocabulary_n5_score'], $progress['vocabulary_n5_total']),
            'vocabulary_n4' => calculatePercentage($progress['vocabulary_n4_score'], $progress['vocabulary_n4_total']),
            'vocabulary_n3' => calculatePercentage($progress['vocabulary_n3_score'], $progress['vocabulary_n3_total']),
            'last_updated' => $progress['last_updated']
        ];
        
        jsonResponse([
            'success' => true,
            'progress' => $progressData
        ]);
        
    } catch (Exception $e) {
        error_log("Get progress error: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Có lỗi xảy ra khi lấy tiến độ: ' . $e->getMessage()]);
    }
}

function saveUserProgress() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['success' => false, 'message' => 'Method không hợp lệ']);
    }
    
    $type = sanitizeInput($_POST['type'] ?? '');
    $score = (int)($_POST['score'] ?? 0);
    $total = (int)($_POST['total'] ?? 0);
    
    // Debug log
    error_log("Save progress: type=$type, score=$score, total=$total");
    
    if (empty($type) || $score < 0 || $total <= 0) {
        jsonResponse(['success' => false, 'message' => 'Dữ liệu không hợp lệ: type=' . $type . ', score=' . $score . ', total=' . $total]);
    }
    
    try {
        $db = Database::getInstance();
        $userId = $_SESSION['user_id'];
        
        // Map type to database columns
        $typeMapping = [
            'hiragana' => ['hiragana_score', 'hiragana_total'],
            'katakana' => ['katakana_score', 'katakana_total'],
            'numbers' => ['numbers_score', 'numbers_total'],
            'vocabulary_n5' => ['vocabulary_n5_score', 'vocabulary_n5_total'],
            'vocabulary_n4' => ['vocabulary_n4_score', 'vocabulary_n4_total'],
            'vocabulary_n3' => ['vocabulary_n3_score', 'vocabulary_n3_total']
        ];
        
        if (!isset($typeMapping[$type])) {
            jsonResponse(['success' => false, 'message' => 'Loại bài học không hợp lệ: ' . $type]);
        }
        
        $scoreColumn = $typeMapping[$type][0];
        $totalColumn = $typeMapping[$type][1];
        
        // Kiểm tra xem user đã có record chưa
        $existing = $db->fetchOne(
            "SELECT id, $scoreColumn as current_score, $totalColumn as current_total FROM user_progress WHERE user_id = ?",
            [$userId]
        );
        
        if ($existing) {
            // Cập nhật record hiện tại (chỉ cập nhật nếu điểm số tốt hơn)
            $currentPercentage = calculatePercentage($existing['current_score'], $existing['current_total']);
            $newPercentage = calculatePercentage($score, $total);
            
            if ($newPercentage >= $currentPercentage) {
                $db->query(
                    "UPDATE user_progress SET $scoreColumn = ?, $totalColumn = ?, last_updated = NOW() WHERE user_id = ?",
                    [$score, $total, $userId]
                );
                error_log("Updated progress for user $userId: $scoreColumn = $score, $totalColumn = $total");
            } else {
                error_log("Progress not updated - new percentage ($newPercentage%) is not better than current ($currentPercentage%)");
            }
        } else {
            // Tạo record mới
            $db->query(
                "INSERT INTO user_progress (user_id, $scoreColumn, $totalColumn, created_at, last_updated) VALUES (?, ?, ?, NOW(), NOW())",
                [$userId, $score, $total]
            );
            error_log("Created new progress record for user $userId");
        }
        
        // Log activity
        logUserActivity($userId, $type, $score, $total);
        
        jsonResponse([
            'success' => true,
            'message' => 'Đã lưu tiến độ thành công',
            'percentage' => calculatePercentage($score, $total)
        ]);
        
    } catch (Exception $e) {
        error_log("Save progress error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        jsonResponse(['success' => false, 'message' => 'Có lỗi xảy ra khi lưu tiến độ: ' . $e->getMessage()]);
    }
}

function updateUserProgress() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['success' => false, 'message' => 'Method không hợp lệ']);
    }
    
    $type = sanitizeInput($_POST['type'] ?? '');
    $score = (int)($_POST['score'] ?? 0);
    $total = (int)($_POST['total'] ?? 0);
    
    try {
        $db = Database::getInstance();
        $userId = $_SESSION['user_id'];
        
        $typeMapping = [
            'hiragana' => ['hiragana_score', 'hiragana_total'],
            'katakana' => ['katakana_score', 'katakana_total'],
            'numbers' => ['numbers_score', 'numbers_total'],
            'vocabulary_n5' => ['vocabulary_n5_score', 'vocabulary_n5_total'],
            'vocabulary_n4' => ['vocabulary_n4_score', 'vocabulary_n4_total'],
            'vocabulary_n3' => ['vocabulary_n3_score', 'vocabulary_n3_total']
        ];
        
        if (!isset($typeMapping[$type])) {
            jsonResponse(['success' => false, 'message' => 'Loại bài học không hợp lệ']);
        }
        
        $scoreColumn = $typeMapping[$type][0];
        $totalColumn = $typeMapping[$type][1];
        
        // Upsert - Insert or Update
        $existing = $db->fetchOne("SELECT id FROM user_progress WHERE user_id = ?", [$userId]);
        
        if ($existing) {
            // Update existing record
            $db->query(
                "UPDATE user_progress SET $scoreColumn = ?, $totalColumn = ?, last_updated = NOW() WHERE user_id = ?",
                [$score, $total, $userId]
            );
        } else {
            // Insert new record
            $db->query(
                "INSERT INTO user_progress (user_id, $scoreColumn, $totalColumn, created_at, last_updated) VALUES (?, ?, ?, NOW(), NOW())",
                [$userId, $score, $total]
            );
        }
        
        logUserActivity($userId, $type, $score, $total);
        
        jsonResponse([
            'success' => true,
            'message' => 'Đã cập nhật tiến độ thành công',
            'percentage' => calculatePercentage($score, $total)
        ]);
        
    } catch (Exception $e) {
        error_log("Update progress error: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Có lỗi xảy ra khi cập nhật tiến độ: ' . $e->getMessage()]);
    }
}

function getUserStats() {
    try {
        $db = Database::getInstance();
        $userId = $_SESSION['user_id'];
        
        // Lấy thống kê tổng quan
        $stats = $db->fetchOne(
            "SELECT 
                COALESCE(hiragana_score, 0) + COALESCE(katakana_score, 0) + COALESCE(numbers_score, 0) + COALESCE(vocabulary_n5_score, 0) + COALESCE(vocabulary_n4_score, 0) + COALESCE(vocabulary_n3_score, 0) as total_score,
                COALESCE(hiragana_total, 0) + COALESCE(katakana_total, 0) + COALESCE(numbers_total, 0) + COALESCE(vocabulary_n5_total, 0) + COALESCE(vocabulary_n4_total, 0) + COALESCE(vocabulary_n3_total, 0) as total_questions,
                last_updated
             FROM user_progress WHERE user_id = ?",
            [$userId]
        );
        
        // Lấy lịch sử hoạt động gần đây
        $recentActivities = $db->fetchAll(
            "SELECT activity_type, score, total_questions, created_at 
             FROM user_activities 
             WHERE user_id = ? 
             ORDER BY created_at DESC 
             LIMIT 10",
            [$userId]
        );
        
        // Tính streak (chuỗi ngày học liên tiếp)
        $streak = calculateLearningStreak($userId);
        
        jsonResponse([
            'success' => true,
            'stats' => [
                'total_score' => $stats['total_score'] ?? 0,
                'total_questions' => $stats['total_questions'] ?? 0,
                'overall_percentage' => calculatePercentage($stats['total_score'] ?? 0, $stats['total_questions'] ?? 1),
                'learning_streak' => $streak,
                'last_updated' => $stats['last_updated'] ?? null,
                'recent_activities' => $recentActivities
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Get stats error: " . $e->getMessage());
        jsonResponse(['success' => false, 'message' => 'Có lỗi xảy ra khi lấy thống kê: ' . $e->getMessage()]);
    }
}

// Helper functions
function calculatePercentage($score, $total) {
    if ($total <= 0) return 0;
    return round(($score / $total) * 100, 1);
}

function logUserActivity($userId, $type, $score, $total) {
    try {
        $db = Database::getInstance();
        $db->query(
            "INSERT INTO user_activities (user_id, activity_type, score, total_questions, created_at) 
             VALUES (?, ?, ?, ?, NOW())",
            [$userId, $type, $score, $total]
        );
        error_log("Logged activity for user $userId: $type - $score/$total");
    } catch (Exception $e) {
        error_log("Log activity error: " . $e->getMessage());
    }
}

function calculateLearningStreak($userId) {
    try {
        $db = Database::getInstance();
        
        // Lấy các ngày có hoạt động học tập (distinct dates)
        $activities = $db->fetchAll(
            "SELECT DISTINCT DATE(created_at) as activity_date 
             FROM user_activities 
             WHERE user_id = ? 
             ORDER BY activity_date DESC",
            [$userId]
        );
        
        if (empty($activities)) return 0;
        
        $streak = 0;
        $currentDate = new DateTime();
        $currentDate->setTime(0, 0, 0); // Reset time to 00:00:00
        
        foreach ($activities as $activity) {
            $activityDate = new DateTime($activity['activity_date']);
            $diff = $currentDate->diff($activityDate)->days;
            
            if ($diff == $streak) {
                $streak++;
                $currentDate->sub(new DateInterval('P1D'));
            } else {
                break;
            }
        }
        
        return $streak;
        
    } catch (Exception $e) {
        error_log("Calculate streak error: " . $e->getMessage());
        return 0;
    }
}
?>
