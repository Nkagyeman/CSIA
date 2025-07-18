<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Rate limiting (5 attempts per 5 minutes)
$rate_limit_key = 'reg_' . md5($_SERVER['REMOTE_ADDR']);
$attempts = $_SESSION[$rate_limit_key]['attempts'] ?? 0;
$last_attempt = $_SESSION[$rate_limit_key]['time'] ?? 0;
$time_window = 300; // 5 minutes in seconds

// Only enforce rate limit if there were previous failed attempts
if ($attempts >= 5 && (time() - $last_attempt < $time_window)) {
    die("<p class='error-message'>Too many attempts. Please try again in 5 minutes.</p>");
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION[$rate_limit_key] = time();
    
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Invalid form submission";
    } else {
        $conn = new mysqli('localhost', 'root', '', 'studyflow');
        
        if ($conn->connect_error) {
            die("<p class='error-message'>Database connection failed: " . $conn->connect_error . "</p>");
        }

               $username = $conn->real_escape_string(trim($_POST['username']));
        $email = $conn->real_escape_string(trim($_POST['email'])); // <-- Add this line
        $password = trim($_POST['password']);
        $confirm_password = trim($_POST['confirm_password']);

        // Client-side should validate first, but server-side is mandatory
        if (strlen($username) < 4) {
            $error = "Username must be at least 4 characters";
        } elseif (strlen($password) < 8) {
            $error = "Password must be at least 8 characters";
        } elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password) || !preg_match('/[^A-Za-z0-9]/', $password)) {
            $error = "Password must contain uppercase, number, and special character";
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match!";
        } elseif (!empty($username)) {
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $error = "Username already exists!";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt->close(); // Close previous SELECT stmt

                $stmt = $conn->prepare("INSERT INTO users (username, password, email) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $username, $hashed_password, $email);

                if ($stmt->execute()) {
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    header("Location: register.php?registered=1");
                    exit();
                } else {
                    $error = "Registration failed: " . $conn->error;
                }
            }
            $stmt->close();

        }
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Study Flow - Register</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f5f7fa;
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }
        
        .main-logo {
            position: absolute;
            top: 40px;
            left: 40px;
            font-size: 36px;
            font-weight: 800;
            color: #3a7bd5;
            text-decoration: none;
        }
        
        .login-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 80px 20px;
            box-sizing: border-box;
        }
        
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
        
        .login-form input {
            width: 100%;
            padding: 18px;
            margin: 15px 0;
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
            margin-top: 25px;
            transition: background-color 0.3s;
        }
        
        .login-form button:hover {
            background-color: #2c65c4;
        }
        
        .login-prompt {
            margin-top: 25px;
            color: #666;
            font-size: 18px;
        }
        
        .login-prompt a {
            color: #3a7bd5;
            text-decoration: none;
            font-weight: 600;
        }
        
        .error-message {
            color: #e74c3c;
            margin-bottom: 25px;
            font-size: 16px;
        }
        
        .success-message {
            color: #2ecc71;
            margin-bottom: 25px;
            font-size: 16px;
        }
        
        .password-rules {
            text-align: left;
            margin: 10px 0;
            font-size: 14px;
            color: #666;
        }
    </style>
    <script>
        function validateForm() {
            const password = document.querySelector('input[name="password"]').value;
            const confirm = document.querySelector('input[name="confirm_password"]').value;
            const errorElement = document.getElementById('js-errors');
            
            errorElement.innerHTML = '';
            
            // Client-side validation
            if (password.length < 8) {
                errorElement.innerHTML += '<li>Password must be at least 8 characters</li>';
            }
            if (!/[A-Z]/.test(password)) {
                errorElement.innerHTML += '<li>Password needs at least one uppercase letter</li>';
            }
            if (!/[0-9]/.test(password)) {
                errorElement.innerHTML += '<li>Password needs at least one number</li>';
            }
            if (!/[^A-Za-z0-9]/.test(password)) {
                errorElement.innerHTML += '<li>Password needs at least one special character</li>';
            }
            if (password !== confirm) {
                errorElement.innerHTML += '<li>Passwords do not match</li>';
            }
            
            if (errorElement.innerHTML !== '') {
                errorElement.innerHTML = '<ul>' + errorElement.innerHTML + '</ul>';
                return false;
            }
            return true;
        }
        
        // Live password strength indicator
        function checkPasswordStrength() {
            const password = document.querySelector('input[name="password"]').value;
            const strengthBar = document.getElementById('password-strength');
            let strength = 0;
            
            if (password.length >= 8) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            strengthBar.style.width = (strength * 25) + '%';
            strengthBar.style.backgroundColor = 
                strength === 4 ? '#2ecc71' : 
                strength >= 2 ? '#f39c12' : '#e74c3c';
        }
    </script>
</head>
<body>
    <a href="login.php" class="main-logo">STUDYFLOW</a>
    
    <div class="login-wrapper">
        <div class="login-box">
            <div class="welcome-message">Create your<br>Study Flow account!</div>
            
            <?php if (!empty($error)): ?>
                <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            
            <?php if (isset($_GET['registered'])): ?>
                <p class="success-message">Registration successful! Please <a href="login.php">login</a>.</p>
            <?php endif; ?>
            
            <div id="js-errors" class="error-message"></div>
            
            <form class="login-form" method="POST" action="register.php" onsubmit="return validateForm()">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <input type="text" name="username" placeholder="Username (min 4 chars)" required minlength="4">
                
                  <input type="email" name="email" placeholder="Email" id="email" required>
                
                <input type="password" name="password" placeholder="Password" required 
                       oninput="checkPasswordStrength()">
                
                <div class="password-rules">
                    <div style="height: 5px; background: #eee; margin-bottom: 5px;">
                        <div id="password-strength" style="height: 100%; width: 0%; transition: all 0.3s;"></div>
                    </div>
                    Password must contain:
                    <ul style="margin: 5px 0 0 20px;">
                        <li>At least 8 characters</li>
                        <li>1 uppercase letter</li>
                        <li>1 number</li>
                        <li>1 special character</li>
                    </ul>
                </div>
                
                <input type="password" name="confirm_password" placeholder="Confirm Password" required>
                
                <button type="submit">Register</button>
            </form>
            
            <div class="login-prompt">Already have an account? <a href="login.php">Log in</a></div>
        </div>
    </div>
</body>
</html>