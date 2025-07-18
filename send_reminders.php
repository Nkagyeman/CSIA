<?php
require 'vendor/autoload.php'; // Composer autoload for SendGrid

// CONFIGURE THIS FIRST
$SENDGRID_API_KEY = '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'; // Replace this
$SENDER_EMAIL = 'nkaagyeman@gmail.com';      // Verified sender on SendGrid
$SENDER_NAME = 'StudyFlow Reminders';

// --- DB Connection ---
$conn = new mysqli('127.0.0.1', 'root', '', 'studyflow');
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Set timezone
date_default_timezone_set('Africa/Accra');

// Define reminder time window (now to 1 hour ahead)
$now = date('Y-m-d H:i:s');
$next_hour = date('Y-m-d H:i:s', strtotime('+1 hour'));

// Fetch assignments due within 1 hour
$sql = "
    SELECT a.title, a.deadline, u.email, u.username
    FROM assignments a
    JOIN users u ON a.user_id = u.user_id
    WHERE a.deadline BETWEEN ? AND ? AND a.reminder_sent = 0

";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $now, $next_hour);
$stmt->execute();
$result = $stmt->get_result();

$assignments = [];
while ($row = $result->fetch_assoc()) {
    $assignments[] = $row;
}
$stmt->close();
$conn->close();

// --- Send Email Reminders ---
$sendgrid = new \SendGrid($SENDGRID_API_KEY);

foreach ($assignments as $task) {
    $email = new \SendGrid\Mail\Mail();
    $email->setFrom($SENDER_EMAIL, $SENDER_NAME);
    $email->setSubject("⏰ Upcoming Assignment: " . $task['title']);
    $email->addTo($task['email'], $task['username']);

    $formatted_deadline = date("g:i A, M j", strtotime($task['deadline']));
    $htmlContent = "
        Hello " . htmlspecialchars($task['username']) . ",<br><br>
        This is a friendly reminder from <strong>StudyFlow</strong>.<br>
        Your assignment <strong>" . htmlspecialchars($task['title']) . "</strong> is due at <strong>$formatted_deadline</strong>.<br><br>
        Please make sure to complete and submit it on time.<br><br>
        – StudyFlow Team
    ";

    $email->addContent("text/html", $htmlContent);

    try {
        $response = $sendgrid->send($email);
        $status = $response->statusCode();
        echo "Email sent to: " . $task['email'] . " (Status: $status)\n";
        if ($status < 400) {
    // Connect and mark reminder as sent
    $conn = new mysqli('127.0.0.1', 'root', '', 'studyflow');
    if (!$conn->connect_error) {
        $update = $conn->prepare("UPDATE assignments SET reminder_sent = 1 WHERE title = ? AND deadline = ?");
        $update->bind_param("ss", $task['title'], $task['deadline']);
        $update->execute();
        $update->close();
        $conn->close();
    }
}

        if ($status >= 400) {
            echo "❌ Error details: " . $response->body() . "\n";
        }
    } catch (Exception $e) {
        echo "❌ Exception: " . $e->getMessage() . "\n";
    }
}
