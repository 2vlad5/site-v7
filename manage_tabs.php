
<?php
session_start();
require_once 'functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'creator') {
    header("Location: dashboard.php");
    exit();
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];
$firstName = $_SESSION['first_name'] ?? '';
$lastName = $_SESSION['last_name'] ?? '';

// Получаем список всех пользователей
$users = loadJson('data/users.json');
$tabPermissions = loadJson('data/tab_permissions.json');
$allTabs = getAllTabs();

// Обработка POST запроса
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'], $_POST['tabs'])) {
    setCustomTabPermissions($_POST['user_id'], $_POST['tabs']);
    logActivity($userId, 'update_tabs', "Обновил права доступа к вкладкам для пользователя: " . $_POST['user_id']);
    header("Location: manage_tabs.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление доступом к вкладкам</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .search-container {
            margin-bottom: 15px;
            padding: 10px;
            background: white;
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .user-permissions {
            margin-bottom: 8px;
            border: 1px solid #eee;
            border-radius: 6px;
            overflow: hidden;
        }
        .permissions-toggle {
            padding: 8px 12px;
            background: #f8f9fa;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .permissions-toggle h3 {
            margin: 0;
            font-size: 14px;
            flex: 1;
        }
        .permissions-content {
            display: none;
            padding: 10px;
            background: white;
        }
        .permissions-content.show {
            display: block;
        }
        .tab-checkbox {
            padding: 4px 8px;
            margin: 2px 0;
        }
        .permission-section {
            margin-bottom: 10px;
            padding: 8px;
        }
        .permission-section h4 {
            margin: 0 0 8px 0;
            font-size: 14px;
        }
        .search-controls {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
        }
        .search-input {
            flex: 1;
        }
        .role-filter {
            width: 200px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        .search-stats {
            color: #666;
            font-size: 14px;
        }
        .tabs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 10px;
        }
        .tab-checkbox {
            background: #f8f9fa;
            padding: 8px;
            border-radius: 4px;
            transition: background-color 0.2s;
        }
        .tab-checkbox:hover {
            background: #e9ecef;
        }
        .tab-checkbox.disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .tab-checkbox.disabled input {
            pointer-events: none;
        }
        .search-input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        .permissions-toggle {
            cursor: pointer;
            padding: 5px 10px;
            background: #f8f9fa;
            border-radius: 4px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 10px;
        }
        .permissions-toggle i {
            transition: transform 0.3s;
        }
        .permissions-toggle.collapsed i {
            transform: rotate(-90deg);
        }
        .permissions-content {
            transition: max-height 0.3s ease-out;
            overflow: hidden;
        }
        .permissions-content.collapsed {
            max-height: 0;
        }
        .no-results {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-top: 20px;
            color: #666;
        }
        .creator-permissions {
            border: 2px solid #4CAF50;
            background: #f1f8e9;
        }
        .creator-permissions .permissions-toggle {
            background: #e8f5e9;
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <?php renderMenu($userId, $userRole, $firstName, $lastName, 'manage_tabs'); ?>
        
        <div class="main-content">
            <div class="header">
                <h1>Управление доступом к вкладкам</h1>
            </div>

            <div class="search-container">
                <div class="search-controls">
                    <input type="text" class="search-input" placeholder="Поиск пользователей..." id="userSearch">
                    <select id="roleFilter" class="role-filter">
                        <option value="">Все роли</option>
                        <option value="user">Пользователь</option>
                        <option value="admin">Администратор</option>
                        <option value="creator">Создатель</option>
                    </select>
                </div>
                <div class="search-stats">
                    Найдено: <span id="foundUsers">0</span> из <span id="totalUsers">0</span>
                </div>
            </div>

            <div class="users-list">
                <?php foreach ($users as $user): ?>
                    <div class="user-permissions <?= $user['id'] === $userId ? 'creator-permissions' : '' ?>" data-user-name="<?= strtolower($user['last_name'] . ' ' . $user['first_name'] . ' ' . ($user['middle_name'] ?? '')) ?>">
                        <div class="permissions-toggle">
                            <i class="fas fa-chevron-down"></i>
                            <h3><?= htmlspecialchars($user['last_name'] . ' ' . $user['first_name']) ?></h3>
                            <span class="user-role">(<?= htmlspecialchars($user['role']) ?>)</span>
                        </div>
                        
                        <form action="manage_tabs.php" method="post" class="permissions-content">
                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                            
                            <div class="permissions-sections">
                                <?php foreach (getAllTabs() as $section => $permissions): ?>
                                <div class="permission-section">
                                    <h4><?= htmlspecialchars($section) ?></h4>
                                    <div class="tabs-grid">
                                        <?php foreach ($permissions as $tabId => $tabName): ?>
                                        <div class="tab-checkbox">
                                            <label>
                                                <input type="checkbox" 
                                                       name="tabs[]" 
                                                       value="<?= $tabId ?>"
                                                       <?= (isset($tabPermissions['custom'][$user['id']]) ? 
                                                            (in_array($tabId, $tabPermissions['custom'][$user['id']]) ? 'checked' : '') :
                                                            (in_array($tabId, $tabPermissions['default'][$user['role']]) ? 'checked' : '')) ?>>
                                                <?= htmlspecialchars($tabName) ?>
                                            </label>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Сохранить</button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                <div class="no-results" style="display: none;">
                    <i class="fas fa-search"></i>
                    <p>Пользователи не найдены</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('userSearch');
            const roleFilter = document.getElementById('roleFilter');
            const foundUsersSpan = document.getElementById('foundUsers');
            const totalUsersSpan = document.getElementById('totalUsers');
            
            // Отключаем чекбокс "Доступ к сайту" для создателей
            document.querySelectorAll('.user-permissions').forEach(userCard => {
                if (userCard.querySelector('.user-role').textContent.includes('creator')) {
                    const accessCheckbox = userCard.querySelector('input[value="access"]');
                    if (accessCheckbox) {
                        accessCheckbox.checked = true;
                        accessCheckbox.disabled = true;
                        accessCheckbox.parentElement.parentElement.classList.add('disabled');
                    }
                }
            });

            // Обновляем статистику
            function updateStats() {
                const totalUsers = document.querySelectorAll('.user-permissions').length;
                const visibleUsers = document.querySelectorAll('.user-permissions[style=""]').length;
                totalUsersSpan.textContent = totalUsers;
                foundUsersSpan.textContent = visibleUsers;
            }

            // Фильтрация по роли и поиску
            function filterUsers() {
                const searchTerm = searchInput.value.toLowerCase();
                const selectedRole = roleFilter.value;
                let hasResults = false;

                userCards.forEach(card => {
                    const userName = card.dataset.userName.toLowerCase();
                    const userRole = card.querySelector('.user-role').textContent.toLowerCase();
                    const roleMatch = !selectedRole || userRole.includes(selectedRole);
                    const searchMatch = searchTerm === '' || userName.includes(searchTerm);

                    if (roleMatch && searchMatch) {
                        card.style.display = '';
                        hasResults = true;
                    } else {
                        card.style.display = 'none';
                    }
                });

                noResults.style.display = hasResults ? 'none' : 'block';
                updateStats();
            }

            // Обработчики событий
            searchInput.addEventListener('input', filterUsers);
            roleFilter.addEventListener('change', filterUsers);

            // Инициализация
            updateStats();
            // Поиск пользователей
            const userCards = document.querySelectorAll('.user-permissions');
            const noResults = document.querySelector('.no-results');

            // Сворачивание/разворачивание разделов
            const toggles = document.querySelectorAll('.permissions-toggle');
            toggles.forEach(toggle => {
                toggle.addEventListener('click', function() {
                    const content = this.nextElementSibling;
                    content.classList.toggle('show');
                    const icon = this.querySelector('i');
                    icon.classList.toggle('fa-chevron-down');
                    icon.classList.toggle('fa-chevron-right');
                });
            });

            // Все разделы свернуты по умолчанию
            document.querySelectorAll('.permissions-content').forEach(content => {
                content.classList.remove('show');
            });
            document.querySelectorAll('.permissions-toggle i').forEach(icon => {
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-right');
            });
        });
    </script>
</body>
</html>
