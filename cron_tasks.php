<?php
//  This file contains tasks that should be run periodically

// Include required files
require_once 'functions.php';
require_once 'cleanup_notifications.php';

// Clean up old notifications (shift assignments older than 3 days)
$cleanupCount = cleanupOldNotifications();

// Log the cleanup activity if notifications were removed
if ($cleanupCount > 0) {
    // We'll use the system user ID for logging
    $systemUserId = 'system';
    
    // Log the activity
    logActivity($systemUserId, 'cleanup', "Автоматическая очистка: удалено $cleanupCount устаревших уведомлений о сменах");
}

echo "Cron tasks completed successfully.\n";
echo "- Notifications cleanup: removed $cleanupCount old notifications\n";
?>
 