
<?php
session_start();
require_once 'functions.php';

$user = checkAccess();
$userId = $user['id'];

if (!hasTabAccess($userId, 'passwords')) {
    header("Location: dashboard.php");
    exit();
}

$safeId = $_GET['id'] ?? '';

if (empty($safeId) || $safeId === 'default') {
    header("Location: passwords.php?error=invalid_safe");
    exit();
}

// Загружаем сейфы и пароли
$safes = loadJson("data/safes/{$userId}.json");
$passwords = loadJson("data/passwords/{$userId}.json");

// Перемещаем пароли в основной сейф
foreach ($passwords as &$password) {
    if ($password['safe_id'] === $safeId) {
        $password['safe_id'] = 'default';
    }
}

// Удаляем сейф
$safes = array_filter($safes, function($safe) use ($safeId) {
    return $safe['id'] !== $safeId;
});

// Сохраняем изменения
saveJson("data/safes/{$userId}.json", array_values($safes));
saveJson("data/passwords/{$userId}.json", $passwords);

header("Location: passwords.php?success=safe_deleted");
exit();
