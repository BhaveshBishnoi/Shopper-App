<?php
// Start session and include required files before any output
session_start();
require_once "config/db_connect.php";
require_once "includes/functions.php";

// Store referer at the start before any potential output
$return_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';

try {
    // Clean any output buffering
    while (ob_get_level()) {
        ob_end_clean();
    }

    // Check if export type is specified
    if (!isset($_GET['type'])) {
        throw new Exception("Export type not specified");
    }

    $type = $_GET['type'];
    $allowed_types = ['products', 'customers', 'sales'];

    if (!in_array($type, $allowed_types)) {
        throw new Exception("Invalid export type");
    }

    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $type . '_' . date('Y-m-d') . '.csv');

    // Create output handle
    $output = fopen('php://output', 'w');

    switch ($type) {
        case 'products':
            // Get products data
            $sql = "SELECT p.*, 
                   COALESCE(si.total_sold, 0) as total_sold,
                   COALESCE(si.last_sold_date, '') as last_sold_date
            FROM products p
            LEFT JOIN (
                SELECT product_id, 
                       SUM(quantity) as total_sold,
                       MAX(created_at) as last_sold_date
                FROM sales_items
                GROUP BY product_id
            ) si ON p.id = si.product_id
            ORDER BY p.name";

            $headers = ['SKU', 'Name', 'Category', 'Description', 'Price', 'Cost Price', 
                       'Stock Quantity', 'Low Stock Threshold', 'GST Rate', 'Status', 
                       'Total Sold', 'Last Sold Date'];
            break;

        case 'customers':
            // Get customers data
            $sql = "SELECT c.*, 
                   COUNT(s.id) as total_orders,
                   SUM(s.total_amount) as total_spent,
                   MAX(s.created_at) as last_order_date
            FROM customers c
            LEFT JOIN sales s ON c.id = s.customer_id
            GROUP BY c.id
            ORDER BY c.name";

            $headers = ['Name', 'Email', 'Phone', 'Address', 'GSTIN', 
                       'Total Orders', 'Total Spent', 'Last Order Date'];
            break;

        case 'sales':
            // Get sales data
            $sql = "SELECT s.id as sale_id, s.invoice_number, 
                   c.name as customer_name, c.email as customer_email,
                   s.total_amount, s.payment_method, s.payment_status,
                   s.created_at as sale_date,
                   GROUP_CONCAT(CONCAT(p.name, ' (', si.quantity, ' units)') SEPARATOR '; ') as items
            FROM sales s
            LEFT JOIN customers c ON s.customer_id = c.id
            LEFT JOIN sales_items si ON s.id = si.sale_id
            LEFT JOIN products p ON si.product_id = p.id
            GROUP BY s.id
            ORDER BY s.created_at DESC";

            $headers = ['Invoice Number', 'Customer Name', 'Customer Email', 
                       'Total Amount', 'Payment Method', 'Payment Status', 
                       'Sale Date', 'Items'];
            break;
    }

    // Write headers
    fputcsv($output, $headers);

    // Get and write data
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        throw new Exception("Error fetching data: " . mysqli_error($conn));
    }

    while ($row = mysqli_fetch_assoc($result)) {
        // Format data based on type
        switch ($type) {
            case 'products':
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
                    $row['status'],
                    $row['total_sold'],
                    $row['last_sold_date']
                ]);
                break;

            case 'customers':
                fputcsv($output, [
                    $row['name'],
                    $row['email'],
                    $row['phone'],
                    $row['address'],
                    $row['gstin'],
                    $row['total_orders'],
                    $row['total_spent'],
                    $row['last_order_date']
                ]);
                break;

            case 'sales':
                fputcsv($output, [
                    $row['invoice_number'],
                    $row['customer_name'],
                    $row['customer_email'],
                    $row['total_amount'],
                    $row['payment_method'],
                    $row['payment_status'],
                    $row['sale_date'],
                    $row['items']
                ]);
                break;
        }
    }

    // Close the output
    fclose($output);
    exit;

} catch (Exception $e) {
    $_SESSION['error'] = "Export failed: " . $e->getMessage();
    // Use stored referer URL instead of accessing it directly
    header("Location: " . $return_url);
    exit;
}
?>
