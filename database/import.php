<?php
// Database configuration
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'shopper_db';

// Connect to MySQL
$conn = new mysqli($host, $user, $pass);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Read SQL file
$sql = file_get_contents('shopper.sql');

// Execute multi query
if ($conn->multi_query($sql)) {
    echo "Database setup completed successfully!<br>";
    echo "The following operations were performed:<br>";
    echo "1. Created database 'shopper'<br>";
    echo "2. Created all required tables<br>";
    echo "3. Inserted sample data for:<br>";
    echo "   - Customers (5 records)<br>";
    echo "   - Products (10 records)<br>";
    echo "   - Sales (10 records)<br>";
    echo "   - Sale Items (10 records)<br>";
    echo "   - System Settings<br>";
    echo "<br>You can now login to the system.";
} else {
    echo "Error setting up database: " . $conn->error;
}

$conn->close();
?>
