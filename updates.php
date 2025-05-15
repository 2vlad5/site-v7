
<?php
session_start();
require_once 'functions.php';

$user = checkAccess();
$userId = $user['id'];
$userRole = $user['role'];
$firstName = $user['first_name'];
$lastName = $user['last_name'];

// Проверка прав доступа к вкладке
if (!hasTabAccess($userId, 'updates')) {
    header("Location: dashboard.php");
    exit();
}

$updates = loadJson('data/updates.json');

// Обработка добавления новой записи
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($userRole === 'creator' || hasTabAccess($userId, 'updates_create'))) {
    $newUpdate = [
        'id' => generateId(),
        'version' => $_POST['version'],
        'release_type' => $_POST['release_type'],
        'release_date' => $_POST['release_date'],
        'description' => $_POST['description'],
        'created_by' => $userId,
        'created_at' => getClientAdjustedTime(),
        'custom_author' => !empty($_POST['custom_author']) ? $_POST['custom_author'] : ''
    ];
    
    $updates['updates'][] = $newUpdate;
    saveJson('data/updates.json', $updates);
    
    logActivity($userId, 'create_update', 'Добавил новую запись об обновлении версии ' . $_POST['version']);
    header("Location: updates.php");
    exit();
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Обновления системы</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="dashboard">
        <?php renderMenu($userId, $userRole, $firstName, $lastName, 'updates'); ?>
        
        <div class="main-content">
            <div class="header">
                <h1>Обновления системы</h1>
            </div>

            <?php if ($userRole === 'creator' || hasTabAccess($userId, 'updates_create')): ?>
            <div class="add-update-form">
                <h2>Добавить новое обновление</h2>
                <form method="POST" action="updates.php">
                    <div class="update-form-grid">
                        <div class="form-group version-group">
                            <label for="version">Версия (0.00):</label>
                            <div style="display: flex; align-items: center;">
                                <span style="color: red; margin-right: 5px;">v</span>
                                <input type="text" id="version" name="version" pattern="^\d+\.\d{2}$" title="Формат: 0.00" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="release_type">Тип релиза:</label>
                            <select id="release_type" name="release_type" required class="release-type-select">
                                <option value="Test">Test</option>
                                <option value="Alpha">Alpha</option>
                                <option value="Beta">Beta</option>
                                <option value="Stable">Stable</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="release_date">Дата выпуска:</label>
                            <input type="date" id="release_date" name="release_date" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="description">Описание изменений:</label>
                        <div class="description-help">Используйте "-" для создания пунктов списка и "--" для подпунктов (с отступом в 3 пробела). Каждый пункт с новой строки.</div>
                        <textarea id="description" name="description" required rows="8" class="update-description-input" placeholder="- Основной пункт&#10;  - Подпункт&#10;- Другой пункт"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="custom_author">Автор (необязательно):</label>
                        <input type="text" id="custom_author" name="custom_author" placeholder="Оставьте пустым для автоматического заполнения">
                    </div>
                    <button type="submit" class="btn btn-primary btn-add-update">
                        <i class="fas fa-plus"></i> Добавить обновление
                    </button>
                </form>
            </div>
            <?php endif; ?>

            <div class="updates-list">
                <?php foreach (array_reverse($updates['updates']) as $update): ?>
                    <?php $author = getUserById($update['created_by']); ?>
                    <div class="update-item">
                        <div class="update-header">
                            <h3><span style="color: red;">v</span> <?= htmlspecialchars($update['version']) ?> (<?= htmlspecialchars($update['release_type'] ?? 'Stable') ?>)</h3>
                            <span class="update-date">Дата выпуска: <?= date('d.m.Y', strtotime($update['release_date'])) ?></span>
                        </div>
                        <div class="update-author">
                            Автор: <?= !empty($update['custom_author']) ? htmlspecialchars($update['custom_author']) : htmlspecialchars($author['last_name'] . ' ' . $author['first_name']) ?>
                        </div>
                        <div class="update-description">
                            <?php
                            $lines = explode("\n", htmlspecialchars($update['description']));
                            echo '<div class="update-points">';
                            foreach ($lines as $line) {
                                $line = trim($line);
                                if (empty($line)) continue;
                                if (str_starts_with($line, '--')) {
                                    echo '<div class="update-subpoint">' . substr($line, 2) . '</div>';
                                } elseif (str_starts_with($line, '-')) {
                                    echo '<div class="update-point">' . substr($line, 1) . '</div>';
                                } else {
                                    echo '<div class="update-text">' . $line . '</div>';
                                }
                            }
                            echo '</div>';
                            ?>
                        </div>
                        <?php if ($userRole === 'creator' || hasTabAccess($userId, 'updates_edit') || hasTabAccess($userId, 'updates_delete')): ?>
                        <div class="update-actions">
                            <?php if ($userRole === 'creator' || hasTabAccess($userId, 'updates_edit')): ?>
                            <a href="#" class="btn btn-sm btn-primary" onclick="editUpdate('<?= $update['id'] ?>')">
                                <i class="fas fa-edit"></i> Редактировать
                            </a>
                            <?php endif; ?>
                            <?php if ($userRole === 'creator' || hasTabAccess($userId, 'updates_delete')): ?>
                            <a href="#" class="btn btn-sm btn-danger" onclick="deleteUpdate('<?= $update['id'] ?>')">
                                <i class="fas fa-trash"></i> Удалить
                            </a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                
                <script>
                function editUpdate(id) {
                    if (confirm('Редактировать это обновление?')) {
                        // Здесь будет реализована функция редактирования
                        window.location.href = 'edit_update.php?id=' + id;
                    }
                }
                
                function deleteUpdate(id) {
                    if (confirm('Вы уверены, что хотите удалить это обновление?')) {
                        window.location.href = 'delete_update.php?id=' + id;
                    }
                }
                </script>
            </div>
        </div>
    </div>
</body>
</html>
