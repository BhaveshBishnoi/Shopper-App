<?php
// Start session and include required files before any output
session_start();
require_once "../config/db_connect.php";
require_once "../includes/functions.php";

try {
    // Check if product ID is provided
    if (!isset($_GET['id'])) {
        throw new Exception("Product ID not provided");
    }

    $id = intval($_GET['id']);

    // Check if product exists
    $sql = "SELECT * FROM products WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        throw new Exception("Database error: " . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (!$result || mysqli_num_rows($result) === 0) {
        throw new Exception("Product not found");
    }

    mysqli_stmt_close($stmt);

    // Check if product is used in any sales
    $sql = "SELECT COUNT(*) as count FROM sales_items WHERE product_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        throw new Exception("Database error: " . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);

    if ($row['count'] > 0) {
        throw new Exception("Cannot delete product as it is associated with sales records");
    }

    mysqli_stmt_close($stmt);

    // Delete product
    $sql = "DELETE FROM products WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        throw new Exception("Database error: " . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($stmt, "i", $id);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Error deleting product: " . mysqli_error($conn));
    }

    mysqli_stmt_close($stmt);
    mysqli_close($conn);

    // Return success response
    $_SESSION['success'] = "Product deleted successfully";
    header("Location: index.php");
    exit;

} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header("Location: index.php");
    exit;
}
?>
