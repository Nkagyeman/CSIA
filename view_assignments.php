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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        // Delete assignment
        $stmt = $conn->prepare("DELETE FROM assignments WHERE assignment_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $_POST['delete_id'], $_SESSION['user_id']);
        $stmt->execute();
        $stmt->close();
    } elseif (isset($_POST['update_assignment'])) {
        // Update assignment
        $priority = $_POST['priority'] ?? 'medium';
        $status = $_POST['status'] ?? 'not_started';
        
        $stmt = $conn->prepare("UPDATE assignments SET 
                              title = ?, 
                              description = ?, 
                              deadline = ?, 
                              priority = ?, 
                              status = ?,
                              subject_id = ?
                              WHERE assignment_id = ? AND user_id = ?");
        $stmt->bind_param("sssssiii", 
            $_POST['title'],
            $_POST['description'],
            $_POST['deadline'],
            $priority,
            $status,
            $_POST['subject_id'],
            $_POST['assignment_id'],
            $_SESSION['user_id']
        );
        $stmt->execute();
        $stmt->close();
    } elseif (isset($_POST['add_assignment'])) {
        // Add new assignment
        $priority = $_POST['priority'] ?? 'medium';
        $status = $_POST['status'] ?? 'not_started';
        
        $stmt = $conn->prepare("INSERT INTO assignments 
                              (user_id, subject_id, title, description, deadline, priority, status) 
                              VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iisssss", 
            $_SESSION['user_id'],
            $_POST['subject_id'],
            $_POST['title'],
            $_POST['description'],
            $_POST['deadline'],
            $priority,
            $status
        );
        $stmt->execute();
        $stmt->close();
    }
}

// Get all assignments for the current user with subject names
$assignments = [];
$stmt = $conn->prepare("
    SELECT a.*, s.subject_name 
    FROM assignments a
    JOIN subjects s ON a.subject_id = s.subject_id
    WHERE a.user_id = ?
    ORDER BY a.deadline ASC
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $assignments[] = $row;
}
$stmt->close();

// Get all subjects for dropdowns
$subjects = [];
$stmt = $conn->prepare("SELECT subject_id, subject_name FROM subjects WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $subjects[] = $row;
}
$stmt->close();

$conn->close();

// Function to get deadline status
function getDeadlineStatus($deadline, $status) {
    if ($status === 'completed') return ['text' => 'Completed', 'class' => 'completed'];
    
    $now = new DateTime();
    $deadlineDate = new DateTime($deadline);
    
    if ($deadlineDate < $now) {
        return ['text' => 'Overdue: ' . $deadlineDate->format('M j, Y g:i A'), 'class' => 'overdue'];
    }
    
    $interval = $now->diff($deadlineDate);
    
    if ($interval->days === 0) {
        return ['text' => 'Due Today: ' . $deadlineDate->format('g:i A'), 'class' => 'today'];
    } elseif ($interval->days <= 7) {
        return ['text' => 'Due in ' . $interval->days . ' day' . ($interval->days > 1 ? 's' : ''), 'class' => 'upcoming'];
    } else {
        return ['text' => 'Due: ' . $deadlineDate->format('M j, Y'), 'class' => 'future'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignments | StudyFlow</title>
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

        /* Assignment Cards */
        .assignments-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .assignment-card {
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            transition: var(--transition);
            border-left: 4px solid var(--primary);
        }

        .assignment-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .assignment-card.high {
            border-left-color: var(--danger);
        }

        .assignment-card.medium {
            border-left-color: var(--warning);
        }

        .assignment-card.low {
            border-left-color: var(--success);
        }

        .assignment-card.completed {
            opacity: 0.8;
            background-color: rgba(16, 185, 129, 0.05);
        }

        .card-header {
            padding: 1.25rem 1.5rem 0;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .card-subject {
            font-size: 0.85rem;
            color: var(--gray);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-body {
            padding: 0 1.5rem 1.25rem;
        }

        .card-description {
            font-size: 0.9rem;
            color: var(--gray);
            margin: 0.75rem 0;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .card-footer {
            padding: 1rem 1.5rem;
            background: rgba(0, 0, 0, 0.02);
            border-top: 1px solid rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .deadline {
            font-size: 0.85rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .deadline.overdue {
            color: var(--danger);
        }

        .deadline.today {
            color: var(--warning);
        }

        .deadline.upcoming {
            color: var(--success);
        }

        .deadline.future {
            color: var(--gray);
        }

        .deadline.completed {
            color: var(--success);
        }

        .badge {
            display: inline-block;
            padding: 0.35rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-primary {
            background: rgba(79, 70, 229, 0.1);
            color: var(--primary);
        }

        .badge-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .badge-warning {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }

        .badge-danger {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }

        .badge-gray {
            background: rgba(107, 114, 128, 0.1);
            color: var(--gray);
        }

        .card-actions {
            display: flex;
            gap: 0.5rem;
        }

        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(0, 0, 0, 0.05);
            color: var(--gray);
            cursor: pointer;
            transition: var(--transition);
            border: none;
        }

        .action-btn:hover {
            background: var(--primary);
            color: white;
        }

        /* Empty State */
        .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow-sm);
        }

        .empty-icon {
            font-size: 3rem;
            color: var(--gray);
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-text {
            color: var(--gray);
            margin-bottom: 1.5rem;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .modal-content {
            background: white;
            border-radius: 16px;
            box-shadow: var(--shadow-lg);
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            animation: fadeIn 0.3s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--gray);
        }

        .modal-body {
            padding: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }

        .form-textarea {
            min-height: 120px;
            resize: vertical;
        }

        .modal-footer {
            padding: 1.25rem 1.5rem;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .assignments-grid {
                grid-template-columns: 1fr;
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
            <button class="btn" onclick="openModal('add')">
                <i class="fas fa-plus"></i> Add Assignment
            </button>
        </header>

        <div class="assignments-grid">
            <?php if (empty($assignments)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <h3>No Assignments Yet</h3>
                    <p class="empty-text">Get started by adding your first assignment</p>
                    <button class="btn" onclick="openModal('add')">
                        <i class="fas fa-plus"></i> Add Assignment
                    </button>
                </div>
            <?php else: ?>
                <?php foreach ($assignments as $assignment): 
                    $deadline = getDeadlineStatus($assignment['deadline'], $assignment['status']);
                    $priorityClass = strtolower($assignment['priority'] ?? 'medium');
                ?>
                <div class="assignment-card <?php echo $priorityClass; ?> <?php echo $assignment['status'] === 'completed' ? 'completed' : ''; ?>">
                    <div class="card-header">
                        <div>
                            <h3 class="card-title"><?php echo htmlspecialchars($assignment['title'] ?? ''); ?></h3>
                            <div class="card-subject">
                                <i class="fas fa-book"></i>
                                <?php echo htmlspecialchars($assignment['subject_name'] ?? ''); ?>
                            </div>
                        </div>
                        <span class="badge badge-<?php 
                            echo $assignment['status'] === 'completed' ? 'success' : 
                                 ($priorityClass === 'high' ? 'danger' : 
                                  ($priorityClass === 'medium' ? 'warning' : 'success'));
                        ?>">
                            <?php echo ucfirst($assignment['status'] === 'completed' ? 'Completed' : $priorityClass); ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($assignment['description'])): ?>
                            <p class="card-description"><?php echo htmlspecialchars($assignment['description']); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer">
                        <div class="deadline <?php echo $deadline['class']; ?>">
                            <i class="far fa-clock"></i>
                            <?php echo $deadline['text']; ?>
                        </div>
                        <div class="card-actions">
                            <button class="action-btn" onclick="openModal('edit', <?php echo $assignment['assignment_id']; ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="action-btn" onclick="confirmDelete(<?php echo $assignment['assignment_id']; ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div style="text-align: center;">
            <a href="dashboard.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>
    
    <!-- Add/Edit Assignment Modal -->
    <div id="assignmentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Add Assignment</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <form id="assignmentForm" method="POST">
                <input type="hidden" id="assignment_id" name="assignment_id">
                <input type="hidden" id="formAction" name="add_assignment">
                
                <div class="modal-body">
                    <div class="form-group">
                        <label for="title" class="form-label">Title</label>
                        <input type="text" id="title" name="title" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="subject_id" class="form-label">Subject</label>
                        <select id="subject_id" name="subject_id" class="form-control" required>
                            <option value="">Select Subject</option>
                            <?php foreach ($subjects as $subject): ?>
                                <option value="<?php echo $subject['subject_id']; ?>">
                                    <?php echo htmlspecialchars($subject['subject_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="description" class="form-label">Description</label>
                        <textarea id="description" name="description" class="form-control form-textarea"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="deadline" class="form-label">Due Date</label>
                        <input type="datetime-local" id="deadline" name="deadline" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="priority" class="form-label">Priority</label>
                        <select id="priority" name="priority" class="form-control" required>
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="status" class="form-label">Status</label>
                        <select id="status" name="status" class="form-control" required>
                            <option value="not_started">Not Started</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn">Save Assignment</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Confirm Deletion</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <form id="deleteForm" method="POST">
                <input type="hidden" id="delete_id" name="delete_id">
                <div class="modal-body">
                    <p>Are you sure you want to delete this assignment? This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn" style="background: var(--danger);">Delete Assignment</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Open the appropriate modal
        function openModal(action, id = null) {
            if (action === 'add') {
                document.getElementById('modalTitle').textContent = 'Add Assignment';
                document.getElementById('formAction').name = 'add_assignment';
                document.getElementById('assignmentForm').reset();
                document.getElementById('assignment_id').value = '';
                document.getElementById('assignmentModal').style.display = 'flex';
                document.body.style.overflow = 'hidden';
            } else if (action === 'edit' && id) {
                // Find the assignment to edit
                const assignment = <?php echo json_encode($assignments); ?>.find(a => a.assignment_id == id);
                if (assignment) {
                    document.getElementById('modalTitle').textContent = 'Edit Assignment';
                    document.getElementById('formAction').name = 'update_assignment';
                    document.getElementById('assignment_id').value = assignment.assignment_id;
                    document.getElementById('title').value = assignment.title;
                    document.getElementById('subject_id').value = assignment.subject_id;
                    document.getElementById('description').value = assignment.description || '';
                    
                    // Format the deadline for datetime-local input
                    let deadline = '';
                    if (assignment.deadline) {
                        deadline = assignment.deadline.replace(' ', 'T');
                        if (deadline.length > 16) {
                            deadline = deadline.substring(0, 16);
                        }
                    }
                    document.getElementById('deadline').value = deadline;
                    
                    document.getElementById('priority').value = assignment.priority || 'medium';
                    document.getElementById('status').value = assignment.status || 'not_started';
                    document.getElementById('assignmentModal').style.display = 'flex';
                    document.body.style.overflow = 'hidden';
                }
            }
        }
        
        // Confirm delete action
        function confirmDelete(id) {
            document.getElementById('delete_id').value = id;
            document.getElementById('deleteModal').style.display = 'flex';
        }
        
        // Close modal
        function closeModal() {
            document.getElementById('assignmentModal').style.display = 'none';
            document.getElementById('deleteModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                closeModal();
            }
        }
    </script>
</body>
</html>