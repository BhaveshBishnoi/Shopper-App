<?php
session_start();
require_once "../includes/functions.php";
require_once "../config/db_connect.php";
require_once "../includes/notifications.php";

$page_title = "Import Products";

// Handle file upload
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["file"])) {
    $file = $_FILES["file"];
    $allowed = ["csv"];
    $ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    
    if (!in_array($ext, $allowed)) {
        add_notification("Please upload a CSV file", "error");
    } elseif ($file["error"] !== 0) {
        add_notification("Error uploading file: " . $file["error"], "error");
    } else {
        $handle = fopen($file["tmp_name"], "r");
        $header = fgetcsv($handle);
        $success_count = 0;
        $error_count = 0;
        
        mysqli_begin_transaction($conn);
        
        try {
            while (($data = fgetcsv($handle)) !== FALSE) {
                if (count($data) !== count($header)) {
                    throw new Exception("Column count mismatch in CSV");
                }
                
                $row = array_combine($header, $data);
                
                // Validate required fields
                if (empty($row['sku']) || empty($row['name']) || empty($row['price']) || empty($row['gst_rate']) || empty($row['stock_quantity'])) {
                    throw new Exception("Required fields missing: sku, name, price, gst_rate, stock_quantity");
                }
                
                // Check if product exists
                $stmt = mysqli_prepare($conn, "SELECT id FROM products WHERE sku = ?");
                if (!$stmt) {
                    throw new Exception("Error preparing product check query: " . mysqli_error($conn));
                }
                
                mysqli_stmt_bind_param($stmt, "s", $row['sku']);
                
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception("Error checking product existence: " . mysqli_error($conn));
                }
                
                $result = mysqli_stmt_get_result($stmt);
                if (!$result) {
                    throw new Exception("Error getting product check result: " . mysqli_error($conn));
                }
                
                $product = mysqli_fetch_assoc($result);
                
                // Prepare data
                $sku = $row['sku'];
                $name = $row['name'];
                $description = !empty($row['description']) ? $row['description'] : '';
                $category = !empty($row['category']) ? $row['category'] : 'General';
                $price = floatval($row['price']);
                $cost_price = !empty($row['cost_price']) ? floatval($row['cost_price']) : null;
                $gst_rate = floatval($row['gst_rate']);
                $stock_quantity = intval($row['stock_quantity']);
                $low_stock_threshold = !empty($row['low_stock_threshold']) ? intval($row['low_stock_threshold']) : 10;
                
                if ($product) {
                    // Update existing product
                    $query = "UPDATE products SET 
                             name = ?, description = ?, category = ?, price = ?, cost_price = ?, gst_rate = ?, 
                             stock_quantity = ?, low_stock_threshold = ?, updated_at = NOW()
                             WHERE sku = ?";
                             
                    $stmt = mysqli_prepare($conn, $query);
                    if (!$stmt) {
                        throw new Exception("Error preparing product update query: " . mysqli_error($conn));
                    }
                    
                    mysqli_stmt_bind_param($stmt, "sssdddiis", 
                        $name, $description, $category, $price, $cost_price, $gst_rate, 
                        $stock_quantity, $low_stock_threshold, $sku
                    );
                } else {
                    // Insert new product
                    $query = "INSERT INTO products (sku, name, description, category, price, cost_price, gst_rate, stock_quantity, low_stock_threshold) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                             
                    $stmt = mysqli_prepare($conn, $query);
                    if (!$stmt) {
                        throw new Exception("Error preparing product insert query: " . mysqli_error($conn));
                    }
                    
                    mysqli_stmt_bind_param($stmt, "ssssdddii", 
                        $sku, $name, $description, $category, $price, $cost_price, $gst_rate, 
                        $stock_quantity, $low_stock_threshold
                    );
                }
                
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception("Error saving product: " . mysqli_error($conn));
                }
                
                $success_count++;
            }
            
            mysqli_commit($conn);
            add_notification("Successfully imported {$success_count} products!", "success");
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            add_notification($e->getMessage(), "error");
        }
        
        fclose($handle);
    }
}

require_once "../includes/header.php";
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Import Products</h5>
                    <div>
                        <a href="export.php" class="btn btn-info btn-sm">
                            <i class="fas fa-file-export me-2"></i>Export Products
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (has_notifications()): ?>
                        <?php foreach (get_notifications() as $notification): ?>
                            <div class="alert alert-<?php echo $notification['type']; ?>">
                                <?php echo $notification['message']; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <!-- Import Form -->
                    <form method="post" enctype="multipart/form-data" class="mb-4">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="file" class="form-control-label">Choose CSV File</label>
                                    <input type="file" class="form-control" id="file" name="file" accept=".csv" required>
                                    <small class="form-text text-muted">Only CSV files are allowed</small>
                                </div>
                            </div>
                            <div class="col-12 mt-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-file-import me-2"></i>Import Products
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- CSV Format Guide -->
                    <div class="mt-4">
                        <h6 class="mb-3">CSV Format Guide</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Column Name</th>
                                        <th>Required</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>sku</td>
                                        <td><span class="badge bg-danger">Yes</span></td>
                                        <td>Unique product identifier</td>
                                    </tr>
                                    <tr>
                                        <td>name</td>
                                        <td><span class="badge bg-danger">Yes</span></td>
                                        <td>Product name</td>
                                    </tr>
                                    <tr>
                                        <td>description</td>
                                        <td><span class="badge bg-secondary">No</span></td>
                                        <td>Product description</td>
                                    </tr>
                                    <tr>
                                        <td>category</td>
                                        <td><span class="badge bg-secondary">No</span></td>
                                        <td>Product category</td>
                                    </tr>
                                    <tr>
                                        <td>price</td>
                                        <td><span class="badge bg-danger">Yes</span></td>
                                        <td>Selling price</td>
                                    </tr>
                                    <tr>
                                        <td>cost_price</td>
                                        <td><span class="badge bg-secondary">No</span></td>
                                        <td>Cost price</td>
                                    </tr>
                                    <tr>
                                        <td>gst_rate</td>
                                        <td><span class="badge bg-danger">Yes</span></td>
                                        <td>GST rate in percentage</td>
                                    </tr>
                                    <tr>
                                        <td>stock_quantity</td>
                                        <td><span class="badge bg-danger">Yes</span></td>
                                        <td>Current stock quantity</td>
                                    </tr>
                                    <tr>
                                        <td>low_stock_threshold</td>
                                        <td><span class="badge bg-secondary">No</span></td>
                                        <td>Minimum stock level for alerts</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Sample CSV -->
                        <div class="mt-4">
                            <h6 class="mb-3">Sample CSV Format</h6>
                            <pre class="bg-light p-3 rounded"><code>sku,name,description,category,price,cost_price,gst_rate,stock_quantity,low_stock_threshold
PRD001,Product 1,Description 1,General,999.99,800.00,18,100,10
PRD002,Product 2,Description 2,Electronics,499.99,400.00,18,50,5</code></pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once "../includes/footer.php"; ?>
