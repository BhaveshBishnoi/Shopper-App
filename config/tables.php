<?php
require_once "db_connect.php";

// Create products table
$sql_products = "CREATE TABLE IF NOT EXISTS products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    stock_quantity INT NOT NULL,
    low_stock_threshold INT DEFAULT 10,
    sku VARCHAR(50) UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

// Create customers table
$sql_customers = "CREATE TABLE IF NOT EXISTS customers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE,
    phone VARCHAR(20),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

// Create sales table
$sql_sales = "CREATE TABLE IF NOT EXISTS sales (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT,
    customer_name VARCHAR(255),
    total_amount DECIMAL(10,2) NOT NULL,
    gst_rate DECIMAL(4,2) NOT NULL,
    gst_amount DECIMAL(10,2) NOT NULL,
    payment_status ENUM('paid', 'unpaid') DEFAULT 'unpaid',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
)";

// Create sale_items table for multiple products in a sale
$sql_sale_items = "CREATE TABLE IF NOT EXISTS sale_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sale_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    price_per_unit DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
)";

// Create settings table
$sql_settings = "CREATE TABLE IF NOT EXISTS settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_name VARCHAR(255),
    company_address TEXT,
    company_phone VARCHAR(20),
    company_email VARCHAR(255),
    currency_symbol VARCHAR(10) DEFAULT '$',
    date_format VARCHAR(20) DEFAULT 'Y-m-d',
    smtp_host VARCHAR(255),
    smtp_port INT,
    smtp_username VARCHAR(255),
    smtp_password VARCHAR(255),
    smtp_encryption ENUM('none', 'tls', 'ssl') DEFAULT 'tls',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
// Create Distributor table
$sql_distributors = "CREATE TABLE IF NOT EXISTS distributors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    contact_person VARCHAR(100),
    phone VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    total_goods_received DECIMAL(12,2) DEFAULT 0,
    total_amount_paid DECIMAL(12,2) DEFAULT 0,
    pending_amount DECIMAL(12,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

// Execute all table creation queries
$tables = [
    'products' => $sql_products,
    'customers' => $sql_customers,
    'sales' => $sql_sales,
    'sale_items' => $sql_sale_items,
    'settings' => $sql_settings,
    'distributors'=> $sql_distributors
];

$errors = [];
foreach ($tables as $table_name => $sql) {
    if (!mysqli_query($conn, $sql)) {
        $errors[] = "Error creating table $table_name: " . mysqli_error($conn);
    }
}

// Insert default settings if not exists
$check_settings = mysqli_query($conn, "SELECT id FROM settings LIMIT 1");
if (mysqli_num_rows($check_settings) == 0) {
    $default_settings = "INSERT INTO settings (company_name, currency_symbol, date_format) 
                        VALUES ('My Company', '$', 'Y-m-d')";
    mysqli_query($conn, $default_settings);
}

if (!empty($errors)) {
    foreach ($errors as $error) {
        error_log($error);
    }
}

mysqli_close($conn);
?>
