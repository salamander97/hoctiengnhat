<?php
// php/vocabulary-api.php - API từ vựng (UPDATED VERSION)

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
    case 'get_categories':
        getCategories();
        break;
    case 'get_study_words':
        getStudyWords();
        break;
    case 'get_review_words':
        getReviewWords();
        break;
    case 'update_word_knowledge':
        updateWordKnowledge();
        break;
    case 'get_quiz_questions':
        getQuizQuestions();
        break;
    case 'save_quiz_result':
        saveQuizResult();
        break;
    // ✨ NEW ACTIONS
    case 'unlock_next_category':
        unlockNextCategory();
        break;
    case 'save_word_knowledge':
        saveWordKnowledge();
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'Action không hợp lệ']);
}

function getCategories() {
    try {
        $db = Database::getInstance();
        $userId = $_SESSION['user_id'];
        
        $categories = $db->fetchAll(
            "SELECT 
                vc.id,
                vc.category_name as name,
                vc.category_name_en as name_en,
                vc.category_icon as icon,
                vc.category_color as color,
                vc.description,
                vc.difficulty_level,
                vc.estimated_hours,
                vc.total_words,
                vc.display_order,
                vc.unlock_condition,
                vc.is_active,
                
                -- User progress data
                COALESCE(ucp.learned_words, 0) as learned_words,
                COALESCE(ucp.mastered_words, 0) as mastered_words,
                COALESCE(ucp.completion_percentage, 0) as completion_percentage,
                COALESCE(ucp.quiz_best_score, 0) as quiz_best_score,
                COALESCE(ucp.quiz_attempts, 0) as quiz_attempts,
                COALESCE(ucp.total_study_time, 0) as total_study_time,
                COALESCE(ucp.is_completed, false) as is_completed,
                COALESCE(ucp.is_unlocked, false) as is_unlocked,
                ucp.last_studied_at
                
            FROM vocabulary_categories vc
            LEFT JOIN user_category_progress ucp ON vc.id = ucp.category_id AND ucp.user_id = ?
            WHERE vc.is_active = true
            ORDER BY vc.display_order ASC",
            [$userId]
        );
        
        // Process categories để đảm bảo unlock logic đúng
        foreach ($categories as &$category) {
            // Convert booleans
            $category['is_completed'] = (bool)$category['is_completed'];
            $category['is_unlocked'] = (bool)$category['is_unlocked'];
            
            // Auto unlock first 2 categories nếu chưa có progress
            if (($category['display_order'] <= 2) && !$category['is_unlocked']) {
                $category['is_unlocked'] = true;
                
                // Save to database
                $unlockStmt = $db->prepare(
                    "INSERT INTO user_category_progress 
                     (user_id, category_id, is_unlocked, created_at, updated_at)
                     VALUES (?, ?, true, NOW(), NOW())
                     ON CONFLICT (user_id, category_id) 
                     DO UPDATE SET is_unlocked = true, updated_at = NOW()"
                );
                
                // Execute with error handling
                try {
                    $unlockStmt->execute([$userId, $category['id']]);
                    error_log("Auto-unlocked category {$category['id']} for user $userId");
                } catch (Exception $e) {
                    // For MySQL/MariaDB, use INSERT ... ON DUPLICATE KEY UPDATE
                    $db->query(
                        "INSERT INTO user_category_progress 
                         (user_id, category_id, is_unlocked, created_at, updated_at)
                         VALUES (?, ?, true, NOW(), NOW())
                         ON DUPLICATE KEY UPDATE is_unlocked = true, updated_at = NOW()",
                        [$userId, $category['id']]
                    );
                    error_log("Auto-unlocked category {$category['id']} for user $userId (MySQL fallback)");
                }
            }
            
            // Parse unlock_condition JSON
            if ($category['unlock_condition']) {
                $category['unlock_condition'] = json_decode($category['unlock_condition'], true);
            }
            
            // Convert numeric strings to proper types
            $category['id'] = (int)$category['id'];
            $category['difficulty_level'] = (int)$category['difficulty_level'];
            $category['total_words'] = (int)$category['total_words'];
            $category['learned_words'] = (int)$category['learned_words'];
            $category['mastered_words'] = (int)$category['mastered_words'];
            $category['completion_percentage'] = (float)$category['completion_percentage'];
            $category['quiz_best_score'] = (int)$category['quiz_best_score'];
            $category['quiz_attempts'] = (int)$category['quiz_attempts'];
            $category['total_study_time'] = (int)$category['total_study_time'];
            $category['display_order'] = (int)$category['display_order'];
            
            if ($category['estimated_hours']) {
                $category['estimated_hours'] = (float)$category['estimated_hours'];
            }
        }
        
        jsonResponse([
            'success' => true,
            'data' => $categories,
            'total' => count($categories)
        ]);
        
    } catch (Exception $e) {
        error_log("Get categories error: " . $e->getMessage());
        jsonResponse([
            'success' => false,
            'message' => 'Error getting categories: ' . $e->getMessage()
        ]);
    }
}

function getStudyWords() {
    try {
        $db = Database::getInstance();
        $userId = $_SESSION['user_id'];
        $categoryId = (int)($_GET['category_id'] ?? 0);
        
        if (!$categoryId) {
            jsonResponse(['success' => false, 'message' => 'Category ID không hợp lệ']);
        }
        
        // Get words from the category
        $words = $db->fetchAll(
            "SELECT 
                vw.id,
                vw.japanese_word,
                vw.kanji,
                vw.romaji,
                vw.vietnamese_meaning,
                vw.word_type,
                vw.example_sentence_jp,
                vw.example_sentence_vn,
                vw.usage_note,
                vw.frequency_rank,
                vw.audio_url,
                vw.image_url,
                vw.jlpt_level,
                vw.display_order,
                
                -- User knowledge data (for SRS)
                uwk.knowledge_level,
                uwk.ease_factor,
                uwk.interval_days,
                uwk.next_review_date,
                uwk.total_reviews,
                uwk.correct_reviews,
                uwk.difficulty_level as srs_difficulty
                
            FROM vocabulary_words vw
            LEFT JOIN user_word_knowledge uwk ON vw.id = uwk.word_id AND uwk.user_id = ?
            WHERE vw.category_id = ? AND vw.is_active = true
            ORDER BY vw.display_order ASC, vw.frequency_rank ASC",
            [$userId, $categoryId]
        );
        
        // Process words
        foreach ($words as &$word) {
            $word['id'] = (int)$word['id'];
            $word['frequency_rank'] = (int)($word['frequency_rank'] ?? 3);
            $word['display_order'] = (int)($word['display_order'] ?? 0);
            $word['knowledge_level'] = (int)($word['knowledge_level'] ?? 0);
            $word['ease_factor'] = (float)($word['ease_factor'] ?? 2.5);
            $word['interval_days'] = (int)($word['interval_days'] ?? 0);
            $word['total_reviews'] = (int)($word['total_reviews'] ?? 0);
            $word['correct_reviews'] = (int)($word['correct_reviews'] ?? 0);
        }
        
        jsonResponse([
            'success' => true,
            'data' => $words,
            'category_id' => $categoryId,
            'total' => count($words)
        ]);
        
    } catch (Exception $e) {
        error_log("Get study words error: " . $e->getMessage());
        jsonResponse([
            'success' => false,
            'message' => 'Error getting study words: ' . $e->getMessage()
        ]);
    }
}

function getReviewWords() {
    try {
        $db = Database::getInstance();
        $userId = $_SESSION['user_id'];
        
        // Get words that need review based on SRS algorithm
        $words = $db->fetchAll(
            "SELECT 
                vw.id,
                vw.japanese_word,
                vw.kanji,
                vw.romaji,
                vw.vietnamese_meaning,
                vw.example_sentence_jp,
                vw.example_sentence_vn,
                vw.jlpt_level,
                uwk.knowledge_level,
                uwk.ease_factor,
                uwk.next_review_date,
                uwk.difficulty_level
            FROM user_word_knowledge uwk
            JOIN vocabulary_words vw ON uwk.word_id = vw.id
            WHERE uwk.user_id = ? 
                AND uwk.next_review_date <= NOW()
                AND vw.is_active = true
            ORDER BY uwk.next_review_date ASC, uwk.knowledge_level ASC
            LIMIT 20",
            [$userId]
        );
        
        jsonResponse([
            'success' => true,
            'data' => $words,
            'total' => count($words)
        ]);
        
    } catch (Exception $e) {
        error_log("Get review words error: " . $e->getMessage());
        jsonResponse([
            'success' => false,
            'message' => 'Error getting review words: ' . $e->getMessage()
        ]);
    }
}

function updateWordKnowledge() {
    // Legacy function - keeping for compatibility
    saveWordKnowledge();
}

function getQuizQuestions() {
    try {
        $db = Database::getInstance();
        $userId = $_SESSION['user_id'];
        $categoryId = (int)($_GET['category_id'] ?? 0);
        $limit = (int)($_GET['limit'] ?? 10);
        
        if (!$categoryId) {
            jsonResponse(['success' => false, 'message' => 'Category ID không hợp lệ']);
        }
        
        // Get random words for quiz
        $words = $db->fetchAll(
            "SELECT 
                id,
                japanese_word,
                kanji,
                romaji,
                vietnamese_meaning,
                jlpt_level
            FROM vocabulary_words 
            WHERE category_id = ? AND is_active = true
            ORDER BY RANDOM()
            LIMIT ?",
            [$categoryId, $limit]
        );
        
        jsonResponse([
            'success' => true,
            'data' => $words,
            'total' => count($words)
        ]);
        
    } catch (Exception $e) {
        error_log("Get quiz questions error: " . $e->getMessage());
        jsonResponse([
            'success' => false,
            'message' => 'Error getting quiz questions: ' . $e->getMessage()
        ]);
    }
}

function saveQuizResult() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['success' => false, 'message' => 'Method không hợp lệ']);
    }
    
    try {
        $db = Database::getInstance();
        $userId = $_SESSION['user_id'];
        
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $totalQuestions = (int)($_POST['total_questions'] ?? 0);
        $correctAnswers = (int)($_POST['correct_answers'] ?? 0);
        $timeSpent = (int)($_POST['time_spent'] ?? 0);
        
        if (!$categoryId || !$totalQuestions) {
            jsonResponse(['success' => false, 'message' => 'Dữ liệu quiz không hợp lệ']);
        }
        
        // Save quiz session
        $db->query(
            "INSERT INTO quiz_sessions 
             (user_id, quiz_type, total_questions, correct_answers, time_spent, 
              started_at, completed_at, is_completed)
             VALUES (?, 'vocabulary', ?, ?, ?, NOW(), NOW(), true)",
            [$userId, $totalQuestions, $correctAnswers, $timeSpent]
        );
        
        // Update category progress with quiz results
        $score = round(($correctAnswers / $totalQuestions) * 100);
        
        $existing = $db->fetchOne(
            "SELECT quiz_best_score, quiz_attempts FROM user_category_progress 
             WHERE user_id = ? AND category_id = ?",
            [$userId, $categoryId]
        );
        
        if ($existing) {
            $newBestScore = max($existing['quiz_best_score'], $score);
            $newAttempts = $existing['quiz_attempts'] + 1;
            
            $db->query(
                "UPDATE user_category_progress 
                 SET quiz_best_score = ?, quiz_attempts = ?, updated_at = NOW()
                 WHERE user_id = ? AND category_id = ?",
                [$newBestScore, $newAttempts, $userId, $categoryId]
            );
        }
        
        // Log activity
        $db->query(
            "INSERT INTO user_activities 
             (user_id, activity_type, score, total_questions, time_spent, created_at)
             VALUES (?, 'vocabulary_quiz', ?, ?, ?, NOW())",
            [$userId, $correctAnswers, $totalQuestions, $timeSpent]
        );
        
        jsonResponse([
            'success' => true,
            'message' => 'Quiz result saved successfully',
            'score' => $score,
            'percentage' => $score
        ]);
        
    } catch (Exception $e) {
        error_log("Save quiz result error: " . $e->getMessage());
        jsonResponse([
            'success' => false,
            'message' => 'Error saving quiz result: ' . $e->getMessage()
        ]);
    }
}

// ✨ NEW FUNCTIONS

function unlockNextCategory() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['success' => false, 'message' => 'Method không hợp lệ']);
    }
    
    try {
        $db = Database::getInstance();
        $userId = $_SESSION['user_id'];
        
        $completedCategoryId = (int)($_POST['completed_category_id'] ?? 0);
        
        if (!$completedCategoryId || !$userId) {
            jsonResponse([
                'success' => false,
                'message' => 'Missing completed_category_id or user_id'
            ]);
            return;
        }
        
        $unlockedCategory = unlockNextCategoryLogic($db, $userId, $completedCategoryId);
        
        if ($unlockedCategory) {
            jsonResponse([
                'success' => true,
                'message' => 'Next category unlocked successfully',
                'unlocked_category' => $unlockedCategory
            ]);
        } else {
            jsonResponse([
                'success' => true,
                'message' => 'No more categories to unlock or conditions not met'
            ]);
        }
        
    } catch (Exception $e) {
        error_log("Unlock next category error: " . $e->getMessage());
        jsonResponse([
            'success' => false,
            'message' => 'Error unlocking category: ' . $e->getMessage()
        ]);
    }
}

function saveWordKnowledge() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['success' => false, 'message' => 'Method không hợp lệ']);
    }
    
    try {
        $db = Database::getInstance();
        $userId = $_SESSION['user_id'];
        
        $wordId = (int)($_POST['word_id'] ?? 0);
        $knowledgeLevel = (int)($_POST['knowledge_level'] ?? 0);
        $easeFactor = (float)($_POST['ease_factor'] ?? 2.5);
        $intervalDays = (int)($_POST['interval_days'] ?? 0);
        $nextReviewDate = $_POST['next_review_date'] ?? '';
        $difficulty = $_POST['difficulty'] ?? 'normal';
        
        if (!$wordId || !$userId) {
            jsonResponse([
                'success' => false,
                'message' => 'Missing word_id or user_id'
            ]);
            return;
        }
        
        // Validate next_review_date format
        if (empty($nextReviewDate)) {
            $nextReviewDate = date('Y-m-d H:i:s');
        }
        
        error_log("Save word knowledge: wordId=$wordId, level=$knowledgeLevel, ease=$easeFactor, interval=$intervalDays, difficulty=$difficulty");
        
        // Check if record exists
        $existing = $db->fetchOne(
            "SELECT id, total_reviews, correct_reviews, incorrect_reviews, consecutive_correct 
             FROM user_word_knowledge 
             WHERE user_id = ? AND word_id = ?",
            [$userId, $wordId]
        );
        
        if ($existing) {
            // Update existing record
            $newTotalReviews = $existing['total_reviews'] + 1;
            $newCorrectReviews = $existing['correct_reviews'];
            $newIncorrectReviews = $existing['incorrect_reviews'];
            $newConsecutiveCorrect = $existing['consecutive_correct'];
            
            // Update counts based on difficulty
            if ($difficulty === 'easy' || $difficulty === 'normal') {
                $newCorrectReviews++;
                $newConsecutiveCorrect++;
            } else { // hard
                $newIncorrectReviews++;
                $newConsecutiveCorrect = 0;
            }
            
            // Determine difficulty level based on knowledge level
            $difficultyLevel = 'new';
            if ($knowledgeLevel >= 7) {
                $difficultyLevel = 'mature';
            } elseif ($knowledgeLevel >= 1) {
                $difficultyLevel = 'learning';
            }
            
            $db->query(
                "UPDATE user_word_knowledge 
                 SET knowledge_level = ?, ease_factor = ?, interval_days = ?, 
                     next_review_date = ?, total_reviews = ?, correct_reviews = ?,
                     incorrect_reviews = ?, consecutive_correct = ?,
                     difficulty_level = ?, last_reviewed_at = NOW(), updated_at = NOW()
                 WHERE user_id = ? AND word_id = ?",
                [$knowledgeLevel, $easeFactor, $intervalDays, $nextReviewDate,
                 $newTotalReviews, $newCorrectReviews, $newIncorrectReviews, 
                 $newConsecutiveCorrect, $difficultyLevel, $userId, $wordId]
            );
            
        } else {
            // Insert new record
            $totalReviews = 1;
            $correctReviews = ($difficulty === 'easy' || $difficulty === 'normal') ? 1 : 0;
            $incorrectReviews = ($difficulty === 'hard') ? 1 : 0;
            $consecutiveCorrect = ($difficulty === 'hard') ? 0 : 1;
            
            $difficultyLevel = 'new';
            if ($knowledgeLevel >= 7) {
                $difficultyLevel = 'mature';
            } elseif ($knowledgeLevel >= 1) {
                $difficultyLevel = 'learning';
            }
            
            $db->query(
                "INSERT INTO user_word_knowledge 
                 (user_id, word_id, knowledge_level, ease_factor, interval_days, 
                  next_review_date, total_reviews, correct_reviews, incorrect_reviews,
                  consecutive_correct, difficulty_level, first_learned_at, 
                  last_reviewed_at, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW(), NOW())",
                [$userId, $wordId, $knowledgeLevel, $easeFactor, $intervalDays,
                 $nextReviewDate, $totalReviews, $correctReviews, $incorrectReviews,
                 $consecutiveCorrect, $difficultyLevel]
            );
        }
        
        jsonResponse([
            'success' => true,
            'message' => 'Word knowledge saved successfully',
            'data' => [
                'word_id' => $wordId,
                'knowledge_level' => $knowledgeLevel,
                'next_review_date' => $nextReviewDate
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Save word knowledge error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        jsonResponse([
            'success' => false,
            'message' => 'Error saving word knowledge: ' . $e->getMessage()
        ]);
    }
}

// Helper function for unlocking logic (shared with user-progress.php)
function unlockNextCategoryLogic($db, $userId, $completedCategoryId) {
    try {
        // Get completed category info
        $completedCategory = $db->fetchOne(
            "SELECT display_order, difficulty_level, category_name 
             FROM vocabulary_categories 
             WHERE id = ?",
            [$completedCategoryId]
        );
        
        if (!$completedCategory) {
            error_log("Completed category not found: $completedCategoryId");
            return null;
        }
        
        // Find next category to unlock
        $nextCategory = $db->fetchOne(
            "SELECT vc.id, vc.category_name, vc.unlock_condition, vc.display_order
             FROM vocabulary_categories vc
             LEFT JOIN user_category_progress ucp ON vc.id = ucp.category_id AND ucp.user_id = ?
             WHERE vc.is_active = true 
             AND (ucp.is_unlocked IS NULL OR ucp.is_unlocked = false)
             AND vc.display_order > ?
             ORDER BY vc.display_order ASC
             LIMIT 1",
            [$userId, $completedCategory['display_order']]
        );
        
        if (!$nextCategory) {
            return null;
        }
        
        // Check unlock conditions
        $unlockCondition = $nextCategory['unlock_condition'] ? 
            json_decode($nextCategory['unlock_condition'], true) : [];
        
        $canUnlock = true;
        
        // Check required categories
        if (!empty($unlockCondition['required_categories'])) {
            foreach ($unlockCondition['required_categories'] as $requiredCatId) {
                $requiredCat = $db->fetchOne(
                    "SELECT is_completed 
                     FROM user_category_progress 
                     WHERE user_id = ? AND category_id = ?",
                    [$userId, $requiredCatId]
                );
                
                if (!$requiredCat || !$requiredCat['is_completed']) {
                    $canUnlock = false;
                    break;
                }
            }
        }
        
        // Check minimum completion percentage
        if ($canUnlock && !empty($unlockCondition['min_completion'])) {
            $avgCompletion = $db->fetchOne(
                "SELECT AVG(completion_percentage) as avg_completion
                 FROM user_category_progress 
                 WHERE user_id = ? AND is_completed = true",
                [$userId]
            );
            
            $avgCompletionValue = $avgCompletion['avg_completion'] ?? 0;
            if ($avgCompletionValue < $unlockCondition['min_completion']) {
                $canUnlock = false;
            }
        }
        
        // Unlock category if conditions are met
        if ($canUnlock) {
            // Check if record exists
            $existingProgress = $db->fetchOne(
                "SELECT id FROM user_category_progress WHERE user_id = ? AND category_id = ?",
                [$userId, $nextCategory['id']]
            );
            
            if ($existingProgress) {
                // Update existing record
                $db->query(
                    "UPDATE user_category_progress 
                     SET is_unlocked = true, updated_at = NOW()
                     WHERE user_id = ? AND category_id = ?",
                    [$userId, $nextCategory['id']]
                );
            } else {
                // Insert new record
                $db->query(
                    "INSERT INTO user_category_progress 
                     (user_id, category_id, is_unlocked, created_at, updated_at)
                     VALUES (?, ?, true, NOW(), NOW())",
                    [$userId, $nextCategory['id']]
                );
            }
            
            return $nextCategory['category_name'];
        }
        
        return null;
        
    } catch (Exception $e) {
        error_log("Error unlocking next category: " . $e->getMessage());
        return null;
    }
}
?>
