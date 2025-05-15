<?php
session_start();
require_once  'functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$userId = $_SESSION['user_id'];
$notificationId = $_GET['id'] ?? '';

if (empty($notificationId)) {
    header("Location: notifications.php");
    exit();
}

// Загружаем уведомления
$notifications = loadJson('data/notifications.json');
$updated = false;

// Находим и отмечаем уведомление как прочитанное
foreach ($notifications as &$notification) {
    if ($notification['id'] === $notificationId && $notification['user_id'] === $userId) {
        $notification['is_read'] = true;
        $notification['read_at'] = time();
        $updated = true;
        break;
    }
}

// Сохраняем обновленные уведомления
if ($updated) {
    saveJson('data/notifications.json', $notifications);
}

// Получаем URL для возврата
$returnUrl = isset($_GET['return']) ? $_GET['return'] : 'notifications.php';

// Редирект обратно на страницу
header("Location: $returnUrl");
exit();
 