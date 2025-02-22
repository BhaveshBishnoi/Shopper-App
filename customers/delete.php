<?php
// Start session and include required files before any output
session_start();
require_once "../config/db_connect.php";
require_once "../includes/functions.php";

// Check if customer ID is provided
if (!isset($_GET['id'])) {
    handleError("Customer ID not provided", "index.php");
}

$customer_id = intval($_GET['id']);

// Check if customer exists and get their details
$stmt = mysqli_prepare($conn, "SELECT * FROM customers WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $customer_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    handleError("Customer not found", "index.php");
}

// Check if customer has any associated sales
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) as sales_count FROM sales WHERE customer_id = ?");
mysqli_stmt_bind_param($stmt, "i", $customer_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);

if ($row['sales_count'] > 0) {
    handleError("Cannot delete customer with associated sales", "index.php");
}

// Delete the customer
$stmt = mysqli_prepare($conn, "DELETE FROM customers WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $customer_id);

if (mysqli_stmt_execute($stmt)) {
    handleSuccess("Customer deleted successfully", "index.php");
} else {
    handleError("Error deleting customer: " . mysqli_error($conn), "index.php");
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>
