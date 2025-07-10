<?php
include("db.php");

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: index.php");
    exit();
}

$stmt = $conn->prepare("SELECT * FROM tasks WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$task = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Task</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins&display=swap" rel="stylesheet">
</head>
<body class="container py-5" style="font-family: 'Poppins', sans-serif;">
  <h2 class="mb-4">✏️ Edit Task</h2>
  <form action="update.php" method="POST">
    <input type="hidden" name="id" value="<?= $task['id'] ?>">
    <div class="mb-3">
      <input type="text" name="task" class="form-control" value="<?= htmlspecialchars($task['task']) ?>" required>
    </div>
    <div class="mb-3">
      <input type="datetime-local" name="due_at" class="form-control" value="<?= $task['due_at'] ? date('Y-m-d\TH:i', strtotime($task['due_at'])) : '' ?>">
    </div>
    <button type="submit" class="btn btn-primary">Update</button>
    <a href="index.php" class="btn btn-secondary">Cancel</a>
  </form>
</body>
</html>