<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include("db.php");

// Authentication functions
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

function requireRole($required_role) {
    requireLogin();
    if (!hasRole($required_role)) {
        header("Location: index.php?error=insufficient_permissions");
        exit();
    }
}

function hasRole($role) {
    if (!isLoggedIn()) return false;
    return $_SESSION['user_role'] === $role || $_SESSION['user_role'] === 'admin';
}

function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getCurrentUserRole() {
    return $_SESSION['user_role'] ?? null;
}

// Login function
function loginUser($username, $password) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT id, username, email, password_hash, role FROM users WHERE username = ? AND is_active = 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password_hash'])) {
            // Update last login
            $update_stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $update_stmt->bind_param("i", $user['id']);
            $update_stmt->execute();
            
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            
            return ['success' => true, 'user' => $user];
        }
    }
    
    return ['success' => false, 'error' => 'Invalid username or password'];
}

// Registration function
function registerUser($username, $email, $password, $confirm_password) {
    global $conn;
    
    // Validation
    if (empty($username) || empty($email) || empty($password)) {
        return ['success' => false, 'error' => 'All fields are required'];
    }
    
    if ($password !== $confirm_password) {
        return ['success' => false, 'error' => 'Passwords do not match'];
    }
    
    if (strlen($password) < 8) {
        return ['success' => false, 'error' => 'Password must be at least 8 characters long'];
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Invalid email format'];
    }
    
    // Check if username or email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return ['success' => false, 'error' => 'Username or email already exists'];
    }
    
    // Hash password and create user
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, 'user')");
    $stmt->bind_param("sss", $username, $email, $password_hash);
    
    if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Registration successful! Please login.'];
    } else {
        return ['success' => false, 'error' => 'Registration failed. Please try again.'];
    }
}

// Logout function
function logoutUser() {
    session_destroy();
    header("Location: login.php");
    exit();
}

// CSRF protection
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'login') {
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $login_error = 'Invalid request';
        } else {
            $result = loginUser($_POST['username'], $_POST['password']);
            if ($result['success']) {
                // Route based on role
                if ($_SESSION['user_role'] === 'admin') {
                    header('Location: admin_dashboard.php');
                    exit();
                } else {
                    header('Location: user_dashboard.php');
                    exit();
                }
            } else {
                $login_error = $result['error'];
            }
        }
    } elseif ($_POST['action'] === 'register') {
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $register_error = 'Invalid request';
        } else {
            $result = registerUser($_POST['username'], $_POST['email'], $_POST['password'], $_POST['confirm_password']);
            if ($result['success']) {
                $register_success = $result['message'];
            } else {
                $register_error = $result['error'];
            }
        }
    }
}
?> 