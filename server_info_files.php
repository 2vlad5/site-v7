<?php
session_start();
require_once 'functions.php';

// Function to delete file or directory
function deleteFileOrDirectory($path) {
    if (is_dir($path)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            if ($fileinfo->isDir()) {
                rmdir($fileinfo->getRealPath());
            } else {
                unlink($fileinfo->getRealPath());
            }
        }
        rmdir($path);
    } else {
        unlink($path);
    }
}
$user = checkAccess();
$userId = $user['id'];
$userRole = $user['role'];
$firstName = $user['first_name'];
$lastName = $user['last_name'];

if (!$user || $userRole !== 'creator' && !hasTabAccess($userId, 'server_info_files')) {
    header("Location: dashboard.php");
    exit();
}

// Получение размера файлов
function getDirectorySize($path) {
    $size = 0;
    $files = scandir($path);
    foreach ($files as $file) {
        if ($file == '.' || $file == '..') continue;
        $fullPath = $path . '/' . $file;
        if (is_dir($fullPath)) {
            $size += getDirectorySize($fullPath);
        } else {
            $size += filesize($fullPath);
        }
    }
    return $size;
}

// Получение списка файлов и их размеров
function formatSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

function getFilesList($path, $level = 0) {
    $files = [];
    $items = scandir($path);
    foreach ($items as $item) {
        if ($item == '.' || $item == '..') continue;
        $fullPath = $path . '/' . $item;
        $isDir = is_dir($fullPath);
        $files[] = [
            'name' => $item,
            'path' => $fullPath,
            'size' => $isDir ? getDirectorySize($fullPath) : filesize($fullPath),
            'type' => $isDir ? 'directory' : pathinfo($fullPath, PATHINFO_EXTENSION),
            'level' => $level,
            'is_dir' => $isDir
        ];

        if ($isDir) {
            $subFiles = getFilesList($fullPath, $level + 1);
            $files = array_merge($files, $subFiles);
        }
    }
    return $files;
}

// Загрузка статуса обслуживания
$maintenanceFile = 'data/maintenance.json';
if (!file_exists($maintenanceFile)) {
    file_put_contents($maintenanceFile, json_encode(['enabled' => false]));
}
$maintenance = json_decode(file_get_contents($maintenanceFile), true);

// Обработка включения/выключения режима обслуживания
if (isset($_POST['toggle_maintenance'])) {
    $maintenance['enabled'] = !$maintenance['enabled'];
    file_put_contents($maintenanceFile, json_encode($maintenance));

    // If queue is being enabled, check for files to delete
    if ($maintenance['enabled']) {
        $deletedFiles = loadJson('data/deleted_files.json');
        $currentTime = time();
        $updatedFiles = [];

        foreach ($deletedFiles as $file) {
            if ($file['deletion_time'] <= $currentTime) {
                // Delete file if time has passed
                if (file_exists($file['path'])) {
                    deleteFileOrDirectory($file['path']);
                }
            } else {
                // Keep files that shouldn't be deleted yet
                $updatedFiles[] = $file;
            }
        }

        // Update deleted files list
        saveJson('data/deleted_files.json', $updatedFiles);
    }

    header("Location: server_info_files.php");
    exit();
}

// Обработка скачивания файлов
if (isset($_GET['action']) && $_GET['action'] === 'download') {
    $path = $_GET['path'];
    $realPath = realpath($path);

    if ($realPath === false || !is_readable($path)) {
        header("HTTP/1.0 404 Not Found");
        exit;
    }

    if ($path === '.') {
        // Скачивание всех файлов
        $zipname = 'all_files_' . date('Y-m-d_H-i-s') . '.zip';
        $zip = new ZipArchive();
        $zip->open($zipname, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator('.', 
                RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen(realpath('.')) + 1);

            // Пропускаем временные файлы и директории
            if (strpos($relativePath, 'temp') === 0 ||
                strpos($relativePath, $zipname) === 0) {
                continue;
            }

            if ($file->isDir()) {
                $zip->addEmptyDir($relativePath);
            } else {
                $zip->addFile($filePath, $relativePath);
            }
        }

        $zip->close();

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="'.basename($zipname).'"');
        header('Content-Length: ' . filesize($zipname));
        readfile($zipname);
        unlink($zipname); // Удаляем временный файл
        exit;
    } else {
        // Для одиночных файлов
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.basename($path).'"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
}

// Обработка удаления файлов
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $deletedFiles = loadJson('data/deleted_files.json');

    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'read_file':
                $filePath = $_POST['file_path'];
                if (file_exists($filePath) && is_readable($filePath)) {
                    $content = file_get_contents($filePath);
                    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

                    header('Content-Type: application/json');

                    // Определяем тип файла для правильного отображения
                    $isText = in_array($extension, ['txt', 'php', 'js', 'css', 'html', 'json', 'md', 'log', 'ini']);
                    $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp']);

                    if ($content !== false) {
                        if ($isImage) {
                            $imageData = base64_encode($content);
                            echo json_encode([
                                'success' => true,
                                'content' => $imageData,
                                'type' => 'image',
                                'extension' => $extension,
                                'mime' => mime_content_type($filePath)
                            ]);
                        } else {
                            // For text files, ensure proper encoding
                            $encoding = mb_detect_encoding($content, 'UTF-8, Windows-1251, ISO-8859-1');
                            if ($encoding === false) $encoding = 'UTF-8';
                            $encodedContent = mb_convert_encoding($content, 'UTF-8', $encoding);
                            echo json_encode([
                                'success' => true,
                                'content' => base64_encode($encodedContent),
                                'type' => 'text',
                                'extension' => $extension,
                                'encoding' => $encoding
                            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        }
                    } else {
                        echo json_encode([
                            'success' => false,
                            'error' => 'Не удалось прочитать содержимое файла'
                        ]);
                    }
                } else {
                    echo json_encode([
                        'success' => false,
                        'error' => 'Файл не существует или недоступен для чтения'
                    ]);
                }
                exit;

            case 'save_file':
                $filePath = $_POST['file_path'];
                $content = $_POST['content'];
                if (is_writable($filePath)) {
                    if (file_put_contents($filePath, $content) !== false) {
                        echo json_encode(['success' => true]);
                    } else {
                        echo json_encode(['success' => false, 'error' => 'Failed to write file']);
                    }
                } else {
                    echo json_encode(['success' => false, 'error' => 'File is not writable']);
                }
                exit;
            case 'create_directory':
                $dirName = $_POST['directory_name'];
                $newPath = './' . trim($dirName, '/');
                if (!file_exists($newPath)) {
                    mkdir($newPath, 0777, true);
                }
                header("Location: server_info_files.php");
                exit;

            case 'create_file':
                $filePath = $_POST['file_path'];
                $newPath = './' . trim($filePath, '/');
                if (!file_exists($newPath)) {
                    touch($newPath);
                    chmod($newPath, 0666);
                }
                header("Location: server_info_files.php");
                exit;

            case 'upload_files':
                if (isset($_FILES['files'])) {
                    foreach ($_FILES['files']['tmp_name'] as $key => $tmp_name) {
                        if ($_FILES['files']['error'][$key] === UPLOAD_ERR_OK) {
                            $fileName = $_FILES['files']['name'][$key];
                            move_uploaded_file($tmp_name, './' . $fileName);
                        }
                    }
                }
                header("Location: server_info_files.php");
                exit;
                break;
            case 'schedule_deletion':
                $path = $_POST['path'];
                $delay = (int)$_POST['delay'];
                $deletionTime = time() + $delay;

                $deletedFiles[] = [
                    'path' => $path,
                    'scheduled_at' => time(),
                    'deletion_time' => $deletionTime,
                    'status' => 'scheduled'
                ];

                saveJson('data/deleted_files.json', $deletedFiles);

                // Проверяем включена ли очередь на удаление
                if ($maintenance['enabled']) {
                    if ($delay === 0) {
                        // Немедленное удаление
                        deleteFileOrDirectory($path);
                    } else {
                        // Проверяем, не пришло ли время удалять отложенные файлы
                        $currentTime = time();
                        foreach ($deletedFiles as $key => $file) {
                            if ($file['deletion_time'] <= $currentTime) {
                                deleteFileOrDirectory($file['path']);
                                unset($deletedFiles[$key]);
                            }
                        }
                        $deletedFiles = array_values($deletedFiles);
                        saveJson('data/deleted_files.json', $deletedFiles);
                    }
                } else {
                    // Если очередь отключена, просто добавляем в список без проверки времени
                    if ($delay === 0) {
                        deleteFileOrDirectory($path);
                    }
                }
                break;

            case 'cancel_deletion':
                $path = $_POST['path'];
                foreach ($deletedFiles as $key => $file) {
                    if ($file['path'] === $path) {
                        unset($deletedFiles[$key]);
                    }
                }
                $deletedFiles = array_values($deletedFiles);
                saveJson('data/deleted_files.json', $deletedFiles);
                break;

            case 'rename_file':
                $oldPath = $_POST['old_path'];
                $newFilename = $_POST['new_filename'];
                $newPath = dirname($oldPath) . '/' . $newFilename;

                if (rename($oldPath, $newPath)) {
                    // Update deleted files if necessary
                    foreach ($deletedFiles as &$delFile) {
                        if ($delFile['path'] === $oldPath) {
                            $delFile['path'] = $newPath;
                        }
                    }
                    saveJson('data/deleted_files.json', $deletedFiles);
                }

                break;
        }

        header("Location: server_info_files.php");
        exit();
    }
}

$files = getFilesList('.');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Информация о сервере</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/theme/monokai.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/xml/xml.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/javascript/javascript.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/css/css.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/clike/clike.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/htmlmixed/htmlmixed.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/php/php.min.js"></script>
    <style>
    .file-actions {
        display: flex;
        gap: 5px;
    }
    .file-actions button {
        padding: 5px 10px;
        border: none;
        background-color: #4CAF50;
        color: white;
        cursor: pointer;
        border-radius: 3px;
    }
    .file-actions button.delete {
        background-color: #f44336;
    }
    .file-actions input[type=number] {
        width: 50px;
    }
    .directory-toggle {
        cursor: pointer;
        margin-right: 5px;
    }
    .directory-content {
        display: none;
    }
    .directory-content.expanded {
        display: table-row;
    }
    .toggle-icon {
        display: inline-block;
        width: 16px;
        text-align: center;
    }
    .delete-actions {
        display: flex;
        gap: 5px;
    }
    .delete, .delete-later {
        padding: 5px 10px;
        border: none;
        background-color: #f44336;
        color: white;
        cursor: pointer;
        border-radius: 3px;
    }
    .delete-later {
        background-color: #ff9800;
    }
    #deleteDialog {
        display: none;
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: white;
        padding: 20px;
        border-radius: 5px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        z-index: 1000;
    }
    #deleteDialog input {
        margin: 10px 0;
        padding: 5px;
    }
    #deleteDialog button {
        margin: 5px;
        padding: 5px 15px;
    }
    .dialog-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.5);
        z-index: 999;
    }
    #renameDialog {
        display: none;
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: white;
        padding: 20px;
        border-radius: 5px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        z-index: 1001;
    }

    #renameDialog input {
        margin: 10px 0;
        padding: 5px;
    }

    #renameDialog button {
        margin: 5px;
        padding: 5px 15px;
    }

    #createFileDialog {
        display: none;
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: white;
        padding: 20px;
        border-radius: 5px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        z-index: 1001;
    }

    #createFileDialog input {
        margin: 10px 0;
        padding: 5px;
        width: 100%;
    }

    #createFileDialog button {
        margin: 5px;
        padding: 5px 15px;
    }

    </style>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initially hide only nested directory contents
        const allFileRows = document.querySelectorAll('tr[data-parent]');
        allFileRows.forEach(row => {
            const parentPath = row.getAttribute('data-parent');
            if (parentPath && parentPath.includes('/')) {
                row.style.display = 'none';
            }
        });

        // Initially hide file types grid
        document.getElementById('fileTypesGrid').style.display = 'none';
    });

    function hideAllSubdirectories(path) {
        const allRows = document.querySelectorAll('tr');
        allRows.forEach(row => {
            if (row.getAttribute('data-parent') && row.getAttribute('data-parent').startsWith(path + '/')) {
                row.style.display = 'none';
                const parentPath = row.getAttribute('data-parent');
                const toggleIcon = document.querySelector(`#toggle-${parentPath.replace(/[\/\.]/g, '_')} .toggle-icon`);
                if (toggleIcon) toggleIcon.textContent = '+';
            }
        });
    }

    function toggleDirectory(path) {
        const icon = document.querySelector(`#toggle-${path.replace(/[\/\.]/g, '_')} .toggle-icon`);
        const currentRow = document.querySelector(`tr[data-path="${path}"]`);
        const directChildren = document.querySelectorAll(`tr[data-parent="${path}"]`);
        const isExpanded = directChildren[0]?.style.display !== 'none';

        if (isExpanded) {
            // Hide all nested content
            const allRows = document.querySelectorAll('tr[data-parent]');
            allRows.forEach(row => {
                const parentPath = row.getAttribute('data-parent');
                if (parentPath && parentPath.startsWith(path)) {
                    row.style.display = 'none';
                    if (row.classList.contains('directory-row')) {
                        const toggleIcon = row.querySelector('.toggle-icon');
                        if (toggleIcon) toggleIcon.textContent = '+';
                    }
                }
            });
            icon.textContent = '+';
        } else {
            // Move and show direct children
            const nextRow = currentRow.nextElementSibling;
            directChildren.forEach(row => {
                currentRow.parentNode.insertBefore(row, nextRow);
                row.style.display = 'table-row';
            });
            icon.textContent = '−';
        }
    }

    let currentPath = '';
    let currentName = '';

    function showRenameDialog(path, name) {
        currentPath = path;
        currentName = name;
        document.getElementById('renameFilename').value = name;
        document.getElementById('renameDialog').style.display = 'block';
        document.getElementById('dialogOverlay').style.display = 'block';
    }

    function closeRenameDialog() {
        document.getElementById('renameDialog').style.display = 'none';
        document.getElementById('dialogOverlay').style.display = 'none';
    }

    function submitRename() {
        const newFilename = document.getElementById('renameFilename').value;
        const form = document.createElement('form');
        form.method = 'post';

        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'rename_file';

        const oldPathInput = document.createElement('input');
        oldPathInput.type = 'hidden';
        oldPathInput.name = 'old_path';
        oldPathInput.value = currentPath;

        const newFilenameInput = document.createElement('input');
        newFilenameInput.type = 'hidden';
        newFilenameInput.name = 'new_filename';
        newFilenameInput.value = newFilename;

        form.appendChild(actionInput);
        form.appendChild(oldPathInput);
        form.appendChild(newFilenameInput);
        document.body.appendChild(form);
        form.submit();
    }

    function showDeleteDialog(path) {
        currentPath = path;
        document.getElementById('deleteDialog').style.display = 'block';
        document.getElementById('dialogOverlay').style.display = 'block';
    }

    function closeDeleteDialog() {
        document.getElementById('deleteDialog').style.display = 'none';
        document.getElementById('dialogOverlay').style.display = 'none';
    }

    function submitDelete() {
        const minutes = document.getElementById('deleteMinutes').value;
        const form = document.createElement('form');
        form.method = 'post';

        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'schedule_deletion';

        const pathInput = document.createElement('input');
        pathInput.type = 'hidden';
        pathInput.name = 'path';
        pathInput.value = currentPath;

        const delayInput = document.createElement('input');
        delayInput.type = 'hidden';
        delayInput.name = 'delay';
        delayInput.value = minutes * 60; // Convert minutes to seconds

        form.appendChild(actionInput);
        form.appendChild(pathInput);
        form.appendChild(delayInput);
        document.body.appendChild(form);
        form.submit();
    }

    function toggleFileTypes() {
        const grid = document.getElementById('fileTypesGrid');
        const icon = document.getElementById('fileTypesIcon');
        if (grid.style.display === 'none') {
            grid.style.display = 'grid';
            icon.className = 'fas fa-chevron-down';
        } else {
            grid.style.display = 'none';
            icon.className = 'fas fa-chevron-right';
        }
    }

    function filterFiles(searchTerm) {
        searchTerm = searchTerm.toLowerCase();
        const fileRows = document.querySelectorAll('tr[data-path]');

        fileRows.forEach(row => {
            const filePath = row.getAttribute('data-path').toLowerCase();
            const fileName = row.querySelector('td:first-child').textContent.toLowerCase(); // Get the filename from the first cell
            const fileType = row.querySelector('td:nth-child(2)').textContent.toLowerCase(); // Get the filetype from the second cell

            if (fileName.includes(searchTerm) || fileType.includes(searchTerm)) {
                row.style.display = 'table-row';
            } else {
                row.style.display = 'none';
            }
        });
    }
    </script>
    <style>
    .file-actions {
        display: flex;
        gap: 5px;
        justify-content: center;
    }

    .file-actions button, 
    .file-actions a {
        width: 38px;
        height: 38px;
        border: none;
        border-radius: 3px;
        color: white;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .file-actions button i,
    .file-actions a i {
        font-size: 16px;
    }

    .file-actions .btn-danger {
        background-color: #f44336;
    }

    .file-actions .btn-warning {
        background-color: #ff9800;
    }

    .file-actions .btn-primary {
        background-color: #2196F3;
    }

    .file-actions .btn-success {
        background-color: #4CAF50;
    }

    .file-actions .btn-secondary {
        background-color: #757575;
    }

    .file-actions button:hover,
    .file-actions a:hover {
        opacity: 0.9;
    }

    .file-types-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 10px;
    }
    </style>
</head>
<body>
    <div class="dashboard">
        <?php renderMenu($userId, $userRole, $firstName, $lastName, 'server_info_files.php'); ?>

        <div class="main-content">
            <div class="header">
                <h1>Информация о сервере</h1>
            </div>

            <div class="server-info-container">
                <!-- Раздел файловой системы -->
                <div class="info-section">
                    <h2><i class="fas fa-hard-drive"></i> Файловая система</h2>
                    <div class="files-list modern-table">
                        <table>
                            <thead>
                                <tr class="table-header">
                                    <th class="file-column"><i class="fas fa-file-alt"></i> Файл/Директория</th>
                                    <th class="type-column"><i class="fas fa-tag"></i> Тип</th>
                                    <th class="size-column"><i class="fas fa-weight"></i> Размер</th>
                                    <th style="text-align: center;"><i class="fas fa-cogs"></i> Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                    $deletedFiles = loadJson('data/deleted_files.json');

                                    // Фильтрация файлов по поисковому запросу
                                    // if (isset($_GET['search']) && !empty($_GET['search'])) {
                                    //     $searchTerm = strtolower($_GET['search']);
                                    //     $files = array_filter($files, function($file) use ($searchTerm) {
                                    //         return strpos(strtolower($file['name']), $searchTerm) !== false ||
                                    //                strpos(strtolower($file['type']), $searchTerm) !== false;
                                    //     });
                                    // }

                                    // Сначала отсортируем файлы по их полным путям
                                    usort($files, function($a, $b) {
                                        $pathA = dirname($a['path']);
                                        $pathB = dirname($b['path']);

                                        if ($pathA === $pathB) {
                                            // В одной директории - сначала папки, потом файлы
                                            if ($a['is_dir'] !== $b['is_dir']) {
                                                return $b['is_dir'] - $a['is_dir'];
                                            }
                                            return strcmp($a['name'], $b['name']);
                                        }

                                        // Разные директории - сортируем по пути
                                        return strcmp($pathA, $pathB);
                                    });

                                    foreach ($files as $file):
                                    $isScheduledForDeletion = false;
                                    $deletionTime = null;
                                    foreach ($deletedFiles as $delFile) {
                                        if ($delFile['path'] === $file['path']) {
                                            $isScheduledForDeletion = true;
                                            $deletionTime = $delFile['deletion_time'];
                                            break;
                                        }
                                    }
                                ?>
                                <tr class="<?= $file['is_dir'] ? 'directory-row' : 'file-row' ?>" data-parent="<?= $file['level'] > 0 ? dirname($file['path']) : '' ?>" data-path="<?= $file['path'] ?>">
                                    <td>
                                        <?= str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $file['level']) ?>
                                        <?php if ($file['is_dir']): ?>
                                            <span id="toggle-<?= str_replace(['/', '.'], '_', $file['path']) ?>" class="directory-toggle" onclick="toggleDirectory('<?= $file['path'] ?>')">
                                                <span class="toggle-icon">+</span>
                                            </span>
                                        <?php endif; ?>
                                        <i class="fas fa-<?= $file['is_dir'] ? 'folder' : 'file' ?>"></i>
                                        <?= htmlspecialchars($file['name']) ?>
                                        <?php if ($isScheduledForDeletion): ?>
                                            <span style="color:red;"> (Scheduled for deletion: <?= date('Y-m-d H:i:s', $deletionTime) ?>)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($file['type']) ?></td>
                                    <td><?= number_format($file['size'] / 1024, 2) ?> KB</td>
                                    <td>
                                        <div class="file-actions">
                                            <?php if (!$isScheduledForDeletion): ?>
                                                <div class="delete-actions">
                                                    <form method="post" style="display:inline;">
                                                        <input type="hidden" name="action" value="schedule_deletion">
                                                        <input type="hidden" name="path" value="<?= htmlspecialchars($file['path']) ?>">
                                                        <input type="hidden" name="delay" value="0">
                                                        <button type="submit" class="btn btn-danger" title="Удалить" style="width: 38px; height: 38px;"><i class="fas fa-trash"></i></button>
                                                    </form>
                                                    <button onclick="showDeleteDialog('<?= htmlspecialchars($file['path']) ?>')" class="btn btn-warning" title="Удалить позже" style="width: 38px; height: 38px;">
                                                        <i class="fas fa-clock"></i>
                                                    </button>
                                                </div>
                                            <?php else: ?>
                                                <form method="post" style="display:inline;">
                                                    <input type="hidden" name="action" value="cancel_deletion">
                                                    <input type="hidden" name="path" value="<?= htmlspecialchars($file['path']) ?>">
                                                    <button type="submit" class="btn btn-secondary" style="width: 38px; height: 38px;">Отмена</button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if (!$isScheduledForDeletion): ?>
                                            <button onclick="showRenameDialog('<?= htmlspecialchars($file['path']) ?>', '<?= htmlspecialchars($file['name']) ?>')" class="btn btn-primary" title="Переименовать" style="width: 38px; height: 38px;">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button onclick="viewFile('<?= htmlspecialchars($file['path']) ?>')" class="btn btn-primary" title="Просмотр/Редактирование" style="width: 38px; height: 38px;">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <a href="?action=download&path=<?= urlencode($file['path']) ?>" class="btn btn-success" title="Скачать" style="width: 38px; height: 38px;">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            <?php if ($file['is_dir']): ?>
                                            <button onclick="showCreateFileDialog('<?= htmlspecialchars($file['path']) ?>')" class="btn btn-info" title="Создать файл" style="width: 38px; height: 38px;">
                                                <i class="fas fa-plus"></i>
                                            </button>                                            <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Раздел управления файловой системой -->
                <div class="info-section">
                    <h2><i class="fas fa-tools"></i> Управление файловой системой</h2>
                    <div class="filesystem-controls">
                        <!-- Поиск файлов -->
                        <div class="control-group">
                            <h3><i class="fas fa-search"></i> Поиск файлов</h3>
                            <div class="filesystem-form">
                                <div class="input-group">
                                    <input type="text" id="fileSearch" placeholder="Поиск по имени файла или расширению" oninput="filterFiles(this.value)">
                                </div>
                            </div>
                        </div>
                        <!-- Очередь на удаление -->
                        <div class="control-group">
                            <h3><i class="fas fa-trash"></i> Управление удалением</h3>
                            <div class="deletion-queue-status">
                                <p>Очередь на удаление: 
                                    <span class="status-badge <?= $maintenance['enabled'] ? 'status-enabled' : 'status-disabled' ?>">
                                        <?= $maintenance['enabled'] ? 'Включена' : 'Отключена' ?>
                                    </span>
                                </p>
                            </div>
                            <form method="post" class="filesystem-form">
                                <input type="hidden" name="toggle_maintenance" value="1">
                                <button type="submit" class="btn <?= $maintenance['enabled'] ? 'btn-success' : 'btn-warning' ?>">
                                    <?= $maintenance['enabled'] ? 'Отключить очередь на удаление' : 'Включить очередь на удаление' ?>
                                </button>
                            </form>
                        </div>

                        <!-- Создание новой папки -->
                        <div class="control-group">
                            <h3><i class="fas fa-folder-plus"></i> Создать папку</h3>
                            <form method="post" class="filesystem-form">
                                                               <input type="hidden" name="action" value="create_directory">
                                <div class="input-group"><input type="text" name="directory_name" placeholder="Имя папки" required>
                                    <button type="submit" class="btn btn-primary">Создать</button>
                                </div>
                            </form>
                        </div>

                        <!-- Загрузка файлов -->
                        <div class="control-group">
                            <h3><i class="fas fa-download"></i> Скачать все файлы</h3>
                            <div class="filesystem-form">
                                <form method="get" action="?action=download">
                                    <input type="hidden" name="action" value="download">
                                    <input type="hidden" name="path" value=".">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-file-archive"></i> Скачать все файлы (ZIP)
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div class="control-group">
                            <h3><i class="fas fa-upload"></i> Загрузить файлы</h3>
                            <form method="post" enctype="multipart/form-data" class="filesystem-form">
                                <input type="hidden" name="action" value="upload_files">
                                <input type="hidden" name="MAX_FILE_SIZE" value="104857600"><!-- 100MB в байтах -->
                                <div class="input-group">
                                    <input type="file" name="files[]" multiple>
                                    <button type="submit" class="btn btn-primary">Загрузить</button>
                                </div>
                                <small class="text-muted">Максимальный размер файла: 100MB</small>
                            </form>
                        </div>

                        <!-- Статистика файловой системы -->
                        <div class="control-group">
                            <h3><i class="fas fa-chart-pie"></i> Статистика</h3>

                            <!-- Общая статистика -->
                            <div class="stats-grid">
                                <div class="stat-item">
                                    <span class="stat-label">Всего файлов:</span>
                                    <span class="stat-value"><?= count($files) ?></span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-label">Общий размер:</span>
                                    <span class="stat-value"><?= formatSize(array_sum(array_column($files, 'size'))) ?></span>
                                </div>
                            </div>

                            <!-- Визуализация использования диска -->
                            <?php
                            $totalSpace = disk_total_space(".");
                            $freeSpace = disk_free_space(".");
                            $usedSpace = $totalSpace - $freeSpace;
                            $usagePercentage = round(($usedSpace / $totalSpace) * 100, 2);

                            // Группировка файлов по типу
                            $fileTypes = [];
                            foreach ($files as $file) {
                                $type = $file['type'] === 'directory' ? 'directory' : strtolower($file['type']);
                                if (!isset($fileTypes[$type])) {
                                    $fileTypes[$type] = [
                                        'count' => 0,
                                        'size' => 0
                                    ];
                                }
                                $fileTypes[$type]['count']++;
                                $fileTypes[$type]['size'] += $file['size'];
                            }
                            arsort($fileTypes);
                            ?>

                            <div class="disk-usage-section">
                                <h4>Использование диска</h4>
                                <div class="disk-usage-bar">
                                    <div class="progress-bar" style="width: <?= $usagePercentage ?>%">
                                        <span class="progress-text"><?= $usagePercentage ?>% использовано</span>
                                    </div>
                                </div>
                                <div class="disk-usage-details">
                                    <div>Всего: <?= formatSize($totalSpace) ?></div>
                                    <div>Использовано: <?= formatSize($usedSpace) ?></div>
                                    <div>Свободно: <?= formatSize($freeSpace) ?></div>
                                </div>
                            </div>

                            <div class="file-types-section">
                                <h4>
                                    Распределение по типам файлов
                                    <span onclick="toggleFileTypes()" style="cursor: pointer;">
                                        <i id="fileTypesIcon" class="fas fa-chevron-right"></i>
                                    </span>
                                </h4>
                                <div class="file-types-grid" id="fileTypesGrid">
                                    <?php 
                            $totalFiles = array_sum(array_column($fileTypes, 'count'));
                            foreach ($fileTypes as $type => $info): 
                                $percentage = round(($info['count'] / $totalFiles) * 100, 1);
                            ?>
                                    <div class="file-type-item">
                                        <div class="file-type-header">
                                            <i class="fas fa-<?= $type === 'directory' ? 'folder' : 'file' ?>"></i>
                                            <span><?= $type === '' ? 'Без расширения' : $type ?></span>
                                        </div>
                                        <div class="file-type-bar">
                                            <div class="progress-bar" style="width: <?= $percentage ?>%">
                                                <span class="type-percentage"><?= $percentage ?>%</span>
                                            </div>
                                        </div>
                                        <div class="file-type-details">
                                            <span><?= $info['count'] ?> файлов</span>
                                            <span><?= formatSize($info['size']) ?></span>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<div class="dialog-overlay" id="dialogOverlay"></div>
    <div id="deleteDialog">
        <h3>Удалить позже</h3>
        <p>Через сколько минут удалить файл?</p>
        <input type="number" id="deleteMinutes" min="1" value="5">
        <div>
            <button onclick="submitDelete()" class="btn btn-danger">Подтвердить</button>
            <button onclick="closeDeleteDialog()" class="btn">Отмена</button>
        </div>
    </div>

    <div id="renameDialog">
        <h3>Переименовать файл</h3>
        <p>Введите новое имя файла:</p>
        <input type="text" id="renameFilename">
        <div>
            <button onclick="submitRename()" class="btn btn-danger">Подтвердить</button>
            <button onclick="closeRenameDialog()" class="btn">Отмена</button>
        </div>
    </div>

    <div id="createFileDialog">
        <h3>Создать новый файл</h3>
        <p>Введите имя нового файла:</p>
        <input type="text" id="newFileName">
        <div>
            <button onclick="submitCreateFile()" class="btn btn-primary">Создать</button>
            <button onclick="closeCreateFileDialog()" class="btn">Отмена</button>
        </div>
    </div>

    <div id="fileViewerDialog" class="modal">
        <div class="modal-content">
            <h3 style="margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #eee;">
                <i class="fas fa-file"></i>
                Просмотр файла: <span id="viewerFileName" style="font-weight: bold; color: #2196F3;"></span>
            </h3>
            <div id="fileContent">
                <div id="imageViewer" style="display:none; text-align:center;">
                    <img id="viewerImage" style="max-width:100%; max-height:70vh;">
                </div>
                <div id="textEditor" style="display:none;">
                    <div id="codeEditor"></div>
                    <div class="button-group" style="margin-top: 10px;">
                        <button onclick="saveFileContent()" class="btn btn-primary"><i class="fas fa-save"></i> Сохранить</button>
                        <button onclick="closeFileViewer()" class="btn btn-secondary"><i class="fas fa-times"></i> Закрыть</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <style>
    .modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        display: none;
        justify-content: center;
        align-items: center;
        z-index: 3000;
    }
    .modal-content {
        width: 80%;
        max-width: 800px;
        max-height: 90vh;
        background: white;
        padding: 20px;
        border-radius: 5px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        position: relative;
        overflow-y: auto;
    }
    .button-group {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
    }
    #deleteDialog, #renameDialog, #createFileDialog {
        z-index: 2000;
    }
    .dialog-overlay {
        z-index: 1900;
    }
    #fileViewerDialog {
        z-index: 2500 !important;
    }
    </style>
    </div>
    <script>
    let editor;
    let currentDirectory = '';

    function getFileMode(fileName) {
        const extension = fileName.split('.').pop().toLowerCase();
        switch(extension) {
            case 'php': return 'php';
            case 'js': return 'javascript';
            case 'html': return 'htmlmixed';
            case 'css': return 'css';
            case 'xml': return 'xml';
            default: return 'text/plain';
        }
    }

    function showCreateFileDialog(directory) {
        currentDirectory = directory;
        document.getElementById('createFileDialog').style.display = 'block';
        document.getElementById('dialogOverlay').style.display = 'block';
        document.getElementById('newFileName').value = '';
    }

    function closeCreateFileDialog() {
        document.getElementById('createFileDialog').style.display = 'none';
        document.getElementById('dialogOverlay').style.display = 'none';
    }

    function submitCreateFile() {
        const fileName = document.getElementById('newFileName').value;
        if (!fileName) return;

        const form = document.createElement('form');
        form.method = 'post';

        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'create_file';

        const pathInput = document.createElement('input');
        pathInput.type = 'hidden';
        pathInput.name = 'file_path';
        pathInput.value = currentDirectory + '/' + fileName;

        form.appendChild(actionInput);
        form.appendChild(pathInput);
        document.body.appendChild(form);
        form.submit();
    }

    let currentFilePath = '';

    async function viewFile(path) {
        try {
            currentFilePath = path;
            const fileName = path.split('/').pop();
            document.getElementById('viewerFileName').textContent = fileName;
            document.getElementById('fileViewerDialog').style.display = 'flex';
            document.getElementById('dialogOverlay').style.display = 'block';

            // Reset content areas
            document.getElementById('imageViewer').style.display = 'none';
            document.getElementById('textEditor').style.display = 'none';
            if (editor) {
                editor.toTextArea();
                editor = null;
            }
            const codeEditor = document.getElementById('codeEditor');
            if (codeEditor) {
                codeEditor.innerHTML = '';
            }

            // Create form data
            const formData = new FormData();
            formData.append('action', 'read_file');
            formData.append('file_path', path);

            // Load file content
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                throw new Error('Network response was not ok');
            }

            const data = await response.json();

            // Handle the response data
            if (data.success) {
                if (data.type === 'image') {
                    // Show image viewer
                    document.getElementById('imageViewer').style.display = 'block';
                    document.getElementById('textEditor').style.display = 'none';
                    document.getElementById('viewerImage').src = 'data:image/' + data.extension + ';base64,' + data.content;
                } else {
                    // Show text editor with proper encoding
                    document.getElementById('imageViewer').style.display = 'none';
                    document.getElementById('textEditor').style.display = 'block';
                    try {
                            let decodedContent;
                            const decoder = new TextDecoder('utf-8');
                            try {
                                const rawContent = data.content ? atob(data.content) : '';
                                decodedContent = decoder.decode(new Uint8Array([...rawContent].map(c => c.charCodeAt(0))));
                            } catch (e) {
                                console.error('Decoding error:', e);
                                decodedContent = data.content ? decodeURIComponent(escape(atob(data.content))) : '';
                            }

                            // Clean up existing editor if present
                            if (editor) {
                                const wrapper = editor.getWrapperElement();
                                if (wrapper && wrapper.parentNode) {
                                    wrapper.parentNode.removeChild(wrapper);
                                }
                                editor = null;
                            }

                            const mode = getFileMode(fileName);
                            editor = CodeMirror(document.getElementById('codeEditor'), {
                                value: decodedContent,
                                mode: mode === 'php' ? {name: 'php', startOpen: true} : mode,
                                theme: 'monokai',
                                lineNumbers: true,
                                indentUnit: 4,
                                lineWrapping: true,
                                viewportMargin: Infinity,
                                tabSize: 4,
                                maxHighlightLength: 10000,
                                matchBrackets: true,
                                autoCloseBrackets: true,
                                autoCloseTags: true,
                            workTime: 100,
                            workDelay: 300
                        });
                        editor.refresh();
                    } catch (e) {
                        console.error('Error setting up CodeMirror:', e);
                        const textArea = document.createElement('textarea');
                        textArea.value = window.atob(data.content);
                        textArea.style.width = '100%';
                        textArea.style.height = '400px';
                        textArea.style.marginBottom = '10px';
                        document.getElementById('codeEditor').appendChild(textArea);
                    }
                }
            } else {
                alert('Ошибка: ' + (data.error || 'Не удалось загрузить файл'));
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Ошибка при загрузке файла: ' + error.message);
            closeFileViewer();
        }
    }

    function closeFileViewer() {
        document.getElementById('fileViewerDialog').style.display = 'none';
        document.getElementById('dialogOverlay').style.display = 'none';
        document.getElementById('imageViewer').style.display = 'none';
        document.getElementById('textEditor').style.display = 'none';

        // Clear editor properly
        if (editor) {
            const wrapper = editor.getWrapperElement();
            if (wrapper && wrapper.parentNode) {
                wrapper.parentNode.removeChild(wrapper);
            }
            editor = null;
        }

        const codeEditor = document.getElementById('codeEditor');
        if (codeEditor) {
            codeEditor.innerHTML = '';
        }
    }

    function saveFileContent() {
        const content = editor ? editor.getValue() : document.getElementById('editorContent').value;

        fetch('?', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=save_file&file_path=' + encodeURIComponent(currentFilePath) + 
                  '&content=' + encodeURIComponent(content)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Файл сохранен');
                closeFileViewer();
            } else {
                alert('Ошибка при сохранении файла');
            }
        });
    }
    </script>
</body>
</html>