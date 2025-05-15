<?php
session_start();
require_once  'functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user = checkAccess();
$userId = $user['id'];
$userRole = $user['role'];
// $username = $_SESSION['username'];
$firstName = $user['first_name'];
$lastName = $user['last_name'];

if (!hasTabAccess($userId, 'paper_reports')) {
    header("Location: dashboard.php");
    exit();
}

// Загружаем список отчетов
$allReports = loadJson('data/paper_reports.json');

// Фильтруем отчеты в зависимости от роли
$reports = [];
$rejectedReports = [];

if ($userRole === 'creator' || hasTabAccess($userId, 'paper_reports+')) {
    // Создатель и обладатель расширенных отсчётов видит все отчеты
    foreach ($allReports as $report) {
        if (isset($report['status']) && $report['status'] === 'rejected') {
            $rejectedReports[] = $report;
        } else {
            $reports[] = $report;
        }
    }
} elseif ($userRole === 'admin') {
    // Администратор видит отчеты, где у него есть доступ
    foreach ($allReports as $report) {
        if (isset($report['status']) && $report['status'] === 'rejected') {
            // Администратор видит свои отклоненные запросы
            if (isset($report['admin_access']) && in_array($userId, $report['admin_access'])) {
                $rejectedReports[] = $report;
            }
        } elseif (isset($report['admin_access']) && in_array($userId, $report['admin_access'])) {
            $reports[] = $report;
        }
    }
}

// Группируем отчеты по формату
$excelReports = [];
$htmlReports = [];
$txtReports = [];

foreach ($reports as $report) {
    $format = $report['format'] ?? 'excel';
    
    switch ($format) {
        case 'excel':
            $excelReports[] = $report;
            break;
        case 'html':
            $htmlReports[] = $report;
            break;
        case 'txt':
            $txtReports[] = $report;
            break;
        default:
            $excelReports[] = $report;
    }
}

// Группируем отчеты по пользователям для каждого формата
$excelReportsByUser = getReportsGroupedByUsers($excelReports);
$htmlReportsByUser = getReportsGroupedByUsers($htmlReports);
$txtReportsByUser = getReportsGroupedByUsers($txtReports);

// Получаем запросы на отчеты (только для создателя)
$reportRequests = [];
$pendingRequests = [];
if ($userRole === 'creator') {
    $reportRequests = getReportRequests();
    // Фильтруем только ожидающие обработки
    $pendingRequests = array_filter($reportRequests, function($req) {
        return $req['status'] === 'pending';
    });
}

// Получаем все журналы для администратора (чтобы он мог запросить отчет)
$availableJournals = [];
if ($userRole === 'admin') {
    $allJournals = loadJson('data/journals.json');
    foreach ($allJournals as $journal) {
        if (isset($journal['admin_access']) && in_array($userId, $journal['admin_access'])) {
            $availableJournals[] = $journal;
        }
    }
}

// Получаем ожидающие запросы для администратора
$myPendingRequests = [];
if ($userRole === 'admin') {
    $allRequests = loadJson('data/report_requests.json');
    foreach ($allRequests as $request) {
        if ($request['user_id'] === $userId && $request['status'] === 'pending') {
            $myPendingRequests[] = $request;
        }
    }
}

// По умолчанию активный формат (для фильтрации)
$activeFormat = isset($_GET['format']) ? $_GET['format'] : 'all';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Рабочий журнал - Бумажный отчёт</title>
    <meta name="description" content="Built with jdoodle.ai - Система учета рабочего времени с экспортом в Excel">
    <meta property="og:title" content="Рабочий журнал - Бумажный отчёт">
    <meta property="og:description" content="Built with jdoodle.ai - Удобная система учета рабочего времени с возможностью экспорта в Excel">
    <meta property="og:image" content="https://images.unsplash.com/photo-1542744095-fcf48d80b0fd?ixid=M3w3MjUzNDh8MHwxfHNlYXJjaHw2fHxtb2Rlcm4lMjBvZmZpY2UlMjB3b3Jrc3BhY2UlMjB0ZWFtd29ya3xlbnwwfHx8fDE3NDI5MjM1OTV8MA&ixlib=rb-4.0.3&fit=fillmax&h=450&w=800">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="dashboard">

        <?php renderMenu($userId, $userRole, $firstName, $lastName); ?>

        <div class="main-content">
            <div class="paper-reports-container">
                <div class="paper-reports-title">
                    <h2>Бумажный отчёт</h2>
                    <p>Управление экспортированными отчетами</p>
                </div>
                
                <!-- Информация о форматах -->
                <div class="format-info">
                    <i class="fas fa-info-circle"></i>
                    <p>Доступны три формата экспорта отчетов:</p>
                    <p><strong>Excel (CSV)</strong> - для работы с табличными данными</p>
                    <p><strong>HTML</strong> - для просмотра отчетов в браузере</p>
                    <p><strong>TXT</strong> - <span class="format-recommend">самый проработанный и понятный формат</span> для быстрого просмотра</p>
                </div>
                
                <?php if ($userRole === 'creator' && !empty($pendingRequests)): ?>
                <div class="reports-section">
                    <div class="header">
                        <h2>Запросы на отчёты (<?= count($pendingRequests) ?>)</h2>
                    </div>
                    
                    <div class="report-requests">
                        <?php foreach ($pendingRequests as $request): ?>
                            <?php
                            $requestingUser = getUserById($request['user_id']);
                            $requestUserName = $requestingUser ? ($requestingUser['last_name'] . ' ' . $requestingUser['first_name'] . ' ' . ($requestingUser['middle_name'] ?? '')) : 'Неизвестный пользователь';
                            
                            // Получаем данные журнала
                            $journalData = null;
                            $journals = loadJson('data/journals.json');
                            foreach ($journals as $journal) {
                                if ($journal['id'] === $request['journal_id']) {
                                    $journalData = $journal;
                                    break;
                                }
                            }
                            
                            if (!$journalData) continue;
                            
                            // Получаем данные о владельце журнала
                            $journalOwner = getUserById($journalData['user_id']);
                            $ownerName = $journalOwner ? ($journalOwner['last_name'] . ' ' . $journalOwner['first_name'] . ' ' . ($journalOwner['middle_name'] ?? '')) : 'Неизвестный пользователь';
                            ?>
                            
                            <div class="request-item">
                                <div class="request-info">
                                    <h3><?= htmlspecialchars($journalData['title']) ?> - <?= getRussianMonth($request['month']) ?> <?= $request['year'] ?></h3>
                                    <p>Запросил: <?= htmlspecialchars($requestUserName) ?></p>
                                    <p>Сотрудник: <?= htmlspecialchars($ownerName) ?></p>
                                    <p>Дата запроса: <?= date('d.m.Y H:i', $request['requested_at']) ?></p>
                                </div>
                                <div class="request-actions">
                                    <button class="btn btn-success" onclick="openApproveModal('<?= $request['id'] ?>')">Подтвердить</button>
                                    <button class="btn btn-danger" onclick="openRejectModal('<?= $request['id'] ?>')">Отклонить</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if (empty($pendingRequests)): ?>
                            <div class="alert">
                                Нет ожидающих запросов на отчеты.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($userRole === 'admin' && !empty($myPendingRequests)): ?>
                <div class="reports-section">
                    <div class="header">
                        <h2>Мои запросы (в ожидании)</h2>
                    </div>
                    
                    <div class="my-pending-requests">
                        <?php foreach ($myPendingRequests as $request): ?>
                            <?php
                            // Получаем данные журнала
                            $journalData = null;
                            $journals = loadJson('data/journals.json');
                            foreach ($journals as $journal) {
                                if ($journal['id'] === $request['journal_id']) {
                                    $journalData = $journal;
                                    break;
                                }
                            }
                            
                            if (!$journalData) continue;
                            
                            // Получаем данные о владельце журнала
                            $journalOwner = getUserById($journalData['user_id']);
                            $ownerName = '';
                            if ($journalOwner) {
                                $ownerName = trim($journalOwner['last_name'] . ' ' . $journalOwner['first_name'] . ' ' . ($journalOwner['middle_name'] ?? ''));
                            }
                            ?>
                            
                            <div class="request-item pending-request">
                                <div class="request-info">
                                    <h3><?= htmlspecialchars($journalData['title']) ?> - <?= getRussianMonth($request['month']) ?> <?= $request['year'] ?></h3>
                                    <p>Сотрудник: <?= htmlspecialchars($ownerName) ?></p>
                                    <p>Дата запроса: <?= date('d.m.Y H:i', $request['requested_at']) ?></p>
                                    <p class="pending-message"><i class="fas fa-clock"></i> Ожидайте, ответ придёт в течении суток</p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($userRole === 'admin' && !empty($availableJournals)): ?>
                <div class="reports-section">
                    <div class="header">
                        <h2>Запросить отчёт</h2>
                    </div>
                    
                    <form action="request_report.php" method="post">
                        <div class="form-group">
                            <label for="journal_id">Выберите журнал</label>
                            <select id="journal_id" name="journal_id" required>
                                <?php foreach ($availableJournals as $journal): ?>
                                    <?php
                                    $journalOwner = getUserById($journal['user_id']);
                                    $ownerName = $journalOwner ? ($journalOwner['last_name'] . ' ' . $journalOwner['first_name'] . ' ' . ($journalOwner['middle_name'] ?? '')) : 'Неизвестный пользователь';
                                    ?>
                                    <option value="<?= $journal['id'] ?>"><?= htmlspecialchars($journal['title']) ?> (<?= htmlspecialchars($ownerName) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="month">Выберите месяц</label>
                            <select id="month" name="month" required>
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?= $m ?>" <?= $m == date('n') ? 'selected' : '' ?>><?= getRussianMonth($m) ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="format">Формат отчета</label>
                            <select id="format" name="format" required>
                                <option value="excel">Excel (CSV)</option>
                                <option value="html">HTML</option>
                                <option value="txt" selected>Текстовый файл</option>
                            </select>
                            <p class="hint">Текстовый формат (TXT) - самый проработанный и понятный</p>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Запросить отчёт</button>
                    </form>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($rejectedReports)): ?>
                <div class="reports-section">
                    <div class="header">
                        <h2>Отклоненные запросы</h2>
                    </div>
                    
                    <div class="rejected-requests">
                        <?php 
                        // Сортировка по времени отклонения (последние в начале)
                        usort($rejectedReports, function($a, $b) {
                            return $b['rejected_at'] - $a['rejected_at'];
                        });
                        
                        foreach ($rejectedReports as $report): ?>
                            <?php
                            // Получаем данные о журнале
                            $journalData = null;
                            $journals = loadJson('data/journals.json');
                            foreach ($journals as $journal) {
                                if ($journal['id'] === $report['journal_id']) {
                                    $journalData = $journal;
                                    break;
                                }
                            }
                            
                            if (!$journalData) continue;
                            
                            // Получаем данные о владельце журнала
                            $journalOwner = getUserById($journalData['user_id']);
                            $ownerName = '';
                            if ($journalOwner) {
                                $ownerName = trim($journalOwner['last_name'] . ' ' . $journalOwner['first_name'] . ' ' . ($journalOwner['middle_name'] ?? ''));
                            }
                            ?>
                            
                            <div class="request-item rejected-request">
                                <div class="request-info">
                                    <h3><?= htmlspecialchars($journalData['title']) ?> - <?= getRussianMonth($report['month']) ?> <?= $report['year'] ?></h3>
                                    <p>Сотрудник: <?= htmlspecialchars($ownerName) ?></p>
                                    <p>Дата отклонения: <?= date('d.m.Y H:i', $report['rejected_at']) ?></p>
                                    <p class="reject-reason">Причина: <?= htmlspecialchars($report['reject_reason'] ?? 'Не указана') ?></p>
                                </div>
                                <div class="request-actions">
                                    <?php if ($userRole === 'admin'): ?>
                                    <button class="btn btn-primary" onclick="location.href='request_report.php?journal_id=<?= $journalData['id'] ?>&month=<?= $report['month'] ?>'">Запросить снова</button>
                                    <?php elseif ($userRole === 'creator'): ?>
                                    <a href="delete_rejected_report.php?id=<?= $report['id'] ?>" class="btn btn-danger" onclick="return confirm('Вы уверены, что хотите удалить этот отклоненный запрос?')">Удалить</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="reports-section">
                    <div class="header">
                        <h2>Доступные отчёты</h2>
                    </div>
                    
                    <!-- Фильтры по формату -->
                    <div class="format-filter">
                        <button class="filter-btn <?= $activeFormat === 'all' ? 'active' : '' ?>" onclick="location.href='paper_reports.php?format=all'">Все форматы</button>
                        <button class="filter-btn <?= $activeFormat === 'txt' ? 'active' : '' ?>" onclick="location.href='paper_reports.php?format=txt'">
                            <i class="fas fa-file-alt"></i> Текстовые (TXT)
                        </button>
                        <button class="filter-btn <?= $activeFormat === 'excel' ? 'active' : '' ?>" onclick="location.href='paper_reports.php?format=excel'">
                            <i class="fas fa-file-excel"></i> Excel (CSV)
                        </button>
                        <button class="filter-btn <?= $activeFormat === 'html' ? 'active' : '' ?>" onclick="location.href='paper_reports.php?format=html'">
                            <i class="fas fa-file-code"></i> HTML
                        </button>
                    </div>
                    
                    <?php 
                    $noReports = empty($txtReportsByUser) && empty($excelReportsByUser) && empty($htmlReportsByUser);
                    if ($noReports): 
                    ?>
                        <div class="alert">
                            У вас пока нет доступных отчетов.
                        </div>
                    <?php else: ?>
                        
                        <?php if ($activeFormat === 'all' || $activeFormat === 'txt'): ?>
                            <?php if (!empty($txtReportsByUser)): ?>
                                <h3 class="format-section-title"><i class="fas fa-file-alt"></i> Текстовые отчеты (TXT)</h3>
                                <?php foreach ($txtReportsByUser as $userReports): ?>
                                    <div class="user-journals-section">
                                        <h3><?= htmlspecialchars($userReports['user']['last_name'] . ' ' . $userReports['user']['first_name'] . ' ' . ($userReports['user']['middle_name'] ?? '')) ?></h3>
                                        
                                        <div class="reports-list">
                                            <?php foreach ($userReports['reports'] as $report): ?>
                                                <div class="report-card">
                                                    <h3><?= getRussianMonth($report['month']) ?> <?= $report['year'] ?></h3>
                                                    <div class="report-meta">
                                                        Создан: <?= date('d.m.Y H:i', $report['created_at']) ?>
                                                        <?php if (isset($report['hourly_rate']) && $report['hourly_rate'] > 0): ?>
                                                        <br>Ставка: <?= number_format($report['hourly_rate'], 2) ?> руб/час
                                                        <?php endif; ?>
                                                        <br><i class="fas fa-file-alt"></i> Формат: TXT
                                                    </div>
                                                    
                                                    <div class="report-actions">
                                                        <a href="download_report.php?id=<?= $report['id'] ?>" class="btn btn-primary">Скачать</a>
                                                        <?php if ($userRole === 'creator'): ?>
                                                            <button class="btn btn-primary" onclick="openManageReportAccess('<?= $report['id'] ?>')">Управление доступом</button>
                                                            <a href="delete_report.php?id=<?= $report['id'] ?>" class="btn btn-danger" onclick="return confirm('Вы уверены, что хотите удалить этот отчет?')">Удалить</a>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php if ($activeFormat === 'all' || $activeFormat === 'excel'): ?>
                            <?php if (!empty($excelReportsByUser)): ?>
                                <h3 class="format-section-title"><i class="fas fa-file-excel"></i> Отчеты Excel (CSV)</h3>
                                <?php foreach ($excelReportsByUser as $userReports): ?>
                                    <div class="user-journals-section">
                                        <h3><?= htmlspecialchars($userReports['user']['last_name'] . ' ' . $userReports['user']['first_name'] . ' ' . ($userReports['user']['middle_name'] ?? '')) ?></h3>
                                        
                                        <div class="reports-list">
                                            <?php foreach ($userReports['reports'] as $report): ?>
                                                <div class="report-card">
                                                    <h3><?= getRussianMonth($report['month']) ?> <?= $report['year'] ?></h3>
                                                    <div class="report-meta">
                                                        Создан: <?= date('d.m.Y H:i', $report['created_at']) ?>
                                                        <?php if (isset($report['hourly_rate']) && $report['hourly_rate'] > 0): ?>
                                                        <br>Ставка: <?= number_format($report['hourly_rate'], 2) ?> руб/час
                                                        <?php endif; ?>
                                                        <br><i class="fas fa-file-excel"></i> Формат: Excel (CSV)
                                                    </div>
                                                    
                                                    <div class="report-actions">
                                                        <a href="download_report.php?id=<?= $report['id'] ?>" class="btn btn-primary">Скачать</a>
                                                        <?php if ($userRole === 'creator'): ?>
                                                            <button class="btn btn-primary" onclick="openManageReportAccess('<?= $report['id'] ?>')">Управление доступом</button>
                                                            <a href="delete_report.php?id=<?= $report['id'] ?>" class="btn btn-danger" onclick="return confirm('Вы уверены, что хотите удалить этот отчет?')">Удалить</a>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php if ($activeFormat === 'all' || $activeFormat === 'html'): ?>
                            <?php if (!empty($htmlReportsByUser)): ?>
                                <h3 class="format-section-title"><i class="fas fa-file-code"></i> HTML-отчеты</h3>
                                <?php foreach ($htmlReportsByUser as $userReports): ?>
                                    <div class="user-journals-section">
                                        <h3><?= htmlspecialchars($userReports['user']['last_name'] . ' ' . $userReports['user']['first_name'] . ' ' . ($userReports['user']['middle_name'] ?? '')) ?></h3>
                                        
                                        <div class="reports-list">
                                            <?php foreach ($userReports['reports'] as $report): ?>
                                                <div class="report-card">
                                                    <h3><?= getRussianMonth($report['month']) ?> <?= $report['year'] ?></h3>
                                                    <div class="report-meta">
                                                        Создан: <?= date('d.m.Y H:i', $report['created_at']) ?>
                                                        <?php if (isset($report['hourly_rate']) && $report['hourly_rate'] > 0): ?>
                                                        <br>Ставка: <?= number_format($report['hourly_rate'], 2) ?> руб/час
                                                        <?php endif; ?>
                                                        <br><i class="fas fa-file-code"></i> Формат: HTML
                                                    </div>
                                                    
                                                    <div class="report-actions">
                                                        <a href="download_report.php?id=<?= $report['id'] ?>" class="btn btn-primary">Скачать</a>
                                                        <?php if ($userRole === 'creator'): ?>
                                                            <button class="btn btn-primary" onclick="openManageReportAccess('<?= $report['id'] ?>')">Управление доступом</button>
                                                            <a href="delete_report.php?id=<?= $report['id'] ?>" class="btn btn-danger" onclick="return confirm('Вы уверены, что хотите удалить этот отчет?')">Удалить</a>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($userRole === 'creator'): ?>
    <!-- Модальное окно управления доступом к отчету -->
    <div id="manage-report-access-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Управление доступом к отчету</h2>
                <button class="modal-close" onclick="closeModal('manage-report-access-modal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="manage-report-access-form" action="manage_report_access.php" method="post">
                    <input type="hidden" id="report-id" name="report_id">
                    <p>Выберите администраторов, которые будут иметь доступ к этому отчету:</p>
                    
                    <?php
                    $admins = getAllAdmins();
                    if (empty($admins)): ?>
                        <p>Нет доступных администраторов.</p>
                    <?php else: ?>
                        <div class="admin-list">
                            <?php foreach ($admins as $admin): ?>
                                <div class="admin-item">
                                    <label>
                                        <input type="checkbox" name="admin_access[]" value="<?= $admin['id'] ?>" class="admin-checkbox">
                                        <?= htmlspecialchars($admin['last_name'] . ' ' . $admin['first_name'] . ' ' . ($admin['middle_name'] ?? '')) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-danger" onclick="closeModal('manage-report-access-modal')">Отмена</button>
                <button class="btn btn-success" onclick="document.getElementById('manage-report-access-form').submit()">Сохранить</button>
            </div>
        </div>
    </div>
    
    <!-- Модальное окно подтверждения запроса на отчет -->
    <div id="approve-request-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Подтверждение запроса</h2>
                <button class="modal-close" onclick="closeModal('approve-request-modal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="approve-request-form" action="process_report_request.php" method="get">
                    <input type="hidden" id="approve-request-id" name="request_id">
                    <input type="hidden" name="action" value="approve">
                    
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
                <button class="btn btn-danger" onclick="closeModal('approve-request-modal')">Отмена</button>
                <button class="btn btn-success" onclick="document.getElementById('approve-request-form').submit()">Подтвердить</button>
            </div>
        </div>
    </div>
    
    <!-- Модальное окно отклонения запроса на отчет -->
    <div id="reject-request-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Отклонение запроса</h2>
                <button class="modal-close" onclick="closeModal('reject-request-modal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="reject-request-form" action="process_report_request.php" method="get">
                    <input type="hidden" id="reject-request-id" name="request_id">
                    <input type="hidden" name="action" value="reject">
                    
                    <div class="form-group">
                        <label for="reject-reason">Причина отклонения</label>
                        <textarea id="reject-reason" name="reason" rows="3" placeholder="Укажите причину отклонения запроса"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" onclick="closeModal('reject-request-modal')">Отмена</button>
                <button class="btn btn-danger" onclick="document.getElementById('reject-request-form').submit()">Отклонить</button>
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
        
        <?php if ($userRole === 'creator'): ?>
        function openManageReportAccess(reportId) {
            document.getElementById('report-id').value = reportId;
            
            // Сбрасываем все чекбоксы
            var checkboxes = document.querySelectorAll('.admin-checkbox');
            checkboxes.forEach(function(checkbox) {
                checkbox.checked = false;
            });
            
            // Загружаем текущие права доступа
            var reports = <?= json_encode($reports) ?>;
            var report = reports.find(function(r) { return r.id === reportId; });
            
            if (report && report.admin_access) {
                report.admin_access.forEach(function(adminId) {
                    var checkbox = document.querySelector('.admin-checkbox[value="' + adminId + '"]');
                    if (checkbox) checkbox.checked = true;
                });
            }
            
            openModal('manage-report-access-modal');
        }
        
        function openApproveModal(requestId) {
            document.getElementById('approve-request-id').value = requestId;
            document.getElementById('hourly-rate').value = '';
            openModal('approve-request-modal');
        }
        
        function openRejectModal(requestId) {
            document.getElementById('reject-request-id').value = requestId;
            document.getElementById('reject-reason').value = '';
            openModal('reject-request-modal');
        }
        <?php endif; ?>
    </script>
</body>
</html>
 