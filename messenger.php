<?php
session_start();
require_once 'functions.php';

$user = checkAccess();
$userId = $user['id'];
$userRole = $user['role'];
$firstName = $user['first_name'];
$lastName = $user['last_name'];

if (!hasTabAccess($userId, 'messenger')) {
    header("Location: dashboard.php");
    exit();
}

// Загружаем всех пользователей с правом messenger_input
$users = loadJson('data/users.json');
$availableUsers = array_filter($users, function($user) {
    return hasTabAccess($user['id'], 'messenger_input');
});

// Загружаем чаты
$chats = loadJson('data/messenger_chats.json');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Мессенджер - MWJ-2v5</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .messenger-container {
            display: flex;
            height: calc(100vh - 100px);
            gap: 20px;
            padding: 20px;
        }
        .chat-list {
            width: 300px;
            background: white;
            border-radius: 8px;
            padding: 15px;
            overflow-y: auto;
        }
        .chat-area {
            flex-grow: 1;
            background: white;
            border-radius: 8px;
            display: flex;
            flex-direction: column;
        }
        .chat-header {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }
        .messages-container {
            flex-grow: 1;
            overflow-y: auto;
            padding: 15px;
        }
        .message-input {
            padding: 15px;
            border-top: 1px solid #eee;
            display: flex;
            gap: 10px;
        }
        .message-input textarea {
            flex-grow: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            resize: none;
            height: 60px;
        }
        .attachment-preview {
            max-width: 200px;
            max-height: 200px;
            margin: 10px 0;
        }
        .user-select {
            margin-bottom: 15px;
        }
    .chat-item {
            display: flex;
            align-items: center;
            gap: 10px;
            position: relative;
        }
        .chat-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            overflow: hidden;
        }
        .chat-icon img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .chat-info {
            flex-grow: 1;
        }
        .delete-chat, .edit-chat {
            background: none;
            border: none;
            padding: 5px;
            cursor: pointer;
            color: #666;
            opacity: 0;
            transition: opacity 0.2s;
        }
        .chat-item:hover .delete-chat,
        .chat-item:hover .edit-chat {
            opacity: 1;
        }
        .delete-chat:hover {
            color: #dc3545;
        }
        .edit-chat:hover {
            color: #007bff;
        }
        .chat-item {
            padding: 12px;
            border-radius: 8px;
            cursor: pointer;
            margin-bottom: 8px;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .chat-item:hover {
            background: #f8f9fa;
            transform: translateY(-1px);
        }
        .chat-item.active {
            background: #e3f2fd;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .messages-container {
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .message {
            margin-bottom: 5px;
            padding: 12px 16px;
            border-radius: 18px;
            max-width: 70%;
            position: relative;
            animation: fadeIn 0.3s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .message.sent {
            background: #0084ff;
            color: white;
            margin-left: auto;
            border-bottom-right-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .message.received {
            background: #e4e6eb;
            color: #050505;
            margin-right: auto;
            border-bottom-left-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .message.system {
            background: #fff3e0;
            color: #1c1e21;
            margin: 20px auto;
            text-align: center;
            border-radius: 12px;
            font-style: italic;
            max-width: 90%;
        }
        .message strong {
            font-size: 13px;
            opacity: 0.8;
            display: block;
            margin-bottom: 4px;
        }
        .message p {
            margin: 0;
            line-height: 1.4;
        }
        .message small {
            font-size: 11px;
            opacity: 0.7;
            display: block;
            margin-top: 5px;
            text-align: right;
        }

        .unread-count {
            background: #1a73e8;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 12px;
            position: absolute;
            right: 8px;
            top: 8px;
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <?php renderMenu($userId, $userRole, $firstName, $lastName, 'messenger'); ?>

        <div class="main-content">
            <div class="messenger-container">
                <div class="chat-list">
                    <h3>Чаты</h3>
                    <?php if (hasTabAccess($userId, 'messenger_create')): ?>
                    <button class="btn btn-primary" onclick="openNewChatModal()">
                        <i class="fas fa-plus"></i> Новый чат
                    </button>
                    <?php endif; ?>
                    <div id="chats-container">
                        <!-- Чаты будут загружены через AJAX -->
                    </div>
                </div>

                <div class="chat-area">
                    <div class="chat-header">
                        <h3 id="current-chat-title">Вы открыли внутренний месенджер, пожалуйста выберите пользователя.</h3>
                    </div>
                    <div class="messages-container" id="messages-container">
                        <!-- Сообщения будут загружены через AJAX -->
                    </div>
                    <div class="message-input" id="message-input" style="display: none;">
                        <textarea id="message-text" placeholder="Введите сообщение..."></textarea>
                        <button class="btn" onclick="attachFile()">
                            <i class="fas fa-paperclip"></i>
                        </button>
                        <button class="btn btn-primary" onclick="sendMessage()">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Модальное окно создания чата -->
    <div id="new-chat-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Новый чат</h2>
                <button class="modal-close" onclick="closeNewChatModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Выберите участников:</label>
                    <div id="users-list">
                        <!-- Список пользователей будет загружен через AJAX -->
                    </div>
                </div>
                <div class="form-group">
                    <label for="chat-name">Название беседы (необязательно):</label>
                    <input type="text" id="chat-name" placeholder="Введите название беседы">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeNewChatModal()">Отмена</button>
                <button class="btn btn-primary" onclick="createChat()">Создать</button>
            </div>
        </div>
    </div>

    <!-- Модальное окно редактирования чата -->
    <div id="edit-chat-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Редактировать чат</h2>
                <button class="modal-close" onclick="closeEditChatModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="edit-chat-name">Название чата:</label>
                    <input type="text" id="edit-chat-name">
                </div>
                <div class="form-group">
                    <label for="chat-icon">Иконка чата:</label>
                    <input type="file" id="chat-icon" accept="image/*">
                    <div id="icon-preview"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeEditChatModal()">Отмена</button>
                <button class="btn btn-primary" onclick="saveEditChat()">Сохранить</button>
            </div>
        </div>
    </div>

    <input type="file" id="file-input" style="display: none" accept="image/*,.pdf,.doc,.docx" onchange="handleFileSelect(this)">

    <script>
        // Function to check tab access permissions
        function hasTabAccess(tabName) {
            const permissions = <?php echo json_encode(loadJson('data/tab_permissions.json')); ?>;
            const userId = '<?php echo $userId; ?>';
            const userRole = '<?php echo $userRole; ?>';

            // Check custom permissions
            if (permissions.custom && permissions.custom[userId]) {
                return permissions.custom[userId].includes(tabName);
            }

            // Check default permissions
            if (permissions.default && permissions.default[userRole]) {
                return permissions.default[userRole].includes(tabName);
            }

            return false;
        }

        let currentChatId = null;
        let lastMessageId = null;
        const updateInterval = 3000; // 3 секунды

        function openNewChatModal() {
            document.getElementById('new-chat-modal').classList.add('active');
            loadAvailableUsers();
        }

        function closeNewChatModal() {
            document.getElementById('new-chat-modal').classList.remove('active');
        }

    let currentEditChatId = null;

    function editChat(chatId, event) {
        event.stopPropagation();
        currentEditChatId = chatId;
        document.getElementById('edit-chat-modal').classList.add('active');

        // Load current chat name
        const chatItem = document.querySelector(`.chat-item[onclick="openChat('${chatId}')"]`);
        const chatName = chatItem.querySelector('.chat-info strong').textContent;
        document.getElementById('edit-chat-name').value = chatName;
    }

    function closeEditChatModal() {
        document.getElementById('edit-chat-modal').classList.remove('active');
        document.getElementById('icon-preview').innerHTML = '';
        document.getElementById('chat-icon').value = '';
        currentEditChatId = null;
    }

    function saveEditChat() {
        if (!currentEditChatId) return;

        const chatName = document.getElementById('edit-chat-name').value;
        const iconInput = document.getElementById('chat-icon');
        const formData = new FormData();

        formData.append('chat_id', currentEditChatId);
        formData.append('name', chatName);

        if (iconInput.files && iconInput.files[0]) {
            formData.append('icon', iconInput.files[0]);
        }

        fetch('messenger_actions.php?action=update_chat', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                closeEditChatModal();
                loadChats();
            } else {
                alert(result.error || 'Ошибка при обновлении чата');
            }
        });
    }

    // Preview chat icon
    document.getElementById('chat-icon').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('icon-preview').innerHTML = 
                    `<img src="${e.target.result}" style="max-width: 100px; max-height: 100px;">`;
            }
            reader.readAsDataURL(file);
        }
    });

        function loadAvailableUsers() {
            fetch('messenger_actions.php?action=get_available_users')
                .then(response => response.json())
                .then(users => {
                    const container = document.getElementById('users-list');
                    container.innerHTML = users.map(user => `
                        <div class="user-select">
                            <input type="checkbox" id="user-${user.id}" value="${user.id}">
                            <label for="user-${user.id}">${user.last_name} ${user.first_name}</label>
                        </div>
                    `).join('');
                });
        }

        function createChat() {
            const selectedUsers = Array.from(document.querySelectorAll('#users-list input:checked'))
                .map(input => input.value);
            const chatName = document.getElementById('chat-name').value;

            if (selectedUsers.length === 0) {
                alert('Выберите хотя бы одного участника');
                return;
            }

            fetch('messenger_actions.php?action=create_chat', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    users: selectedUsers,
                    name: chatName
                })
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    closeNewChatModal();
                    loadChats();
                } else {
                    alert(result.error || 'Ошибка при создании чата');
                }
            });
        }

        function deleteChat(chatId, event) {
            event.stopPropagation();
            if (!confirm('Вы уверены, что хотите удалить этот чат?')) {
                return;
            }

            fetch(`messenger_actions.php?action=delete_chat&chat_id=${chatId}`)
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        loadChats();
                        if (currentChatId === chatId) {
                            currentChatId = null;
                            document.getElementById('messages-container').innerHTML = '';
                            document.getElementById('current-chat-title').textContent = 'Выберите чат';
                        }
                    } else {
                        alert(result.error || 'Ошибка при удалении чата');
                    }
                });
        }

        function loadChats() {
            fetch('messenger_actions.php?action=get_chats')
                .then(response => response.json())
                .then(chats => {
                    if (!Array.isArray(chats)) chats = [];
                    // Sort chats by last message time and unread count
                    chats.sort((a, b) => {
                        return (b.last_message_time || 0) - (a.last_message_time || 0);
                    });
                    const container = document.getElementById('chats-container');
                    container.innerHTML = chats.map(chat => `
                        <div class="chat-item ${chat.id === currentChatId ? 'active' : ''}" 
                             onclick="openChat('${chat.id}')">
                            <div class="chat-icon">
                                <img src="${chat.icon || 'default_chat.png'}" alt="Chat icon">
                            </div>
                            <div class="chat-info">
                                <strong>${chat.name || 'Чат ' + chat.id}</strong>
                                <p>${chat.last_message || 'Нет сообщений'}</p>
                                ${chat.unread_count ? `<span class="unread-count">${chat.unread_count}</span>` : ''}
                            </div>
                            ${(!chat.is_system && (chat.creator_id === '<?php echo $userId; ?>' || 
               (hasTabAccess('<?php echo $userId; ?>', 'messenger_delete') && 
                hasTabAccess('<?php echo $userId; ?>', 'messenger_manage')))) ? 
                              `<button class="delete-chat" onclick="deleteChat('${chat.id}', event)">
                                   <i class="fas fa-trash"></i>
                               </button>` : ''}
                            ${(chat.creator_id === '<?php echo $userId; ?>' && chat.users.length > 2) ? 
                              `<button class="edit-chat" onclick="editChat('${chat.id}', event)">
                                   <i class="fas fa-edit"></i>
                               </button>` : ''}
                        </div>
                    `).join('');
                });
        }

        function openChat(chatId) {
            currentChatId = chatId;
            lastMessageId = 0; // Reset last message ID
            document.querySelectorAll('.chat-item').forEach(item => item.classList.remove('active'));
            const chatItem = document.querySelector(`.chat-item[onclick="openChat('${chatId}')"]`);
            if (chatItem) {
                chatItem.classList.add('active');
                const chatTitle = chatItem.querySelector('.chat-info strong').textContent;
                document.getElementById('current-chat-title').textContent = chatTitle;
                document.getElementById('message-input').style.display = 'flex';
            }
            document.getElementById('messages-container').innerHTML = '';
            loadMessages();
        }

        function loadMessages() {
            if (!currentChatId) return;

            fetch(`messenger_actions.php?action=get_messages&chat_id=${currentChatId}&last_id=${lastMessageId || 0}`)
                .then(response => response.json())
                .then(result => {
                    const container = document.getElementById('messages-container');
                    if (!result.messages) result.messages = [];

                    if (lastMessageId === 0) {
                        container.innerHTML = ''; // Clear container only on first load
                    }

                    if (result.messages.length > 0) {
                        result.messages.forEach(message => {
                            const messageDiv = document.createElement('div');
                            messageDiv.className = `message ${message.type}`;
                            messageDiv.innerHTML = `
                                <strong>${message.sender_name}</strong>
                                <p>${message.content}</p>
                                ${message.attachment ? `<img src="${message.attachment}" class="attachment-preview">` : ''}
                                <small>${message.time}</small>
                            `;
                            container.appendChild(messageDiv);
                        });
                        lastMessageId = result.messages[result.messages.length - 1].id;
                        container.scrollTop = container.scrollHeight;
                    }
                });
        }

        function sendMessage() {
            if (!currentChatId) return;

            const text = document.getElementById('message-text').value.trim();
            if (!text) return;

            fetch('messenger_actions.php?action=send_message', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    chat_id: currentChatId,
                    message: text
                })
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    document.getElementById('message-text').value = '';
                    loadMessages();
                }
            });
        }

        function attachFile() {
            document.getElementById('file-input').click();
        }

        function handleFileSelect(input) {
            if (!currentChatId || !input.files || !input.files[0]) return;

            const file = input.files[0];
            if (file.size > 5 * 1024 * 1024) {
                alert('Файл слишком большой. Максимальный размер: 5MB');
                return;
            }

            const formData = new FormData();
            formData.append('file', file);
            formData.append('chat_id', currentChatId);

            fetch('messenger_actions.php?action=upload_file', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    loadMessages();
                } else {
                    alert(result.error || 'Ошибка при загрузке файла');
                }
            });
        }

        // Инициализация
        loadChats();
        setInterval(() => {
            if (currentChatId) {
                loadMessages();
            }
            loadChats();
        }, updateInterval);

        // Обработка Enter для отправки сообщения
        document.getElementById('message-text').addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });
    </script>
</body>
</html>