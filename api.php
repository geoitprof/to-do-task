<?php
include("auth.php");
requireLogin();

include("db.php");

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$user_id = getCurrentUserId();
$is_admin = hasRole('admin');

// Helper function to check task ownership
function canAccessTask($task_id, $user_id, $is_admin) {
    global $conn;
    if ($is_admin) return true;
    
    $stmt = $conn->prepare("SELECT user_id FROM tasks WHERE id = ?");
    $stmt->bind_param("i", $task_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $task = $result->fetch_assoc();
    
    return $task && $task['user_id'] == $user_id;
}

switch ($action) {
    case 'toggle_status':
        $id = intval($input['id']);
        
        if (!canAccessTask($id, $user_id, $is_admin)) {
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            break;
        }
        
        // Accept both 'completed' (bool/int) and 'status' (string)
        $completed = isset($input['completed']) ? (bool)$input['completed'] : null;
        $status = $completed !== null ? ($completed ? 'completed' : 'pending') : (isset($input['status']) ? $input['status'] : 'pending');
        $completed_at = $status === 'completed' ? 'NOW()' : 'NULL';
        
        $stmt = $conn->prepare("UPDATE tasks SET status = ?, completed_at = $completed_at WHERE id = ?");
        $stmt->bind_param("si", $status, $id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $conn->error]);
        }
        break;

    case 'start_timer':
        $task_id = intval($input['task_id']);
        
        if (!canAccessTask($task_id, $user_id, $is_admin)) {
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            break;
        }
        
        // Prevent multiple active timers for this user/task
        $active_timer = $conn->query("SELECT id, start_time FROM time_entries WHERE task_id = $task_id AND end_time IS NULL");
        if ($active_timer->num_rows > 0) {
            $timer = $active_timer->fetch_assoc();
            echo json_encode(['success' => false, 'error' => 'Timer already running', 'timer_id' => $timer['id'], 'start_time' => $timer['start_time']]);
        } else {
            // Optionally add user_id if schema supports
            $stmt = $conn->prepare("INSERT INTO time_entries (task_id, start_time) VALUES (?, NOW())");
            $stmt->bind_param("i", $task_id);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'timer_id' => $conn->insert_id, 'start_time' => date('Y-m-d H:i:s')]);
            } else {
                echo json_encode(['success' => false, 'error' => $conn->error]);
            }
        }
        break;

    case 'stop_timer':
        $timer_id = intval($input['timer_id']);
        
        // Get start time and calculate duration
        $timer = $conn->query("SELECT start_time, task_id FROM time_entries WHERE id = $timer_id")->fetch_assoc();
        if ($timer) {
            if (!canAccessTask($timer['task_id'], $user_id, $is_admin)) {
                echo json_encode(['success' => false, 'error' => 'Access denied']);
                break;
            }
            
            $start = new DateTime($timer['start_time']);
            $end = new DateTime();
            $duration = $end->getTimestamp() - $start->getTimestamp();
            $duration_minutes = round($duration / 60);
            
            $stmt = $conn->prepare("UPDATE time_entries SET end_time = NOW(), duration = ? WHERE id = ?");
            $stmt->bind_param("ii", $duration_minutes, $timer_id);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'duration' => $duration_minutes]);
            } else {
                echo json_encode(['success' => false, 'error' => $conn->error]);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Timer not found']);
        }
        break;

    case 'add_subtask':
        $parent_id = intval($input['parent_id']);
        
        if (!canAccessTask($parent_id, $user_id, $is_admin)) {
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            break;
        }
        
        $task = $conn->real_escape_string($input['task']);
        
        $stmt = $conn->prepare("INSERT INTO tasks (task, parent_id, user_id, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("sii", $task, $parent_id, $user_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'id' => $conn->insert_id]);
        } else {
            echo json_encode(['success' => false, 'error' => $conn->error]);
        }
        break;

    case 'update_order':
        $tasks = $input['tasks'];
        
        foreach ($tasks as $task) {
            $id = intval($task['id']);
            if (canAccessTask($id, $user_id, $is_admin)) {
                $order = intval($task['order']);
                $conn->query("UPDATE tasks SET sort_order = $order WHERE id = $id");
            }
        }
        
        echo json_encode(['success' => true]);
        break;

    case 'bulk_action':
        $task_ids = array_map('intval', $input['task_ids']);
        $bulk_action = $input['bulk_action'];
        
        // Filter tasks that user can access
        $accessible_tasks = [];
        foreach ($task_ids as $task_id) {
            if (canAccessTask($task_id, $user_id, $is_admin)) {
                $accessible_tasks[] = $task_id;
            }
        }
        
        if (empty($accessible_tasks)) {
            echo json_encode(['success' => false, 'error' => 'No accessible tasks']);
            break;
        }
        
        $ids_string = implode(',', $accessible_tasks);
        
        switch ($bulk_action) {
            case 'complete':
                $conn->query("UPDATE tasks SET status = 'completed', completed_at = NOW() WHERE id IN ($ids_string)");
                break;
            case 'delete':
                $conn->query("DELETE FROM tasks WHERE id IN ($ids_string)");
                break;
            case 'set_priority':
                $priority = $conn->real_escape_string($input['priority']);
                $conn->query("UPDATE tasks SET priority = '$priority' WHERE id IN ($ids_string)");
                break;
        }
        
        echo json_encode(['success' => true]);
        break;

    case 'get_task_stats':
        $task_id = intval($input['task_id']);
        
        if (!canAccessTask($task_id, $user_id, $is_admin)) {
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            break;
        }
        
        // Get task details and time tracking info
        $task = $conn->query("SELECT *, 
            (SELECT SUM(duration) FROM time_entries WHERE task_id = $task_id) as total_time,
            (SELECT COUNT(*) FROM tasks WHERE parent_id = $task_id) as subtask_count,
            (SELECT COUNT(*) FROM tasks WHERE parent_id = $task_id AND status = 'completed') as completed_subtasks
            FROM tasks WHERE id = $task_id")->fetch_assoc();
        
        echo json_encode(['success' => true, 'task' => $task]);
        break;

    case 'duplicate_task':
        $task_id = intval($input['task_id']);
        
        if (!canAccessTask($task_id, $user_id, $is_admin)) {
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            break;
        }
        
        $task = $conn->query("SELECT * FROM tasks WHERE id = $task_id")->fetch_assoc();
        if ($task) {
            unset($task['id']);
            $task['task'] = $task['task'] . ' (Copy)';
            $task['status'] = 'pending';
            $task['completed_at'] = null;
            $task['user_id'] = $user_id; // Ensure new task belongs to current user
            
            $columns = implode(',', array_keys($task));
            $values = "'" . implode("','", array_map([$conn, 'real_escape_string'], array_values($task))) . "'";
            
            if ($conn->query("INSERT INTO tasks ($columns) VALUES ($values)")) {
                echo json_encode(['success' => true, 'id' => $conn->insert_id]);
            } else {
                echo json_encode(['success' => false, 'error' => $conn->error]);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Task not found']);
        }
        break;

    case 'save_template':
        $task_id = intval($input['task_id']);
        
        if (!canAccessTask($task_id, $user_id, $is_admin)) {
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            break;
        }
        
        $template_name = $conn->real_escape_string($input['template_name']);
        
        // Copy all relevant fields, handle nulls
        $task = $conn->query("SELECT task, description, category, priority, estimated_time, tags FROM tasks WHERE id = $task_id")->fetch_assoc();
        if ($task) {
            $description = $task['description'] ?? '';
            $category = $task['category'] ?? '';
            $priority = $task['priority'] ?? 'medium';
            $estimated_time = $task['estimated_time'] ?? 0;
            $tags = $task['tags'] ?? '';
            $stmt = $conn->prepare("INSERT INTO task_templates (name, description, category, priority, estimated_time, tags) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $template_name, $description, $category, $priority, $estimated_time, $tags);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'id' => $conn->insert_id]);
            } else {
                echo json_encode(['success' => false, 'error' => $conn->error]);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Task not found']);
        }
        break;

    case 'get_templates':
        $templates = [];
        $result = $conn->query("SELECT * FROM task_templates ORDER BY name");
        while ($row = $result->fetch_assoc()) {
            $templates[] = $row;
        }
        echo json_encode(['success' => true, 'templates' => $templates]);
        break;

    case 'search_suggestions':
        $query = $conn->real_escape_string($input['query']);
        $suggestions = [];
        
        // Get task titles (user-specific for non-admins)
        $user_filter = $is_admin ? "" : "AND user_id = $user_id";
        $result = $conn->query("SELECT DISTINCT task as suggestion FROM tasks WHERE task LIKE '%$query%' $user_filter LIMIT 5");
        while ($row = $result->fetch_assoc()) {
            $suggestions[] = $row['suggestion'];
        }
        
        // Get tags (user-specific for non-admins)
        $result = $conn->query("SELECT DISTINCT tags FROM tasks WHERE tags LIKE '%$query%' AND tags IS NOT NULL $user_filter LIMIT 3");
        while ($row = $result->fetch_assoc()) {
            $tags = explode(',', $row['tags']);
            foreach ($tags as $tag) {
                $tag = trim($tag);
                if (stripos($tag, $query) !== false && !in_array($tag, $suggestions)) {
                    $suggestions[] = $tag;
                }
            }
        }
        
        echo json_encode(['success' => true, 'suggestions' => $suggestions]);
        break;

    case 'get_task':
        $id = intval($input['id']);
        
        if (!canAccessTask($id, $user_id, $is_admin)) {
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            break;
        }
        
        $stmt = $conn->prepare("SELECT id, task, due_at, priority FROM tasks WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            echo json_encode(['success' => true, 'task' => $row]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Task not found']);
        }
        break;

    case 'get_tasks':
        $tasks = [];
        $result = $conn->query("SELECT id, task, priority, status, due_at FROM tasks WHERE user_id = $user_id ORDER BY created_at DESC");
        while ($row = $result->fetch_assoc()) {
            $tasks[] = $row;
        }
        echo json_encode(['success' => true, 'tasks' => $tasks]);
        break;

    case 'get_tasks_completed':
        $tasks = [];
        $result = $conn->query("SELECT id, task, priority, status, due_at FROM tasks WHERE user_id = $user_id AND status = 'completed' ORDER BY created_at DESC");
        while ($row = $result->fetch_assoc()) {
            $tasks[] = $row;
        }
        echo json_encode(['success' => true, 'tasks' => $tasks]);
        break;

    case 'get_tasks_pending':
        $tasks = [];
        $result = $conn->query("SELECT id, task, priority, status, due_at FROM tasks WHERE user_id = $user_id AND status = 'pending' ORDER BY created_at DESC");
        while ($row = $result->fetch_assoc()) {
            $tasks[] = $row;
        }
        echo json_encode(['success' => true, 'tasks' => $tasks]);
        break;

    case 'get_tasks_overdue':
        $tasks = [];
        $result = $conn->query("SELECT id, task, priority, status, due_at FROM tasks WHERE user_id = $user_id AND status = 'pending' AND due_at < NOW() AND due_at IS NOT NULL ORDER BY due_at ASC");
        while ($row = $result->fetch_assoc()) {
            $tasks[] = $row;
        }
        echo json_encode(['success' => true, 'tasks' => $tasks]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}

$conn->close();
?>