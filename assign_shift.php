<?php
session_start();
require_once   'functions.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit();
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];

// Проверяем права доступа - только создатель или администратор
if ($userRole !== 'creator' && $userRole !== 'admin') {
    header("Location: dashboard.php?error=access_denied");
    exit();
}

// Получаем данные из формы
$journalId = $_POST['journal_id'] ?? '';
$date = $_POST['date'] ?? '';
$startTime = $_POST['start_time'] ?? '';
$endTime = $_POST['end_time'] ?? '';
$lunchMinutes = (int)($_POST['lunch_minutes'] ?? 60);
$notes = $_POST['notes'] ?? '';

if (empty($journalId) || empty($date) || empty($startTime) || empty($endTime)) {
    header("Location: journal.php?id={$journalId}&error=missing_data");
    exit();
}

// Проверяем, что дата не в прошлом
$today = date('Y-m-d');
if ($date < $today) {
    header("Location: journal.php?id={$journalId}&error=past_date");
    exit();
}

// Проверяем доступ к журналу
if (!canAccessJournal($journalId, $userId, $userRole)) {
    header("Location: dashboard.php?error=access_denied");
    exit();
}

// Получаем данные журнала для лога
$journals = loadJson('data/journals.json');
$journalData = null;
$journalOwner = null;

foreach ($journals as $journal) {
    if ($journal['id'] == $journalId) {
        $journalData = $journal;
        $journalOwner = getUserById($journal['user_id']);
        break;
    }
}

if (!$journalData) {
    header("Location: dashboard.php?error=journal_not_found");
    exit();
}

// Загружаем текущие записи
$entriesFile = "data/entries/{$journalId}.json";
$entries = loadJson($entriesFile);

// Добавляем запись на указанную дату
$entries[$date] = [
    'start_time' => $startTime,
    'end_time' => $endTime,
    'lunch_minutes' => $lunchMinutes,
    'notes' => $notes,
    'is_day_off' => false,
    'assigned_by' => $userId,
    'assigned_at' => time(),
    'is_assigned' => true
];

// Сохраняем обновленные записи
saveJson($entriesFile, $entries);

// Добавляем уведомление для пользователя
$notifications = loadJson('data/notifications.json');
$notificationId = generateId();

// Форматируем дату для уведомления
list($year, $month, $day) = explode('-', $date);
$formattedDate = $day . ' ' . getRussianMonthGenitive((int)$month) . ' ' . $year;

// Формируем текст уведомления
$notificationText = "Вам назначена смена на {$formattedDate}: с {$startTime} до {$endTime}";
if (!empty($notes)) {
    $notificationText .= ". Примечание: {$notes}";
}

// Создаем уведомление
$newNotification = [
    'id' => $notificationId,
    'user_id' => $journalData['user_id'],
    'title' => 'Новая смена',
    'message' => $notificationText,
    'created_at' => time(),
    'created_by' => $userId,
    'is_read' => false,
    'journal_id' => $journalId,
    'date' => $date,
    'type' => 'shift_assignment'
];

$notifications[] = $newNotification;
saveJson('data/notifications.json', $notifications);

// Логируем назначение смены
$assignerName = "{$_SESSION['last_name']} {$_SESSION['first_name']}";
$ownerName = $journalOwner ? "{$journalOwner['last_name']} {$journalOwner['first_name']}" : "Неизвестный пользователь";

logActivity($userId, 'assign_shift', "Назначил смену на {$formattedDate} для пользователя {$ownerName} в журнале {$journalData['title']}", $journalId);

// Возвращаемся на страницу журнала
header("Location: journal.php?id={$journalId}&month={$month}&success=shift_assigned");
exit();
 