<?php
session_start();
require_once  'functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$userRole = $_SESSION['role'];
$userId = $_SESSION['user_id'];

// Проверяем права доступа - только создатель может удалять пользователей
if ($userRole !== 'creator') {
    header("Location: dashboard.php");
    exit();
}

$deleteUserId = $_GET['id'] ?? '';
if (empty($deleteUserId)) {
    header("Location: users.php");
    exit();
}

// Загружаем текущих пользователей
$users = loadJson('data/users.json');
$updatedUsers = [];
$deletedUser = null;

// Удаляем пользователя
foreach ($users as $user) {
    if ($user['id'] !== $deleteUserId) {
        $updatedUsers[] = $user;
    } else {
        $deletedUser = $user;
    }
}

// Сохраняем обновленных пользователей
saveJson('data/users.json', $updatedUsers);

// Логируем удаление пользователя
if ($deletedUser) {
    logActivity($userId, 'delete_user', "Удалил пользователя: {$deletedUser['first_name']} {$deletedUser['last_name']}", $deleteUserId);
}

// Перенаправляем обратно на страницу пользователей
header("Location: users.php");
exit();
 