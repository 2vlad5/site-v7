<?php
session_start();
require_once  'functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];

// Проверяем права доступа - только создатель может обрабатывать запросы
if ($userRole !== 'creator') {
    header("Location: dashboard.php");
    exit();
}

$requestId = $_GET['request_id'] ?? '';
$action = $_GET['action'] ?? '';
$hourlyRate = isset($_GET['hourly_rate']) ? (float)$_GET['hourly_rate'] : '';
$format = $_GET['format'] ?? 'txt';  // По умолчанию TXT формат

if (empty($requestId) || !in_array($action, ['approve', 'reject'])) {
    header("Location: paper_reports.php?error=invalid_request");
    exit();
}

// Загружаем запросы
$requests = loadJson('data/report_requests.json');
$requestData = null;

// Находим запрос
foreach ($requests as $request) {
    if ($request['id'] === $requestId) {
        $requestData = $request;
        break;
    }
}

if (!$requestData || $requestData['status'] !== 'pending') {
    header("Location: paper_reports.php?error=not_found");
    exit();
}

// Обновляем статус запроса
updateReportRequestStatus($requestId, $action === 'approve' ? 'approved' : 'rejected', $userId);

// Если запрос одобрен, создаем отчет
if ($action === 'approve') {
    // Загружаем журнал
    $journals = loadJson('data/journals.json');
    $journalData = null;
    foreach ($journals as $journal) {
        if ($journal['id'] === $requestData['journal_id']) {
            $journalData = $journal;
            break;
        }
    }
    
    if ($journalData) {
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
        $entriesFile = "data/entries/{$journalData['id']}.json";
        $entries = loadJson($entriesFile);
        
        // Генерируем имя файла отчета с учетом формата
        $monthName = getRussianMonth($requestData['month']);
        $extension = '';
        switch ($format) {
            case 'excel':
                $extension = 'csv';
                break;
            case 'html':
                $extension = 'html';
                break;
            case 'txt':
            default:
                $extension = 'txt';
                break;
        }
        $reportFileName = "{$ownerFullName}_{$monthName}_{$requestData['year']}.{$extension}";
        
        // Создаем директорию для хранения отчетов, если не существует
        $reportsDir = "data/reports";
        if (!file_exists($reportsDir)) {
            mkdir($reportsDir, 0777, true);
        }
        
        // Подготавливаем данные для сохранения отчета
        $reportData = [
            'id' => generateId(),
            'journal_id' => $journalData['id'],
            'user_id' => $journalData['user_id'],
            'month' => $requestData['month'],
            'year' => $requestData['year'],
            'filename' => $reportFileName,
            'format' => $format,
            'created_by' => $userId,
            'created_at' => time(),
            'admin_access' => [$requestData['user_id']], // Даем доступ запросившему администратору
            'request_id' => $requestId,
            'hourly_rate' => $hourlyRate
        ];
        
        // Сохраняем отчет в хранилище
        $reports = loadJson('data/paper_reports.json');
        $reports[] = $reportData;
        saveJson('data/paper_reports.json', $reports);
        
        // Экспортируем файл и сохраняем на сервере в зависимости от формата
        $reportFilePath = "{$reportsDir}/{$reportFileName}";
        
        switch ($format) {
            case 'excel':
                exportJournalMonthToFile($journalData, $entries, $requestData['month'], $reportFilePath, $ownerFullName, $hourlyRate);
                break;
            case 'html':
                // Для HTML:
                // 1. Создаем HTML в памяти
                ob_start();
                exportJournalToHTML($journalData, $entries, $requestData['month'], $hourlyRate, true); // True означает "сохранить в файл"
                $htmlContent = ob_get_clean();
                
                // 2. Сохраняем в файл
                file_put_contents($reportFilePath, $htmlContent);
                break;
            case 'txt':
            default:
                // Для TXT формата
                ob_start();
                exportJournalToTXT($journalData, $entries, $requestData['month'], $hourlyRate, true); // True означает "сохранить в файл"
                $txtContent = ob_get_clean();
                
                // Сохраняем в файл
                file_put_contents($reportFilePath, $txtContent);
                break;
        }
        
        // Логируем создание отчета
        logActivity($userId, 'approve_report', "Одобрил запрос на отчет по журналу: {$journalData['title']} за {$monthName} {$requestData['year']} в формате " . strtoupper($format), $requestData['journal_id']);
    }
} else {
    // Логируем отклонение запроса
    $journals = loadJson('data/journals.json');
    $journalData = null;
    foreach ($journals as $journal) {
        if ($journal['id'] === $requestData['journal_id']) {
            $journalData = $journal;
            break;
        }
    }
    
    if ($journalData) {
        $monthName = getRussianMonth($requestData['month']);
        logActivity($userId, 'reject_report', "Отклонил запрос на отчет по журналу: {$journalData['title']} за {$monthName} {$requestData['year']}", $requestData['journal_id']);
        
        // Добавляем запись об отклонении в отчеты с пометкой rejected
        $reportData = [
            'id' => generateId(),
            'journal_id' => $journalData['id'],
            'user_id' => $journalData['user_id'],
            'month' => $requestData['month'],
            'year' => $requestData['year'],
            'created_by' => $userId,
            'created_at' => time(),
            'admin_access' => [$requestData['user_id']], // Даем доступ запросившему администратору
            'request_id' => $requestId,
            'status' => 'rejected',
            'rejected_at' => time(),
            'reject_reason' => $_GET['reason'] ?? 'Отклонено создателем'
        ];
        
        // Сохраняем отчет с пометкой rejected в хранилище
        $reports = loadJson('data/paper_reports.json');
        $reports[] = $reportData;
        saveJson('data/paper_reports.json', $reports);
    }
}

header("Location: paper_reports.php?success=request_processed");
exit();
 