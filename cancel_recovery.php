<?php
session_start();
require_once  'functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];

// Проверяем права доступа - только создатель или администратор может отменять запросы
if ($userRole !== 'creator' && $userRole !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

// Получаем ID запроса
$requestId = $_GET['id'] ?? '';
if (empty($requestId)) {
    header("Location: dashboard.php");
    exit();
}

// Загружаем запросы на восстановление
$recoveryRequests = loadJson('data/recovery_requests.json');
$requestData = null;

foreach ($recoveryRequests as $request) {
    if ($request['id'] === $requestId) {
        $requestData = $request;
        break;
    }
}

if (!$requestData || $requestData['status'] !== 'pending') {
    header("Location: dashboard.php?error=request_not_found");
    exit();
}

// Проверяем, имеет ли пользователь доступ к журналу
if (!canAccessJournal($requestData['journal_id'], $userId, $userRole)) {
    header("Location: dashboard.php?error=access_denied");
    exit();
}

// Обрабатываем запрос на восстановление (отклоняем)
$rejectData = [
    'reason' => 'Запрос отклонен администратором'
];

if (processRecoveryRequest($requestId, 'rejected', $userId, $rejectData)) {
    // Извлекаем данные для возврата на нужную страницу
    list($year, $month, $day) = explode('-', $requestData['date']);
    
    // Загружаем данные журнала для лога
    $journals = loadJson('data/journals.json');
    $journalData = null;
    foreach ($journals as $journal) {
        if ($journal['id'] === $requestData['journal_id']) {
            $journalData = $journal;
            break;
        }
    }
    
    // Логируем отказ в восстановлении
    if ($journalData) {
        $monthName = getRussianMonth((int)$month);
        $userName = getUserById($requestData['user_id']);
        $userFullName = '';
        if ($userName) {
            $userFullName = trim($userName['first_name'] . ' ' . $userName['last_name']);
        }
        
        logActivity($userId, 'reject_recovery', "Отклонил запрос на восстановление дня {$day} {$monthName} {$year} для пользователя {$userFullName} в журнале {$journalData['title']}", $requestId);
    }
    
    // Возвращаемся на страницу журнала
    header("Location: journal.php?id={$requestData['journal_id']}&month={$month}&day={$day}&success=recovery_rejected");
} else {
    // В случае ошибки возвращаемся на панель управления
    header("Location: dashboard.php?error=recovery_action_failed");
}

exit();
 