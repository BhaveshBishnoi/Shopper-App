<?php
require_once "../config/db_connect.php";

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid inventory ID']);
    exit;
}

$id = intval($_POST['id']);

// First, get the product ID and quantity to adjust stock
$query = "SELECT product_id, quantity FROM inventory WHERE id = $id";
$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) === 0) {
    echo json_encode(['success' => false, 'message' => 'Inventory record not found']);
    exit;
}

$record = mysqli_fetch_assoc($result);
$product_id = $record['product_id'];
$quantity = $record['quantity'];

// Start transaction
mysqli_begin_transaction($conn);

try {
    // Delete the inventory record
    $delete_query = "DELETE FROM inventory WHERE id = $id";
    $delete_result = mysqli_query($conn, $delete_query);

    if (!$delete_result) {
        throw new Exception("Failed to delete inventory record");
    }

    // Update product stock
    $update_query = "UPDATE products SET stock = stock - $quantity WHERE id = $product_id";
    $update_result = mysqli_query($conn, $update_query);

    if (!$update_result) {
        throw new Exception("Failed to update product stock");
    }

    // Commit transaction
    mysqli_commit($conn);
    echo json_encode(['success' => true, 'message' => 'Inventory record deleted successfully']);
} catch (Exception $e) {
    // Rollback transaction on error
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}