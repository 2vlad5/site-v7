
<?php
// Prevent any output before JSON response
error_reporting(E_ALL);
ini_set('display_errors', 0);
ob_start();

session_start();
require_once 'functions.php';

// Clear any existing output buffers
while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/json');

$user = checkAccess();
$userId = $user['id'];

if (!hasTabAccess($userId, 'messenger')) {
    die(json_encode(['error' => 'Access denied']));
}

$action = $_GET['action'] ?? '';
$response = ['success' => false];

switch ($action) {
    case 'get_available_users':
        if (!hasTabAccess($userId, 'messenger_create')) {
            die(json_encode(['error' => 'Access denied']));
        }
        
        $users = loadJson('data/users.json');
        $availableUsers = array_filter($users, function($user) {
            return hasTabAccess($user['id'], 'messenger_input') && $user['id'] !== $_SESSION['user_id'];
        });
        
        echo json_encode(array_values($availableUsers));
        break;

    case 'create_chat':
        if (!hasTabAccess($userId, 'messenger_create')) {
            die(json_encode(['error' => 'Access denied']));
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $users = $data['users'] ?? [];
        $chatName = $data['name'] ?? '';
        
        if (empty($users)) {
            die(json_encode(['error' => 'No users selected']));
        }
        
        $chats = loadJson('data/messenger_chats.json') ?: [];
        $newChat = [
            'id' => generateId(),
            'name' => $chatName ?: 'Новый чат',
            'creator_id' => $userId,
            'users' => array_merge($users, [$userId]),
            'created_at' => time()
        ];
        
        $chats[] = $newChat;
        saveJson('data/messenger_chats.json', array_values($chats));
        
        echo json_encode(['success' => true, 'chat_id' => $newChat['id']]);
        break;

    case 'get_chats':
        $chats = loadJson('data/messenger_chats.json') ?: [];
        $userChats = array_filter($chats, function($chat) use ($userId) {
            return isset($chat['users']) && ($chat['users'][0] === '*' || in_array($userId, $chat['users']));
        });
        $userChats = array_values($userChats);
        
        $messages = loadJson('data/messenger_messages.json') ?: [];
        foreach ($userChats as &$chat) {
            $chatMessages = array_filter($messages, function($msg) use ($chat) {
                return isset($msg['chat_id']) && $msg['chat_id'] === $chat['id'];
            });
            if (!empty($chatMessages)) {
                $lastMessage = end($chatMessages);
                $chat['last_message'] = mb_substr($lastMessage['content'], 0, 50) . (mb_strlen($lastMessage['content']) > 50 ? '...' : '');
            } else {
                $chat['last_message'] = 'Нет сообщений';
            }
        }
        
        echo json_encode(array_values($userChats));
        break;

    case 'get_messages':
        $chatId = $_GET['chat_id'] ?? '';
        $lastId = $_GET['last_id'] ?? 0;
        
        if (empty($chatId)) {
            die(json_encode(['error' => 'Chat ID required']));
        }
        
        $chats = loadJson('data/messenger_chats.json');
        $chat = array_filter($chats, function($c) use ($chatId) {
            return $c['id'] === $chatId;
        });
        
        if (empty($chat) || !in_array($userId, reset($chat)['users'])) {
            die(json_encode(['error' => 'Access denied']));
        }
        
        $messages = loadJson('data/messenger_messages.json') ?: [];
        $chatMessages = array_filter($messages, function($msg) use ($chatId, $lastId) {
            return isset($msg['chat_id']) && $msg['chat_id'] === $chatId && $msg['id'] > $lastId;
        });
        
        echo json_encode(['success' => true, 'messages' => array_values($chatMessages)]);
        break;

    case 'send_message':
        if (!hasTabAccess($userId, 'messenger_output')) {
            die(json_encode(['error' => 'Access denied']));
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $chatId = $data['chat_id'] ?? '';
        $isSystemMessage = $data['system_message'] ?? false;
        
        $chats = loadJson('data/messenger_chats.json');
        $chat = array_filter($chats, function($c) use ($chatId) {
            return $c['id'] === $chatId;
        });
        $chat = reset($chat);
        
        if ($chat && isset($chat['readonly']) && $chat['readonly']) {
            die(json_encode(['error' => 'This is a read-only chat']));
        }

        if ($isSystemMessage && !hasTabAccess($userId, 'messenger_manage')) {
            die(json_encode(['error' => 'Access denied for system messages']));
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $chatId = $data['chat_id'] ?? '';
        $message = $data['message'] ?? '';
        
        if (empty($chatId) || empty($message)) {
            die(json_encode(['error' => 'Invalid data']));
        }
        
        $messages = loadJson('data/messenger_messages.json');
        $newMessage = [
            'id' => count($messages) + 1,
            'chat_id' => $chatId,
            'sender_id' => $userId,
            'sender_name' => $isSystemMessage ? 'Система' : $user['last_name'] . ' ' . $user['first_name'],
            'content' => htmlspecialchars($message),
            'type' => $isSystemMessage ? 'system' : 'sent',
            'time' => date('Y-m-d H:i:s'),
            'created_at' => time()
        ];
        
        $messages[] = $newMessage;
        saveJson('data/messenger_messages.json', $messages);
        
        echo json_encode(['success' => true]);
        break;

    case 'upload_file':
        if (!hasTabAccess($userId, 'messenger_output')) {
            die(json_encode(['error' => 'Access denied']));
        }
        
        $chatId = $_POST['chat_id'] ?? '';
        if (empty($chatId) || empty($_FILES['file'])) {
            die(json_encode(['error' => 'Invalid data']));
        }
        
        $file = $_FILES['file'];
        if ($file['size'] > 5 * 1024 * 1024) {
            die(json_encode(['error' => 'File too large']));
        }
        
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'application/msword'];
        if (!in_array($file['type'], $allowedTypes)) {
            die(json_encode(['error' => 'Invalid file type']));
        }
        
        $uploadDir = 'uploads/messenger/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $filename = uniqid() . '_' . $file['name'];
        move_uploaded_file($file['tmp_name'], $uploadDir . $filename);
        
        $messages = loadJson('data/messenger_messages.json');
        $newMessage = [
            'id' => count($messages) + 1,
            'chat_id' => $chatId,
            'sender_id' => $userId,
            'sender_name' => $user['last_name'] . ' ' . $user['first_name'],
            'content' => 'Прикрепленный файл',
            'attachment' => $uploadDir . $filename,
            'type' => 'sent',
            'time' => date('Y-m-d H:i:s'),
            'created_at' => time()
        ];
        
        $messages[] = $newMessage;
        saveJson('data/messenger_messages.json', $messages);
        
        echo json_encode(['success' => true]);
        break;

    case 'update_chat':
        $chatId = $_POST['chat_id'] ?? '';
        $chatName = $_POST['name'] ?? '';
        
        if (empty($chatId)) {
            die(json_encode(['error' => 'Chat ID required']));
        }

        $chats = loadJson('data/messenger_chats.json');
        foreach ($chats as &$chat) {
            if ($chat['id'] === $chatId) {
                if ($chat['creator_id'] !== $userId && 
                    !hasTabAccess($userId, 'messenger_manage')) {
                    die(json_encode(['error' => 'Access denied']));
                }
                if (!empty($chatName) && count($chat['users']) > 2) {
                    $chat['name'] = $chatName;
                }
                if (!empty($_FILES['icon'])) {
                    $uploadDir = 'uploads/messenger/chat_img/';
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    
                    $iconPath = $uploadDir . uniqid() . '_' . $_FILES['icon']['name'];
                    if (move_uploaded_file($_FILES['icon']['tmp_name'], $iconPath)) {
                        // Remove old icon if exists
                        if (isset($chat['icon']) && file_exists($chat['icon'])) {
                            unlink($chat['icon']);
                        }
                        $chat['icon'] = $iconPath;
                    }
                }
                break;
            }
        }
        saveJson('data/messenger_chats.json', $chats);
        echo json_encode(['success' => true]);
        break;

    case 'delete_chat':
        $chatId = $_GET['chat_id'] ?? '';
        if (empty($chatId)) {
            die(json_encode(['error' => 'Chat ID required']));
        }

        $chats = loadJson('data/messenger_chats.json');
        $found = false;
        
        foreach ($chats as $key => $chat) {
            if ($chat['id'] === $chatId) {
                if ($chat['creator_id'] !== $userId && 
                    !(hasTabAccess($userId, 'messenger_delete') && 
                      hasTabAccess($userId, 'messenger_manage'))) {
                    die(json_encode(['error' => 'Access denied']));
                }
                unset($chats[$key]);
                $found = true;
                break;
            }
        }
        
        if ($found) {
            $chats = array_values($chats);
            saveJson('data/messenger_chats.json', $chats);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => 'Chat not found']);
        }
        break;

    default:
        echo json_encode(['error' => 'Invalid action']);
        break;
}
