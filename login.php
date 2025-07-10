<?php
include("auth.php");

// Redirect if already logged in
if (isLoggedIn()) {
    if ($_SESSION['user_role'] === 'admin') {
        header('Location: admin_dashboard.php');
        exit();
    } else {
        header('Location: user_dashboard.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Todo App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #111;
            --primary-hover: #222;
            --success-color: #1a7f37;
            --warning-color: #9a6700;
            --danger-color: #cf222e;
            --dark-color: #111;
            --light-color: #f6f8fa;
            --border-color: #d0d7de;
            --text-primary: #111;
            --text-secondary: #656d76;
            --text-muted: #8c959f;
            --bg-primary: #fff;
            --bg-secondary: #f6f8fa;
            --bg-tertiary: #fafbfc;
            --shadow-small: 0 1px 3px rgba(27, 31, 36, 0.04);
            --shadow-medium: 0 6px 24px rgba(0,0,0,0.10);
            --shadow-large: 0 12px 48px rgba(0,0,0,0.18);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Noto Sans', Helvetica, Arial, sans-serif;
            background: var(--bg-secondary);
            color: var(--text-primary);
            line-height: 1.5;
            font-size: 14px;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .auth-container {
            width: 100%;
            max-width: 400px;
            padding: 20px;
        }

        .auth-card {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            box-shadow: var(--shadow-medium);
            overflow: hidden;
        }

        .auth-header {
            background: var(--bg-primary);
            border-bottom: 1px solid var(--border-color);
            padding: 24px;
            text-align: center;
        }

        .auth-header h1 {
            font-size: 24px;
            font-weight: 600;
            margin: 0 0 8px 0;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .auth-header h1 i {
            color: var(--primary-color);
        }

        .auth-header p {
            font-size: 14px;
            color: var(--text-muted);
            margin: 0;
        }

        .auth-body {
            padding: 24px;
        }

        .nav-tabs {
            border: none;
            margin-bottom: 24px;
            display: flex;
            gap: 1px;
            background: var(--border-color);
            border-radius: 6px;
            padding: 2px;
        }

        .nav-tabs .nav-link {
            border: none;
            border-radius: 4px;
            padding: 8px 16px;
            font-weight: 500;
            color: var(--text-secondary);
            background: transparent;
            transition: all 0.2s ease;
            flex: 1;
            text-align: center;
        }

        .nav-tabs .nav-link.active {
            background: var(--bg-primary);
            color: var(--text-primary);
            box-shadow: var(--shadow-small);
        }

        .nav-tabs .nav-link:hover {
            border: none;
            color: var(--text-primary);
        }

        .form-label {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 6px;
            font-size: 14px;
        }

        .form-control {
            padding: 8px 12px;
            font-size: 14px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            background: var(--bg-primary);
            color: var(--text-primary);
            transition: border-color 0.2s ease;
            width: 100%;
            box-sizing: border-box;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(9, 105, 218, 0.1);
        }

        .input-group {
            position: relative;
            display: flex;
        }

        .input-group-text {
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-right: none;
            border-radius: 6px 0 0 6px;
            padding: 8px 12px;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
        }

        .input-group .form-control {
            border-left: none;
            border-radius: 0 6px 6px 0;
        }

        .password-toggle {
            cursor: pointer;
            color: var(--text-secondary);
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-left: none;
            border-radius: 0 6px 6px 0;
            background: var(--bg-tertiary);
        }

        .password-toggle:hover {
            color: var(--primary-color);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 8px 16px;
            font-size: 14px;
            font-weight: 500;
            line-height: 20px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            background: var(--bg-primary);
            color: var(--text-primary);
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
            white-space: nowrap;
            width: 100%;
        }

        .btn:hover {
            background: var(--bg-secondary);
            border-color: var(--text-muted);
            text-decoration: none;
            color: var(--text-primary);
        }

        .btn-primary {
            background: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-hover);
            border-color: var(--primary-hover);
            color: white;
        }

        .alert {
            border-radius: 6px;
            border: 1px solid;
            padding: 12px 16px;
            margin-bottom: 16px;
            font-size: 14px;
        }

        .alert-danger {
            background: #ffebe9;
            border-color: #ff8182;
            color: #cf222e;
        }

        .alert-success {
            background: #dafbe1;
            border-color: #7ee787;
            color: #1a7f37;
        }

        .text-center {
            text-align: center;
        }

        .text-muted {
            color: var(--text-muted);
        }

        .small {
            font-size: 12px;
        }

        .mt-4 {
            margin-top: 16px;
        }

        .mb-3 {
            margin-bottom: 12px;
        }

        .mb-4 {
            margin-bottom: 16px;
        }

        .me-2 {
            margin-right: 8px;
        }

        .me-1 {
            margin-right: 4px;
        }

        @media (max-width: 480px) {
            .auth-container {
                padding: 16px;
            }
            
            .auth-header {
                padding: 20px;
            }
            
            .auth-body {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1><i class=""></i>Todo Task</h1>
                <p>Manage your tasks efficiently with our secure role-based system</p>
            </div>
            
            <div class="auth-body">

                <div class="tab-content" id="authTabsContent">
                    <!-- Login Tab -->
                    <div class="tab-pane fade show active" id="login" role="tabpanel">
                        <?php if (isset($login_error)): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($login_error) ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <input type="hidden" name="action" value="login">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            
                            <div class="mb-4">
                                <label for="username" class="form-label">Username</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-user"></i>
                                    </span>
                                    <input type="text" class="form-control" id="username" name="username" required>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-lock"></i>
                                    </span>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <span class="input-group-text password-toggle" onclick="togglePassword('password')">
                                        <i class="fas fa-eye" id="password-icon"></i>
                                    </span>
                                </div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-sign-in-alt me-2"></i>Login
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(inputId + '-icon');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Auto-switch to register tab if there's a registration success message
        <?php if (isset($register_success)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const registerTab = new bootstrap.Tab(document.getElementById('register-tab'));
            registerTab.show();
        });
        <?php endif; ?>
    </script>
</body>
</html> 