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

// Get user's assignments for calendar
$assignments = [];
$stmt = $conn->prepare("
    SELECT a.assignment_id, a.title, a.description, a.deadline, a.priority, s.subject_name 
    FROM assignments a
    JOIN subjects s ON a.subject_id = s.subject_id
    WHERE a.user_id = ?
    ORDER BY a.deadline ASC
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $assignments[] = [
        'id' => $row['assignment_id'],
        'title' => $row['title'],
        'subject' => $row['subject_name'],
        'start' => $row['deadline'],
        'priority' => $row['priority'],
        'description' => $row['description'],
        'color' => getEventColor($row['priority'])
    ];
}
$stmt->close();

// Function to determine event color based on priority
function getEventColor($priority) {
    switch ($priority) {
        case 'high': return '#e74c3c'; // Red for high priority
        case 'medium': return '#f39c12'; // Orange for medium
        case 'low': return '#3498db'; // Blue for low
        default: return '#3a7bd5'; // Default color
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Study Schedule | StudyFlow</title>
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
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
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: var(--card-bg);
            padding: 2rem;
            border-radius: 16px;
            box-shadow: var(--shadow-md);
        }

        /* Header */
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .page-title {
            display: flex;
            align-items: center;
            gap: 1rem;
            color: var(--primary);
            margin: 0;
        }

        .page-title i {
            font-size: 1.5rem;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            gap: 0.5rem;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid var(--primary);
            color: var(--primary);
        }

        .btn-outline:hover {
            background: var(--primary);
            color: white;
        }

        /* Calendar */
        #calendar {
            margin-bottom: 2rem;
            background: white;
            border-radius: 12px;
            padding: 1rem;
            box-shadow: var(--shadow-sm);
        }

        .fc-toolbar-title {
            font-weight: 600;
            color: var(--dark);
        }

        .fc-button {
            background: var(--light) !important;
            border: 1px solid #e5e7eb !important;
            color: var(--dark) !important;
            font-weight: 500 !important;
            text-transform: capitalize !important;
        }

        .fc-button:hover {
            background: #f3f4f6 !important;
        }

        .fc-button-primary:not(:disabled).fc-button-active {
            background: var(--primary) !important;
            color: white !important;
        }

        /* Legend */
        .legend {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
            gap: 1.5rem;
            flex-wrap: wrap;
        }

        .legend-item {
            display: flex;
            align-items: center;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .legend-color {
            width: 16px;
            height: 16px;
            margin-right: 0.5rem;
            border-radius: 4px;
        }

        /* Tooltip */
        .event-tooltip {
            position: absolute;
            background: white;
            padding: 1.25rem;
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
            z-index: 100;
            max-width: 320px;
            display: none;
            border: 1px solid #e5e7eb;
        }

        .event-tooltip h3 {
            color: var(--primary);
            margin-bottom: 0.75rem;
            font-size: 1.25rem;
        }

        .event-tooltip p {
            margin-bottom: 0.5rem;
            color: var(--dark);
        }

        .event-tooltip strong {
            color: var(--dark);
            font-weight: 600;
        }

        .event-tooltip a {
            display: inline-block;
            margin-top: 1rem;
            color: var(--primary);
            font-weight: 500;
            text-decoration: none;
            transition: var(--transition);
        }

        .event-tooltip a:hover {
            color: var(--secondary);
            text-decoration: underline;
        }

        /* Footer */
        .footer {
            text-align: center;
            margin-top: 2rem;
            color: var(--gray);
            font-size: 0.875rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header-section {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .action-buttons {
                width: 100%;
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-section">
            <h1 class="page-title">
                <i class="fas fa-calendar-alt"></i> Study Schedule
            </h1>
            <div class="action-buttons">
                <a href="dashboard.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> Dashboard
                </a>
                <a href="add_assignment.php" class="btn">
                    <i class="fas fa-plus"></i> Add Assignment
                </a>
            </div>
        </div>
        
        <div class="legend">
            <div class="legend-item">
                <div class="legend-color" style="background-color: #e74c3c;"></div>
                <span>High Priority</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background-color: #f39c12;"></div>
                <span>Medium Priority</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background-color: #3498db;"></div>
                <span>Low Priority</span>
            </div>
        </div>
        
        <div id="calendar"></div>
        
        <div class="footer">
            <p>Click on assignments to view details</p>
        </div>
    </div>
    
    <div class="event-tooltip" id="eventTooltip"></div>
    
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const calendarEl = document.getElementById('calendar');
            const assignments = <?php echo json_encode($assignments); ?>;
            
            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                events: assignments,
                eventClick: function(info) {
                    const event = info.event;
                    const tooltip = document.getElementById('eventTooltip');
                    
                    tooltip.innerHTML = `
                        <h3>${event.title}</h3>
                        <p><strong>Subject:</strong> ${event.extendedProps.subject}</p>
                        <p><strong>Due:</strong> ${event.start.toLocaleString()}</p>
                        <p><strong>Priority:</strong> ${event.extendedProps.priority.charAt(0).toUpperCase() + event.extendedProps.priority.slice(1)}</p>
                        <p><strong>Description:</strong> ${event.extendedProps.description || 'No description'}</p>
                        <a href="view_assignments.php">View in Assignments <i class="fas fa-arrow-right"></i></a>
                    `;
                    
                    tooltip.style.display = 'block';
                    tooltip.style.left = (info.jsEvent.pageX + 10) + 'px';
                    tooltip.style.top = (info.jsEvent.pageY + 10) + 'px';
                    
                    info.jsEvent.preventDefault();
                },
                eventContent: function(arg) {
                    // Custom event content with priority indicator
                    const priorityIcon = arg.event.extendedProps.priority === 'high' ? '❗' : 
                                      arg.event.extendedProps.priority === 'medium' ? '🔸' : '▫️';
                    
                    return {
                        html: `
                            <div style="padding: 4px; font-size: 0.85em; line-height: 1.3;">
                                <div style="font-weight: 500;">${priorityIcon} ${arg.event.title}</div>
                                <div style="font-size: 0.8em; color: #6b7280;">${arg.event.extendedProps.subject}</div>
                            </div>
                        `
                    };
                }
            });
            
            calendar.render();
            
            // Close tooltip when clicking elsewhere
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.fc-event') && !e.target.closest('#eventTooltip')) {
                    document.getElementById('eventTooltip').style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>