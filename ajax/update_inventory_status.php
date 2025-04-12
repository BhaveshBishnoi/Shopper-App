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

if (!isset($_POST['status']) || !in_array($_POST['status'], ['paid', 'pending'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

$id = intval($_POST['id']);
$status = $_POST['status'];

// Verify the record exists
$check_query = "SELECT id FROM inventory WHERE id = $id";
$check_result = mysqli_query($conn, $check_query);

if (mysqli_num_rows($check_result) === 0) {
    echo json_encode(['success' => false, 'message' => 'Inventory record not found']);
    exit;
}

// Update the status
$update_query = "UPDATE inventory SET payment_status = '$status', updated_at = NOW() WHERE id = $id";
$result = mysqli_query($conn, $update_query);

if ($result) {
    echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update status']);
}