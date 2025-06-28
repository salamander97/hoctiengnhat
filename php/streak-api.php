<?php
// File: php/streak-api.php
// API cho Streak System

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once 'config/database.php';
require_once 'utils/auth.php';

// Xử lý OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $pdo = Database::getInstance()->getConnection();
    
    // Kiểm tra authentication
    $user = authenticate();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit();
    }
    
    $action = $_GET['action'] ?? '';
    $user_id = $user['id'];
    
    switch ($action) {
        case 'get_streak_info':
            getStreakInfo($pdo, $user_id);
            break;
            
        case 'update_streak':
            updateStreak($pdo, $user_id);
            break;
            
        case 'get_streak_calendar':
            getStreakCalendar($pdo, $user_id);
            break;
            
        case 'get_streak_stats':
            getStreakStats($pdo, $user_id);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    error_log("Streak API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}

// Lấy thông tin streak của user
function getStreakInfo($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM get_user_streak_info(?)");
        $stmt->execute([$user_id]);
        $streak_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($streak_info) {
            // Thêm thông tin motivational message
            $current_streak = (int)$streak_info['current_streak'];
            $message = getStreakMessage($current_streak);
            $streak_info['motivation_message'] = $message['message'];
            $streak_info['motivation_emoji'] = $message['emoji'];
            
            echo json_encode([
                'success' => true,
                'data' => $streak_info
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'No streak data found'
            ]);
        }
        
    } catch (Exception $e) {
        error_log("Get streak info error: " . $e->getMessage());
        throw $e;
    }
}

// Cập nhật streak (gọi khi user có hoạt động mới)
function updateStreak($pdo, $user_id) {
    try {
        // Gọi function update streak
        $stmt = $pdo->prepare("SELECT update_user_streak(?) as new_streak");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $new_streak = (int)$result['new_streak'];
        
        // Lấy thông tin streak mới
        $stmt = $pdo->prepare("SELECT * FROM get_user_streak_info(?)");
        $stmt->execute([$user_id]);
        $streak_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Check achievement
        $achievement = checkStreakAchievement($pdo, $user_id, $new_streak);
        
        echo json_encode([
            'success' => true,
            'data' => $streak_info,
            'new_achievement' => $achievement
        ]);
        
    } catch (Exception $e) {
        error_log("Update streak error: " . $e->getMessage());
        throw $e;
    }
}

// Lấy calendar streak (30 ngày gần đây)
function getStreakCalendar($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                activity_date,
                activities_count,
                is_streak_day
            FROM daily_streaks 
            WHERE user_id = ? 
            AND activity_date >= CURRENT_DATE - INTERVAL '30 days'
            ORDER BY activity_date ASC
        ");
        $stmt->execute([$user_id]);
        $calendar_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $calendar_data
        ]);
        
    } catch (Exception $e) {
        error_log("Get streak calendar error: " . $e->getMessage());
        throw $e;
    }
}

// Lấy thống kê streak
function getStreakStats($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_study_days,
                COUNT(CASE WHEN is_streak_day THEN 1 END) as streak_days,
                SUM(activities_count) as total_activities,
                AVG(activities_count) as avg_activities_per_day,
                MAX(activities_count) as max_activities_day
            FROM daily_streaks 
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Thêm streak info
        $stmt = $pdo->prepare("SELECT * FROM get_user_streak_info(?)");
        $stmt->execute([$user_id]);
        $streak_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $combined_stats = array_merge($stats, $streak_info);
        
        echo json_encode([
            'success' => true,
            'data' => $combined_stats
        ]);
        
    } catch (Exception $e) {
        error_log("Get streak stats error: " . $e->getMessage());
        throw $e;
    }
}

// Tạo motivational message dựa trên streak
function getStreakMessage($streak) {
    if ($streak == 0) {
        return [
            'message' => 'Hãy bắt đầu chuỗi học tập của bạn!',
            'emoji' => '🌱'
        ];
    } elseif ($streak < 7) {
        return [
            'message' => 'Tuyệt vời! Tiếp tục phát huy nhé!',
            'emoji' => '🔥'
        ];
    } elseif ($streak < 30) {
        return [
            'message' => 'Chuỗi học tập ấn tượng! Bạn đang rất tốt!',
            'emoji' => '⭐'
        ];
    } elseif ($streak < 100) {
        return [
            'message' => 'Tuyệt vời! Bạn là một học viên kiên trì!',
            'emoji' => '🏆'
        ];
    } else {
        return [
            'message' => 'Huyền thoại! Chuỗi học tập đáng kinh ngạc!',
            'emoji' => '👑'
        ];
    }
}

// Check achievement cho streak
function checkStreakAchievement($pdo, $user_id, $streak) {
    $milestones = [7, 14, 30, 50, 100, 365];
    
    if (in_array($streak, $milestones)) {
        try {
            // Check xem đã có achievement này chưa
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count 
                FROM user_achievements 
                WHERE user_id = ? 
                AND achievement_type = 'streak_milestone'
                AND JSON_EXTRACT(achievement_data, '$.streak_days') = ?
            ");
            $stmt->execute([$user_id, $streak]);
            $exists = $stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
            
            if (!$exists) {
                // Tạo achievement mới
                $achievement_data = json_encode([
                    'streak_days' => $streak,
                    'achieved_at' => date('Y-m-d H:i:s')
                ]);
                
                $stmt = $pdo->prepare("
                    INSERT INTO user_achievements (user_id, achievement_type, achievement_data)
                    VALUES (?, 'streak_milestone', ?)
                ");
                $stmt->execute([$user_id, $achievement_data]);
                
                return [
                    'type' => 'streak_milestone',
                    'title' => "Chuỗi học tập $streak ngày!",
                    'description' => "Bạn đã học liên tiếp $streak ngày!",
                    'emoji' => $streak >= 100 ? '👑' : ($streak >= 30 ? '🏆' : '🔥')
                ];
            }
        } catch (Exception $e) {
            error_log("Achievement check error: " . $e->getMessage());
        }
    }
    
    return null;
}
?>
