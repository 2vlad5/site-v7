<?php
session_start();
require_once  'functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];

// Проверяем права доступа - только создатель может удалять журналы
if ($userRole !== 'creator') {
    header("Location: dashboard.php");
    exit();
}

$journalId = $_GET['id'] ?? '';
if (empty($journalId)) {
    header("Location: dashboard.php");
    exit();
}

// Загружаем все журналы
$journals = loadJson('data/journals.json');
$updatedJournals = [];
$deletedJournal = null;

// Ищем журнал для удаления
foreach ($journals as $journal) {
    if ($journal['id'] === $journalId) {
        $deletedJournal = $journal;
    } else {
        $updatedJournals[] = $journal;
    }
}

if ($deletedJournal) {
    // Сохраняем обновленный список журналов
    saveJson('data/journals.json', $updatedJournals);
    
    // Удаляем файл записей журнала
    $entriesFile = "data/entries/{$journalId}.json";
    if (file_exists($entriesFile)) {
        unlink($entriesFile);
    }
    
    // Удаляем связанные отчеты
    $reports = loadJson('data/paper_reports.json');
    $updatedReports = [];
    $deletedReports = [];
    
    foreach ($reports as $report) {
        if ($report['journal_id'] === $journalId) {
            $deletedReports[] = $report;
        } else {
            $updatedReports[] = $report;
        }
    }
    
    saveJson('data/paper_reports.json', $updatedReports);
    
    // Удаляем файлы отчетов
    foreach ($deletedReports as $report) {
        if (isset($report['filename'])) {
            $reportFile = "data/reports/{$report['filename']}";
            if (file_exists($reportFile)) {
                unlink($reportFile);
            }
        }
    }
    
    // Удаляем связанные запросы на восстановление
    $recoveryRequests = loadJson('data/recovery_requests.json');
    $updatedRecoveryRequests = [];
    
    foreach ($recoveryRequests as $request) {
        if ($request['journal_id'] !== $journalId) {
            $updatedRecoveryRequests[] = $request;
        }
    }
    
    saveJson('data/recovery_requests.json', $updatedRecoveryRequests);
    
    // Удаляем связанные уведомления
    $notifications = loadJson('data/notifications.json');
    $updatedNotifications = [];
    
    foreach ($notifications as $notification) {
        if (!isset($notification['journal_id']) || $notification['journal_id'] !== $journalId) {
            $updatedNotifications[] = $notification;
        }
    }
    
    saveJson('data/notifications.json', $updatedNotifications);
    
    // Удаляем связанные запросы на отчеты
    $reportRequests = loadJson('data/report_requests.json');
    $updatedReportRequests = [];
    
    foreach ($reportRequests as $request) {
        if ($request['journal_id'] !== $journalId) {
            $updatedReportRequests[] = $request;
        }
    }
    
    saveJson('data/report_requests.json', $updatedReportRequests);
    
    // Логируем удаление журнала
    logActivity($userId, 'delete_journal', "Удалил журнал: {$deletedJournal['title']}", $journalId);
}

// Возвращаемся на панель управления
header("Location: dashboard.php?tab=my_journals");
exit();
 