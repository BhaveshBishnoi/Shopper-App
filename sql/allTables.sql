-- Products table with soft delete and improved indexing
CREATE TABLE IF NOT EXISTS products (
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
    is_deleted BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_sku (sku),
    INDEX idx_category (category),
    INDEX idx_active_product (is_deleted, stock_quantity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Customers table with additional contact fields
CREATE TABLE IF NOT EXISTS customers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE,
    phone VARCHAR(20),
    address TEXT,
    gst_number VARCHAR(50),
    shipping_address TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_phone (phone),
    INDEX idx_active_customer (is_active, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- user table
CREATE TABLE IF NOT EXISTS users (
    id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100),  // Add this line if you need email
    password VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sales table with enhanced payment tracking
CREATE TABLE IF NOT EXISTS sales (
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
    due_date DATE,
    is_returned BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    INDEX idx_customer (customer_id),
    INDEX idx_invoice (invoice_number),
    INDEX idx_date (created_at),
    INDEX idx_payment_status (payment_status, due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sale items with cost tracking
CREATE TABLE IF NOT EXISTS sale_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sale_id INT NOT NULL,
    product_id INT NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    quantity INT NOT NULL,
    price_per_unit DECIMAL(10,2) NOT NULL,
    cost_per_unit DECIMAL(10,2),
    discount_per_unit DECIMAL(10,2) DEFAULT 0,
    tax_amount DECIMAL(10,2) DEFAULT 0,
    total_price DECIMAL(10,2) NOT NULL,
    return_quantity INT DEFAULT 0,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    INDEX idx_sale (sale_id),
    INDEX idx_product (product_id),
    INDEX idx_product_sales (product_id, sale_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Distributors with enhanced financial tracking
CREATE TABLE IF NOT EXISTS distributors (
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
    credit_limit DECIMAL(12,2) DEFAULT 0,
    payment_terms INT DEFAULT 30, -- days
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_phone (phone),
    INDEX idx_email (email),
    INDEX idx_active_distributor (is_active, pending_amount)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Distributor products with batch tracking
CREATE TABLE IF NOT EXISTS distributor_products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    distributor_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 0,
    purchase_price DECIMAL(10,2) NOT NULL,
    batch_number VARCHAR(50),
    expiry_date DATE,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (distributor_id) REFERENCES distributors(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY (distributor_id, product_id, batch_number),
    INDEX idx_distributor (distributor_id),
    INDEX idx_product (product_id),
    INDEX idx_batch (batch_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Enhanced distributor transactions
CREATE TABLE IF NOT EXISTS distributor_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    distributor_id INT NOT NULL,
    product_id INT NULL,
    transaction_date DATE NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    quantity INT,
    transaction_type ENUM('payment', 'purchase', 'credit_note', 'debit_note') NOT NULL,
    payment_method ENUM('cash', 'bank_transfer', 'cheque', 'upi', 'other') NOT NULL,
    reference_number VARCHAR(100),
    category VARCHAR(50),
    description TEXT,
    is_reconciled BOOLEAN DEFAULT FALSE,
    reconciled_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (distributor_id) REFERENCES distributors(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    INDEX idx_distributor (distributor_id),
    INDEX idx_product (product_id),
    INDEX idx_date (transaction_date),
    INDEX idx_type (transaction_type),
    INDEX idx_reconciliation (is_reconciled, transaction_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Settings with additional business features
CREATE TABLE IF NOT EXISTS settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_name VARCHAR(255) NOT NULL,
    company_address TEXT,
    company_phone VARCHAR(20),
    company_email VARCHAR(255),
    company_logo VARCHAR(255),
    currency_symbol VARCHAR(10) DEFAULT '₹',
    currency_code VARCHAR(3) DEFAULT 'INR',
    date_format VARCHAR(20) DEFAULT 'd-m-Y',
    timezone VARCHAR(50) DEFAULT 'Asia/Kolkata',
    invoice_prefix VARCHAR(10) DEFAULT 'INV',
    invoice_start_number INT DEFAULT 1000,
    tax_enabled BOOLEAN DEFAULT TRUE,
    tax_rate DECIMAL(4,2) DEFAULT 18.00,
    gst_enabled BOOLEAN DEFAULT TRUE,
    gst_number VARCHAR(50),
    invoice_terms TEXT,
    low_stock_notification BOOLEAN DEFAULT TRUE,
    smtp_host VARCHAR(255),
    smtp_port INT,
    smtp_username VARCHAR(255),
    smtp_password VARCHAR(255),
    smtp_encryption ENUM('none', 'tls', 'ssl') DEFAULT 'tls',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Users with enhanced security
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    full_name VARCHAR(100),
    role ENUM('admin', 'manager', 'staff', 'accountant') DEFAULT 'staff',
    last_login DATETIME,
    last_password_change DATETIME,
    login_attempts INT DEFAULT 0,
    status ENUM('active', 'inactive', 'locked') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Audit log with more detailed tracking
CREATE TABLE IF NOT EXISTS audit_log (
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
    INDEX idx_date (created_at),
    INDEX idx_table_record (table_name, record_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Inventory with multi-location support
CREATE TABLE IF NOT EXISTS inventory (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    distributor_id INT,
    location_id INT,
    quantity INT NOT NULL,
    purchase_price DECIMAL(10,2) NOT NULL,
    batch_number VARCHAR(50),
    expiry_date DATE,
    transaction_date DATE NOT NULL,
    payment_status ENUM('paid', 'pending', 'partial') DEFAULT 'pending',
    reference_number VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (distributor_id) REFERENCES distributors(id) ON DELETE SET NULL,
    INDEX idx_product_distributor (product_id, distributor_id),
    INDEX idx_batch (batch_number),
    INDEX idx_expiry (expiry_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;