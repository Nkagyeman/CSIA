<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Generate CSRF token for login form
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Rate limiting (3 attempts per minute)
$rate_limit_key = 'login_' . md5($_SERVER['REMOTE_ADDR']);
$attempts = $_SESSION[$rate_limit_key]['attempts'] ?? 0;
$last_attempt = $_SESSION[$rate_limit_key]['time'] ?? 0;

if (time() - $last_attempt < 60 && $attempts >= 3) {
    die("<p class='error-message'>Too many attempts. Please wait 1 minute.</p>");
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update rate limiting counters
    $_SESSION[$rate_limit_key] = [
        'attempts' => $attempts + 1,
        'time' => time()
    ];
    
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Invalid form submission";
    } else {
        $conn = new mysqli('localhost', 'root', '', 'studyflow');
        
        if ($conn->connect_error) {
            die("<p class='error-message'>Database connection failed: " . $conn->connect_error . "</p>");
        }

        $username = $conn->real_escape_string(trim($_POST['username']));
        $password = trim($_POST['password']);

        // Use prepared statement
        $stmt = $conn->prepare("SELECT user_id, password FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                // Successful login - reset attempt counter
                unset($_SESSION[$rate_limit_key]);
                
                // Regenerate session ID to prevent fixation
                session_regenerate_id(true);
                
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['last_activity'] = time();
                
                // Regenerate CSRF token
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                
                header("Location: dashboard.php");
                exit();
            } else {
                $error = "Invalid credentials";
            }
        } else {
            $error = "Invalid credentials";
        }
        $stmt->close();
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Study Flow - Login</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f5f7fa;
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }
        
        /* Logo styling - now a link you can customize */
        .main-logo {
            position: absolute;
            top: 40px;
            left: 40px;
            font-size: 36px;
            font-weight: 800;
            color: #3a7bd5;
            text-decoration: none;
        }
        
        /* Larger container */
        .login-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 80px 20px;
            box-sizing: border-box;
        }
        
        /* Bigger form box */
        .login-box {
            width: 500px;
            padding: 60px;
            background-color: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .welcome-message {
            font-size: 28px;
            color: #333;
            margin-bottom: 50px;
            line-height: 1.3;
        }
        
        /* Larger form elements */
        .login-form input {
            width: 100%;
            padding: 18px;
            margin: 20px 0;
            border: 2px solid #eee;
            border-radius: 12px;
            box-sizing: border-box;
            font-size: 18px;
            transition: border-color 0.3s;
        }
        
        .login-form input:focus {
            border-color: #3a7bd5;
            outline: none;
        }
        
        .login-form button {
            width: 100%;
            padding: 18px;
            background-color: #3a7bd5;
            color: white;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-weight: bold;
            font-size: 18px;
            margin-top: 30px;
            transition: background-color 0.3s;
        }
        
        .login-form button:hover {
            background-color: #2c65c4;
        }
        
        .signup-prompt {
            margin-top: 30px;
            color: #666;
            font-size: 18px;
        }
        
        .signup-prompt a {
            color: #3a7bd5;
            text-decoration: none;
            font-weight: 600;
        }
        
        .error-message {
            color: #e74c3c;
            margin-bottom: 25px;
            font-size: 16px;
        }
    </style>
</head>
<body>
    <a href="login.php" class="main-logo">STUDYFLOW</a>
    
    <div class="login-wrapper">
        <div class="login-box">
            <div class="welcome-message">Welcome to<br>Study Flow!</div>
            
            <?php if (!empty($error)): ?>
                <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            
            <div class="login-form">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <input type="text" name="username" placeholder="Username" required>
                    <input type="password" name="password" placeholder="Password" required>
                    <button type="submit">Login</button>
                </form>
                <div class="signup-prompt">New user? <a href="register.php">Sign up</a></div>
            </div>
        </div>
    </div>
</body>
</html>