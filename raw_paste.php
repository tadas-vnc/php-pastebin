<?php
require_once 'config/config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get paste
$stmt = $db->prepare('SELECT * FROM pastes WHERE id = :id');
$stmt->bindValue(':id', $id, SQLITE3_INTEGER);
$result = $stmt->execute();
$paste = $result->fetchArray(SQLITE3_ASSOC);

if (!$paste) {
    die('Paste not found');
}

// Check if paste is private and user has access
if (!$paste['is_public'] && 
    (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != $paste['user_id']) &&
    $_SERVER['REMOTE_ADDR'] != $paste['ip_address']) {
    die('Access denied');
}

// Handle password protection
if ($paste['password_hash']) {
    if (!isset($_SESSION['paste_access_' . $id])) {
        die('Password required');
    }
}

// Read content
$content = file_get_contents(__DIR__ . '/' . $paste['file_path']);
if ($paste['is_encrypted']) {
    if (!isset($_POST['password'])) {
        die('Password required for encrypted content');
    }
    $key = hash('sha256', $_POST['password'], true);
    $content = openssl_decrypt($content, 'AES-256-CBC', $key, 0, substr($key, 0, 16));
}

// Output raw content
header('Content-Type: text/plain');
echo $content;
?> 