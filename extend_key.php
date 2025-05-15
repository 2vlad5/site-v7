<?php
session_start();
require_once  'functions.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit();
}

$userRole = $_SESSION['role'];
$userId = $_SESSION['user_id'];

// Проверяем права доступа - только создатель
if ($userRole !== 'creator') {
    header("Location: dashboard.php");
    exit();
}

$targetUserId = $_POST['user_id'] ?? '';
$days = isset($_POST['days']) ? (int)$_POST['days'] : 30;

if (empty($targetUserId) || $days <= 0) {
    header("Location: access_keys.php");
    exit();
}

// Загружаем текущих пользователей
$users = loadJson('data/users.json');
$updated = false;
$targetUser = null;

// Продлеваем ключ доступа
foreach ($users as &$user) {
    if ($user['id'] === $targetUserId) {
        $targetUser = $user;
        $currentExpiry = $user['access_key_expiry'] ?? time();
        // Если ключ истек, считаем от текущего времени
        if ($currentExpiry < time()) {
            $currentExpiry = time();
        }
        
        $user['access_key_expiry'] = $currentExpiry + ($days * 86400);
        $updated = true;
        break;
    }
}

// Сохраняем обновленных пользователей
if ($updated) {
    saveJson('data/users.json', $users);
    
    // Логируем продление ключа
    if ($targetUser) {
        logActivity($userId, 'extend_key', "Продлил ключ доступа для пользователя: {$targetUser['first_name']} {$targetUser['last_name']} на {$days} дней", $targetUserId);
    }
}

// Перенаправляем обратно на страницу ключей
header("Location: access_keys.php");
exit();
 