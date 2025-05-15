
<?php
session_start();
require_once 'functions.php';

$user = checkAccess();
$userId = $user['id'];

if (!hasTabAccess($userId, 'passwords')) {
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $serviceName = $_POST['service_name'] ?? '';
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $notes = $_POST['notes'] ?? '';

    if (empty($serviceName) || empty($username) || empty($password)) {
        header("Location: passwords.php?error=missing_fields");
        exit();
    }

    // Создаем директорию для паролей, если она не существует
    if (!file_exists('data/passwords')) {
        mkdir('data/passwords', 0777, true);
    }

    // Загружаем существующие пароли
    $passwordsFile = "data/passwords/{$userId}.json";
    $passwords = loadJson($passwordsFile);

    // Добавляем новый пароль
    $passwords[] = [
        'id' => generateId(),
        'service_name' => $serviceName,
        'username' => $username,
        'password' => $password,
        'notes' => $notes,
        'created_at' => time()
    ];

    // Сохраняем обновленный список
    saveJson($passwordsFile, $passwords);

    header("Location: passwords.php?success=password_saved");
    exit();
}

header("Location: passwords.php");
exit();
