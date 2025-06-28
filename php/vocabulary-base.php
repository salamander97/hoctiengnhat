<?php
// php/vocabulary-base.php - Base class cho hệ thống từ vựng N5

require_once 'config.php';

class VocabularyManager {
    protected $db;
    protected $userId;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->userId = null;
    }
    
    /**
     * Kiểm tra user đã đăng nhập chưa
     * @return int User ID
     */
    protected function requireAuth() {
        if (!isLoggedIn()) {
            jsonResponse(['success' => false, 'message' => 'Vui lòng đăng nhập']);
        }
        $this->userId = $_SESSION['user_id'];
        return $this->userId;
    }
    
    /**
     * Khởi tạo dữ liệu vocabulary cho user mới
     * @param int $userId
     * @return bool
     */
    public function initializeUserVocabulary($userId) {
        try {
            // Lấy tất cả categories
            $categories = $this->db->fetchAll(
                "SELECT id, unlock_condition FROM vocabulary_categories ORDER BY display_order"
            );
            
            foreach ($categories as $category) {
                // Kiểm tra xem user đã có progress chưa
                $existing = $this->db->fetchOne(
                    "SELECT id FROM user_category_progress WHERE user_id = ? AND category_id = ?",
                    [$userId, $category['id']]
                );
                
                if (!$existing) {
                    $unlockCondition = json_decode($category['unlock_condition'], true);
                    $isUnlocked = $this->checkCategoryUnlock($userId, $category['id'], $unlockCondition);
                    
                    // Tạo progress record
                    $this->db->query(
                        "INSERT INTO user_category_progress (user_id, category_id, is_unlocked, created_at) 
                         VALUES (?, ?, ?, NOW())",
                        [$userId, $category['id'], $isUnlocked]
                    );
                }
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Initialize user vocabulary error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Kiểm tra điều kiện mở khóa category
     * @param int $userId
     * @param int $categoryId
     * @param array $unlockCondition
     * @return bool
     */
    public function checkCategoryUnlock($userId, $categoryId, $unlockCondition = null) {
        try {
            // Nếu không có unlock condition, mặc định là mở
            if (!$unlockCondition) {
                $category = $this->db->fetchOne(
                    "SELECT unlock_condition FROM vocabulary_categories WHERE id = ?",
                    [$categoryId]
                );
                $unlockCondition = json_decode($category['unlock_condition'] ?? '{}', true);
            }
            
            // Nếu không có required categories, luôn mở
            if (empty($unlockCondition['required_categories'])) {
                return true;
            }
            
            $requiredCategories = $unlockCondition['required_categories'];
            $minCompletion = $unlockCondition['min_completion'] ?? 70;
            
            // Kiểm tra tất cả required categories
            foreach ($requiredCategories as $requiredCategoryId) {
                $progress = $this->db->fetchOne(
                    "SELECT completion_percentage FROM user_category_progress 
                     WHERE user_id = ? AND category_id = ?",
                    [$userId, $requiredCategoryId]
                );
                
                if (!$progress || $progress['completion_percentage'] < $minCompletion) {
                    return false;
                }
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Check category unlock error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Cập nhật unlock status cho các categories
     * @param int $userId
     */
    public function updateCategoryUnlocks($userId) {
        try {
            $categories = $this->db->fetchAll(
                "SELECT id, unlock_condition FROM vocabulary_categories ORDER BY display_order"
            );
            
            foreach ($categories as $category) {
                $unlockCondition = json_decode($category['unlock_condition'], true);
                $shouldUnlock = $this->checkCategoryUnlock($userId, $category['id'], $unlockCondition);
                
                // Cập nhật unlock status
                $this->db->query(
                    "UPDATE user_category_progress 
                     SET is_unlocked = ? 
                     WHERE user_id = ? AND category_id = ? AND is_unlocked = false",
                    [$shouldUnlock, $userId, $category['id']]
                );
            }
        } catch (Exception $e) {
            error_log("Update category unlocks error: " . $e->getMessage());
        }
    }
    
    /**
     * Tính toán phần trăm hoàn thành
     * @param int $completed
     * @param int $total
     * @return float
     */
    protected function calculateCompletion($completed, $total) {
        if ($total <= 0) return 0.0;
        return round(($completed / $total) * 100, 2);
    }
    
    /**
     * Cập nhật tiến độ category
     * @param int $userId
     * @param int $categoryId
     */
 public function updateCategoryProgress($userId, $categoryId) {
    try {
        error_log("🔄 [FIX] Updating progress for user $userId, category $categoryId");
        
        // 1. Lấy tổng số từ trong category (active words only)
        $totalWordsResult = $this->db->fetchOne(
            "SELECT COUNT(*) as total FROM vocabulary_words WHERE category_id = ? AND is_active = true",
            [$categoryId]
        );
        $totalWords = $totalWordsResult['total'] ?? 0;
        
        if ($totalWords == 0) {
            error_log("❌ No active words found for category: $categoryId");
            return false;
        }
        
        error_log("📊 Total active words in category $categoryId: $totalWords");

        // 2. Đếm số từ đã học (knowledge_level >= 1) - FIX QUERY
        $learnedWordsResult = $this->db->fetchOne(
            "SELECT COUNT(*) as learned 
             FROM user_word_knowledge uwk
             INNER JOIN vocabulary_words vw ON uwk.word_id = vw.id
             WHERE uwk.user_id = ? 
               AND vw.category_id = ? 
               AND vw.is_active = true 
               AND uwk.knowledge_level >= 1",
            [$userId, $categoryId]
        );
        $learnedWords = $learnedWordsResult['learned'] ?? 0;
        
        // 3. Đếm số từ đã thành thạo (knowledge_level >= 4) - FIX QUERY
        $masteredWordsResult = $this->db->fetchOne(
            "SELECT COUNT(*) as mastered 
             FROM user_word_knowledge uwk
             INNER JOIN vocabulary_words vw ON uwk.word_id = vw.id
             WHERE uwk.user_id = ? 
               AND vw.category_id = ? 
               AND vw.is_active = true 
               AND uwk.knowledge_level >= 4",
            [$userId, $categoryId]
        );
        $masteredWords = $masteredWordsResult['mastered'] ?? 0;
        
        error_log("📈 [FIX] Progress stats: learned=$learnedWords, mastered=$masteredWords of $totalWords total");
        
        // 4. Tính completion percentage dựa trên learned words
        $completionPercentage = $totalWords > 0 ? round(($learnedWords / $totalWords) * 100, 2) : 0.0;
        $isCompleted = $completionPercentage >= 80; // 80% learned = completed
        
        error_log("📊 [FIX] Completion: $completionPercentage% (completed: " . ($isCompleted ? 'true' : 'false') . ")");
        
        // 5. Cập nhật hoặc tạo progress record
        $existingProgress = $this->db->fetchOne(
            "SELECT id, completion_percentage FROM user_category_progress WHERE user_id = ? AND category_id = ?",
            [$userId, $categoryId]
        );
        
        if (!$existingProgress) {
            // Tạo record mới
            error_log("🆕 [FIX] Creating new progress record");
            $this->db->query(
                "INSERT INTO user_category_progress 
                 (user_id, category_id, total_words, learned_words, mastered_words, 
                  completion_percentage, is_completed, is_unlocked, last_studied_at, created_at, updated_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, true, NOW(), NOW(), NOW())",
                [$userId, $categoryId, $totalWords, $learnedWords, $masteredWords, 
                 $completionPercentage, $isCompleted]
            );
        } else {
            // Update existing record
            error_log("🔄 [FIX] Updating existing progress. Old: {$existingProgress['completion_percentage']}% -> New: $completionPercentage%");
            $this->db->query(
                "UPDATE user_category_progress 
                 SET total_words = ?, learned_words = ?, mastered_words = ?, 
                     completion_percentage = ?, is_completed = ?, last_studied_at = NOW(), updated_at = NOW()
                 WHERE user_id = ? AND category_id = ?",
                [$totalWords, $learnedWords, $masteredWords, $completionPercentage, 
                 $isCompleted, $userId, $categoryId]
            );
        }
        
        // 6. Verify kết quả sau khi update
        $verifyProgress = $this->db->fetchOne(
            "SELECT completion_percentage, learned_words, mastered_words, updated_at 
             FROM user_category_progress WHERE user_id = ? AND category_id = ?",
            [$userId, $categoryId]
        );
        error_log("✅ [FIX] Verified progress: " . json_encode($verifyProgress));
        
        // 7. Update category unlocks và overall progress
        $this->updateCategoryUnlocks($userId);
        $this->updateOverallVocabularyProgress($userId);
        
        return true;
        
    } catch (Exception $e) {
        error_log("❌ [FIX] Update category progress error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        return false;
    }
}
    
    /**
     * Cập nhật tổng tiến độ vocabulary
     * @param int $userId
     */
    private function updateOverallVocabularyProgress($userId) {
        try {
            // Đếm categories đã unlock
            $unlockedCategories = $this->db->fetchOne(
                "SELECT COUNT(*) as unlocked FROM user_category_progress 
                 WHERE user_id = ? AND is_unlocked = true",
                [$userId]
            )['unlocked'] ?? 0;
            
            // Tổng từ đã học
            $totalLearned = $this->db->fetchOne(
                "SELECT COUNT(*) as total 
                 FROM user_word_knowledge uwk
                 JOIN vocabulary_words vw ON uwk.word_id = vw.id
                 WHERE uwk.user_id = ? AND uwk.knowledge_level >= 1",
                [$userId]
            )['total'] ?? 0;
            
            // Tổng từ đã thành thạo
            $totalMastered = $this->db->fetchOne(
                "SELECT COUNT(*) as total 
                 FROM user_word_knowledge uwk
                 JOIN vocabulary_words vw ON uwk.word_id = vw.id
                 WHERE uwk.user_id = ? AND uwk.knowledge_level >= 4",
                [$userId]
            )['total'] ?? 0;
            
            // Tổng điểm từ quiz
            $totalScore = $this->db->fetchOne(
                "SELECT COALESCE(SUM(score), 0) as total_score 
                 FROM vocabulary_quiz_sessions 
                 WHERE user_id = ? AND is_completed = true",
                [$userId]
            )['total_score'] ?? 0;
            
            // Tổng thời gian học
            $totalStudyTime = $this->db->fetchOne(
                "SELECT COALESCE(SUM(total_study_time), 0) as total_time 
                 FROM user_category_progress 
                 WHERE user_id = ?",
                [$userId]
            )['total_time'] ?? 0;
            
            // Cập nhật user_progress
            $this->db->query(
                "UPDATE user_progress 
                 SET vocabulary_categories_unlocked = ?, vocabulary_words_learned = ?, 
                     vocabulary_words_mastered = ?, vocabulary_total_score = ?, 
                     vocabulary_study_time = ?, last_updated = NOW()
                 WHERE user_id = ?",
                [$unlockedCategories, $totalLearned, $totalMastered, $totalScore, $totalStudyTime, $userId]
            );
            
        } catch (Exception $e) {
            error_log("Update overall vocabulary progress error: " . $e->getMessage());
        }
    }
    
    /**
     * Cập nhật kiến thức từ vựng với Spaced Repetition
     * @param int $userId
     * @param int $wordId
     * @param bool $isCorrect
     * @param int $difficultyRating (1-5, user tự đánh giá)
     * @return bool
     */
    public function updateWordKnowledge($userId, $wordId, $isCorrect, $difficultyRating = 3) {
        try {
            // Lấy knowledge hiện tại
            $current = $this->db->fetchOne(
                "SELECT * FROM user_word_knowledge WHERE user_id = ? AND word_id = ?",
                [$userId, $wordId]
            );
            
            if (!$current) {
                // Tạo record mới
                $this->db->query(
                    "INSERT INTO user_word_knowledge 
                     (user_id, word_id, knowledge_level, correct_count, wrong_count, 
                      difficulty_rating, last_reviewed_at, next_review_at, created_at, updated_at) 
                     VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, NOW(), NOW())",
                    [
                        $userId, $wordId, 
                        $isCorrect ? 1 : 0,
                        $isCorrect ? 1 : 0,
                        $isCorrect ? 0 : 1,
                        $difficultyRating,
                        $this->calculateNextReview(1, 2.5, $isCorrect, $difficultyRating)
                    ]
                );
            } else {
                // Cập nhật record hiện tại
                $newCorrectCount = $current['correct_count'] + ($isCorrect ? 1 : 0);
                $newWrongCount = $current['wrong_count'] + ($isCorrect ? 0 : 1);
                
                // Tính knowledge level mới
                $newKnowledgeLevel = $this->calculateKnowledgeLevel(
                    $current['knowledge_level'], 
                    $isCorrect, 
                    $newCorrectCount, 
                    $newWrongCount
                );
                
                // Tính ease factor và interval mới (SM-2 algorithm)
                $newEaseFactor = $this->calculateEaseFactor($current['ease_factor'], $difficultyRating);
                $newInterval = $this->calculateInterval($current['interval_days'], $newEaseFactor, $isCorrect);
                $nextReview = $this->calculateNextReview($newInterval, $newEaseFactor, $isCorrect, $difficultyRating);
                
                $this->db->query(
                    "UPDATE user_word_knowledge 
                     SET knowledge_level = ?, correct_count = ?, wrong_count = ?, 
                         ease_factor = ?, interval_days = ?, difficulty_rating = ?,
                         last_reviewed_at = NOW(), next_review_at = ?, updated_at = NOW()
                     WHERE user_id = ? AND word_id = ?",
                    [
                        $newKnowledgeLevel, $newCorrectCount, $newWrongCount,
                        $newEaseFactor, $newInterval, $difficultyRating,
                        $nextReview, $userId, $wordId
                    ]
                );
            }
            
            // Cập nhật tiến độ category
            $categoryId = $this->db->fetchOne(
                "SELECT category_id FROM vocabulary_words WHERE id = ?",
                [$wordId]
            )['category_id'];
            
            $this->updateCategoryProgress($userId, $categoryId);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Update word knowledge error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Tính knowledge level dựa trên performance
     * @param int $currentLevel
     * @param bool $isCorrect
     * @param int $correctCount
     * @param int $wrongCount
     * @return int
     */
    private function calculateKnowledgeLevel($currentLevel, $isCorrect, $correctCount, $wrongCount) {
        $totalAttempts = $correctCount + $wrongCount;
        $accuracy = $totalAttempts > 0 ? ($correctCount / $totalAttempts) : 0;
        
        if ($isCorrect) {
            // Tăng level nếu trả lời đúng
            if ($currentLevel < 5) {
                if ($correctCount >= 3 && $accuracy >= 0.8) {
                    return min(5, $currentLevel + 1);
                }
            }
            return max(1, $currentLevel); // Ít nhất level 1 nếu đã trả lời đúng
        } else {
            // Giảm level nếu trả lời sai
            if ($currentLevel > 0 && $accuracy < 0.5) {
                return max(0, $currentLevel - 1);
            }
            return $currentLevel;
        }
    }
    
    /**
     * Tính ease factor (SM-2 algorithm)
     * @param float $currentEase
     * @param int $difficulty (1=easy, 5=hard)
     * @return float
     */
    private function calculateEaseFactor($currentEase, $difficulty) {
        // Convert difficulty rating to quality (SM-2)
        $quality = 6 - $difficulty; // 1->5, 2->4, 3->3, 4->2, 5->1
        
        $newEase = $currentEase + (0.1 - (5 - $quality) * (0.08 + (5 - $quality) * 0.02));
        
        return max(1.3, $newEase); // Minimum ease factor
    }
    
    /**
     * Tính interval ngày cho lần review tiếp theo
     * @param int $currentInterval
     * @param float $easeFactor
     * @param bool $isCorrect
     * @return int
     */
    private function calculateInterval($currentInterval, $easeFactor, $isCorrect) {
        if (!$isCorrect) {
            return 1; // Reset về 1 ngày nếu sai
        }
        
        if ($currentInterval == 1) {
            return 6; // Lần đầu đúng: 6 ngày
        } else {
            return max(1, round($currentInterval * $easeFactor));
        }
    }
    
    /**
     * Tính ngày review tiếp theo
     * @param int $intervalDays
     * @param float $easeFactor
     * @param bool $isCorrect
     * @param int $difficulty
     * @return string
     */
    private function calculateNextReview($intervalDays, $easeFactor, $isCorrect, $difficulty) {
        $now = new DateTime();
        
        if (!$isCorrect || $difficulty >= 4) {
            // Nếu sai hoặc khó, review sớm hơn
            $now->add(new DateInterval('P1D'));
        } else {
            $now->add(new DateInterval("P{$intervalDays}D"));
        }
        
        return $now->format('Y-m-d H:i:s');
    }
    
    /**
     * Lấy từ cần ôn tập dựa trên spaced repetition
     * @param int $userId
     * @param int $limit
     * @return array
     */
    public function getWordsForReview($userId, $limit = 20) {
        try {
            return $this->db->fetchAll(
                "SELECT vw.*, uwk.knowledge_level, uwk.next_review_at, uwk.difficulty_rating,
                        vc.category_name, vc.category_icon
                 FROM user_word_knowledge uwk
                 JOIN vocabulary_words vw ON uwk.word_id = vw.id
                 JOIN vocabulary_categories vc ON vw.category_id = vc.id
                 WHERE uwk.user_id = ? 
                   AND uwk.next_review_at <= NOW()
                   AND vw.is_active = true
                 ORDER BY uwk.next_review_at ASC, uwk.knowledge_level ASC
                 LIMIT ?",
                [$userId, $limit]
            );
        } catch (Exception $e) {
            error_log("Get words for review error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Lấy thống kê tổng quan vocabulary của user
     * @param int $userId
     * @return array
     */
    public function getVocabularyStats($userId) {
        try {
            // Thống kê cơ bản
            $stats = $this->db->fetchOne(
                "SELECT 
                    vocabulary_categories_unlocked,
                    vocabulary_words_learned,
                    vocabulary_words_mastered,
                    vocabulary_total_score,
                    vocabulary_study_time
                 FROM user_progress WHERE user_id = ?",
                [$userId]
            );
            
            if (!$stats) {
                return $this->getDefaultStats();
            }
            
            // Thống kê chi tiết theo category
            $categoryStats = $this->db->fetchAll(
                "SELECT vc.category_name, vc.category_icon, vc.category_color,
                        ucp.completion_percentage, ucp.learned_words, ucp.mastered_words,
                        ucp.quiz_best_score, ucp.is_completed, ucp.is_unlocked,
                        ucp.total_study_time
                 FROM user_category_progress ucp
                 JOIN vocabulary_categories vc ON ucp.category_id = vc.id
                 WHERE ucp.user_id = ?
                 ORDER BY vc.display_order",
                [$userId]
            );
            
            // Từ cần ôn tập
            $reviewCount = $this->db->fetchOne(
                "SELECT COUNT(*) as count 
                 FROM user_word_knowledge uwk
                 JOIN vocabulary_words vw ON uwk.word_id = vw.id
                 WHERE uwk.user_id = ? AND uwk.next_review_at <= NOW()",
                [$userId]
            )['count'] ?? 0;
            
            return [
                'overall' => $stats,
                'categories' => $categoryStats,
                'review_count' => $reviewCount,
                'total_categories' => count($categoryStats)
            ];
            
        } catch (Exception $e) {
            error_log("Get vocabulary stats error: " . $e->getMessage());
            return $this->getDefaultStats();
        }
    }
    
    /**
     * Default stats cho user mới
     * @return array
     */
    private function getDefaultStats() {
        return [
            'overall' => [
                'vocabulary_categories_unlocked' => 2,
                'vocabulary_words_learned' => 0,
                'vocabulary_words_mastered' => 0,
                'vocabulary_total_score' => 0,
                'vocabulary_study_time' => 0
            ],
            'categories' => [],
            'review_count' => 0,
            'total_categories' => 0
        ];
    }
    
    /**
     * Log activity để tracking
     * @param int $userId
     * @param string $activityType
     * @param array $data
     */
    public function logActivity($userId, $activityType, $data = []) {
        try {
            $this->db->query(
                "INSERT INTO user_activities (user_id, activity_type, score, total_questions, created_at)
                 VALUES (?, ?, ?, ?, NOW())",
                [
                    $userId,
                    'vocabulary_' . $activityType,
                    $data['score'] ?? 0,
                    $data['total_questions'] ?? 0
                ]
            );
        } catch (Exception $e) {
            error_log("Log vocabulary activity error: " . $e->getMessage());
        }
    }
    
    /**
     * Lấy leaderboard vocabulary
     * @param int $limit
     * @return array
     */
    public function getLeaderboard($limit = 10) {
        try {
            return $this->db->fetchAll(
                "SELECT u.display_name, u.username,
                        up.vocabulary_words_mastered,
                        up.vocabulary_total_score,
                        up.vocabulary_categories_unlocked
                 FROM user_progress up
                 JOIN users u ON up.user_id = u.id
                 WHERE u.is_active = true
                 ORDER BY up.vocabulary_words_mastered DESC, up.vocabulary_total_score DESC
                 LIMIT ?",
                [$limit]
            );
        } catch (Exception $e) {
            error_log("Get vocabulary leaderboard error: " . $e->getMessage());
            return [];
        }
    }
}
?>

