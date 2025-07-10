<?php
include("auth.php");
requireLogin();
include("db.php");
$user_id = getCurrentUserId();
$is_admin = hasRole('admin');
$task_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$task_id) {
    die('<div class="alert alert-danger">Invalid task ID.</div>');
}
// Check access
$stmt = $conn->prepare("SELECT * FROM tasks WHERE id = ?");
$stmt->bind_param("i", $task_id);
$stmt->execute();
$result = $stmt->get_result();
$task = $result->fetch_assoc();
if (!$task || (!$is_admin && $task['user_id'] != $user_id)) {
    die('<div class="alert alert-danger">Access denied or task not found.</div>');
}
// Get subtasks
$subtasks = [];
$subtask_result = $conn->query("SELECT * FROM tasks WHERE parent_id = $task_id ORDER BY created_at");
while ($row = $subtask_result->fetch_assoc()) {
    $subtasks[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: rgba(30, 34, 40, 0.85);
            min-height: 100vh;
            overflow-y: auto;
        }
        .modal-center {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .task-modal-card {
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 8px 40px rgba(30,34,90,0.18), 0 1.5px 6px rgba(9,105,218,0.08);
            max-width: 540px;
            width: 100%;
            padding: 2.5rem 2rem 2rem 2rem;
            position: relative;
            animation: fadeInModal 0.4s cubic-bezier(.4,2,.6,1);
        }
        @keyframes fadeInModal {
            from { opacity: 0; transform: translateY(40px) scale(0.98); }
            to { opacity: 1; transform: none; }
        }
        .close-btn {
            position: absolute;
            top: 18px;
            right: 18px;
            background: #f6f8fa;
            border: none;
            border-radius: 50%;
            width: 38px;
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            color: #656d76;
            transition: background 0.2s;
        }
        .close-btn:hover {
            background: #eaeef2;
            color: #cf222e;
        }
        .task-modal-card h2 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .task-modal-card .badge {
            font-size: 0.95rem;
            margin-right: 0.4em;
            margin-bottom: 0.2em;
        }
        .task-modal-card .card-title {
            margin-bottom: 0.5rem;
        }
        .task-modal-card .subtasks-list {
            margin-top: 1.5rem;
        }
        .task-modal-card .list-group-item {
            border: none;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            background: #f6f8fa;
        }
        @media (max-width: 600px) {
            .task-modal-card { padding: 1.2rem 0.5rem 1.5rem 0.5rem; }
        }
    </style>
</head>
<body>
    <div class="modal-center">
        <div class="task-modal-card">
            <button class="close-btn" onclick="window.location.href='user_dashboard.php'" title="Close"><i class="fas fa-times"></i></button>
            <h2 class="card-title mb-3"><i class="fas fa-tasks me-2 text-primary"></i><?= htmlspecialchars($task['task']) ?></h2>
            <div class="mb-2">
                <span class="badge bg-<?= $task['priority'] === 'high' ? 'danger' : ($task['priority'] === 'medium' ? 'warning text-dark' : 'success') ?>">Priority: <?= ucfirst($task['priority']) ?></span>
                <span class="badge bg-<?= $task['status'] === 'completed' ? 'success' : 'secondary' ?>">Status: <?= ucfirst($task['status']) ?></span>
                <?php if ($task['due_at']): ?>
                    <span class="badge bg-primary">Due: <?= date('M j, Y H:i', strtotime($task['due_at'])) ?></span>
                <?php endif; ?>
                <?php if (!empty($task['category'])): ?>
                    <span class="badge bg-info text-dark">Category: <?= htmlspecialchars($task['category']) ?></span>
                <?php endif; ?>
                <?php if (!empty($task['tags'])): ?>
                    <span class="badge bg-secondary">Tags: <?= htmlspecialchars($task['tags']) ?></span>
                <?php endif; ?>
            </div>
            <?php if (!empty($task['description'])): ?>
                <p class="mt-3 mb-2" style="font-size:1.1rem; color:#24292f; line-height:1.6;">
                    <?= nl2br(htmlspecialchars($task['description'])) ?>
                </p>
            <?php endif; ?>
            <?php if ($task['estimated_time']): ?>
                <div class="mb-2"><i class="fas fa-stopwatch"></i> Estimated Time: <?= intval($task['estimated_time']) ?> min</div>
            <?php endif; ?>
            <div class="mt-4 mb-2 d-flex gap-2">
                <a href="edit.php?id=<?= $task['id'] ?>" class="btn btn-outline-secondary"><i class="fas fa-edit"></i> Edit</a>
                <form method="POST" action="delete.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this task?');">
                    <input type="hidden" name="id" value="<?= $task['id'] ?>">
                    <button type="submit" class="btn btn-outline-danger"><i class="fas fa-trash"></i> Delete</button>
                </form>
            </div>
            <?php if (count($subtasks) > 0): ?>
            <div class="subtasks-list">
                <div class="fw-bold mb-2"><i class="fas fa-list"></i> Subtasks</div>
                <ul class="list-group list-group-flush">
                    <?php foreach ($subtasks as $sub): ?>
                    <li class="list-group-item">
                        <span class="fw-bold"> <?= htmlspecialchars($sub['task']) ?> </span>
                        <span class="badge bg-<?= $sub['priority'] === 'high' ? 'danger' : ($sub['priority'] === 'medium' ? 'warning text-dark' : 'success') ?> ms-2">Priority: <?= ucfirst($sub['priority']) ?></span>
                        <span class="badge bg-<?= $sub['status'] === 'completed' ? 'success' : 'secondary' ?> ms-2">Status: <?= ucfirst($sub['status']) ?></span>
                        <?php if ($sub['due_at']): ?>
                            <span class="badge bg-primary ms-2">Due: <?= date('M j, Y H:i', strtotime($sub['due_at'])) ?></span>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 