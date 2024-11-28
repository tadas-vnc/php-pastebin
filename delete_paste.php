<?php
require_once 'config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Method not allowed');
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

// Get paste info
$stmt = $db->prepare('SELECT * FROM pastes WHERE id = :id');
$stmt->bindValue(':id', $id, SQLITE3_INTEGER);
$result = $stmt->execute();
$paste = $result->fetchArray(SQLITE3_ASSOC);

if (!$paste) {
    die(json_encode(['success' => false, 'message' => 'Paste not found']));
}

// Check if user has permission to delete
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != $paste['user_id']) {
    die(json_encode(['success' => false, 'message' => 'Permission denied']));
}

// Delete the file
if (file_exists(__DIR__ . '/' . $paste['file_path'])) {
    unlink(__DIR__ . '/' . $paste['file_path']);
}

// Delete from database
$stmt = $db->prepare('DELETE FROM pastes WHERE id = :id');
$stmt->bindValue(':id', $id, SQLITE3_INTEGER);
$result = $stmt->execute();

echo json_encode(['success' => true]); 