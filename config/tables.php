<?php
require_once "db_connect.php";

// Create products table
$sql_products = "CREATE TABLE IF NOT EXISTS products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    cost_price DECIMAL(10,2),
    stock_quantity INT NOT NULL DEFAULT 0,
    low_stock_threshold INT DEFAULT 10,
    sku VARCHAR(50) UNIQUE,
    category VARCHAR(100),
    gst_rate DECIMAL(4,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_sku (sku),
    INDEX idx_category (category)
)";

// Create customers table
$sql_customers = "CREATE TABLE IF NOT EXISTS customers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE,
    phone VARCHAR(20),
    address TEXT,
    gst_number VARCHAR(50),
    shipping_address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_phone (phone)
)";

// Create sales table
$sql_sales = "CREATE TABLE IF NOT EXISTS sales (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT,
    customer_name VARCHAR(255),
    invoice_number VARCHAR(50) UNIQUE,
    total_amount DECIMAL(10,2) NOT NULL,
    discount_amount DECIMAL(10,2) DEFAULT 0,
    tax_amount DECIMAL(10,2) DEFAULT 0,
    gst_rate DECIMAL(4,2) NOT NULL,
    gst_amount DECIMAL(10,2) NOT NULL,
    payment_status ENUM('paid', 'unpaid', 'partial') DEFAULT 'unpaid',
    payment_method ENUM('cash', 'card', 'bank_transfer', 'upi', 'other'),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    INDEX idx_customer (customer_id),
    INDEX idx_invoice (invoice_number),
    INDEX idx_date (created_at)
)";

// Create sale_items table for multiple products in a sale
$sql_sale_items = "CREATE TABLE IF NOT EXISTS sale_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sale_id INT NOT NULL,
    product_id INT NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    quantity INT NOT NULL,
    price_per_unit DECIMAL(10,2) NOT NULL,
    discount_per_unit DECIMAL(10,2) DEFAULT 0,
    tax_amount DECIMAL(10,2) DEFAULT 0,
    total_price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    INDEX idx_sale (sale_id),
    INDEX idx_product (product_id)
)";

// Create distributors table
$sql_distributors = "CREATE TABLE IF NOT EXISTS distributors (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    contact_person VARCHAR(100),
    phone VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    gst_number VARCHAR(50),
    total_goods_received DECIMAL(12,2) DEFAULT 0,
    total_amount_paid DECIMAL(12,2) DEFAULT 0,
    pending_amount DECIMAL(12,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_phone (phone),
    INDEX idx_email (email)
)";

// Create distributor_products table
$sql_distributor_products = "CREATE TABLE IF NOT EXISTS distributor_products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    distributor_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 0,
    purchase_price DECIMAL(10,2) NOT NULL,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (distributor_id) REFERENCES distributors(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY (distributor_id, product_id),
    INDEX idx_distributor (distributor_id),
    INDEX idx_product (product_id)
)";

// Create distributor_transactions table (updated with product_id)
$sql_distributor_transactions = "CREATE TABLE IF NOT EXISTS distributor_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    distributor_id INT NOT NULL,
    product_id INT NULL,
    transaction_date DATE NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    quantity INT,
    transaction_type ENUM('payment', 'purchase') NOT NULL,
    payment_method ENUM('cash', 'bank_transfer', 'cheque', 'upi', 'other') NOT NULL,
    reference_number VARCHAR(100),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (distributor_id) REFERENCES distributors(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    INDEX idx_distributor (distributor_id),
    INDEX idx_product (product_id),
    INDEX idx_date (transaction_date),
    INDEX idx_type (transaction_type)
)";

// Create settings table
$sql_settings = "CREATE TABLE IF NOT EXISTS settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_name VARCHAR(255) NOT NULL,
    company_address TEXT,
    company_phone VARCHAR(20),
    company_email VARCHAR(255),
    company_logo VARCHAR(255),
    currency_symbol VARCHAR(10) DEFAULT '$',
    currency_code VARCHAR(3) DEFAULT 'USD',
    date_format VARCHAR(20) DEFAULT 'Y-m-d',
    timezone VARCHAR(50) DEFAULT 'UTC',
    invoice_prefix VARCHAR(10) DEFAULT 'INV',
    invoice_start_number INT DEFAULT 1000,
    tax_enabled BOOLEAN DEFAULT FALSE,
    tax_rate DECIMAL(4,2) DEFAULT 0,
    smtp_host VARCHAR(255),
    smtp_port INT,
    smtp_username VARCHAR(255),
    smtp_password VARCHAR(255),
    smtp_encryption ENUM('none', 'tls', 'ssl') DEFAULT 'tls',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

// Create users table
$sql_users = "CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    full_name VARCHAR(100),
    role ENUM('admin', 'manager', 'staff') DEFAULT 'staff',
    last_login DATETIME,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_email (email)
)";

// Create audit_log table
$sql_audit_log = "CREATE TABLE IF NOT EXISTS audit_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(50) NOT NULL,
    table_name VARCHAR(50),
    record_id INT,
    old_value TEXT,
    new_value TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_date (created_at)
)";

// Create inventory table
$sql_inventory = "CREATE TABLE IF NOT EXISTS inventory (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    distributor_id INT,
    quantity INT NOT NULL,
    purchase_price DECIMAL(10,2) NOT NULL,
    transaction_date DATE NOT NULL,
    payment_status ENUM('paid', 'pending') DEFAULT 'pending',
    reference_number VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (distributor_id) REFERENCES distributors(id) ON DELETE SET NULL
)";

// Execute all table creation queries
$tables = [
    'products' => $sql_products,
    'customers' => $sql_customers,
    'sales' => $sql_sales,
    'sale_items' => $sql_sale_items,
    'distributors' => $sql_distributors,
    'distributor_products' => $sql_distributor_products,
    'distributor_transactions' => $sql_distributor_transactions,
    'settings' => $sql_settings,
    'users' => $sql_users,
    'audit_log' => $sql_audit_log,
    'inventory' => $sql_inventory
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
                        VALUES ('My Company', '₹', 'Y-m-d')";
    if (!mysqli_query($conn, $default_settings)) {
        $errors[] = "Error inserting default settings: " . mysqli_error($conn);
    }
}

// Create default admin user if not exists
$check_admin = mysqli_query($conn, "SELECT id FROM users WHERE username = 'admin' LIMIT 1");
if (mysqli_num_rows($check_admin) == 0) {
    $hashed_password = password_hash('admin123', PASSWORD_DEFAULT);
    $default_admin = "INSERT INTO users (username, password, email, full_name, role) 
                      VALUES ('admin', '$hashed_password', 'admin@example.com', 'Administrator', 'admin')";
    if (!mysqli_query($conn, $default_admin)) {
        $errors[] = "Error creating default admin user: " . mysqli_error($conn);
    }
}

if (!empty($errors)) {
    foreach ($errors as $error) {
        error_log($error);
    }
    echo "<div class='alert alert-danger'>Some errors occurred during setup. Check error logs for details.</div>";
} else {
    echo "<div class='alert alert-success'>Database tables created successfully!</div>";
}

mysqli_close($conn);
?>