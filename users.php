<?php
session_start();
require_once 'functions.php';

$user = checkAccess();
$userId = $user['id'];
$userRole = $user['role'];
$firstName = $user['first_name'];
$lastName = $user['last_name'];

if (!hasTabAccess($userId, 'users')) {
    header("Location: dashboard.php");
    exit();
}

// Загружаем список пользователей
$users = loadJson('data/users.json');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Рабочий журнал - Управление пользователями</title>
    <meta name="description" content="Built with jdoodle.ai - Система учета рабочего времени с журналами по месяцам">
    <meta property="og:title" content="Рабочий журнал - Управление пользователями">
    <meta property="og:description" content="Built with jdoodle.ai - Удобная система учета рабочего времени с возможностью экспорта в Excel">
    <meta property="og:image" content="https://images.unsplash.com/photo-1524758870432-af57e54afa26?ixid=M3w3MjUzNDh8MHwxfHNlYXJjaHwyfHxtb2Rlcm4lMjBvZmZpY2UlMjB3b3Jrc3BhY2UlMjB0ZWFtd29ya3xlbnwwfHx8fDE3NDI5MjM1OTV8MA&ixlib=rb-4.0.3&fit=fillmax&h=450&w=800">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="dashboard">

        <?php renderMenu($userId, $userRole, $firstName, $lastName); ?>

        <div class="main-content">
            <div class="header">
                <h1>Управление пользователями</h1>
                <button class="btn" onclick="openModal('create-user-modal')">Создать пользователя</button>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Логин</th>
                        <th>Фамилия</th>
                        <th>Имя</th>
                        <th>Отчество</th>
                        <th>Email</th>
                        <th>Роль</th>
                        <th>Ключ доступа до</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <?php 
                        // Администраторы могут видеть только обычных пользователей
                        if ($userRole === 'admin' && $user['role'] !== 'user') {
                            continue;
                        }

                        // Пропускаем текущего пользователя
                        if ($user['id'] == $userId) {
                            continue;
                        }

                        $keyExpiry = isset($user['access_key_expiry']) 
                            ? date('d.m.Y', $user['access_key_expiry']) 
                            : 'Нет ключа';

                        $keyStatus = '';
                        if (isset($user['access_key_expiry'])) {
                            if ($user['access_key_expiry'] < time()) {
                                $keyStatus = ' <span style="color: red">(истек)</span>';
                            } else {
                                $keyStatus = ' <span style="color: green">(активен)</span>';
                            }
                        }
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($user['username']) ?></td>
                            <td><?= htmlspecialchars($user['last_name'] ?? '') ?></td>
                            <td><?= htmlspecialchars($user['first_name'] ?? '') ?></td>
                            <td><?= htmlspecialchars($user['middle_name'] ?? '') ?></td>
                            <td><?= htmlspecialchars($user['email'] ?? '') ?></td>
                            <td><?= htmlspecialchars($user['role']) ?></td>
                            <td><?= $keyExpiry . $keyStatus ?></td>
                            <td>
                                <button class="btn" onclick="openEditUserModal('<?= $user['id'] ?>', '<?= htmlspecialchars($user['username']) ?>', '<?= htmlspecialchars($user['first_name'] ?? '') ?>', '<?= htmlspecialchars($user['middle_name'] ?? '') ?>', '<?= htmlspecialchars($user['last_name'] ?? '') ?>', '<?= htmlspecialchars($user['email'] ?? '') ?>', '<?= $user['role'] ?>')">Редактировать</button>
                                <?php if ($userRole === 'creator'): ?>
                                <button class="btn btn-danger" onclick="deleteUser('<?= $user['id'] ?>')">Удалить</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Модальное окно создания пользователя -->
    <div id="create-user-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Создать пользователя</h2>
                <button class="modal-close" onclick="closeModal('create-user-modal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="create-user-form" action="create_user.php" method="post">
                    <div class="form-group">
                        <label for="username">Логин</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Пароль</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <div class="form-group">
                        <label for="last_name">Фамилия</label>
                        <input type="text" id="last_name" name="last_name" required>
                    </div>
                    <div class="form-group">
                        <label for="first_name">Имя</label>
                        <input type="text" id="first_name" name="first_name" required>
                    </div>
                    <div class="form-group">
                        <label for="middle_name">Отчество</label>
                        <input type="text" id="middle_name" name="middle_name">
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="role">Роль</label>
                        <select id="role" name="role" required>
                            <option value="user">Пользователь</option>
                            <?php if ($userRole === 'creator'): ?>
                            <option value="admin">Администратор</option>
                            <option value="creator">Создатель</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <?php if ($userRole === 'creator'): ?>
                    <div class="form-group">
                        <label for="access_key_days">Срок действия ключа доступа (дней)</label>
                        <input type="number" id="access_key_days" name="access_key_days" value="30" min="1">
                    </div>
                    <?php endif; ?>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-danger" onclick="closeModal('create-user-modal')">Отмена</button>
                <button class="btn btn-success" onclick="document.getElementById('create-user-form').submit()">Создать</button>
            </div>
        </div>
    </div>

    <!-- Модальное окно редактирования пользователя -->
    <div id="edit-user-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Редактировать пользователя</h2>
                <button class="modal-close" onclick="closeModal('edit-user-modal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="edit-user-form" action="edit_user.php" method="post">
                    <input type="hidden" id="edit-user-id" name="user_id">
                    <div class="form-group">
                        <label for="edit-username">Логин</label>
                        <input type="text" id="edit-username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-password">Новый пароль (оставьте пустым, чтобы не менять)</label>
                        <input type="password" id="edit-password" name="password">
                    </div>
                    <div class="form-group">
                        <label for="edit-last-name">Фамилия</label>
                        <input type="text" id="edit-last-name" name="last_name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-first-name">Имя</label>
                        <input type="text" id="edit-first-name" name="first_name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-middle-name">Отчество</label>
                        <input type="text" id="edit-middle-name" name="middle_name">
                    </div>
                    <div class="form-group">
                        <label for="edit-email">Email</label>
                        <input type="email" id="edit-email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-role">Роль</label>
                        <select id="edit-role" name="role" required>
                            <option value="user">Пользователь</option>
                            <?php if ($userRole === 'creator'): ?>
                            <option value="admin">Администратор</option>
                            <option value="creator">Создатель</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <?php if ($userRole === 'creator'): ?>
                    <div class="form-group">
                        <label for="edit-access-key-days">Продлить ключ доступа (дней)</label>
                        <input type="number" id="edit-access-key-days" name="access_key_days" value="30" min="1">
                    </div>
                    <?php endif; ?>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-danger" onclick="closeModal('edit-user-modal')">Отмена</button>
                <button class="btn btn-success" onclick="document.getElementById('edit-user-form').submit()">Сохранить</button>
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

        function openEditUserModal(userId, username, firstName, middleName, lastName, email, role) {
            document.getElementById('edit-user-id').value = userId;
            document.getElementById('edit-username').value = username;
            document.getElementById('edit-password').value = '';
            document.getElementById('edit-first-name').value = firstName;
            document.getElementById('edit-middle-name').value = middleName || '';
            document.getElementById('edit-last-name').value = lastName;
            document.getElementById('edit-email').value = email;
            document.getElementById('edit-role').value = role;
            openModal('edit-user-modal');
        }

        function deleteUser(userId) {
            if (confirm('Вы уверены, что хотите удалить этого пользователя?')) {
                window.location.href = 'delete_user.php?id=' + userId;
            }
        }
    </script>
</body>
</html>