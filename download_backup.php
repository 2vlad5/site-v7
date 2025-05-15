<?php
session_start();
require_once  'functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user = checkAccess();
$userId = $user['id'];
$userRole = $user['role'];

// Проверяем права доступа - только доверенный имеет доступ
if (!hasTabAccess($userId, 'my_journals')) {
    header("Location: dashboard.php");
    exit();
}

// Получаем имя файла из GET-параметра
$fileName = isset($_GET['file']) ? $_GET['file'] : '';

if (empty($fileName)) {
    header("Location: backup_journals.php?error=no_file");
    exit();
}

// Проверяем безопасность пути файла
$fileName = basename($fileName);
$filePath = 'data/backups/' . $fileName;

if (!file_exists($filePath)) {
    header("Location: backup_journals.php?error=file_not_found");
    exit();
}

// Логируем скачивание резервной копии
logActivity($userId, 'download_backup', "Скачал резервную копию: {$fileName}");

// Отправляем файл для скачивания
header('Content-Description: File Transfer');
header('Content-Type: application/json');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;
 