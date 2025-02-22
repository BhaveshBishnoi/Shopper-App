<?php
session_start();
require_once "../config/db_connect.php";
require_once "../includes/functions.php";

header('Content-Type: application/json');

if (!isset($_GET['gst_rate'])) {
    http_response_code(400);
    echo json_encode(['error' => 'GST rate is required']);
    exit;
}

$gst_rate = floatval($_GET['gst_rate']);

try {
    $sql = "SELECT id, sku, name, price, gst_rate 
            FROM products 
            WHERE gst_rate = ?
            ORDER BY name";
            
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        throw new Exception(mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($stmt, "d", $gst_rate);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception(mysqli_stmt_error($stmt));
    }
    
    $result = mysqli_stmt_get_result($stmt);
    $products = mysqli_fetch_all($result, MYSQLI_ASSOC);
    
    echo json_encode($products);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
