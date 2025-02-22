<?php
session_start();
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: /shopper/index.php");
    exit;
}

require_once "../config/db_connect.php";
require_once "../includes/functions.php";

try {
    // Get sales data with customer and item details
    $sql = "SELECT 
        s.invoice_number,
        c.name as customer_name,
        c.email as customer_email,
        c.phone as customer_phone,
        s.created_at as sale_date,
        s.total_amount as sale_total,
        s.payment_method,
        s.payment_status,
        p.name as product_name,
        p.sku as product_sku,
        si.quantity,
        si.price as unit_price,
        si.gst_rate,
        si.gst_amount,
        (si.quantity * si.price) as subtotal
    FROM sales s
    LEFT JOIN customers c ON s.customer_id = c.id
    LEFT JOIN sale_items si ON s.id = si.sale_id
    LEFT JOIN products p ON si.product_id = p.id
    ORDER BY s.created_at DESC, s.invoice_number";

    $result = mysqli_query($conn, $sql);
    if (!$result) {
        throw new Exception("Error fetching data: " . mysqli_error($conn));
    }

    // Send headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="sales_export_' . date('Y-m-d_H-i-s') . '.csv"');

    // Create output stream
    $output = fopen('php://output', 'w');
    if ($output === false) {
        throw new Exception("Failed to create output stream");
    }

    // Add UTF-8 BOM for Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Write headers
    $headers = [
        'Invoice Number',
        'Sale Date',
        'Customer Name',
        'Customer Email',
        'Customer Phone',
        'Product Name',
        'Product SKU',
        'Quantity',
        'Unit Price',
        'Subtotal',
        'GST Rate (%)',
        'GST Amount',
        'Sale Total',
        'Payment Method',
        'Payment Status'
    ];
    fputcsv($output, $headers);

    // Write data rows
    while ($row = mysqli_fetch_assoc($result)) {
        $data = [
            $row['invoice_number'],
            date('Y-m-d H:i:s', strtotime($row['sale_date'])),
            $row['customer_name'],
            $row['customer_email'],
            $row['customer_phone'],
            $row['product_name'],
            $row['product_sku'],
            $row['quantity'],
            number_format($row['unit_price'], 2),
            number_format($row['subtotal'], 2),
            $row['gst_rate'],
            number_format($row['gst_amount'], 2),
            number_format($row['sale_total'], 2),
            $row['payment_method'],
            $row['payment_status']
        ];
        fputcsv($output, $data);
    }

    // Close the output stream
    fclose($output);
    exit;

} catch (Exception $e) {
    $_SESSION['error'] = "Export failed: " . $e->getMessage();
    header("Location: import.php");
    exit;
}
