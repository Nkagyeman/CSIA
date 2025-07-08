<?php
session_start();
require_once 'db_connection.php';

// Check deadlines and send notifications
function checkDeadlines($userId) {
    global $conn;
    
    // 1. Upcoming deadlines (24h notice)
    $upcomingQuery = $conn->prepare("
        SELECT * FROM assignments 
        WHERE user_id = ? 
        AND deadline BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
        AND reminder_sent = FALSE
    ");
    $upcomingQuery->bind_param("i", $userId);
    $upcomingQuery->execute();
    $upcoming = $upcomingQuery->get_result();
    
    while ($assignment = $upcoming->fetch_assoc()) {
        // Create notification
        $message = "Upcoming deadline: " . $assignment['title'] . " due in " . 
                  round((strtotime($assignment['deadline']) - time()) / 3600) . " hours";
        
        createNotification($userId, "Deadline Approaching", $message, 'warning');
        
        // Mark as notified
        $update = $conn->prepare("UPDATE assignments SET reminder_sent = TRUE WHERE assignment_id = ?");
        $update->bind_param("i", $assignment['assignment_id']);
        $update->execute();
    }
    
    // 2. Past due assignments
    $pastDueQuery = $conn->prepare("
        SELECT * FROM assignments 
        WHERE user_id = ? 
        AND deadline < NOW()
        AND deadline_notification_sent = FALSE
    ");
    $pastDueQuery->bind_param("i", $userId);
    $pastDueQuery->execute();
    $pastDue = $pastDueQuery->get_result();
    
    while ($assignment = $pastDue->fetch_assoc()) {
        $message = "Assignment overdue: " . $assignment['title'] . " was due on " . 
                  date('M j, Y g:i A', strtotime($assignment['deadline']));
        
        createNotification($userId, "Assignment Overdue", $message, 'error');
        
        // Mark as notified
        $update = $conn->prepare("UPDATE assignments SET deadline_notification_sent = TRUE WHERE assignment_id = ?");
        $update->bind_param("i", $assignment['assignment_id']);
        $update->execute();
    }
    
    return ['success' => true];
}

if (isset($_SESSION['user_id'])) {
    echo json_encode(checkDeadlines($_SESSION['user_id']));
}
?>