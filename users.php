<?php
include("auth.php");
requireRole('admin');

include("db.php");

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update_role':
            $user_id = intval($_POST['user_id']);
            $new_role = $_POST['role'];
            
            if (in_array($new_role, ['admin', 'manager', 'user'])) {
                $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
                $stmt->bind_param("si", $new_role, $user_id);
                if ($stmt->execute()) {
                    $success_message = "User role updated successfully";
                } else {
                    $error_message = "Failed to update user role";
                }
            }
            break;
            
        case 'toggle_status':
            $user_id = intval($_POST['user_id']);
            $new_status = $_POST['status'] === 'active' ? 1 : 0;
            
            $stmt = $conn->prepare("UPDATE users SET is_active = ? WHERE id = ?");
            $stmt->bind_param("ii", $new_status, $user_id);
            if ($stmt->execute()) {
                $success_message = "User status updated successfully";
            } else {
                $error_message = "Failed to update user status";
            }
            break;
            
        case 'delete_user':
            $user_id = intval($_POST['user_id']);
            
            // Don't allow admin to delete themselves
            if ($user_id == getCurrentUserId()) {
                $error_message = "You cannot delete your own account";
                break;
            }
            
            // Delete user's tasks first
            $conn->query("DELETE FROM tasks WHERE user_id = $user_id");
            
            // Delete user
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            if ($stmt->execute()) {
                $success_message = "User deleted successfully";
            } else {
                $error_message = "Failed to delete user";
            }
            break;

        case 'create_user':
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? 'user';
            if ($username && $email && $password && in_array($role, ['admin', 'manager', 'user'])) {
                // Check if username or email already exists
                $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
                $stmt->bind_param("ss", $username, $email);
                $stmt->execute();
                $stmt->store_result();
                if ($stmt->num_rows > 0) {
                    $error_message = "Username or email already exists.";
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, role, is_active, created_at) VALUES (?, ?, ?, ?, 1, NOW())");
                    $stmt->bind_param("ssss", $username, $email, $hashed_password, $role);
                    if ($stmt->execute()) {
                        header('Location: users.php');
                        exit();
                    } else {
                        $error_message = "Failed to create user.";
                    }
                }
            } else {
                $error_message = "All fields are required and must be valid.";
            }
            break;
    }
}

// Get all users
$users = [];
$result = $conn->query("SELECT id, username, email, role, is_active, created_at, last_login FROM users ORDER BY created_at DESC");
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Todo Task</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #111 0%, #333 100%);
            min-height: 100vh;
            color: #fff;
            overflow-x: hidden;
        }

        /* Side Panel */
        .side-panel {
            width: 280px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-right: 1px solid rgba(255, 255, 255, 0.2);
            min-height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            padding-top: 80px;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1);
            animation: slideInLeft 0.8s ease-out;
        }

        .side-panel .nav-link {
            color: #111;
            font-weight: 500;
            padding: 20px 32px;
            border-radius: 0;
            transition: all 0.3s ease;
            margin: 4px 16px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 16px;
            text-decoration: none;
        }

        .side-panel .nav-link.active,
        .side-panel .nav-link:hover {
            background: linear-gradient(135deg, #111, #333);
            color: #fff;
            transform: translateX(8px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }

        .side-panel .nav-link i {
            font-size: 1.2rem;
            width: 24px;
            text-align: center;
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            padding: 100px 40px 40px 40px;
            min-height: 100vh;
            background: transparent;
            animation: fadeInUp 0.8s ease-out 0.2s both;
        }

        /* Navbar */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1100;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            height: 80px;
            display: flex;
            align-items: center;
            padding: 0 40px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            animation: slideInDown 0.8s ease-out;
        }

        .navbar-brand {
            font-weight: 700;
            color: #111;
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .navbar-brand i {
            background: linear-gradient(135deg, #111, #333);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 28px;
        }

        .navbar-user {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .navbar-user .user-badge {
            background: rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 12px;
            padding: 8px 16px;
            font-size: 14px;
            font-weight: 500;
            color: #111;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .navbar-user .role-badge {
            background: linear-gradient(135deg, #111, #333);
            color: white;
            border-radius: 20px;
            padding: 6px 16px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        /* Header */
        .header {
            margin-bottom: 40px;
            animation: slideInDown 0.8s ease-out 0.4s both;
        }

        .header-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .header-title i {
            background: linear-gradient(135deg, #fff, #ccc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 2.8rem;
        }

        /* Alerts */
        .alert {
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 16px 20px;
            margin-bottom: 24px;
            font-size: 14px;
            backdrop-filter: blur(10px);
            animation: slideInUp 0.8s ease-out 0.6s both;
        }

        .alert-success {
            background: rgba(26, 127, 55, 0.1);
            border-color: rgba(26, 127, 55, 0.3);
            color: #4ade80;
        }

        .alert-danger {
            background: rgba(207, 34, 46, 0.1);
            border-color: rgba(207, 34, 46, 0.3);
            color: #f87171;
        }

        /* Stats Section */
        .stats-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
            animation: slideInUp 0.8s ease-out 0.8s both;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            padding: 32px 24px;
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #111, #333, #111);
        }

        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.2);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #111;
            margin-bottom: 8px;
            display: block;
        }

        .stat-label {
            color: #666;
            font-weight: 500;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* User Cards */
        .user-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            padding: 24px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            animation: slideInUp 0.8s ease-out 1s both;
        }

        .user-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(180deg, #111, #333);
        }

        .user-card.admin::before {
            background: linear-gradient(180deg, #cf222e, #a40e26);
        }

        .user-card.manager::before {
            background: linear-gradient(180deg, #9a6700, #7c5a00);
        }

        .user-card.user::before {
            background: linear-gradient(180deg, #1a7f37, #116329);
        }

        .user-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.2);
        }

        .user-card h5 {
            color: #111;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .user-card .text-muted {
            color: #666 !important;
        }

        /* Role Badges */
        .role-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .role-badge.admin {
            background: linear-gradient(135deg, #cf222e, #a40e26);
            color: white;
        }

        .role-badge.manager {
            background: linear-gradient(135deg, #9a6700, #7c5a00);
            color: white;
        }

        .role-badge.user {
            background: linear-gradient(135deg, #1a7f37, #116329);
            color: white;
        }

        /* Status Badges */
        .status-badge {
            padding: 4px 10px;
            border-radius: 16px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-badge.active {
            background: linear-gradient(135deg, #1a7f37, #116329);
            color: white;
        }

        .status-badge.inactive {
            background: linear-gradient(135deg, #cf222e, #a40e26);
            color: white;
        }

        /* Buttons */
        .btn {
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.9rem;
            padding: 8px 16px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            position: relative;
            overflow: hidden;
        }

        .btn-primary {
            background: linear-gradient(135deg, #111, #333);
            border: none;
            color: #fff;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #222, #444);
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        }

        .btn-danger {
            background: linear-gradient(135deg, #cf222e, #a40e26);
            border: none;
            color: #fff;
            box-shadow: 0 4px 15px rgba(207, 34, 46, 0.3);
        }

        .btn-danger:hover {
            background: linear-gradient(135deg, #a40e26, #8b0000);
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(207, 34, 46, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, #1a7f37, #116329);
            border: none;
            color: #fff;
            box-shadow: 0 4px 15px rgba(26, 127, 55, 0.3);
        }

        .btn-success:hover {
            background: linear-gradient(135deg, #116329, #0d4f1f);
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(26, 127, 55, 0.4);
        }

        .btn-warning {
            background: linear-gradient(135deg, #9a6700, #7c5a00);
            border: none;
            color: #fff;
            box-shadow: 0 4px 15px rgba(154, 103, 0, 0.3);
        }

        .btn-warning:hover {
            background: linear-gradient(135deg, #7c5a00, #5a4200);
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(154, 103, 0, 0.4);
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8rem;
        }

        /* Form Controls */
        .form-control, .form-select {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            padding: 12px 16px;
            color: #111;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: #111;
            box-shadow: 0 0 0 3px rgba(17, 17, 17, 0.1);
            background: rgba(255, 255, 255, 1);
        }

        .form-label {
            color: #111;
            font-weight: 600;
            margin-bottom: 8px;
        }

        /* Modal */
        .modal-content {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            padding: 24px 32px;
        }

        .modal-title {
            color: #111;
            font-weight: 600;
            font-size: 1.3rem;
        }

        .modal-body {
            padding: 24px 32px;
        }

        .modal-footer {
            border-top: 1px solid rgba(255, 255, 255, 0.2);
            padding: 24px 32px;
        }

        .btn-close {
            background: rgba(0, 0, 0, 0.1);
            border-radius: 50%;
            padding: 8px;
        }

        /* Floating Elements */
        .floating-shapes {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: -1;
        }

        .shape {
            position: absolute;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 50%;
            animation: float 8s ease-in-out infinite;
        }

        .shape:nth-child(1) {
            width: 100px;
            height: 100px;
            top: 15%;
            right: 10%;
            animation-delay: 0s;
        }

        .shape:nth-child(2) {
            width: 60px;
            height: 60px;
            bottom: 20%;
            left: 15%;
            animation-delay: 3s;
        }

        .shape:nth-child(3) {
            width: 80px;
            height: 80px;
            top: 60%;
            right: 20%;
            animation-delay: 6s;
        }

        /* Animations */
        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-100px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0px) rotate(0deg);
            }
            50% {
                transform: translateY(-20px) rotate(180deg);
            }
        }

        /* Responsive */
        @media (max-width: 991px) {
            .side-panel {
                width: 80px;
                padding-top: 80px;
            }

            .side-panel .nav-link span {
                display: none;
            }

            .main-content {
                margin-left: 80px;
                padding: 100px 20px 40px 20px;
        }

        .navbar {
                padding: 0 20px;
            }

            .header-title {
                font-size: 2rem;
            }

            .stat-card {
                padding: 24px 16px;
            }

            .stat-number {
                font-size: 2rem;
            }

            .user-card {
                padding: 20px;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 100px 16px 40px 16px;
            }

            .header-title {
                font-size: 1.8rem;
            }

        .navbar-user {
                gap: 8px;
        }

        .navbar-user .user-badge {
                padding: 6px 12px;
            font-size: 12px;
            }

            .stats-section {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 16px;
            }

            .modal-dialog {
                margin: 20px;
            }
        }

        /* Modal z-index fix */
        .modal {
            z-index: 2000;
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
        <span class="navbar-brand">
            <i class="fas fa-users-cog"></i>
            User Management
        </span>
        <div class="navbar-user">
            <div class="user-badge">
                <i class="fas fa-user"></i>
                <?= htmlspecialchars($_SESSION['username']) ?>
            </div>
            <div class="role-badge">
                <?= ucfirst($_SESSION['user_role']) ?>
            </div>
            <a href="logout.php" class="btn btn-danger btn-sm" title="Logout">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </div>
    </nav>

    <!-- Side Panel -->
    <div class="side-panel">
        <a href="admin_dashboard.php" class="nav-link">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
        <a href="users.php" class="nav-link active">
            <i class="fas fa-users"></i>
            <span>User Management</span>
        </a>
        <a href="logout.php" class="nav-link">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="header-title">
                    <i class="fas fa-users"></i>
                    Manage Users
                </h1>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
                    <i class="fas fa-plus"></i>
                    Create New User
                </button>
            </div>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

            <!-- Statistics -->
            <div class="stats-section">
                <div class="stat-card">
                    <div class="stat-number"><?= count($users) ?></div>
                    <div class="stat-label">Total Users</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= count(array_filter($users, fn($u) => $u['role'] === 'admin')) ?></div>
                    <div class="stat-label">Admins</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= count(array_filter($users, fn($u) => $u['role'] === 'manager')) ?></div>
                    <div class="stat-label">Managers</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= count(array_filter($users, fn($u) => $u['is_active'])) ?></div>
                    <div class="stat-label">Active Users</div>
                </div>
            </div>

        <!-- Users List -->
        <div class="row">
            <?php foreach ($users as $user): ?>
                <div class="col-md-6 col-lg-4 mb-3">
                    <div class="user-card <?= $user['role'] ?>">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h5 class="mb-1"><?= htmlspecialchars($user['username']) ?></h5>
                                <small class="text-muted"><?= htmlspecialchars($user['email']) ?></small>
                            </div>
                            <div class="text-end">
                                <span class="role-badge <?= $user['role'] ?>"><?= ucfirst($user['role']) ?></span>
                                <br>
                                <span class="status-badge <?= $user['is_active'] ? 'active' : 'inactive' ?>">
                                    <?= $user['is_active'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <small class="text-muted">
                                <i class="fas fa-calendar me-1"></i>
                                Joined: <?= date('M j, Y', strtotime($user['created_at'])) ?>
                            </small>
                            <?php if ($user['last_login']): ?>
                                <br>
                                <small class="text-muted">
                                    <i class="fas fa-clock me-1"></i>
                                    Last login: <?= date('M j, Y H:i', strtotime($user['last_login'])) ?>
                                </small>
                            <?php endif; ?>
                        </div>

                        <div class="d-flex gap-2">
                            <?php if ($user['id'] != getCurrentUserId()): ?>
                                <!-- Role Update -->
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="update_role">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <select name="role" class="form-select form-select-sm" onchange="this.form.submit()">
                                        <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>User</option>
                                        <option value="manager" <?= $user['role'] === 'manager' ? 'selected' : '' ?>>Manager</option>
                                        <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                    </select>
                                </form>

                                <!-- Status Toggle -->
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <input type="hidden" name="status" value="<?= $user['is_active'] ? 'inactive' : 'active' ?>">
                                    <button type="submit" class="btn btn-sm <?= $user['is_active'] ? 'btn-warning' : 'btn-success' ?>">
                                        <i class="fas <?= $user['is_active'] ? 'fa-ban' : 'fa-check' ?>"></i>
                                    </button>
                                </form>

                                <!-- Delete User -->
                                <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this user? This will also delete all their tasks.')">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted small">Current User</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        </div>

    <!-- Create User Modal -->
    <div class="modal fade" id="createUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="users.php">
                    <div class="modal-header">
                        <h5 class="modal-title">Create New User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_user">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select" required>
                                <option value="user">User</option>
                                <option value="manager">Manager</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 