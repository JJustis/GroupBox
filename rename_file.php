<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Function to output JSON response and exit
function json_response($success, $message, $debug = null) {
    $response = ['success' => $success, 'message' => $message];
    if ($debug !== null) {
        $response['debug'] = $debug;
    }
    echo json_encode($response);
    exit;
}

// Set the content type to JSON
header('Content-Type: application/json');

// Initialize debug information
$debug_info = [];

try {
    $debug_info[] = "Script started";

    // Function to sanitize input
    function sanitize_input($input) {
        return htmlspecialchars(strip_tags(trim($input)));
    }

    // Check if the request method is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $debug_info[] = "Request method is POST";

    // Get and sanitize input
    $file_id = isset($_POST['file_id']) ? sanitize_input($_POST['file_id']) : '';
    $new_filename = isset($_POST['new_filename']) ? sanitize_input($_POST['new_filename']) : '';

    $debug_info[] = "Received parameters: file_id=$file_id, new_filename=$new_filename";

    // Validate input
    if (empty($file_id) || empty($new_filename)) {
        throw new Exception('Missing required parameters');
    }

    if (!is_numeric($file_id)) {
        throw new Exception('Invalid file_id format');
    }

    $debug_info[] = "Input validation passed";

    // Database connection details
    $db_host = 'localhost';
    $db_user = 'root';
    $db_pass = '';
    $db_name = 'reservesphp';

    // Create database connection
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

    // Check connection
    if ($conn->connect_error) {
        throw new Exception('Database connection failed: ' . $conn->connect_error);
    }

    $debug_info[] = "Database connection successful";

    // Get table structure
    $result = $conn->query("DESCRIBE files");
    $table_structure = [];
    while ($row = $result->fetch_assoc()) {
        $table_structure[] = $row['Field'] . ' (' . $row['Type'] . ')';
    }
    $debug_info[] = "Table structure: " . implode(', ', $table_structure);

    // Get total number of rows in the files table
    $total_rows = $conn->query("SELECT COUNT(*) FROM files")->fetch_row()[0];
    $debug_info[] = "Total rows in files table: $total_rows";

    // Get the range of file_id values
    $id_range = $conn->query("SELECT MIN(id) as min_id, MAX(id) as max_id FROM files")->fetch_assoc();
    $debug_info[] = "ID range: min=" . $id_range['min_id'] . ", max=" . $id_range['max_id'];

    // Prepare SQL statement to get the current filename
    $stmt = $conn->prepare("SELECT filename FROM files WHERE id = ?");
    if (!$stmt) {
        throw new Exception('Prepare statement failed: ' . $conn->error);
    }
    $stmt->bind_param("i", $file_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $debug_info[] = "SQL query executed: SELECT filename FROM files WHERE id = $file_id";

    if ($result->num_rows === 0) {
        // Add more debug information about the query
        $debug_info[] = "No rows returned from the query";
        
        // Check if the file_id exists in the table
        $check_id = $conn->query("SELECT COUNT(*) FROM files WHERE id = $file_id")->fetch_row()[0];
        $debug_info[] = "Files with id $file_id: $check_id";

        throw new Exception('File not found in database');
    }

    $row = $result->fetch_assoc();
    $current_filename = $row['filename'];

    $debug_info[] = "Current filename retrieved: $current_filename";

    // Close the first prepared statement
    $stmt->close();

    // Get file extension
    $file_extension = pathinfo($current_filename, PATHINFO_EXTENSION);

    // Append the original extension to the new filename if it's not already there
    if (pathinfo($new_filename, PATHINFO_EXTENSION) !== $file_extension) {
        $new_filename .= '.' . $file_extension;
    }

    $debug_info[] = "New filename with extension: $new_filename";

    // Prepare SQL statement to update the filename
    $stmt = $conn->prepare("UPDATE files SET filename = ? WHERE id = ?");
    if (!$stmt) {
        throw new Exception('Prepare statement failed: ' . $conn->error);
    }
    $stmt->bind_param("si", $new_filename, $file_id);

    // Execute the statement
    if ($stmt->execute()) {
        $debug_info[] = "Database update successful";
        $debug_info[] = "Rows affected: " . $stmt->affected_rows;
        
        // Rename the actual file on the server
        $upload_dir = '/uploads/'; // Replace with your actual upload directory
        $old_path = $upload_dir . $current_filename;
        $new_path = $upload_dir . $new_filename;
        
        if (file_exists($old_path)) {
            if (rename($old_path, $new_path)) {
                $debug_info[] = "File renamed successfully on the server";
                json_response(true, 'File renamed successfully', $debug_info);
            } else {
                throw new Exception('Database updated but failed to rename physical file: ' . error_get_last()['message']);
            }
        } else {
            $debug_info[] = "Physical file not found: $old_path";
            throw new Exception('Database updated but physical file not found');
        }
    } else {
        throw new Exception('Failed to rename file in database: ' . $stmt->error);
    }

    // Close the statement and database connection
    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    $debug_info[] = "Exception caught: " . $e->getMessage();
    json_response(false, $e->getMessage(), $debug_info);
}

// If we reach here, something unexpected happened
json_response(false, 'An unexpected error occurred', $debug_info);
?>