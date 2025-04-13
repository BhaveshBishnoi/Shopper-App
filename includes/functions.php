<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error message function
function handleError($message, $redirect_url = null) {
    $_SESSION['error'] = $message;
    if ($redirect_url) {
        header("Location: $redirect_url");
        exit;
    }
}

// Success message function
function handleSuccess($message, $redirect_url = null) {
    $_SESSION['success'] = $message;
    if ($redirect_url) {
        header("Location: $redirect_url");
        exit;
    }
}

// Function to check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Function to require login
function require_login() {
    if (!is_logged_in()) {
        handleError("Please login to continue", "/shopper/auth/login.php");
    }
}

// Function to check user role
function has_permission($required_role) {
    if (!isset($_SESSION['user_role'])) {
        return false;
    }
    
    $role_hierarchy = [
        'admin' => 3,
        'manager' => 2,
        'staff' => 1
    ];
    
    $user_level = $role_hierarchy[$_SESSION['user_role']] ?? 0;
    $required_level = $role_hierarchy[$required_role] ?? 0;
    
    return $user_level >= $required_level;
}

// Function to require specific role
function require_permission($required_role) {
    if (!has_permission($required_role)) {
        handleError("You don't have permission to access this page", "/shopper/dashboard.php");
    }
}

// Function to sanitize input
function sanitize_input($data) {
    global $conn;
    $data = trim($data);
    $data = mysqli_real_escape_string($conn, $data);
    $data = htmlspecialchars($data);
    return $data;
}

// Function to format currency
function format_currency($amount) {
    return '₹' . number_format($amount, 2);
}

// Function to format date
function format_date($date) {
    return date('d M Y', strtotime($date));
}

// Function to check if product exists
function product_exists($sku) {
    global $conn;
    $sku = sanitize_input($sku);
    $query = "SELECT id FROM products WHERE sku = '$sku'";
    $result = mysqli_query($conn, $query);
    return mysqli_num_rows($result) > 0;
}

// Function to check if customer exists
function customer_exists($email) {
    global $conn;
    $email = sanitize_input($email);
    $query = "SELECT id FROM customers WHERE email = '$email'";
    $result = mysqli_query($conn, $query);
    return mysqli_num_rows($result) > 0;
}

// Function to check if a value exists in database
function value_exists($table, $column, $value) {
    global $conn;
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM $table WHERE $column = ?");
    mysqli_stmt_bind_param($stmt, "s", $value);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $count = mysqli_fetch_array($result)[0];
    return $count > 0;
}

// Function to get low stock products
function get_low_stock_products() {
    global $conn;
    $query = "SELECT * FROM products WHERE stock_quantity <= low_stock_threshold ORDER BY stock_quantity ASC";
    $result = mysqli_query($conn, $query);
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

// Function to get recent sales
function get_recent_sales($limit = 5) {
    global $conn;
    $query = "SELECT s.*, c.name as customer_name 
              FROM sales s 
              LEFT JOIN customers c ON s.customer_id = c.id 
              ORDER BY s.created_at DESC 
              LIMIT $limit";
    $result = mysqli_query($conn, $query);
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

// Function to get total revenue
function get_total_revenue() {
    global $conn;
    $query = "SELECT SUM(total_amount) as total FROM sales WHERE payment_status = 'paid'";
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    return $row['total'] ?? 0;
}

// Function to get total products
function get_total_products() {
    global $conn;
    $query = "SELECT COUNT(*) as total FROM products";
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    return $row['total'] ?? 0;
}

// Function to get total sales
function get_total_sales() {
    global $conn;
    $query = "SELECT COUNT(*) as total FROM sales";
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    return $row['total'] ?? 0;
}

// Function to get top selling products
function get_top_selling_products($limit = 5) {
    global $conn;
    $query = "SELECT p.*, SUM(si.quantity) as total_sold 
              FROM products p 
              JOIN sale_items si ON p.id = si.product_id 
              GROUP BY p.id 
              ORDER BY total_sold DESC 
              LIMIT $limit";
    $result = mysqli_query($conn, $query);
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

// Function to generate sales report
function generate_sales_report($start_date, $end_date) {
    global $conn;
    $start_date = sanitize_input($start_date);
    $end_date = sanitize_input($end_date);
    
    $query = "SELECT s.*, c.name as customer_name 
              FROM sales s 
              LEFT JOIN customers c ON s.customer_id = c.id 
              WHERE DATE(s.created_at) BETWEEN '$start_date' AND '$end_date' 
              ORDER BY s.created_at DESC";
    
    $result = mysqli_query($conn, $query);
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

// Function to import CSV data
function import_csv($file, $table) {
    global $conn;
    $csv = array_map('str_getcsv', file($file['tmp_name']));
    $headers = array_shift($csv);
    
    $success = 0;
    $failed = 0;
    
    foreach ($csv as $row) {
        $data = array_combine($headers, $row);
        $columns = implode(',', array_keys($data));
        $values = "'" . implode("','", array_map('sanitize_input', array_values($data))) . "'";
        
        $query = "INSERT INTO $table ($columns) VALUES ($values)";
        if (mysqli_query($conn, $query)) {
            $success++;
        } else {
            $failed++;
        }
    }
    
    return ['success' => $success, 'failed' => $failed];
}

// Function to export table to CSV
function export_csv($table) {
    global $conn;
    
    // Clean any output buffering
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $table . '_' . date('Y-m-d') . '.csv');
    
    // Create output handle
    $output = fopen('php://output', 'w');
    
    // Get table columns
    $columns_query = "SHOW COLUMNS FROM " . mysqli_real_escape_string($conn, $table);
    $columns_result = mysqli_query($conn, $columns_query);
    
    if (!$columns_result) {
        die("Error getting table structure: " . mysqli_error($conn));
    }
    
    $headers = [];
    while ($column = mysqli_fetch_assoc($columns_result)) {
        $headers[] = $column['Field'];
    }
    
    // Write headers to CSV
    fputcsv($output, $headers);
    
    // Get data
    $query = "SELECT * FROM " . mysqli_real_escape_string($conn, $table);
    $result = mysqli_query($conn, $query);
    
    if (!$result) {
        die("Error exporting data: " . mysqli_error($conn));
    }
    
    // Write data rows
    while ($row = mysqli_fetch_assoc($result)) {
        fputcsv($output, $row);
    }
    
    // Close the file
    fclose($output);
    exit;
}

// Function to generate random string
function generate_random_string($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $string = '';
    for ($i = 0; $i < $length; $i++) {
        $string .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $string;
}

function notify_product_created($product_name, $sku) {
    // Implement your notification logic here
    // For example, you could send an email, log the event, or display a message
    error_log("Product created: $product_name (SKU: $sku)");
}

function notify_product_low_stock($product_name, $stock_quantity, $low_stock_threshold) {
    // Implement your notification logic here
    // For example, you could send an email, log the event, or display a message
    error_log("Low stock alert for product: $product_name. Current stock: $stock_quantity, Threshold: $low_stock_threshold");
}

?>
