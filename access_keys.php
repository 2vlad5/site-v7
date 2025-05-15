<?php
session_start();
require_once   'functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user = checkAccess();
$userId = $user['id'];
$userRole = $user['role'];
$firstName = $user['first_name'];
$lastName = $user['last_name'];

// Проверяем права доступа - только доверенный имеет доступ
if (!hasTabAccess($userId, 'backup_journals')) {
    header("Location: dashboard.php");
    exit();
}

// Загружаем список пользователей
$users = loadJson('data/users.json');

// Фильтруем создателей, их ключи не нужно продлевать
$filteredUsers = [];
foreach ($users as $user) {
    if ($user['role'] !== 'creator' && $user['id'] !== $userId) {
        $filteredUsers[] = $user;
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Рабочий журнал - Управление ключами доступа</title>
    <meta name="description" content="Built with jdoodle.ai - Система учета рабочего времени с журналами по месяцам">
    <meta property="og:title" content="Рабочий журнал - Управление ключами доступа">
    <meta property="og:description" content="Built with jdoodle.ai - Удобная система учета рабочего времени с возможностью экспорта в Excel">
    <meta property="og:image" content="https://images.unsplash.com/photo-1531973576160-7125cd663d86?ixid=M3w3MjUzNDh8MHwxfHNlYXJjaHwzfHxtb2Rlcm4lMjBvZmZpY2UlMjB3b3Jrc3BhY2UlMjB0ZWFtd29ya3xlbnwwfHx8fDE3NDI5MjM1OTV8MA&ixlib=rb-4.0.3&fit=fillmax&h=450&w=800">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="dashboard">
                <?php renderMenu($userId, $userRole, $firstName, $lastName); ?>

        <div class="main-content">
            <div class="header">
                <h1>Управление ключами доступа</h1>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Пользователь</th>
                        <th>Роль</th>
                        <th>Статус ключа</th>
                        <th>Срок действия до</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($filteredUsers as $user): ?>
                        <?php 
                        $hasKey = isset($user['access_key_expiry']);
                        $expired = $hasKey && $user['access_key_expiry'] < time();
                        $expiryDate = $hasKey ? date('d.m.Y H:i', $user['access_key_expiry']) : '-';
                        
                        $status = 'Нет ключа';
                        $statusClass = 'text-warning';
                        
                        if ($hasKey) {
                            if ($expired) {
                                $status = 'Истек';
                                $statusClass = 'text-danger';
                            } else {
                                $status = 'Активен';
                                $statusClass = 'text-success';
                            }
                        }
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($user['last_name'] . ' ' . $user['first_name'] . ' ' . ($user['middle_name'] ?? '')) ?></td>
                            <td><?= htmlspecialchars($user['role']) ?></td>
                            <td class="<?= $statusClass ?>"><?= $status ?></td>
                            <td><?= $expiryDate ?></td>
                            <td>
                                <button class="btn" onclick="openExtendKeyModal('<?= $user['id'] ?>', '<?= htmlspecialchars($user['last_name'] . ' ' . $user['first_name'] . ' ' . ($user['middle_name'] ?? '')) ?>')">
                                    <?= $hasKey ? 'Продлить ключ' : 'Создать ключ' ?>
                                </button>
                                <?php if ($hasKey): ?>
                                <button class="btn btn-danger" onclick="revokeKey('<?= $user['id'] ?>')">Отозвать ключ</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Модальное окно продления ключа -->
    <div id="extend-key-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="extend-key-title">Продлить ключ доступа</h2>
                <button class="modal-close" onclick="closeModal('extend-key-modal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="extend-key-form" action="extend_key.php" method="post">
                    <input type="hidden" id="extend-key-user-id" name="user_id">
                    <div class="form-group">
                        <label for="extend-key-days">Продлить на (дней)</label>
                        <input type="number" id="extend-key-days" name="days" value="30" min="1">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-danger" onclick="closeModal('extend-key-modal')">Отмена</button>
                <button class="btn btn-success" onclick="document.getElementById('extend-key-form').submit()">Продлить</button>
            </div>
        </div>
    </div>
    
    <script>
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
        
        function openExtendKeyModal(userId, username) {
            document.getElementById('extend-key-user-id').value = userId;
            document.getElementById('extend-key-title').innerText = 'Продлить ключ доступа для ' + username;
            openModal('extend-key-modal');
        }
        
        function revokeKey(userId) {
            if (confirm('Вы уверены, что хотите отозвать ключ доступа?')) {
                window.location.href = 'revoke_key.php?id=' + userId;
            }
        }
    </script>
</body>
</html>
 