<?php
session_start();
require_once  'functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$userRole = $_SESSION['role'];
$userId = $_SESSION['user_id'];

// Проверяем права доступа - только создатель может удалять отчеты
if ($userRole !== 'creator') {
    header("Location: dashboard.php");
    exit();
}

$reportId = $_GET['id'] ?? '';
if (empty($reportId)) {
    header("Location: paper_reports.php");
    exit();
}

// Загружаем текущие отчеты
$reports = loadJson('data/paper_reports.json');
$updatedReports = [];
$deletedReport = null;

// Удаляем отчет
foreach ($reports as $report) {
    if ($report['id'] !== $reportId) {
        $updatedReports[] = $report;
    } else {
        $deletedReport = $report;
    }
}

// Если нашли отчет для удаления
if ($deletedReport) {
    // Сохраняем обновленный список отчетов
    saveJson('data/paper_reports.json', $updatedReports);
    
    // Удаляем файл отчета
    $reportFilePath = "data/reports/{$deletedReport['filename']}";
    if (file_exists($reportFilePath)) {
        unlink($reportFilePath);
    }
    
    // Логируем удаление отчета
    $monthName = getRussianMonth($deletedReport['month']);
    logActivity($userId, 'delete_report', "Удалил отчет за {$monthName} {$deletedReport['year']}", $reportId);
}

// Перенаправляем обратно на страницу отчетов
header("Location: paper_reports.php");
exit();
 