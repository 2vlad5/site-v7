<?php
session_start();
require_once  'functions.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit();
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];

$journalId = $_POST['journal_id'] ?? '';
$date = $_POST['date'] ?? '';
$startTime = $_POST['start_time'] ?? '';
$endTime = $_POST['end_time'] ?? '';
$lunchMinutes = (int)($_POST['lunch_minutes'] ?? 0);
$notes = $_POST['notes'] ?? '';
$isDayOff = isset($_POST['is_day_off']) && $_POST['is_day_off'] == 1;
$inaccurateFields = isset($_POST['inaccurate_fields']) ? $_POST['inaccurate_fields'] : [];


// Проверяем права доступа к журналу
if (!canAccessJournal($journalId, $userId, $userRole)) {
    header("Location: dashboard.php?error=access_denied");
    exit();
}

// Получаем данные журнала для лога
$journals = loadJson('data/journals.json');
$journalData = null;
foreach ($journals as $journal) {
    if ($journal['id'] == $journalId) {
        $journalData = $journal;
        break;
    }
}

// Загружаем текущие записи
$entriesFile = "data/entries/{$journalId}.json";
$entries = loadJson($entriesFile);

// Проверяем, существовала ли запись ранее
$isNewEntry = !isset($entries[$date]);

// Обновляем или добавляем запись
if ($isDayOff) {
    // Если день отмечен как выходной
    $entries[$date] = [
        'is_day_off' => true,
        'notes' => $notes,
        'inaccurate_fields' => $inaccurateFields
    ];
} else {
    // Обычный рабочий день
    $entries[$date] = [
        'start_time' => $startTime,
        'end_time' => $endTime,
        'lunch_minutes' => $lunchMinutes,
        'notes' => $notes,
        'is_day_off' => false,
        'inaccurate_fields' => $inaccurateFields
    ];
}

// Сохраняем обновленные записи
saveJson($entriesFile, $entries);

// Логируем изменение записи
if ($journalData) {
    list($year, $month, $day) = explode('-', $date);
    $monthName = getRussianMonth((int)$month);

    $actionDesc = $isDayOff ? "Отметил выходной день" : ($isNewEntry ? "Создал запись" : "Изменил запись");

    logActivity($userId, 'edit_entry', "{$actionDesc} в журнале: {$journalData['title']} ({$day} {$monthName} {$year})", $journalId);
}

// Извлекаем месяц из даты для возврата на нужную вкладку
list($year, $month, $day) = explode('-', $date);
$month = (int)$month;
$day = (int)$day;

// Перенаправляем обратно на страницу журнала
header("Location: journal.php?id={$journalId}&month={$month}&day={$day}");
exit();
?>