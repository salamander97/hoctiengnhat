<?php
// File: debug-api.php
// Debug API đơn giản để kiểm tra kết nối

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Bắt tất cả errors và output dưới dạng JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);

function debugResponse($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

try {
    require_once 'php/config.php';

    $action = $_GET['action'] ?? 'status';

    switch ($action) {
        case 'status':
            debugResponse([
                'success' => true,
                'message' => 'API is working',
                'timestamp' => date('Y-m-d H:i:s'),
                'php_version' => PHP_VERSION,
                'server' => $_SERVER['SERVER_NAME'] ?? 'localhost'
            ]);
            break;

        case 'test_db':
            $db = Database::getInstance();
            
            // Test basic query
            $result = $db->fetchOne("SELECT NOW() as current_time");
            
            debugResponse([
                'success' => true,
                'message' => 'Database connection OK',
                'current_time' => $result['current_time'] ?? 'N/A'
            ]);
            break;

        case 'check_tables':
            $db = Database::getInstance();
            
            $tables = [
                'users' => "SELECT COUNT(*) as count FROM users",
                'vocabulary_categories' => "SELECT COUNT(*) as count FROM vocabulary_categories",
                'vocabulary_words' => "SELECT COUNT(*) as count FROM vocabulary_words",
                'user_category_progress' => "SELECT COUNT(*) as count FROM user_category_progress"
            ];
            
            $results = [];
            foreach ($tables as $table => $query) {
                try {
                    $count = $db->fetchOne($query)['count'] ?? 0;
                    $results[$table] = $count;
                } catch (Exception $e) {
                    $results[$table] = 'ERROR: ' . $e->getMessage();
                }
            }
            
            debugResponse([
                'success' => true,
                'message' => 'Table check completed',
                'tables' => $results
            ]);
            break;

        case 'check_user':
            session_start();
            
            if (!isset($_SESSION['user_id'])) {
                debugResponse([
                    'success' => false,
                    'message' => 'User not logged in',
                    'session' => $_SESSION
                ]);
            }
            
            $userId = $_SESSION['user_id'];
            $db = Database::getInstance();
            
            $user = $db->fetchOne(
                "SELECT id, username, display_name, is_active FROM users WHERE id = ?",
                [$userId]
            );
            
            debugResponse([
                'success' => true,
                'message' => 'User found',
                'user' => $user,
                'session_id' => session_id()
            ]);
            break;

        case 'create_sample':
            session_start();
            
            if (!isset($_SESSION['user_id'])) {
                debugResponse([
                    'success' => false,
                    'message' => 'User not logged in'
                ]);
            }
            
            $userId = $_SESSION['user_id'];
            $db = Database::getInstance();
            
            // Tạo category mẫu
            $categoryId = 1;
            $db->query(
                "INSERT INTO vocabulary_categories 
                 (id, category_name, category_name_en, category_icon, category_color, description, is_active, display_order)
                 VALUES (?, ?, ?, ?, ?, ?, TRUE, 1)
                 ON CONFLICT (id) DO UPDATE SET 
                    category_name = EXCLUDED.category_name,
                    is_active = TRUE",
                [$categoryId, 'Chào hỏi & Giao tiếp', 'greetings', '👋', '#FF6B6B', 'Từ vựng chào hỏi cơ bản']
            );
            
            // Tạo words mẫu
            $sampleWords = [
                ['こんにちは', '', 'konnichiwa', 'Xin chào'],
                ['ありがとう', '', 'arigatou', 'Cảm ơn'],
                ['すみません', '', 'sumimasen', 'Xin lỗi'],
                ['はじめまして', '', 'hajimemashite', 'Rất vui được gặp bạn'],
                ['おはよう', '', 'ohayou', 'Chào buổi sáng'],
                ['こんばんは', '', 'konbanwa', 'Chào buổi tối'],
                ['さようなら', '', 'sayounara', 'Tạm biệt'],
                ['げんき', '元気', 'genki', 'Khỏe mạnh'],
                ['なまえ', '名前', 'namae', 'Tên'],
                ['がくせい', '学生', 'gakusei', 'Học sinh']
            ];
            
            $wordCount = 0;
            foreach ($sampleWords as $i => $wordData) {
                try {
                    $db->query(
                        "INSERT INTO vocabulary_words 
                         (category_id, japanese_word, kanji, romaji, vietnamese_meaning, word_type, frequency_rank, display_order, is_active)
                         VALUES (?, ?, ?, ?, ?, 'expression', ?, ?, TRUE)
                         ON CONFLICT DO NOTHING",
                        [$categoryId, $wordData[0], $wordData[1], $wordData[2], $wordData[3], $i + 1, $i + 1]
                    );
                    $wordCount++;
                } catch (Exception $e) {
                    // Skip duplicates
                }
            }
            
            // Tạo user progress
            $db->query(
                "INSERT INTO user_category_progress 
                 (user_id, category_id, is_unlocked, created_at, updated_at)
                 VALUES (?, ?, TRUE, NOW(), NOW())
                 ON CONFLICT (user_id, category_id) 
                 DO UPDATE SET is_unlocked = TRUE, updated_at = NOW()",
                [$userId, $categoryId]
            );
            
            debugResponse([
                'success' => true,
                'message' => 'Sample data created',
                'category_id' => $categoryId,
                'words_created' => $wordCount,
                'user_id' => $userId
            ]);
            break;

        case 'test_quiz':
            session_start();
            
            if (!isset($_SESSION['user_id'])) {
                debugResponse([
                    'success' => false,
                    'message' => 'User not logged in'
                ]);
            }
            
            $userId = $_SESSION['user_id'];
            $categoryId = (int) ($_GET['category_id'] ?? 1);
            $db = Database::getInstance();
            
            // Test quiz query
            $words = $db->fetchAll(
                "SELECT vw.id, vw.japanese_word, vw.kanji, vw.romaji, vw.vietnamese_meaning, vw.word_type
                 FROM vocabulary_words vw
                 WHERE vw.category_id = ? AND vw.is_active = TRUE
                 LIMIT 5",
                [$categoryId]
            );
            
            $questions = [];
            foreach ($words as $word) {
                // Tạo câu hỏi đơn giản
                $allOptions = [
                    $word['vietnamese_meaning'],
                    'Tùy chọn A',
                    'Tùy chọn B', 
                    'Tùy chọn C'
                ];
                shuffle($allOptions);
                
                $correctIndex = array_search($word['vietnamese_meaning'], $allOptions);
                
                $questions[] = [
                    'id' => (int) $word['id'],
                    'type' => 'meaning',
                    'question' => $word['japanese_word'],
                    'correct_answer' => $correctIndex,
                    'options' => $allOptions,
                    'word_data' => [
                        'romaji' => $word['romaji'],
                        'word_type' => $word['word_type']
                    ]
                ];
            }
            
            debugResponse([
                'success' => true,
                'message' => 'Quiz test completed',
                'data' => [
                    'session_id' => 'test_' . time(),
                    'questions' => $questions,
                    'total_questions' => count($questions),
                    'type' => 'meaning',
                    'category_id' => $categoryId
                ]
            ]);
            break;

        default:
            debugResponse([
                'success' => false,
                'message' => 'Unknown action: ' . $action,
                'available_actions' => ['status', 'test_db', 'check_tables', 'check_user', 'create_sample', 'test_quiz']
            ]);
    }

} catch (Exception $e) {
    debugResponse([
        'success' => false,
        'message' => 'Exception: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
} catch (Error $e) {
    debugResponse([
        'success' => false,
        'message' => 'Fatal Error: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>
