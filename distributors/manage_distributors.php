<?php
session_start();
require_once "../config/db_connect.php";
require_once "../includes/functions.php";
require_once "../includes/header.php";

// Check if product ID is provided
if (!isset($_GET['id'])) {
    $_SESSION['error'] = "Product ID not provided";
    header("Location: ../products/index.php");
    exit;
}

$product_id = intval($_GET['id']);

// Get product details
$product = mysqli_query($conn, "SELECT id, name, sku FROM products WHERE id = $product_id");
if (mysqli_num_rows($product) === 0) {
    $_SESSION['error'] = "Product not found";
    header("Location: ../products/index.php");
    exit;
}
$product = mysqli_fetch_assoc($product);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        mysqli_begin_transaction($conn);

        if (isset($_POST['add_distributor'])) {
            // Add new distributor to product
            $distributor_id = intval($_POST['distributor_id']);
            $quantity = intval($_POST['quantity']);
            $purchase_price = floatval($_POST['purchase_price']);

            // Validate
            if ($distributor_id <= 0) throw new Exception("Invalid distributor");
            if ($quantity <= 0) throw new Exception("Quantity must be positive");
            if ($purchase_price <= 0) throw new Exception("Purchase price must be positive");

            // Check if relationship already exists
            $exists = mysqli_query($conn, "SELECT id FROM distributor_products 
                                         WHERE product_id = $product_id AND distributor_id = $distributor_id");
            if (mysqli_num_rows($exists) > 0) {
                throw new Exception("This distributor is already assigned to this product");
            }

            // Create relationship
            $stmt = mysqli_prepare($conn, "INSERT INTO distributor_products 
                                         (product_id, distributor_id, quantity, purchase_price)
                                         VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "iiid", $product_id, $distributor_id, $quantity, $purchase_price);
            mysqli_stmt_execute($stmt);

            // Update distributor's financials
            $total_amount = $quantity * $purchase_price;
            mysqli_query($conn, "UPDATE distributors 
                                SET total_goods_received = total_goods_received + $total_amount,
                                    pending_amount = (total_goods_received + $total_amount) - total_amount_paid
                                WHERE id = $distributor_id");

            $_SESSION['success'] = "Distributor added successfully";
        }
        elseif (isset($_POST['update_quantities'])) {
            // Update quantities for existing distributors
            foreach ($_POST['quantities'] as $dp_id => $quantity) {
                $dp_id = intval($dp_id);
                $quantity = intval($quantity);
                
                if ($quantity < 0) continue;
                
                // Get current values
                $current = mysqli_query($conn, "SELECT distributor_id, quantity, purchase_price 
                                              FROM distributor_products 
                                              WHERE id = $dp_id AND product_id = $product_id");
                if (mysqli_num_rows($current) === 0) continue;
                $current = mysqli_fetch_assoc($current);
                
                $difference = $quantity - $current['quantity'];
                if ($difference == 0) continue;
                
                // Update relationship
                mysqli_query($conn, "UPDATE distributor_products 
                                    SET quantity = $quantity 
                                    WHERE id = $dp_id");
                
                // Update distributor's financials
                $amount_diff = $difference * $current['purchase_price'];
                mysqli_query($conn, "UPDATE distributors 
                                    SET total_goods_received = total_goods_received + $amount_diff,
                                        pending_amount = (total_goods_received + $amount_diff) - total_amount_paid
                                    WHERE id = {$current['distributor_id']}");
            }
            
            $_SESSION['success'] = "Quantities updated successfully";
        }
        elseif (isset($_POST['remove_distributor'])) {
            // Remove distributor from product
            $dp_id = intval($_POST['dp_id']);
            
            // Get current values
            $current = mysqli_query($conn, "SELECT distributor_id, quantity, purchase_price 
                                          FROM distributor_products 
                                          WHERE id = $dp_id AND product_id = $product_id");
            if (mysqli_num_rows($current) > 0) {
                $current = mysqli_fetch_assoc($current);
                
                // Delete relationship
                mysqli_query($conn, "DELETE FROM distributor_products WHERE id = $dp_id");
                
                // Update distributor's financials
                $amount = $current['quantity'] * $current['purchase_price'];
                mysqli_query($conn, "UPDATE distributors 
                                   SET total_goods_received = total_goods_received - $amount,
                                       pending_amount = (total_goods_received - $amount) - total_amount_paid
                                   WHERE id = {$current['distributor_id']}");
                
                $_SESSION['success'] = "Distributor removed successfully";
            }
        }

        mysqli_commit($conn);
        header("Location: manage_distributors.php?id=$product_id");
        exit;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['error'] = $e->getMessage();
        header("Location: manage_distributors.php?id=$product_id");
        exit;
    }
}

// Get current distributors
$current_distributors = mysqli_query($conn, 
    "SELECT dp.id, d.id as distributor_id, d.name, dp.quantity, dp.purchase_price
     FROM distributor_products dp
     JOIN distributors d ON dp.distributor_id = d.id
     WHERE dp.product_id = $product_id
     ORDER BY d.name");

// Get available distributors (not already assigned)
$available_distributors = mysqli_query($conn,
    "SELECT id, name FROM distributors 
     WHERE id NOT IN (SELECT distributor_id FROM distributor_products WHERE product_id = $product_id)
     ORDER BY name");
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header pb-0">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Manage Distributors for: <?= htmlspecialchars($product['name']) ?> (<?= htmlspecialchars($product['sku']) ?>)</h5>
                        <a href="../products/edit.php?id=<?= $product_id ?>" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left me-2"></i>Back to Product
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (mysqli_num_rows($current_distributors) > 0): ?>
                        <form method="POST">
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Distributor</th>
                                            <th class="text-end">Current Quantity</th>
                                            <th class="text-end">Purchase Price</th>
                                            <th class="text-end">Total Value</th>
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($distributor = mysqli_fetch_assoc($current_distributors)): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($distributor['name']) ?></td>
                                                <td class="text-end">
                                                    <input type="number" name="quantities[<?= $distributor['id'] ?>]" 
                                                           value="<?= $distributor['quantity'] ?>" min="0" class="form-control text-end">
                                                </td>
                                                <td class="text-end">₹<?= number_format($distributor['purchase_price'], 2) ?></td>
                                                <td class="text-end">₹<?= number_format($distributor['quantity'] * $distributor['purchase_price'], 2) ?></td>
                                                <td class="text-end">
                                                    <button type="submit" name="remove_distributor" value="1" 
                                                            class="btn btn-danger btn-sm"
                                                            onclick="return confirm('Remove this distributor?')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                    <input type="hidden" name="dp_id" value="<?= $distributor['id'] ?>">
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="text-end mt-3">
                                <button type="submit" name="update_quantities" class="btn btn-primary">
                                    Update Quantities
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-info">
                            This product is not currently assigned to any distributors.
                        </div>
                    <?php endif; ?>

                    <?php if (mysqli_num_rows($available_distributors) > 0): ?>
                        <div class="card mt-4">
                            <div class="card-header">
                                <h6>Add New Distributor</h6>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="row">
                                        <div class="col-md-5">
                                            <div class="mb-3">
                                                <label class="form-label">Distributor</label>
                                                <select class="form-select" name="distributor_id" required>
                                                    <option value="">Select Distributor</option>
                                                    <?php while ($distributor = mysqli_fetch_assoc($available_distributors)): ?>
                                                        <option value="<?= $distributor['id'] ?>"><?= htmlspecialchars($distributor['name']) ?></option>
                                                    <?php endwhile; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label class="form-label">Quantity</label>
                                                <input type="number" name="quantity" min="1" value="1" class="form-control" required>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label class="form-label">Purchase Price (per unit)</label>
                                                <input type="number" step="0.01" name="purchase_price" min="0.01" class="form-control" required>
                                            </div>
                                        </div>
                                        <div class="col-md-1 d-flex align-items-end">
                                            <button type="submit" name="add_distributor" class="btn btn-primary">
                                                Add
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once "../includes/footer.php"; ?>