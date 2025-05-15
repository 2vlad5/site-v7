<?php
session_start();
require_once  'functions.php';
// Include the cleanup functionality
require_once 'cleanup_notifications.php';

$user = checkAccess();
$userId = $user['id'];
$userRole = $user['role'];
$firstName = $user['first_name'];
$lastName = $user['last_name'];

if (!hasTabAccess($userId, 'notifications')) {
    header("Location: dashboard.php");
    exit();
}

// Run cleanup for old notifications when viewing the notifications page
cleanupOldNotifications();

// Check for scheduled file deletions if user has access to server_info_files
if (hasTabAccess($userId, 'server_info_files')) {
    $deletedFiles = loadJson('data/deleted_files.json');
    $currentTime = time();
    $sevenDays = 7 * 24 * 60 * 60;
    $allNotifications = loadJson('data/notifications.json') ?? [];

    foreach ($deletedFiles as $file) {
        $file_already_notified = false;
        $timeLeft = round(($file['deletion_time'] - $currentTime) / 3600, 1); // Calculate time left in hours
        foreach ($allNotifications as $notification) {
             if ($notification['type'] === 'file_deletion' && 
                 ($notification['message'] === "Файл {$file['path']} будет удален через {$timeLeft} часов" || 
                  $notification['message'] === "Файл {$file['path']} был удален")) {
                $file_already_notified = true;
                break;
             }
        }
        $timeLeft = round(($file['deletion_time'] - $currentTime) / 3600, 1); // Hours left
        
        if($file_already_notified) {
            continue;
        }

        if ($file['deletion_time'] > $currentTime && ($file['deletion_time'] - $currentTime) <= $sevenDays) {
            // Add notification about upcoming deletion only if it doesn't exist
            $notificationExists = false;
            foreach ($allNotifications as $existingNotification) {
                if ($existingNotification['type'] === 'file_deletion' && 
                    $existingNotification['user_id'] === $userId && 
                    $existingNotification['message'] === "Файл {$file['path']} будет удален через {$timeLeft} часов") {
                    $notificationExists = true;
                    break;
                }
            }
            
            if (!$notificationExists) {
                $allNotifications[] = [
                    'id' => generateId(),
                    'user_id' => $userId,
                    'type' => 'file_deletion',
                    'title' => 'Запланировано удаление файла',
                    'message' => "Файл {$file['path']} будет удален через {$timeLeft} часов",
                    'created_at' => time(),
                    'is_read' => false
                ];
            }
        } elseif ($file['deletion_time'] <= $currentTime) {
             // Check if the file exists
             if (file_exists($file['path'])) {
                // File not deleted, update notification message
                $message = "Файл {$file['path']} не был удален, будет удален при следующем включении очереди на удаление";
                $title = 'Файл ожидает удаления';
             } else {
                // File has been deleted
                $message = "Файл {$file['path']} удалён";
                $title = 'Файл удалён';
             }
                
             // Check if this notification already exists
             $notificationExists = false;
             foreach ($allNotifications as $notification) {
                 if ($notification['type'] === 'file_deletion' && 
                     $notification['user_id'] === $userId && 
                     $notification['message'] === $message) {
                     $notificationExists = true;
                     break;
                 }
             }
             
             if (!$notificationExists) {
                 $allNotifications[] = [
                     'id' => generateId(),
                     'user_id' => $userId,
                     'type' => 'file_deletion',
                     'title' => $title,
                     'message' => $message,
                     'created_at' => time(),
                     'is_read' => false
                 ];
             }
        }
    }
    saveJson('data/notifications.json', $allNotifications);
}

// Загружаем уведомления пользователя
$allNotifications = loadJson('data/notifications.json') ?? [];
$userNotifications = [];

foreach ($allNotifications as $notification) {
    if ($notification['user_id'] === $userId) {
        $userNotifications[] = $notification;
    }
}

// Сортируем уведомления по времени создания (самые новые первыми)
usort($userNotifications, function($a, $b) {
    return $b['created_at'] - $a['created_at'];
});

// Получаем непрочитанные уведомления для счетчика
$unreadNotifications = 0;
foreach ($userNotifications as $notification) {
    if (!$notification['is_read']) {
        $unreadNotifications++;
    }
}

// Устанавливаем фильтр по типу уведомлений
$filterType = isset($_GET['type']) ? $_GET['type'] : 'all';
$filteredNotifications = [];

foreach ($userNotifications as $notification) {
    if ($filterType === 'all' || $notification['type'] === $filterType) {
        $filteredNotifications[] = $notification;
    }
}

// Обработка действия "Отметить все как прочитанные"
if (isset($_GET['action']) && $_GET['action'] === 'mark_all_read') {
    foreach ($allNotifications as &$notification) {
        if ($notification['user_id'] === $userId && !$notification['is_read']) {
            $notification['is_read'] = true;
            $notification['read_at'] = time();
        }
    }

    saveJson('data/notifications.json', $allNotifications);

    // Перенаправляем на ту же страницу для обновления данных
    header("Location: notifications.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Рабочий журнал - Уведомления</title>
    <meta name="description" content="Built with jdoodle.ai - Система учета рабочего времени с уведомлениями">
    <meta property="og:title" content="Рабочий журнал - Уведомления">
    <meta property="og:description" content="Built with jdoodle.ai - Удобная система учета рабочего времени с возможностью получения уведомлений">
    <meta property="og:image" content="https://images.unsplash.com/photo-1533749047139-189de3cf06d3?ixid=M3w3MjUzNDh8MHwxfHNlYXJjaHwxfHxjYWxlbmRhciUyMG5vdGlmaWNhdGlvbiUyMG1zY2hlZHVsZSUyMG1pbWUlMjBtYW5hZ2VtZW50fGVufDB8fHx8MTc0MzE2ODcwMHww&ixlib=rb-4.0.3&fit=fillmax&h=450&w=800">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .notification-shift {
            background-color: #e3f2fd;
        }

        .notification-deletion {
            background-color: #ffebee;
        }

        .notification-deletion.notification-unread {
            border-left: 4px solid #f44336;
        }
    </style>
</head>
<body>
    <div class="dashboard">

        <?php renderMenu($userId, $userRole, $firstName, $lastName); ?>

        <div class="main-content">
            <div class="header">
                <h1>Уведомления</h1>
                <div>
                    <?php if ($unreadNotifications > 0): ?>
                        <a href="notifications.php?action=mark_all_read" class="btn"><i class="fas fa-check-double"></i> Отметить все как прочитанные</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Фильтры по типу уведомлений -->
            <div class="notifications-filter">
                <a href="notifications.php?type=all" class="filter-btn <?= $filterType === 'all' ? 'active' : '' ?>">Все</a>
                <a href="notifications.php?type=shift_assignment" class="filter-btn <?= $filterType === 'shift_assignment' ? 'active' : '' ?>">Назначения смен</a>
                <a href="notifications.php?type=file_deletion" class="filter-btn <?= $filterType === 'file_deletion' ? 'active' : '' ?>">Файловая система</a>
            </div>

            <!-- Список уведомлений -->
            <div class="notifications-list">
                <?php if (empty($filteredNotifications)): ?>
                    <div class="empty-notifications">
                        <i class="fas fa-bell-slash"></i>
                        <p>У вас нет уведомлений</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($filteredNotifications as $notification): ?>
                        <?php
                        // Определяем иконку - оставляем только для смен
                        $icon = 'fa-bell';
                        $typeClass = '';

                        if ($notification['type'] === 'shift_assignment') {
                            $icon = 'fa-calendar-check';
                            $typeClass = 'notification-shift';
                        } elseif ($notification['type'] === 'file_deletion') {
                            $icon = 'fa-trash';
                            $typeClass = 'notification-deletion';
                        }

                        // Определяем класс для непрочитанных уведомлений
                        $unreadClass = $notification['is_read'] ? '' : 'notification-unread';

                        // Форматируем дату
                        $notificationDate = date('d.m.Y H:i', $notification['created_at']);

                        // Определяем, есть ли ссылка на журнал
                        $hasJournalLink = isset($notification['journal_id']) && isset($notification['date']);

                        if ($hasJournalLink) {
                            list($year, $month, $day) = explode('-', $notification['date']);
                        }
                        ?>
                        <div class="notification-item <?= $typeClass ?> <?= $unreadClass ?>">
                            <div class="notification-icon">
                                <i class="fas <?= $icon ?>"></i>
                            </div>
                            <div class="notification-content">
                                <div class="notification-header">
                                    <h3><?= htmlspecialchars($notification['title']) ?></h3>
                                    <span class="notification-time"><?= $notificationDate ?></span>
                                </div>
                                <div class="notification-message">
                                    <?= htmlspecialchars($notification['message']) ?>
                                </div>
                                <?php if ($hasJournalLink): ?>
                                <div class="notification-actions">
                                    <a href="journal.php?id=<?= $notification['journal_id'] ?>&month=<?= $month ?>&day=<?= $day ?>" class="btn btn-sm">Перейти к журналу</a>
                                    <?php if (!$notification['is_read']): ?>
                                    <a href="mark_notification_read.php?id=<?= $notification['id'] ?>" class="btn btn-sm">Отметить как прочитанное</a>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>