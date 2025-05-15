<?php
session_start();
require_once  'functions.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit();
}

$userRole = $_SESSION['role'];
$userId = $_SESSION['user_id'];

// Проверяем права доступа - только создатель
if ($userRole !== 'creator') {
    header("Location: dashboard.php");
    exit();
}

$title = $_POST['title'] ?? '';
$year = isset($_POST['year']) ? (int)$_POST['year'] : date('Y');
$journalUserId = $_POST['user_id'] ?? '';

if (empty($title) || empty($journalUserId)) {
    header("Location: dashboard.php");
    exit();
}

// Создаем новый журнал
$newJournal = [
    'id' => generateId(),
    'title' => $title,
    'year' => $year,
    'user_id' => $journalUserId,
    'created_at' => time(),
    'admin_access' => []
];

// Загружаем текущие журналы и добавляем новый
$journals = loadJson('data/journals.json');
$journals[] = $newJournal;
saveJson('data/journals.json', $journals);

// Создаем пустой файл с записями для журнала
$entriesFile = "data/entries/{$newJournal['id']}.json";
saveJson($entriesFile, []);

// Логируем создание журнала
$journalOwner = getUserById($journalUserId);
$ownerName = $journalOwner ? ($journalOwner['first_name'] . ' ' . $journalOwner['last_name']) : 'Неизвестный пользователь';
logActivity($userId, 'create_journal', "Создал журнал: {$title} для пользователя {$ownerName}", $newJournal['id']);

// Перенаправляем обратно на панель управления
header("Location: dashboard.php?tab=all_journals");
exit();
 