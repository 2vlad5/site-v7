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

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';
$role = $_POST['role'] ?? 'user';
$firstName = $_POST['first_name'] ?? '';
$middleName = $_POST['middle_name'] ?? '';
$lastName = $_POST['last_name'] ?? '';
$email = $_POST['email'] ?? '';
$accessKeyDays = isset($_POST['access_key_days']) ? (int)$_POST['access_key_days'] : 30;

// Администраторы могут создавать только пользователей
if ($userRole === 'admin' && $role !== 'user') {
    $role = 'user';
}

// Создаем нового пользователя
$newUser = [
    'id' => generateId(),
    'username' => $username,
    'password' => $password,
    'role' => $role,
    'first_name' => $firstName,
    'middle_name' => $middleName,
    'last_name' => $lastName,
    'email' => $email
];

// Для создателя добавляем ключ доступа
if ($userRole === 'creator' && $accessKeyDays > 0) {
    $newUser['access_key_expiry'] = time() + ($accessKeyDays * 86400);
}

// Загружаем текущих пользователей и добавляем нового
$users = loadJson('data/users.json');
$users[] = $newUser;
saveJson('data/users.json', $users);

// Логируем создание пользователя
$fullName = $firstName . ' ' . ($middleName ? $middleName . ' ' : '') . $lastName;
logActivity($userId, 'create_user', "Создал нового пользователя: $fullName", $newUser['id']);

// Перенаправляем обратно на страницу пользователей
header("Location: users.php");
exit();
 