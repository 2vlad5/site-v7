
<?php
session_start();
require_once 'functions.php';

$user = checkAccess();
$userId = $user['id'];
$userRole = $user['role'];

// Проверка прав доступа
if (!($userRole === 'creator' || hasTabAccess($userId, 'updates_delete'))) {
    header("Location: updates.php");
    exit();
}

if (isset($_GET['id'])) {
    $updates = loadJson('data/updates.json');
    $updatedList = array_filter($updates['updates'], function($update) {
        return $update['id'] !== $_GET['id'];
    });
    
    $updates['updates'] = array_values($updatedList);
    saveJson('data/updates.json', $updates);
    
    logActivity($userId, 'delete_update', 'Удалил запись об обновлении');
}

header("Location: updates.php");
exit();
