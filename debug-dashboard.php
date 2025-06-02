<?php
// debug-dashboard.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🔍 Debug Dashboard API</h2>";

try {
    echo "<h3>1. Include config</h3>";
    require_once 'php/config.php';
    echo "✅ Config loaded<br>";
    
    echo "<h3>2. Test database</h3>";
    $db = Database::getInstance();
    echo "✅ Database connected<br>";
    
    echo "<h3>3. Check session</h3>";
    echo "Session ID: " . session_id() . "<br>";
    echo "Session data: <pre>" . print_r($_SESSION, true) . "</pre>";
    
    echo "<h3>4. Check login status</h3>";
    if (function_exists('isLoggedIn')) {
        $loggedIn = isLoggedIn();
        echo "Logged in: " . ($loggedIn ? 'Yes' : 'No') . "<br>";
        
        if ($loggedIn) {
            $user = getCurrentUser();
            echo "Current user: <pre>" . print_r($user, true) . "</pre>";
        }
    } else {
        echo "❌ isLoggedIn function not found<br>";
    }
    
    echo "<h3>5. Test dashboard.php directly</h3>";
    $_GET['action'] = 'overview';
    
    ob_start();
    include 'php/dashboard.php';
    $output = ob_get_clean();
    
    echo "Output: <pre>" . htmlspecialchars($output) . "</pre>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "<br>";
    echo "Stack trace: <pre>" . $e->getTraceAsString() . "</pre>";
}
?>
