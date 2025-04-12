<?php
// Enable full error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session securely
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 86400,
        'cookie_secure'   => isset($_SERVER['HTTPS']),
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict'
    ]);
}

// Verify required files
$required_files = [
    '../config/db_connect.php',
    '../includes/functions.php',
    '../includes/notifications.php',
    '../includes/product_notifications.php',
    '../includes/header.php',
    '../includes/footer.php'
];

foreach ($required_files as $file) {
    if (!file_exists($file)) {
        die("Missing required file: $file");
    }
    if (!is_readable($file)) {
        die("Cannot read required file: $file");
    }
}

// Include required files
require_once "../config/db_connect.php";

// Test database connection
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

require_once "../includes/functions.php";
require_once "../includes/notifications.php";
require_once "../includes/product_notifications.php";

// Initialize variables
$errors = [];
$success = false;
$product_data = [
    'name' => '',
    'sku' => '',
    'description' => '',
    'category' => '',
    'price' => '',
    'cost_price' => '',
    'stock_quantity' => 0,
    'low_stock_threshold' => 10,
    'gst_rate' => 0
];

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Sanitize and validate input
        $product_data = [
            'name' => sanitize_input($_POST['name'] ?? ''),
            'sku' => sanitize_input($_POST['sku'] ?? ''),
            'description' => sanitize_input($_POST['description'] ?? ''),
            'category' => sanitize_input($_POST['category'] ?? ''),
            'price' => floatval($_POST['price'] ?? 0),
            'cost_price' => !empty($_POST['cost_price']) ? floatval($_POST['cost_price']) : null,
            'stock_quantity' => intval($_POST['stock_quantity'] ?? 0),
            'low_stock_threshold' => !empty($_POST['low_stock_threshold']) ? intval($_POST['low_stock_threshold']) : 10,
            'gst_rate' => floatval($_POST['gst_rate'] ?? 0)
        ];

        // Validate required fields
        if (empty($product_data['name'])) $errors[] = "Product name is required";
        if (empty($product_data['sku'])) $errors[] = "SKU is required";
        if ($product_data['price'] <= 0) $errors[] = "Price must be greater than 0";
        if ($product_data['stock_quantity'] < 0) $errors[] = "Stock quantity cannot be negative";
        if ($product_data['low_stock_threshold'] < 0) $errors[] = "Low stock threshold cannot be negative";
        if ($product_data['gst_rate'] < 0) $errors[] = "GST rate cannot be negative";

        // Check if SKU already exists
        if (empty($errors)) {
            $stmt = mysqli_prepare($conn, "SELECT id FROM products WHERE sku = ?");
            mysqli_stmt_bind_param($stmt, "s", $product_data['sku']);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            if (mysqli_stmt_num_rows($stmt) > 0) {
                $errors[] = "SKU already exists";
            }
            mysqli_stmt_close($stmt);
        }

        // Process if no errors
        if (empty($errors)) {
            mysqli_begin_transaction($conn);

            // Insert product
            $query = "INSERT INTO products (name, sku, description, category, price, cost_price, 
                     stock_quantity, low_stock_threshold, gst_rate) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "ssssddiid", 
                $product_data['name'], 
                $product_data['sku'], 
                $product_data['description'], 
                $product_data['category'],
                $product_data['price'], 
                $product_data['cost_price'], 
                $product_data['stock_quantity'],
                $product_data['low_stock_threshold'], 
                $product_data['gst_rate']
            );

            if (mysqli_stmt_execute($stmt)) {
                $product_id = mysqli_insert_id($conn);
                
                // Handle distributor assignment if provided
                if (!empty($_POST['distributor_id'])) {
                    $distributor_id = intval($_POST['distributor_id']);
                    $distributor_quantity = intval($_POST['distributor_quantity'] ?? $product_data['stock_quantity']);
                    $purchase_price = floatval($_POST['purchase_price'] ?? $product_data['cost_price'] ?? $product_data['price']);
                    
                    // Insert into distributor_products
                    $distributor_query = "INSERT INTO distributor_products 
                                        (distributor_id, product_id, quantity, purchase_price)
                                        VALUES (?, ?, ?, ?)";
                    $stmt = mysqli_prepare($conn, $distributor_query);
                    mysqli_stmt_bind_param($stmt, "iiid", $distributor_id, $product_id, $distributor_quantity, $purchase_price);
                    mysqli_stmt_execute($stmt);
                    
                    // Update distributor's financials
                    $update_distributor = "UPDATE distributors 
                                          SET total_goods_received = total_goods_received + ?,
                                              pending_amount = (total_goods_received + ?) - total_amount_paid
                                          WHERE id = ?";
                    $stmt = mysqli_prepare($conn, $update_distributor);
                    $total_amount = $distributor_quantity * $purchase_price;
                    mysqli_stmt_bind_param($stmt, "ddi", $total_amount, $total_amount, $distributor_id);
                    mysqli_stmt_execute($stmt);
                }
                
                mysqli_commit($conn);
                $success = true;
                
                // Notifications
                notify_product_created($product_data['name'], $product_data['sku']);
                if ($product_data['stock_quantity'] <= $product_data['low_stock_threshold']) {
                    notify_product_low_stock($product_data['name'], $product_data['stock_quantity'], $product_data['low_stock_threshold']);
                }
                
                header("Location: index.php?success=1");
                exit;
            } else {
                throw new Exception("Database error: " . mysqli_error($conn));
            }
        }
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $errors[] = "Error: " . $e->getMessage();
        error_log("Product creation error: " . $e->getMessage());
    }
}

// Get distributors for dropdown
$distributors = [];
$distributor_result = mysqli_query($conn, "SELECT id, name FROM distributors ORDER BY name");
if ($distributor_result) {
    while ($row = mysqli_fetch_assoc($distributor_result)) {
        $distributors[] = $row;
    }
}

require_once "../includes/header.php";
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header pb-0">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Add New Product</h5>
                        <a href="index.php" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left me-2"></i>Back to Products
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <h5>Please fix the following errors:</h5>
                            <ul>
                                <?php foreach ($errors as $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" id="addProductForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Product Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name" required 
                                           value="<?= htmlspecialchars($product_data['name']) ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="sku" class="form-label">SKU <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="sku" name="sku" required
                                           value="<?= htmlspecialchars($product_data['sku']) ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="category" class="form-label">Category</label>
                                    <input type="text" class="form-control" id="category" name="category"
                                           value="<?= htmlspecialchars($product_data['category']) ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($product_data['description']) ?></textarea>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="price" class="form-label">Price <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" class="form-control" id="price" name="price" required
                                           value="<?= htmlspecialchars($product_data['price']) ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="cost_price" class="form-label">Cost Price</label>
                                    <input type="number" step="0.01" class="form-control" id="cost_price" name="cost_price"
                                           value="<?= htmlspecialchars($product_data['cost_price'] ?? '') ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="stock_quantity" class="form-label">Stock Quantity <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="stock_quantity" name="stock_quantity" required
                                           value="<?= htmlspecialchars($product_data['stock_quantity']) ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="low_stock_threshold" class="form-label">Low Stock Threshold</label>
                                    <input type="number" class="form-control" id="low_stock_threshold" name="low_stock_threshold"
                                           value="<?= htmlspecialchars($product_data['low_stock_threshold']) ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="gst_rate" class="form-label">GST Rate (%)</label>
                                    <input type="number" step="0.01" class="form-control" id="gst_rate" name="gst_rate"
                                           value="<?= htmlspecialchars($product_data['gst_rate']) ?>">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Distributor Assignment Section -->
                        <div class="card mt-4">
                            <div class="card-header">
                                <h6>Distributor Information (Optional)</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="distributor_id" class="form-label">Distributor</label>
                                            <select class="form-select" id="distributor_id" name="distributor_id">
                                                <option value="">Select Distributor</option>
                                                <?php foreach ($distributors as $distributor): ?>
                                                    <option value="<?= $distributor['id'] ?>"
                                                        <?= isset($_POST['distributor_id']) && $_POST['distributor_id'] == $distributor['id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($distributor['name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="distributor_quantity" class="form-label">Quantity</label>
                                            <input type="number" class="form-control" id="distributor_quantity" name="distributor_quantity"
                                                   min="1" value="<?= htmlspecialchars($_POST['distributor_quantity'] ?? $product_data['stock_quantity']) ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label for="purchase_price" class="form-label">Purchase Price (per unit)</label>
                                            <input type="number" step="0.01" class="form-control" id="purchase_price" name="purchase_price"
                                                   value="<?= htmlspecialchars($_POST['purchase_price'] ?? $product_data['cost_price'] ?? '') ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-end mt-4">
                            <button type="submit" class="btn btn-primary">Add Product</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Client-side validation
document.getElementById('addProductForm').addEventListener('submit', function(e) {
    let valid = true;
    
    // Validate required fields
    const requiredFields = ['name', 'sku', 'price', 'stock_quantity'];
    requiredFields.forEach(field => {
        const element = document.getElementById(field);
        if (!element.value.trim()) {
            alert(`Please fill in the ${element.labels[0].textContent} field`);
            element.focus();
            valid = false;
            return false;
        }
    });
    
    // Validate distributor fields if distributor is selected
    const distributorId = document.getElementById('distributor_id').value;
    if (distributorId) {
        const quantity = document.getElementById('distributor_quantity').value;
        const purchasePrice = document.getElementById('purchase_price').value;
        
        if (!quantity || quantity <= 0) {
            alert('Please enter a valid quantity for the distributor');
            valid = false;
        }
        
        if (!purchasePrice || purchasePrice <= 0) {
            alert('Please enter a valid purchase price for the distributor');
            valid = false;
        }
    }
    
    if (!valid) {
        e.preventDefault();
    }
});
</script>

<?php 
require_once "../includes/footer.php";
?>