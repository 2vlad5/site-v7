<?php
session_start();
require_once  'functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$userRole = $_SESSION['role'];
$userId = $_SESSION['user_id'];

// Проверяем права доступа - только создатель
if ($userRole !== 'creator') {
    header("Location: dashboard.php");
    exit();
}

$targetUserId = $_GET['id'] ?? '';
if (empty($targetUserId)) {
    header("Location: access_keys.php");
    exit();
}

// Загружаем текущих пользователей
$users = loadJson('data/users.json');
$updated = false;
$targetUser = null;

// Отзываем ключ доступа
foreach ($users as &$user) {
    if ($user['id'] === $targetUserId) {
        $targetUser = $user;
        unset($user['access_key_expiry']);
        $updated = true;
        break;
    }
}

// Сохраняем обновленных пользователей
if ($updated) {
    saveJson('data/users.json', $users);
    
    // Логируем отзыв ключа
    if ($targetUser) {
        logActivity($userId, 'revoke_key', "Отозвал ключ доступа у пользователя: {$targetUser['first_name']} {$targetUser['last_name']}", $targetUserId);
    }
}

// Перенаправляем обратно на страницу ключей
header("Location: access_keys.php");
exit();
 