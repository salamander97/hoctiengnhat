<?php
// php/vocabulary-api.php - API endpoints cho hệ thống từ vựng N5

require_once 'vocabulary-base.php';

class VocabularyAPI extends VocabularyManager
{

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Handle tất cả requests
     */
    public function handleRequest()
    {
        $action = $_GET['action'] ?? $_POST['action'] ?? '';

        try {
            switch ($action) {
                case 'get_categories':
                    $this->getCategories();
                    break;

                case 'get_study_words':
                    $this->getStudyWords();
                    break;

                case 'get_category_words':
                    $this->getCategoryWords();
                    break;

                case 'get_user_progress':
                    $this->getUserProgress();
                    break;

                case 'update_word_knowledge':
                    $this->updateWordKnowledgeAPI();
                    break;

                case 'get_quiz_questions':
                    $this->getQuizQuestions();
                    break;

                case 'save_quiz_result':
                    $this->saveQuizResult();
                    break;

                case 'get_review_words':
                    $this->getReviewWordsAPI();
                    break;

                case 'get_study_session':
                    $this->getStudySession();
                    break;

                case 'save_study_time':
                    $this->saveStudyTime();
                    break;

                case 'get_vocabulary_stats':
                    $this->getVocabularyStatsAPI();
                    break;

                case 'get_leaderboard':
                    $this->getLeaderboardAPI();
                    break;

                case 'initialize_user':
                    $this->initializeUserAPI();
                    break;

                case 'get_category_detail':
                    $this->getCategoryDetail();
                    break;

                case 'search_words':
                    $this->searchWords();
                    break;

                default:
                    jsonResponse(['success' => false, 'message' => 'Action không hợp lệ: ' . $action]);
            }
        } catch (Exception $e) {
            error_log("Vocabulary API Error: " . $e->getMessage());
            jsonResponse(['success' => false, 'message' => 'Có lỗi xảy ra, vui lòng thử lại']);
        }
    }

    /**
     * API: Lấy danh sách categories với progress của user
     * GET /vocabulary-api.php?action=get_categories
     */
    private function getCategories()
    {
        $userId = $this->requireAuth();

        try {
            // Khởi tạo vocabulary cho user nếu chưa có
            $this->initializeUserVocabulary($userId);

            $categories = $this->db->fetchAll(
                "SELECT vc.*, 
                        COALESCE(ucp.total_words, 0) as total_words,
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
                 ORDER BY vc.display_order",
                [$userId]
            );

            // Format dữ liệu output
            $formattedCategories = [];
            foreach ($categories as $category) {
                $formattedCategories[] = [
                    'id' => (int) $category['id'],
                    'name' => $category['category_name'],
                    'name_en' => $category['category_name_en'],
                    'icon' => $category['category_icon'],
                    'color' => $category['category_color'],
                    'description' => $category['description'],
                    'difficulty_level' => (int) $category['difficulty_level'],
                    'estimated_hours' => (float) $category['estimated_hours'],
                    'total_words' => (int) $category['total_words'],
                    'learned_words' => (int) $category['learned_words'],
                    'mastered_words' => (int) $category['mastered_words'],
                    'completion_percentage' => (float) $category['completion_percentage'],
                    'quiz_best_score' => (int) $category['quiz_best_score'],
                    'quiz_attempts' => (int) $category['quiz_attempts'],
                    'total_study_time' => (int) $category['total_study_time'],
                    'is_completed' => (bool) $category['is_completed'],
                    'is_unlocked' => (bool) $category['is_unlocked'],
                    'last_studied_at' => $category['last_studied_at'],
                    'unlock_condition' => json_decode($category['unlock_condition'], true)
                ];
            }

            jsonResponse([
                'success' => true,
                'data' => $formattedCategories
            ]);

        } catch (Exception $e) {
            error_log("Get categories error: " . $e->getMessage());
            jsonResponse(['success' => false, 'message' => 'Không thể lấy danh sách chủ đề']);
        }
    }

    /**
     * API: Lấy từ vựng theo category
     * GET /vocabulary-api.php?action=get_category_words&category_id=1&mode=study
     */
    private function getCategoryWords()
    {
        $userId = $this->requireAuth();
        $categoryId = (int) ($_GET['category_id'] ?? 0);
        $mode = sanitizeInput($_GET['mode'] ?? 'all'); // all, study, review, new
        $limit = (int) ($_GET['limit'] ?? 0);

        if ($categoryId <= 0) {
            jsonResponse(['success' => false, 'message' => 'Category ID không hợp lệ']);
        }

        try {
            // Kiểm tra xem category có unlocked không
            $categoryAccess = $this->db->fetchOne(
                "SELECT is_unlocked FROM user_category_progress WHERE user_id = ? AND category_id = ?",
                [$userId, $categoryId]
            );

            if (!$categoryAccess || !$categoryAccess['is_unlocked']) {
                jsonResponse(['success' => false, 'message' => 'Chủ đề này chưa được mở khóa']);
            }

            // Build query dựa trên mode
            $whereCondition = "vw.category_id = ? AND vw.is_active = true";
            $params = [$categoryId];

            switch ($mode) {
                case 'new':
                    // Từ chưa học (chưa có trong user_word_knowledge)
                    $whereCondition .= " AND uwk.id IS NULL";
                    break;
                case 'review':
                    // Từ cần ôn tập
                    $whereCondition .= " AND uwk.next_review_at <= NOW()";
                    break;
                case 'study':
                    // Từ đã học nhưng chưa thành thạo
                    $whereCondition .= " AND uwk.knowledge_level BETWEEN 1 AND 3";
                    break;
                default:
                    // All words
                    break;
            }

            $limitClause = $limit > 0 ? "LIMIT $limit" : "";

            $words = $this->db->fetchAll(
                "SELECT vw.*, 
                        COALESCE(uwk.knowledge_level, 0) as knowledge_level,
                        COALESCE(uwk.correct_count, 0) as correct_count,
                        COALESCE(uwk.wrong_count, 0) as wrong_count,
                        COALESCE(uwk.difficulty_rating, 3) as difficulty_rating,
                        uwk.last_reviewed_at,
                        uwk.next_review_at,
                        vc.category_name,
                        vc.category_icon,
                        vc.category_color
                 FROM vocabulary_words vw
                 LEFT JOIN user_word_knowledge uwk ON vw.id = uwk.word_id AND uwk.user_id = ?
                 JOIN vocabulary_categories vc ON vw.category_id = vc.id
                 WHERE $whereCondition
                 ORDER BY vw.display_order, vw.frequency_rank
                 $limitClause",
                array_merge([$userId], $params)
            );

            // Format dữ liệu
            $formattedWords = [];
            foreach ($words as $word) {
                $formattedWords[] = [
                    'id' => (int) $word['id'],
                    'japanese_word' => $word['japanese_word'],
                    'kanji' => $word['kanji'],
                    'romaji' => $word['romaji'],
                    'vietnamese_meaning' => $word['vietnamese_meaning'],
                    'word_type' => $word['word_type'],
                    'example_sentence_jp' => $word['example_sentence_jp'],
                    'example_sentence_vn' => $word['example_sentence_vn'],
                    'usage_note' => $word['usage_note'],
                    'frequency_rank' => (int) $word['frequency_rank'],
                    'audio_url' => $word['audio_url'],
                    'image_url' => $word['image_url'],
                    'knowledge_level' => (int) $word['knowledge_level'],
                    'correct_count' => (int) $word['correct_count'],
                    'wrong_count' => (int) $word['wrong_count'],
                    'difficulty_rating' => (int) $word['difficulty_rating'],
                    'last_reviewed_at' => $word['last_reviewed_at'],
                    'next_review_at' => $word['next_review_at'],
                    'category' => [
                        'name' => $word['category_name'],
                        'icon' => $word['category_icon'],
                        'color' => $word['category_color']
                    ]
                ];
            }

            jsonResponse([
                'success' => true,
                'data' => $formattedWords,
                'count' => count($formattedWords),
                'mode' => $mode
            ]);

        } catch (Exception $e) {
            error_log("Get category words error: " . $e->getMessage());
            jsonResponse(['success' => false, 'message' => 'Không thể lấy từ vựng']);
        }
    }

    /**
 * API: Lấy từ vựng cho study session
 * GET /vocabulary-api.php?action=get_study_words&category_id=1&session_type=new&limit=20
 */
private function getStudyWords() {
    $userId = $this->requireAuth();
    $categoryId = (int)($_GET['category_id'] ?? 0);
    $sessionType = sanitizeInput($_GET['session_type'] ?? 'new');
    $limit = (int)($_GET['limit'] ?? 20);
    
    if ($categoryId <= 0) {
        jsonResponse(['success' => false, 'message' => 'Category ID không hợp lệ']);
    }
    
    // Map session_type to mode for getCategoryWords
    $modeMapping = [
        'new' => 'new',
        'review' => 'review', 
        'mixed' => 'all'
    ];
    
    $_GET['mode'] = $modeMapping[$sessionType] ?? 'new';
    $_GET['limit'] = $limit;
    
    // Gọi getCategoryWords
    $this->getCategoryWords();
}

    /**
     * API: Lấy tiến độ user
     * GET /vocabulary-api.php?action=get_user_progress
     */
    private function getUserProgress()
    {
        $userId = $this->requireAuth();

        try {
            $stats = $this->getVocabularyStats($userId);

            jsonResponse([
                'success' => true,
                'data' => $stats
            ]);

        } catch (Exception $e) {
            error_log("Get user progress error: " . $e->getMessage());
            jsonResponse(['success' => false, 'message' => 'Không thể lấy tiến độ học tập']);
        }
    }

    /**
     * API: Cập nhật kiến thức từ vựng
     * POST /vocabulary-api.php?action=update_word_knowledge
     * Body: {word_id, is_correct, difficulty_rating, study_time}
     */
    private function updateWordKnowledgeAPI()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['success' => false, 'message' => 'Method không hợp lệ']);
        }

        $userId = $this->requireAuth();

        // Lấy dữ liệu từ JSON body
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }

        $wordId = (int) ($input['word_id'] ?? 0);
        $isCorrect = (bool) ($input['is_correct'] ?? false);
        $difficultyRating = (int) ($input['difficulty_rating'] ?? 3);
        $studyTime = (int) ($input['study_time'] ?? 0); // giây

        if ($wordId <= 0) {
            jsonResponse(['success' => false, 'message' => 'Word ID không hợp lệ']);
        }

        if ($difficultyRating < 1 || $difficultyRating > 5) {
            jsonResponse(['success' => false, 'message' => 'Difficulty rating phải từ 1-5']);
        }

        try {
            // Cập nhật word knowledge
            $result = $this->updateWordKnowledge($userId, $wordId, $isCorrect, $difficultyRating);

            if ($result) {
                // Cập nhật study time cho category
                if ($studyTime > 0) {
                    $categoryId = $this->db->fetchOne(
                        "SELECT category_id FROM vocabulary_words WHERE id = ?",
                        [$wordId]
                    )['category_id'];
            
                    $this->db->query(
                        "UPDATE user_category_progress 
                         SET total_study_time = total_study_time + ? 
                         WHERE user_id = ? AND category_id = ?",
                        [$studyTime, $userId, $categoryId]
                    );
                }
            
                // Log activity
                $this->logActivity($userId, 'word_study', [
                    'word_id' => $wordId,
                    'is_correct' => $isCorrect,
                    'difficulty' => $difficultyRating,
                    'study_time' => $studyTime
                ]);
            
                jsonResponse([
                    'success' => true,
                    'message' => 'Đã cập nhật tiến độ học từ'
                ]);
            } else {
                jsonResponse(['success' => false, 'message' => 'Không thể cập nhật tiến độ']);
            }
            

        } catch (Exception $e) {
            error_log("Update word knowledge API error: " . $e->getMessage());
            jsonResponse(['success' => false, 'message' => 'Có lỗi xảy ra khi cập nhật']);
        }
    }

    /**
     * API: Lấy câu hỏi quiz
     * GET /vocabulary-api.php?action=get_quiz_questions&category_id=1&count=20&type=meaning
     */
    private function getQuizQuestions()
    {
        $userId = $this->requireAuth();
        $categoryId = (int) ($_GET['category_id'] ?? 0);
        $count = (int) ($_GET['count'] ?? 20);
        $type = sanitizeInput($_GET['type'] ?? 'meaning'); // meaning, reading, mixed
        $mode = sanitizeInput($_GET['mode'] ?? 'category'); // category, review, mixed

        if ($count < 5 || $count > 50) {
            $count = 20;
        }

        try {
            $whereCondition = "vw.is_active = true";
            $params = [];

            if ($mode === 'category' && $categoryId > 0) {
                $whereCondition .= " AND vw.category_id = ?";
                $params[] = $categoryId;

                // Kiểm tra unlock
                $categoryAccess = $this->db->fetchOne(
                    "SELECT is_unlocked FROM user_category_progress WHERE user_id = ? AND category_id = ?",
                    [$userId, $categoryId]
                );

                if (!$categoryAccess || !$categoryAccess['is_unlocked']) {
                    jsonResponse(['success' => false, 'message' => 'Chủ đề này chưa được mở khóa']);
                }
            } elseif ($mode === 'review') {
                $whereCondition .= " AND uwk.user_id = ? AND uwk.next_review_at <= NOW()";
                $params[] = $userId;
            } else {
                // Mixed mode - từ tất cả categories đã unlock
                $whereCondition .= " AND EXISTS (
                    SELECT 1 FROM user_category_progress ucp 
                    WHERE ucp.category_id = vw.category_id 
                    AND ucp.user_id = ? AND ucp.is_unlocked = true
                )";
                $params[] = $userId;
            }

            // Lấy từ vựng
            $words = $this->db->fetchAll(
                "SELECT vw.*, vc.category_name, vc.category_icon
                 FROM vocabulary_words vw
                 LEFT JOIN user_word_knowledge uwk ON vw.id = uwk.word_id AND uwk.user_id = ?
                 JOIN vocabulary_categories vc ON vw.category_id = vc.id
                 WHERE $whereCondition
                 ORDER BY RANDOM()
                 LIMIT ?",
                array_merge([$userId], $params, [$count])
            );

            if (empty($words)) {
                jsonResponse(['success' => false, 'message' => 'Không có từ vựng để tạo quiz']);
            }

            // Tạo câu hỏi
            $questions = [];
            foreach ($words as $word) {
                $questions[] = $this->generateQuizQuestion($word, $type, $words);
            }

            // Tạo quiz session
            $sessionId = $this->createQuizSession($userId, $categoryId, $mode, count($questions));

            jsonResponse([
                'success' => true,
                'data' => [
                    'session_id' => $sessionId,
                    'questions' => $questions,
                    'total_questions' => count($questions),
                    'type' => $type,
                    'mode' => $mode,
                    'category_id' => $categoryId
                ]
            ]);

        } catch (Exception $e) {
            error_log("Get quiz questions error: " . $e->getMessage());
            jsonResponse(['success' => false, 'message' => 'Không thể tạo câu hỏi quiz']);
        }
    }

    /**
     * Tạo một câu hỏi quiz
     */
    private function generateQuizQuestion($word, $type, $allWords)
    {
        $question = [
            'id' => (int) $word['id'],
            'type' => $type
        ];

        // Tạo options sai ngẫu nhiên
        $wrongOptions = [];
        $shuffledWords = $allWords;
        shuffle($shuffledWords);

        foreach ($shuffledWords as $w) {
            if ($w['id'] != $word['id'] && count($wrongOptions) < 3) {
                if ($type === 'meaning') {
                    $wrongOptions[] = $w['vietnamese_meaning'];
                } else {
                    $wrongOptions[] = $w['japanese_word'];
                }
            }
        }

        if ($type === 'meaning') {
            // Cho từ tiếng Nhật, hỏi nghĩa tiếng Việt
            $question['question'] = $word['japanese_word'];
            if ($word['kanji']) {
                $question['question_kanji'] = $word['kanji'];
            }
            $question['correct_answer'] = $word['vietnamese_meaning'];
            $question['question_type'] = 'jp_to_vn';
        } else {
            // Cho nghĩa tiếng Việt, hỏi từ tiếng Nhật
            $question['question'] = $word['vietnamese_meaning'];
            $question['correct_answer'] = $word['japanese_word'];
            $question['question_type'] = 'vn_to_jp';
        }

        // Trộn options
        $options = array_merge([$question['correct_answer']], $wrongOptions);
        shuffle($options);

        $question['options'] = $options;
        $question['word_data'] = [
            'romaji' => $word['romaji'],
            'word_type' => $word['word_type'],
            'example_jp' => $word['example_sentence_jp'],
            'example_vn' => $word['example_sentence_vn'],
            'usage_note' => $word['usage_note']
        ];

        return $question;
    }

    /**
     * Tạo quiz session
     */
    private function createQuizSession($userId, $categoryId, $quizType, $totalQuestions)
    {
        try {
            $stmt = $this->db->query(
                "INSERT INTO vocabulary_quiz_sessions 
                 (user_id, category_id, quiz_type, total_questions, started_at) 
                 VALUES (?, ?, ?, ?, NOW()) RETURNING id",
                [$userId, $categoryId, $quizType, $totalQuestions]
            );

            return $stmt->fetchColumn();
        } catch (Exception $e) {
            error_log("Create quiz session error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * API: Lưu kết quả quiz
     * POST /vocabulary-api.php?action=save_quiz_result
     */
    private function saveQuizResult()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['success' => false, 'message' => 'Method không hợp lệ']);
        }

        $userId = $this->requireAuth();

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }

        $sessionId = (int) ($input['session_id'] ?? 0);
        $correctAnswers = (int) ($input['correct_answers'] ?? 0);
        $timeSpent = (int) ($input['time_spent'] ?? 0);
        $answers = $input['answers'] ?? []; // [{word_id, is_correct, time_taken}]

        try {
            // Lấy session info
            $session = $this->db->fetchOne(
                "SELECT * FROM vocabulary_quiz_sessions WHERE id = ? AND user_id = ?",
                [$sessionId, $userId]
            );

            if (!$session) {
                jsonResponse(['success' => false, 'message' => 'Session không hợp lệ']);
            }

            $totalQuestions = $session['total_questions'];
            $score = round(($correctAnswers / $totalQuestions) * 100);
            $percentage = round(($correctAnswers / $totalQuestions) * 100, 2);

            // Cập nhật session
            $this->db->query(
                "UPDATE vocabulary_quiz_sessions 
                 SET correct_answers = ?, time_spent = ?, score = ?, percentage = ?, 
                     completed_at = NOW(), is_completed = true, quiz_data = ?
                 WHERE id = ?",
                [$correctAnswers, $timeSpent, $score, $percentage, json_encode($answers), $sessionId]
            );

            // Cập nhật word knowledge cho từng từ
            foreach ($answers as $answer) {
                if (isset($answer['word_id']) && isset($answer['is_correct'])) {
                    $this->updateWordKnowledge(
                        $userId,
                        $answer['word_id'],
                        $answer['is_correct'],
                        $answer['difficulty_rating'] ?? 3
                    );
                }
            }

            // Cập nhật quiz best score cho category
            if ($session['category_id']) {
                $this->db->query(
                    "UPDATE user_category_progress 
                     SET quiz_best_score = GREATEST(quiz_best_score, ?),
                         quiz_attempts = quiz_attempts + 1
                     WHERE user_id = ? AND category_id = ?",
                    [$score, $userId, $session['category_id']]
                );
            }

            // Log activity
            $this->logActivity($userId, 'quiz_completed', [
                'score' => $correctAnswers,
                'total_questions' => $totalQuestions,
                'time_spent' => $timeSpent,
                'quiz_type' => $session['quiz_type']
            ]);

            jsonResponse([
                'success' => true,
                'data' => [
                    'score' => $score,
                    'percentage' => $percentage,
                    'correct_answers' => $correctAnswers,
                    'total_questions' => $totalQuestions,
                    'time_spent' => $timeSpent,
                    'grade' => $this->calculateGrade($percentage)
                ]
            ]);

        } catch (Exception $e) {
            error_log("Save quiz result error: " . $e->getMessage());
            jsonResponse(['success' => false, 'message' => 'Không thể lưu kết quả quiz']);
        }
    }

    /**
     * Tính grade dựa trên percentage
     */
    private function calculateGrade($percentage)
    {
        if ($percentage >= 95)
            return ['grade' => 'A+', 'message' => 'Xuất sắc! 🌟', 'color' => '#28a745'];
        if ($percentage >= 85)
            return ['grade' => 'A', 'message' => 'Rất tốt! 👏', 'color' => '#28a745'];
        if ($percentage >= 75)
            return ['grade' => 'B', 'message' => 'Tốt! 👍', 'color' => '#17a2b8'];
        if ($percentage >= 65)
            return ['grade' => 'C', 'message' => 'Khá! 📚', 'color' => '#ffc107'];
        if ($percentage >= 50)
            return ['grade' => 'D', 'message' => 'Cần cố gắng thêm! 💪', 'color' => '#fd7e14'];
        return ['grade' => 'F', 'message' => 'Hãy ôn tập thêm! 📖', 'color' => '#dc3545'];
    }

    /**
     * API: Lấy từ cần ôn tập
     * GET /vocabulary-api.php?action=get_review_words&limit=20
     */
    private function getReviewWordsAPI()
    {
        $userId = $this->requireAuth();
        $limit = (int) ($_GET['limit'] ?? 20);

        try {
            $words = $this->getWordsForReview($userId, $limit);

            jsonResponse([
                'success' => true,
                'data' => [
                    'words' => $words,
                    'stats' => [
                        'new' => 3,
                        'learning' => 5,
                        'review' => 8,
                        'mature' => 4
                    ]
                ],
                'count' => count($words)
            ]);

        } catch (Exception $e) {
            error_log("Get review words API error: " . $e->getMessage());
            jsonResponse(['success' => false, 'message' => 'Không thể lấy từ cần ôn tập']);
        }
    }

    /**
     * API: Lấy session học tập (flashcard)
     * GET /vocabulary-api.php?action=get_study_session&category_id=1&mode=new&limit=10
     */
    private function getStudySession()
    {
        $userId = $this->requireAuth();
        $categoryId = (int) ($_GET['category_id'] ?? 0);
        $mode = sanitizeInput($_GET['mode'] ?? 'new'); // new, review, all
        $limit = (int) ($_GET['limit'] ?? 10);

        try {
            // Gọi lại getCategoryWords với params tương ứng
            $_GET['mode'] = $mode;
            $_GET['limit'] = $limit;
            $this->getCategoryWords();

        } catch (Exception $e) {
            error_log("Get study session error: " . $e->getMessage());
            jsonResponse(['success' => false, 'message' => 'Không thể tạo session học tập']);
        }
    }

    /**
     * API: Lưu thời gian học
     * POST /vocabulary-api.php?action=save_study_time
     */
    private function saveStudyTime()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['success' => false, 'message' => 'Method không hợp lệ']);
        }

        $userId = $this->requireAuth();

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }

        $categoryId = (int) ($input['category_id'] ?? 0);
        $studyTime = (int) ($input['study_time'] ?? 0); // giây

        if ($studyTime <= 0) {
            jsonResponse(['success' => false, 'message' => 'Thời gian học không hợp lệ']);
        }

        try {
            if ($categoryId > 0) {
                // Cập nhật study time cho category cụ thể
                $this->db->query(
                    "UPDATE user_category_progress 
                     SET total_study_time = total_study_time + ?, last_studied_at = NOW()
                     WHERE user_id = ? AND category_id = ?",
                    [$studyTime, $userId, $categoryId]
                );
            }

            // Cập nhật tổng study time
            $this->updateOverallVocabularyProgress($userId);

            jsonResponse([
                'success' => true,
                'message' => 'Đã lưu thời gian học tập'
            ]);

        } catch (Exception $e) {
            error_log("Save study time error: " . $e->getMessage());
            jsonResponse(['success' => false, 'message' => 'Không thể lưu thời gian học tập']);
        }
    }

    /**
     * API: Lấy thống kê vocabulary
     * GET /vocabulary-api.php?action=get_vocabulary_stats
     */
    private function getVocabularyStatsAPI()
    {
        $userId = $this->requireAuth();

        try {
            $stats = $this->getVocabularyStats($userId);

            // Thêm thông tin bổ sung
            $additionalStats = [
                'words_due_today' => $this->getWordsDueToday($userId),
                'daily_streak' => $this->calculateDailyStreak($userId),
                'weekly_progress' => $this->getWeeklyProgress($userId),
                'favorite_category' => $this->getFavoriteCategory($userId)
            ];

            jsonResponse([
                'success' => true,
                'data' => array_merge($stats, $additionalStats)
            ]);

        } catch (Exception $e) {
            error_log("Get vocabulary stats API error: " . $e->getMessage());
            jsonResponse(['success' => false, 'message' => 'Không thể lấy thống kê']);
        }
    }

    /**
     * API: Lấy bảng xếp hạng
     * GET /vocabulary-api.php?action=get_leaderboard&limit=10
     */
    private function getLeaderboardAPI()
    {
        $limit = (int) ($_GET['limit'] ?? 10);

        try {
            $leaderboard = $this->getLeaderboard($limit);

            jsonResponse([
                'success' => true,
                'data' => $leaderboard
            ]);

        } catch (Exception $e) {
            error_log("Get leaderboard API error: " . $e->getMessage());
            jsonResponse(['success' => false, 'message' => 'Không thể lấy bảng xếp hạng']);
        }
    }

    /**
     * API: Khởi tạo vocabulary cho user
     * POST /vocabulary-api.php?action=initialize_user
     */
    private function initializeUserAPI()
    {
        $userId = $this->requireAuth();

        try {
            $result = $this->initializeUserVocabulary($userId);

            if ($result) {
                jsonResponse([
                    'success' => true,
                    'message' => 'Đã khởi tạo vocabulary cho user'
                ]);
            } else {
                jsonResponse(['success' => false, 'message' => 'Không thể khởi tạo vocabulary']);
            }

        } catch (Exception $e) {
            error_log("Initialize user API error: " . $e->getMessage());
            jsonResponse(['success' => false, 'message' => 'Có lỗi xảy ra khi khởi tạo']);
        }
    }

    /**
     * API: Lấy chi tiết một category
     * GET /vocabulary-api.php?action=get_category_detail&category_id=1
     */
    private function getCategoryDetail()
    {
        $userId = $this->requireAuth();
        $categoryId = (int) ($_GET['category_id'] ?? 0);

        if ($categoryId <= 0) {
            jsonResponse(['success' => false, 'message' => 'Category ID không hợp lệ']);
        }

        try {
            // Lấy thông tin category
            $category = $this->db->fetchOne(
                "SELECT vc.*, 
                        COALESCE(ucp.total_words, 0) as total_words,
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
                 WHERE vc.id = ? AND vc.is_active = true",
                [$userId, $categoryId]
            );

            if (!$category) {
                jsonResponse(['success' => false, 'message' => 'Không tìm thấy chủ đề']);
            }

            // Lấy thống kê từ vựng trong category
            $wordStats = $this->db->fetchOne(
                "SELECT 
                    COUNT(*) as total_words,
                    COUNT(CASE WHEN uwk.knowledge_level >= 1 THEN 1 END) as learned_words,
                    COUNT(CASE WHEN uwk.knowledge_level >= 4 THEN 1 END) as mastered_words,
                    COUNT(CASE WHEN uwk.next_review_at <= NOW() THEN 1 END) as due_words,
                    AVG(CASE WHEN uwk.knowledge_level > 0 THEN uwk.knowledge_level END) as avg_knowledge_level
                 FROM vocabulary_words vw
                 LEFT JOIN user_word_knowledge uwk ON vw.id = uwk.word_id AND uwk.user_id = ?
                 WHERE vw.category_id = ? AND vw.is_active = true",
                [$userId, $categoryId]
            );

            // Lấy lịch sử quiz gần đây
            $recentQuizzes = $this->db->fetchAll(
                "SELECT score, percentage, correct_answers, total_questions, time_spent, completed_at
                 FROM vocabulary_quiz_sessions
                 WHERE user_id = ? AND category_id = ? AND is_completed = true
                 ORDER BY completed_at DESC
                 LIMIT 5",
                [$userId, $categoryId]
            );

            // Format response
            $categoryDetail = [
                'id' => (int) $category['id'],
                'name' => $category['category_name'],
                'name_en' => $category['category_name_en'],
                'icon' => $category['category_icon'],
                'color' => $category['category_color'],
                'description' => $category['description'],
                'difficulty_level' => (int) $category['difficulty_level'],
                'estimated_hours' => (float) $category['estimated_hours'],
                'is_unlocked' => (bool) $category['is_unlocked'],
                'is_completed' => (bool) $category['is_completed'],
                'last_studied_at' => $category['last_studied_at'],
                'unlock_condition' => json_decode($category['unlock_condition'], true),
                'progress' => [
                    'total_words' => (int) $wordStats['total_words'],
                    'learned_words' => (int) $wordStats['learned_words'],
                    'mastered_words' => (int) $wordStats['mastered_words'],
                    'due_words' => (int) $wordStats['due_words'],
                    'completion_percentage' => (float) $category['completion_percentage'],
                    'avg_knowledge_level' => round($wordStats['avg_knowledge_level'] ?? 0, 1)
                ],
                'quiz_stats' => [
                    'best_score' => (int) $category['quiz_best_score'],
                    'attempts' => (int) $category['quiz_attempts'],
                    'recent_quizzes' => $recentQuizzes
                ],
                'study_time' => [
                    'total_seconds' => (int) $category['total_study_time'],
                    'total_hours' => round($category['total_study_time'] / 3600, 1),
                    'estimated_hours' => (float) $category['estimated_hours']
                ]
            ];

            jsonResponse([
                'success' => true,
                'data' => $categoryDetail
            ]);

        } catch (Exception $e) {
            error_log("Get category detail error: " . $e->getMessage());
            jsonResponse(['success' => false, 'message' => 'Không thể lấy chi tiết chủ đề']);
        }
    }

    /**
     * API: Tìm kiếm từ vựng
     * GET /vocabulary-api.php?action=search_words&query=こんにちは&limit=20
     */
    private function searchWords()
    {
        $userId = $this->requireAuth();
        $query = sanitizeInput($_GET['query'] ?? '');
        $limit = (int) ($_GET['limit'] ?? 20);

        if (strlen($query) < 2) {
            jsonResponse(['success' => false, 'message' => 'Từ khóa tìm kiếm quá ngắn']);
        }

        try {
            // Tìm kiếm trong các trường
            $words = $this->db->fetchAll(
                "SELECT vw.*, vc.category_name, vc.category_icon, vc.category_color,
                        COALESCE(uwk.knowledge_level, 0) as knowledge_level,
                        uwk.last_reviewed_at
                 FROM vocabulary_words vw
                 JOIN vocabulary_categories vc ON vw.category_id = vc.id
                 LEFT JOIN user_word_knowledge uwk ON vw.id = uwk.word_id AND uwk.user_id = ?
                 JOIN user_category_progress ucp ON vc.id = ucp.category_id AND ucp.user_id = ?
                 WHERE vw.is_active = true 
                   AND ucp.is_unlocked = true
                   AND (
                       vw.japanese_word ILIKE ? OR
                       vw.kanji ILIKE ? OR
                       vw.romaji ILIKE ? OR
                       vw.vietnamese_meaning ILIKE ?
                   )
                 ORDER BY 
                   CASE 
                     WHEN vw.japanese_word ILIKE ? THEN 1
                     WHEN vw.kanji ILIKE ? THEN 2
                     WHEN vw.romaji ILIKE ? THEN 3
                     ELSE 4
                   END,
                   vw.frequency_rank
                 LIMIT ?",
                [
                    $userId,
                    $userId,
                    "%$query%",
                    "%$query%",
                    "%$query%",
                    "%$query%",
                    "%$query%",
                    "%$query%",
                    "%$query%",
                    $limit
                ]
            );

            // Format kết quả
            $formattedWords = [];
            foreach ($words as $word) {
                $formattedWords[] = [
                    'id' => (int) $word['id'],
                    'japanese_word' => $word['japanese_word'],
                    'kanji' => $word['kanji'],
                    'romaji' => $word['romaji'],
                    'vietnamese_meaning' => $word['vietnamese_meaning'],
                    'word_type' => $word['word_type'],
                    'example_sentence_jp' => $word['example_sentence_jp'],
                    'example_sentence_vn' => $word['example_sentence_vn'],
                    'usage_note' => $word['usage_note'],
                    'knowledge_level' => (int) $word['knowledge_level'],
                    'last_reviewed_at' => $word['last_reviewed_at'],
                    'category' => [
                        'name' => $word['category_name'],
                        'icon' => $word['category_icon'],
                        'color' => $word['category_color']
                    ]
                ];
            }

            jsonResponse([
                'success' => true,
                'data' => $formattedWords,
                'count' => count($formattedWords),
                'query' => $query
            ]);

        } catch (Exception $e) {
            error_log("Search words error: " . $e->getMessage());
            jsonResponse(['success' => false, 'message' => 'Không thể tìm kiếm từ vựng']);
        }
    }

    // Helper functions for additional stats

    /**
     * Lấy số từ cần ôn tập hôm nay
     */
    private function getWordsDueToday($userId)
    {
        try {
            return $this->db->fetchOne(
                "SELECT COUNT(*) as count 
                 FROM user_word_knowledge uwk
                 JOIN vocabulary_words vw ON uwk.word_id = vw.id
                 WHERE uwk.user_id = ? AND DATE(uwk.next_review_at) <= CURRENT_DATE",
                [$userId]
            )['count'] ?? 0;
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Tính daily streak
     */
    private function calculateDailyStreak($userId)
    {
        try {
            // Đếm số ngày liên tiếp có hoạt động vocabulary
            $activities = $this->db->fetchAll(
                "SELECT DISTINCT DATE(created_at) as activity_date
                 FROM user_activities 
                 WHERE user_id = ? AND activity_type LIKE 'vocabulary_%'
                 ORDER BY activity_date DESC
                 LIMIT 30",
                [$userId]
            );

            if (empty($activities))
                return 0;

            $streak = 0;
            $currentDate = new DateTime();
            $currentDate->setTime(0, 0, 0);

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
            return 0;
        }
    }

    /**
     * Lấy tiến độ tuần này
     */
    private function getWeeklyProgress($userId)
    {
        try {
            return $this->db->fetchOne(
                "SELECT 
                    COUNT(*) as sessions_this_week,
                    SUM(score) as total_score_this_week,
                    AVG(percentage) as avg_percentage_this_week
                 FROM vocabulary_quiz_sessions
                 WHERE user_id = ? 
                   AND is_completed = true 
                   AND completed_at >= DATE_TRUNC('week', NOW())",
                [$userId]
            );
        } catch (Exception $e) {
            return [
                'sessions_this_week' => 0,
                'total_score_this_week' => 0,
                'avg_percentage_this_week' => 0
            ];
        }
    }

    /**
     * Lấy category yêu thích (học nhiều nhất)
     */
    private function getFavoriteCategory($userId)
    {
        try {
            return $this->db->fetchOne(
                "SELECT vc.category_name, vc.category_icon, ucp.total_study_time
                 FROM user_category_progress ucp
                 JOIN vocabulary_categories vc ON ucp.category_id = vc.id
                 WHERE ucp.user_id = ?
                 ORDER BY ucp.total_study_time DESC
                 LIMIT 1",
                [$userId]
            );
        } catch (Exception $e) {
            return null;
        }
    }
}

// Xử lý request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // Handle CORS preflight
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    exit(0);
}

try {
    $api = new VocabularyAPI();
    $api->handleRequest();
} catch (Exception $e) {
    error_log("Vocabulary API Exception: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Server error occurred']);
}
?>

