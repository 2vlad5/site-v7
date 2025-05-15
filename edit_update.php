
<?php
session_start();
require_once 'functions.php';

$user = checkAccess();
$userId = $user['id'];
$userRole = $user['role'];

// Проверка прав доступа
if (!($userRole === 'creator' || hasTabAccess($userId, 'updates_edit'))) {
    header("Location: updates.php");
    exit();
}

$updates = loadJson('data/updates.json');
$updateData = null;

if (isset($_GET['id'])) {
    foreach ($updates['updates'] as $update) {
        if ($update['id'] === $_GET['id']) {
            $updateData = $update;
            break;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($updates['updates'] as &$update) {
        if ($update['id'] === $_POST['id']) {
            $update['version'] = $_POST['version'];
            $update['release_date'] = $_POST['release_date'];
            $update['description'] = $_POST['description'];
            $update['custom_author'] = $_POST['custom_author'];
            break;
        }
    }
    
    saveJson('data/updates.json', $updates);
    logActivity($userId, 'edit_update', 'Отредактировал запись об обновлении');
    
    header("Location: updates.php");
    exit();
}

if (!$updateData) {
    header("Location: updates.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактирование обновления</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="dashboard">
        <?php renderMenu($userId, $userRole, $user['first_name'], $user['last_name'], 'updates'); ?>
        
        <div class="main-content">
            <div class="header">
                <h1>Редактирование обновления</h1>
            </div>
            
            <div class="add-update-form">
                <form method="POST">
                    <input type="hidden" name="id" value="<?= htmlspecialchars($updateData['id']) ?>">
                    <div class="form-group">
                        <label for="version">Версия (0.00):</label>
                        <input type="text" id="version" name="version" pattern="^\d+\.\d{2}$" title="Формат: 0.00" required value="<?= htmlspecialchars($updateData['version']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="release_date">Дата выпуска:</label>
                        <input type="date" id="release_date" name="release_date" required value="<?= htmlspecialchars($updateData['release_date']) ?>">
                    </div>
                    <div class="form-group">
                        <label for="description">Описание изменений:</label>
                        <textarea id="description" name="description" required rows="4"><?= htmlspecialchars($updateData['description']) ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="custom_author">Автор (необязательно):</label>
                        <input type="text" id="custom_author" name="custom_author" value="<?= htmlspecialchars($updateData['custom_author'] ?? '') ?>" placeholder="Оставьте пустым для автоматического заполнения">
                    </div>
                    <button type="submit" class="btn btn-primary">Сохранить изменения</button>
                    <a href="updates.php" class="btn btn-secondary">Отмена</a>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
