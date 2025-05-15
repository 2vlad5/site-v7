<?php
require_once  'functions.php';

// This script will delete shift assignment notifications that are more than 3 days old
function cleanupOldNotifications() {
    // Load all notifications
    $notifications = loadJson('data/notifications.json');
    $updatedNotifications = [];
    $cleanupCount = 0;
    
    // Current time
    $currentTime = time();
    // 3 days in seconds
    $threeDay = 3 * 24 * 60 * 60;
    
    foreach ($notifications as $notification) {
        // Only process shift assignment notifications
        if ($notification['type'] === 'shift_assignment') {
            // Check if notification date is in the past
            if (isset($notification['date'])) {
                $notificationDate = strtotime($notification['date']);
                
                // If notification date is more than 3 days in the past, skip it (don't add to updated list)
                if ($notificationDate < ($currentTime - $threeDay)) {
                    $cleanupCount++;
                    continue;
                }
            }
        }
        
        // Keep all other notifications
        $updatedNotifications[] = $notification;
    }
    
    // Save the updated notifications list only if changes were made
    if ($cleanupCount > 0) {
        saveJson('data/notifications.json', $updatedNotifications);
        return $cleanupCount;
    }
    
    return 0;
}

// Execute the cleanup when this script is called directly
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    $count = cleanupOldNotifications();
    echo "Cleanup complete. Removed $count old shift assignment notifications.\n";
}
?>
 