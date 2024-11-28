<?php
session_start();

// Create/connect to database
$db = new SQLite3(__DIR__ . '/../database.sqlite');

// Create users table if it doesn't exist
$db->exec('
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        email TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )
');

function redirect($path) {
    header("Location: $path");
    exit();
}
?>