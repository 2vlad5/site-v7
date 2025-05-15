<?php
session_start();
require_once 'functions.php';

$user = checkAccess();
$userId = $user['id'];
$userRole = $user['role'];
$firstName = $user['first_name'];
$lastName = $user['last_name'];

if (!hasTabAccess($userId, 'passwords')) {
    header("Location: dashboard.php");
    exit();
}

// Загружаем пароли пользователя
$passwords = loadJson("data/passwords/{$userId}.json");

// Загружаем категории сейфов пользователя
$safes = loadJson("data/safes/{$userId}.json");
if (!$safes) {
    $safes = [
        ['id' => 'default', 'name' => 'Основной сейф', 'color' => '#2196F3'],
        ['id' => 'gmail', 'name' => 'Gmail', 'color' => '#DB4437'],
        ['id' => 'work', 'name' => 'Рабочие', 'color' => '#0F9D58']
    ];
    saveJson("data/safes/{$userId}.json", $safes);
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Личный сейф паролей</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="dashboard">
        <?php renderMenu($userId, $userRole, $firstName, $lastName, 'passwords'); ?>

        <div class="main-content">
            <div class="header">
                <h1>Личный сейф паролей</h1>
                <div class="header-actions">
                    <button class="btn" onclick="openModal('add-safe-modal')">Добавить сейф</button>
                    <button class="btn" onclick="openModal('add-password-modal')">Добавить пароль</button>
                </div>
            </div>

            <div class="safes-container">
                <?php foreach ($safes as $safe): ?>
                <div class="safe-section" data-safe-id="<?= $safe['id'] ?>" style="border-left: 4px solid <?= $safe['color'] ?>">
                    <div class="safe-header">
                        <h2>
                            <button class="btn-icon" onclick="toggleSafe('<?= $safe['id'] ?>')" data-safe-toggle="<?= $safe['id'] ?>">
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            <?= htmlspecialchars($safe['name']) ?>
                        </h2>
                        <div class="safe-actions">
                            <button class="btn-icon" onclick="editSafe('<?= $safe['id'] ?>')">
                                <i class="fas fa-edit"></i>
                            </button>
                            <?php if ($safe['id'] !== 'default'): ?>
                            <button class="btn-icon" onclick="deleteSafe('<?= $safe['id'] ?>')">
                                <i class="fas fa-trash"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="passwords-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Сервис</th>
                                    <th>Логин</th>
                                    <th>Пароль</th>
                                    <th>Заметки</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($passwords as $password): 
                                    $passwordSafeId = isset($password['safe_id']) ? $password['safe_id'] : 'default';
                                    if ($passwordSafeId === $safe['id']): ?>
                                <tr>
                                    <td><?= htmlspecialchars($password['service_name']) ?></td>
                                    <td><?= htmlspecialchars($password['username']) ?></td>
                                    <td class="password-field">
                                        <span class="hidden-password">••••••••</span>
                                        <button class="btn-icon show-password" onclick="togglePassword(this)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <span class="actual-password" style="display:none"><?= htmlspecialchars($password['password']) ?></span>
                                    </td>
                                    <td class="notes-cell"><?= !empty($password['notes']) ? htmlspecialchars($password['notes']) : '-' ?></td>
                                    <td class="actions-cell">
                                        <button class="btn-icon" onclick="editPassword('<?= $password['id'] ?>')">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn-icon" onclick="deletePassword('<?= $password['id'] ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endif; endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Модальное окно добавления пароля -->
    <div id="add-password-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Добавить пароль</h2>
                <button class="modal-close" onclick="closeModal('add-password-modal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="add-password-form" action="save_password.php" method="post">
                    <div class="form-group">
                        <label for="safe_id">Сейф</label>
                        <select id="safe_id" name="safe_id" required>
                            <?php foreach ($safes as $safe): ?>
                            <option value="<?= $safe['id'] ?>"><?= htmlspecialchars($safe['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="service_name">Название сервиса</label>
                        <input type="text" id="service_name" name="service_name" required>
                    </div>
                    <div class="form-group">
                        <label for="username">Логин</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Пароль</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <div class="form-group">
                        <label for="notes">Заметки</label>
                        <textarea id="notes" name="notes"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-danger" onclick="closeModal('add-password-modal')">Отмена</button>
                <button class="btn btn-success" onclick="document.getElementById('add-password-form').submit()">Сохранить</button>
            </div>
        </div>
    </div>

    <!-- Модальное окно добавления сейфа -->
    <div id="add-safe-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Добавить сейф</h2>
                <button class="modal-close" onclick="closeModal('add-safe-modal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="add-safe-form" action="save_safe.php" method="post">
                    <div class="form-group">
                        <label for="safe_name">Название сейфа</label>
                        <input type="text" id="safe_name" name="safe_name" required>
                    </div>
                    <div class="form-group">
                        <label for="safe_color">Цвет</label>
                        <input type="color" id="safe_color" name="safe_color" value="#2196F3">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-danger" onclick="closeModal('add-safe-modal')">Отмена</button>
                <button class="btn btn-success" onclick="document.getElementById('add-safe-form').submit()">Сохранить</button>
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

        function togglePassword(button) {
            const container = button.parentElement;
            const hiddenPwd = container.querySelector('.hidden-password');
            const actualPwd = container.querySelector('.actual-password');
            const icon = button.querySelector('i');

            if (hiddenPwd.style.display !== 'none') {
                hiddenPwd.style.display = 'none';
                actualPwd.style.display = 'inline';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                hiddenPwd.style.display = 'inline';
                actualPwd.style.display = 'none';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        function deletePassword(id) {
            if (confirm('Вы уверены, что хотите удалить этот пароль?')) {
                window.location.href = 'delete_password.php?id=' + id;
            }
        }

        function deleteSafe(id) {
            if (confirm('Вы уверены, что хотите удалить этот сейф? Все пароли будут перемещены в основной сейф.')) {
                window.location.href = 'delete_safe.php?id=' + id;
            }
        }

         function toggleSafe(safeId) {
            console.log("Toggling safe: " + safeId); // Debugging
            const safeSection = document.querySelector(`.safe-section[data-safe-id="${safeId}"]`);
            if (!safeSection) {
                console.error("Safe section not found for id:", safeId);
                return;
            }

            const passwordsGrid = safeSection.querySelector('.passwords-grid');
             if (!passwordsGrid) {
                console.error("passwordsGrid not found for id:", safeId);
                return;
            }
            const toggleButton = document.querySelector(`button[data-safe-toggle="${safeId}"] i`);

            if (passwordsGrid.style.display === 'none') {
                passwordsGrid.style.display = 'grid';
                toggleButton.classList.remove('fa-chevron-right');
                toggleButton.classList.add('fa-chevron-down');
            } else {
                passwordsGrid.style.display = 'none';
                toggleButton.classList.remove('fa-chevron-down');
                toggleButton.classList.add('fa-chevron-right');
            }
        }
    </script>
</body>
</html>