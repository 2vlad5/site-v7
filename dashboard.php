<?php
session_start();
require_once   'functions.php';

$user = checkAccess();
$userId = $user['id'];
$userRole = $user['role'];
// $username = $_SESSION['username'];
$firstName = $user['first_name'];
$lastName = $user['last_name'];

if (!hasTabAccess($userId, 'access')) {
    echo('Ваш доступ к системе прекращён, обратитесь к создателю сайта');
    exit();
}
$user = getUserById($userId);

// Вкладка по умолчанию в зависимости от роли пользователя
$currentTab = isset($_GET['tab']) ? $_GET['tab'] : ($userRole === 'creator' || $userRole === 'admin' ? 'all_journals' : 'my_journals');


// Получаем собственные журналы пользователя
$myJournals = getUserJournals($userId);

// Группируем собственные журналы по годам
$myJournalsByYear = groupJournalsByYear($myJournals);

// Получаем все журналы в зависимости от роли
$allJournals = [];
if (hasTabAccess($userId, 'all_journals-IA')) {
    $allJournals = loadJson('data/journals.json'); // Создатель видит все журналы
} elseif ($userRole === 'admin') {
    // Администратор видит журналы, где у него есть доступ
    $allJournalsList = loadJson('data/journals.json');
    foreach ($allJournalsList as $journal) {
        if (
            (isset($journal['admin_access']) && in_array($userId, $journal['admin_access']))
        ) {
            $allJournals[] = $journal;
        }
    }
}

// Группируем журналы по пользователям для создателя и администратора
$journalsByUser = [];
if ($currentTab === 'all_journals' && (hasTabAccess($userId, 'all_journals'))) {
    $journalsByUser = getJournalsGroupedByUsers($allJournals);
}

// Загрузка логов активности для создателя
$activityLogs = [];
if ((hasTabAccess($userId, 'activity_log')) && $currentTab === 'activity_log') {
    $activityLogs = loadJson('data/activity_log.json');
    // Сортировка по времени (последние в начале)
    usort($activityLogs, function($a, $b) {
        return $b['timestamp'] - $a['timestamp'];
    });
    // Ограничение до 20 записей
    $activityLogs = array_slice($activityLogs, 0, 20);
}

// Получаем количество ожидающих запросов на отчеты для создателя
$pendingReportsCount = 0;
if ($userRole === 'creator') {
    $pendingReportsCount = getReportRequestsCount();
}

// Проверяем наличие ожидающих запросов на восстановление дней
$hasPendingRecoveryRequests = ($userRole === 'creator' || $userRole === 'admin') && hasPendingRecoveryRequests();

// Получаем непрочитанные уведомления
$unreadNotifications = getUnreadNotificationsCount($userId);

// Проверяем наличие назначенных смен
$hasUpcomingShifts = false;
$upcomingShifts = [];

if ($userRole === 'user') {
    $upcomingShifts = getUpcomingShifts($userId);
    $hasUpcomingShifts = !empty($upcomingShifts);
}

// Текущий год
$currentYear = date('Y');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Рабочий журнал - Панель управления</title>
    <meta name="description" content="Built with jdoodle.ai - Система учета рабочего времени с журналами по месяцам">
    <meta property="og:title" content="Рабочий журнал - Панель управления">
    <meta property="og:description" content="Built with jdoodle.ai - Удобная система учета рабочего времени с возможностью экспорта в Excel">
    <meta property="og:image" content="https://images.unsplash.com/photo-1507208773393-40d9fc670acf?ixid=M3w3MjUzNDh8MHwxfHNlYXJjaHwxfHxtb2Rlcm4lMjBvZmZpY2UlMjB3b3Jrc3BhY2UlMjB0ZWFtd29ya3xlbnwwfHx8fDE3NDI5MjM1OTV8MA&ixlib=rb-4.0.3&fit=fillmax&h=450&w=800">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="dashboard">

        <?php renderMenu($userId, $userRole, $firstName, $lastName); ?>

        <div class="main-content">
            <?php if (isset($_SESSION['key_warning'])): ?>
                <div class="warning-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    Внимание! Срок действия вашего ключа доступа истекает <?= date('d.m.Y', $_SESSION['key_expiry']) ?>. 
                    Обратитесь к администратору для продления доступа.
                </div>
            <?php endif; ?>

            <?php if ($hasUpcomingShifts): ?>
                <div class="shift-assignments-alert">
                    <i class="fas fa-calendar-check"></i>
                    <div class="alert-content">
                        <h3>У вас есть назначенные смены</h3>
                        <p>Ваш график работы был обновлен. Проверьте назначенные смены.</p>
                    </div>
                    <div class="alert-actions">
                        <a href="shifts_calendar.php" class="btn">Просмотреть</a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($hasPendingRecoveryRequests): ?>
                <div class="warning-message recovery-warning">
                    <i class="fas fa-exclamation-circle"></i>
                    Внимание! В системе есть ожидающие запросы на восстановление дней.
                    Пожалуйста, проверьте журналы с отметкой <span class="recovery-notification-badge">!</span>
                </div>
            <?php endif; ?>

            <?php if ($currentTab === 'my_journals'): ?>
                <div class="header">
                    <h1>Мои журналы</h1>
                    <?php if ($userRole === 'creator'): ?>
                    <button class="btn" onclick="openModal('create-journal-modal')">Создать журнал</button>
                    <?php endif; ?>
                </div>

                <?php if (empty($myJournalsByYear)): ?>
                    <div class="alert">
                        У вас пока нет рабочих журналов.
                        <?php if ($userRole === 'user'): ?>
                            Обратитесь к администратору для получения доступа.
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <!-- Группировка по годам (от более свежих к старым) -->
                    <?php 
                    krsort($myJournalsByYear); // Сортировка годов в обратном порядке
                    foreach ($myJournalsByYear as $year => $journals): 
                    ?>
                    <div class="year-section <?= $year == $currentYear ? 'current-year' : '' ?>">
                        <h2><?= $year ?> <?= $year == $currentYear ? '<span class="current-year-badge">Текущий год</span>' : '' ?></h2>
                        <div class="journals-list">
                            <?php foreach ($journals as $journal): ?>
                                <?php
                                // Проверяем наличие ожидающих запросов восстановления для этого журнала
                                $recoveryCount = ($userRole === 'creator' || $userRole === 'admin') ? 
                                    getJournalRecoveryRequestsCount($journal['id']) : 0;
                                ?>
                                <div class="journal-card">
                                    <h3>
                                        <?= htmlspecialchars($journal['title']) ?>
                                        <?php if ($recoveryCount > 0): ?>
                                            <span class="recovery-notification-badge" title="<?= $recoveryCount ?> запросов на восстановление">!</span>
                                        <?php endif; ?>
                                    </h3>
                                    <p>Год: <?= htmlspecialchars($journal['year']) ?></p>
                                    <div class="journal-actions">
                                        <a href="journal.php?id=<?= $journal['id'] ?>" class="btn">Открыть журнал</a>
                                        <?php if ($userRole === 'creator'): ?>
                                            <button class="btn btn-primary" onclick="openEditJournalModal('<?= $journal['id'] ?>', '<?= htmlspecialchars($journal['title']) ?>', '<?= $journal['year'] ?>')">Редактировать</button>
                                            <div class="export-dropdown">
                                                <button class="btn btn-success">Экспорт</button>
                                                <div class="export-dropdown-content">
                                                    <a href="export_journal.php?id=<?= $journal['id'] ?>&month=<?= date('n') ?>&format=excel" class="btn-sm">Excel</a>
                                                    <a href="export_journal.php?id=<?= $journal['id'] ?>&month=<?= date('n') ?>&format=html" class="btn-sm">HTML</a>
                                                    <a href="export_journal.php?id=<?= $journal['id'] ?>&month=<?= date('n') ?>&format=txt" class="btn-sm">TXT</a>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>

            <?php elseif ($currentTab === 'all_journals' && (hasTabAccess($userId, 'all_journals'))): ?>
                <div class="header">
                    <h1>Все журналы</h1>
                    <?php if ($userRole === 'creator'): ?>
                    <button class="btn" onclick="openModal('create-journal-modal')">Создать журнал</button>
                    <?php endif; ?>
                </div>

                <?php if (empty($journalsByUser)): ?>
                    <div class="alert">
                        Журналы не найдены.
                    </div>
                <?php else: ?>
                    <div class="users-journals-container">
                        <div class="users-list">
                            <?php foreach ($journalsByUser as $userId => $userJournals): 
                                $totalJournals = count($userJournals['journals']);
                                $hasPendingRecovery = false;
                                foreach ($userJournals['journals'] as $journal) {
                                    if (getJournalRecoveryRequestsCount($journal['id']) > 0) {
                                        $hasPendingRecovery = true;
                                        break;
                                    }
                                }
                            ?>
                                <div class="user-item" data-user-id="<?= $userId ?>">
                                    <div class="user-info" onclick="toggleUserJournals('<?= $userId ?>')">
                                        <div class="user-details">
                                            <h3>
                                                <?= htmlspecialchars($userJournals['user']['last_name'] . ' ' . $userJournals['user']['first_name'] . ' ' . ($userJournals['user']['middle_name'] ?? '')) ?>
                                                <span class="journal-count"><?= $totalJournals ?> журнал<?= $totalJournals % 10 == 1 && $totalJournals % 100 != 11 ? '' : ($totalJournals % 10 >= 2 && $totalJournals % 10 <= 4 && ($totalJournals % 100 < 10 || $totalJournals % 100 >= 20) ? 'а' : 'ов') ?></span>
                                                <?php if ($hasPendingRecovery): ?>
                                                    <span class="recovery-badge"><i class="fas fa-exclamation-circle"></i></span>
                                                <?php endif; ?>
                                            </h3>
                                        </div>
                                        <div class="toggle-icon">
                                            <i class="fas fa-chevron-down"></i>
                                        </div>
                                    </div>
                                    <div class="user-journals" id="user-journals-<?= $userId ?>" style="display: none;">
                                        <?php 
                                        // Группируем журналы пользователя по годам
                                        $userJournalsByYear = groupJournalsByYear($userJournals['journals']);
                                        krsort($userJournalsByYear); // Сортировка годов в обратном порядке

                                        foreach ($userJournalsByYear as $year => $journals): 
                                        ?>
                                            <div class="year-subsection <?= $year == $currentYear ? 'current-year' : '' ?>">
                                                <h4><?= $year ?> <?= $year == $currentYear ? '<span class="current-year-badge">Текущий год</span>' : '' ?></h4>
                                                <div class="journals-grid">
                                                    <?php foreach ($journals as $journal): ?>
                                                        <?php
                                                        // Проверяем наличие ожидающих запросов восстановления для этого журнала
                                                        $recoveryCount = ($userRole === 'creator' || $userRole === 'admin') ? 
                                                            getJournalRecoveryRequestsCount($journal['id']) : 0;
                                                        ?>
                                                        <div class="journal-card">
                                                            <h3>
                                                                <?= htmlspecialchars($journal['title']) ?>
                                                                <?php if ($recoveryCount > 0): ?>
                                                                    <span class="recovery-notification-badge" title="<?= $recoveryCount ?> запросов на восстановление">!</span>
                                                                <?php endif; ?>
                                                            </h3>
                                                            <p>Год: <?= htmlspecialchars($journal['year']) ?></p>
                                                            <div class="journal-actions">
                                                                <a href="journal.php?id=<?= $journal['id'] ?>" class="btn">Открыть журнал</a>
                                                                <?php if ($userRole === 'creator'): ?>
                                                                    <button class="btn btn-primary" onclick="openEditJournalModal('<?= $journal['id'] ?>', '<?= htmlspecialchars($journal['title']) ?>', '<?= $journal['year'] ?>')">Редактировать</button>
                                                                    <div class="export-dropdown">
                                                                        <button class="btn btn-success">Экспорт</button>
                                                                        <div class="export-dropdown-content">
                                                                            <a href="export_journal.php?id=<?= $journal['id'] ?>&month=<?= date('n') ?>&format=excel" class="btn-sm">Excel</a>
                                                                            <a href="export_journal.php?id=<?= $journal['id'] ?>&month=<?= date('n') ?>&format=html" class="btn-sm">HTML</a>
                                                                            <a href="export_journal.php?id=<?= $journal['id'] ?>&month=<?= date('n') ?>&format=txt" class="btn-sm">TXT</a>
                                                                        </div>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

            <?php elseif ($currentTab === 'activity_log' && hasTabAccess($userId, 'activity_log')): ?>
                <div class="header">
                    <h1>Журнал активности</h1>
                </div>

                <div class="activity-log-container">
                    <div class="activity-log-title">
                        <h2>Последние 20 действий в системе</h2>
                        <p>Отслеживание действий пользователей</p>
                    </div>

                    <?php if (empty($activityLogs)): ?>
                        <div class="alert">
                            Пока нет записей активности.
                        </div>
                    <?php elseif ($activityLogs): ?>
                        <ul class="activity-log-list">
                            <?php foreach ($activityLogs as $log): ?>
                                <?php
                                $user = getUserById($log['user_id']);
                                $userName = $user ? ($user['last_name'] . ' ' . $user['first_name']) : 'Неизвестный пользователь';
                                ?>
                                <li class="activity-log-item">
                                    <div class="activity-log-time"><?= date('d.m.Y H:i', $log['timestamp']) ?></div>
                                    <div class="activity-log-icon"><i class="fas <?= getActionIcon($log['action']) ?>"></i></div>
                                    <div class="activity-log-content">
                                        <strong><?= htmlspecialchars($userName) ?></strong>
                                        <span><?= htmlspecialchars($log['description']) ?></span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php elseif ($currentTab === 'activity_log' && !hasTabAccess($userId, 'activity_log')): ?>
                <div class="header">
                    <h1>Журнал активности</h1>
                </div>
                <div class="activity-log-container">
                    <div class="activity-log-title">
                        <h2>Предупреждение!</h2>
                        <p>Зарегистрирован несанкционированный доступ!</p>
                    </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Модальное окно создания журнала -->
    <?php if ($userRole === 'creator'): ?>
    <div id="create-journal-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Создать новый журнал</h2>
                <button class="modal-close" onclick="closeModal('create-journal-modal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="create-journal-form" action="create_journal.php" method="post">
                    <div class="form-group">
                        <label for="journal-title">Название журнала</label>
                        <input type="text" id="journal-title" name="title" required>
                    </div>
                    <div class="form-group">
                        <label for="journal-year">Год</label>
                        <input type="number" id="journal-year" name="year" value="<?= date('Y') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="journal-user">Пользователь</label>
                        <select id="journal-user" name="user_id" required>
                            <?php 
                            $users = loadJson('data/users.json');
                            foreach ($users as $u) {
                                // Теперь создатель может создавать журналы для себя и других пользователей
                                $fullName = $u['last_name'] . ' ' . $u['first_name'] . ' ' . ($u['middle_name'] ?? '');
                                echo '<option value="' . $u['id'] . '">' . htmlspecialchars(trim($fullName)) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-danger" onclick="closeModal('create-journal-modal')">Отмена</button>
                <button class="btn btn-success" onclick="document.getElementById('create-journal-form').submit()">Создать</button>
            </div>
        </div>
    </div>

    <!-- Модальное окно редактирования журнала -->
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

        function toggleUserJournals(userId) {
            const journalsContainer = document.getElementById('user-journals-' + userId);
            const toggleIcon = document.querySelector(`.user-item[data-user-id="${userId}"] .toggle-icon i`);

            if (journalsContainer.style.display === 'none') {
                journalsContainer.style.display = 'block';
                toggleIcon.classList.remove('fa-chevron-down');
                toggleIcon.classList.add('fa-chevron-up');
            } else {
                journalsContainer.style.display = 'none';
                toggleIcon.classList.remove('fa-chevron-up');
                toggleIcon.classList.add('fa-chevron-down');
            }
        }
    </script>
</body>
</html>