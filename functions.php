<?php
//     Функция для загрузки данных из JSON файла
function loadJson($file) {
    if (!file_exists($file)) {
        return [];
    }
    $data = file_get_contents($file);
    return json_decode($data, true) ?: [];
}

// Функция для сохранения данных в JSON файл
function saveJson($file, $data) {
    if (!file_exists(dirname($file))) {
        mkdir(dirname($file), 0777, true);
    }
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

// Synchronizes time with client's device time
function getClientAdjustedTime() {
    if (isset($_SESSION['client_time']) && isset($_SESSION['time_diff'])) {
        // Return current server time adjusted by the known difference
        return time() + $_SESSION['time_diff'];
    }

    // Fall back to server time if client time is not available
    return time();
}

// Gets current date according to client's timezone
function getClientDate($format = 'Y-m-d') {
    $timestamp = getClientAdjustedTime();
    return date($format, $timestamp);
}

// Gets current time according to client's timezone
function getClientTime($format = 'H:i:s') {
    $timestamp = getClientAdjustedTime();
    return date($format, $timestamp);
}

// Gets current datetime according to client's timezone
function getClientDateTime($format = 'Y-m-d H:i:s') {
    $timestamp = getClientAdjustedTime();
    return date($format, $timestamp);
}

// Получение информации о пользователе по ID
function getUserById($userId) {
    $users = loadJson('data/users.json');
    foreach ($users as $user) {
        if ($user['id'] == $userId) {
            return $user;
        }
    }
    return null;
}

// Получение списка журналов пользователя
function getUserJournals($userId) {
    $journals = loadJson('data/journals.json');
    $userJournals = [];

    foreach ($journals as $journal) {
        if ($journal['user_id'] == $userId) {
            $userJournals[] = $journal;
        }
    }

    return $userJournals;
}

// Группировка журналов по годам
function groupJournalsByYear($journals) {
    $groupedJournals = [];

    foreach ($journals as $journal) {
        $year = $journal['year'];
        if (!isset($groupedJournals[$year])) {
            $groupedJournals[$year] = [];
        }
        $groupedJournals[$year][] = $journal;
    }

    return $groupedJournals;
}

// Проверка прав доступа на просмотр журнала
function canAccessJournal($journalId, $userId, $userRole) {
    if ($userRole === 'creator') {
        return true; // Создатель имеет доступ ко всем журналам
    }

    $journals = loadJson('data/journals.json');
    foreach ($journals as $journal) {
        if ($journal['id'] == $journalId) {
            if ($journal['user_id'] == $userId) {
                return true; // Пользователь имеет доступ к своему журналу
            }

            if ($userRole === 'admin' && isset($journal['admin_access']) && in_array($userId, $journal['admin_access'])) {
                return true; // Администратор имеет специальный доступ
            }
        }
    }

    return false;
}

// Проверка прав доступа к бумажному отчету
function canAccessPaperReport($reportId, $userId, $userRole) {
    if ($userRole === 'creator') {
        return true; // Создатель имеет доступ ко всем отчетам
    }

    $reports = loadJson('data/paper_reports.json');
    foreach ($reports as $report) {
        if ($report['id'] == $reportId) {
            if ($report['user_id'] == $userId) {
                return true; // Пользователь имеет доступ к своему отчету
            }

            if ($userRole === 'admin' && isset($report['admin_access']) && in_array($userId, $report['admin_access'])) {
                return true; // Администратор имеет специальный доступ
            }
        }
    }

    return false;
}

// Генерация случайного ID
function generateId() {
    return uniqid('', true);
}

// Получение названия месяца на русском
function getRussianMonth($month) {
    $months = [
        1 => 'Январь',
        2 => 'Февраль',
        3 => 'Март',
        4 => 'Апрель',
        5 => 'Май',
        6 => 'Июнь',
        7 => 'Июль',
        8 => 'Август',
        9 => 'Сентябрь',
        10 => 'Октябрь',
        11 => 'Ноябрь',
        12 => 'Декабрь'
    ];

    return $months[$month] ?? '';
}

// Получение названия месяца в родительном падеже
function getRussianMonthGenitive($month) {
    $months = [
        1 => 'Января',
        2 => 'Февраля',
        3 => 'Марта',
        4 => 'Апреля',
        5 => 'Мая',
        6 => 'Июня',
        7 => 'Июля',
        8 => 'Августа',
        9 => 'Сентября',
        10 => 'Октября',
        11 => 'Ноября',
        12 => 'Декабря'
    ];

    return $months[$month] ?? '';
}

// Расчет рабочих часов за день - исправлено
function calculateHours($startTime, $endTime, $lunchMinutes) {
    if (empty($startTime) || empty($endTime)) {
        return 0;
    }

    // Преобразуем время в метку времени Unix
    $start = strtotime($startTime);
    $end = strtotime($endTime);

    if ($start === false || $end === false) {
        return 0;
    }

    // Вычисляем разницу в минутах
    $totalMinutes = ($end - $start) / 60;

    // Если получается отрицательное число (конец дня меньше начала)
    // значит, смена переходит через полночь
    if ($totalMinutes < 0) {
        $totalMinutes += 24 * 60; // Добавляем 24 часа (в минутах)
    }

    // Вычитаем обеденный перерыв
    $workMinutes = $totalMinutes - $lunchMinutes;

    // Переводим в часы с округлением до двух знаков
    return round($workMinutes / 60, 2);
}

// Получить цвет дня на основе отработанных часов
function getDayColor($hours) {
    if ($hours === 0) {
        return 'gray';
    } elseif ($hours <= 5) {
        return 'yellow';
    } elseif ($hours <= 10) {
        return 'green';
    } else {
        return 'purple';
    }
}

// Проверка статуса ключа доступа
function checkAccessKeyStatus($user) {
    if (!isset($user['access_key_expiry'])) {
        return 'missing'; // Ключ отсутствует
    }

    $daysLeft = ceil(($user['access_key_expiry'] - getClientAdjustedTime()) / 86400);

    if ($daysLeft <= 0) {
        return 'expired'; // Ключ истек
    } elseif ($daysLeft <= 7) {
        return 'warning'; // Ключ скоро истечет
    }

    return 'valid'; // Ключ действителен
}

// Получение всех администраторов
function getAllAdmins() {
    $users = loadJson('data/users.json');
    $admins = [];

    foreach ($users as $user) {
        if ($user['role'] === 'admin') {
            $admins[] = $user;
        }
    }

    return $admins;
}

// Группировка журналов по пользователям
function getJournalsGroupedByUsers($journals) {
    $groupedJournals = [];

    foreach ($journals as $journal) {
        $userId = $journal['user_id'];

        if (!isset($groupedJournals[$userId])) {
            $user = getUserById($userId);
            if ($user) {
                $groupedJournals[$userId] = [
                    'user' => $user,
                    'journals' => []
                ];
            }
        }

        if (isset($groupedJournals[$userId])) {
            $groupedJournals[$userId]['journals'][] = $journal;
        }
    }

    return $groupedJournals;
}

// Группировка отчетов по пользователям
function getReportsGroupedByUsers($reports) {
    $groupedReports = [];

    foreach ($reports as $report) {
        $userId = $report['user_id'];

        if (!isset($groupedReports[$userId])) {
            $user = getUserById($userId);
            if ($user) {
                $groupedReports[$userId] = [
                    'user' => $user,
                    'reports' => []
                ];
            }
        }

        if (isset($groupedReports[$userId])) {
            $groupedReports[$userId]['reports'][] = $report;
        }
    }

    return $groupedReports;
}

// Добавление записи в журнал активности
function logActivity($userId, $action, $description, $entityId = null) {
    $log = [
        'id' => generateId(),
        'user_id' => $userId,
        'action' => $action,
        'description' => $description,
        'entity_id' => $entityId,
        'timestamp' => getClientAdjustedTime()
    ];

    $logs = loadJson('data/activity_log.json');
    $logs[] = $log;

    // Оставляем только последние 100 записей
    if (count($logs) > 100) {
        usort($logs, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });
        $logs = array_slice($logs, 0, 100);
    }

    saveJson('data/activity_log.json', $logs);
}

// Получение иконки для действия
function getActionIcon($action) {
    $icons = [
        'login' => 'fa-sign-in-alt',
        'logout' => 'fa-sign-out-alt',
        'create_user' => 'fa-user-plus',
        'edit_user' => 'fa-user-edit',
        'delete_user' => 'fa-user-minus',
        'create_journal' => 'fa-plus-circle',
        'edit_journal' => 'fa-edit',
        'delete_journal' => 'fa-trash',
        'edit_entry' => 'fa-calendar-day',
        'export_excel' => 'fa-file-excel',
        'export_html' => 'fa-file-code',
        'export_txt' => 'fa-file-alt',
        'extend_key' => 'fa-key',
        'revoke_key' => 'fa-key-slash',
        'manage_access' => 'fa-users-cog',
        'request_report' => 'fa-file-invoice',
        'approve_report' => 'fa-check-circle',
        'reject_report' => 'fa-times-circle',
        'delete_report' => 'fa-trash-alt',
        'request_recovery' => 'fa-life-ring',
        'help_recovery' => 'fa-hand-holding',
        'reject_recovery' => 'fa-times-circle',
        'backup_journal' => 'fa-save',
        'backup_journals' => 'fa-database',
        'restore_backup' => 'fa-undo',
        'delete_backup' => 'fa-trash-alt',
        'download_backup' => 'fa-download',
        'upload_backup' => 'fa-upload',
        'assign_shift' => 'fa-calendar-check',
        'mark_read' => 'fa-envelope-open',
        'cleanup' => 'fa-broom',
        'scheduler_run' => 'fa-clock'
    ];

    return $icons[$action] ?? 'fa-circle';
}

// Получить текущий месяц
function getCurrentMonth() {
    return (int)date('n', getClientAdjustedTime()); // Возвращает номер месяца без ведущего нуля (1-12)
}

// Преобразование массива данных в CSV
function arrayToCsv($data) {
    $output = fopen('php://temp', 'w');
    foreach ($data as $row) {
        fputcsv($output, $row, ';', '"');
    }
    rewind($output);
    $csv = stream_get_contents($output);
    fclose($output);
    return $csv;
}

// Функция для экспорта журнала в Excel (CSV формат) в файл
function exportJournalMonthToFile($journalData, $entries, $month, $filePath, $fullName, $hourlyRate = '') {
    // Создаем файл
    $output = fopen($filePath, 'w');

    // Для корректной работы с кириллицей в Excel
    fputs($output, "\xEF\xBB\xBF"); // BOM (Byte Order Mark)

    // Данные сотрудника
    fputcsv($output, ['Сотрудник:', $fullName], ';', '"');
    fputcsv($output, ['Журнал:', $journalData['title']], ';', '"');
    fputcsv($output, ['Месяц:', getRussianMonth($month) . ' ' . $journalData['year']], ';', '"');
    fputcsv($output, [], ';', '"'); // Пустая строка

    // Заголовки таблицы
    fputcsv($output, ['Дата', 'Статус', 'Начало', 'Конец', 'Обед (мин)', 'Часы'], ';', '"');

    $totalHours = 0;
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $journalData['year']);

    // Заполняем данные по дням для выбранного месяца
    for ($day = 1; $day <= $daysInMonth; $day++) {
        $dayKey = sprintf("%04d-%02d-%02d", $journalData['year'], $month, $day);
        $dayData = $entries[$dayKey] ?? null;

        $isDayOff = isset($dayData['is_day_off']) && $dayData['is_day_off'] === true;
        $status = $isDayOff ? 'Выходной' : 'Рабочий';

        $startTime = $isDayOff ? '-' : ($dayData['start_time'] ?? '');
        $endTime = $isDayOff ? '-' : ($dayData['end_time'] ?? '');
        $lunchMinutes = $isDayOff ? '-' : ($dayData['lunch_minutes'] ?? 0);

        $hours = 0;
        if ($dayData && !$isDayOff) {
            $hours = calculateHours($startTime, $endTime, $lunchMinutes);
        }

        $totalHours += $hours;

        fputcsv($output, [
            $day, // Только номер дня без названия месяца
            $status,
            $startTime,
            $endTime,
            is_numeric($lunchMinutes) ? $lunchMinutes : $lunchMinutes,
            $isDayOff ? '-' : number_format($hours, 2, ',', '')
        ], ';', '"');
    }

    // Добавляем итоговую строку
    fputcsv($output, [], ';', '"'); // Пустая строка
    fputcsv($output, ['Всего часов:', number_format($totalHours, 2, ',', '')], ';', '"');

    // Если указана почасовая ставка, добавляем расчет
    if (!empty($hourlyRate)) {
        fputcsv($output, ['Ставка (руб/час):', number_format($hourlyRate, 2, ',', '')], ';', '"');
        $totalAmount = $totalHours * $hourlyRate;
        fputcsv($output, ['Итого к оплате:', number_format($totalAmount, 2, ',', '')], ';', '"');
    }

    fclose($output);
    return true;
}

// Функция для экспорта журнала в Excel (CSV формат) через браузер
function exportJournalToCSV($journalData, $entries, $month, $hourlyRate = '') {
    $monthName = getRussianMonth($month);
    $filename = $journalData['title'] . '_' . $monthName . '_' . $journalData['year'] . '.csv';

    // Заголовки для браузера
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Для корректной работы с кириллицей в Excel
    echo "\xEF\xBB\xBF"; // BOM (Byte Order Mark)

    $output = fopen('php://output', 'w');

    // Получаем данные о владельце журнала
    $journalOwner = getUserById($journalData['user_id']);
    $ownerFullName = '';
    if ($journalOwner) {
        $ownerFirstName = $journalOwner['first_name'] ?? '';
        $ownerMiddleName = $journalOwner['middle_name'] ?? '';
        $ownerLastName = $journalOwner['last_name'] ?? '';
        $ownerFullName = trim("$ownerLastName $ownerFirstName $ownerMiddleName");
    }

    // Данные сотрудника
    fputcsv($output, ['Сотрудник:', $ownerFullName], ';', '"');
    fputcsv($output, ['Журнал:', $journalData['title']], ';', '"');
    fputcsv($output, ['Месяц:', $monthName . ' ' . $journalData['year']], ';', '"');
    fputcsv($output, [], ';', '"'); // Пустая строка

    // Заголовки таблицы
    fputcsv($output, ['Дата', 'Статус', 'Начало', 'Конец', 'Обед (мин)', 'Часы'], ';', '"');

    $totalHours = 0;
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $journalData['year']);

    // Заполняем данные по дням
    for ($day = 1; $day <= $daysInMonth; $day++) {
        $dayKey = sprintf("%04d-%02d-%02d", $journalData['year'], $month, $day);
        $dayData = $entries[$dayKey] ?? null;

        $isDayOff = isset($dayData['is_day_off']) && $dayData['is_day_off'] === true;
        $status = $isDayOff ? 'Выходной' : 'Рабочий';

        $startTime = $isDayOff ? '-' : ($dayData['start_time'] ?? '');
        $endTime = $isDayOff ? '-' : ($dayData['end_time'] ?? '');
        $lunchMinutes = $isDayOff ? '-' : ($dayData['lunch_minutes'] ?? 0);

        $hours = 0;
        if ($dayData && !$isDayOff) {
            $hours = calculateHours($startTime, $endTime, $lunchMinutes);
        }

        $totalHours += $hours;

        fputcsv($output, [
            $day, // Только номер дня без названия месяца
            $status,
            $startTime,
            $endTime,
            is_numeric($lunchMinutes) ? $lunchMinutes : $lunchMinutes,
            $isDayOff ? '-' : number_format($hours, 2, ',', '')
        ], ';', '"');
    }

    // Добавляем итоговую строку
    fputcsv($output, [], ';', '"'); // Пустая строка
    fputcsv($output, ['Всего часов:', number_format($totalHours, 2, ',', '')], ';', '"');

    // Если указана почасовая ставка, добавляем расчет
    if (!empty($hourlyRate)) {
        fputcsv($output, ['Ставка (руб/час):', number_format($hourlyRate, 2, ',', '')], ';', '"');
        $totalAmount = $totalHours * $hourlyRate;
        fputcsv($output, ['Итого к оплате:', number_format($totalAmount, 2, ',', '')], ';', '"');
    }

    fclose($output);
    exit;
}

// Функция для экспорта журнала в текстовый файл
function exportJournalToTXT($journalData, $entries, $month, $hourlyRate = '', $saveToFile = false) {
    $monthName = getRussianMonth($month);
    $filename = $journalData['title'] . '_' . $monthName . '_' . $journalData['year'] . '.txt';

    if (!$saveToFile) {
        // Заголовки для браузера
        header('Content-Type: text/plain; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
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

    // Данные сотрудника
    echo "Сотрудник: " . $ownerFullName . "\n";
    echo "Журнал: " . $journalData['title'] . "\n";
    echo "Месяц: " . $monthName . " " . $journalData['year'] . "\n\n";

    // Заголовки таблицы
    echo "Дата\tСтатус\t\tНачало\t\tКонец\t\tОбед (мин)\tЧасы\n";
    echo "---------------------------------------------------------------------\n";

    $totalHours = 0;
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $journalData['year']);

    // Заполняем данные по дням
    for ($day = 1; $day <= $daysInMonth; $day++) {
        $dayKey = sprintf("%04d-%02d-%02d", $journalData['year'], $month, $day);
        $dayData = $entries[$dayKey] ?? null;

        $isDayOff = isset($dayData['is_day_off']) && $dayData['is_day_off'] === true;
        $status = $isDayOff ? 'Выходной' : 'Рабочий';

        $startTime = $isDayOff ? '-' : ($dayData['start_time'] ?? '');
        $endTime = $isDayOff ? '-' : ($dayData['end_time'] ?? '');
        $lunchMinutes = $isDayOff ? '-' : ($dayData['lunch_minutes'] ?? 0);

        $hours = 0;
        if ($dayData && !$isDayOff) {
            $hours = calculateHours($startTime, $endTime, $lunchMinutes);
        }

        $totalHours += $hours;

        // Форматирование вывода с выравниванием по табуляции
        $dayStr = str_pad($day, 2, ' ', STR_PAD_LEFT);
        $statusStr = str_pad($status, 16, ' ', STR_PAD_RIGHT);
        $startTimeStr = str_pad($startTime, 8, ' ', STR_PAD_RIGHT);
        $endTimeStr = str_pad($endTime, 8, ' ', STR_PAD_RIGHT);
        $lunchStr = str_pad(is_numeric($lunchMinutes) ? $lunchMinutes : $lunchMinutes, 8, ' ', STR_PAD_RIGHT);
        $hoursStr = $isDayOff ? '-' : number_format($hours, 2, '.', '');

        echo "{$dayStr}\t{$statusStr}{$startTimeStr}\t{$endTimeStr}\t{$lunchStr}\t{$hoursStr}\n";
    }

    // Добавляем итоговую строку
    echo "---------------------------------------------------------------------\n";
    echo "Всего часов: " . number_format($totalHours, 2, '.', '') . "\n";

    // Если указана почасовая ставка, добавляем расчет
    if (!empty($hourlyRate)) {
        echo "Ставка (руб/час): " . number_format($hourlyRate, 2, '.', '') . "\n";
        $totalAmount = $totalHours * $hourlyRate;
        echo "Итого к оплате: " . number_format($totalAmount, 2, '.', '') . " руб.\n";
    }

    if (!$saveToFile) {
        exit;
    }
}

// Функция для экспорта журнала в HTML формат
function exportJournalToHTML($journalData, $entries, $month, $hourlyRate = '', $saveToFile = false) {
    $monthName = getRussianMonth($month);
    $filename = $journalData['title'] . '_' . $monthName . '_' . $journalData['year'] . '.html';

    if (!$saveToFile) {
        // Заголовки для браузера
        header('Content-Type: text/html; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
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

    // Начало HTML файла
    ob_start();
    ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Отчет: <?= htmlspecialchars($journalData['title']) ?> - <?= htmlspecialchars($monthName) ?> <?= $journalData['year'] ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .report-header {
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        .report-header h1 {
            color: #2980b9;
            margin-bottom: 10px;
        }
        .report-header p {
            margin: 5px 0;
            color: #555;
        }
        .report-info {
            margin-bottom: 20px;
        }
        .report-info p {
            margin: 5px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        .total-row {
            font-weight: bold;
            background-color: #f0f8ff;
        }
        .total-row td {
            border-top: 2px solid #2980b9;
        }
        .gray { background-color: #e0e0e0; color: #888; }
        .yellow { background-color: #fff9c4; color: #d35400; }
        .green { background-color: #c8e6c9; color: #006266; }
        .purple { background-color: #e1bee7; color: #4834d4; }
        .day-off { background-color: #e0e0e0; color: #1a73e8; }
        .color-note {
            display: inline-block;
            width: 20px;
            height: 20px;
            margin-right: 5px;
            vertical-align: middle;
        }
        .legends {
            margin-top: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 4px;
        }
        .legend-item {
            margin-bottom: 5px;
            display: flex;
            align-items: center;
        }
        .payment-info {
            margin-top: 20px;
            padding: 15px;
            background-color: #e8f4fd;
            border-radius: 4px;
            border-left: 4px solid #2980b9;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            font-size: 14px;
            color: #777;
            text-align: center;
        }
        .day-status {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 500;
        }
        .day-off-status {
            background-color: #e0e0e0;
            color: #1a73e8;
        }
        .work-day-status {
            background-color: #e8f5e9;
            color: #2ecc71;
        }
    </style>
</head>
<body>
    <div class="report-header">
        <h1>Отчет о рабочем времени</h1>
        <p>Формирован: <?= date('d.m.Y H:i', getClientAdjustedTime()) ?></p>
    </div>

    <div class="report-info">
        <p><strong>Сотрудник:</strong> <?= htmlspecialchars($ownerFullName) ?></p>
        <p><strong>Журнал:</strong> <?= htmlspecialchars($journalData['title']) ?></p>
        <p><strong>Период:</strong> <?= htmlspecialchars($monthName) ?> <?= $journalData['year'] ?></p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Дата</th>
                <th>Статус</th>
                <th>Начало</th>
                <th>Конец</th>
                <th>Обед (мин)</th>
                <th>Часы</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $totalHours = 0;
            $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $journalData['year']);

            for ($day = 1; $day <= $daysInMonth; $day++) {
                $dayKey = sprintf("%04d-%02d-%02d", $journalData['year'], $month, $day);
                $dayData = $entries[$dayKey] ?? null;

                $isDayOff = isset($dayData['is_day_off']) && $dayData['is_day_off'] === true;
                $status = $isDayOff ? 'Выходной' : 'Рабочий';

                $startTime = $isDayOff ? '-' : ($dayData['start_time'] ?? '');
                $endTime = $isDayOff ? '-' : ($dayData['end_time'] ?? '');
                $lunchMinutes = $isDayOff ? '-' : ($dayData['lunch_minutes'] ?? 0);

                $hours = 0;
                if ($dayData && !$isDayOff) {
                    $hours = calculateHours($startTime, $endTime, $lunchMinutes);
                }

                $totalHours += $hours;

                $colorClass = $isDayOff ? 'day-off' : getDayColor($hours);
                $statusClass = $isDayOff ? 'day-off-status' : 'work-day-status';
                ?>
                <tr class="<?= $colorClass ?>">
                    <td><?= $day ?> <?= getRussianMonthGenitive($month) ?></td>
                    <td><span class="day-status <?= $statusClass ?>"><?= $status ?></span></td>
                    <td><?= htmlspecialchars($startTime) ?></td>
                    <td><?= htmlspecialchars($endTime) ?></td>
                    <td><?= is_numeric($lunchMinutes) ? $lunchMinutes : htmlspecialchars($lunchMinutes) ?></td>
                    <td><?= $isDayOff ? '-' : number_format($hours, 2, '.', '') ?></td>
                </tr>
            <?php } ?>
            <tr class="total-row">
                <td colspan="5">Всего часов:</td>
                <td><?= number_format($totalHours, 2, '.', '') ?></td>
            </tr>
        </tbody>
    </table>

    <?php if (!empty($hourlyRate)): ?>
    <div class="payment-info">
        <p><strong>Ставка:</strong> <?= number_format($hourlyRate, 2, '.', '') ?> руб/час</p>
        <p><strong>Итого к оплате:</strong> <?= number_format($totalHours * $hourlyRate, 2, '.', '') ?> руб.</p>
    </div>
    <?php endif; ?>

    <div class="legends">
        <h3>Цветовые обозначения</h3>
        <div class="legend-item">
            <div class="color-note day-off"></div> Выходной день
        </div>
        <div class="legend-item">
            <div class="color-note gray"></div> Не заполнено (0 часов)
        </div>
        <div class="legend-item">
            <div class="color-note yellow"></div> До 5 часов
        </div>
        <div class="legend-item">
            <div class="color-note green"></div> От 6 до 10 часов
        </div>
        <div class="legend-item">
            <div class="color-note purple"></div> Более 10 часов
        </div>
    </div>

    <div class="footer">
        <p>Отчет сформирован автоматически системой учета рабочего времени</p>
    </div>
</body>
</html>
    <?php
    $htmlContent = ob_get_clean();

    if (!$saveToFile) {
        echo $htmlContent;
        exit;
    }

    return $htmlContent;
}

// Получение запросов на отчеты для создателя
function getReportRequests() {
    return loadJson('data/report_requests.json');
}

// Проверка, существует ли запрос на отчет
function reportRequestExists($journalId, $month, $year) {
    $requests = loadJson('data/report_requests.json');

    foreach ($requests as $request) {
        if ($request['journal_id'] === $journalId &&
            $request['month'] === $month &&
            $request['year'] === $year &&
            $request['status'] === 'pending') {
            return true;
        }
    }

    return false;
}

// Проверка, существует ли уже отчет
function reportExists($journalId, $month, $year) {
    $reports = loadJson('data/paper_reports.json');

    foreach ($reports as $report) {
        if ($report['journal_id'] === $journalId &&
            $report['month'] === $month &&
            $report['year'] === $year) {
            return true;
        }
    }

    return false;
}

// Получение количества запросов на отчеты для создателя
function getReportRequestsCount() {
    $requests = loadJson('data/report_requests.json');
    $pendingCount = 0;

    foreach ($requests as $request) {
        if ($request['status'] === 'pending') {
            $pendingCount++;
        }
    }

    return $pendingCount;
}

// Добавление запроса на отчет
function addReportRequest($userId, $journalId, $month, $year) {
    $requests = loadJson('data/report_requests.json');

    // Проверка, существует ли уже запрос
    if (reportRequestExists($journalId, $month, $year)) {
        return false;
    }

    // Проверка, существует ли уже отчет
    if (reportExists($journalId, $month, $year)) {
        return false;
    }

    $newRequest = [
        'id' => generateId(),
        'user_id' => $userId,
        'journal_id' => $journalId,
        'month' => $month,
        'year' => $year,
        'status' => 'pending',
        'requested_at' => getClientAdjustedTime(),
        'processed_at' => null,
        'processed_by' => null
    ];

    $requests[] = $newRequest;
    saveJson('data/report_requests.json', $requests);

    return true;
}

// Обновление статуса запроса на отчет
function updateReportRequestStatus($requestId, $status, $processedBy) {
    $requests = loadJson('data/report_requests.json');
    $updated = false;

    foreach ($requests as &$request) {
        if ($request['id'] === $requestId) {
            $request['status'] = $status;
            $request['processed_at'] = getClientAdjustedTime();
            $request['processed_by'] = $processedBy;
            $updated = true;
            break;
        }
    }

    if ($updated) {
        saveJson('data/report_requests.json', $requests);
        return true;
    }

    return false;
}

// Получение отклоненных запросов для пользователя
function getRejectedRequests($userId) {
    $requests = loadJson('data/report_requests.json');
    $rejected = [];

    foreach ($requests as $request) {
        if ($request['user_id'] === $userId && $request['status'] === 'rejected') {
            $rejected[] = $request;
        }
    }

    // Сортировка по времени (последние в начале)
    usort($rejected, function($a, $b) {
        return $b['processed_at'] - $a['processed_at'];
    });

    return $rejected;
}

// Получение количества запросов на восстановление дней
function getRecoveryRequestsCount() {
    $requests = loadJson('data/recovery_requests.json');
    $pendingCount = 0;

    foreach ($requests as $request) {
        if ($request['status'] === 'pending') {
            $pendingCount++;
        }
    }

    return $pendingCount;
}

// Получение запросов на восстановление для журнала
function getJournalRecoveryRequestsCount($journalId) {
    $requests = loadJson('data/recovery_requests.json');
    $count = 0;

    foreach ($requests as $request) {
        if ($request['journal_id'] === $journalId && $request['status'] === 'pending') {
            $count++;
        }
    }

    return $count;
}

// Проверка наличия ожидающих запросов на восстановление
function hasPendingRecoveryRequests() {
    $requests = loadJson('data/recovery_requests.json');

    foreach ($requests as $request) {
        if ($request['status'] === 'pending') {
            return true;
        }
    }

    return false;
}

// Создание нового запроса на восстановление дня
function createRecoveryRequest($userId, $journalId, $date, $comment = '') {
    $requests = loadJson('data/recovery_requests.json');

    // Проверяем, существует ли уже запрос на восстановление для этого дня
    foreach ($requests as $request) {
        if ($request['journal_id'] === $journalId && $request['date'] === $date && $request['status'] === 'pending') {
            return false;
        }
    }

    $newRequest = [
        'id' => generateId(),
        'user_id' => $userId,
        'journal_id' => $journalId,
        'date' => $date,
        'comment' => $comment,
        'status' => 'pending',
        'requested_at' => getClientAdjustedTime(),
        'processed_at' => null,
        'processed_by' => null,
        'helper_comment' => ''
    ];

    $requests[] = $newRequest;
    saveJson('data/recovery_requests.json', $requests);

    return true;
}

// Обработка запроса на восстановление дня
function processRecoveryRequest($requestId, $status, $processedBy, $data = []) {
    $requests = loadJson('data/recovery_requests.json');
    $updated = false;
    $requestData = null;

    foreach ($requests as &$request) {
        if ($request['id'] === $requestId) {
            $requestData = $request;
            $request['status'] = $status;
            $request['processed_at'] = getClientAdjustedTime();
            $request['processed_by'] = $processedBy;

            if ($status === 'completed' && isset($data['start_time'], $data['end_time'], $data['lunch_minutes'])) {
                $request['recovery_data'] = [
                    'start_time' => $data['start_time'],
                    'end_time' => $data['end_time'],
                    'lunch_minutes' => $data['lunch_minutes']
                ];

                if (isset($data['comment'])) {
                    $request['helper_comment'] = $data['comment'];
                }

                // Обновляем данные в журнале
                updateJournalEntry($request['journal_id'], $request['date'], $data);
            } elseif ($status === 'rejected' && isset($data['reason'])) {
                $request['reject_reason'] = $data['reason'];
            }

            $updated = true;
            break;
        }
    }

    if ($updated) {
        saveJson('data/recovery_requests.json', $requests);
        return true;
    }

    return false;
}

// Обновление данных в журнале
function updateJournalEntry($journalId, $date, $data) {
    $entriesFile = "data/entries/{$journalId}.json";
    $entries = loadJson($entriesFile);

    $entries[$date] = [
        'start_time' => $data['start_time'],
        'end_time' => $data['end_time'],
        'lunch_minutes' => $data['lunch_minutes'],
        'notes' => $data['comment'] ?? 'Восстановлено администратором',
        'is_day_off' => false
    ];

    saveJson($entriesFile, $entries);
    return true;
}

// Получение запросов на восстановление для журнала
function getRecoveryRequestsForJournal($journalId) {
    $requests = loadJson('data/recovery_requests.json');
    $journalRequests = [];

    foreach ($requests as $request) {
        if ($request['journal_id'] === $journalId) {
            $journalRequests[] = $request;
        }
    }

    return $journalRequests;
}

// Проверка доступа к вкладке
function hasTabAccess($userId, $tabName) {
    $tabPermissions = loadJson('data/tab_permissions.json');
    $user = getUserById($userId);

    if (!$user) {
        return false;
    }

    // Проверяем кастомные права
    if (isset($tabPermissions['custom'][$userId])) {
        return in_array($tabName, $tabPermissions['custom'][$userId]);
    }

    // Используем права по умолчанию
    return in_array($tabName, $tabPermissions['default'][$user['role']]);
}

// Централизованная проверка доступа
function checkAccess() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: index.php");
        exit();
    }

    $userId = $_SESSION['user_id'];
    $user = getUserById($userId);

    if (!$user) {
        header("Location: logout.php");
        exit();
    }

    return $user;
}

// Отрисовка меню
function renderMenu($userId, $userRole, $firstName, $lastName, $currentPage = '') {
    $unreadNotifications = getUnreadNotificationsCount($userId);
    $pendingReportsCount = ($userRole === 'creator') ? getReportRequestsCount() : 0;

    echo '<div class="sidebar">';
    echo '<div class="sidebar-header">';
    echo '<h2>Единый портал <small>mwj-2v5</small></h2>';
    echo '<p>' . htmlspecialchars($lastName . ' ' . $firstName) . ' (' . htmlspecialchars($userRole) . ')</p>';
    echo '</div>';
    echo '<div class="sidebar-menu">';
    echo '<ul>';

    // Добавляем пункты меню в зависимости от прав доступа
    if (hasTabAccess($userId, 'all_journals')) {
        echo '<li><a href="dashboard.php?tab=all_journals" class="' . ($currentPage === 'all_journals' ? 'active' : '') . '"><i class="fas fa-book-open"></i> Все журналы</a></li>';
    }
    if (hasTabAccess($userId, 'my_journals')) {
        echo '<li><a href="dashboard.php?tab=my_journals" class="' . ($currentPage === 'my_journals' ? 'active' : '') . '"><i class="fas fa-book"></i> Мои журналы</a></li>';
    }
    if (hasTabAccess($userId, 'shifts_calendar')) {
        echo '<li><a href="shifts_calendar.php" class="' . ($currentPage === 'shifts_calendar' ? 'active' : '') . '"><i class="fas fa-calendar-alt"></i> Календарь смен</a></li>';
    }
    if (hasTabAccess($userId, 'notifications')) {
        echo '<li><a href="notifications.php" class="' . ($currentPage === 'notifications' ? 'active' : '') . '">';
        echo '<i class="fas fa-bell"></i> Уведомления';
        if ($unreadNotifications > 0) {
            echo '<span class="badge badge-warning">' . $unreadNotifications . '</span>';
        }
        echo '</a></li>';
    }
    if (hasTabAccess($userId, 'paper_reports')) {
        echo '<li><a href="paper_reports.php" class="' . ($currentPage === 'paper_reports' ? 'active' : '') . '">';
        echo '<i class="fas fa-file-alt"></i> Бумажный отчёт';
        if ($userRole === 'creator' && $pendingReportsCount > 0) {
            echo '<span class="badge badge-warning">' . $pendingReportsCount . '</span>';
        }
        echo '</a></li>';
    }
    if (hasTabAccess($userId, 'backup_journals')) {
        echo '<li><a href="backup_journals.php" class="' . ($currentPage === 'backup_journals' ? 'active' : '') . '"><i class="fas fa-save"></i> Бэкапы</a></li>';
    }
    if (hasTabAccess($userId, 'activity_log')) {
        echo '<li><a href="dashboard.php?tab=activity_log" class="' . ($currentPage === 'activity_log' ? 'active' : '') . '"><i class="fas fa-history"></i> Журнал активности</a></li>';
    }
    if (hasTabAccess($userId, 'scheduler')) {
        echo '<li><a href="scheduler.php" class="' . ($currentPage === 'scheduler' ? 'active' : '') . '"><i class="fas fa-clock"></i> Планировщик</a></li>';
    }
    if (hasTabAccess($userId, 'users')) {
        echo '<li><a href="users.php" class="' . ($currentPage === 'users' ? 'active' : '') . '"><i class="fas fa-users"></i> Пользователи</a></li>';
    }
    if (hasTabAccess($userId, 'access_keys')) {
        echo '<li><a href="access_keys.php" class="' . ($currentPage === 'access_keys' ? 'active' : '') . '"><i class="fas fa-key"></i> Ключи доступа</a></li>';
    }
    if ($userRole === 'creator') {
        echo '<li><a href="manage_tabs.php" class="' . ($currentPage === 'manage_tabs' ? 'active' : '') . '"><i class="fas fa-user-lock"></i> Разрешения</a></li>';
    }
    if (hasTabAccess($userId, 'updates')) {
        echo '<li><a href="updates.php" class="' . ($currentPage === 'updates' ? 'active' : '') . '"><i class="fas fa-clock-rotate-left"></i> Обновления</a></li>';
    }
    if (hasTabAccess($userId, 'server_info_files')) {
        echo '<li><a href="server_info_files.php" class="' . ($currentPage === 'server_info_files' ? 'active' : '') . '"><i class="fas fa-server"></i> Файловая система</a></li>';
    }
    if (hasTabAccess($userId, 'passwords')) {
        echo '<li><a href=passwords.php class="' . ($currentPage === 'passwords' ? 'active' : '') . '"><i class="fas fa-server"></i> Мои пароли</a></li>';
    }
    if (hasTabAccess($userId, 'messenger')) {
        echo '<li><a href=messenger.php class="' . ($currentPage === 'messenger' ? 'active' : '') . '"><i class="fa-solid fa-envelope"></i> Мессенджер 2v5</a></li>';
    }
    if ($userRole === 'creator') {
        echo '<li><a href="retro_admin.php" class="' . ($currentPage === 'retro_admin' ? 'active' : '') . '"><i class="fas fa-terminal"></i> Ретро панель</a></li>';
    }

    echo '<li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Выход</a></li>';
    echo '</ul>';
    echo '</div>';
    echo '</div>';
}

// Установка кастомных прав доступа к вкладкам
function setCustomTabPermissions($userId, $tabs) {
    $tabPermissions = loadJson('data/tab_permissions.json');
    $tabPermissions['custom'][$userId] = $tabs;
    saveJson('data/tab_permissions.json', $tabPermissions);
}

// Получение всех доступных вкладок
function getAllTabs() {
    return [
        'Основные права' => [
            'access' => 'Доступ к сайту'
        ],
        'Журналы' => [
            'my_journals' => 'Мои журналы',
            'all_journals' => 'Все журналы',
            'all_journals_edit' => 'Редактирование',
            'all_journals_delete' => 'Удаление'
        ],
        'Уведомления' => [
            'notifications' => 'Доступ к службе',
            'notifications_manage' => 'Управление',
            'notifications_service_files' => 'Служба файловой системы',
            'notifications_service_mesengers' => 'Служба сообщений'
        ],
        'Смены' => [
            'shifts_calendar' => 'Доступ к службе',
            'shifts_calendar_assign' => 'Назначение смен'
        ],
        'Бумажные отчеты' => [
            'paper_reports' => 'Доступ к службе',
            'paper_reports_manage' => 'Управление отчетами',
            'paper_reports_approve' => 'Подтверждение отчетов'
        ],
        'Управление пользователями' => [
            'users' => 'Доступ к службе',
            'users_create' => 'Создание',
            'users_edit' => 'Редактирование',
            'users_delete' => 'Удаление'
        ],
        'Бэкапы журналов' => [
            'backup_journals' => 'Доступ к службе',
            'backup_journals_create' => 'Создание',
            'backup_journals_restore' => 'Восстановление'
        ],
        'Последняя активность' => [
            'activity_log' => 'Доступ к службе',
            'activity_log_export' => 'Экспорт журнала'
        ],
        'Планировщик' => [
            'scheduler' => 'Доступ к службе',
            'scheduler_manage' => 'Управление задачами'
        ],
        'Ключи доступа' => [
            'access_keys' => 'Доступ к службе',
            'access_keys_manage' => 'Управление ключами'
        ],
        'Инвентаризация' => [
            'inventory' => 'Доступ к службе',
            'inventory_create' => 'Добавление',
            'inventory_edit' => 'Редактирование',
            'inventory_delete' => 'Удаление'
        ],
        'Обновления' => [
            'updates' => 'Доступ к службе',
            'updates_create' => 'Создание',
            'updates_edit' => 'Редактирование',
            'updates_delete' => 'Удаление'
        ],
        'Файловая система' => [
            'server_info_files' => 'Доступ к службе'
        ],
        'Мои пароли' => [
            'passwords' => 'Доступ к службе'
        ],
        'Мессенджер' => [
            'messenger' => 'Доступ к службе',
            'messenger_input' => 'Входящие сообщения',
            'messenger_output' => 'Исходящие сообщения',
            'messenger_create' => 'Создание чатов',
            'messenger_manage' => 'Управление чатами',
            'messenger_delete' => 'Удаление чатов'
        ]
    ];
}

// Функции для работы с инвентаризацией
function getInventoryItems() {
    return loadJson('data/inventory.json');
}

function saveInventoryItem($data) {
    $items = getInventoryItems();
    $data['id'] = generateId();
    $data['created_at'] = getClientAdjustedTime();
    $data['updated_at'] = getClientAdjustedTime();

    $items[] = $data;
    saveJson('data/inventory.json', $items);
    return $data['id'];
}

function updateInventoryItem($id, $data) {
    $items = getInventoryItems();
    foreach ($items as &$item) {
        if ($item['id'] === $id) {
            $item = array_merge($item, $data);
            $item['updated_at'] = getClientAdjustedTime();
            break;
        }
    }
    saveJson('data/inventory.json', $items);
}

function deleteInventoryItem($id) {
    $items = getInventoryItems();
    $items = array_filter($items, function($item) use ($id) {
        return $item['id'] !== $id;
    });
    saveJson('data/inventory.json', array_values($items));
}

// Получение количества непрочитанных уведомлений
function getUnreadNotificationsCount($userId) {
    $notifications = loadJson('data/notifications.json');
    $count = 0;

    foreach ($notifications as $notification) {
        if ($notification['user_id'] === $userId && !$notification['is_read']) {
            $count++;
        }
    }

    return $count;
}

// Получение назначенных смен для пользователя
function getUserAssignedShifts($userId, $fromDate = null, $toDate = null) {
    $journals = getUserJournals($userId);
    $assignedShifts = [];

    foreach ($journals as $journal) {
        $entriesFile = "data/entries/{$journal['id']}.json";
        $entries = loadJson($entriesFile);

        foreach ($entries as $date => $entry) {
            if (isset($entry['is_assigned']) && $entry['is_assigned']) {
                // Если установлен диапазон дат, проверяем вхождение
                if ($fromDate && $date < $fromDate) {
                    continue;
                }
                if ($toDate && $date > $toDate) {
                    continue;
                }

                $assignedShifts[$date] = [
                    'journal_id' => $journal['id'],
                    'journal_title' => $journal['title'],
                    'entry' => $entry
                ];
            }
        }
    }

    return $assignedShifts;
}

// Проверка наличия назначенных смен у пользователя
function hasAssignedShifts($userId) {
    $journals = getUserJournals($userId);

    foreach ($journals as $journal) {
        $entriesFile = "data/entries/{$journal['id']}.json";
        $entries = loadJson($entriesFile);

        foreach ($entries as $entry) {
            if (isset($entry['is_assigned']) && $entry['is_assigned']) {
                return true;
            }
        }
    }

    return false;
}

// Получение ближайших назначенных смен
function getUpcomingShifts($userId, $daysAhead = 7) {
    $today = getClientDate();
    $endDate = date('Y-m-d', strtotime("+{$daysAhead} days", getClientAdjustedTime()));

    return getUserAssignedShifts($userId, $today, $endDate);
}
?>
