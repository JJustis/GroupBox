<?php
session_start();
require 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    echo 'Not authenticated';
    exit();
}

if (isset($_GET['file_id'])) {
    $file_id = $_GET['file_id'];
    $user_id = $_SESSION['user_id'];

    // Check if the user has access to the file
    $stmt = $pdo->prepare('SELECT 1 FROM files f 
                           JOIN group_members gm ON f.group_id = gm.group_id 
                           WHERE f.id = ? AND gm.user_id = ?');
    $stmt->execute([$file_id, $user_id]);

    if ($stmt->fetch()) {
        // Fetch edit history
        $stmt = $pdo->prepare('SELECT fe.edit_time, u.username
                               FROM file_edits fe
                               JOIN users u ON fe.user_id = u.id
                               WHERE fe.file_id = ?
                               ORDER BY fe.edit_time DESC
                               LIMIT 10');
        $stmt->execute([$file_id]);
        $edits = $stmt->fetchAll();

        if (count($edits) > 0) {
            echo '<ul class="text-sm text-gray-600">';
            foreach ($edits as $edit) {
                echo '<li>' . htmlspecialchars($edit['username']) . ' - ' . date('Y-m-d H:i', strtotime($edit['edit_time'])) . '</li>';
            }
            echo '</ul>';
        } else {
            echo 'No edit history available.';
        }
    } else {
        echo 'You don\'t have permission to view this file\'s edit history.';
    }
} else {
    echo 'No file specified.';
}