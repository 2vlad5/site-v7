<?php
session_start();
require_once  'functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];

// Проверяем права доступа - только создатель может удалять отклоненные отчеты
if ($userRole !== 'creator') {
    header("Location: dashboard.php");
    exit();
}

// Получаем ID отчета из GET-параметра
$reportId = $_GET['id'] ?? '';
if (empty($reportId)) {
    header("Location: paper_reports.php");
    exit();
}

// Загружаем все отчеты
$reports = loadJson('data/paper_reports.json');
$updatedReports = [];
$deletedReport = null;

// Ищем отчет и проверяем что он отклоненный
foreach ($reports as $report) {
    if ($report['id'] === $reportId && isset($report['status']) && $report['status'] === 'rejected') {
        $deletedReport = $report;
    } else {
        $updatedReports[] = $report;
    }
}

if ($deletedReport) {
    // Сохраняем обновленный список отчетов
    saveJson('data/paper_reports.json', $updatedReports);
    
    // Логируем удаление отклоненного отчета
    logActivity($userId, 'delete_report', "Удалил отклоненный запрос на отчет", $reportId);
    
    // Возвращаемся на страницу отчетов с уведомлением об успехе
    header("Location: paper_reports.php?success=rejected_report_deleted");
} else {
    // Возвращаемся на страницу отчетов с ошибкой
    header("Location: paper_reports.php?error=report_not_found");
}

exit();
 