<?php
session_start();
if (!isset($_SESSION['pending_logout'])) {
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Confirm Logout</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f5f7fa;
            margin: 0;
            padding: 40px;
            text-align: center;
        }
        .confirmation-box {
            max-width: 500px;
            margin: 100px auto;
            padding: 40px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .btn {
            padding: 12px 24px;
            margin: 10px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s;
        }
        .btn-confirm {
            background: #e74c3c;
            color: white;
        }
        .btn-cancel {
            background: #ecf0f1;
            color: #333;
        }
    </style>
    <script>
        // Client-side check for unsaved changes
        window.addEventListener('beforeunload', function(e) {
            if (document.querySelector('input.dirty') || document.querySelector('textarea.dirty')) {
                e.preventDefault();
                e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
            }
        });
    </script>
</head>
<body>
    <div class="confirmation-box">
        <h2>You have unsaved changes</h2>
        <p>Are you sure you want to log out? All unsaved changes will be lost.</p>
        
        <form method="POST" action="logout.php">
            <button type="submit" name="confirm_logout" class="btn btn-confirm">Log Out Anyway</button>
            <a href="dashboard.php" class="btn btn-cancel">Cancel</a>
        </form>
    </div>
</body>
</html>