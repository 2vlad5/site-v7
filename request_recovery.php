<?php
session_start();
require_once  'functions.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit();
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];
$username = $_SESSION['username'];

// Получаем данные из формы
$journalId = $_POST['journal_id'] ?? '';
$date = $_POST['date'] ?? '';
$comment = $_POST['comment'] ?? '';

if (empty($journalId) || empty($date)) {
    header("Location: dashboard.php");
    exit();
}

// Проверяем, что журнал принадлежит пользователю
$journals = loadJson('data/journals.json');
$journalData = null;

foreach ($journals as $journal) {
    if ($journal['id'] === $journalId && $journal['user_id'] === $userId) {
        $journalData = $journal;
        break;
    }
}

if (!$journalData) {
    header("Location: dashboard.php?error=access_denied");
    exit();
}

// Создаем запрос на восстановление дня
if (createRecoveryRequest($userId, $journalId, $date, $comment)) {
    // Извлекаем данные о дате для лога
    list($year, $month, $day) = explode('-', $date);
    $monthName = getRussianMonth((int)$month);
    
    // Логируем запрос на восстановление
    logActivity($userId, 'request_recovery', "Запросил восстановление дня: {$day} {$monthName} {$year} в журнале {$journalData['title']}", $journalId);
    
    // Возвращаемся на страницу журнала
    header("Location: journal.php?id={$journalId}&month={$month}&day={$day}&success=recovery_requested");
} else {
    // Извлекаем данные о дате для редиректа
    list($year, $month, $day) = explode('-', $date);
    
    // Возвращаемся на страницу журнала с ошибкой
    header("Location: journal.php?id={$journalId}&month={$month}&day={$day}&error=recovery_exists");
}

exit();
 