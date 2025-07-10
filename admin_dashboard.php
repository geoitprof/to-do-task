<?php
include("auth.php");
requireRole('admin');
include("db.php");

// Get statistics
$total_users = $conn->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'];
$total_tasks = $conn->query("SELECT COUNT(*) as c FROM tasks")->fetch_assoc()['c'];
$completed_tasks = $conn->query("SELECT COUNT(*) as c FROM tasks WHERE status = 'completed'")->fetch_assoc()['c'];
$pending_tasks = $conn->query("SELECT COUNT(*) as c FROM tasks WHERE status = 'pending'")->fetch_assoc()['c'];
$overdue_tasks = $conn->query("SELECT COUNT(*) as c FROM tasks WHERE status = 'pending' AND due_at < NOW() AND due_at IS NOT NULL")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Todo Task</title>
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

        /* Welcome Alert */
        .welcome-alert {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 40px;
            color: #fff;
            animation: slideInUp 0.8s ease-out 0.6s both;
        }

        .welcome-alert strong {
            color: #fff;
            font-size: 1.1rem;
        }

        /* Stats Cards */
        .stats-row {
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

        /* Buttons */
        .btn {
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            padding: 14px 28px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            position: relative;
            overflow: hidden;
        }

        .btn-primary {
            background: linear-gradient(135deg, #111, #333);
            border: none;
            color: #fff;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #222, #444);
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.3);
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc3545, #c82333);
            border: none;
            color: #fff;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }

        .btn-danger:hover {
            background: linear-gradient(135deg, #c82333, #bd2130);
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(220, 53, 69, 0.4);
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
            <i class="fas fa-check-circle"></i>
            Todo Task
        </span>
        <div class="navbar-user">
            <div class="user-badge">
                <i class="fas fa-user"></i>
                <?= htmlspecialchars($_SESSION['username']) ?>
            </div>
            <div class="role-badge">Admin</div>
            <a href="logout.php" class="btn btn-danger btn-sm" title="Logout">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </div>
    </nav>

    <!-- Side Panel -->
    <div class="side-panel">
        <a href="admin_dashboard.php" class="nav-link active">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
        <a href="users.php" class="nav-link">
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
            <h1 class="header-title">
                <i class="fas fa-tachometer-alt"></i>
                Admin Dashboard
            </h1>
        </div>

        <div class="welcome-alert">
            <strong>Welcome back, <?= htmlspecialchars($_SESSION['username']) ?>! 👋</strong><br>
            As an <b>administrator</b>, you have full control over the system. Manage users, monitor statistics, and oversee all activities across the platform.
        </div>

        <div class="row g-4 stats-row">
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="stat-card">
                    <div class="stat-number"><?= $total_users ?></div>
                    <div class="stat-label">Total Users</div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="stat-card">
                    <div class="stat-number"><?= $total_tasks ?></div>
                    <div class="stat-label">Total Tasks</div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="stat-card">
                    <div class="stat-number" style="color: #1a7f37;"><?= $completed_tasks ?></div>
                    <div class="stat-label">Completed</div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="stat-card">
                    <div class="stat-number" style="color: #9a6700;"><?= $pending_tasks ?></div>
                    <div class="stat-label">Pending</div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="stat-card">
                    <div class="stat-number" style="color: #cf222e;"><?= $overdue_tasks ?></div>
                    <div class="stat-label">Overdue</div>
                </div>
            </div>
        </div>

        <div class="mb-4">
            <a href="users.php" class="btn btn-primary">
                <i class="fas fa-users"></i>
                Manage Users
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 