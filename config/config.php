<?php
// Database connection for XAMPP (localhost)
// IMPORTANT: Use the exact database name you created in phpMyAdmin

$host = 'localhost';
$dbname = 'clearance_system'; // ← CHANGE THIS to match your database name
$username = 'root';
$password = ''; // Leave empty for XAMPP default

try {
    $pdo = new PDO(
        "mysql:host=" . $host . ";dbname=" . $dbname . ";charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>