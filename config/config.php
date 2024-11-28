<?php
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Create/connect to database
$db = new SQLite3(__DIR__ . '/../database.sqlite');
if (!$db) {
    die('Database connection failed: ' . $db->lastErrorMsg());
}

// Enable foreign keys
$db->exec('PRAGMA foreign_keys = ON');

// Create the pastes table with the correct structure
$result = $db->exec('
    CREATE TABLE IF NOT EXISTS pastes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        content TEXT NOT NULL,
        file_path TEXT NOT NULL,
        user_id INTEGER,
        ip_address TEXT NOT NULL,
        is_public BOOLEAN DEFAULT 1,
        language TEXT DEFAULT "plaintext",
        theme TEXT DEFAULT "default",
        is_encrypted BOOLEAN DEFAULT 0,
        password_hash TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )
');

// Create pastes directory if it doesn't exist
if (!file_exists(__DIR__ . '/../pastes')) {
    mkdir(__DIR__ . '/../pastes', 0755, true);
}

$isDark = isset($_COOKIE['darkMode']) && $_COOKIE['darkMode'] === 'true';

function redirect($path) {
    header("Location: $path");
    exit();
}
?>