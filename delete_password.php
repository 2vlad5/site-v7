
<?php
session_start();
require_once 'functions.php';

$user = checkAccess();
$userId = $user['id'];

if (!hasTabAccess($userId, 'passwords')) {
    header("Location: dashboard.php");
    exit();
}

$passwordId = $_GET['id'] ?? '';
if (empty($passwordId)) {
    header("Location: passwords.php?error=invalid_id");
    exit();
}

$passwordsFile = "data/passwords/{$userId}.json";
$passwords = loadJson($passwordsFile);

// Удаляем пароль
$passwords = array_filter($passwords, function($password) use ($passwordId) {
    return $password['id'] !== $passwordId;
});

// Сохраняем обновленный список
saveJson($passwordsFile, array_values($passwords));

header("Location: passwords.php?success=password_deleted");
exit();
