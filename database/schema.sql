-- Drop existing tables if they exist
DROP TABLE IF EXISTS sale_items;
DROP TABLE IF EXISTS sales;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS settings;

-- Create settings table
CREATE TABLE settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(50) UNIQUE NOT NULL,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Insert default settings
INSERT INTO settings (setting_key, setting_value) VALUES
('company_name', 'Your Company Name'),
('company_address', 'Your Company Address'),
('company_phone', 'Your Company Phone'),
('company_email', 'Your Company Email'),
('company_gstin', 'Your Company GSTIN'),
('currency_symbol', 'INR'),
('date_format', 'Y-m-d'),
('low_stock_threshold', '10');

-- Create customers table
CREATE TABLE customers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    is_walkin BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Create products table
CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    sku VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    category VARCHAR(50) DEFAULT 'General',
    price DECIMAL(10,2) NOT NULL,
    cost_price DECIMAL(10,2),
    stock_quantity INT NOT NULL DEFAULT 0,
    low_stock_threshold INT NOT NULL DEFAULT 10,
    reorder_level INT NOT NULL DEFAULT 10,
    gst_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Create sales table
CREATE TABLE sales (
    id INT PRIMARY KEY AUTO_INCREMENT,
    invoice_number VARCHAR(20) UNIQUE NOT NULL,
    customer_id INT,
    sale_date DATE NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    gst_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    payment_method VARCHAR(20) NOT NULL DEFAULT 'none',
    payment_status VARCHAR(20) NOT NULL DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Create sale_items table
CREATE TABLE sale_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sale_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    gst_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    gst_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_amount DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Insert sample data
INSERT INTO customers (name, email, phone, is_walkin) VALUES
('John Doe', 'john@example.com', '1234567890', FALSE),
('Jane Smith', 'jane@example.com', '9876543210', FALSE);

-- Insert sample products with cost_price
INSERT INTO products (name, sku, description, category, price, cost_price, stock_quantity, low_stock_threshold, gst_rate) VALUES
('Product 1', 'SKU001', 'Description 1', 'Electronics', 100.00, 80.00, 50, 15, 5.00),
('Product 2', 'SKU002', 'Description 2', 'Accessories', 200.00, 150.00, 30, 10, 5.00),
('Product 3', 'SKU003', 'Description 3', 'General', 150.00, 120.00, 40, 20, 5.00);

-- Insert sample sales
INSERT INTO sales (invoice_number, customer_id, sale_date, subtotal, gst_amount, total_amount, payment_method, payment_status) VALUES
('INV-2025-001', 1, CURDATE(), 100.00, 5.00, 105.00, 'cash', 'paid'),
('INV-2025-002', 2, CURDATE(), 200.00, 10.00, 210.00, 'card', 'paid');

-- Insert sample sale items
INSERT INTO sale_items (sale_id, product_id, quantity, price, gst_rate, gst_amount, total_amount) VALUES
(1, 1, 1, 100.00, 5.00, 5.00, 105.00),
(2, 2, 1, 200.00, 5.00, 10.00, 210.00);
