<?php
session_start();
require 'db_connection.php';

if (!isset($_SESSION['user_id']) || !isset($_FILES['file']) || !isset($_POST['group_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$user_id = $_SESSION['user_id'];
$group_id = $_POST['group_id'];
$uploadDir = 'uploads/' . $group_id . '/';

if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$filename = basename($_FILES['file']['name']);
$uploadFile = $uploadDir . $filename;

// Check if file already exists
$stmt = $pdo->prepare('SELECT id FROM files WHERE filename = ? AND group_id = ?');
$stmt->execute([$filename, $group_id]);
$existingFile = $stmt->fetch();

if ($existingFile) {
    echo json_encode(['success' => false, 'message' => 'File with the same name already exists.']);
    exit;
}

if (move_uploaded_file($_FILES['file']['tmp_name'], $uploadFile)) {
    $stmt = $pdo->prepare('INSERT INTO files (filename, group_id, uploader_id) VALUES (?, ?, ?)');
    $stmt->execute([$filename, $group_id, $user_id]);
    $file_id = $pdo->lastInsertId();

    $stmt = $pdo->prepare('SELECT f.id, f.filename, f.group_id, u.username as uploader, f.upload_time 
                           FROM files f 
                           JOIN users u ON f.uploader_id = u.id 
                           WHERE f.id = ?');
    $stmt->execute([$file_id]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'file' => $file]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to upload file.']);
}