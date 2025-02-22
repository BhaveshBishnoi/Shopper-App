<?php
session_start();
require_once "../config/db_connect.php";
require_once "../includes/functions.php";
require_once "../includes/notifications.php";
require_once "../includes/product_notifications.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Start transaction
        mysqli_begin_transaction($conn);

        // Sanitize and validate input
        $name = sanitize_input($_POST['name']);
        $sku = sanitize_input($_POST['sku']);
        $description = sanitize_input($_POST['description']);
        $category = sanitize_input($_POST['category']);
        $price = floatval($_POST['price']);
        $cost_price = !empty($_POST['cost_price']) ? floatval($_POST['cost_price']) : null;
        $stock_quantity = intval($_POST['stock_quantity']);
        $low_stock_threshold = !empty($_POST['low_stock_threshold']) ? intval($_POST['low_stock_threshold']) : 10;
        $gst_rate = floatval($_POST['gst_rate']);

        // Validate required fields
        $errors = [];
        if (empty($name)) $errors[] = "Product name is required";
        if (empty($sku)) $errors[] = "SKU is required";
        if ($price <= 0) $errors[] = "Price must be greater than 0";
        if ($stock_quantity < 0) $errors[] = "Stock quantity cannot be negative";
        if ($low_stock_threshold < 0) $errors[] = "Low stock threshold cannot be negative";
        if ($gst_rate < 0) $errors[] = "GST rate cannot be negative";

        // Check if SKU already exists
        $stmt = mysqli_prepare($conn, "SELECT id FROM products WHERE sku = ?");
        mysqli_stmt_bind_param($stmt, "s", $sku);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        if (mysqli_stmt_num_rows($stmt) > 0) {
            $errors[] = "SKU already exists";
        }
        mysqli_stmt_close($stmt);

        if (empty($errors)) {
            // Insert product
            $query = "INSERT INTO products (name, sku, description, category, price, cost_price, 
                     stock_quantity, low_stock_threshold, gst_rate) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = mysqli_prepare($conn, $query);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "ssssddiid", 
                    $name, $sku, $description, $category, 
                    $price, $cost_price, $stock_quantity, 
                    $low_stock_threshold, $gst_rate
                );

                if (mysqli_stmt_execute($stmt)) {
                    mysqli_commit($conn);
                    notify_product_created($name, $sku);
                    
                    // Check if stock is below threshold
                    if ($stock_quantity <= $low_stock_threshold) {
                        notify_product_low_stock($name, $stock_quantity, $low_stock_threshold);
                    }
                    
                    header("Location: index.php");
                    exit;
                } else {
                    throw new Exception(mysqli_error($conn));
                }
            } else {
                throw new Exception(mysqli_error($conn));
            }
        } else {
            foreach ($errors as $error) {
                add_notification($error, "error");
            }
        }
    } catch (Exception $e) {
        mysqli_rollback($conn);
        notify_product_error('creating', $e->getMessage(), ['name' => $name, 'sku' => $sku]);
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
                    <form method="post" action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" id="addProductForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Product Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name" required 
                                           value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="sku" class="form-label">SKU <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="sku" name="sku" required
                                           value="<?= isset($_POST['sku']) ? htmlspecialchars($_POST['sku']) : '' ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="category" class="form-label">Category</label>
                                    <input type="text" class="form-control" id="category" name="category"
                                           value="<?= isset($_POST['category']) ? htmlspecialchars($_POST['category']) : '' ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="3"><?= isset($_POST['description']) ? htmlspecialchars($_POST['description']) : '' ?></textarea>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="price" class="form-label">Price <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" class="form-control" id="price" name="price" required
                                           value="<?= isset($_POST['price']) ? htmlspecialchars($_POST['price']) : '' ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="cost_price" class="form-label">Cost Price</label>
                                    <input type="number" step="0.01" class="form-control" id="cost_price" name="cost_price"
                                           value="<?= isset($_POST['cost_price']) ? htmlspecialchars($_POST['cost_price']) : '' ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="stock_quantity" class="form-label">Stock Quantity <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="stock_quantity" name="stock_quantity" required
                                           value="<?= isset($_POST['stock_quantity']) ? htmlspecialchars($_POST['stock_quantity']) : '0' ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="low_stock_threshold" class="form-label">Low Stock Threshold</label>
                                    <input type="number" class="form-control" id="low_stock_threshold" name="low_stock_threshold"
                                           value="<?= isset($_POST['low_stock_threshold']) ? htmlspecialchars($_POST['low_stock_threshold']) : '10' ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="gst_rate" class="form-label">GST Rate (%)</label>
                                    <input type="number" step="0.01" class="form-control" id="gst_rate" name="gst_rate"
                                           value="<?= isset($_POST['gst_rate']) ? htmlspecialchars($_POST['gst_rate']) : '0' ?>">
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

<?php require_once "../includes/footer.php"; ?>
