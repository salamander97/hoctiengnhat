<?php
/**
 * N5 Lessons API
 * Handles all lesson-related operations for N5 vocabulary learning system
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'config.php';

class N5LessonsAPI {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function handleRequest() {
        try {
            $action = $_GET['action'] ?? '';
            $method = $_SERVER['REQUEST_METHOD'];
            
            switch ($action) {
                case 'getLessons':
                    $this->getLessons();
                    break;
                    
                case 'getLessonDetail':
                    $this->getLessonDetail();
                    break;
                    
                case 'getLessonVocabulary':
                    $this->getLessonVocabulary();
                    break;
                    
                case 'getUserLessonProgress':
                    $this->getUserLessonProgress();
                    break;
                    
                case 'updateStudyProgress':
                    $this->updateStudyProgress();
                    break;
                    
                case 'startQuiz':
                    $this->startQuiz();
                    break;
                    
                case 'submitQuiz':
                    $this->submitQuiz();
                    break;
                    
                case 'updateWordKnowledge':
                    $this->updateWordKnowledge();
                    break;
                    
                default:
                    throw new Exception('Invalid action');
            }
            
        } catch (Exception $e) {
            $this->sendError($e->getMessage());
        }
    }
    
    // GET /n5-lessons-api.php?action=getLessons&user_id=1
    public function getLessons() {
        $userId = $_GET['user_id'] ?? null;
        
        if (!$userId) {
            throw new Exception('User ID is required');
        }
        
        // Get all lessons with user progress
        $lessons = $this->db->fetchAll(
            "SELECT 
                l.*,
                COALESCE(ulp.total_words, 0) as total_words,
                COALESCE(ulp.studied_words, 0) as studied_words,
                COALESCE(ulp.mastered_words, 0) as mastered_words,
                COALESCE(ulp.completion_percentage, 0) as completion_percentage,
                COALESCE(ulp.is_completed, false) as is_completed,
                COALESCE(ulp.best_quiz_score, 0) as best_quiz_score,
                COALESCE(ulp.quiz_attempts, 0) as quiz_attempts,
                ulp.last_studied_at,
                ulp.last_quiz_at
            FROM n5_lessons l
            LEFT JOIN user_lesson_progress ulp ON l.id = ulp.lesson_id AND ulp.user_id = ?
            WHERE l.is_active = true
            ORDER BY l.lesson_number",
            [$userId]
        );
        
        // Calculate overall progress
        $totalLessons = count($lessons);
        $completedLessons = count(array_filter($lessons, fn($l) => $l['is_completed']));
        $overallProgress = $totalLessons > 0 ? round(($completedLessons / $totalLessons) * 100, 1) : 0;
        
        $this->sendSuccess([
            'lessons' => $lessons,
            'stats' => [
                'total_lessons' => $totalLessons,
                'completed_lessons' => $completedLessons,
                'overall_progress' => $overallProgress,
                'unlocked_lessons' => count(array_filter($lessons, fn($l) => $l['lesson_number'] == 1 || $l['completion_percentage'] > 0))
            ]
        ]);
    }
    
    // GET /n5-lessons-api.php?action=getLessonDetail&lesson_id=1&user_id=1
    public function getLessonDetail() {
        $lessonId = $_GET['lesson_id'] ?? null;
        $userId = $_GET['user_id'] ?? null;
        
        if (!$lessonId || !$userId) {
            throw new Exception('Lesson ID and User ID are required');
        }
        
        // Get lesson details
        $lesson = $this->db->fetchOne(
            "SELECT 
                l.*,
                COALESCE(ulp.total_words, 0) as total_words,
                COALESCE(ulp.studied_words, 0) as studied_words,
                COALESCE(ulp.mastered_words, 0) as mastered_words,
                COALESCE(ulp.completion_percentage, 0) as completion_percentage,
                COALESCE(ulp.is_completed, false) as is_completed,
                COALESCE(ulp.best_quiz_score, 0) as best_quiz_score,
                COALESCE(ulp.quiz_attempts, 0) as quiz_attempts,
                COALESCE(ulp.study_sessions, 0) as study_sessions,
                COALESCE(ulp.total_study_time, 0) as total_study_time,
                ulp.last_studied_at,
                ulp.last_quiz_at
            FROM n5_lessons l
            LEFT JOIN user_lesson_progress ulp ON l.id = ulp.lesson_id AND ulp.user_id = ?
            WHERE l.id = ? AND l.is_active = true",
            [$userId, $lessonId]
        );
        
        if (!$lesson) {
            throw new Exception('Lesson not found');
        }
        
        // Get sample vocabulary (first 6 words)
        $sampleWords = $this->db->fetchAll(
            "SELECT japanese_word, romaji, vietnamese_meaning, word_type
            FROM n5_lesson_vocabulary 
            WHERE lesson_id = ? AND is_active = true 
            ORDER BY display_order 
            LIMIT 6",
            [$lessonId]
        );
        
        $lesson['sample_words'] = $sampleWords;
        
        $this->sendSuccess($lesson);
    }
    
    // GET /n5-lessons-api.php?action=getLessonVocabulary&lesson_id=1&user_id=1
    public function getLessonVocabulary() {
        $lessonId = $_GET['lesson_id'] ?? null;
        $userId = $_GET['user_id'] ?? null;
        
        if (!$lessonId || !$userId) {
            throw new Exception('Lesson ID and User ID are required');
        }
        
        // Get all vocabulary for the lesson with user knowledge
        $vocabulary = $this->db->fetchAll(
            "SELECT 
                v.*,
                COALESCE(uwk.knowledge_level, 0) as knowledge_level,
                COALESCE(uwk.correct_count, 0) as correct_count,
                COALESCE(uwk.wrong_count, 0) as wrong_count,
                uwk.last_reviewed_at
            FROM n5_lesson_vocabulary v
            LEFT JOIN user_word_knowledge uwk ON v.id = uwk.word_id AND uwk.user_id = ?
            WHERE v.lesson_id = ? AND v.is_active = true
            ORDER BY v.display_order",
            [$userId, $lessonId]
        );
        
        // Get lesson info
        $lesson = $this->db->fetchOne(
            "SELECT lesson_number, lesson_title, lesson_title_jp, lesson_icon, difficulty_level
            FROM n5_lessons 
            WHERE id = ? AND is_active = true",
            [$lessonId]
        );
        
        $this->sendSuccess([
            'lesson' => $lesson,
            'vocabulary' => $vocabulary,
            'total_words' => count($vocabulary)
        ]);
    }
    
    // GET /n5-lessons-api.php?action=getUserLessonProgress&user_id=1&lesson_id=1
    public function getUserLessonProgress() {
        $userId = $_GET['user_id'] ?? null;
        $lessonId = $_GET['lesson_id'] ?? null;
        
        if (!$userId || !$lessonId) {
            throw new Exception('User ID and Lesson ID are required');
        }
        
        $progress = $this->db->fetchOne(
            "SELECT * FROM user_lesson_progress 
            WHERE user_id = ? AND lesson_id = ?",
            [$userId, $lessonId]
        );
        
        if (!$progress) {
            // Create initial progress record
            $this->db->query(
                "INSERT INTO user_lesson_progress (user_id, lesson_id, total_words) 
                VALUES (?, ?, (SELECT COUNT(*) FROM n5_lesson_vocabulary WHERE lesson_id = ? AND is_active = true))",
                [$userId, $lessonId, $lessonId]
            );
            
            $progress = $this->db->fetchOne(
                "SELECT * FROM user_lesson_progress WHERE user_id = ? AND lesson_id = ?",
                [$userId, $lessonId]
            );
        }
        
        $this->sendSuccess($progress);
    }
    
    // POST /n5-lessons-api.php?action=updateStudyProgress
    public function updateStudyProgress() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $userId = $input['user_id'] ?? null;
        $lessonId = $input['lesson_id'] ?? null;
        $studiedWords = $input['studied_words'] ?? 0;
        $timeSpent = $input['time_spent'] ?? 0;
        
        if (!$userId || !$lessonId) {
            throw new Exception('User ID and Lesson ID are required');
        }
        
        // Update or create progress record
        $this->db->query(
            "INSERT INTO user_lesson_progress (user_id, lesson_id, studied_words, total_study_time, study_sessions, last_studied_at, total_words)
            VALUES (?, ?, ?, ?, 1, NOW(), (SELECT COUNT(*) FROM n5_lesson_vocabulary WHERE lesson_id = ? AND is_active = true))
            ON CONFLICT (user_id, lesson_id) 
            DO UPDATE SET 
                studied_words = GREATEST(user_lesson_progress.studied_words, EXCLUDED.studied_words),
                total_study_time = user_lesson_progress.total_study_time + EXCLUDED.total_study_time,
                study_sessions = user_lesson_progress.study_sessions + 1,
                last_studied_at = NOW(),
                completion_percentage = CASE 
                    WHEN user_lesson_progress.total_words > 0 
                    THEN ROUND((GREATEST(user_lesson_progress.studied_words, EXCLUDED.studied_words)::decimal / user_lesson_progress.total_words) * 100, 2)
                    ELSE 0 
                END",
            [$userId, $lessonId, $studiedWords, $timeSpent, $lessonId]
        );
        
        $this->sendSuccess(['message' => 'Study progress updated successfully']);
    }
    
    // POST /n5-lessons-api.php?action=updateWordKnowledge
    public function updateWordKnowledge() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $userId = $input['user_id'] ?? null;
        $wordId = $input['word_id'] ?? null;
        $knowledgeLevel = $input['knowledge_level'] ?? 0;
        
        if (!$userId || !$wordId) {
            throw new Exception('User ID and Word ID are required');
        }
        
        // Update or create word knowledge record
        $this->db->query(
            "INSERT INTO user_word_knowledge (user_id, word_id, knowledge_level, last_reviewed_at)
            VALUES (?, ?, ?, NOW())
            ON CONFLICT (user_id, word_id)
            DO UPDATE SET 
                knowledge_level = EXCLUDED.knowledge_level,
                last_reviewed_at = NOW()",
            [$userId, $wordId, $knowledgeLevel]
        );
        
        $this->sendSuccess(['message' => 'Word knowledge updated successfully']);
    }
    
    // POST /n5-lessons-api.php?action=startQuiz
    public function startQuiz() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $userId = $input['user_id'] ?? null;
        $lessonId = $input['lesson_id'] ?? null;
        $questionCount = $input['question_count'] ?? 10;
        
        if (!$userId || !$lessonId) {
            throw new Exception('User ID and Lesson ID are required');
        }
        
        // Get vocabulary for quiz
        $vocabulary = $this->db->fetchAll(
            "SELECT id, japanese_word, kanji, romaji, vietnamese_meaning, word_type, example_sentence_jp, example_sentence_vn
            FROM n5_lesson_vocabulary 
            WHERE lesson_id = ? AND is_active = true 
            ORDER BY RANDOM() 
            LIMIT ?",
            [$lessonId, $questionCount]
        );
        
        if (empty($vocabulary)) {
            throw new Exception('No vocabulary found for this lesson');
        }
        
        // Create quiz session
        $sessionId = $this->db->query(
            "INSERT INTO lesson_quiz_sessions (user_id, lesson_id, total_questions, quiz_data)
            VALUES (?, ?, ?, ?) RETURNING id",
            [$userId, $lessonId, count($vocabulary), json_encode(['vocabulary' => $vocabulary])]
        );
        
        // Generate quiz questions
        $questions = $this->generateQuizQuestions($vocabulary);
        
        $this->sendSuccess([
            'session_id' => $sessionId,
            'questions' => $questions,
            'total_questions' => count($questions)
        ]);
    }
    
    // POST /n5-lessons-api.php?action=submitQuiz  
    public function submitQuiz() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $sessionId = $input['session_id'] ?? null;
        $answers = $input['answers'] ?? [];
        $timeSpent = $input['time_spent'] ?? 0;
        
        if (!$sessionId || empty($answers)) {
            throw new Exception('Session ID and answers are required');
        }
        
        // Get quiz session
        $session = $this->db->fetchOne(
            "SELECT * FROM lesson_quiz_sessions WHERE id = ?",
            [$sessionId]
        );
        
        if (!$session) {
            throw new Exception('Quiz session not found');
        }
        
        // Calculate score
        $correctAnswers = 0;
        $totalQuestions = count($answers);
        $quizData = json_decode($session['quiz_data'], true);
        
        foreach ($answers as $answer) {
            if ($answer['is_correct']) {
                $correctAnswers++;
            }
        }
        
        $score = $totalQuestions > 0 ? round(($correctAnswers / $totalQuestions) * 100) : 0;
        
        // Update quiz session
        $this->db->query(
            "UPDATE lesson_quiz_sessions 
            SET correct_answers = ?, wrong_answers = ?, score = ?, time_spent = ?, 
                completed_at = NOW(), is_completed = true,
                quiz_data = ?
            WHERE id = ?",
            [$correctAnswers, $totalQuestions - $correctAnswers, $score, $timeSpent, 
             json_encode(array_merge($quizData, ['answers' => $answers])), $sessionId]
        );
        
        // Update user lesson progress
        $this->db->query(
            "UPDATE user_lesson_progress 
            SET quiz_attempts = quiz_attempts + 1,
                best_quiz_score = GREATEST(best_quiz_score, ?),
                last_quiz_at = NOW(),
                mastered_words = CASE WHEN ? >= 80 THEN total_words ELSE mastered_words END,
                is_completed = CASE WHEN ? >= 80 THEN true ELSE is_completed END,
                completion_percentage = CASE WHEN ? >= 80 THEN 100 ELSE completion_percentage END
            WHERE user_id = ? AND lesson_id = ?",
            [$score, $score, $score, $score, $session['user_id'], $session['lesson_id']]
        );
        
        // Update word knowledge based on quiz results
        foreach ($answers as $answer) {
            if (isset($answer['word_id'])) {
                $this->db->query(
                    "INSERT INTO user_word_knowledge (user_id, word_id, knowledge_level, correct_count, wrong_count, last_reviewed_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                    ON CONFLICT (user_id, word_id)
                    DO UPDATE SET 
                        knowledge_level = CASE WHEN EXCLUDED.correct_count > 0 THEN LEAST(2, user_word_knowledge.knowledge_level + 1) ELSE user_word_knowledge.knowledge_level END,
                        correct_count = user_word_knowledge.correct_count + EXCLUDED.correct_count,
                        wrong_count = user_word_knowledge.wrong_count + EXCLUDED.wrong_count,
                        last_reviewed_at = NOW()",
                    [$session['user_id'], $answer['word_id'], $answer['is_correct'] ? 1 : 0, 
                     $answer['is_correct'] ? 1 : 0, $answer['is_correct'] ? 0 : 1]
                );
            }
        }
        
        $this->sendSuccess([
            'score' => $score,
            'correct_answers' => $correctAnswers,
            'total_questions' => $totalQuestions,
            'percentage' => $score,
            'time_spent' => $timeSpent
        ]);
    }
    
    private function generateQuizQuestions($vocabulary) {
        $questions = [];
        $questionTypes = ['jp_to_vn', 'vn_to_jp', 'romaji_to_jp', 'audio'];
        
        foreach ($vocabulary as $index => $word) {
            $questionType = $questionTypes[$index % count($questionTypes)];
            
            $question = [
                'id' => $word['id'],
                'word_id' => $word['id'],
                'type' => $questionType,
                'word' => $word
            ];
            
            switch ($questionType) {
                case 'jp_to_vn':
                    $question['question_text'] = $word['japanese_word'];
                    $question['question_subtext'] = "({$word['romaji']})";
                    $question['correct_answer'] = $word['vietnamese_meaning'];
                    $question['type_label'] = 'Chọn nghĩa đúng';
                    break;
                    
                case 'vn_to_jp':
                    $question['question_text'] = $word['vietnamese_meaning'];
                    $question['correct_answer'] = $word['japanese_word'];
                    $question['type_label'] = 'Chọn từ tiếng Nhật đúng';
                    break;
                    
                case 'romaji_to_jp':
                    $question['question_text'] = $word['romaji'];
                    $question['question_subtext'] = "Nghĩa: {$word['vietnamese_meaning']}";
                    $question['correct_answer'] = $word['japanese_word'];
                    $question['type_label'] = 'Chọn cách viết đúng';
                    break;
                    
                case 'audio':
                    $question['question_text'] = '🔊 Nghe và chọn từ đúng';
                    $question['audio_text'] = $word['japanese_word'];
                    $question['correct_answer'] = $word['vietnamese_meaning'];
                    $question['type_label'] = 'Câu hỏi nghe';
                    $question['is_audio'] = true;
                    break;
            }
            
            // Generate options (this is simplified - in real app, get from other words)
            $question['options'] = $this->generateQuizOptions($question['correct_answer'], $vocabulary, $questionType);
            
            $questions[] = $question;
        }
        
        return $questions;
    }
    
    private function generateQuizOptions($correctAnswer, $vocabulary, $questionType) {
        // Get potential wrong answers based on question type
        $allOptions = [];
        
        foreach ($vocabulary as $word) {
            switch ($questionType) {
                case 'jp_to_vn':
                case 'audio':
                    $allOptions[] = $word['vietnamese_meaning'];
                    break;
                case 'vn_to_jp':
                case 'romaji_to_jp':
                    $allOptions[] = $word['japanese_word'];
                    break;
            }
        }
        
        // Remove correct answer and get 3 random wrong options
        $wrongOptions = array_filter($allOptions, fn($opt) => $opt !== $correctAnswer);
        $wrongOptions = array_slice(array_values($wrongOptions), 0, 3);
        
        // Add more generic wrong options if needed
        while (count($wrongOptions) < 3) {
            $wrongOptions[] = $questionType === 'jp_to_vn' || $questionType === 'audio' 
                ? 'từ khác' 
                : 'たんご';
        }
        
        // Combine and shuffle options
        $options = array_merge([$correctAnswer], $wrongOptions);
        shuffle($options);
        
        return $options;
    }
    
    private function sendSuccess($data) {
        echo json_encode([
            'success' => true,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }
    
    private function sendError($message, $code = 400) {
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'error' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }
}

// Initialize and handle request
try {
    $api = new N5LessonsAPI();
    $api->handleRequest();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>
