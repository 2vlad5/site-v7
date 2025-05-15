<?php
session_start();
require_once  'functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];

// Получаем ID отчета
$reportId = $_GET['id'] ?? '';
if (empty($reportId)) {
    header("Location: paper_reports.php");
    exit();
}

// Проверяем права доступа к отчету
if (!canAccessPaperReport($reportId, $userId, $userRole)) {
    header("Location: paper_reports.php?error=access_denied");
    exit();
}

// Загружаем данные отчета
$reports = loadJson('data/paper_reports.json');
$reportData = null;
foreach ($reports as $report) {
    if ($report['id'] == $reportId) {
        $reportData = $report;
        break;
    }
}

if (!$reportData) {
    header("Location: paper_reports.php?error=not_found");
    exit();
}

$reportFilePath = "data/reports/{$reportData['filename']}";

// Проверяем существование файла
if (!file_exists($reportFilePath)) {
    // Если файл не существует, попробуем создать его заново
    $journal = null;
    $journals = loadJson('data/journals.json');
    foreach ($journals as $j) {
        if ($j['id'] === $reportData['journal_id']) {
            $journal = $j;
            break;
        }
    }
    
    if ($journal) {
        $entries = loadJson("data/entries/{$journal['id']}.json");
        $format = $reportData['format'] ?? 'txt';
        $hourlyRate = $reportData['hourly_rate'] ?? '';
        
        // Получаем данные о владельце журнала
        $journalOwner = getUserById($journal['user_id']);
        $ownerFullName = '';
        if ($journalOwner) {
            $ownerLastName = $journalOwner['last_name'] ?? '';
            $ownerFirstName = $journalOwner['first_name'] ?? '';
            $ownerMiddleName = $journalOwner['middle_name'] ?? '';
            $ownerFullName = trim("$ownerLastName $ownerFirstName $ownerMiddleName");
        }
        
        // Создаем директорию для отчетов если не существует
        $reportsDir = dirname($reportFilePath);
        if (!file_exists($reportsDir)) {
            mkdir($reportsDir, 0777, true);
        }
        
        // Генерируем отчет в нужном формате
        switch ($format) {
            case 'excel':
                exportJournalMonthToFile($journal, $entries, $reportData['month'], $reportFilePath, $ownerFullName, $hourlyRate);
                break;
            case 'html':
                $htmlContent = exportJournalToHTML($journal, $entries, $reportData['month'], $hourlyRate, true);
                file_put_contents($reportFilePath, $htmlContent);
                break;
            case 'txt':
            default:
                ob_start();
                exportJournalToTXT($journal, $entries, $reportData['month'], $hourlyRate, true);
                $txtContent = ob_get_clean();
                file_put_contents($reportFilePath, $txtContent);
                break;
        }
    } else {
        header("Location: paper_reports.php?error=journal_not_found");
        exit();
    }
}

// Логируем скачивание отчета
logActivity($userId, 'download_report', "Скачал отчет: {$reportData['filename']}", $reportId);

// Определяем MIME тип для разных форматов
$mimeType = 'text/plain';
switch ($reportData['format'] ?? 'txt') {
    case 'excel':
        $mimeType = 'text/csv';
        break;
    case 'html':
        $mimeType = 'text/html';
        break;
}

// Отдаем файл пользователю
header("Content-Type: {$mimeType}; charset=UTF-8");
header('Content-Disposition: attachment; filename="' . $reportData['filename'] . '"');
header('Pragma: no-cache');
header('Expires: 0');

readfile($reportFilePath);
exit();
 