<?php
session_start();
require_once  'functions.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit();
}

$userRole = $_SESSION['role'];
$userId = $_SESSION['user_id'];

// Проверяем права доступа - только создатель или администратор
if ($userRole !== 'creator' && $userRole !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

$editUserId = $_POST['user_id'] ?? '';
$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';
$role = $_POST['role'] ?? 'user';
$firstName = $_POST['first_name'] ?? '';
$middleName = $_POST['middle_name'] ?? '';
$lastName = $_POST['last_name'] ?? '';
$email = $_POST['email'] ?? '';
$accessKeyDays = isset($_POST['access_key_days']) ? (int)$_POST['access_key_days'] : 0;

// Администраторы могут редактировать только пользователей
if ($userRole === 'admin' && $role !== 'user') {
    $role = 'user';
}

// Загружаем текущих пользователей
$users = loadJson('data/users.json');
$updated = false;
$oldUserData = null;

// Обновляем пользователя
foreach ($users as &$user) {
    if ($user['id'] === $editUserId) {
        // Проверяем, можно ли редактировать этого пользователя
        if ($userRole === 'admin' && $user['role'] !== 'user') {
            header("Location: users.php");
            exit();
        }
        
        // Сохраняем старые данные
        $oldUserData = $user;
        
        $user['username'] = $username;
        $user['first_name'] = $firstName;
        $user['middle_name'] = $middleName;
        $user['last_name'] = $lastName;
        $user['email'] = $email;
        
        // Обновляем пароль только если он был указан
        if (!empty($password)) {
            $user['password'] = $password;
        }
        
        // Обновляем роль только если пользователь - создатель
        if ($userRole === 'creator') {
            $user['role'] = $role;
            
            // Продлеваем ключ доступа
            if ($accessKeyDays > 0) {
                $currentExpiry = $user['access_key_expiry'] ?? time();
                // Если ключ истек, считаем от текущего времени
                if ($currentExpiry < time()) {
                    $currentExpiry = time();
                }
                $user['access_key_expiry'] = $currentExpiry + ($accessKeyDays * 86400);
            }
        }
        
        $updated = true;
        break;
    }
}

// Сохраняем обновленных пользователей
if ($updated) {
    saveJson('data/users.json', $users);
    
    // Логируем редактирование пользователя
    $fullName = $firstName . ' ' . ($middleName ? $middleName . ' ' : '') . $lastName;
    logActivity($userId, 'edit_user', "Отредактировал пользователя: $fullName", $editUserId);
}

// Перенаправляем обратно на страницу пользователей
header("Location: users.php");
exit();
 