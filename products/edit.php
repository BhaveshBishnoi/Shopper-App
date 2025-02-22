<?php
session_start();
require_once "../config/db_connect.php";
require_once "../includes/functions.php";

// Check if product ID is provided
if (!isset($_GET['id'])) {
    $_SESSION['error'] = "Product ID not provided";
    header("Location: index.php");
    exit;
}

$id = intval($_GET['id']);

// Get product details
$stmt = mysqli_prepare($conn, "SELECT * FROM products WHERE id = ?");
if (!$stmt) {
    $_SESSION['error'] = "Database error: " . mysqli_error($conn);
    header("Location: index.php");
    exit;
}

mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    $_SESSION['error'] = "Product not found";
    header("Location: index.php");
    exit;
}

$product = mysqli_fetch_assoc($result);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize_input($_POST['name']);
    $sku = sanitize_input($_POST['sku']);
    $description = sanitize_input($_POST['description']);
    $price = floatval($_POST['price']);
    $stock_quantity = intval($_POST['stock_quantity']);
    $category = sanitize_input($_POST['category']);
    $gst_rate = floatval($_POST['gst_rate']);
    
    try {
        // Check if SKU exists for other products
        if ($sku !== $product['sku']) {
            $stmt = mysqli_prepare($conn, "SELECT id FROM products WHERE sku = ? AND id != ?");
            mysqli_stmt_bind_param($stmt, "si", $sku, $id);
            mysqli_stmt_execute($stmt);
            if (mysqli_stmt_get_result($stmt)->num_rows > 0) {
                throw new Exception("SKU already exists");
            }
        }
        
        // Update product
        $stmt = mysqli_prepare($conn, "UPDATE products SET name = ?, sku = ?, description = ?, price = ?, stock_quantity = ?, category = ?, gst_rate = ?, updated_at = NOW() WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "sssdisdi", $name, $sku, $description, $price, $stock_quantity, $category, $gst_rate, $id);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success'] = "Product updated successfully";
            header("Location: index.php");
            exit;
        } else {
            throw new Exception("Error updating product: " . mysqli_error($conn));
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

// Get categories for dropdown
$categories_result = mysqli_query($conn, "SELECT DISTINCT category FROM products WHERE category IS NOT NULL ORDER BY category");
$categories = [];
while ($row = mysqli_fetch_assoc($categories_result)) {
    $categories[] = $row['category'];
}

// Include header
require_once "../includes/header.php";
?>

<div class="container my-4">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Edit Product</h4>
                </div>
                <div class="card-body">
                    <form method="POST" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label for="name" class="form-label">Product Name</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="sku" class="form-label">SKU</label>
                            <input type="text" class="form-control" id="sku" name="sku" value="<?php echo htmlspecialchars($product['sku']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($product['description']); ?></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="price" class="form-label">Price</label>
                                <div class="input-group">
                                    <span class="input-group-text">₹</span>
                                    <input type="number" class="form-control" id="price" name="price" value="<?php echo $product['price']; ?>" step="0.01" min="0" required>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="stock_quantity" class="form-label">Stock Quantity</label>
                                <input type="number" class="form-control" id="stock_quantity" name="stock_quantity" value="<?php echo $product['stock_quantity']; ?>" min="0" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="category" class="form-label">Category</label>
                                <input type="text" class="form-control" id="category" name="category" value="<?php echo htmlspecialchars($product['category']); ?>" list="categories">
                                <datalist id="categories">
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo htmlspecialchars($category); ?>">
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="gst_rate" class="form-label">GST Rate (%)</label>
                                <input type="number" class="form-control" id="gst_rate" name="gst_rate" value="<?php echo $product['gst_rate']; ?>" min="0" max="100" step="0.01" required>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">Update Product</button>
                            <a href="index.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once "../includes/footer.php"; ?>
