<?php
session_start();
require_once  'functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];
$username = $_SESSION['username'];
$firstName = $_SESSION['first_name'] ?? '';
$lastName = $_SESSION['last_name'] ?? '';

// Получаем ID запроса на восстановление
$requestId = $_GET['id'] ?? '';
if (empty($requestId)) {
    header("Location: dashboard.php");
    exit();
}

// Загружаем запрос на восстановление
$recoveryRequests = loadJson('data/recovery_requests.json');
$requestData = null;

foreach ($recoveryRequests as $request) {
    if ($request['id'] === $requestId) {
        $requestData = $request;
        break;
    }
}

if (!$requestData) {
    header("Location: dashboard.php?error=request_not_found");
    exit();
}

// Получаем данные журнала
$journals = loadJson('data/journals.json');
$journalData = null;

foreach ($journals as $journal) {
    if ($journal['id'] === $requestData['journal_id']) {
        $journalData = $journal;
        break;
    }
}

if (!$journalData) {
    header("Location: dashboard.php?error=journal_not_found");
    exit();
}

// Проверяем права доступа к журналу
if (!canAccessJournal($journalData['id'], $userId, $userRole)) {
    header("Location: dashboard.php?error=access_denied");
    exit();
}

// Получаем информацию о пользователе, запросившем восстановление
$requestUser = getUserById($requestData['user_id']);
$requestUserName = '';
if ($requestUser) {
    $requestUserName = trim($requestUser['last_name'] . ' ' . $requestUser['first_name'] . ' ' . ($requestUser['middle_name'] ?? ''));
}

// Получаем информацию о пользователе, обработавшем запрос (если есть)
$processedByUser = null;
if (!empty($requestData['processed_by'])) {
    $processedByUser = getUserById($requestData['processed_by']);
}

// Форматируем дату
list($year, $month, $day) = explode('-', $requestData['date']);
$formattedDate = $day . ' ' . getRussianMonth((int)$month) . ' ' . $year;

// Установим статус запроса для отображения
$statusText = '';
$statusClass = '';
switch ($requestData['status']) {
    case 'pending':
        $statusText = 'В ожидании';
        $statusClass = 'status-pending';
        break;
    case 'completed':
        $statusText = 'Выполнен';
        $statusClass = 'status-completed';
        break;
    case 'rejected':
        $statusText = 'Отклонен';
        $statusClass = 'status-rejected';
        break;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Рабочий журнал - Детали запроса на восстановление</title>
    <meta name="description" content="Built with jdoodle.ai - Система учета рабочего времени с возможностью восстановления данных">
    <meta property="og:title" content="Рабочий журнал - Детали запроса на восстановление">
    <meta property="og:description" content="Built with jdoodle.ai - Просмотр деталей запроса на восстановление дня в журнале">
    <meta property="og:image" content="https://images.unsplash.com/photo-1517245386807-bb43f82c33c4?ixid=M3w3MjUzNDh8MHwxfHNlYXJjaHwzfHxwZW9wbGUlMjB3b3JraW5nJTIwaW4lMjBvZmZpY2UlMjB3aXRoJTIwY29tcHV0ZXIlMjBidXNpbmVzcyUyMHRlYW18ZW58MHx8fHwxNzQyOTI1NTcyfDA&ixlib=rb-4.0.3&fit=fillmax&h=450&w=800">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .recovery-details {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .recovery-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .recovery-header h2 {
            margin: 0;
            font-size: 22px;
        }
        
        .recovery-status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .status-pending {
            background-color: #ffeaa7;
            color: #d35400;
        }
        
        .status-completed {
            background-color: #55efc4;
            color: #006266;
        }
        
        .status-rejected {
            background-color: #fab1a0;
            color: #c0392b;
        }
        
        .recovery-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .recovery-info-item {
            margin-bottom: 15px;
        }
        
        .recovery-info-item h3 {
            font-size: 16px;
            color: #777;
            margin-bottom: 5px;
        }
        
        .recovery-info-item p {
            font-size: 16px;
            margin: 0;
        }
        
        .recovery-comments {
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 6px;
            margin-bottom: 25px;
        }
        
        .comment-section {
            margin-bottom: 20px;
        }
        
        .comment-section h3 {
            font-size: 18px;
            margin-bottom: 10px;
            color: #444;
        }
        
        .comment-content {
            background-color: white;
            padding: 15px;
            border-radius: 6px;
            border-left: 4px solid var(--primary-color);
            font-style: italic;
        }
        
        .comment-content.admin-comment {
            border-left-color: var(--success-color);
        }
        
        .recovery-data {
            background-color: #f0f8ff;
            padding: 20px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .recovery-data h3 {
            font-size: 18px;
            margin-bottom: 15px;
            color: #2980b9;
        }
        
        .data-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }
        
        .data-item {
            background-color: white;
            padding: 12px;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .data-item h4 {
            font-size: 14px;
            color: #777;
            margin: 0 0 5px 0;
        }
        
        .data-item p {
            font-size: 16px;
            font-weight: 600;
            margin: 0;
        }
        
        .recovery-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }
        
        .empty-comment {
            font-style: italic;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <div class="sidebar">
            <div class="sidebar-header">
                <h2>Рабочий журнал</h2>
                <p><?= htmlspecialchars($lastName . ' ' . $firstName) ?> (<?= htmlspecialchars($userRole) ?>)</p>
            </div>
            <div class="sidebar-menu">
                <ul>
                    <?php if ($userRole === 'creator' || $userRole === 'admin'): ?>
                    <li><a href="dashboard.php?tab=all_journals"><i class="fas fa-book-open"></i> Все журналы</a></li>
                    <?php endif; ?>
                    
                    <li><a href="dashboard.php?tab=my_journals"><i class="fas fa-book"></i> Мои журналы</a></li>
                    
                    <?php if ($userRole === 'creator' || $userRole === 'admin'): ?>
                    <li>
                        <a href="paper_reports.php">
                            <i class="fas fa-file-alt"></i> Бумажный отчёт
                            <?php if ($userRole === 'creator'): 
                                $pendingReportsCount = getReportRequestsCount();
                                if ($pendingReportsCount > 0):
                            ?>
                                <span class="badge badge-warning"><?= $pendingReportsCount ?></span>
                            <?php endif; endif; ?>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php if ($userRole === 'creator'): ?>
                    <li><a href="backup_journals.php"><i class="fas fa-save"></i> Сохраненные журналы</a></li>
                    <?php endif; ?>
                    
                    <?php if ($userRole === 'creator'): ?>
                    <li><a href="dashboard.php?tab=activity_log"><i class="fas fa-history"></i> Журнал активности</a></li>
                    <?php endif; ?>
                    
                    <?php if ($userRole === 'creator' || $userRole === 'admin'): ?>
                    <li><a href="users.php"><i class="fas fa-users"></i> Пользователи</a></li>
                    <?php endif; ?>
                    
                    <?php if ($userRole === 'creator'): ?>
                    <li><a href="access_keys.php"><i class="fas fa-key"></i> Ключи доступа</a></li>
                    <?php endif; ?>
                    
                    <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Выход</a></li>
                </ul>
            </div>
        </div>
        <div class="main-content">
            <div class="header">
                <h1>Детали запроса на восстановление</h1>
                <a href="journal.php?id=<?= $journalData['id'] ?>&month=<?= $month ?>&day=<?= $day ?>" class="btn"><i class="fas fa-arrow-left"></i> Вернуться к журналу</a>
            </div>
            
            <div class="recovery-details">
                <div class="recovery-header">
                    <h2>Запрос на восстановление для <?= htmlspecialchars($journalData['title']) ?></h2>
                    <span class="recovery-status <?= $statusClass ?>"><?= $statusText ?></span>
                </div>
                
                <div class="recovery-info">
                    <div>
                        <div class="recovery-info-item">
                            <h3>Журнал</h3>
                            <p><?= htmlspecialchars($journalData['title']) ?> (<?= $journalData['year'] ?>)</p>
                        </div>
                        
                        <div class="recovery-info-item">
                            <h3>Дата</h3>
                            <p><?= $formattedDate ?></p>
                        </div>
                        
                        <div class="recovery-info-item">
                            <h3>Запросил</h3>
                            <p><?= htmlspecialchars($requestUserName) ?></p>
                        </div>
                    </div>
                    
                    <div>
                        <div class="recovery-info-item">
                            <h3>Дата запроса</h3>
                            <p><?= date('d.m.Y H:i', $requestData['requested_at']) ?></p>
                        </div>
                        
                        <?php if ($requestData['status'] !== 'pending'): ?>
                        <div class="recovery-info-item">
                            <h3>Дата обработки</h3>
                            <p><?= date('d.m.Y H:i', $requestData['processed_at']) ?></p>
                        </div>
                        
                        <div class="recovery-info-item">
                            <h3>Обработал</h3>
                            <p>
                                <?php if ($processedByUser): ?>
                                    <?= htmlspecialchars($processedByUser['last_name'] . ' ' . $processedByUser['first_name'] . ' ' . ($processedByUser['middle_name'] ?? '')) ?>
                                <?php else: ?>
                                    Неизвестный пользователь
                                <?php endif; ?>
                            </p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="recovery-comments">
                    <div class="comment-section">
                        <h3>Комментарий пользователя</h3>
                        <div class="comment-content">
                            <?php if (!empty($requestData['comment'])): ?>
                                <?= nl2br(htmlspecialchars($requestData['comment'])) ?>
                            <?php else: ?>
                                <p class="empty-comment">Пользователь не оставил комментарий.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if ($requestData['status'] === 'completed' && !empty($requestData['helper_comment'])): ?>
                    <div class="comment-section">
                        <h3>Комментарий администратора</h3>
                        <div class="comment-content admin-comment">
                            <?= nl2br(htmlspecialchars($requestData['helper_comment'])) ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($requestData['status'] === 'completed' && isset($requestData['recovery_data'])): ?>
                <div class="recovery-data">
                    <h3>Восстановленные данные</h3>
                    <div class="data-grid">
                        <div class="data-item">
                            <h4>Начало смены</h4>
                            <p><?= htmlspecialchars($requestData['recovery_data']['start_time']) ?></p>
                        </div>
                        
                        <div class="data-item">
                            <h4>Конец смены</h4>
                            <p><?= htmlspecialchars($requestData['recovery_data']['end_time']) ?></p>
                        </div>
                        
                        <div class="data-item">
                            <h4>Обед (мин)</h4>
                            <p><?= (int)$requestData['recovery_data']['lunch_minutes'] ?></p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($requestData['status'] === 'pending' && ($userRole === 'creator' || ($userRole === 'admin' && in_array($userId, $journalData['admin_access'] ?? [])))): ?>
                <div class="recovery-actions">
                    <button class="btn btn-success" onclick="openRecoveryHelpModal('<?= $requestData['id'] ?>')">Помочь</button>
                    <a href="cancel_recovery.php?id=<?= $requestData['id'] ?>" class="btn btn-danger" onclick="return confirm('Вы уверены, что хотите отклонить этот запрос?')">Отклонить</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Модальное окно помощи с восстановлением -->
    <?php if ($requestData['status'] === 'pending' && ($userRole === 'creator' || ($userRole === 'admin' && in_array($userId, $journalData['admin_access'] ?? [])))): ?>
    <div id="recovery-help-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Помощь с восстановлением данных</h2>
                <button class="modal-close" onclick="closeModal('recovery-help-modal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="recovery-help-form" action="help_recovery.php" method="post">
                    <input type="hidden" id="recovery-request-id" name="request_id" value="<?= $requestData['id'] ?>">
                    
                    <div class="form-group">
                        <label for="help-start-time">Начало смены</label>
                        <input type="time" id="help-start-time" name="start_time" value="09:00" required>
                    </div>
                    <div class="form-group">
                        <label for="help-end-time">Конец смены</label>
                        <input type="time" id="help-end-time" name="end_time" value="18:00" required>
                    </div>
                    <div class="form-group">
                        <label for="help-lunch-minutes">Обед (мин)</label>
                        <input type="number" id="help-lunch-minutes" name="lunch_minutes" value="60" min="0" required>
                    </div>
                    <div class="form-group">
                        <label for="help-comment">Комментарий (не обязательно)</label>
                        <textarea id="help-comment" name="comment" rows="3" placeholder="Добавьте комментарий при необходимости"></textarea>
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
        
        function openRecoveryHelpModal(requestId) {
            document.getElementById('recovery-request-id').value = requestId;
            openModal('recovery-help-modal');
        }
    </script>
</body>
</html>
 