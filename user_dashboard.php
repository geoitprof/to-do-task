<?php
include("auth.php");
requireLogin();
include("db.php");
$user_id = getCurrentUserId();

// Handle Add Task (PHP-only, from modal)
$add_success = $add_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_task'])) {
    $task = trim($_POST['task'] ?? '');
    $priority = $_POST['priority'] ?? 'medium';
    $due_at = !empty($_POST['due_at']) ? $_POST['due_at'] : null;
    if ($task) {
        $stmt = $conn->prepare("INSERT INTO tasks (task, priority, due_at, user_id, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("sssi", $task, $priority, $due_at, $user_id);
        if ($stmt->execute()) {
            $_SESSION['success'] = 'Task added successfully!';
            header('Location: user_dashboard.php');
            exit();
        } else {
            $add_error = 'Failed to add task.';
        }
    } else {
        $add_error = 'Task title is required.';
    }
}
// Show success/error messages
$success_message = $_SESSION['success'] ?? '';
unset($_SESSION['success']);
$error_message = $_SESSION['error'] ?? '';
unset($_SESSION['error']);
// Get statistics for this user
$total_tasks = $conn->query("SELECT COUNT(*) as c FROM tasks WHERE user_id = $user_id")->fetch_assoc()['c'];
$completed_tasks = $conn->query("SELECT COUNT(*) as c FROM tasks WHERE user_id = $user_id AND status = 'completed'")->fetch_assoc()['c'];
$pending_tasks = $conn->query("SELECT COUNT(*) as c FROM tasks WHERE user_id = $user_id AND status = 'pending'")->fetch_assoc()['c'];
$overdue_tasks = $conn->query("SELECT COUNT(*) as c FROM tasks WHERE user_id = $user_id AND status = 'pending' AND due_at < NOW() AND due_at IS NOT NULL")->fetch_assoc()['c'];

// Filters
$status_filter = $_GET['status'] ?? 'all';
$priority_filter = $_GET['priority'] ?? 'all';
$category_filter = $_GET['category'] ?? 'all';
$search = $_GET['search'] ?? '';
$view = $_GET['view'] ?? 'list';
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

$where_conditions = ["user_id = $user_id"];
if ($status_filter !== 'all') $where_conditions[] = "status = '" . $conn->real_escape_string($status_filter) . "'";
if ($priority_filter !== 'all') $where_conditions[] = "priority = '" . $conn->real_escape_string($priority_filter) . "'";
if ($category_filter !== 'all') $where_conditions[] = "category = '" . $conn->real_escape_string($category_filter) . "'";
if (!empty($search)) {
    $search_term = $conn->real_escape_string($search);
    $where_conditions[] = "(task LIKE '%$search_term%' OR description LIKE '%$search_term%' OR tags LIKE '%$search_term%')";
}
$where_conditions[] = "(DATE(due_at) = '" . $conn->real_escape_string($selected_date) . "' OR (due_at IS NULL AND DATE(created_at) = '" . $conn->real_escape_string($selected_date) . "'))";
$where_conditions[] = "parent_id IS NULL";
$where_clause = "WHERE " . implode(" AND ", $where_conditions);

$categories_result = $conn->query("SELECT DISTINCT category FROM tasks WHERE user_id = $user_id AND category IS NOT NULL AND category != '' ORDER BY category");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - Todo Task</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #111 0%, #333 100%);
            min-height: 100vh;
            color: #fff;
            overflow-x: hidden;
        }
        .floating-shapes {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: -1;
        }
        .shape { position: absolute; background: rgba(255,255,255,0.03); border-radius: 50%; animation: float 8s ease-in-out infinite; }
        .shape:nth-child(1) { width: 100px; height: 100px; top: 15%; right: 10%; animation-delay: 0s; }
        .shape:nth-child(2) { width: 60px; height: 60px; bottom: 20%; left: 15%; animation-delay: 3s; }
        .shape:nth-child(3) { width: 80px; height: 80px; top: 60%; right: 20%; animation-delay: 6s; }
        @keyframes float { 0%,100%{transform:translateY(0px) rotate(0deg);} 50%{transform:translateY(-20px) rotate(180deg);} }
        .side-panel {
            width: 280px;
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(20px);
            border-right: 1px solid rgba(255,255,255,0.2);
            min-height: 100vh;
            position: fixed;
            top: 0; left: 0; z-index: 1000;
            display: flex; flex-direction: column; padding-top: 80px;
            box-shadow: 4px 0 20px rgba(0,0,0,0.1);
            animation: slideInLeft 0.8s ease-out;
        }
        .side-panel .nav-link {
            color: #111; font-weight: 500; padding: 20px 32px; border-radius: 12px; margin: 4px 16px;
            display: flex; align-items: center; gap: 16px; text-decoration: none; transition: all 0.3s ease;
        }
        .side-panel .nav-link.active, .side-panel .nav-link:hover {
            background: linear-gradient(135deg, #111, #333); color: #fff; transform: translateX(8px); box-shadow: 0 8px 25px rgba(0,0,0,0.2);
        }
        .side-panel .nav-link i { font-size: 1.2rem; width: 24px; text-align: center; }
        .main-content {
            margin-left: 280px; padding: 100px 40px 40px 40px; min-height: 100vh; background: transparent;
            animation: fadeInUp 0.8s ease-out 0.2s both;
        }
        .navbar {
            position: fixed; top: 0; left: 0; right: 0; z-index: 1100;
            background: rgba(255,255,255,0.95); backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255,255,255,0.2); height: 80px;
            display: flex; align-items: center; padding: 0 40px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1); animation: slideInDown 0.8s ease-out;
        }
        .navbar-brand {
            font-weight: 700; color: #111; font-size: 24px; display: flex; align-items: center; gap: 12px;
        }
        .navbar-brand i {
            background: linear-gradient(135deg, #111, #333); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; font-size: 28px;
        }
        .navbar-user {
            margin-left: auto; display: flex; align-items: center; gap: 16px;
        }
        .navbar-user .user-badge {
            background: rgba(0,0,0,0.05); border: 1px solid rgba(0,0,0,0.1); border-radius: 12px; padding: 8px 16px; font-size: 14px; font-weight: 500; color: #111; display: flex; align-items: center; gap: 8px;
        }
        .navbar-user .role-badge {
            background: linear-gradient(135deg, #111, #333); color: white; border-radius: 20px; padding: 6px 16px; font-size: 12px; font-weight: 600; text-transform: uppercase; box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .header { margin-bottom: 40px; animation: slideInDown 0.8s ease-out 0.4s both; }
        .header-title { font-size: 2.5rem; font-weight: 700; color: #fff; margin-bottom: 8px; display: flex; align-items: center; gap: 16px; }
        .header-title i { background: linear-gradient(135deg, #fff, #ccc); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; font-size: 2.8rem; }
        .alert {
            border-radius: 16px; border: 1px solid rgba(255,255,255,0.2); padding: 16px 20px; margin-bottom: 24px; font-size: 14px; backdrop-filter: blur(10px); animation: slideInUp 0.8s ease-out 0.6s both;
        }
        .alert-info { background: rgba(0,0,0,0.08); border-color: rgba(0,0,0,0.12); color: #fff; }
        .alert-success { background: rgba(26,127,55,0.1); border-color: rgba(26,127,55,0.3); color: #4ade80; }
        .alert-danger { background: rgba(207,34,46,0.1); border-color: rgba(207,34,46,0.3); color: #f87171; }
        .stats-row { margin-bottom: 40px; animation: slideInUp 0.8s ease-out 0.8s both; }
        .stat-card {
            background: rgba(255,255,255,0.95); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.2); border-radius: 20px; padding: 32px 24px; text-align: center; transition: all 0.3s ease; position: relative; overflow: hidden; box-shadow: 0 8px 32px rgba(0,0,0,0.1);
        }
        .stat-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #111, #333, #111); }
        .stat-card:hover { transform: translateY(-8px); box-shadow: 0 16px 48px rgba(0,0,0,0.2); }
        .stat-number { font-size: 2.5rem; font-weight: 700; color: #111; margin-bottom: 8px; display: block; }
        .stat-label { color: #666; font-weight: 500; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px; }
        .btn, .btn-primary, .btn-outline-primary, .floating-add-btn {
            border-radius: 12px; font-weight: 600; font-size: 1rem; padding: 14px 28px; transition: all 0.3s ease; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; position: relative; overflow: hidden;
        }
        .btn-primary, .btn-outline-primary, .floating-add-btn {
            background: linear-gradient(135deg, #111, #333); border: none; color: #fff; box-shadow: 0 8px 25px rgba(0,0,0,0.2);
        }
        .btn-primary:hover, .btn-outline-primary:hover, .floating-add-btn:hover {
            background: linear-gradient(135deg, #222, #444); color: #fff; transform: translateY(-2px); box-shadow: 0 12px 35px rgba(0,0,0,0.3);
        }
        .btn-danger {
            background: linear-gradient(135deg, #cf222e, #a40e26); border: none; color: #fff; box-shadow: 0 4px 15px rgba(207,34,46,0.3);
        }
        .btn-danger:hover {
            background: linear-gradient(135deg, #a40e26, #8b0000); color: #fff; transform: translateY(-2px); box-shadow: 0 8px 25px rgba(207,34,46,0.4);
        }
        .btn-sm { padding: 8px 16px; font-size: 0.9rem; }
        .form-control, .form-select {
            background: rgba(255,255,255,0.95); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.2); border-radius: 12px; padding: 12px 16px; color: #111; transition: all 0.3s ease;
        }
        .form-control:focus, .form-select:focus {
            outline: none; border-color: #111; box-shadow: 0 0 0 3px rgba(17,17,17,0.1); background: rgba(255,255,255,1);
        }
        .form-label { color: #111; font-weight: 600; margin-bottom: 8px; }
        .modal-content {
            background: rgba(255,255,255,0.95); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.2); border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .modal-header { border-bottom: 1px solid rgba(255,255,255,0.2); padding: 24px 32px; }
        .modal-title { color: #111; font-weight: 600; font-size: 1.3rem; }
        .modal-body { padding: 24px 32px; }
        .modal-footer { border-top: 1px solid rgba(255,255,255,0.2); padding: 24px 32px; }
        .btn-close { background: rgba(0,0,0,0.1); border-radius: 50%; padding: 8px; }
        @keyframes slideInLeft { from{opacity:0;transform:translateX(-100px);} to{opacity:1;transform:translateX(0);} }
        @keyframes slideInDown { from{opacity:0;transform:translateY(-30px);} to{opacity:1;transform:translateY(0);} }
        @keyframes slideInUp { from{opacity:0;transform:translateY(30px);} to{opacity:1;transform:translateY(0);} }
        @keyframes fadeInUp { from{opacity:0;transform:translateY(30px);} to{opacity:1;transform:translateY(0);} }
        @media (max-width: 991px) {
            .side-panel { width: 80px; padding-top: 80px; }
            .side-panel .nav-link span { display: none; }
            .main-content { margin-left: 80px; padding: 100px 20px 40px 20px; }
            .navbar { padding: 0 20px; }
            .header-title { font-size: 2rem; }
            .stat-card { padding: 24px 16px; }
            .stat-number { font-size: 2rem; }
        }
        @media (max-width: 768px) {
            .main-content { padding: 100px 16px 40px 16px; }
            .header-title { font-size: 1.8rem; }
            .navbar-user { gap: 8px; }
            .navbar-user .user-badge { padding: 6px 12px; font-size: 12px; }
            .stats-row { grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px; }
            .modal-dialog { margin: 20px; }
        }
    </style>
</head>
<body>
    <div class="floating-shapes">
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
    </div>
    <!-- Navbar -->
    <nav class="navbar">
        <span class="navbar-brand"><i class="fas fa-list-check"></i> Todo Task</span>
        <div class="navbar-user">
            <div class="user-badge"><i class="fas fa-user"></i> <?= htmlspecialchars($_SESSION['username']) ?></div>
            <div class="role-badge"><?= ucfirst($_SESSION['user_role']) ?></div>
            <a href="logout.php" class="btn btn-danger btn-sm ms-2" title="Logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </nav>
    <!-- Side Panel -->
    <div class="side-panel">
        <a href="user_dashboard.php" class="nav-link active"><i class="fas fa-list-check"></i> <span>Dashboard</span></a>
    </div>
    <!-- Main Content -->
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="header">
                <h1 class="header-title mb-0"><i class="fas fa-list-check"></i> My Dashboard</h1>
                <span class="badge bg-primary ms-2" id="selectedDateBadge">
                    <i class="fas fa-calendar-alt"></i> <?= date('M j, Y', strtotime($selected_date)) ?>
                </span>
            </div>
            <form method="GET" id="datePickerForm" class="d-inline-flex align-items-center">
                <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>">
                <input type="hidden" name="priority" value="<?= htmlspecialchars($priority_filter) ?>">
                <input type="hidden" name="category" value="<?= htmlspecialchars($category_filter) ?>">
                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">
                <input type="date" name="date" id="chooseDateInput" class="form-control me-2 d-none" value="<?= htmlspecialchars($selected_date) ?>" onchange="document.getElementById('datePickerForm').submit();">
                <button type="button" class="btn btn-outline-primary" onclick="document.getElementById('chooseDateInput').showPicker();">
                    <i class="fas fa-calendar-alt"></i> Choose Date
                </button>
            </form>
        </div>
        <div class="mb-4">
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <strong>Welcome, <?= htmlspecialchars($_SESSION['username']) ?>!</strong><br>
                Here you can manage your tasks, track your progress, and stay organized every day.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
        <?php if ($success_message): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card text-center stat-card clickable" data-type="total">
                    <div class="card-body">
                        <div class="stat-number" style="font-size:2rem; font-weight:700; color:#24292f;"><?= $total_tasks ?></div>
                        <div class="stat-label text-muted">My Total Tasks</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center stat-card clickable" data-type="completed">
                    <div class="card-body">
                        <div class="stat-number" style="font-size:2rem; font-weight:700; color:#1a7f37;"><?= $completed_tasks ?></div>
                        <div class="stat-label text-muted">Completed</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center stat-card clickable" data-type="pending">
                    <div class="card-body">
                        <div class="stat-number" style="font-size:2rem; font-weight:700; color:#9a6700;"><?= $pending_tasks ?></div>
                        <div class="stat-label text-muted">Pending</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center stat-card clickable" data-type="overdue">
                    <div class="card-body">
                        <div class="stat-number" style="font-size:2rem; font-weight:700; color:#cf222e;"><?= $overdue_tasks ?></div>
                        <div class="stat-label text-muted">Overdue</div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Filters and Search -->
        <div class="filter-section mb-4">
            <form method="GET" id="filterForm">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Search tasks..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select" onchange="this.form.submit()">
                            <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All</option>
                            <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Priority</label>
                        <select name="priority" class="form-select" onchange="this.form.submit()">
                            <option value="all" <?= $priority_filter === 'all' ? 'selected' : '' ?>>All</option>
                            <option value="high" <?= $priority_filter === 'high' ? 'selected' : '' ?>>High</option>
                            <option value="medium" <?= $priority_filter === 'medium' ? 'selected' : '' ?>>Medium</option>
                            <option value="low" <?= $priority_filter === 'low' ? 'selected' : '' ?>>Low</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Category</label>
                        <select name="category" class="form-select" onchange="this.form.submit()">
                            <option value="all" <?= $category_filter === 'all' ? 'selected' : '' ?>>All</option>
                            <?php while ($cat = $categories_result->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars($cat['category']) ?>" <?= $category_filter === $cat['category'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['category']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">View</label>
                        <select name="view" class="form-select" onchange="toggleView()">
                            <option value="list" <?= $view === 'list' ? 'selected' : '' ?>>List</option>
                            <option value="calendar" <?= $view === 'calendar' ? 'selected' : '' ?>>Calendar</option>
                        </select>
                    </div>
                </div>
            </form>
        </div>
        <!-- List View -->
        <div id="listView" class="<?= $view === 'calendar' ? 'd-none' : '' ?>">
            <div class="mb-3">
                <input type="text" id="taskSearch" class="form-control" placeholder="Search tasks...">
            </div>
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="fas fa-tasks"></i> Pending</h5>
                    <div id="pendingTasksList">
                        <?php
                        $result = $conn->query("SELECT * FROM tasks $where_clause ORDER BY 
                            CASE WHEN status = 'completed' THEN 1 ELSE 0 END,
                            CASE priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 END,
                            due_at ASC, sort_order ASC, created_at DESC");
                        if ($result && $result->num_rows > 0):
                            while ($row = $result->fetch_assoc()):
                                if ($row['status'] === 'completed') continue;
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
                        <div class="task-item task-card-clickable<?= $row['status'] === 'completed' ? ' completed' : '' ?>" data-task-id="<?= $row['id'] ?>" data-due="<?= $row['due_at'] ?>">
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
                                    <button class="btn btn-sm" onclick="openSubtaskModal(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['task'])) ?>')" title="Add Subtask" type="button">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                    <button class="btn btn-sm btn-secondary" onclick="openEditModal(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['task'])) ?>', '<?= htmlspecialchars($row['priority']) ?>', '<?= htmlspecialchars($row['due_at']) ?>')" title="Edit" type="button">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" action="delete.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this task?');">
                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php
                        // Render subtasks (nested)
                        if ($subtasks->num_rows > 0):
                            echo '<div class="subtasks-list ms-4">';
                            while ($sub = $subtasks->fetch_assoc()):
                                $sub_completed = $sub['status'] === 'completed';
                                echo '<div class="task-item '.($sub_completed ? 'completed' : '').'" data-task-id="'.$sub['id'].'">';
                                echo '<div class="task-header">';
                                echo '<div class="task-left">';
                                echo '<input type="checkbox" class="task-checkbox" '.($sub_completed ? 'checked' : '').' onchange="toggleTaskStatus('.$sub['id'].', this.checked)">';
                                echo '<div class="task-content">';
                                echo '<h6 class="task-title '.($sub_completed ? 'text-decoration-line-through' : '').'">'.htmlspecialchars($sub['task']).'</h6>';
                                echo '</div></div></div></div>';
                            endwhile;
                            echo '</div>';
                        endif;
                        ?>
                        <?php endwhile; ?>
                        <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <h4>No tasks found for <?= date('M j, Y', strtotime($selected_date)) ?></h4>
                            <p>Try selecting a different date or add some tasks.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <h5><i class="fas fa-check-circle"></i> Completed</h5>
                    <div id="completedTasksList">
                        <?php
                        $result2 = $conn->query("SELECT * FROM tasks $where_clause ORDER BY 
                            CASE priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 END,
                            due_at ASC, sort_order ASC, created_at DESC");
                        if ($result2 && $result2->num_rows > 0):
                            while ($row = $result2->fetch_assoc()):
                                if ($row['status'] !== 'completed') continue;
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
                        <div class="task-item task-card-clickable<?= $row['status'] === 'completed' ? ' completed' : '' ?>" data-task-id="<?= $row['id'] ?>" data-due="<?= $row['due_at'] ?>">
                            <div class="task-header">
                                <div class="task-left">
                                    <input type="checkbox" class="task-checkbox" checked onchange="toggleTaskStatus(<?= $row['id'] ?>, this.checked)">
                                    <div class="task-content">
                                        <h5 class="task-title text-decoration-line-through"><?= htmlspecialchars($row['task']) ?></h5>
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
                                    <button class="btn btn-sm" onclick="openSubtaskModal(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['task'])) ?>')" title="Add Subtask" type="button">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                    <button class="btn btn-sm btn-secondary" onclick="openEditModal(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['task'])) ?>', '<?= htmlspecialchars($row['priority']) ?>', '<?= htmlspecialchars($row['due_at']) ?>')" title="Edit" type="button">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" action="delete.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this task?');">
                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php
                        // Render subtasks (nested)
                        if ($subtasks->num_rows > 0):
                            echo '<div class="subtasks-list ms-4">';
                            while ($sub = $subtasks->fetch_assoc()):
                                $sub_completed = $sub['status'] === 'completed';
                                echo '<div class="task-item '.($sub_completed ? 'completed' : '').'" data-task-id="'.$sub['id'].'">';
                                echo '<div class="task-header">';
                                echo '<div class="task-left">';
                                echo '<input type="checkbox" class="task-checkbox" '.($sub_completed ? 'checked' : '').' onchange="toggleTaskStatus('.$sub['id'].', this.checked)">';
                                echo '<div class="task-content">';
                                echo '<h6 class="task-title '.($sub_completed ? 'text-decoration-line-through' : '').'">'.htmlspecialchars($sub['task']).'</h6>';
                                echo '</div></div></div></div>';
                            endwhile;
                            echo '</div>';
                        endif;
                        ?>
                        <?php endwhile; ?>
                        <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <h4>No completed tasks found for <?= date('M j, Y', strtotime($selected_date)) ?></h4>
                            <p>Try selecting a different date or add some tasks.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <!-- Calendar View (placeholder) -->
        <div id="calendarView" class="<?= $view === 'list' ? 'd-none' : '' ?>">
            <div id="calendar"></div>
        </div>
        <!-- Floating Add Button -->
        <button class="floating-add-btn" data-bs-toggle="modal" data-bs-target="#quickAddModal" title="Add Task">
            <i class="fas fa-plus"></i>
        </button>
    </div>
    <!-- Quick Add Modal -->
    <div class="modal fade" id="quickAddModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Quick Add Task</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="add_task" value="1">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Task Title</label>
                            <input type="text" class="form-control" name="task" placeholder="Enter task title..." required>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Priority</label>
                                <select class="form-control form-select" name="priority">
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                    <option value="low">Low</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Due Date</label>
                                <input type="datetime-local" class="form-control" name="due_at">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Task</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Edit Task Modal -->
    <div class="modal fade" id="editTaskModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Task</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="update.php">
                    <input type="hidden" name="id" id="editTaskId">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Task Title</label>
                            <input type="text" class="form-control" name="task" id="editTaskTitle" required>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Priority</label>
                                <select class="form-control form-select" name="priority" id="editTaskPriority">
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                    <option value="low">Low</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Due Date</label>
                                <input type="datetime-local" class="form-control" name="due_at" id="editTaskDueAt">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Task</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Add Subtask Modal -->
    <div class="modal fade" id="addSubtaskModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Subtask</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="add_task" value="1">
                    <input type="hidden" name="parent_id" id="subtaskParentId">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Subtask Title</label>
                            <input type="text" class="form-control" name="task" id="subtaskTitle" placeholder="Enter subtask title..." required>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Priority</label>
                                <select class="form-control form-select" name="priority">
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                    <option value="low">Low</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Due Date</label>
                                <input type="datetime-local" class="form-control" name="due_at">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Subtask</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Modal for Data Table -->
    <div class="modal fade" id="statModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="statModalTitle">Tasks</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="statDataTable">
                            <thead>
                                <tr>
                                    <th>Task</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Due Date</th>
                                </tr>
                            </thead>
                            <tbody id="statDataTableBody">
                                <!-- Filled by JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script>
    function openEditModal(id, title, priority, due_at) {
        document.getElementById('editTaskId').value = id;
        document.getElementById('editTaskTitle').value = title;
        document.getElementById('editTaskPriority').value = priority;
        document.getElementById('editTaskDueAt').value = due_at ? due_at.replace(' ', 'T') : '';
        var modal = new bootstrap.Modal(document.getElementById('editTaskModal'));
        modal.show();
    }
    function openSubtaskModal(parent_id, parent_title) {
        document.getElementById('subtaskParentId').value = parent_id;
        document.getElementById('subtaskTitle').value = '';
        var modal = new bootstrap.Modal(document.getElementById('addSubtaskModal'));
        modal.show();
    }
    // Drag-and-drop between Pending and Completed
    const pendingList = document.getElementById('pendingTasksList');
    const completedList = document.getElementById('completedTasksList');
    
    new Sortable(pendingList, {
        group: 'tasks',
        animation: 150,
        onAdd: function (evt) {
            // If dropped from completed, mark as pending
            const card = evt.item;
            const taskId = card.getAttribute('data-task-id');
            if (taskId) {
                toggleTaskStatus(taskId, false, true); // true = from drag
            }
        }
    });
    new Sortable(completedList, {
        group: 'tasks',
        animation: 150,
        onAdd: function (evt) {
            // If dropped from pending, mark as completed
            const card = evt.item;
            const taskId = card.getAttribute('data-task-id');
            if (taskId) {
                toggleTaskStatus(taskId, true, true); // true = from drag
            }
        }
    });
    
    function toggleTaskStatus(taskId, completed, fromDrag) {
        fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'toggle_status', id: taskId, completed: completed ? 1 : 0 })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const card = document.querySelector('.task-item[data-task-id="' + taskId + '"]');
                if (card) {
                    // Update UI classes
                    if (completed) {
                        card.classList.add('completed');
                        card.querySelector('.task-title').classList.add('text-decoration-line-through');
                        if (!fromDrag) completedList.prepend(card);
                    } else {
                        card.classList.remove('completed');
                        card.querySelector('.task-title').classList.remove('text-decoration-line-through');
                        if (!fromDrag) pendingList.prepend(card);
                    }
                }
            } else {
                alert(data.error || 'Failed to update task status.');
            }
        });
    }
    // Stat card click logic
    document.querySelectorAll('.stat-card.clickable').forEach(function(card) {
        card.addEventListener('click', function() {
            const type = card.getAttribute('data-type');
            let url = 'api.php';
            let action = '';
            let modalTitle = '';
            switch(type) {
                case 'total':
                    action = 'get_tasks';
                    modalTitle = 'All My Tasks';
                    break;
                case 'completed':
                    action = 'get_tasks_completed';
                    modalTitle = 'Completed Tasks';
                    break;
                case 'pending':
                    action = 'get_tasks_pending';
                    modalTitle = 'Pending Tasks';
                    break;
                case 'overdue':
                    action = 'get_tasks_overdue';
                    modalTitle = 'Overdue Tasks';
                    break;
            }
            fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: action })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.tasks) {
                    const tbody = document.getElementById('statDataTableBody');
                    tbody.innerHTML = '';
                    data.tasks.forEach(function(task) {
                        const tr = document.createElement('tr');
                        tr.classList.add('clickable-row');
                        tr.style.cursor = 'pointer';
                        tr.addEventListener('click', function() {
                            window.location.href = 'task.php?id=' + task.id;
                        });
                        tr.innerHTML = `
                            <td>${task.task}</td>
                            <td>${task.priority}</td>
                            <td>${task.status}</td>
                            <td>${task.due_at ? task.due_at : ''}</td>
                        `;
                        tbody.appendChild(tr);
                    });
                    document.getElementById('statModalTitle').textContent = modalTitle;
                    var modal = new bootstrap.Modal(document.getElementById('statModal'));
                    modal.show();
                }
            });
        });
    });
    document.querySelectorAll('.task-card-clickable').forEach(function(card) {
        card.addEventListener('click', function(e) {
            // Prevent navigation if clicking on a button, link, or input
            if (e.target.closest('button') || e.target.closest('a') || e.target.closest('input')) return;
            const taskId = card.getAttribute('data-task-id');
            if (taskId) {
                window.location.href = 'task.php?id=' + taskId;
            }
        });
    });
    </script>
</body>
</html> 