<?php
session_start();
require_once  'functions.php';

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

// Загружаем все журналы
$allJournals = loadJson('data/journals.json');

// Получаем список резервных копий
$backupsDirectory = 'data/backups';
if (!file_exists($backupsDirectory)) {
    mkdir($backupsDirectory, 0777, true);
}

$backupFiles = scandir($backupsDirectory);
$backupFiles = array_diff($backupFiles, array('.', '..'));

// Сортируем файлы резервных копий по времени создания (от новых к старым)
usort($backupFiles, function($a, $b) use ($backupsDirectory) {
    return filemtime($backupsDirectory . '/' . $b) - filemtime($backupsDirectory . '/' . $a);
});

// Группировка журналов по пользователям для удобства интерфейса
$journalsByUser = getJournalsGroupedByUsers($allJournals);

// Обработка действий по экспорту и импорту
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'backup_all':
                // Резервное копирование всех журналов
                $backupData = [
                    'journals' => $allJournals,
                    'entries' => [],
                    'recovery_requests' => loadJson('data/recovery_requests.json')
                ];
                
                // Собираем данные записей для всех журналов
                foreach ($allJournals as $journal) {
                    $journalId = $journal['id'];
                    $entriesFile = "data/entries/{$journalId}.json";
                    if (file_exists($entriesFile)) {
                        $backupData['entries'][$journalId] = loadJson($entriesFile);
                    }
                }
                
                // Создаем файл резервной копии
                $backupFileName = 'all_journals_backup_' . date('Y-m-d_H-i-s') . '.json';
                $backupFilePath = $backupsDirectory . '/' . $backupFileName;
                
                file_put_contents($backupFilePath, json_encode($backupData, JSON_PRETTY_PRINT));
                
                // Логируем создание резервной копии
                logActivity($userId, 'backup_journals', "Создал резервную копию всех журналов: {$backupFileName}");
                
                $message = "Резервная копия всех журналов успешно создана: {$backupFileName}";
                $messageType = 'success';
                break;
                
            case 'backup_journal':
                // Резервное копирование конкретного журнала
                $journalId = $_POST['journal_id'] ?? '';
                
                if (empty($journalId)) {
                    $message = "Ошибка: ID журнала не указан.";
                    $messageType = 'error';
                    break;
                }
                
                // Ищем журнал
                $journalData = null;
                foreach ($allJournals as $journal) {
                    if ($journal['id'] === $journalId) {
                        $journalData = $journal;
                        break;
                    }
                }
                
                if (!$journalData) {
                    $message = "Ошибка: Журнал не найден.";
                    $messageType = 'error';
                    break;
                }
                
                // Получаем запросы на восстановление для этого журнала
                $recoveryRequests = loadJson('data/recovery_requests.json');
                $journalRecoveryRequests = [];
                foreach ($recoveryRequests as $request) {
                    if ($request['journal_id'] === $journalId) {
                        $journalRecoveryRequests[] = $request;
                    }
                }
                
                // Создаем резервную копию журнала
                $backupData = [
                    'journals' => [$journalData],
                    'entries' => [],
                    'recovery_requests' => $journalRecoveryRequests
                ];
                
                // Добавляем записи журнала
                $entriesFile = "data/entries/{$journalId}.json";
                if (file_exists($entriesFile)) {
                    $backupData['entries'][$journalId] = loadJson($entriesFile);
                }
                
                // Создаем файл резервной копии
                $backupFileName = 'journal_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $journalData['title']) . '_backup_' . date('Y-m-d_H-i-s') . '.json';
                $backupFilePath = $backupsDirectory . '/' . $backupFileName;
                
                file_put_contents($backupFilePath, json_encode($backupData, JSON_PRETTY_PRINT));
                
                // Логируем создание резервной копии
                logActivity($userId, 'backup_journal', "Создал резервную копию журнала '{$journalData['title']}': {$backupFileName}", $journalId);
                
                $message = "Резервная копия журнала '{$journalData['title']}' успешно создана: {$backupFileName}";
                $messageType = 'success';
                break;
                
            case 'restore_backup':
                // Восстановление из резервной копии
                $backupFile = $_POST['backup_file'] ?? '';
                
                if (empty($backupFile)) {
                    $message = "Ошибка: Файл резервной копии не указан.";
                    $messageType = 'error';
                    break;
                }
                
                $backupFilePath = $backupsDirectory . '/' . $backupFile;
                
                if (!file_exists($backupFilePath)) {
                    $message = "Ошибка: Файл резервной копии не существует.";
                    $messageType = 'error';
                    break;
                }
                
                // Загружаем данные резервной копии
                $backupContent = file_get_contents($backupFilePath);
                $backupData = json_decode($backupContent, true);
                
                if (!$backupData || !isset($backupData['journals'])) {
                    $message = "Ошибка: Некорректный формат файла резервной копии.";
                    $messageType = 'error';
                    break;
                }
                
                // Определяем режим восстановления
                $restoreMode = $_POST['restore_mode'] ?? 'merge';
                
                if ($restoreMode === 'replace') {
                    // Полная замена данных
                    saveJson('data/journals.json', $backupData['journals']);
                    
                    // Восстанавливаем записи журналов
                    foreach ($backupData['entries'] as $journalId => $entries) {
                        $entriesFile = "data/entries/{$journalId}.json";
                        saveJson($entriesFile, $entries);
                    }
                    
                    // Восстанавливаем запросы на восстановление, если они есть в бэкапе
                    if (isset($backupData['recovery_requests'])) {
                        saveJson('data/recovery_requests.json', $backupData['recovery_requests']);
                    }
                } else {
                    // Слияние данных
                    $currentJournals = loadJson('data/journals.json');
                    $updatedJournals = $currentJournals;
                    
                    // Добавляем или обновляем журналы из резервной копии
                    foreach ($backupData['journals'] as $backupJournal) {
                        $journalExists = false;
                        
                        foreach ($updatedJournals as &$currentJournal) {
                            if ($currentJournal['id'] === $backupJournal['id']) {
                                // Обновляем существующий журнал
                                $currentJournal = $backupJournal;
                                $journalExists = true;
                                break;
                            }
                        }
                        
                        if (!$journalExists) {
                            // Добавляем новый журнал
                            $updatedJournals[] = $backupJournal;
                        }
                    }
                    
                    // Сохраняем обновленные журналы
                    saveJson('data/journals.json', $updatedJournals);
                    
                    // Восстанавливаем записи журналов
                    foreach ($backupData['entries'] as $journalId => $entries) {
                        $entriesFile = "data/entries/{$journalId}.json";
                        
                        // Проверяем существование текущих записей
                        if (file_exists($entriesFile)) {
                            $currentEntries = loadJson($entriesFile);
                            $updatedEntries = array_merge($currentEntries, $entries);
                            saveJson($entriesFile, $updatedEntries);
                        } else {
                            saveJson($entriesFile, $entries);
                        }
                    }
                    
                    // Объединяем запросы на восстановление, если они есть в бэкапе
                    if (isset($backupData['recovery_requests'])) {
                        $currentRecoveryRequests = loadJson('data/recovery_requests.json');
                        $backupRecoveryRequests = $backupData['recovery_requests'];
                        
                        // Создаем массив с идентификаторами текущих запросов для быстрого поиска
                        $currentRequestIds = [];
                        foreach ($currentRecoveryRequests as $request) {
                            $currentRequestIds[$request['id']] = true;
                        }
                        
                        // Добавляем запросы из бэкапа, которых нет в текущих
                        foreach ($backupRecoveryRequests as $request) {
                            if (!isset($currentRequestIds[$request['id']])) {
                                $currentRecoveryRequests[] = $request;
                            }
                        }
                        
                        saveJson('data/recovery_requests.json', $currentRecoveryRequests);
                    }
                }
                
                // Логируем восстановление из резервной копии
                logActivity($userId, 'restore_backup', "Восстановил данные из резервной копии: {$backupFile} (режим: " . ($restoreMode === 'replace' ? 'замена' : 'слияние') . ")");
                
                $message = "Данные успешно восстановлены из резервной копии: {$backupFile}";
                $messageType = 'success';
                break;
                
            case 'delete_backup':
                // Удаление резервной копии
                $backupFile = $_POST['backup_file'] ?? '';
                
                if (empty($backupFile)) {
                    $message = "Ошибка: Файл резервной копии не указан.";
                    $messageType = 'error';
                    break;
                }
                
                $backupFilePath = $backupsDirectory . '/' . $backupFile;
                
                if (!file_exists($backupFilePath)) {
                    $message = "Ошибка: Файл резервной копии не существует.";
                    $messageType = 'error';
                    break;
                }
                
                // Удаляем файл
                unlink($backupFilePath);
                
                // Логируем удаление резервной копии
                logActivity($userId, 'delete_backup', "Удалил резервную копию: {$backupFile}");
                
                $message = "Резервная копия успешно удалена: {$backupFile}";
                $messageType = 'success';
                break;
                
            case 'upload_backup':
                // Загрузка резервной копии из файла
                if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
                    $message = "Ошибка: Не удалось загрузить файл.";
                    $messageType = 'error';
                    break;
                }
                
                $uploadedFile = $_FILES['backup_file'];
                $fileName = basename($uploadedFile['name']);
                $targetFilePath = $backupsDirectory . '/' . $fileName;
                
                // Проверяем расширение файла
                $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                if ($fileExtension !== 'json') {
                    $message = "Ошибка: Разрешены только файлы с расширением .json.";
                    $messageType = 'error';
                    break;
                }
                
                // Проверяем формат файла
                $fileContent = file_get_contents($uploadedFile['tmp_name']);
                $fileData = json_decode($fileContent, true);
                
                if (!$fileData || !isset($fileData['journals'])) {
                    $message = "Ошибка: Некорректный формат файла резервной копии.";
                    $messageType = 'error';
                    break;
                }
                
                // Перемещаем файл в директорию резервных копий
                if (move_uploaded_file($uploadedFile['tmp_name'], $targetFilePath)) {
                    // Логируем загрузку резервной копии
                    logActivity($userId, 'upload_backup', "Загрузил резервную копию: {$fileName}");
                    
                    $message = "Резервная копия успешно загружена: {$fileName}";
                    $messageType = 'success';
                } else {
                    $message = "Ошибка: Не удалось сохранить загруженный файл.";
                    $messageType = 'error';
                }
                break;
        }
    }
    
    // Обновляем список резервных копий после обработки действий
    $backupFiles = scandir($backupsDirectory);
    $backupFiles = array_diff($backupFiles, array('.', '..'));
    
    // Сортируем файлы резервных копий по времени создания (от новых к старым)
    usort($backupFiles, function($a, $b) use ($backupsDirectory) {
        return filemtime($backupsDirectory . '/' . $b) - filemtime($backupsDirectory . '/' . $a);
    });
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Рабочий журнал - Сохраненные журналы</title>
    <meta name="description" content="Built with jdoodle.ai - Система учета рабочего времени с возможностью создания резервных копий">
    <meta property="og:title" content="Рабочий журнал - Сохраненные журналы">
    <meta property="og:description" content="Built with jdoodle.ai - Удобная система учета рабочего времени с возможностью экспорта и импорта журналов">
    <meta property="og:image" content="https://images.unsplash.com/photo-1496450681664-3df85efbd29f?ixid=M3w3MjUzNDh8MHwxfHNlYXJjaHwxfHxkYXRhYmFzZSUyMGJhY2t1cCUyMGZpbGVzJTIwc3RvcmFnZSUyMGFyY2hpdmUlMjBjbG91ZCUyMGRpZ2l0YWx8ZW58MHx8fHwxNzQyOTIwNzc4fDA&ixlib=rb-4.0.3&fit=fillmax&h=450&w=800">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="dashboard">

        <?php renderMenu($userId, $userRole, $firstName, $lastName); ?>

        <div class="main-content">
            <div class="header">
                <h1>Сохраненные журналы</h1>
            </div>
            
            <?php if (!empty($message)): ?>
                <div class="<?= $messageType === 'error' ? 'error-message' : 'success-message' ?>">
                    <i class="fas <?= $messageType === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle' ?>"></i>
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>
            
            <div class="backup-container">
                <div class="backup-sections">
                    <div class="backup-section">
                        <h3><i class="fas fa-cloud-upload-alt"></i> Создание резервных копий</h3>
                        <div class="backup-options">
                            <div class="backup-option">
                                <h4>Резервное копирование всех журналов</h4>
                                <p>Создайте копию всех журналов и их записей</p>
                                <form action="backup_journals.php" method="post">
                                    <input type="hidden" name="action" value="backup_all">
                                    <button type="submit" class="btn btn-primary">Сохранить все журналы</button>
                                </form>
                            </div>
                            
                            <div class="backup-option">
                                <h4>Резервное копирование отдельного журнала</h4>
                                <p>Выберите конкретный журнал для резервного копирования</p>
                                <form action="backup_journals.php" method="post">
                                    <input type="hidden" name="action" value="backup_journal">
                                    <div class="form-group">
                                        <select name="journal_id" class="form-control" required>
                                            <option value="">-- Выберите журнал --</option>
                                            <?php foreach ($journalsByUser as $userId => $userJournals): 
                                                $user = $userJournals['user'];
                                                $userFullName = htmlspecialchars($user['last_name'] . ' ' . $user['first_name'] . ' ' . ($user['middle_name'] ?? ''));
                                            ?>
                                                <optgroup label="<?= $userFullName ?>">
                                                    <?php foreach ($userJournals['journals'] as $journal): ?>
                                                        <option value="<?= $journal['id'] ?>"><?= htmlspecialchars($journal['title']) ?> (<?= $journal['year'] ?>)</option>
                                                    <?php endforeach; ?>
                                                </optgroup>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Сохранить журнал</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="backup-section">
                        <h3><i class="fas fa-cloud-download-alt"></i> Управление резервными копиями</h3>
                        
                        <div class="backup-options">
                            <div class="backup-option">
                                <h4>Загрузка резервной копии</h4>
                                <p>Вы можете загрузить файл резервной копии в формате JSON</p>
                                <form action="backup_journals.php" method="post" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="upload_backup">
                                    <div class="form-group">
                                        <input type="file" name="backup_file" accept=".json" required>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Загрузить файл</button>
                                </form>
                            </div>
                            
                            <?php if (!empty($backupFiles)): ?>
                            <div class="backup-option">
                                <h4>Восстановление из резервной копии</h4>
                                <p>Восстановите данные из существующей резервной копии</p>
                                <form action="backup_journals.php" method="post">
                                    <input type="hidden" name="action" value="restore_backup">
                                    <div class="form-group">
                                        <select name="backup_file" class="form-control" required>
                                            <option value="">-- Выберите файл резервной копии --</option>
                                            <?php foreach ($backupFiles as $file): ?>
                                                <option value="<?= htmlspecialchars($file) ?>"><?= htmlspecialchars($file) ?> (<?= date('d.m.Y H:i', filemtime($backupsDirectory . '/' . $file)) ?>)</option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Режим восстановления:</label>
                                        <div class="radio-group">
                                            <label>
                                                <input type="radio" name="restore_mode" value="merge" checked>
                                                Слияние (добавление новых и обновление существующих данных)
                                            </label>
                                            <label>
                                                <input type="radio" name="restore_mode" value="replace">
                                                Полная замена (удаление всех текущих данных)
                                            </label>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-warning">Восстановить данные</button>
                                </form>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if (!empty($backupFiles)): ?>
                    <div class="backup-section">
                        <h3><i class="fas fa-list"></i> Существующие резервные копии</h3>
                        <div class="backup-files">
                            <table class="backup-table">
                                <thead>
                                    <tr>
                                        <th>Название файла</th>
                                        <th>Размер</th>
                                        <th>Дата создания</th>
                                        <th>Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($backupFiles as $file): 
                                        $filePath = $backupsDirectory . '/' . $file;
                                        $fileSize = round(filesize($filePath) / 1024, 2); // в КБ
                                        $fileDate = date('d.m.Y H:i', filemtime($filePath));
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($file) ?></td>
                                        <td><?= $fileSize ?> KB</td>
                                        <td><?= $fileDate ?></td>
                                        <td>
                                            <div class="backup-actions">
                                                <a href="download_backup.php?file=<?= urlencode($file) ?>" class="btn btn-sm" title="Скачать"><i class="fas fa-download"></i></a>
                                                <form action="backup_journals.php" method="post" class="inline-form" onsubmit="return confirm('Вы уверены, что хотите удалить эту резервную копию?');">
                                                    <input type="hidden" name="action" value="delete_backup">
                                                    <input type="hidden" name="backup_file" value="<?= htmlspecialchars($file) ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger" title="Удалить"><i class="fas fa-trash"></i></button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
 