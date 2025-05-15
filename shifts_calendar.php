<?php
session_start();
require_once   'functions.php';

$user = checkAccess();
$userId = $user['id'];
$userRole = $user['role'];
// $username = $_SESSION['username'];
$firstName = $user['first_name'];
$lastName = $user['last_name'];
if (!hasTabAccess($userId, 'shifts_calendar')) {
    header("Location: dashboard.php");
    exit();
}

if (!isset($_SESSION['first_name'])) {
    $firstName = 'n/F/N';
}else{
    $firstName = $_SESSION['first_name'];
}
if (!isset($_SESSION['last_name'])) {
    $lastName = 'n/L/N';
}else{
    $lastName = $_SESSION['last_name'];
}
if (!isset($_SESSION['middle_name'])) {
    $middleName = 'n/M/N';
}else{
    $middleName = $_SESSION['middle_name'];
}

// Получаем все журналы пользователя
$userJournals = getUserJournals($userId);

// Получаем журналы, к которым у пользователя есть доступ (для админа и создателя)
$accessibleJournals = [];
if ($userRole === 'creator' || $userRole === 'admin') {
    $allJournals = loadJson('data/journals.json');

    foreach ($allJournals as $journal) {
        // Создатель видит все журналы
        if ($userRole === 'creator') {
            $accessibleJournals[] = $journal;
        }
        // Администратор видит журналы, к которым у него есть доступ
        elseif ($userRole === 'admin' && 
                isset($journal['admin_access']) && 
                in_array($userId, $journal['admin_access'])) {
            $accessibleJournals[] = $journal;
        }
    }
}

// Получаем пользователей для выбора (только для создателя и администратора)
$users = [];
if ($userRole === 'creator' || $userRole === 'admin') {
    $allUsers = loadJson('data/users.json');

    foreach ($allUsers as $user) {
        // Фильтруем только тех пользователей, к журналам которых есть доступ
        $hasAccess = false;

        if ($userRole === 'creator') {
            $hasAccess = true; // Создатель имеет доступ ко всем
        } elseif ($userRole === 'admin') {
            foreach ($accessibleJournals as $journal) {
                if ($journal['user_id'] === $user['id']) {
                    $hasAccess = true;
                    break;
                }
            }
        }

        if ($hasAccess) {
            $users[$user['id']] = [
                'id' => $user['id'],
                'name' => "{$user['last_name']} {$user['first_name']} {$user['middle_name']}",
                'role' => $user['role']
            ];
        }
    }
}

// Получаем текущий год и месяц
$currentYear = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$currentMonth = isset($_GET['month']) ? (int)$_GET['month'] : date('n');

// Получаем количество дней в текущем месяце
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $currentMonth, $currentYear);

// Получаем день недели для первого дня месяца (0 - воскресенье, 1 - понедельник, и т.д.)
$firstDayOfMonth = date('w', strtotime("{$currentYear}-{$currentMonth}-01"));
// Корректируем для отображения недели с понедельника (1 - понедельник, ..., 7 - воскресенье)
$firstDayOfMonth = ($firstDayOfMonth === 0) ? 7 : $firstDayOfMonth;

// Получаем текущую дату для выделения сегодняшнего дня
$today = date('Y-m-d');

// Получаем все назначенные смены для отображения
$allEntries = [];

// Для обычного пользователя загружаем только его журналы
if ($userRole === 'user') {
    foreach ($userJournals as $journal) {
        $entriesFile = "data/entries/{$journal['id']}.json";
        $entries = loadJson($entriesFile);

        foreach ($entries as $date => $entry) {
            if (isset($entry['is_assigned']) && $entry['is_assigned']) {
                $allEntries[$date][] = [
                    'journal_id' => $journal['id'],
                    'journal_title' => $journal['title'],
                    'entry' => $entry
                ];
            }
        }
    }
}
// Для создателя и администратора загружаем доступные журналы
else {
    foreach ($accessibleJournals as $journal) {
        $entriesFile = "data/entries/{$journal['id']}.json";
        $entries = loadJson($entriesFile);

        foreach ($entries as $date => $entry) {
            if (isset($entry['is_assigned']) && $entry['is_assigned']) {
                $allEntries[$date][] = [
                    'journal_id' => $journal['id'],
                    'journal_title' => $journal['title'],
                    'user_id' => $journal['user_id'],
                    'entry' => $entry
                ];
            }
        }
    }
}

// Получаем непрочитанные уведомления для пользователя
$notifications = loadJson('data/notifications.json');
$unreadNotifications = 0;

foreach ($notifications as $notification) {
    if ($notification['user_id'] === $userId && !$notification['is_read']) {
        $unreadNotifications++;
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Рабочий журнал - Календарь смен</title>
    <meta name="description" content="Built with jdoodle.ai - Система учета рабочего времени с календарем смен">
    <meta property="og:title" content="Рабочий журнал - Календарь смен">
    <meta property="og:description" content="Built with jdoodle.ai - Удобная система учета рабочего времени с возможностью назначения смен">
    <meta property="og:image" content="https://images.unsplash.com/photo-1499750310107-5fef28a66643?ixid=M3w3MjUzNDh8MHwxfHNlYXJjaHwxfHxidXNpbmVzcyUyMHNjaGVkdWxlJTIwY2FsZW5kYXIlMjBwbGFubmluZyUyMG9mZmljZXxlbnwwfHx8fDE3NDMxNjM3MTh8MA&ixlib=rb-4.0.3&fit=fillmax&h=450&w=800">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="dashboard">

        <?php renderMenu($userId, $userRole, $firstName, $lastName); ?>

        <div class="main-content">
            <div class="header">
                <h1>Календарь смен</h1>
                <?php if ($userRole === 'creator' || $userRole === 'admin'): ?>
                <button class="btn" onclick="openAssignShiftModal()">Назначить смену</button>
                <?php endif; ?>
            </div>

            <!-- Навигация по месяцам и годам -->
            <div class="calendar-navigation">
                <div class="month-year-selector">
                    <a href="?year=<?= $currentYear - 1 ?>&month=<?= $currentMonth ?>" class="btn-nav"><i class="fas fa-chevron-left"></i> Год</a>
                    <a href="?year=<?= $currentMonth == 1 ? $currentYear - 1 : $currentYear ?>&month=<?= $currentMonth == 1 ? 12 : $currentMonth - 1 ?>" class="btn-nav"><i class="fas fa-chevron-left"></i> Месяц</a>
                    <span class="current-period"><?= getRussianMonth($currentMonth) ?> <?= $currentYear ?></span>
                    <a href="?year=<?= $currentMonth == 12 ? $currentYear + 1 : $currentYear ?>&month=<?= $currentMonth == 12 ? 1 : $currentMonth + 1 ?>" class="btn-nav">Месяц <i class="fas fa-chevron-right"></i></a>
                    <a href="?year=<?= $currentYear + 1 ?>&month=<?= $currentMonth ?>" class="btn-nav">Год <i class="fas fa-chevron-right"></i></a>
                </div>
                <div class="today-btn">
                    <a href="?year=<?= date('Y') ?>&month=<?= date('n') ?>" class="btn">Сегодня</a>
                </div>
            </div>

            <!-- Календарь -->
            <div class="month-calendar">
                <div class="calendar-header">
                    <div class="weekday">Пн</div>
                    <div class="weekday">Вт</div>
                    <div class="weekday">Ср</div>
                    <div class="weekday">Чт</div>
                    <div class="weekday">Пт</div>
                    <div class="weekday weekend">Сб</div>
                    <div class="weekday weekend">Вс</div>
                </div>
                <div class="calendar-body">
                    <?php
                    // Заполняем пустые ячейки до первого дня месяца
                    for ($i = 1; $i < $firstDayOfMonth; $i++) {
                        echo '<div class="calendar-day empty"></div>';
                    }

                    // Заполняем дни месяца
                    for ($day = 1; $day <= $daysInMonth; $day++) {
                        $date = sprintf("%04d-%02d-%02d", $currentYear, $currentMonth, $day);
                        $isToday = ($date === $today);
                        $dayClass = $isToday ? 'calendar-day today' : 'calendar-day';

                        // Определяем, является ли день выходным (суббота или воскресенье)
                        $dayOfWeek = date('N', strtotime($date));
                        if ($dayOfWeek >= 6) { // 6 - суббота, 7 - воскресенье
                            $dayClass .= ' weekend';
                        }

                        // Проверяем наличие смен на этот день
                        $hasShifts = isset($allEntries[$date]) && !empty($allEntries[$date]);

                        echo '<div class="' . $dayClass . '">';
                        echo '<div class="day-number">' . $day . '</div>';

                        if ($hasShifts) {
                            echo '<div class="day-shifts">';

                            foreach ($allEntries[$date] as $shiftData) {
                                $shiftEntry = $shiftData['entry'];
                                $journalTitle = $shiftData['journal_title'];

                                // Для администратора и создателя показываем имя пользователя
                                $userName = '';
                                if (($userRole === 'creator' || $userRole === 'admin') && isset($shiftData['user_id'])) {
                                    $user = getUserById($shiftData['user_id']);
                                    if ($user) {
                                        $userName = "{$user['last_name']} {$user['first_name']}";
                                    }
                                }

                                echo '<div class="shift-item" onclick="openShiftDetails(\'' . $date . '\', \'' . $shiftData['journal_id'] . '\')">';
                                echo '<div class="shift-time">' . $shiftEntry['start_time'] . ' - ' . $shiftEntry['end_time'] . '</div>';

                                if (!empty($userName)) {
                                    echo '<div class="shift-user">' . htmlspecialchars($userName) . '</div>';
                                }

                                echo '<div class="shift-journal">' . htmlspecialchars($journalTitle) . '</div>';
                                echo '</div>';
                            }

                            echo '</div>';
                        }

                        echo '</div>';
                    }

                    // Заполняем пустые ячейки после последнего дня месяца
                    $lastDayOfMonth = date('N', strtotime("{$currentYear}-{$currentMonth}-{$daysInMonth}"));
                    for ($i = $lastDayOfMonth; $i < 7; $i++) {
                        echo '<div class="calendar-day empty"></div>';
                    }
                    ?>
                </div>
            </div>

            <!-- Легенда календаря -->
            <div class="calendar-legend">
                <div class="legend-item">
                    <div class="legend-color today"></div>
                    <div class="legend-label">Сегодня</div>
                </div>
                <div class="legend-item">
                    <div class="legend-color weekend"></div>
                    <div class="legend-label">Выходной день</div>
                </div>
                <div class="legend-item">
                    <div class="legend-color has-shift"></div>
                    <div class="legend-label">Назначена смена</div>
                </div>
            </div>
        </div>
    </div>

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
                    <div class="form-group">
                        <label for="user-journal">Выберите журнал пользователя</label>
                        <select id="user-journal" name="journal_id" required>
                            <option value="">-- Выберите журнал --</option>
                            <?php foreach ($accessibleJournals as $journal): 
                                // Получаем данные о владельце журнала
                                $owner = getUserById($journal['user_id']);
                                $ownerName = $owner ? "{$owner['last_name']} {$owner['first_name']}" : "Неизвестный пользователь";
                            ?>
                                <option value="<?= $journal['id'] ?>"><?= htmlspecialchars($journal['title']) ?> (<?= htmlspecialchars($ownerName) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="shift-date">Дата смены</label>
                        <input type="date" id="shift-date" name="date" min="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="start-time">Начало смены</label>
                        <input type="time" id="start-time" name="start_time" required>
                    </div>

                    <div class="form-group">
                        <label for="end-time">Конец смены</label>
                        <input type="time" id="end-time" name="end_time" required>
                    </div>

                    <div class="form-group">
                        <label for="lunch-minutes">Обед (мин)</label>
                        <input type="number" id="lunch-minutes" name="lunch_minutes" value="60" min="0">
                    </div>

                    <div class="form-group">
                        <label for="shift-notes">Примечания</label>
                        <textarea id="shift-notes" name="notes" rows="3" placeholder="Дополнительная информация о смене"></textarea>
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

    <!-- Модальное окно с деталями смены -->
    <div id="shift-details-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Информация о смене</h2>
                <button class="modal-close" onclick="closeModal('shift-details-modal')">&times;</button>
            </div>
            <div class="modal-body" id="shift-details-content">
                <!-- Содержимое будет добавлено через JavaScript -->
            </div>
        </div>
    </div>

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        function openAssignShiftModal() {
            // Устанавливаем текущую дату по умолчанию
            const today = new Date();
            document.getElementById('shift-date').value = today.toISOString().split('T')[0];
            document.getElementById('start-time').value = '09:00';
            document.getElementById('end-time').value = '18:00';
            openModal('assign-shift-modal');
        }

        function openShiftDetails(date, journalId) {
            // Здесь должен быть AJAX-запрос для получения детальной информации о смене
            // Для демонстрации просто загрузим данные, которые у нас уже есть

            const shifts = <?= json_encode($allEntries) ?>;
            const users = <?= json_encode($users) ?>;
            const userRole = "<?= $userRole ?>";

            let content = '';

            if (shifts[date]) {
                const shiftData = shifts[date].find(shift => shift.journal_id === journalId);

                if (shiftData) {
                    const entry = shiftData.entry;
                    const journalTitle = shiftData.journal_title;

                    // Форматируем дату для отображения
                    const dateObj = new Date(date);
                    const day = dateObj.getDate();
                    const month = dateObj.getMonth() + 1;
                    const year = dateObj.getFullYear();
                    const formattedDate = `${day} ${getRussianMonthGenitive(month)} ${year}`;

                    content += `<div class="shift-details">`;
                    content += `<div class="detail-row"><strong>Дата:</strong> ${formattedDate}</div>`;
                    content += `<div class="detail-row"><strong>Журнал:</strong> ${journalTitle}</div>`;

                    if (userRole === 'creator' || userRole === 'admin') {
                        const userData = users[shiftData.user_id] || {};
                        content += `<div class="detail-row"><strong>Сотрудник:</strong> ${userData.name || 'Неизвестный пользователь'}</div>`;
                    }

                    content += `<div class="detail-row"><strong>Начало смены:</strong> ${entry.start_time}</div>`;
                    content += `<div class="detail-row"><strong>Конец смены:</strong> ${entry.end_time}</div>`;
                    content += `<div class="detail-row"><strong>Обед:</strong> ${entry.lunch_minutes} мин.</div>`;

                    if (entry.notes) {
                        content += `<div class="detail-row"><strong>Примечания:</strong> ${entry.notes}</div>`;
                    }

                    // Кто назначил смену
                    if (entry.assigned_by) {
                        const assignerData = users[entry.assigned_by] || {};
                        const assignerName = assignerData.name || 'Неизвестный пользователь';
                        const assignedDate = new Date(entry.assigned_at * 1000);
                        const formattedAssignedDate = assignedDate.toLocaleDateString('ru-RU') + ' ' + assignedDate.toLocaleTimeString('ru-RU');

                        content += `<div class="detail-row"><strong>Назначил:</strong> ${assignerName}</div>`;
                        content += `<div class="detail-row"><strong>Дата назначения:</strong> ${formattedAssignedDate}</div>`;
                    }

                    // Если это администратор или создатель, добавляем кнопку редактирования
                    if (userRole === 'creator' || userRole === 'admin') {
                        content += `<div class="shift-actions">`;
                        content += `<a href="journal.php?id=${journalId}&month=${month}&day=${day}" class="btn">Открыть в журнале</a>`;
                        content += `</div>`;
                    }

                    content += `</div>`;
                } else {
                    content = '<p>Информация о смене не найдена.</p>';
                }
            } else {
                content = '<p>Информация о смене не найдена.</p>';
            }

            document.getElementById('shift-details-content').innerHTML = content;
            openModal('shift-details-modal');
        }

        // Функция для получения названия месяца в родительном падеже
        function getRussianMonthGenitive(month) {
            const months = [
                'Января', 'Февраля', 'Марта', 'Апреля', 'Мая', 'Июня',
                'Июля', 'Августа', 'Сентября', 'Октября', 'Ноября', 'Декабря'
            ];
            return months[month - 1];
        }
    </script>
</body>
</html>