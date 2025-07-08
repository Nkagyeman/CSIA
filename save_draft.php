<?php
session_start();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $_SESSION['unsaved_subject'] = $_POST['subject_name'];
}
?>