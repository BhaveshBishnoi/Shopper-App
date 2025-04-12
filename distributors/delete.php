<?php
session_start();
require_once "../config/db_connect.php";
require_once "../includes/functions.php";

try {
    // Check if distributor ID is provided
    if (!isset($_GET['id'])) {
        throw new Exception("Distributor ID not provided");
    }

    $id = intval($_GET['id']);

    // Check if distributor exists
    $sql = "SELECT * FROM distributors WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        throw new Exception("Database error: " . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (!$result || mysqli_num_rows($result) === 0) {
        throw new Exception("Distributor not found");
    }

    $distributor = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    // Check if distributor has pending amount
    if ($distributor['pending_amount'] > 0) {
        throw new Exception("Cannot delete distributor with pending payments");
    }

    // Delete distributor
    $sql = "DELETE FROM distributors WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        throw new Exception("Database error: " . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($stmt, "i", $id);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Error deleting distributor: " . mysqli_error($conn));
    }

    mysqli_stmt_close($stmt);
    mysqli_close($conn);

    // Return success response
    $_SESSION['success'] = "Distributor deleted successfully";
    header("Location: index.php");
    exit;

} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header("Location: index.php");
    exit;
}
?>