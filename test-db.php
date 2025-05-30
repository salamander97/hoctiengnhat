<?php
try {
    $pdo = new PDO("pgsql:host=localhost;dbname=japanese_learning", "your_username", "your_password");
    echo "✅ Database connection successful!";
    
    $stmt = $pdo->query("SELECT version()");
    $version = $stmt->fetch();
    echo "<br>PostgreSQL version: " . $version['version'];
} catch(PDOException $e) {
    echo "❌ Database error: " . $e->getMessage();
}
?>
