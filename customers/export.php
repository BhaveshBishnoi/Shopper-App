<?php
// Start session and check login
session_start();
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: /shopper/index.php");
    exit;
}

require_once "../includes/functions.php";
require_once "../config/db_connect.php";
require_once "../includes/customer_notifications.php";

try {
    // Clean any output buffering
    while (ob_get_level()) {
        ob_end_clean();
    }

    // Get all customers
    $sql = "SELECT c.*, 
           COUNT(s.id) as total_orders,
           COALESCE(SUM(s.total_amount), 0) as total_spent,
           MAX(s.created_at) as last_order_date
           FROM customers c
           LEFT JOIN sales s ON c.id = s.customer_id
           GROUP BY c.id
           ORDER BY c.name";

    $result = mysqli_query($conn, $sql);
    if (!$result) {
        throw new Exception(mysqli_error($conn));
    }

    $customer_count = mysqli_num_rows($result);

    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=customers_' . date('Y-m-d') . '.csv');

    // Create output handle
    $output = fopen('php://output', 'w');

    // Add headers
    fputcsv($output, [
        'Name', 'Email', 'Phone', 'Address', 'GSTIN',
        'Total Orders', 'Total Spent', 'Last Order Date'
    ]);

    // Add data
    while ($row = mysqli_fetch_assoc($result)) {
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
    }

    notify_customer_export_success($customer_count);
    fclose($output);
    exit;

} catch (Exception $e) {
    notify_customer_export_error($e->getMessage());
    header("Location: index.php");
    exit;
}
?>
