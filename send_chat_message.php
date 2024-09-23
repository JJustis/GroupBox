<?php
session_start();
require 'db_connection.php';

if (!isset($_SESSION['user_id']) || !isset($_POST['group_id']) || !isset($_POST['message'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$user_id = $_SESSION['user_id'];
$group_id = $_POST['group_id'];
$message = $_POST['message'];

$stmt = $pdo->prepare('INSERT INTO group_messages (group_id, user_id, message) VALUES (?, ?, ?)');
if ($stmt->execute([$group_id, $user_id, $message])) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to send message']);
}