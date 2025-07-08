<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$conn = new mysqli('localhost', 'root', '', 'studyflow');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get user data
$user_id = $_SESSION['user_id'];
$user_stmt = $conn->prepare("SELECT username FROM users WHERE user_id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();
$user_stmt->close();

// Get stats data
$subjects_count = 0;
$pending_assignments = 0;
$upcoming_deadlines = 0;
$completed_tasks = 0;

// Count subjects
$count_stmt = $conn->prepare("SELECT COUNT(*) FROM subjects WHERE user_id = ?");
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$count_stmt->bind_result($subjects_count);
$count_stmt->fetch();
$count_stmt->close();

// Count pending assignments
$count_stmt = $conn->prepare("SELECT COUNT(*) FROM assignments WHERE user_id = ? AND status != 'completed'");
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$count_stmt->bind_result($pending_assignments);
$count_stmt->fetch();
$count_stmt->close();

// Count upcoming deadlines (within 7 days)
$count_stmt = $conn->prepare("SELECT COUNT(*) FROM assignments WHERE user_id = ? AND deadline BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)");
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$count_stmt->bind_result($upcoming_deadlines);
$count_stmt->fetch();
$count_stmt->close();

// Count completed tasks
$count_stmt = $conn->prepare("SELECT COUNT(*) FROM assignments WHERE user_id = ? AND status = 'completed'");
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$count_stmt->bind_result($completed_tasks);
$count_stmt->fetch();
$count_stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudyFlow Dashboard</title>
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
            background-color: var(--light);
            color: var(--dark);
            line-height: 1.5;
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1.5rem;
        }

        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2.5rem;
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

        .user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-light), var(--accent));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        /* Welcome Banner */
        .welcome-banner {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 2rem;
            border-radius: 16px;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-md);
            position: relative;
            overflow: hidden;
        }

        .welcome-banner::before {
            content: "";
            position: absolute;
            top: -50px;
            right: -50px;
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 70%);
            border-radius: 50%;
        }

        .welcome-title {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .welcome-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            max-width: 600px;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }

        .stat-card {
            background: var(--card-bg);
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }

        .stat-header {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            color: white;
        }

        .stat-title {
            font-size: 0.9rem;
            color: var(--gray);
            font-weight: 500;
        }

        .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .stat-change {
            font-size: 0.85rem;
            display: flex;
            align-items: center;
        }

        .stat-change.positive {
            color: var(--success);
        }

        .stat-change.negative {
            color: var(--danger);
        }

        /* Actions Grid */
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }

        .action-card {
            background: var(--card-bg);
            padding: 1.75rem;
            border-radius: 16px;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            text-decoration: none;
            color: var(--dark);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
            border-color: rgba(0, 0, 0, 0.1);
        }

        .action-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.25rem;
            color: white;
            font-size: 1.25rem;
        }

        .action-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
        }

        .action-desc {
            color: var(--gray);
            margin-bottom: 1.5rem;
            font-size: 0.95rem;
        }

        .action-link {
            display: inline-flex;
            align-items: center;
            color: var(--primary);
            font-weight: 500;
            text-decoration: none;
            transition: var(--transition);
        }

        .action-link i {
            margin-left: 0.5rem;
            transition: var(--transition);
        }

        .action-card:hover .action-link {
            color: var(--secondary);
        }

        .action-card:hover .action-link i {
            transform: translateX(3px);
        }

        /* Footer */
        .footer {
            text-align: center;
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }

        .logout-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1.5rem;
            background: var(--card-bg);
            color: var(--danger);
            border: 1px solid var(--danger);
            border-radius: 8px;
            font-weight: 500;
            text-decoration: none;
            transition: var(--transition);
        }

        .logout-btn:hover {
            background: var(--danger);
            color: white;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
                margin-bottom: 1.5rem;
            }
            
            .welcome-banner {
                padding: 1.5rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .container {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <header class="header">
            <a href="dashboard.php" class="logo">
                <div class="logo-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <span>StudyFlow</span>
            </a>
            <div class="user-menu">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                </div>
            </div>
        </header>

        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <h1 class="welcome-title">Welcome back, <?php echo htmlspecialchars($user['username']); ?>!</h1>
            <p class="welcome-subtitle">Here's what's happening with your studies today</p>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon" style="background: linear-gradient(135deg, var(--primary), var(--primary-light));">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-title">Subjects</div>
                </div>
                <div class="stat-value"><?php echo $subjects_count; ?></div>
                <div class="stat-change positive">
                    <i class="fas fa-arrow-up"></i> 2 new this week
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon" style="background: linear-gradient(135deg, var(--warning), #f97316);">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <div class="stat-title">Pending Assignments</div>
                </div>
                <div class="stat-value"><?php echo $pending_assignments; ?></div>
                <div class="stat-change negative">
                    <i class="fas fa-arrow-up"></i> 1 due tomorrow
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon" style="background: linear-gradient(135deg, var(--accent), #db2777);">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div class="stat-title">Upcoming Deadlines</div>
                </div>
                <div class="stat-value"><?php echo $upcoming_deadlines; ?></div>
                <div class="stat-change">
                    <i class="fas fa-calendar"></i> This week
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon" style="background: linear-gradient(135deg, var(--success), #059669);">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-title">Completed Tasks</div>
                </div>
                <div class="stat-value"><?php echo $completed_tasks; ?></div>
                <div class="stat-change positive">
                    <i class="fas fa-arrow-up"></i> 5 this week
                </div>
            </div>
        </div>

        <!-- Actions Grid -->
        <div class="actions-grid">
            <a href="add_subject.php" class="action-card">
                <div class="action-icon" style="background: linear-gradient(135deg, var(--primary), var(--primary-light));">
                    <i class="fas fa-book-open"></i>
                </div>
                <h3 class="action-title">Add Subjects</h3>
                <p class="action-desc">Organize your courses and study materials by subject</p>
                <span class="action-link">Add subject <i class="fas fa-arrow-right"></i></span>
            </a>
            
            <a href="add_assignment.php" class="action-card">
                <div class="action-icon" style="background: linear-gradient(135deg, #f59e0b, #f97316);">
                    <i class="fas fa-tasks"></i>
                </div>
                <h3 class="action-title">Add Assignments</h3>
                <p class="action-desc">Create new tasks with deadlines and priorities</p>
                <span class="action-link">Create assignment <i class="fas fa-arrow-right"></i></span>
            </a>
            
            <a href="view_assignments.php" class="action-card">
                <div class="action-icon" style="background: linear-gradient(135deg, var(--accent), #db2777);">
                    <i class="fas fa-list-ul"></i>
                </div>
                <h3 class="action-title">View Assignments</h3>
                <p class="action-desc">Manage your current workload and track progress</p>
                <span class="action-link">View all <i class="fas fa-arrow-right"></i></span>
            </a>
            
            <a href="schedule.php" class="action-card">
                <div class="action-icon" style="background: linear-gradient(135deg, var(--success), #059669);">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <h3 class="action-title">Study Schedule</h3>
                <p class="action-desc">Plan your study sessions and manage your time</p>
                <span class="action-link">View schedule <i class="fas fa-arrow-right"></i></span>
            </a>
        </div>

        <!-- Footer -->
        <div class="footer">
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
    
    <!-- Notification Container -->
<div id="notificationContainer" style="position: fixed; top: 20px; right: 20px; z-index: 1000;"></div>

<script>
// Notification functions
function showNotification(title, message, type = 'info') {
    const container = document.getElementById('notificationContainer');
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <div style="padding: 15px; background: white; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); 
                    margin-bottom: 10px; border-left: 4px solid ${getNotificationColor(type)};">
            <strong>${title}</strong>
            <p style="margin: 5px 0 0;">${message}</p>
        </div>
    `;
    container.appendChild(notification);
    
    setTimeout(() => notification.remove(), 5000);
}

function getNotificationColor(type) {
    const colors = {
        'success': '#10b981',
        'warning': '#f59e0b',
        'error': '#ef4444',
        'info': '#3a7bd5'
    };
    return colors[type] || colors['info'];
}

// Deadline checking
function checkDeadlines() {
    fetch('check_deadlines.php')
        .then(response => response.json())
        .then(data => {
            if (data.upcoming) {
                data.upcoming.forEach(assignment => {
                    showNotification(
                        'Deadline Approaching', 
                        `${assignment.title} due in ${assignment.hours_remaining} hours`,
                        'warning'
                    );
                });
            }
            if (data.overdue) {
                data.overdue.forEach(assignment => {
                    showNotification(
                        'Assignment Overdue', 
                        `${assignment.title} was due on ${assignment.due_date}`,
                        'error'
                    );
                });
            }
        });
}

// Run on page load and every 5 minutes
document.addEventListener('DOMContentLoaded', function() {
    checkDeadlines();
    setInterval(checkDeadlines, 300000);
});
</script>
</body>
</html>