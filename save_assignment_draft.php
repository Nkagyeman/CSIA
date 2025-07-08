<?php
session_start();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $data = json_decode(file_get_contents('php://input'), true);
    $_SESSION['unsaved_assignment'] = [
        'subject_id' => $data['subject_id'],
        'title' => $data['title'],
        'description' => $data['description'],
        'deadline' => $data['deadline'],
        'priority' => $data['priority']
    ];
}
?>