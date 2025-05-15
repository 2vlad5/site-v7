<?php
session_start();
require_once  'functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];

// Проверяем права доступа - только создатель может экспортировать
if ($userRole !== 'creator') {
    header("Location: dashboard.php");
    exit();
}

// Получаем ID журнала и месяц
$journalId = $_GET['id'] ?? '';
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');

if (empty($journalId)) {
    header("Location: dashboard.php");
    exit();
}

// Получаем ставку, если она была указана
$hourlyRate = isset($_GET['hourly_rate']) ? (float)$_GET['hourly_rate'] : '';

// Загружаем журнал
$journals = loadJson('data/journals.json');
$journalData = null;
foreach ($journals as $journal) {
    if ($journal['id'] == $journalId) {
        $journalData = $journal;
        break;
    }
}

if (!$journalData) {
    header("Location: dashboard.php");
    exit();
}

// Получаем данные о владельце журнала
$journalOwner = getUserById($journalData['user_id']);
$ownerFullName = '';
if ($journalOwner) {
    $ownerFirstName = $journalOwner['first_name'] ?? '';
    $ownerMiddleName = $journalOwner['middle_name'] ?? '';
    $ownerLastName = $journalOwner['last_name'] ?? '';
    $ownerFullName = trim("$ownerLastName $ownerFirstName $ownerMiddleName");
}

// Загружаем данные записей для текущего журнала
$entriesFile = "data/entries/{$journalId}.json";
$entries = loadJson($entriesFile);

// Имя месяца
$monthName = getRussianMonth($month);

// Генерируем имя файла отчета
$reportFileName = "{$ownerFullName}_{$monthName}_{$journalData['year']}.csv";

// Создаем директорию для хранения отчетов, если не существует
$reportsDir = "data/reports";
if (!file_exists($reportsDir)) {
    mkdir($reportsDir, 0777, true);
}

// Логируем экспорт
logActivity($userId, 'export_excel', "Экспортировал в Excel журнал: {$journalData['title']} за {$monthName} {$journalData['year']}", $journalId);

// Подготавливаем данные для сохранения отчета
$reportData = [
    'id' => generateId(),
    'journal_id' => $journalId,
    'user_id' => $journalData['user_id'],
    'month' => $month,
    'year' => $journalData['year'],
    'filename' => $reportFileName,
    'created_by' => $userId,
    'created_at' => time(),
    'admin_access' => $journalData['admin_access'] ?? [],
    'hourly_rate' => $hourlyRate
];

// Сохраняем отчет в хранилище
$reports = loadJson('data/paper_reports.json');
$reports[] = $reportData;
saveJson('data/paper_reports.json', $reports);

// Экспортируем в CSV и сохраняем на сервере
$reportFilePath = "{$reportsDir}/{$reportFileName}";
exportJournalMonthToFile($journalData, $entries, $month, $reportFilePath, $ownerFullName, $hourlyRate);

// Теперь отдаем файл пользователю
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $reportFileName . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Для корректной работы с кириллицей в Excel
echo "\xEF\xBB\xBF"; // BOM (Byte Order Mark)
readfile($reportFilePath);
exit();
 