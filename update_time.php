<?php
session_start();

//  Get client time from POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clientTime = isset($_POST['clientTime']) ? (int)$_POST['clientTime'] : null;
    $timezoneOffset = isset($_POST['timezoneOffset']) ? (int)$_POST['timezoneOffset'] : 0;
    
    if ($clientTime) {
        // Store in session for use throughout the application
        $_SESSION['client_time'] = $clientTime;
        $_SESSION['timezone_offset'] = $timezoneOffset;
        $_SESSION['time_diff'] = $clientTime - time(); // Difference between client and server time
        
        // Return success
        http_response_code(200);
        echo json_encode(['status' => 'success']);
        exit;
    }
}

// Return error if we reach here
http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
exit;
?>
 