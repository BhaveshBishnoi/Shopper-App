<?php
// Database configuration
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'shopper');

// Report all errors
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Create connection
try {
    // Connect directly to the database
    $conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
    
    if (!$conn) {
        throw new Exception("Connection failed: " . mysqli_connect_error());
    }
    
    // Set charset to utf8mb4
    mysqli_set_charset($conn, 'utf8mb4');
    
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Set timezone
date_default_timezone_set('Asia/Kolkata');

// Currency symbol
define('CURRENCY_SYMBOL', '₹');

// Function to handle database errors
function handleDatabaseError($error, $sql = '') {
    global $conn;
    $message = "Database error: " . $error;
    if ($sql) {
        $message .= "\nSQL: " . $sql;
    }
    error_log($message);
    die($message);
}
?>
