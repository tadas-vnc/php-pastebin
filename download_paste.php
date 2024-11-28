<?php
require_once 'config/config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get paste
$stmt = $db->prepare('
    SELECT p.*, u.username 
    FROM pastes p 
    LEFT JOIN users u ON p.user_id = u.id 
    WHERE p.id = :id
');
$stmt->bindValue(':id', $id, SQLITE3_INTEGER);
$result = $stmt->execute();
$paste = $result->fetchArray(SQLITE3_ASSOC);

if (!$paste) {
    die('Paste not found');
}

// Check access
if (!$paste['is_public'] && 
    (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != $paste['user_id']) &&
    $_SERVER['REMOTE_ADDR'] != $paste['ip_address']) {
    die('Access denied');
}

// Read content
$content = file_get_contents(__DIR__ . '/' . $paste['file_path']);
if ($paste['is_encrypted']) {
    if (!isset($_POST['password'])) {
        die('Password required');
    }
    $key = hash('sha256', $_POST['password'], true);
    $content = openssl_decrypt($content, 'AES-256-CBC', $key, 0, substr($key, 0, 16));
}

// Set headers for download
header('Content-Type: text/plain');
header('Content-Disposition: attachment; filename="' . $paste['title'] . '.txt"');
header('Content-Length: ' . strlen($content));

// Output content
echo $content; 