<?php
session_start();
require 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    exit('Not authenticated');
}

if (isset($_GET['file_id'])) {
    $file_id = $_GET['file_id'];
    $user_id = $_SESSION['user_id'];

    // Check if the user has access to the file
    $stmt = $pdo->prepare('SELECT f.filename, f.group_id FROM files f 
                           JOIN group_members gm ON f.group_id = gm.group_id 
                           WHERE f.id = ? AND gm.user_id = ?');
    $stmt->execute([$file_id, $user_id]);
    $file = $stmt->fetch();

    if ($file) {
        $filepath = 'uploads/' . $file['group_id'] . '/' . $file['filename'];
        if (file_exists($filepath)) {
            $content = file_get_contents($filepath);
            echo $content;
        } else {
            header('HTTP/1.1 404 Not Found');
            echo 'File not found';
        }
    } else {
        header('HTTP/1.1 403 Forbidden');
        echo 'You don\'t have permission to access this file';
    }
} else {
    header('HTTP/1.1 400 Bad Request');
    echo 'No file specified';
}