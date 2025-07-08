<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'studyflow');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize variables
$subject_name = '';
$error = '';
$success = '';

// Form submission handling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject_name = trim($_POST['subject_name']);
    
    // Validate input
    if (empty($subject_name)) {
        $error = "Subject name cannot be empty";
    } elseif (strlen($subject_name) < 3) {
        $error = "Subject name must be at least 3 characters";
    } else {
        // Check if subject already exists
        $stmt = $conn->prepare("SELECT subject_id FROM subjects WHERE user_id = ? AND subject_name = ?");
        $stmt->bind_param("is", $_SESSION['user_id'], $subject_name);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $error = "You already have a subject with this name";
        } else {
            // Insert new subject
            $insert_stmt = $conn->prepare("INSERT INTO subjects (user_id, subject_name) VALUES (?, ?)");
            $insert_stmt->bind_param("is", $_SESSION['user_id'], $subject_name);
            
            if ($insert_stmt->execute()) {
                $success = "Subject added successfully!";
                $subject_name = '';
                
                // Clear any saved draft
                if (isset($_SESSION['unsaved_subject'])) {
                    unset($_SESSION['unsaved_subject']);
                }
            } else {
                $error = "Error adding subject: " . $conn->error;
            }
        }
    }
}

// Check for unsaved draft
if (isset($_SESSION['unsaved_subject'])) {
    $subject_name = $_SESSION['unsaved_subject'];
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Subject - StudyFlow</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-light: #6366f1;
            --secondary: #7c3aed;
            --accent: #ec4899;
            --success: #10b981;
            --danger: #ef4444;
            --light: #f9fafb;
            --dark: #111827;
            --gray: #6b7280;
            --card-bg: #ffffff;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1);
            --transition: all 0.2s ease;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f5f7ff;
            color: var(--dark);
            line-height: 1.5;
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            text-decoration: none;
        }

        .logo-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border-radius: 10px;
        }

        /* Form Container */
        .form-container {
            max-width: 500px;
            margin: 2rem auto;
            padding: 2.5rem;
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: var(--shadow-md);
            position: relative;
            overflow: hidden;
        }

        .form-container::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 6px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }

        .form-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .form-title {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--dark);
        }

        .form-subtitle {
            color: var(--gray);
            font-size: 1rem;
        }

        /* Form Elements */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark);
        }

        .form-input {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            transition: var(--transition);
            background-color: var(--light);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }

        .btn {
            display: block;
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            margin-top: 1.5rem;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        /* Messages */
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: 500;
            text-align: center;
        }

        .alert-error {
            background-color: #fee2e2;
            color: var(--danger);
            border: 1px solid #fecaca;
        }

        .alert-success {
            background-color: #dcfce7;
            color: var(--success);
            border: 1px solid #bbf7d0;
        }

        /* Back Link */
        .back-link {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 1.5rem;
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }

        .back-link:hover {
            color: var(--secondary);
        }

        .back-link i {
            margin-right: 0.5rem;
        }

        /* Animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .form-container {
            animation: fadeIn 0.3s ease-out;
        }

        /* Responsive */
        @media (max-width: 640px) {
            .form-container {
                padding: 1.5rem;
                margin: 1rem;
            }
            
            .form-title {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <a href="dashboard.php" class="logo">
                <div class="logo-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <span>StudyFlow</span>
            </a>
        </header>

        <div class="form-container">
            <div class="form-header">
                <h1 class="form-title">Add New Subject</h1>
                <p class="form-subtitle">Organize your study materials by subject</p>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" id="subjectForm">
                <div class="form-group">
                    <label for="subject_name" class="form-label">Subject Name</label>
                    <input type="text" id="subject_name" name="subject_name" 
                           value="<?php echo htmlspecialchars($subject_name); ?>" 
                           class="form-input dirty-tracker" required
                           placeholder="e.g. Mathematics, Physics, History">
                </div>
                
                <button type="submit" class="btn">
                    <i class="fas fa-plus"></i> Add Subject
                </button>
            </form>
            
            <a href="dashboard.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <script>
        // Track unsaved changes
        document.querySelectorAll('.dirty-tracker').forEach(el => {
            el.addEventListener('input', () => {
                sessionStorage.setItem('subjectDraft', document.getElementById('subject_name').value);
                el.classList.add('dirty');
            });
        });

        // Warn before leaving with unsaved changes
        window.addEventListener('beforeunload', (e) => {
            const dirtyFields = document.querySelectorAll('.dirty');
            if (dirtyFields.length > 0) {
                fetch('save_draft.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `subject_name=${encodeURIComponent(document.getElementById('subject_name').value)}`
                });
                
                e.preventDefault();
                e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
            }
        });

        // Load draft from session storage
        document.addEventListener('DOMContentLoaded', () => {
            const draft = sessionStorage.getItem('subjectDraft');
            if (draft && !document.getElementById('subject_name').value) {
                document.getElementById('subject_name').value = draft;
            }
        });

        // Clear storage on successful form submission
        document.getElementById('subjectForm').addEventListener('submit', () => {
            sessionStorage.removeItem('subjectDraft');
        });
    </script>
</body>
</html>