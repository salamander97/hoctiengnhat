<?php
// File: test-api-direct.php
// Test API trực tiếp với đúng format

header('Content-Type: application/json');

session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

// Simulate the exact request from vocabulary-study.js
$_SERVER['REQUEST_METHOD'] = 'POST';
$_GET['action'] = 'update_word_knowledge';

// Simulate JSON body
$testData = [
    'word_id' => 10,
    'is_correct' => true,
    'difficulty_rating' => 4,
    'study_time' => 5
];

// Override php://input
$GLOBALS['mock_input'] = json_encode($testData);

// Mock file_get_contents for php://input
function file_get_contents($filename) {
    if ($filename === 'php://input') {
        return $GLOBALS['mock_input'];
    }
    return \file_get_contents($filename);
}

try {
    require_once 'php/vocabulary-api.php';
    
    $api = new VocabularyAPI();
    $api->handleRequest();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Exception: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
} catch (Error $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Fatal Error: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>
