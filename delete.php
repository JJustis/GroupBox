<?php
session_start();
require 'db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['file_id']) && isset($_POST['group_id'])) {
    $file_id = $_POST['file_id'];
    $group_id = $_POST['group_id'];
    $user_id = $_SESSION['user_id'];

    try {
        $pdo->beginTransaction();

        // Check if the user is a member of the group
        $stmt = $pdo->prepare('SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ?');
        $stmt->execute([$group_id, $user_id]);

        if (!$stmt->fetch()) {
            throw new Exception('You don\'t have permission to delete files in this group');
        }

        // Get the filename
        $stmt = $pdo->prepare('SELECT filename FROM files WHERE id = ? AND group_id = ?');
        $stmt->execute([$file_id, $group_id]);
        $file = $stmt->fetch();

        if (!$file) {
            throw new Exception('File not found');
        }

        $filepath = 'uploads/' . $group_id . '/' . $file['filename'];

        // Delete associated edit history first
        $stmt = $pdo->prepare('DELETE FROM file_edits WHERE file_id = ?');
        $stmt->execute([$file_id]);

        // Now delete the file from the database
        $stmt = $pdo->prepare('DELETE FROM files WHERE id = ?');
        $stmt->execute([$file_id]);

        // Delete the file from the filesystem
        if (file_exists($filepath) && !unlink($filepath)) {
            throw new Exception('Failed to delete file from filesystem');
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'File deleted successfully']);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Failed to delete file: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}