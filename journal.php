<?php
session_start();
require_once    'functions.php';

$user = checkAccess();
$userId = $user['id'];
$userRole = $user['role'];
// $username = $_SESSION['username'];
$firstName = $user['first_name'];
$lastName = $user['last_name'];

if (!hasTabAccess($userId, 'my_journals')) {
    header("Location: dashboard.php");
    exit();
}

// Получаем ID журнала из GET-параметра
$journalId = $_GET['id'] ?? '';
if (empty($journalId)) {
    header("Location: dashboard.php");
    exit();
}

// Проверяем права доступа к журналу
if (!canAccessJournal($journalId, $userId, $userRole)) {
    header("Location: dashboard.php?error=access_denied");
    exit();
}

// Загружаем данные журнала
$journals = loadJson('data/journals.json');
$journalData = null;
foreach ($journals as $journal) {
    if ($journal['id'] == $journalId) {
        $journalData = $journal;
        break;
    }
}

if (!$journalData) {
    header("Location: dashboard.php?error=not_found");
    exit();
}

// Получаем данные о владельце журнала
$journalOwner = getUserById($journalData['user_id']);
$ownerFirstName = $journalOwner ? $journalOwner['first_name'] : '';
$ownerMiddleName = $journalOwner ? ($journalOwner['middle_name'] ?? '') : '';
$ownerLastName = $journalOwner ? $journalOwner['last_name'] : '';
$journalOwnerName = trim("$ownerLastName $ownerFirstName $ownerMiddleName");

// Получаем список администраторов для установки доступа
$admins = getAllAdmins();

// Текущий месяц и год
$currentMonth = isset($_GET['month']) ? (int)$_GET['month'] : getCurrentMonth();
$year = $journalData['year'];

// Загружаем данные записей для текущего журнала
$entriesFile = "data/entries/{$journalId}.json";
$entries = loadJson($entriesFile);

// Получаем выбранный день для редактирования
$selectedDay = isset($_GET['day']) ? (int)$_GET['day'] : null;

// Текущая дата для выделения сегодняшнего дня
$today = date('Y-m-d');

// Проверяем, есть ли запросы на восстановление для выбранного дня
$recoveryRequests = loadJson('data/recovery_requests.json');
$dayRecoveryRequest = null;
$selectedDate = $selectedDay ? sprintf("%04d-%02d-%02d", $year, $currentMonth, $selectedDay) : null;

if ($selectedDate) {
    foreach ($recoveryRequests as $request) {
        if ($request['journal_id'] === $journalId && $request['date'] === $selectedDate) {
            $dayRecoveryRequest = $request;
            break;
        }
    }
}

// Подсчитываем количество ожидающих запросов на восстановление для этого журнала
$pendingRecoveryCount = 0;
if ($userRole === 'creator' || $userRole === 'admin') {
    foreach ($recoveryRequests as $request) {
        if ($request['journal_id'] === $journalId && $request['status'] === 'pending') {
            $pendingRecoveryCount++;
        }
    }
}

// Проверяем, имеются ли назначенные смены для этого журнала
$hasAssignedShifts = false;
foreach ($entries as $entry) {
    if (isset($entry['is_assigned']) && $entry['is_assigned']) {
        $hasAssignedShifts = true;
        break;
    }
}

// Получаем непрочитанные уведомления
$unreadNotifications = getUnreadNotificationsCount($userId);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Рабочий журнал - <?= htmlspecialchars($journalData['title']) ?></title>
    <meta name="description" content="Built with jdoodle.ai - Система учета рабочего времени с журналами по месяцам">
    <meta property="og:title" content="Рабочий журнал - Просмотр журнала">
    <meta property="og:description" content="Built with jdoodle.ai - Удобная система учета рабочего времени с возможностью экспорта в Excel">
    <meta property="og:image" content="https://images.unsplash.com/photo-1551434678-e076c223a692?ixid=M3w3MjUzNDh8MHwxfHNlYXJjaHw5fHxtb2Rlcm4lMjBvZmZpY2UlMjB3b3Jrc3BhY2UlMjB0ZWFtd29ya3xlbnwwfHx8fDE3NDI5MjM1OTV8MA&ixlib=rb-4.0.3&fit=fillmax&h=450&w=800">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="dashboard">

        <?php renderMenu($userId, $userRole, $firstName, $lastName); ?>

        <div class="main-content">
            <div class="header">
                <h1>
                    <?= htmlspecialchars($journalData['title']) ?> (<?= $year ?>)
                    <?php if ($pendingRecoveryCount > 0): ?>
                        <span class="recovery-notification-badge journal-badge" title="<?= $pendingRecoveryCount ?> запросов на восстановление">
                            <?= $pendingRecoveryCount ?>
                        </span>
                    <?php endif; ?>
                </h1>
                <div>
                    <a href="dashboard.php" class="btn">Назад</a>
                    <?php if ($userRole === 'creator'): ?>
                    <button class="btn btn-primary" onclick="openEditJournalModal('<?= $journalId ?>', '<?= htmlspecialchars($journalData['title']) ?>', '<?= $journalData['year'] ?>')">Редактировать</button>
                    <button class="btn btn-success" onclick="openExportModal()">Экспорт</button>
                    <button class="btn btn-primary" onclick="openModal('manage-access-modal')">Управление доступом</button>
                    <button class="btn btn-danger" onclick="if(confirm('Вы уверены, что хотите удалить этот журнал?')) window.location.href='delete_journal.php?id=<?= $journalId ?>'">Удалить журнал</button>
                    <?php endif; ?>
                    
                    <?php if ($userRole === 'creator' || $userRole === 'admin'): ?>
                    <button class="btn btn-primary" onclick="openAssignShiftModal()">Назначить смену</button>
                    <?php endif; ?>
                </div>
            </div>
            
            <p><strong>Пользователь:</strong> <?= htmlspecialchars($journalOwnerName) ?></p>
            
            <?php if ($userRole === 'user' && $hasAssignedShifts): ?>
            <div class="shift-notification">
                <i class="fas fa-calendar-check"></i>
                <div class="notification-content">
                    <h3>У вас есть назначенные смены</h3>
                    <p>Администратор назначил для вас смены в этом журнале. Проверьте календарь смен.</p>
                    <div class="notification-actions">
                        <a href="shifts_calendar.php" class="btn btn-sm">Перейти к календарю</a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Вкладки месяцев -->
            <div class="month-tabs">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <a href="?id=<?= $journalId ?>&month=<?= $m ?>" class="month-tab <?= $m == $currentMonth ? 'active' : '' ?>">
                        <?= getRussianMonth($m) ?>
                    </a>
                <?php endfor; ?>
            </div>
            
            <!-- Календарь месяца -->
            <div class="calendar-preview">
                <?php
                $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $currentMonth, $year);
                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $dayKey = sprintf("%04d-%02d-%02d", $year, $currentMonth, $day);
                    $dayData = $entries[$dayKey] ?? null;
                    
                    $isDayOff = isset($dayData['is_day_off']) && $dayData['is_day_off'] === true;
                    $isAssigned = isset($dayData['is_assigned']) && $dayData['is_assigned'] === true;
                    
                    $hours = 0;
                    if ($dayData && !$isDayOff) {
                        $hours = calculateHours($dayData['start_time'], $dayData['end_time'], $dayData['lunch_minutes']);
                    }
                    
                    $color = $isDayOff ? 'day-off' : getDayColor($hours);
                    $isSelected = $selectedDay === $day;
                    
                    // Выделяем сегодняшний день
                    $isToday = ($dayKey === $today);
                    $todayClass = $isToday ? ' today' : '';
                    
                    // Добавляем класс для назначенных смен
                    $assignedClass = $isAssigned ? ' assigned-day' : '';
                    
                    // Проверяем, есть ли запрос на восстановление для этого дня и его статус
                    $recoveryClass = '';
                    foreach ($recoveryRequests as $request) {
                        if ($request['journal_id'] === $journalId && $request['date'] === $dayKey) {
                            switch ($request['status']) {
                                case 'pending':
                                    $recoveryClass = ' recovery-requested';
                                    break;
                                case 'completed':
                                    $recoveryClass = ' recovery-requested recovery-completed';
                                    break;
                                case 'rejected':
                                    $recoveryClass = ' recovery-requested recovery-rejected';
                                    break;
                            }
                            break;
                        }
                    }
                    
                    $dayContent = $isDayOff ? '<span class="day-off-letter">В</span>' : $day;
                    $weekday = date('N', strtotime("{$year}-{$currentMonth}-{$day}"));
                    $weekdayNames = ['пн', 'вт', 'ср', 'чт', 'пт', 'сб', 'вс'];
                    $weekdayLabel = $weekdayNames[$weekday - 1];
                    $weekdayClass = ($weekday >= 6) ? 'weekend' : 'workday';
                    
                    echo "<a href=\"?id={$journalId}&month={$currentMonth}&day={$day}\" 
                             class=\"day-circle {$color}" . ($isSelected ? ' selected' : '') . $todayClass . $recoveryClass . $assignedClass . "\">
                             {$dayContent}
                             <span class=\"weekday-label {$weekdayClass}\">{$weekdayLabel}</span>
                         </a>";
                }
                ?>
            </div>
            
            <?php if ($selectedDay): ?>
                <?php
                $selectedDate = sprintf("%04d-%02d-%02d", $year, $currentMonth, $selectedDay);
                $dayData = $entries[$selectedDate] ?? [
                    'start_time' => '',
                    'end_time' => '',
                    'lunch_minutes' => 0,
                    'notes' => '',
                    'is_day_off' => false,
                    'is_assigned' => false
                ];
                
                // Проверка, является ли день выходным
                $isDayOff = isset($dayData['is_day_off']) && $dayData['is_day_off'] === true;
                
                // Проверка, является ли день назначенным
                $isAssigned = isset($dayData['is_assigned']) && $dayData['is_assigned'] === true;
                $assignedBy = null;
                
                if ($isAssigned && isset($dayData['assigned_by'])) {
                    $assignedBy = getUserById($dayData['assigned_by']);
                }
                ?>
                <!-- Редактор дня -->
                <div class="day-editor">
                    <h3><?= $selectedDay ?> <?= getRussianMonth($currentMonth) ?> <?= $year ?></h3>
                    
                    <?php if ($isAssigned): ?>
                        <div class="recovery-notice" style="background-color: #e3f2fd; border-left-color: var(--primary-color);">
                            <i class="fas fa-calendar-check" style="color: var(--primary-color);"></i>
                            <span>
                                Эта смена была назначена
                                <?php if ($assignedBy): ?>
                                    пользователем <?= htmlspecialchars($assignedBy['last_name'] . ' ' . $assignedBy['first_name']) ?>
                                <?php else: ?>
                                    администратором
                                <?php endif; ?>
                                <?= date('d.m.Y H:i', $dayData['assigned_at']) ?>.
                            </span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($dayRecoveryRequest): ?>
                        <div class="recovery-notice <?= $dayRecoveryRequest['status'] ?>">
                            <i class="fas fa-info-circle"></i>
                            <?php if ($dayRecoveryRequest['status'] === 'pending'): ?>
                                <span>Запрос на восстановление данных отправлен и ожидает обработки.</span>
                                <?php if ($userRole === 'creator' || ($userRole === 'admin' && in_array($userId, $journalData['admin_access'] ?? []))): ?>
                                    <div class="recovery-actions">
                                        <a href="recovery_details.php?id=<?= $dayRecoveryRequest['id'] ?>" class="btn btn-info btn-sm">Подробнее</a>
                                        <button class="btn btn-success btn-sm" onclick="openRecoveryHelpModal('<?= $dayRecoveryRequest['id'] ?>')">Помочь</button>
                                        <a href="cancel_recovery.php?id=<?= $dayRecoveryRequest['id'] ?>" class="btn btn-danger btn-sm">Отклонить</a>
                                    </div>
                                <?php endif; ?>
                            <?php elseif ($dayRecoveryRequest['status'] === 'completed'): ?>
                                <span>Данные были восстановлены администратором.</span>
                                <div class="recovery-actions">
                                    <a href="recovery_details.php?id=<?= $dayRecoveryRequest['id'] ?>" class="btn btn-info btn-sm">Подробнее</a>
                                </div>
                            <?php elseif ($dayRecoveryRequest['status'] === 'rejected'): ?>
                                <span>Запрос на восстановление был отклонен.</span>
                                <div class="recovery-actions">
                                    <a href="recovery_details.php?id=<?= $dayRecoveryRequest['id'] ?>" class="btn btn-info btn-sm">Подробнее</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form action="save_entry.php" method="post">
                        <input type="hidden" name="journal_id" value="<?= $journalId ?>">
                        <input type="hidden" name="date" value="<?= $selectedDate ?>">
                        
                        <div class="day-off-toggle">
                            <label>
                                <input type="checkbox" name="is_day_off" id="is-day-off" value="1" <?= $isDayOff ? 'checked' : '' ?> onchange="toggleDayOffFields()">
                                <span class="day-off-label">Выходной день</span>
                            </label>
                        </div>
                        
                        <div id="work-day-fields" class="<?= $isDayOff ? 'hidden' : '' ?>">
                            <div class="day-form">
                                <div class="form-group">
                                    <label for="start_time">Начало смены</label>
                                    <div class="time-input-group">
                                        <input type="time" id="start_time" name="start_time" value="<?= htmlspecialchars($dayData['start_time']) ?>" class="<?= isset($dayData['inaccurate_fields']['start_time']) ? 'inaccurate' : '' ?>">
                                        <label class="inaccurate-checkbox">
                                            <input type="checkbox" name="inaccurate_fields[start_time]" <?= isset($dayData['inaccurate_fields']['start_time']) ? 'checked' : '' ?>>
                                            <span class="inaccurate-label">Неточно</span>
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="end_time">Конец смены</label>
                                    <div class="time-input-group">
                                        <input type="time" id="end_time" name="end_time" value="<?= htmlspecialchars($dayData['end_time']) ?>" class="<?= isset($dayData['inaccurate_fields']['end_time']) ? 'inaccurate' : '' ?>">
                                        <label class="inaccurate-checkbox">
                                            <input type="checkbox" name="inaccurate_fields[end_time]" <?= isset($dayData['inaccurate_fields']['end_time']) ? 'checked' : '' ?>>
                                            <span class="inaccurate-label">Неточно</span>
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="lunch_minutes">Обед (мин)</label>
                                    <div class="time-input-group">
                                        <input type="number" id="lunch_minutes" name="lunch_minutes" value="<?= (int)$dayData['lunch_minutes'] ?>" min="0" class="<?= isset($dayData['inaccurate_fields']['lunch_minutes']) ? 'inaccurate' : '' ?>">
                                        <label class="inaccurate-checkbox">
                                            <input type="checkbox" name="inaccurate_fields[lunch_minutes]" <?= isset($dayData['inaccurate_fields']['lunch_minutes']) ? 'checked' : '' ?>>
                                            <span class="inaccurate-label">Неточно</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="notes">Примечания</label>
                            <textarea id="notes" name="notes" rows="2"><?= htmlspecialchars($dayData['notes'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-success">Сохранить</button>
                            <?php if ($journalData['user_id'] == $userId && !$dayRecoveryRequest && !$isDayOff): ?>
                            <button type="button" class="btn btn-warning" onclick="openRecoveryRequestModal('<?= $journalId ?>', '<?= $selectedDate ?>')">Запросить восстановление</button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
            
            <!-- Таблица с данными за месяц -->
            <h3>Данные за <?= getRussianMonth($currentMonth) ?></h3>
            <table>
                <thead>
                    <tr>
                        <th>Дата</th>
                        <th>Статус</th>
                        <th>Начало</th>
                        <th>Конец</th>
                        <th>Обед (мин)</th>
                        <th>Часы</th>
                        <th>Примечания</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $totalHours = 0;
                    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $currentMonth, $year);
                    
                    for ($day = 1; $day <= $daysInMonth; $day++) {
                        $dayKey = sprintf("%04d-%02d-%02d", $year, $currentMonth, $day);
                        $dayData = $entries[$dayKey] ?? null;
                        
                        $isDayOff = isset($dayData['is_day_off']) && $dayData['is_day_off'] === true;
                        $isAssigned = isset($dayData['is_assigned']) && $dayData['is_assigned'] === true;
                        
                        $startTime = $isDayOff ? '-' : ($dayData['start_time'] ?? '');
                        $endTime = $isDayOff ? '-' : ($dayData['end_time'] ?? '');
                        $lunchMinutes = $isDayOff ? '-' : ($dayData['lunch_minutes'] ?? 0);
                        $notes = $dayData['notes'] ?? '';
                        
                        $hours = $isDayOff ? 0 : calculateHours($startTime, $endTime, $lunchMinutes);
                        if (!$isDayOff) {
                            $totalHours += $hours;
                        }
                        
                        // Проверяем, это сегодняшний день?
                        $isTodayRow = ($dayKey === $today) ? ' class="today-row"' : '';
                        
                        // Проверяем, есть ли запрос на восстановление для этого дня
                        $recoveryStatus = '';
                        $recoveryRequestId = null;
                        foreach ($recoveryRequests as $request) {
                            if ($request['journal_id'] === $journalId && $request['date'] === $dayKey) {
                                $recoveryRequestId = $request['id'];
                                if ($request['status'] === 'pending') {
                                    $recoveryStatus = '<span class="recovery-badge pending">Запрос на восстановление</span>';
                                } elseif ($request['status'] === 'completed') {
                                    $recoveryStatus = '<span class="recovery-badge completed">Восстановлено</span>';
                                } elseif ($request['status'] === 'rejected') {
                                    $recoveryStatus = '<span class="recovery-badge rejected">Отклонено</span>';
                                }
                                break;
                            }
                        }
                        
                        // Дополнительные сведения для назначенных смен
                        $assignedInfo = '';
                        if ($isAssigned) {
                            $assignedInfo = '<span class="recovery-badge" style="background-color: #e3f2fd; color: var(--primary-color);">Назначено</span>';
                        }
                        
                        echo "<tr{$isTodayRow}>";
                        echo "<td>{$day} " . getRussianMonth($currentMonth) . "</td>";
                        echo "<td>" . ($isDayOff ? 
                                '<span class="day-status day-off-status">Выходной</span>' : 
                                '<span class="day-status work-day-status">Рабочий</span>') . 
                                ($isAssigned ? ' ' . $assignedInfo : '') . 
                             "</td>";
                        echo "<td>" . htmlspecialchars($startTime) . "</td>";
                        echo "<td>" . htmlspecialchars($endTime) . "</td>";
                        echo "<td>" . (is_numeric($lunchMinutes) ? (int)$lunchMinutes : $lunchMinutes) . "</td>";
                        echo "<td>" . ($isDayOff ? '-' : number_format($hours, 2)) . "</td>";
                        echo "<td>" . htmlspecialchars($notes) . "</td>";
                        echo "<td>";
                        if ($recoveryRequestId) {
                            echo "<a href=\"recovery_details.php?id={$recoveryRequestId}\" class=\"btn btn-sm btn-info\">Подробнее</a>";
                        }
                        echo "</td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="5">Всего часов:</th>
                        <th><?= number_format($totalHours, 2) ?></th>
                        <th></th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    
    <!-- Модальное окно редактирования журнала -->
    <?php if ($userRole === 'creator'): ?>
    <div id="edit-journal-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Редактировать журнал</h2>
                <button class="modal-close" onclick="closeModal('edit-journal-modal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="edit-journal-form" action="edit_journal.php" method="post">
                    <input type="hidden" id="edit-journal-id" name="journal_id">
                    <div class="form-group">
                        <label for="edit-journal-title">Название журнала</label>
                        <input type="text" id="edit-journal-title" name="title" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-journal-year">Год</label>
                        <input type="number" id="edit-journal-year" name="year" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-danger" onclick="closeModal('edit-journal-modal')">Отмена</button>
                <button class="btn btn-success" onclick="document.getElementById('edit-journal-form').submit()">Сохранить</button>
            </div>
        </div>
    </div>
    
    <!-- Модальное окно управления доступом -->
    <div id="manage-access-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Управление доступом администраторов</h2>
                <button class="modal-close" onclick="closeModal('manage-access-modal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="manage-access-form" action="manage_journal_access.php" method="post">
                    <input type="hidden" name="journal_id" value="<?= $journalId ?>">
                    <p>Выберите администраторов, которые будут иметь доступ к этому журналу:</p>
                    
                    <?php if (empty($admins)): ?>
                        <p>Нет доступных администраторов.</p>
                    <?php else: ?>
                        <div class="admin-list">
                            <?php foreach ($admins as $admin): ?>
                                <?php 
                                $hasAccess = isset($journalData['admin_access']) && in_array($admin['id'], $journalData['admin_access']);
                                $adminName = trim($admin['last_name'] . ' ' . $admin['first_name'] . ' ' . ($admin['middle_name'] ?? ''));
                                ?>
                                <div class="admin-item">
                                    <label>
                                        <input type="checkbox" name="admin_access[]" value="<?= $admin['id'] ?>" <?= $hasAccess ? 'checked' : '' ?>>
                                        <?= htmlspecialchars($adminName) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-danger" onclick="closeModal('manage-access-modal')">Отмена</button>
                <button class="btn btn-success" onclick="document.getElementById('manage-access-form').submit()">Сохранить</button>
            </div>
        </div>
    </div>
    
    <!-- Модальное окно экспорта -->
    <div id="export-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Экспорт журнала</h2>
                <button class="modal-close" onclick="closeModal('export-modal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="export-form" action="export_journal.php" method="get">
                    <input type="hidden" name="id" value="<?= $journalId ?>">
                    <input type="hidden" name="month" value="<?= $currentMonth ?>">
                    
                    <div class="form-group">
                        <label for="export-format">Формат экспорта</label>
                        <select id="export-format" name="format" required>
                            <option value="excel">Excel (CSV)</option>
                            <option value="html">HTML</option>
                            <option value="txt" selected>Текстовый файл (рекомендуется)</option>
                        </select>
                        <p class="hint">Текстовый формат (TXT) - самый проработанный и понятный</p>
                    </div>
                    
                    <div class="form-group">
                        <label for="hourly-rate">Почасовая ставка (руб/час)</label>
                        <input type="number" id="hourly-rate" name="hourly_rate" step="0.01" min="0">
                        <p class="hint">Оставьте пустым, если не требуется расчет оплаты</p>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-danger" onclick="closeModal('export-modal')">Отмена</button>
                <button class="btn btn-success" onclick="document.getElementById('export-form').submit()">Экспортировать</button>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Модальное окно назначения смены -->
    <?php if ($userRole === 'creator' || $userRole === 'admin'): ?>
    <div id="assign-shift-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Назначить смену</h2>
                <button class="modal-close" onclick="closeModal('assign-shift-modal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="assign-shift-form" action="assign_shift.php" method="post">
                    <input type="hidden" name="journal_id" value="<?= $journalId ?>">
                    
                    <div class="form-group">
                        <label for="assign-date">Дата смены</label>
                        <?php if ($selectedDay): ?>
                            <?php
                            // Проверка что выбранная дата не в прошлом
                            $isPastDate = $selectedDate < date('Y-m-d');
                            $minDate = date('Y-m-d');
                            $defaultDate = $isPastDate ? $minDate : $selectedDate;
                            ?>
                            <input type="date" id="assign-date" name="date" value="<?= $defaultDate ?>" min="<?= $minDate ?>" required>
                            <?php if ($isPastDate): ?>
                            <p class="hint" style="color: var(--danger-color);">Нельзя назначать смены на прошедшие даты.</p>
                            <?php endif; ?>
                        <?php else: ?>
                            <input type="date" id="assign-date" name="date" min="<?= date('Y-m-d') ?>" required>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="assign-start-time">Начало смены</label>
                        <input type="time" id="assign-start-time" name="start_time" value="09:00" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="assign-end-time">Конец смены</label>
                        <input type="time" id="assign-end-time" name="end_time" value="18:00" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="assign-lunch-minutes">Обед (мин)</label>
                        <input type="number" id="assign-lunch-minutes" name="lunch_minutes" value="60" min="0">
                    </div>
                    
                    <div class="form-group">
                        <label for="assign-notes">Примечания</label>
                        <textarea id="assign-notes" name="notes" rows="2" placeholder="Дополнительная информация о смене"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-danger" onclick="closeModal('assign-shift-modal')">Отмена</button>
                <button class="btn btn-success" onclick="document.getElementById('assign-shift-form').submit()">Назначить</button>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Модальное окно запроса на восстановление -->
    <div id="recovery-request-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Запрос на восстановление данных</h2>
                <button class="modal-close" onclick="closeModal('recovery-request-modal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="recovery-request-form" action="request_recovery.php" method="post">
                    <input type="hidden" id="recovery-journal-id" name="journal_id">
                    <input type="hidden" id="recovery-date" name="date">
                    
                    <p>Если вы не помните данные о рабочем дне, вы можете запросить помощь администратора для восстановления информации.</p>
                    
                    <div class="form-group">
                        <label for="recovery-comment">Комментарий (не обязательно)</label>
                        <textarea id="recovery-comment" name="comment" rows="3" placeholder="Укажите любую информацию, которая может помочь администратору восстановить данные"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-danger" onclick="closeModal('recovery-request-modal')">Отмена</button>
                <button class="btn btn-success" onclick="document.getElementById('recovery-request-form').submit()">Отправить запрос</button>
            </div>
        </div>
    </div>
    
    <!-- Модальное окно помощи с восстановлением -->
    <?php if ($userRole === 'creator' || $userRole === 'admin'): ?>
    <div id="recovery-help-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Помощь с восстановлением данных</h2>
                <button class="modal-close" onclick="closeModal('recovery-help-modal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="recovery-help-form" action="help_recovery.php" method="post">
                    <input type="hidden" id="recovery-request-id" name="request_id">
                    
                    <div class="form-group">
                        <label for="help-start-time">Начало смены</label>
                        <input type="time" id="help-start-time" name="start_time" required>
                    </div>
                    <div class="form-group">
                        <label for="help-end-time">Конец смены</label>
                        <input type="time" id="help-end-time" name="end_time" required>
                    </div>
                    <div class="form-group">
                        <label for="help-lunch-minutes">Обед (мин)</label>
                        <input type="number" id="help-lunch-minutes" name="lunch_minutes" value="60" min="0" required>
                    </div>
                    <div class="form-group">
                        <label for="help-comment">Комментарий (не обязательно)</label>
                        <textarea id="help-comment" name="comment" rows="2" placeholder="Добавьте комментарий при необходимости"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-danger" onclick="closeModal('recovery-help-modal')">Отмена</button>
                <button class="btn btn-success" onclick="document.getElementById('recovery-help-form').submit()">Сохранить и восстановить</button>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <script>
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
        
        function openEditJournalModal(journalId, title, year) {
            document.getElementById('edit-journal-id').value = journalId;
            document.getElementById('edit-journal-title').value = title;
            document.getElementById('edit-journal-year').value = year;
            openModal('edit-journal-modal');
        }
        
        function openExportModal() {
            document.getElementById('hourly-rate').value = '';
            openModal('export-modal');
        }
        
        function openRecoveryRequestModal(journalId, date) {
            document.getElementById('recovery-journal-id').value = journalId;
            document.getElementById('recovery-date').value = date;
            document.getElementById('recovery-comment').value = '';
            openModal('recovery-request-modal');
        }
        
        function openRecoveryHelpModal(requestId) {
            document.getElementById('recovery-request-id').value = requestId;
            document.getElementById('help-start-time').value = '09:00';
            document.getElementById('help-end-time').value = '18:00';
            document.getElementById('help-lunch-minutes').value = '60';
            document.getElementById('help-comment').value = '';
            openModal('recovery-help-modal');
        }
        
        function toggleDayOffFields() {
            const isDayOff = document.getElementById('is-day-off').checked;
            const workDayFields = document.getElementById('work-day-fields');
            
            if (isDayOff) {
                workDayFields.classList.add('hidden');
            } else {
                workDayFields.classList.remove('hidden');
            }
        }
        
        function openAssignShiftModal() {
            <?php if ($selectedDay): ?>
                <?php
                // Проверка что выбранная дата не в прошлом
                $isPastDate = $selectedDate < date('Y-m-d');
                $defaultDate = $isPastDate ? date('Y-m-d') : $selectedDate;
                ?>
                document.getElementById('assign-date').value = '<?= $defaultDate ?>';
                <?php if ($isPastDate): ?>
                alert('Нельзя назначать смены на прошедшие даты. Выбрана текущая дата.');
                <?php endif; ?>
            <?php else: ?>
                document.getElementById('assign-date').value = new Date().toISOString().split('T')[0];
            <?php endif; ?>
            document.getElementById('assign-start-time').value = '09:00';
            document.getElementById('assign-end-time').value = '18:00';
            document.getElementById('assign-lunch-minutes').value = '60';
            document.getElementById('assign-notes').value = '';
            openModal('assign-shift-modal');
        }
    </script>
</body>
</html>
 