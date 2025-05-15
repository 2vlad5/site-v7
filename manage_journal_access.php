<?php
session_start();
require_once  'functions.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit();
}

$userRole = $_SESSION['role'];
$userId = $_SESSION['user_id'];

// Проверяем права доступа - только создатель может управлять доступом
if ($userRole !== 'creator') {
    header("Location: dashboard.php");
    exit();
}

$journalId = $_POST['journal_id'] ?? '';
$adminAccess = $_POST['admin_access'] ?? [];

if (empty($journalId)) {
    header("Location: dashboard.php");
    exit();
}

// Загружаем текущие журналы
$journals = loadJson('data/journals.json');
$updated = false;
$journalData = null;

// Обновляем список администраторов с доступом
foreach ($journals as &$journal) {
    if ($journal['id'] === $journalId) {
        $journalData = $journal;
        $journal['admin_access'] = $adminAccess;
        $updated = true;
        break;
    }
}

// Сохраняем обновленные журналы
if ($updated) {
    saveJson('data/journals.json', $journals);
    
    // Логируем изменение прав доступа
    if ($journalData) {
        logActivity($userId, 'manage_access', "Изменил права доступа к журналу: {$journalData['title']}", $journalId);
    }
}

// Перенаправляем обратно на страницу журнала
header("Location: journal.php?id={$journalId}");
exit();
 