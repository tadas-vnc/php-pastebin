<?php
require_once 'config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'Method not allowed']));
}

// Get JSON data
$json = file_get_contents('php://input');
$data = json_decode($json, true);
$id = isset($data['id']) ? (int)$data['id'] : 0;

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
$filename = __DIR__ . '/pastes/' . $id . '.txt';
if (file_exists($filename)) {
    unlink($filename);
}

// Delete from database
$stmt = $db->prepare('DELETE FROM pastes WHERE id = :id');
$stmt->bindValue(':id', $id, SQLITE3_INTEGER);
$result = $stmt->execute();

if ($result) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $db->lastErrorMsg()]);
} 