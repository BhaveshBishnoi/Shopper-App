<?php
session_start();
require_once "../config/db_connect.php";

// 1. Ensure the table has no required 'email' column
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)";

// Force-alter table if 'email' exists (removes it)
$alter_sql = "ALTER TABLE users DROP COLUMN IF EXISTS email";
mysqli_query($conn, $alter_sql);

// Proceed with table creation
if(mysqli_query($conn, $sql)){
    // Check if default admin exists
    $check_admin = "SELECT * FROM users WHERE username = 'admin'";
    $result = mysqli_query($conn, $check_admin);
    
    if(mysqli_num_rows($result) == 0){
        // Create admin (no email needed)
        $default_password = password_hash("admin123", PASSWORD_DEFAULT);
        $insert_admin = "INSERT INTO users (username, password) VALUES (?, ?)";
        
        $stmt = mysqli_prepare($conn, $insert_admin);
        $admin_username = "admin";
        mysqli_stmt_bind_param($stmt, "ss", $admin_username, $default_password);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

// LOGIN HANDLING (unchanged)
if($_SERVER["REQUEST_METHOD"] == "POST"){
    if(empty(trim($_POST["username"])) || empty(trim($_POST["password"]))){
        header("location: ../index.php?error=empty_fields");
        exit();
    }

    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);
    
    $sql = "SELECT id, username, password FROM users WHERE username = ?";
    
    if($stmt = mysqli_prepare($conn, $sql)){
        mysqli_stmt_bind_param($stmt, "s", $username);
        
        if(mysqli_stmt_execute($stmt)){
            mysqli_stmt_store_result($stmt);
            
            if(mysqli_stmt_num_rows($stmt) == 1){
                mysqli_stmt_bind_result($stmt, $id, $username, $hashed_password);
                if(mysqli_stmt_fetch($stmt)){
                    if(password_verify($password, $hashed_password)){
                        $_SESSION["loggedin"] = true;
                        $_SESSION["id"] = $id;
                        $_SESSION["username"] = $username;
                        header("location: ../dashboard.php");
                        exit();
                    } else {
                        header("location: ../index.php?error=invalid_password");
                        exit();
                    }
                }
            } else {
                header("location: ../index.php?error=invalid_username");
                exit();
            }
        } else {
            header("location: ../index.php?error=server_error");
            exit();
        }
        mysqli_stmt_close($stmt);
    }
    mysqli_close($conn);
}