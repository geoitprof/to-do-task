<?php
// session_start(); // Removed from db.php. Handle session in main/auth files.

$host = "localhost";
$user = "root";
$pass = ""; // change this if needed
$db = "todo_app";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create users table if it doesn't exist
$create_users_table = "
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'manager', 'user') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL
)";

if (!$conn->query($create_users_table)) {
    die("Error creating users table: " . $conn->error);
}

// Add user_id column to tasks table if it doesn't exist
$check_user_id_column = "SHOW COLUMNS FROM tasks LIKE 'user_id'";
$result = $conn->query($check_user_id_column);
if ($result->num_rows == 0) {
    $add_user_id_column = "ALTER TABLE tasks ADD COLUMN user_id INT DEFAULT 1";
    $conn->query($add_user_id_column);
}

// Create default admin user if no users exist
$check_users = "SELECT COUNT(*) as count FROM users";
$result = $conn->query($check_users);
$row = $result->fetch_assoc();

if ($row['count'] == 0) {
    $default_password = password_hash('admin123', PASSWORD_DEFAULT);
    $create_admin = "INSERT INTO users (username, email, password_hash, role) VALUES ('admin', 'admin@todoapp.com', '$default_password', 'admin')";
    $conn->query($create_admin);
}
?>