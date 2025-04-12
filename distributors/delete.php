<?php
require_once "../config/db_connect.php";

// Check if ID is provided
if (!isset($_GET['id'])) {
    header("Location: manage_distributors.php");
    exit;
}

$id = intval($_GET['id']);

// Start transaction
mysqli_begin_transaction($conn);

try {
    // First delete related transactions
    $stmt = mysqli_prepare($conn, "DELETE FROM distributor_transactions WHERE distributor_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);

    // Then delete distributor products
    $stmt = mysqli_prepare($conn, "DELETE FROM distributor_products WHERE distributor_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);

    // Finally delete the distributor
    $stmt = mysqli_prepare($conn, "DELETE FROM distributors WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);

    mysqli_commit($conn);
    $_SESSION['success'] = "Distributor deleted successfully";
} catch (Exception $e) {
    mysqli_rollback($conn);
    $_SESSION['error'] = "Error deleting distributor: " . $e->getMessage();
}

header("Location: manage_distributors.php");
exit;
?>