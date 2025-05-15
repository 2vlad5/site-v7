<?php
session_start();
require_once  'functions.php';

if (isset($_SESSION['user_id'])) {
    // Логируем выход пользователя
    logActivity($_SESSION['user_id'], 'logout', "Выход из системы");
}

session_destroy();
header("Location: index.php");
exit();
 