<?php
session_start();
require_once  'functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];

// Проверяем права доступа
if ($userRole !== 'creator' && $userRole !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

// Получаем параметры
$journalId = $_GET['id'] ?? '';
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$format = $_GET['format'] ?? 'excel';
$hourlyRate = isset($_GET['hourly_rate']) ? (float)$_GET['hourly_rate'] : '';

if (empty($journalId)) {
    header("Location: dashboard.php");
    exit();
}

// Проверяем доступ к журналу
if (!canAccessJournal($journalId, $userId, $userRole)) {
    header("Location: dashboard.php?error=access_denied");
    exit();
}

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

// Определяем формат экспорта и выполняем соответствующую функцию
if ($format === 'excel') {
    // Логируем экспорт в Excel
    logActivity($userId, 'export_excel', "Экспортировал в Excel журнал: {$journalData['title']} за {$monthName} {$journalData['year']}", $journalId);
    
    // Подготавливаем данные для сохранения отчета
    $reportData = [
        'id' => generateId(),
        'journal_id' => $journalId,
        'user_id' => $journalData['user_id'],
        'month' => $month,
        'year' => $journalData['year'],
        'filename' => "{$ownerFullName}_{$monthName}_{$journalData['year']}.csv",
        'format' => 'excel',
        'created_by' => $userId,
        'created_at' => time(),
        'admin_access' => $journalData['admin_access'] ?? [],
        'hourly_rate' => $hourlyRate
    ];
    
    // Сохраняем отчет в хранилище
    $reports = loadJson('data/paper_reports.json');
    $reports[] = $reportData;
    saveJson('data/paper_reports.json', $reports);
    
    // Экспортируем в CSV
    exportJournalToCSV($journalData, $entries, $month, $hourlyRate);
} elseif ($format === 'html') {
    // Логируем экспорт в HTML
    logActivity($userId, 'export_html', "Экспортировал в HTML журнал: {$journalData['title']} за {$monthName} {$journalData['year']}", $journalId);
    
    // Подготавливаем данные для сохранения отчета
    $reportData = [
        'id' => generateId(),
        'journal_id' => $journalId,
        'user_id' => $journalData['user_id'],
        'month' => $month,
        'year' => $journalData['year'],
        'filename' => "{$ownerFullName}_{$monthName}_{$journalData['year']}.html",
        'format' => 'html',
        'created_by' => $userId,
        'created_at' => time(),
        'admin_access' => $journalData['admin_access'] ?? [],
        'hourly_rate' => $hourlyRate
    ];
    
    // Сохраняем отчет в хранилище
    $reports = loadJson('data/paper_reports.json');
    $reports[] = $reportData;
    saveJson('data/paper_reports.json', $reports);
    
    // Экспортируем в HTML
    exportJournalToHTML($journalData, $entries, $month, $hourlyRate);
} elseif ($format === 'txt') {
    // Логируем экспорт в TXT
    logActivity($userId, 'export_txt', "Экспортировал в TXT журнал: {$journalData['title']} за {$monthName} {$journalData['year']}", $journalId);
    
    // Подготавливаем данные для сохранения отчета
    $reportData = [
        'id' => generateId(),
        'journal_id' => $journalId,
        'user_id' => $journalData['user_id'],
        'month' => $month,
        'year' => $journalData['year'],
        'filename' => "{$ownerFullName}_{$monthName}_{$journalData['year']}.txt",
        'format' => 'txt',
        'created_by' => $userId,
        'created_at' => time(),
        'admin_access' => $journalData['admin_access'] ?? [],
        'hourly_rate' => $hourlyRate
    ];
    
    // Сохраняем отчет в хранилище
    $reports = loadJson('data/paper_reports.json');
    $reports[] = $reportData;
    saveJson('data/paper_reports.json', $reports);
    
    // Экспортируем в TXT
    exportJournalToTXT($journalData, $entries, $month, $hourlyRate);
} else {
    // Неизвестный формат, возвращаемся на страницу журнала
    header("Location: journal.php?id={$journalId}&month={$month}");
    exit();
}
 