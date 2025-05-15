<?php
session_start();
require_once  'functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];

// Проверяем права доступа - только администратор может запрашивать отчет
if ($userRole !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

// Получаем данные запроса (могут быть как от POST, так и от GET)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $journalId = $_POST['journal_id'] ?? '';
    $month = isset($_POST['month']) ? (int)$_POST['month'] : null;
    $format = $_POST['format'] ?? 'txt';
    // Автоматически получаем год из данных журнала
    $journals = loadJson('data/journals.json');
    $year = null;
    foreach ($journals as $journal) {
        if ($journal['id'] === $journalId) {
            $year = $journal['year'];
            break;
        }
    }
} else {
    $journalId = $_GET['journal_id'] ?? '';
    $month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
    $format = $_GET['format'] ?? 'txt';
    // Автоматически получаем год из данных журнала
    $journals = loadJson('data/journals.json');
    $year = null;
    foreach ($journals as $journal) {
        if ($journal['id'] === $journalId) {
            $year = $journal['year'];
            break;
        }
    }
}

if (empty($journalId) || !$month || !$year) {
    header("Location: paper_reports.php?error=missing_data");
    exit();
}

// Проверяем, имеет ли администратор доступ к журналу
$hasAccess = false;
$journals = loadJson('data/journals.json');
foreach ($journals as $journal) {
    if ($journal['id'] === $journalId && 
        isset($journal['admin_access']) && 
        in_array($userId, $journal['admin_access'])) {
        $hasAccess = true;
        break;
    }
}

if (!$hasAccess) {
    header("Location: paper_reports.php?error=access_denied");
    exit();
}

// Проверяем, существует ли уже запрос на этот отчет
if (reportRequestExists($journalId, $month, $year)) {
    header("Location: paper_reports.php?error=request_exists");
    exit();
}

// Проверяем, существует ли уже сам отчет
if (reportExists($journalId, $month, $year)) {
    header("Location: paper_reports.php?error=report_exists");
    exit();
}

// Добавляем запрос на отчет с указанием формата
$newRequest = [
    'id' => generateId(),
    'user_id' => $userId,
    'journal_id' => $journalId,
    'month' => $month,
    'year' => $year,
    'format' => $format,
    'status' => 'pending',
    'requested_at' => time(),
    'processed_at' => null,
    'processed_by' => null
];

$requests = loadJson('data/report_requests.json');
$requests[] = $newRequest;
saveJson('data/report_requests.json', $requests);

// Получаем данные журнала для лога
$journalData = null;
foreach ($journals as $journal) {
    if ($journal['id'] === $journalId) {
        $journalData = $journal;
        break;
    }
}

if ($journalData) {
    // Логируем запрос отчета
    logActivity($userId, 'request_report', "Запросил отчет по журналу: {$journalData['title']} за " . getRussianMonth($month) . " {$year} в формате " . strtoupper($format), $journalId);
}

header("Location: paper_reports.php?success=request_created");
exit();
 