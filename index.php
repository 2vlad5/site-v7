<?php
session_start();
if   (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Рабочий журнал - Вход</title>
    <meta name="description" content="Built with jdoodle.ai - Система учета рабочего времени с журналами по месяцам">
    <meta property="og:title" content="Рабочий журнал - Система учета рабочего времени">
    <meta property="og:description" content="Built with jdoodle.ai - Удобная система учета рабочего времени с возможностью экспорта в Excel">
    <meta property="og:image" content="https://images.unsplash.com/photo-1507208773393-40d9fc670acf?ixid=M3w3MjUzNDh8MHwxfHNlYXJjaHwxfHxtb2Rlcm4lMjBvZmZpY2UlMjB3b3Jrc3BhY2UlMjB0ZWFtd29ya3xlbnwwfHx8fDE3NDI5MjM1OTV8MA&ixlib=rb-4.0.3&fit=fillmax&h=450&w=800">
    <link rel="stylesheet" href="css/style.css">
    <link rel="icon" href="favicon.ico" type="image/x-icon">
    <!-- Include time synchronization script -->
    <script src="time_sync.js"></script>
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-image">
            <img src="https://images.unsplash.com/photo-1507208773393-40d9fc670acf?ixid=M3w3MjUzNDh8MHwxfHNlYXJjaHwxfHxtb2Rlcm4lMjBvZmZpY2UlMjB3b3Jrc3BhY2UlMjB0ZWFtd29ya3xlbnwwfHx8fDE3NDI5MjM1OTV8MA&ixlib=rb-4.0.3&fit=fillmax&h=450&w=800" alt="Рабочее пространство">
        </div>
        <div class="login-form">
            <h1>Рабочий журнал</h1>
            <form action="login_process.php" method="post">
                <?php if (isset($_GET['error']) && $_GET['error'] == 'invalid'): ?>
                    <div class="error-message">Неверный логин или пароль</div>
                <?php endif; ?>
                <?php if (isset($_GET['error']) && $_GET['error'] == 'expired'): ?>
                    <div class="error-message">Срок действия ключа доступа истек. Обратитесь к администратору.</div>
                <?php endif; ?>
                <?php if (isset($_GET['error']) && $_GET['error'] == 'no_key'): ?>
                    <div class="error-message">У вас отсутствует ключ доступа. Обратитесь к администратору.</div>
                <?php endif; ?>
                <div class="form-group">
                    <label for="username">Логин</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password">Пароль</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="btn-login">Войти</button>
            </form>
            <div id="current-time" style="margin-top: 20px; text-align: center; color: #666;"></div>
        </div>
    </div>

    <script>
        // Display current client time to show that time is correct
        function updateCurrentTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString();
            document.getElementById('current-time').textContent = 'Текущее время: ' + timeString;
        }
        
        // Update time every second
        updateCurrentTime();
        setInterval(updateCurrentTime, 1000);
    </script>
</body>
</html>
 