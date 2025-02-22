<?php
require_once "../config/db_connect.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    $_SESSION['error'] = "Invalid request method";
    header("Location: add.php");
    exit;
}

// Start transaction
mysqli_begin_transaction($conn);

try {
    // Handle customer data
    $customer_id = null;
    $customer_name = null;
    
    if (!empty($_POST['customer_id'])) {
        $customer_id = intval($_POST['customer_id']);
        // Get customer name for non-registered customers
        $customer_query = "SELECT name FROM customers WHERE id = ?";
        $stmt = mysqli_prepare($conn, $customer_query);
        mysqli_stmt_bind_param($stmt, "i", $customer_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $customer = mysqli_fetch_assoc($result);
        $customer_name = $customer['name'];
    } else {
        // Create new customer if details provided
        $customer_name = sanitize_input($_POST['customer_name']);
        if (!empty($_POST['customer_email']) || !empty($_POST['customer_phone'])) {
            $customer_email = sanitize_input($_POST['customer_email']);
            $customer_phone = sanitize_input($_POST['customer_phone']);
            
            $insert_customer = "INSERT INTO customers (name, email, phone) VALUES (?, ?, ?)";
            $stmt = mysqli_prepare($conn, $insert_customer);
            mysqli_stmt_bind_param($stmt, "sss", $customer_name, $customer_email, $customer_phone);
            mysqli_stmt_execute($stmt);
            $customer_id = mysqli_insert_id($conn);
        }
    }
    
    // Create sale record
    $total_amount = floatval($_POST['total_amount']);
    $gst_amount = floatval($_POST['gst_amount']);
    $gst_rate = floatval($_POST['gst_rate']);
    $payment_status = sanitize_input($_POST['payment_status']);
    
    $sale_query = "INSERT INTO sales (customer_id, customer_name, total_amount, gst_rate, gst_amount, payment_status) 
                   VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sale_query);
    mysqli_stmt_bind_param($stmt, "isddds", $customer_id, $customer_name, $total_amount, $gst_rate, $gst_amount, $payment_status);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Error creating sale: " . mysqli_error($conn));
    }
    
    $sale_id = mysqli_insert_id($conn);
    
    // Process sale items
    $products = $_POST['product_id'];
    $quantities = $_POST['quantity'];
    $prices = $_POST['price'];
    
    for ($i = 0; $i < count($products); $i++) {
        $product_id = intval($products[$i]);
        $quantity = intval($quantities[$i]);
        $price = floatval($prices[$i]);
        $total_price = $quantity * $price;
        
        // Add sale item
        $item_query = "INSERT INTO sale_items (sale_id, product_id, quantity, price_per_unit, total_price) 
                      VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $item_query);
        mysqli_stmt_bind_param($stmt, "iidd", $sale_id, $product_id, $quantity, $price, $total_price);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error adding sale item: " . mysqli_error($conn));
        }
        
        // Update product stock
        $stmt = mysqli_prepare($conn, "UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "ii", $quantity, $product_id);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error updating product stock: " . mysqli_error($conn));
        }
    }
    
    mysqli_commit($conn);
    $_SESSION['success'] = "Sale created successfully";
    header("Location: view.php?id=" . $sale_id);
    exit;
    
} catch (Exception $e) {
    mysqli_rollback($conn);
    $_SESSION['error'] = $e->getMessage();
    header("Location: add.php");
    exit;
}
?>
