<?php
session_start();
require 'db_connection.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['group_id']) || !isset($_GET['last_id'])) {
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$group_id = $_GET['group_id'];
$last_id = $_GET['last_id'];

$stmt = $pdo->prepare('SELECT m.id, m.message, m.timestamp, u.username 
                       FROM group_messages m 
                       JOIN users u ON m.user_id = u.id 
                       WHERE m.group_id = ? AND m.id > ? 
                       ORDER BY m.timestamp ASC');
$stmt->execute([$group_id, $last_id]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($messages);