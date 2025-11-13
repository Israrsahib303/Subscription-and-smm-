<?php
// This file is created by install.php
if (!file_exists(__DIR__ . '/config.php')) {
    die('Configuration file not found. Please run install.php');
}
require_once __DIR__ . '/config.php';

// Create a new PDO instance
try {
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // On connection error, show a generic message
    die('Database Connection Error: Could not connect. Please check your config.php');
}
?>