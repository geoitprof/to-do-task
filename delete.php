<?php
session_start();
include("auth.php");
requireLogin();

include("db.php");

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Function to delete task and its attachments
function deleteTaskWithAttachments($conn, $task_id) {
    // First, get task details to access attachments
    $stmt = $conn->prepare("SELECT attachments FROM tasks WHERE id = ?");
    $stmt->bind_param("i", $task_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Delete physical attachment files
        if (!empty($row['attachments'])) {
            $attachments = json_decode($row['attachments'], true);
            if (is_array($attachments)) {
                foreach ($attachments as $filename) {
                    $filepath = 'uploads/' . $filename;
                    if (file_exists($filepath)) {
                        unlink($filepath);
                    }
                }
            }
        }
        
        // Delete the task from database
        $delete_stmt = $conn->prepare("DELETE FROM tasks WHERE id = ?");
        $delete_stmt->bind_param("i", $task_id);
        
        if ($delete_stmt->execute()) {
            return ['success' => true, 'message' => 'Task deleted successfully'];
        } else {
            return ['success' => false, 'error' => 'Failed to delete task from database'];
        }
    } else {
        return ['success' => false, 'error' => 'Task not found'];
    }
}

// Handle different request methods
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // AJAX request handling
    header('Content-Type: application/json');
    
    if (!isset($_POST['id']) || empty($_POST['id'])) {
        echo json_encode(['success' => false, 'error' => 'No task ID provided. Please try again.']);
        exit();
    }
    
    $id = intval($_POST['id']);
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid task ID.']);
        exit();
    }
    
    // Check if task exists and get details
    $user_id = getCurrentUserId();
    $is_admin = hasRole('admin');
    
    $check_stmt = $conn->prepare("SELECT id, task, parent_id, user_id FROM tasks WHERE id = ?");
    if (!$check_stmt) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
        exit();
    }
    $check_stmt->bind_param("i", $id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Task not found.']);
        exit();
    }
    
    $task_data = $check_result->fetch_assoc();
    
    // Check if user can access this task
    if (!$is_admin && $task_data['user_id'] != $user_id) {
        echo json_encode(['success' => false, 'error' => 'Access denied. You do not have permission to delete this task.']);
        exit();
    }
    
    // Check if this task has subtasks
    $subtask_stmt = $conn->prepare("SELECT COUNT(*) as subtask_count FROM tasks WHERE parent_id = ?");
    if (!$subtask_stmt) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
        exit();
    }
    $subtask_stmt->bind_param("i", $id);
    $subtask_stmt->execute();
    $subtask_result = $subtask_stmt->get_result();
    $subtask_count = $subtask_result->fetch_assoc()['subtask_count'];
    
    // Handle cascade deletion if requested
    $cascade = isset($_POST['cascade']) && $_POST['cascade'] === 'true';
    
    if ($subtask_count > 0 && !$cascade) {
        echo json_encode([
            'success' => false, 
            'error' => 'This task has subtasks. Please specify if you want to delete them too.',
            'has_subtasks' => true,
            'subtask_count' => $subtask_count
        ]);
        exit();
    }
    
    // Begin transaction for cascade deletion
    $conn->begin_transaction();
    
    try {
        if ($cascade && $subtask_count > 0) {
            // Delete all subtasks first
            $subtasks_stmt = $conn->prepare("SELECT id FROM tasks WHERE parent_id = ?");
            if (!$subtasks_stmt) throw new Exception('Database error: ' . $conn->error);
            $subtasks_stmt->bind_param("i", $id);
            $subtasks_stmt->execute();
            $subtasks_result = $subtasks_stmt->get_result();
            
            while ($subtask = $subtasks_result->fetch_assoc()) {
                $subtask_result = deleteTaskWithAttachments($conn, $subtask['id']);
                if (!$subtask_result['success']) {
                    throw new Exception('Failed to delete subtask: ' . $subtask_result['error']);
                }
            }
        }
        
        // Delete the main task
        $result = deleteTaskWithAttachments($conn, $id);
        
        if ($result['success']) {
            $conn->commit();
            echo json_encode([
                'success' => true, 
                'message' => $cascade ? 'Task and all subtasks deleted successfully.' : 'Task deleted successfully.'
            ]);
        } else {
            throw new Exception($result['error']);
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => 'Delete failed: ' . $e->getMessage()]);
    }
    
    exit();
}

// Handle GET request (traditional redirect method)
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    if ($id <= 0) {
        $_SESSION['error'] = 'Invalid task ID';
        header("Location: index.php");
        exit();
    }
    
    // Check if confirmation is provided
    $confirm = isset($_GET['confirm']) && $_GET['confirm'] === 'yes';
    
    if (!$confirm) {
        // Redirect to confirmation page or back with error
        $_SESSION['error'] = 'Task deletion must be confirmed';
        header("Location: index.php");
        exit();
    }
    
    $result = deleteTaskWithAttachments($conn, $id);
    
    if ($result['success']) {
        $_SESSION['success'] = $result['message'];
    } else {
        $_SESSION['error'] = $result['error'];
    }
    
    header("Location: index.php");
    exit();
}

// If no ID provided, redirect back
$_SESSION['error'] = 'No task specified for deletion';
header("Location: index.php");
exit();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Task</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #111 0%, #333 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .delete-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            padding: 40px;
            max-width: 500px;
            text-align: center;
        }
        
        .delete-icon {
            font-size: 4rem;
            color: #dc3545;
            margin-bottom: 20px;
        }
        
        .btn {
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 500;
            margin: 0 10px;
        }
    </style>
</head>
<body>
    <!-- This HTML is shown only if accessed directly without proper parameters -->
    <div class="delete-container">
        <i class="fas fa-exclamation-triangle delete-icon"></i>
        <h2 class="mb-3">Invalid Request</h2>
        <p class="text-muted mb-4">No task specified for deletion or invalid request method.</p>
        <a href="index.php" class="btn btn-primary">
            <i class="fas fa-arrow-left me-2"></i>Back to Tasks
        </a>
    </div>

    <script>
        // JavaScript functions for AJAX deletion (to be used from other pages)
        
        /**
         * Delete task with confirmation
         * @param {number} taskId - The ID of the task to delete
         * @param {string} taskName - The name of the task (for confirmation)
         * @param {boolean} hasSubtasks - Whether the task has subtasks
         * @param {function} callback - Callback function after deletion
         */
        async function deleteTask(taskId, taskName, hasSubtasks = false, callback = null) {
            let message = `Are you sure you want to delete the task "${taskName}"?`;
            if (hasSubtasks) {
                message += '\n\nThis task also has subtasks, which will be deleted as well.';
            }

            if (!confirm(message)) {
                return;
            }

            const formData = new FormData();
            formData.append('id', taskId);

            if (hasSubtasks) {
                formData.append('cascade', 'true');
            }

            try {
                let response = await fetch('delete.php', {
                    method: 'POST',
                    body: formData
                });

                let data = await response.json();

                if (data.success) {
                    alert(data.message);
                    if (callback) callback(true, data);
                    else location.reload();
                    return;
                }

                if (data.has_subtasks) {
                    const confirmCascade = confirm(`${data.error}\n\nDo you want to delete the task and all ${data.subtask_count} subtasks?`);
                    if (confirmCascade) {
                        formData.append('cascade', 'true');
                        
                        response = await fetch('delete.php', {
                            method: 'POST',
                            body: formData
                        });
                        
                        data = await response.json();

                        if (data.success) {
                            alert(data.message);
                        } else {
                            alert('Error: ' + data.error);
                        }
                        
                        if (callback) callback(data.success, data);
                        else if (data.success) location.reload();
                    }
                } else {
                    alert('Error: ' + data.error);
                    if (callback) callback(false, data);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred while deleting the task.');
                if (callback) {
                    callback(false, error);
                }
            }
        }
        
        /**
         * Simple delete function (no subtask handling)
         * @param {number} taskId - The ID of the task to delete
         * @param {function} callback - Callback function after deletion
         */
        async function simpleDeleteTask(taskId, callback = null) {
            if (!confirm('Are you sure you want to delete this task?')) {
                return;
            }

            const formData = new FormData();
            formData.append('id', taskId);
            
            try {
                const response = await fetch('delete.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    if (callback) {
                        callback(true, data);
                    } else {
                        location.reload();
                    }
                } else {
                    alert('Error: ' + data.error);
                    if (callback) {
                        callback(false, data);
                    }
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred while deleting the task.');
                if (callback) {
                    callback(false, error);
                }
            }
        }
    </script>
</body>
</html>