<?php
// File: quick-debug-category.php
// Debug nhanh để so sánh category 1 vs category 2

require_once 'php/config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    die("Please login first");
}

$userId = $_SESSION['user_id'];
$db = Database::getInstance();

echo "<h2>🔍 Quick Debug: Category 1 vs Category 2</h2>";

// Function to debug category
function debugCategory($db, $userId, $categoryId) {
    echo "<h3>📊 Category {$categoryId} Debug:</h3>";
    
    // 1. Check category exists and active
    $category = $db->fetchOne(
        "SELECT * FROM vocabulary_categories WHERE id = ? AND is_active = true",
        [$categoryId]
    );
    
    if (!$category) {
        echo "<p style='color: red;'>❌ Category {$categoryId} not found or inactive</p>";
        return;
    }
    
    echo "<p>✅ Category: {$category['category_name']}</p>";
    
    // 2. Check unlock status
    $progress = $db->fetchOne(
        "SELECT * FROM user_category_progress WHERE user_id = ? AND category_id = ?",
        [$userId, $categoryId]
    );
    
    if (!$progress) {
        echo "<p style='color: orange;'>⚠️ No progress record - creating one...</p>";
        $db->query(
            "INSERT INTO user_category_progress (user_id, category_id, is_unlocked, created_at) 
             VALUES (?, ?, true, NOW())",
            [$userId, $categoryId]
        );
        $progress = $db->fetchOne(
            "SELECT * FROM user_category_progress WHERE user_id = ? AND category_id = ?",
            [$userId, $categoryId]
        );
    }
    
    $unlocked = $progress['is_unlocked'] ? '🔓 Unlocked' : '🔒 Locked';
    echo "<p>{$unlocked}</p>";
    
    if (!$progress['is_unlocked']) {
        echo "<p style='color: orange;'>⚠️ Unlocking category for debug...</p>";
        $db->query(
            "UPDATE user_category_progress SET is_unlocked = true WHERE user_id = ? AND category_id = ?",
            [$userId, $categoryId]
        );
    }
    
    // 3. Count total words
    $totalWords = $db->fetchOne(
        "SELECT COUNT(*) as count FROM vocabulary_words WHERE category_id = ? AND is_active = true",
        [$categoryId]
    )['count'];
    
    echo "<p>📝 Total words: {$totalWords}</p>";
    
    // 4. Count learned words (có trong user_word_knowledge)
    $learnedWords = $db->fetchOne(
        "SELECT COUNT(*) as count 
         FROM user_word_knowledge uwk
         JOIN vocabulary_words vw ON uwk.word_id = vw.id
         WHERE uwk.user_id = ? AND vw.category_id = ?",
        [$userId, $categoryId]
    )['count'];
    
    echo "<p>🎯 Learned words: {$learnedWords}</p>";
    
    // 5. Count NEW words (chưa học)
    $newWords = $db->fetchAll(
        "SELECT vw.id, vw.japanese_word, vw.vietnamese_meaning
         FROM vocabulary_words vw
         LEFT JOIN user_word_knowledge uwk ON vw.id = uwk.word_id AND uwk.user_id = ?
         WHERE vw.category_id = ? AND vw.is_active = true AND uwk.id IS NULL
         LIMIT 5",
        [$userId, $categoryId]
    );
    
    echo "<p>🆕 New words: " . count($newWords) . "</p>";
    
    if (count($newWords) > 0) {
        echo "<details><summary>Sample new words:</summary>";
        foreach ($newWords as $word) {
            echo "<p>- {$word['japanese_word']} = {$word['vietnamese_meaning']}</p>";
        }
        echo "</details>";
    }
    
    // 6. Test exact API query
    echo "<h4>🔍 API Query Test:</h4>";
    
    // Query theo mode 'new'
    $apiWords = $db->fetchAll(
        "SELECT vw.*, 
                COALESCE(uwk.knowledge_level, 0) as knowledge_level,
                vc.category_name, vc.category_icon, vc.category_color
         FROM vocabulary_words vw
         LEFT JOIN user_word_knowledge uwk ON vw.id = uwk.word_id AND uwk.user_id = ?
         JOIN vocabulary_categories vc ON vw.category_id = vc.id
         WHERE vw.category_id = ? AND vw.is_active = true AND uwk.id IS NULL
         ORDER BY vw.display_order, vw.frequency_rank
         LIMIT 50",
        [$userId, $categoryId]
    );
    
    echo "<p>📊 API result (mode=new): " . count($apiWords) . " words</p>";
    
    // Query theo mode 'all'
    $allApiWords = $db->fetchAll(
        "SELECT vw.*, 
                COALESCE(uwk.knowledge_level, 0) as knowledge_level,
                vc.category_name, vc.category_icon, vc.category_color
         FROM vocabulary_words vw
         LEFT JOIN user_word_knowledge uwk ON vw.id = uwk.word_id AND uwk.user_id = ?
         JOIN vocabulary_categories vc ON vw.category_id = vc.id
         WHERE vw.category_id = ? AND vw.is_active = true
         ORDER BY vw.display_order, vw.frequency_rank
         LIMIT 50",
        [$userId, $categoryId]
    );
    
    echo "<p>📊 API result (mode=all): " . count($allApiWords) . " words</p>";
    
    // 7. Show sample learned words if any
    if ($learnedWords > 0) {
        $sampleLearned = $db->fetchAll(
            "SELECT vw.japanese_word, vw.vietnamese_meaning, uwk.knowledge_level
             FROM user_word_knowledge uwk
             JOIN vocabulary_words vw ON uwk.word_id = vw.id
             WHERE uwk.user_id = ? AND vw.category_id = ?
             LIMIT 5",
            [$userId, $categoryId]
        );
        
        echo "<details><summary>Sample learned words:</summary>";
        foreach ($sampleLearned as $word) {
            echo "<p>- {$word['japanese_word']} = {$word['vietnamese_meaning']} (Level: {$word['knowledge_level']})</p>";
        }
        echo "</details>";
    }
    
    echo "<hr>";
}

// Debug both categories
debugCategory($db, $userId, 1);
debugCategory($db, $userId, 2);

// Quick fix option
echo "<h3>🛠️ Quick Fix Options:</h3>";
echo "<a href='?action=reset_category_1' style='background: red; color: white; padding: 10px; text-decoration: none; border-radius: 5px;'>🔄 Reset Category 1 Progress</a> ";
echo "<a href='?action=unlock_all' style='background: blue; color: white; padding: 10px; text-decoration: none; border-radius: 5px;'>🔓 Unlock All Categories</a>";

// Handle quick fix actions
if (isset($_GET['action'])) {
    echo "<hr>";
    
    if ($_GET['action'] === 'reset_category_1') {
        echo "<h4>🔄 Resetting Category 1...</h4>";
        
        // Delete all word knowledge for category 1
        $db->query(
            "DELETE uwk FROM user_word_knowledge uwk
             JOIN vocabulary_words vw ON uwk.word_id = vw.id
             WHERE uwk.user_id = ? AND vw.category_id = 1",
            [$userId]
        );
        
        // Reset category progress
        $db->query(
            "UPDATE user_category_progress 
             SET learned_words = 0, mastered_words = 0, completion_percentage = 0, is_completed = false
             WHERE user_id = ? AND category_id = 1",
            [$userId]
        );
        
        echo "<p style='color: green;'>✅ Category 1 reset! Now try the API again.</p>";
        echo "<a href='?'>🔍 Debug Again</a>";
        
    } elseif ($_GET['action'] === 'unlock_all') {
        echo "<h4>🔓 Unlocking all categories...</h4>";
        
        $db->query(
            "UPDATE user_category_progress SET is_unlocked = true WHERE user_id = ?",
            [$userId]
        );
        
        echo "<p style='color: green;'>✅ All categories unlocked!</p>";
        echo "<a href='?'>🔍 Debug Again</a>";
    }
}

echo "<hr>";
echo "<p><strong>🌐 Test URLs:</strong></p>";
echo "<a href='/php/vocabulary-api.php?action=get_category_words&category_id=1&mode=new&limit=50' target='_blank'>API Category 1 (mode=new)</a><br>";
echo "<a href='/php/vocabulary-api.php?action=get_category_words&category_id=1&mode=all&limit=50' target='_blank'>API Category 1 (mode=all)</a><br>";
echo "<a href='/php/vocabulary-api.php?action=get_category_words&category_id=2&mode=new&limit=50' target='_blank'>API Category 2 (mode=new)</a><br>";
echo "<a href='/vocabulary-study.html?category_id=1' target='_blank'>Study Page Category 1</a><br>";
echo "<a href='/vocabulary-study.html?category_id=2' target='_blank'>Study Page Category 2</a><br>";
?>
