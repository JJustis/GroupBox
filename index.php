<?php
session_start();
require 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];


// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit();
}
// Handle group creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_group'])) {
    $group_name = $_POST['group_name'];
    
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('INSERT INTO groups (name, creator_id) VALUES (?, ?)');
        $stmt->execute([$group_name, $user_id]);

        $group_id = $pdo->lastInsertId();

        // Add creator to the group
        $stmt = $pdo->prepare('INSERT INTO group_members (group_id, user_id) VALUES (?, ?)');
        $stmt->execute([$group_id, $user_id]);

        $pdo->commit();
        $message = "Group created successfully.";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Error creating group: " . $e->getMessage();
    }
}

// Get user's groups
$stmt = $pdo->prepare('SELECT g.id, g.name FROM groups g JOIN group_members gm ON g.id = gm.group_id WHERE gm.user_id = ?');
$stmt->execute([$user_id]);
$groups = $stmt->fetchAll();

// Pagination and search for files
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;
$search = isset($_GET['search']) ? $_GET['search'] : '';

$group_files = [];
if (isset($_GET['group_id'])) {
    $stmt = $pdo->prepare('SELECT f.id, f.filename, u.username as uploader, f.upload_time,
                           COALESCE(le.edit_time, f.upload_time) as last_edit_time, 
                           COALESCE(eu.username, u.username) as last_editor
                           FROM files f 
                           JOIN users u ON f.uploader_id = u.id 
                           LEFT JOIN (
                               SELECT file_id, MAX(edit_time) as max_edit_time
                               FROM file_edits
                               GROUP BY file_id
                           ) le_max ON f.id = le_max.file_id
                           LEFT JOIN file_edits le ON le_max.file_id = le.file_id AND le_max.max_edit_time = le.edit_time
                           LEFT JOIN users eu ON le.user_id = eu.id
                           WHERE f.group_id = ? 
                           AND f.filename LIKE ?
                           ORDER BY last_edit_time DESC
                           LIMIT ? OFFSET ?');
    $stmt->execute([$_GET['group_id'], '%' . $search . '%', $perPage, $offset]);
    $group_files = $stmt->fetchAll();

    // Get total count for pagination
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM files WHERE group_id = ? AND filename LIKE ?');
    $stmt->execute([$_GET['group_id'], '%' . $search . '%']);
    $totalFiles = $stmt->fetch()['count'];
    $totalPages = ceil($totalFiles / $perPage);
}

// Get group members
$group_members = [];
if (isset($_GET['group_id'])) {
    $stmt = $pdo->prepare('SELECT u.id, u.username FROM users u JOIN group_members gm ON u.id = gm.user_id WHERE gm.group_id = ?');
    $stmt->execute([$_GET['group_id']]);
    $group_members = $stmt->fetchAll();
}

// Handle adding a new member to the group
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_member']) && isset($_POST['group_id']) && isset($_POST['username'])) {
    $group_id = $_POST['group_id'];
    $username = $_POST['username'];

    // Check if the user exists
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user) {
        // Check if the user is already a member of the group
        $stmt = $pdo->prepare('SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ?');
        $stmt->execute([$group_id, $user['id']]);

        if (!$stmt->fetch()) {
            // Add the user to the group
            $stmt = $pdo->prepare('INSERT INTO group_members (group_id, user_id) VALUES (?, ?)');
            $stmt->execute([$group_id, $user['id']]);
            $message = "User added to the group successfully.";
        } else {
            $error = "User is already a member of this group.";
        }
    } else {
        $error = "User not found.";
    }
}

// Function to get chat messages
function getChatMessages($groupId, $lastMessageId = 0) {
    global $pdo;
    $stmt = $pdo->prepare('SELECT m.id, m.message, m.timestamp, u.username 
                           FROM group_messages m 
                           JOIN users u ON m.user_id = u.id 
                           WHERE m.group_id = ? AND m.id > ? 
                           ORDER BY m.timestamp ASC');
    $stmt->execute([$groupId, $lastMessageId]);
    return $stmt->fetchAll();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GroupBox</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        .edit-modal, .rename-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }
        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 600px;
        }
        .chat-container {
            height: 300px;
            overflow-y: auto;
            border: 1px solid #ced4da;
            padding: 10px;
            margin-bottom: 10px;
        }
        .chat-message {
            margin-bottom: 10px;
        }
        .chat-username {
            font-weight: bold;
        }
        .chat-timestamp {
            font-size: 0.8em;
            color: #6c757d;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto p-4">
	        <div class="flex justify-between items-center mb-4">
            <h1 class="text-3xl font-bold">GroupBox</h1>
            <a href="?logout=1" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">
                Logout
            </a>
        </div>
        
        <?php if (isset($message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4" role="alert">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4" role="alert">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div class="flex space-x-4">
            <div class="w-1/3">
                <h2 class="text-2xl font-bold mb-2">Your Groups</h2>
                <ul class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
                    <?php foreach ($groups as $group): ?>
                        <li class="mb-2">
                            <a href="?group_id=<?php echo $group['id']; ?>" class="text-blue-500 hover:text-blue-700">
                                <?php echo htmlspecialchars($group['name']); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <h3 class="text-xl font-bold mb-2">Create New Group</h3>
                <form action="" method="post" class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
                    <div class="mb-4">
                        <input type="text" name="group_name" placeholder="Group Name" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    </div>
                    <button type="submit" name="create_group" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                        Create Group
                    </button>
                </form>

                <h3 class="text-xl font-bold mb-2">Group Chat</h3>
                <div id="chatContainer" class="chat-container bg-white shadow-md rounded p-4 mb-4">
                    <!-- Chat messages will be loaded here -->
                </div>
                <form id="chatForm" class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
                    <div class="mb-4">
                        <input type="text" id="chatMessage" placeholder="Type your message" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    </div>
                    <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                        Send
                    </button>
                </form>
            </div>

            <div class="w-2/3">
                <?php if (isset($_GET['group_id'])): ?>
                    <h2 class="text-2xl font-bold mb-2">Group Files</h2>
                    <form id="uploadForm" onsubmit="uploadFile(event)" enctype="multipart/form-data" class="mb-4">
                        <input type="hidden" name="group_id" value="<?php echo $_GET['group_id']; ?>">
                        <div class="flex items-center">
                            <input type="file" name="file" id="file" class="py-2 px-4 border rounded">
                            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded ml-2">Upload</button>
                        </div>
                    </form>

                    <form action="" method="get" class="mb-4">
                        <input type="hidden" name="group_id" value="<?php echo $_GET['group_id']; ?>">
                        <div class="flex items-center">
                            <input type="text" name="search" placeholder="Search files" value="<?php echo htmlspecialchars($search); ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded ml-2">Search</button>
                        </div>
                    </form>

                    <ul id="fileList" class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
                        <?php foreach ($group_files as $file): ?>
                            <li class="mb-4 pb-4 border-b">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <a href="download.php?file=<?php echo urlencode($file['filename']); ?>&group_id=<?php echo $_GET['group_id']; ?>" class="text-blue-500 hover:text-blue-700 text-lg">
                                            <?php echo htmlspecialchars($file['filename']); ?>
                                        </a>
                                        <p class="text-sm text-gray-500">
                                            Uploaded by <?php echo htmlspecialchars($file['uploader']); ?> on <?php echo date('Y-m-d H:i', strtotime($file['upload_time'])); ?>
                                        </p>
                                        <p class="text-sm text-gray-500">
                                            Last edited by <?php echo htmlspecialchars($file['last_editor']); ?> on <?php echo date('Y-m-d H:i', strtotime($file['last_edit_time'])); ?>
                                        </p>
                                    </div>
                                    <div>
                                        <button onclick="editFile(<?php echo $file['id']; ?>, '<?php echo htmlspecialchars($file['filename']); ?>')" class="text-green-500 hover:text-green-700">Edit</button>
                                        <button onclick="showRenameModal(<?php echo $file['id']; ?>, '<?php echo htmlspecialchars($file['filename']); ?>')" class="text-blue-500 hover:text-blue-700 ml-2">Rename</button>
                                        <button onclick="deleteFile(<?php echo $file['id']; ?>, <?php echo $_GET['group_id']; ?>)" class="text-red-500 hover:text-red-700 ml-2">Delete</button>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <button onclick="toggleEditHistory(<?php echo $file['id']; ?>)" class="text-sm text-blue-500 hover:text-blue-700">Show Edit History</button>
                                    <div id="editHistory<?php echo $file['id']; ?>" class="hidden mt-2">
                                        Loading edit history...
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <?php if ($totalPages > 1): ?>
                        <div class="flex justify-center">


<?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <a href="?group_id=<?php echo $_GET['group_id']; ?>&page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" class="mx-1 px-3 py-2 bg-blue-500 text-white rounded <?php echo $page === $i ? 'bg-blue-700' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>

                    <h3 class="text-xl font-bold mt-8 mb-2">Group Members</h3>
                    <ul class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
                        <?php foreach ($group_members as $member): ?>
                            <li class="mb-2">
                                <?php echo htmlspecialchars($member['username']); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <h3 class="text-xl font-bold mb-2">Add Member</h3>
                    <form action="" method="post" class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
                        <input type="hidden" name="group_id" value="<?php echo $_GET['group_id']; ?>">
                        <div class="mb-4">
                            <input type="text" name="username" placeholder="Username" required class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        </div>
                        <button type="submit" name="add_member" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                            Add Member
                        </button>
                    </form>
                <?php else: ?>
                    <p class="text-xl">Select a group to view files and members.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Edit File Modal -->
    <div id="editFileModal" class="edit-modal">
        <div class="modal-content">
            <h3 class="text-lg font-medium mb-4" id="modal-title">
                Edit File: <span id="editFileName"></span>
            </h3>
            <textarea id="fileContent" class="w-full h-64 p-2 text-gray-800 border rounded-lg mb-4"></textarea>
            <div class="flex justify-end">
                <button onclick="closeEditModal()" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded mr-2">Cancel</button>
                <button onclick="saveFile()" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Save</button>
            </div>
        </div>
    </div>

    <!-- Rename File Modal -->
    <div id="renameFileModal" class="rename-modal">
        <div class="modal-content">
            <h3 class="text-lg font-medium mb-4">Rename File</h3>
            <input type="text" id="newFileName" class="w-full p-2 text-gray-800 border rounded-lg mb-4" placeholder="New file name">
            <div class="flex justify-end">
                <button onclick="closeRenameModal()" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded mr-2">Cancel</button>
                <button onclick="renameFile()" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Rename</button>
            </div>
        </div>
    </div>

    <script>
        let currentFileId = null;
        let currentFileName = null;
        let currentGroupId = <?php echo isset($_GET['group_id']) ? $_GET['group_id'] : 'null'; ?>;
        let lastMessageId = 0;

        function editFile(fileId, fileName) {
            currentFileId = fileId;
            currentFileName = fileName;
            document.getElementById('editFileName').textContent = fileName;
            document.getElementById('editFileModal').style.display = 'block';
            
            fetch(`get_file_content.php?file_id=${fileId}`)
                .then(response => response.text())
                .then(content => {
                    document.getElementById('fileContent').value = content;
                })
                .catch(error => console.error('Error:', error));
        }

        function closeEditModal() {
            document.getElementById('editFileModal').style.display = 'none';
        }

        function saveFile() {
            const content = document.getElementById('fileContent').value;
            
            fetch('save_file.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `file_id=${currentFileId}&content=${encodeURIComponent(content)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('File saved successfully');
                    closeEditModal();
                    updateFileInfo(currentFileId, data.lastEditTime, data.lastEditor);
                } else {
                    alert('Failed to save file: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while saving the file');
            });
        }

        function showRenameModal(fileId, fileName) {
            currentFileId = fileId;
            currentFileName = fileName;
            document.getElementById('newFileName').value = fileName;
            document.getElementById('renameFileModal').style.display = 'block';
        }

        function closeRenameModal() {
            document.getElementById('renameFileModal').style.display = 'none';
        }

        function renameFile() {
            const newFileName = document.getElementById('newFileName').value;
            
            fetch('rename_file.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `file_id=${currentFileId}&new_filename=${encodeURIComponent(newFileName)}&group_id=${currentGroupId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('File renamed successfully');
                    closeRenameModal();
                    updateFileName(currentFileId, newFileName);
                } else {
                    alert('Failed to rename file: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while renaming the file');
            });
        }

        function updateFileInfo(fileId, lastEditTime, lastEditor) {
            const fileItem = document.querySelector(`button[onclick="editFile(${fileId}, '${currentFileName}')"]`).closest('li');
            const lastEditInfo = fileItem.querySelector('p:nth-child(2)');
            lastEditInfo.textContent = `Last edited by ${lastEditor} on ${lastEditTime}`;
        }

        function updateFileName(fileId, newFileName) {
            const fileItem = document.querySelector(`button[onclick="editFile(${fileId}, '${currentFileName}')"]`).closest('li');
            const fileLink = fileItem.querySelector('a');
            fileLink.textContent = newFileName;
            fileLink.href = `download.php?file=${encodeURIComponent(newFileName)}&group_id=${currentGroupId}`;
            
            const editButton = fileItem.querySelector('button:nth-child(1)');
            const renameButton = fileItem.querySelector('button:nth-child(2)');
            editButton.setAttribute('onclick', `editFile(${fileId}, '${newFileName}')`);
            renameButton.setAttribute('onclick', `showRenameModal(${fileId}, '${newFileName}')`);
            
            currentFileName = newFileName;
        }

        function deleteFile(fileId, groupId) {
            if (confirm('Are you sure you want to delete this file?')) {
                fetch('delete_file.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `file_id=${fileId}&group_id=${groupId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const fileItem = document.querySelector(`button[onclick="deleteFile(${fileId}, ${groupId})"]`).closest('li');
                        fileItem.remove();
                    } else {
                        alert('Failed to delete file: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while deleting the file');
                });
            }
        }

        function toggleEditHistory(fileId) {
            const historyDiv = document.getElementById(`editHistory${fileId}`);
            if (historyDiv.classList.contains('hidden')) {
                historyDiv.classList.remove('hidden');
                fetch(`get_edit_history.php?file_id=${fileId}`)
                    .then(response => response.text())
                    .then(history => {
                        historyDiv.innerHTML = history;
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        historyDiv.innerHTML = 'Failed to load edit history.';
                    });
            } else {
                historyDiv.classList.add('hidden');
            }
        }

        function uploadFile(event) {
            event.preventDefault();
            const formData = new FormData(event.target);
            
            fetch('upload_file.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('File uploaded successfully');
                    addFileToList(data.file);
                } else {
                    alert('Failed to upload file: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while uploading the file');
            });
        }

        function addFileToList(file) {
            const fileList = document.getElementById('fileList');
            const newFileItem = document.createElement('li');
            newFileItem.className = 'mb-4 pb-4 border-b';
            newFileItem.innerHTML = `
                <div class="flex justify-between items-start">
                    <div>
                        <a href="download.php?file=${encodeURIComponent(file.filename)}&group_id=${file.group_id}" class="text-blue-500 hover:text-blue-700 text-lg">
                            ${file.filename}
                        </a>
                        <p class="text-sm text-gray-500">
                            Uploaded by ${file.uploader} on ${file.upload_time}
                        </p>
                        <p class="text-sm text-gray-500">
                            Last edited by ${file.uploader} on ${file.upload_time}
                        </p>
                    </div>
                    <div>
                        <button onclick="editFile(${file.id}, '${file.filename}')" class="text-green-500 hover:text-green-700">Edit</button>
                        <button onclick="showRenameModal(${file.id}, '${file.filename}')" class="text-blue-500 hover:text-blue-700 ml-2">Rename</button>
                        <button onclick="deleteFile(${file.id}, ${file.group_id})" class="text-red-500 hover:text-red-700 ml-2">Delete</button>
                    </div>
                </div>
                <div class="mt-2">
                    <button onclick="toggleEditHistory(${file.id})" class="text-sm text-blue-500 hover:text-blue-700">Show Edit History</button>
                    <div id="editHistory${file.id}" class="hidden mt-2">
                        No edit history yet.
                    </div>
                </div>
            `;
            fileList.insertBefore(newFileItem, fileList.firstChild);
        }

        function loadChatMessages() {
            if (currentGroupId === null) return;

            fetch(`get_chat_messages.php?group_id=${currentGroupId}&last_id=${lastMessageId}`)
                .then(response => response.json())
                .then(data => {
                    const chatContainer = document.getElementById('chatContainer');
                    data.forEach(message => {
                        if (message.id > lastMessageId) {
                            const messageElement = document.createElement('div');
                            messageElement.className = 'chat-message';
                            messageElement.innerHTML = `
                                <span class="chat-username">${message.username}</span>
                                <span class="chat-timestamp">${message.timestamp}</span>
                                <p>${message.message}</p>
                            `;
                            chatContainer.appendChild(messageElement);
                            lastMessageId = message.id;
                        }
                    });
                    chatContainer.scrollTop = chatContainer.scrollHeight;
                })
                .catch(error => console.error('Error:', error));
        }

        function sendChatMessage(event) {
            event.preventDefault();
            const messageInput = document.getElementById('chatMessage');
            const message = messageInput.value.trim();
            if (message && currentGroupId) {
                fetch('send_chat_message.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `group_id=${currentGroupId}&message=${encodeURIComponent(message)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        messageInput.value = '';
                        loadChatMessages();
                    } else {
                        alert('Failed to send message: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while sending the message');
                });
            }
        }

        document.getElementById('chatForm').addEventListener('submit', sendChatMessage);

        // Load chat messages every 5 seconds
        setInterval(loadChatMessages, 5000);

        // Initial load of chat messages
        loadChatMessages();
    </script>
</body>
</html>