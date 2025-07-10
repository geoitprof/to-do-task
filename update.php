<?php
include("auth.php");
requireLogin();

include("db.php");

function is_ajax() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) ||
        (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false);
}

if ($_SERVER['REQUEST_METHOD'] == "POST") {
    $id = intval($_POST['id']);
    $task = trim($_POST['task']);
    $due_at = !empty($_POST['due_at']) ? $_POST['due_at'] : null;
    $priority = $_POST['priority'] ?? 'medium';
    $user_id = getCurrentUserId();
    $is_admin = hasRole('admin');

    // Check if user can access this task
    if (!$is_admin) {
        $stmt = $conn->prepare("SELECT user_id FROM tasks WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $task_data = $result->fetch_assoc();
        
        if (!$task_data || $task_data['user_id'] != $user_id) {
            if (is_ajax()) {
                echo json_encode(['success' => false, 'error' => 'Access denied']);
                exit();
            } else {
                header("Location: index.php?error=access_denied");
                exit();
            }
        }
    }

    if (!empty($task)) {
        $stmt = $conn->prepare("UPDATE tasks SET task = ?, due_at = ?, priority = ? WHERE id = ?");
        $stmt->bind_param("sssi", $task, $due_at, $priority, $id);
        $success = $stmt->execute();
        if (is_ajax()) {
            if ($success) {
                // Fetch updated task
                $result = $conn->query("SELECT * FROM tasks WHERE id = $id");
                $row = $result->fetch_assoc();
                ob_start();
                // Render the updated task HTML (copy from index.php)
                $created = date("M d, Y h:i A", strtotime($row['created_at']));
                $due = $row['due_at'] ? date("M d, Y h:i A", strtotime($row['due_at'])) : "No due date";
                $dueDate = $row['due_at'] ? new DateTime($row['due_at']) : null;
                $now = new DateTime();
                $isOverdue = $dueDate && $dueDate < $now && $row['status'] === 'pending';
                $time_result = $conn->query("SELECT SUM(duration) as total_time FROM time_entries WHERE task_id = " . $row['id']);
                $time_data = $time_result ? $time_result->fetch_assoc() : ['total_time' => 0];
                $actual_time = $time_data['total_time'] ?? 0;
                $subtasks_result = $conn->query("SELECT * FROM tasks WHERE parent_id = " . $row['id'] . " ORDER BY created_at");
                $subtasks = $subtasks_result ?: $conn->query("SELECT 1 WHERE FALSE");
                ?>
                <div class="task-item <?= $row['status'] === 'completed' ? 'completed' : '' ?>" data-task-id="<?= $row['id'] ?>" data-due="<?= htmlspecialchars($row['due_at']) ?>">
                    <div class="task-header">
                        <div class="task-left">
                            <input type="checkbox" class="task-checkbox" <?= $row['status'] === 'completed' ? 'checked' : '' ?> onchange="toggleTaskStatus(<?= $row['id'] ?>, this.checked)">
                            <div class="task-content">
                                <h5 class="task-title <?= $row['status'] === 'completed' ? 'text-decoration-line-through' : '' ?>"><?= htmlspecialchars($row['task']) ?></h5>
                                <?php if (!empty($row['description'])): ?>
                                <div class="task-description"><?= htmlspecialchars($row['description']) ?></div>
                                <?php endif; ?>
                                <div class="task-meta">
                                    <?php if (!empty($row['category'])): ?>
                                        <?php foreach (explode(',', $row['category']) as $cat): ?>
                                            <span class="category-tag"><?= htmlspecialchars(trim($cat)) ?></span>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    <?php if ($subtasks->num_rows > 0): ?>
                                        <span class="category-tag">
                                            <i class="fas fa-list"></i> <?= $subtasks->num_rows ?> subtask<?= $subtasks->num_rows > 1 ? 's' : '' ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="task-details">
                                    <div class="task-detail">
                                        <i class="fas fa-calendar-alt"></i>
                                        <?= $due ?>
                                    </div>
                                    <?php if ($row['estimated_time'] > 0): ?>
                                    <div class="time-tracker" id="timer-<?= $row['id'] ?>" data-task-id="<?= $row['id'] ?>">
                                        <i class="fas fa-stopwatch"></i>
                                        <span class="timer-text"><?= $actual_time ?>m / <?= $row['estimated_time'] ?>m</span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="task-actions">
                            <button class="btn btn-sm" onclick="addSubtask(<?= $row['id'] ?>)" title="Add Subtask">
                                <i class="fas fa-plus"></i>
                            </button>
                            <a href="#" class="btn btn-sm" title="Edit" onclick="openEditModal(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['task'])) ?>'); return false;">
                                <i class="fas fa-edit"></i>
                            </a>
                            <button class="btn btn-sm" onclick="deleteTask(<?= $row['id'] ?>)" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <?php
                $task_html = ob_get_clean();
                echo json_encode(['success' => true, 'id' => $row['id'], 'task_html' => $task_html]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Update failed']);
            }
            exit();
        }
    } else if (is_ajax()) {
        echo json_encode(['success' => false, 'error' => 'Task title required']);
        exit();
    }
}

// If not AJAX, redirect back
header("Location: user_dashboard.php");
exit();
?>