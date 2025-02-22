-- Create database if not exists
CREATE DATABASE IF NOT EXISTS shopper;
USE shopper;

-- Drop existing tables if they exist
DROP TABLE IF EXISTS sale_items;
DROP TABLE IF EXISTS sales;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS settings;

-- Create tables
CREATE TABLE customers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    sku VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    stock_quantity INT NOT NULL DEFAULT 0,
    reorder_level INT NOT NULL DEFAULT 10,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE sales (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT,
    customer_name VARCHAR(100) NOT NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    gst_rate DECIMAL(5, 2) NOT NULL DEFAULT 0,
    gst_amount DECIMAL(10, 2) NOT NULL DEFAULT 0,
    payment_status ENUM('paid', 'unpaid') NOT NULL DEFAULT 'unpaid',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
);

CREATE TABLE sale_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sale_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    price_per_unit DECIMAL(10, 2) NOT NULL,
    total_price DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

CREATE TABLE settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_name VARCHAR(100) NOT NULL,
    company_address TEXT,
    company_phone VARCHAR(20),
    company_email VARCHAR(100),
    gst_number VARCHAR(50),
    currency CHAR(1) DEFAULT '$',
    date_format VARCHAR(20) DEFAULT 'd/m/Y',
    low_stock_threshold INT DEFAULT 10,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert sample data
-- Customers
INSERT INTO customers (name, email, phone, address) VALUES
('John Doe', 'john@example.com', '9876543210', '123 Main St, City'),
('Jane Smith', 'jane@example.com', '8765432109', '456 Oak Ave, Town'),
('Bob Wilson', 'bob@example.com', '7654321098', '789 Pine Rd, Village'),
('Alice Brown', 'alice@example.com', '6543210987', '321 Elm St, County'),
('Charlie Davis', 'charlie@example.com', '5432109876', '654 Maple Dr, State');

-- Products
INSERT INTO products (name, sku, description, price, stock_quantity, reorder_level) VALUES
('Laptop', 'LAP001', 'High-performance laptop', 45000.00, 15, 5),
('Smartphone', 'PHN001', 'Latest model smartphone', 25000.00, 25, 8),
('Tablet', 'TAB001', '10-inch tablet', 15000.00, 20, 6),
('Headphones', 'AUD001', 'Wireless headphones', 2500.00, 30, 10),
('Mouse', 'ACC001', 'Wireless mouse', 800.00, 50, 15),
('Keyboard', 'ACC002', 'Mechanical keyboard', 1500.00, 40, 12),
('Monitor', 'DSP001', '24-inch LED monitor', 12000.00, 10, 4),
('Printer', 'PRN001', 'Color laser printer', 18000.00, 8, 3),
('Speaker', 'AUD002', 'Bluetooth speaker', 3000.00, 25, 8),
('Power Bank', 'ACC003', '20000mAh power bank', 1800.00, 35, 10);

-- Settings
INSERT INTO settings (
    company_name, company_address, company_phone, company_email, 
    gst_number, currency, date_format, low_stock_threshold
) VALUES (
    'Tech Shop',
    '789 Business Avenue\nTech District\nCity, State 123456',
    '1234567890',
    'contact@techshop.com',
    'GST123456789',
    '$',
    'd/m/Y',
    10
);

-- Sales (Last 30 days)
INSERT INTO sales (customer_id, customer_name, total_amount, gst_rate, gst_amount, payment_status, created_at) VALUES
(1, 'John Doe', 47250.00, 5.00, 2250.00, 'paid', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(2, 'Jane Smith', 26250.00, 5.00, 1250.00, 'paid', DATE_SUB(NOW(), INTERVAL 5 DAY)),
(3, 'Bob Wilson', 15750.00, 5.00, 750.00, 'unpaid', DATE_SUB(NOW(), INTERVAL 8 DAY)),
(4, 'Alice Brown', 2625.00, 5.00, 125.00, 'paid', DATE_SUB(NOW(), INTERVAL 12 DAY)),
(5, 'Charlie Davis', 840.00, 5.00, 40.00, 'paid', DATE_SUB(NOW(), INTERVAL 15 DAY)),
(1, 'John Doe', 1575.00, 5.00, 75.00, 'paid', DATE_SUB(NOW(), INTERVAL 18 DAY)),
(2, 'Jane Smith', 12600.00, 5.00, 600.00, 'unpaid', DATE_SUB(NOW(), INTERVAL 22 DAY)),
(3, 'Bob Wilson', 18900.00, 5.00, 900.00, 'paid', DATE_SUB(NOW(), INTERVAL 25 DAY)),
(4, 'Alice Brown', 3150.00, 5.00, 150.00, 'paid', DATE_SUB(NOW(), INTERVAL 28 DAY)),
(5, 'Charlie Davis', 1890.00, 5.00, 90.00, 'paid', DATE_SUB(NOW(), INTERVAL 30 DAY));

-- Sale Items
INSERT INTO sale_items (sale_id, product_id, quantity, price_per_unit, total_price) VALUES
(1, 1, 1, 45000.00, 45000.00),
(2, 2, 1, 25000.00, 25000.00),
(3, 3, 1, 15000.00, 15000.00),
(4, 4, 1, 2500.00, 2500.00),
(5, 5, 1, 800.00, 800.00),
(6, 6, 1, 1500.00, 1500.00),
(7, 7, 1, 12000.00, 12000.00),
(8, 8, 1, 18000.00, 18000.00),
(9, 9, 1, 3000.00, 3000.00),
(10, 10, 1, 1800.00, 1800.00);

-- Update product stock quantities based on sales
UPDATE products p
JOIN (
    SELECT product_id, SUM(quantity) as total_sold
    FROM sale_items
    GROUP BY product_id
) s ON p.id = s.product_id
SET p.stock_quantity = p.stock_quantity - s.total_sold;
