<?php
session_start();
require_once  'functions.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit();
}

$userRole = $_SESSION['role'];
$userId = $_SESSION['user_id'];

// Проверяем права доступа - только создатель может управлять доступом
if ($userRole !== 'creator') {
    header("Location: dashboard.php");
    exit();
}

$reportId = $_POST['report_id'] ?? '';
$adminAccess = $_POST['admin_access'] ?? [];

if (empty($reportId)) {
    header("Location: paper_reports.php");
    exit();
}

// Загружаем текущие отчеты
$reports = loadJson('data/paper_reports.json');
$updated = false;
$reportData = null;

// Обновляем список администраторов с доступом
foreach ($reports as &$report) {
    if ($report['id'] === $reportId) {
        $reportData = $report;
        $report['admin_access'] = $adminAccess;
        $updated = true;
        break;
    }
}

// Сохраняем обновленные отчеты
if ($updated) {
    saveJson('data/paper_reports.json', $reports);
    
    // Логируем изменение прав доступа
    if ($reportData) {
        $monthName = getRussianMonth($reportData['month']);
        logActivity($userId, 'manage_access', "Изменил права доступа к отчету за {$monthName} {$reportData['year']}", $reportId);
    }
}

// Перенаправляем обратно на страницу отчетов
header("Location: paper_reports.php");
exit();
 