
<?php
session_start();
require_once 'functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'creator') {
    header("Location: dashboard.php");
    exit();
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];
$firstName = $_SESSION['first_name'] ?? '';
$lastName = $_SESSION['last_name'] ?? '';

// Load all journals
$journals = loadJson('data/journals.json');
$selectedJournal = isset($_GET['journal']) ? $_GET['journal'] : null;
$selectedTab = isset($_GET['tab']) ? $_GET['tab'] : 'info';

// Get journal details if selected
$journalDetails = null;
if ($selectedJournal) {
    foreach ($journals as $journal) {
        if ($journal['id'] === $selectedJournal) {
            $journalDetails = $journal;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ретро Панель - Рабочий журнал</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/retro.css">
</head>
<body class="retro">
    <div class="retro-container">
        <div class="retro-sidebar">
            <div class="retro-header">
                <h2>Журналы системы</h2>
            </div>
            <div class="retro-journals-list">
                <?php foreach ($journals as $journal): ?>
                    <a href="?journal=<?= $journal['id'] ?>" 
                       class="retro-journal-item <?= ($selectedJournal === $journal['id']) ? 'active' : '' ?>">
                        ID: <?= $journal['id'] ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <div class="retro-nav">
                <a href="dashboard.php" class="retro-button">← Назад</a>
            </div>
        </div>
        
        <div class="retro-content">
            <?php if ($journalDetails): ?>
                <div class="retro-tabs">
                    <a href="?journal=<?= $selectedJournal ?>&tab=info" 
                       class="retro-tab <?= $selectedTab === 'info' ? 'active' : '' ?>">Информация</a>
                    <a href="?journal=<?= $selectedJournal ?>&tab=owner" 
                       class="retro-tab <?= $selectedTab === 'owner' ? 'active' : '' ?>">Владелец</a>
                    <a href="?journal=<?= $selectedJournal ?>&tab=stats" 
                       class="retro-tab <?= $selectedTab === 'stats' ? 'active' : '' ?>">Статистика</a>
                    <a href="?journal=<?= $selectedJournal ?>&tab=access" 
                       class="retro-tab <?= $selectedTab === 'access' ? 'active' : '' ?>">Доступ</a>
                </div>
                
                <div class="retro-panel">
                    <?php if ($selectedTab === 'info'): ?>
                        <div class="retro-section">
                            <h3>Основная информация</h3>
                            <table class="retro-table">
                                <tr>
                                    <td>Название:</td>
                                    <td><?= htmlspecialchars($journalDetails['title']) ?></td>
                                </tr>
                                <tr>
                                    <td>ID:</td>
                                    <td><?= $journalDetails['id'] ?></td>
                                </tr>
                                <tr>
                                    <td>Год:</td>
                                    <td><?= $journalDetails['year'] ?></td>
                                </tr>
                                <tr>
                                    <td>Создан:</td>
                                    <td><?= date('d.m.Y H:i', $journalDetails['created_at']) ?></td>
                                </tr>
                            </table>
                        </div>
                    <?php elseif ($selectedTab === 'owner'): ?>
                        <?php $owner = getUserById($journalDetails['user_id']); ?>
                        <div class="retro-section">
                            <h3>Владелец журнала</h3>
                            <?php if ($owner): ?>
                                <table class="retro-table">
                                    <tr>
                                        <td>ID:</td>
                                        <td><?= $owner['id'] ?></td>
                                    </tr>
                                    <tr>
                                        <td>Имя:</td>
                                        <td><?= htmlspecialchars($owner['first_name']) ?></td>
                                    </tr>
                                    <tr>
                                        <td>Фамилия:</td>
                                        <td><?= htmlspecialchars($owner['last_name']) ?></td>
                                    </tr>
                                    <tr>
                                        <td>Email:</td>
                                        <td><?= htmlspecialchars($owner['email']) ?></td>
                                    </tr>
                                </table>
                            <?php else: ?>
                                <p class="retro-warning">Владелец не найден</p>
                            <?php endif; ?>
                        </div>
                    <?php elseif ($selectedTab === 'stats'): ?>
                        <?php 
                        $entries = loadJson("data/entries/{$journalDetails['id']}.json");
                        $totalDays = count($entries);
                        $workDays = 0;
                        $totalHours = 0;
                        
                        foreach ($entries as $entry) {
                            if (!isset($entry['is_day_off']) || !$entry['is_day_off']) {
                                $workDays++;
                                $totalHours += calculateHours(
                                    $entry['start_time'] ?? '',
                                    $entry['end_time'] ?? '',
                                    $entry['lunch_minutes'] ?? 0
                                );
                            }
                        }
                        ?>
                        <div class="retro-section">
                            <h3>Статистика журнала</h3>
                            <?php
                            $monthlyStats = [];
                            foreach ($entries as $date => $entry) {
                                $month = date('n', strtotime($date));
                                if (!isset($monthlyStats[$month])) {
                                    $monthlyStats[$month] = [
                                        'total' => 0,
                                        'work' => 0,
                                        'hours' => 0
                                    ];
                                }
                                $monthlyStats[$month]['total']++;
                                if (!isset($entry['is_day_off']) || !$entry['is_day_off']) {
                                    $monthlyStats[$month]['work']++;
                                    $monthlyStats[$month]['hours'] += calculateHours(
                                        $entry['start_time'] ?? '',
                                        $entry['end_time'] ?? '',
                                        $entry['lunch_minutes'] ?? 0
                                    );
                                }
                            }
                            ksort($monthlyStats);
                            ?>
                            <table class="retro-table">
                                <tr>
                                    <th>Месяц</th>
                                    <th>Всего дней</th>
                                    <th>Рабочих дней</th>
                                    <th>Выходных</th>
                                    <th>Общее время (ч)</th>
                                </tr>
                                <?php foreach ($monthlyStats as $month => $stats): ?>
                                <tr>
                                    <td><?= getRussianMonth($month) ?></td>
                                    <td><?= $stats['total'] ?></td>
                                    <td><?= $stats['work'] ?></td>
                                    <td><?= $stats['total'] - $stats['work'] ?></td>
                                    <td><?= number_format($stats['hours'], 2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <tr>
                                    <td><strong>Итого</strong></td>
                                    <td><strong><?= $totalDays ?></strong></td>
                                    <td><strong><?= $workDays ?></strong></td>
                                    <td><strong><?= $totalDays - $workDays ?></strong></td>
                                    <td><strong><?= number_format($totalHours, 2) ?></strong></td>
                                </tr>
                            </table>
                        </div>
                    <?php elseif ($selectedTab === 'access'): ?>
                        <div class="retro-section">
                            <h3>Права доступа</h3>
                            <?php
                            $adminAccess = $journalDetails['admin_access'] ?? [];
                            if (!empty($adminAccess)):
                            ?>
                                <table class="retro-table">
                                    <tr>
                                        <th>ID админа</th>
                                        <th>Имя</th>
                                    </tr>
                                    <?php foreach ($adminAccess as $adminId):
                                        $admin = getUserById($adminId);
                                        if ($admin):
                                    ?>
                                        <tr>
                                            <td><?= $admin['id'] ?></td>
                                            <td><?= htmlspecialchars($admin['last_name'] . ' ' . $admin['first_name']) ?></td>
                                        </tr>
                                    <?php 
                                        endif;
                                    endforeach; ?>
                                </table>
                            <?php else: ?>
                                <p class="retro-warning">Нет назначенных администраторов</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="retro-welcome">
                    <h2>Выберите журнал из списка слева</h2>
                    <p>Для просмотра детальной информации кликните по ID журнала</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
