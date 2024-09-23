<?php
session_start();
require 'db_connection.php';
if (!isset($_SESSION['user_id']) || !isset($_POST['file_id']) || !isset($_POST['content'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}
$user_id = $_SESSION['user_id'];
$file_id = $_POST['file_id'];
$content = $_POST['content'];
try {
    $pdo->beginTransaction();
    
    // Get file information
    $stmt = $pdo->prepare('SELECT filename, group_id FROM files WHERE id = ?');
    $stmt->execute([$file_id]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$file) {
        throw new Exception('File not found');
    }
    
    // Generate new filename for the version
    $file_info = pathinfo($file['filename']);
    $base_name = preg_replace('/_v\d+$/', '', $file_info['filename']); // Remove any existing version suffix
    $version_number = time(); // Using timestamp as version number
    $new_filename = $base_name . '.' . $file_info['extension'];
    $new_filepath = 'uploads/' . $file['group_id'] . '/' . $new_filename;
    
    // Save content to new file
    if (file_put_contents($new_filepath, $content) === false) {
        throw new Exception('Failed to write to file');
    }
    
    // Update file record in database
    $stmt = $pdo->prepare('UPDATE files SET filename = ?, content = ? WHERE id = ?');
    $stmt->execute([$new_filename, $content, $file_id]);
    
    // Add edit history
    $stmt = $pdo->prepare('INSERT INTO file_edits (file_id, user_id, version) VALUES (?, ?, ?)');
    $stmt->execute([$file_id, $user_id, $version_number]);
    
    // Get last edit information
    $stmt = $pdo->prepare('SELECT u.username, fe.edit_time 
                           FROM file_edits fe 
                           JOIN users u ON fe.user_id = u.id 
                           WHERE fe.file_id = ? 
                           ORDER BY fe.edit_time DESC 
                           LIMIT 1');
    $stmt->execute([$file_id]);
    $lastEdit = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'lastEditTime' => date('Y-m-d H:i', strtotime($lastEdit['edit_time'])),
        'lastEditor' => $lastEdit['username'],
        'newFilename' => $new_filename,
        'version' => $version_number
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Failed to save file: ' . $e->getMessage()]);
}