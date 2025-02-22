<?php
session_start();
require_once "../includes/functions.php";
require_once "../config/db_connect.php";
require_once "../includes/product_notifications.php";

try {
    // Clean any output buffering
    while (ob_get_level()) {
        ob_end_clean();
    }

    // Get filter parameters
    $category = isset($_GET['category']) ? sanitize_input($_GET['category']) : '';

    // Build query
    $sql = "SELECT p.*, 
           COALESCE(si.total_sold, 0) as total_sold,
           COALESCE(si.last_sold_date, '') as last_sold_date
           FROM products p
           LEFT JOIN (
               SELECT product_id, 
                      SUM(quantity) as total_sold,
                      MAX(created_at) as last_sold_date
               FROM sale_items
               GROUP BY product_id
           ) si ON p.id = si.product_id";

    // Add category filter if specified
    if (!empty($category)) {
        $sql .= " WHERE p.category = ?";
    }
    
    $sql .= " ORDER BY p.name";

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        throw new Exception(mysqli_error($conn));
    }

    if (!empty($category)) {
        mysqli_stmt_bind_param($stmt, "s", $category);
    }

    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception(mysqli_error($conn));
    }

    $result = mysqli_stmt_get_result($stmt);
    $product_count = mysqli_num_rows($result);

    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=products_' . date('Y-m-d') . '.csv');

    // Create output handle
    $output = fopen('php://output', 'w');

    // Add headers
    fputcsv($output, [
        'SKU', 'Name', 'Category', 'Description', 'Price', 
        'Cost Price', 'Stock Quantity', 'Low Stock Threshold', 
        'GST Rate', 'Total Sold', 'Last Sold Date'
    ]);

    // Add data
    while ($row = mysqli_fetch_assoc($result)) {
        fputcsv($output, [
            $row['sku'],
            $row['name'],
            $row['category'],
            $row['description'],
            $row['price'],
            $row['cost_price'],
            $row['stock_quantity'],
            $row['low_stock_threshold'],
            $row['gst_rate'],
            $row['total_sold'],
            $row['last_sold_date']
        ]);
    }

    notify_product_export_success($product_count, $category);
    fclose($output);
    exit;

} catch (Exception $e) {
    notify_product_export_error($e->getMessage());
    header("Location: index.php");
    exit;
}
