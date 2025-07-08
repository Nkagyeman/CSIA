<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Redirect if not logged in
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
$assignment_data = [
    'subject_id' => '',
    'title' => '',
    'description' => '',
    'deadline' => date('Y-m-d\TH:i'),
    'priority' => 'medium'
];
$error = '';
$success = '';

// Rate limiting - NEW CODE ADDED
$rate_limit_key = 'assignment_add_' . $_SESSION['user_id'];
$max_attempts = 5; // Maximum allowed attempts
$time_period = 60; // Time period in seconds (1 minute)

// Get user's subjects for dropdown
$subjects = [];
$stmt = $conn->prepare("SELECT subject_id, subject_name FROM subjects WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $subjects[$row['subject_id']] = $row['subject_name'];
}
$stmt->close();

// Form submission handling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check rate limit - NEW CODE ADDED
    $current_time = time();
    $attempts = $_SESSION[$rate_limit_key]['attempts'] ?? 0;
    $last_attempt_time = $_SESSION[$rate_limit_key]['time'] ?? 0;
    
    if ($current_time - $last_attempt_time < $time_period && $attempts >= $max_attempts) {
        $error = "You've added too many assignments recently. Please wait a minute before adding another.";
    } else {
        $assignment_data = [
            'subject_id' => $_POST['subject_id'],
            'title' => trim($_POST['title']),
            'description' => trim($_POST['description']),
            'deadline' => $_POST['deadline'],
            'priority' => $_POST['priority']
        ];

        // Validate input
        if (empty($assignment_data['subject_id'])) {
            $error = "Please select a subject";
        } elseif (empty($assignment_data['title'])) {
            $error = "Assignment title cannot be empty";
        } elseif (strtotime($assignment_data['deadline']) < time()) {
            $error = "Deadline must be in the future";
        } else {
            // Update rate limiting counters - NEW CODE ADDED
            if ($current_time - $last_attempt_time > $time_period) {
                // Reset counter if time period has passed
                $_SESSION[$rate_limit_key] = [
                    'attempts' => 1,
                    'time' => $current_time
                ];
            } else {
                // Increment counter
                $_SESSION[$rate_limit_key] = [
                    'attempts' => $attempts + 1,
                    'time' => $last_attempt_time
                ];
            }

            // Insert new assignment
            $stmt = $conn->prepare("INSERT INTO assignments 
                                  (user_id, subject_id, title, description, deadline, priority) 
                                  VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iissss", 
                $_SESSION['user_id'],
                $assignment_data['subject_id'],
                $assignment_data['title'],
                $assignment_data['description'],
                $assignment_data['deadline'],
                $assignment_data['priority']
            );
            
            if ($stmt->execute()) {
                $success = "Assignment added successfully!";
                $assignment_data = [
                    'subject_id' => '',
                    'title' => '',
                    'description' => '',
                    'deadline' => date('Y-m-d\TH:i'),
                    'priority' => 'medium'
                ];
                
                // Clear any saved draft
                if (isset($_SESSION['unsaved_assignment'])) {
                    unset($_SESSION['unsaved_assignment']);
                }
            } else {
                $error = "Error adding assignment: " . $conn->error;
            }
            $stmt->close();
        }
    }
}

// Check for unsaved draft
if (isset($_SESSION['unsaved_assignment'])) {
    $assignment_data = array_merge($assignment_data, $_SESSION['unsaved_assignment']);
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Assignment | StudyFlow</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-light: #6366f1;
            --secondary: #7c3aed;
            --accent: #ec4899;
            --success: #10b981;
            --warning: #f59e0b;
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
            max-width: 600px;
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

        .form-textarea {
            min-height: 120px;
            resize: vertical;
        }

        /* Priority Options */
        .priority-options {
            display: flex;
            gap: 1rem;
            margin-top: 0.5rem;
        }

        .priority-option {
            flex: 1;
            position: relative;
        }

        .priority-option input {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }

        .priority-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            background: var(--light);
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            cursor: pointer;
            transition: var(--transition);
            text-align: center;
        }

        .priority-option input:checked + .priority-label {
            border-color: var(--primary);
            background: rgba(79, 70, 229, 0.05);
        }

        .priority-icon {
            font-size: 1.25rem;
            margin-bottom: 0.5rem;
        }

        .priority-low .priority-icon {
            color: var(--success);
        }

        .priority-medium .priority-icon {
            color: var(--warning);
        }

        .priority-high .priority-icon {
            color: var(--danger);
        }

        /* Button */
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
            
            .priority-options {
                flex-direction: column;
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
                <h1 class="form-title">Add New Assignment</h1>
                <p class="form-subtitle">Organize your tasks with deadlines and priorities</p>
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
            
            <form method="POST" id="assignmentForm">
                <div class="form-group">
                    <label for="subject_id" class="form-label">Subject</label>
                    <select id="subject_id" name="subject_id" class="form-input dirty-tracker" required>
                        <option value="">-- Select Subject --</option>
                        <?php foreach ($subjects as $id => $name): ?>
                            <option value="<?php echo $id; ?>" 
                                <?php echo ($id == $assignment_data['subject_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="title" class="form-label">Assignment Title</label>
                    <input type="text" id="title" name="title" 
                           value="<?php echo htmlspecialchars($assignment_data['title']); ?>" 
                           class="form-input dirty-tracker" required
                           placeholder="Enter assignment title">
                </div>
                
                <div class="form-group">
                    <label for="description" class="form-label">Description (Optional)</label>
                    <textarea id="description" name="description" 
                              class="form-input form-textarea dirty-tracker"
                              placeholder="Add details about your assignment"><?php 
                        echo htmlspecialchars($assignment_data['description']); 
                    ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="deadline" class="form-label">Deadline</label>
                    <input type="datetime-local" id="deadline" name="deadline" 
                           value="<?php echo htmlspecialchars($assignment_data['deadline']); ?>" 
                           class="form-input dirty-tracker" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Priority</label>
                    <div class="priority-options">
                        <div class="priority-option priority-low">
                            <input type="radio" id="priority-low" name="priority" value="low" 
                                <?php echo ($assignment_data['priority'] == 'low') ? 'checked' : ''; ?> 
                                class="dirty-tracker">
                            <label for="priority-low" class="priority-label">
                                <span class="priority-icon"><i class="fas fa-arrow-down"></i></span>
                                <span>Low</span>
                            </label>
                        </div>
                        
                        <div class="priority-option priority-medium">
                            <input type="radio" id="priority-medium" name="priority" value="medium" 
                                <?php echo ($assignment_data['priority'] == 'medium') ? 'checked' : ''; ?> 
                                class="dirty-tracker">
                            <label for="priority-medium" class="priority-label">
                                <span class="priority-icon"><i class="fas fa-equals"></i></span>
                                <span>Medium</span>
                            </label>
                        </div>
                        
                        <div class="priority-option priority-high">
                            <input type="radio" id="priority-high" name="priority" value="high" 
                                <?php echo ($assignment_data['priority'] == 'high') ? 'checked' : ''; ?> 
                                class="dirty-tracker">
                            <label for="priority-high" class="priority-label">
                                <span class="priority-icon"><i class="fas fa-arrow-up"></i></span>
                                <span>High</span>
                            </label>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn">
                    <i class="fas fa-plus"></i> Add Assignment
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
                // Save draft to session storage
                const formData = {
                    subject_id: document.getElementById('subject_id').value,
                    title: document.getElementById('title').value,
                    description: document.getElementById('description').value,
                    deadline: document.getElementById('deadline').value,
                    priority: document.querySelector('input[name="priority"]:checked').value
                };
                sessionStorage.setItem('assignmentDraft', JSON.stringify(formData));
                
                // Mark as dirty
                el.classList.add('dirty');
            });
        });

        // Warn before leaving with unsaved changes
        window.addEventListener('beforeunload', (e) => {
            const dirtyFields = document.querySelectorAll('.dirty');
            if (dirtyFields.length > 0) {
                // Save to server session before leaving
                const formData = {
                    subject_id: document.getElementById('subject_id').value,
                    title: document.getElementById('title').value,
                    description: document.getElementById('description').value,
                    deadline: document.getElementById('deadline').value,
                    priority: document.querySelector('input[name="priority"]:checked').value
                };
                
                fetch('save_assignment_draft.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(formData)
                });
                
                // Show warning
                e.preventDefault();
                e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
            }
        });

        // Load draft from session storage
        document.addEventListener('DOMContentLoaded', () => {
            const draft = sessionStorage.getItem('assignmentDraft');
            if (draft) {
                const formData = JSON.parse(draft);
                document.getElementById('subject_id').value = formData.subject_id;
                document.getElementById('title').value = formData.title;
                document.getElementById('description').value = formData.description;
                document.getElementById('deadline').value = formData.deadline;
                
                // Set the correct priority radio button
                if (formData.priority) {
                    document.querySelector(`input[name="priority"][value="${formData.priority}"]`).checked = true;
                }
            }
        });

        // Clear storage on successful form submission
        document.getElementById('assignmentForm').addEventListener('submit', () => {
            sessionStorage.removeItem('assignmentDraft');
        });
    </script>
</body>
</html>