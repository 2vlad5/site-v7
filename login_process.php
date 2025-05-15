<?php
session_start();
require_once  'functions.php';
require_once 'cleanup_notifications.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $users = loadJson('data/users.json');
    
    foreach ($users as $user) {
        if ($user['username'] === $username && $user['password'] === $password) {
            // Проверка статуса ключа доступа
            if ($user['role'] !== 'creator') {
                $keyStatus = checkAccessKeyStatus($user);
                
                if ($keyStatus === 'missing') {
                    header("Location: index.php?error=no_key");
                    exit();
                } elseif ($keyStatus === 'expired') {
                    header("Location: index.php?error=expired");
                    exit();
                }
                
                // Если ключ истекает скоро, устанавливаем флаг предупреждения
                if ($keyStatus === 'warning') {
                    $_SESSION['key_warning'] = true;
                    $_SESSION['key_expiry'] = $user['access_key_expiry'];
                }
            }
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['first_name'] = $user['first_name'] ?? '';
            $_SESSION['last_name'] = $user['last_name'] ?? '';
            
            // Логируем вход пользователя
            logActivity($user['id'], 'login', "Вход в систему");
            
            // Run cleanup for old notifications on login
            cleanupOldNotifications();
            
            header("Location: dashboard.php");
            exit();
        }
    }
    
    header("Location: index.php?error=invalid");
    exit();
}
 