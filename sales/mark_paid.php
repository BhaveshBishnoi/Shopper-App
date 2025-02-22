<?php
session_start();
require_once "../includes/functions.php";
require_once "../config/db_connect.php";

if (!isset($_GET['id'])) {
    handleError("No sale specified", "index.php");
}

$id = intval($_GET['id']);

// Update sale status to paid and set payment method to cash
$query = "UPDATE sales SET payment_status = 'paid', payment_method = 'cash' WHERE id = ?";

if ($stmt = mysqli_prepare($conn, $query)) {
    mysqli_stmt_bind_param($stmt, "i", $id);
    
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success'] = "Sale has been marked as paid with cash payment.";
    } else {
        $_SESSION['error'] = "Error updating sale status: " . mysqli_error($conn);
    }
    
    mysqli_stmt_close($stmt);
} else {
    $_SESSION['error'] = "Database error: " . mysqli_error($conn);
}

// Redirect back to the sale view page
header("Location: view.php?id=" . $id);
exit;
?>
