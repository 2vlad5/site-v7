<?php
session_start();
require_once  'functions.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit();
}

$userRole = $_SESSION['role'];
$userId = $_SESSION['user_id'];

// Проверяем права доступа - только создатель может редактировать журналы
if ($userRole !== 'creator') {
    header("Location: dashboard.php");
    exit();
}

$journalId = $_POST['journal_id'] ?? '';
$title = $_POST['title'] ?? '';
$year = isset($_POST['year']) ? (int)$_POST['year'] : date('Y');

if (empty($journalId) || empty($title)) {
    header("Location: dashboard.php");
    exit();
}

// Загружаем текущие журналы
$journals = loadJson('data/journals.json');
$updated = false;
$oldJournalData = null;

// Обновляем журнал
foreach ($journals as &$journal) {
    if ($journal['id'] === $journalId) {
        $oldJournalData = $journal;
        
        $journal['title'] = $title;
        $journal['year'] = $year;
        
        $updated = true;
        break;
    }
}

// Сохраняем обновленные журналы
if ($updated) {
    saveJson('data/journals.json', $journals);
    
    // Логируем редактирование журнала
    logActivity($userId, 'edit_journal', "Отредактировал журнал: {$oldJournalData['title']} → {$title}", $journalId);
}

// Перенаправляем обратно на панель управления
header("Location: dashboard.php?tab=my_journals");
exit();
 