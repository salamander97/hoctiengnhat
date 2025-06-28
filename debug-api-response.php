<?php
// File: debug-api-response.php
// Debug API response để tìm lỗi

require_once 'php/config.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    die("Please login first");
}

$userId = $_SESSION['user_id'];

// Test API call giống như vocabulary-study.js
$testData = [
    'word_id' => 10,
    'is_correct' => true,
    'difficulty_rating' => 4,
    'study_time' => 5
];

echo "<h2>🔍 Debug API Response</h2>";
echo "<h3>Request Data:</h3>";
echo "<pre>" . json_encode($testData, JSON_PRETTY_PRINT) . "</pre>";

// Method 1: Test via cURL
echo "<h3>1. Test via cURL:</h3>";

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => 'http://localhost/php/vocabulary-api.php?action=update_word_knowledge',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($testData),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Cookie: ' . session_name() . '=' . session_id()
    ],
    CURLOPT_VERBOSE => true
]);

$response = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$error = curl_error($curl);
curl_close($curl);

echo "<p><strong>HTTP Code:</strong> $httpCode</p>";
if ($error) {
    echo "<p><strong>cURL Error:</strong> $error</p>";
}

echo "<p><strong>Raw Response:</strong></p>";
echo "<pre>" . htmlspecialchars($response) . "</pre>";

// Try to decode JSON
$decodedResponse = json_decode($response, true);
if (json_last_error() === JSON_ERROR_NONE) {
    echo "<p><strong>✅ Valid JSON:</strong></p>";
    echo "<pre>" . json_encode($decodedResponse, JSON_PRETTY_PRINT) . "</pre>";
} else {
    echo "<p><strong>❌ JSON Error:</strong> " . json_last_error_msg() . "</p>";
    
    // Look for PHP errors
    if (strpos($response, 'Fatal error') !== false) {
        echo "<p style='color: red;'><strong>🚨 PHP Fatal Error detected!</strong></p>";
    }
    if (strpos($response, 'Parse error') !== false) {
        echo "<p style='color: red;'><strong>🚨 PHP Parse Error detected!</strong></p>";
    }
    if (strpos($response, 'Warning') !== false) {
        echo "<p style='color: orange;'><strong>⚠️ PHP Warning detected!</strong></p>";
    }
}

// Method 2: Test directly
echo "<hr><h3>2. Test Direct Function Call:</h3>";

try {
    require_once 'php/vocabulary-api.php';
    
    // Simulate POST data
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = $testData;
    
    ob_start(); // Capture output
    
    $api = new VocabularyAPI();
    // Don't call handleRequest as it will exit
    // Instead test the function directly if possible
    
    $output = ob_get_clean();
    
    echo "<p><strong>Direct call output:</strong></p>";
    echo "<pre>" . htmlspecialchars($output) . "</pre>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>❌ Direct call error:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>Stack trace:</strong></p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

// Method 3: Check if functions exist
echo "<hr><h3>3. Function Existence Check:</h3>";

$functionsToCheck = [
    'updateWordKnowledge',
    'updateCategoryProgress', 
    'forceUpdateAllUnlocks',
    'logActivity'
];

foreach ($functionsToCheck as $func) {
    if (method_exists('VocabularyAPI', $func)) {
        echo "<p>✅ Method VocabularyAPI::$func exists</p>";
    } else {
        echo "<p>❌ Method VocabularyAPI::$func NOT found</p>";
    }
}

// Method 4: Check error logs
echo "<hr><h3>4. Recent PHP Error Logs:</h3>";
$errorLog = ini_get('error_log');
if ($errorLog && file_exists($errorLog)) {
    $errors = file_get_contents($errorLog);
    $recentErrors = array_slice(explode("\n", $errors), -20); // Last 20 lines
    echo "<pre>" . htmlspecialchars(implode("\n", $recentErrors)) . "</pre>";
} else {
    echo "<p>No error log file found or accessible</p>";
}

// Method 5: Check database connection
echo "<hr><h3>5. Database Connection Test:</h3>";
try {
    $db = Database::getInstance();
    $test = $db->fetchOne("SELECT 1 as test");
    if ($test && $test['test'] == 1) {
        echo "<p>✅ Database connection OK</p>";
    } else {
        echo "<p>❌ Database query failed</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Database error: " . $e->getMessage() . "</p>";
}

?>

<hr>
<h3>💡 Common Solutions:</h3>
<ul>
    <li><strong>Method not found:</strong> Check if updateCategoryProgress() or forceUpdateAllUnlocks() exists</li>
    <li><strong>Database error:</strong> Check database connection and table structure</li>
    <li><strong>Parse error:</strong> Check PHP syntax in vocabulary-api.php</li>
    <li><strong>Fatal error:</strong> Check function signatures and parameter types</li>
</ul>

<p><a href="javascript:history.back()">← Go Back</a></p>
