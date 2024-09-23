<?php
session_start();
require 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if (isset($_GET['file']) && isset($_GET['group_id'])) {
    $filename = $_GET['file'];
    $group_id = $_GET['group_id'];
    $user_id = $_SESSION['user_id'];

    // Check if the user is a member of the group
    $stmt = $pdo->prepare('SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ?');
    $stmt->execute([$group_id, $user_id]);

    if ($stmt->fetch()) {
        $filepath = 'uploads/' . $group_id . '/' . $filename;

        if (file_exists($filepath)) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="'.basename($filepath).'"');
            header('Content-Length: ' . filesize($filepath));
            readfile($filepath);
            exit;
        } else {
            echo "File not found.";
        }
    } else {
        echo "You don't have permission to access this file.";
    }
} else {
    echo "No file specified or invalid group.";
}