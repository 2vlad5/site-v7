
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
    $safeName = $_POST['safe_name'] ?? '';
    $safeColor = $_POST['safe_color'] ?? '#2196F3';

    if (empty($safeName)) {
        header("Location: passwords.php?error=missing_fields");
        exit();
    }

    // Загружаем существующие сейфы
    $safes = loadJson("data/safes/{$userId}.json");
    if (!$safes) {
        $safes = [];
    }

    // Добавляем новый сейф
    $safes[] = [
        'id' => generateId(),
        'name' => $safeName,
        'color' => $safeColor
    ];

    // Сохраняем обновленный список
    saveJson("data/safes/{$userId}.json", $safes);

    header("Location: passwords.php?success=safe_added");
    exit();
}

header("Location: passwords.php");
exit();
