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
$firstName = $user['first_name'];
$lastName = $user['last_name'];

if (!hasTabAccess($userId, 'scheduler')) {
    header("Location: dashboard.php");
    exit();
}

// Define available cron tasks
$cronTasks = [
    [
        'id' => 'cleanup_notifications',
        'name' => 'Очистка устаревших уведомлений',
        'description' => 'Удаляет уведомления о назначенных сменах, которые старше 3 дней.',
        'file' => 'cleanup_notifications.php'
    ],
    [
        'id' => 'cron_tasks',
        'name' => 'Все задачи',
        'description' => 'Запускает все запланированные задачи (очистка уведомлений и другие автоматические процессы).',
        'file' => 'cron_tasks.php'
    ]
];

// Handle task execution
$executionResult = '';
$executionStatus = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['task_id'])) {
    $taskId = $_POST['task_id'];
    
    // Find the task
    $task = null;
    foreach ($cronTasks as $t) {
        if ($t['id'] === $taskId) {
            $task = $t;
            break;
        }
    }
    
    if ($task) {
        // Execute the task
        ob_start();
        $startTime = microtime(true);
        
        include_once($task['file']);
        
        $executionTime = round((microtime(true) - $startTime) * 1000);
        $output = ob_get_clean();
        
        // Log the execution
        logActivity($userId, 'scheduler_run', "Запустил задачу планировщика: {$task['name']}", $taskId);
        
        $executionResult = $output;
        $executionStatus = "Задача \"{$task['name']}\" выполнена за {$executionTime} мс";
    }
}

// Get task execution history from activity log
$activityLogs = loadJson('data/activity_log.json');
$taskLogs = [];

foreach ($activityLogs as $log) {
    if ($log['action'] === 'scheduler_run' || $log['action'] === 'cleanup') {
        $taskLogs[] = $log;
    }
}

// Sort logs by timestamp (newest first)
usort($taskLogs, function($a, $b) {
    return $b['timestamp'] - $a['timestamp'];
});

// Limit to last 20 entries
$taskLogs = array_slice($taskLogs, 0, 20);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Рабочий журнал - Планировщик</title>
    <meta name="description" content="Built with jdoodle.ai - Система учета рабочего времени с планировщиком задач">
    <meta property="og:title" content="Рабочий журнал - Планировщик задач">
    <meta property="og:description" content="Built with jdoodle.ai - Удобная система учета рабочего времени с автоматизацией процессов">
    <meta property="og:image" content="https://images.unsplash.com/photo-1501139083538-0139583c060f?ixid=M3w3MjUzNDh8MHwxfHNlYXJjaHwxfHxzY2hlZHVsZXIlMjBjYWxlbmRhciUyMGNsb2NrJTIwdGltZSUyMG1hbmFnZW1lbnQlMjBhdXRvbWF0aW9ufGVufDB8fHx8MTc0MzE3MDQwM3ww&ixlib=rb-4.0.3&fit=fillmax&h=450&w=800">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .scheduler-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .scheduler-header {
            display: flex;
            padding: 20px;
            background-color: var(--primary-color);
            color: white;
            align-items: center;
        }
        
        .scheduler-header-image {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            overflow: hidden;
            margin-right: 20px;
            border: 3px solid white;
        }
        
        .scheduler-header-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .scheduler-header-content h2 {
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .scheduler-body {
            padding: 20px;
        }
        
        .task-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .task-card {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s, box-shadow 0.2s;
            border-left: 4px solid var(--primary-color);
        }
        
        .task-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .task-card h3 {
            margin-bottom: 10px;
            color: var(--dark-color);
            display: flex;
            align-items: center;
        }
        
        .task-card h3 i {
            margin-right: 10px;
            color: var(--primary-color);
        }
        
        .task-card p {
            margin-bottom: 15px;
            color: #666;
        }
        
        .execution-result {
            background-color: #f0f8ff;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
            border-left: 4px solid var(--primary-color);
        }
        
        .execution-result h3 {
            margin-bottom: 15px;
            color: var(--primary-color);
            display: flex;
            align-items: center;
        }
        
        .execution-result h3 i {
            margin-right: 10px;
        }
        
        .execution-output {
            background-color: #f8f9fa;
            border-radius: 4px;
            padding: 15px;
            font-family: monospace;
            white-space: pre-wrap;
            overflow-x: auto;
            border: 1px solid #ddd;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .execution-history {
            margin-top: 30px;
        }
        
        .execution-history h3 {
            margin-bottom: 15px;
            color: var(--dark-color);
            display: flex;
            align-items: center;
        }
        
        .execution-history h3 i {
            margin-right: 10px;
            color: var(--primary-color);
        }
        
        .history-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .history-table th, .history-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .history-table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        
        .history-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .scheduler-info {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid var(--warning-color);
        }
        
        .scheduler-info p {
            margin-bottom: 10px;
        }
        
        .scheduler-info strong {
            color: var(--dark-color);
        }
    </style>
</head>
<body>
    <div class="dashboard">

        <?php renderMenu($userId, $userRole, $firstName, $lastName); ?>

        <div class="main-content">
            <div class="header">
                <h1>Планировщик задач</h1>
            </div>
            
            <div class="scheduler-container">
                <div class="scheduler-header">
                    <div class="scheduler-header-image">
                        <img src="https://images.unsplash.com/photo-1501139083538-0139583c060f?ixid=M3w3MjUzNDh8MHwxfHNlYXJjaHwxfHxzY2hlZHVsZXIlMjBjYWxlbmRhciUyMGNsb2NrJTIwdGltZSUyMG1hbmFnZW1lbnQlMjBhdXRvbWF0aW9ufGVufDB8fHx8MTc0MzE3MDQwM3ww&ixlib=rb-4.0.3&fit=fillmax&h=100&w=100" alt="Планировщик">
                    </div>
                    <div class="scheduler-header-content">
                        <h2>Планировщик задач</h2>
                        <p>Управление автоматическими задачами системы</p>
                    </div>
                </div>
                
                <div class="scheduler-body">
                    <div class="scheduler-info">
                        <p><strong>Планировщик задач</strong> позволяет запускать автоматические процессы вручную и отслеживать их выполнение.</p>
                        <p>Для настоящей автоматизации рекомендуется настроить cron-задачи на сервере, указав путь к файлу <code>cron_tasks.php</code>.</p>
                        <p>Пример настройки cron-задачи для ежедневного запуска в полночь:</p>
                        <pre><code>0 0 * * * php /path/to/your/cron_tasks.php</code></pre>
                    </div>
                    
                    <div class="task-list">
                        <?php foreach ($cronTasks as $task): ?>
                            <div class="task-card">
                                <h3><i class="fas fa-tasks"></i> <?= htmlspecialchars($task['name']) ?></h3>
                                <p><?= htmlspecialchars($task['description']) ?></p>
                                <form action="scheduler.php" method="post">
                                    <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                    <button type="submit" class="btn btn-primary">Выполнить</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if (!empty($executionResult)): ?>
                        <div class="execution-result">
                            <h3><i class="fas fa-terminal"></i> Результат выполнения</h3>
                            <p><?= $executionStatus ?></p>
                            <div class="execution-output"><?= htmlspecialchars($executionResult) ?></div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="execution-history">
                        <h3><i class="fas fa-history"></i> История выполнения задач</h3>
                        
                        <?php if (empty($taskLogs)): ?>
                            <div class="alert">
                                История выполнения задач пуста.
                            </div>
                        <?php else: ?>
                            <table class="history-table">
                                <thead>
                                    <tr>
                                        <th>Дата и время</th>
                                        <th>Задача</th>
                                        <th>Результат</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($taskLogs as $log): ?>
                                        <tr>
                                            <td><?= date('d.m.Y H:i:s', $log['timestamp']) ?></td>
                                            <td><?= htmlspecialchars($log['description']) ?></td>
                                            <td>
                                                <?php if ($log['action'] === 'cleanup'): ?>
                                                    <span class="text-success">Автоматически</span>
                                                <?php else: ?>
                                                    <span class="text-info">Вручную</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
 