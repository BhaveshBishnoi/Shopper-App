<?php
require_once "../config/db_connect.php";
require_once "../includes/header.php";
require_once "../includes/functions.php";

$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$product = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM products WHERE id = $product_id"));

if (!$product) {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $quantity = intval($_POST['quantity']);
    $purchase_price = floatval($_POST['purchase_price']);
    $distributor_id = intval($_POST['distributor_id']);
    $transaction_date = $_POST['transaction_date'];
    $payment_status = $_POST['payment_status'];
    $reference_number = sanitize_input($_POST['reference_number']);
    $notes = sanitize_input($_POST['notes']);

    mysqli_begin_transaction($conn);
    try {
        // Add to inventory
        $stmt = mysqli_prepare($conn, "INSERT INTO inventory 
            (product_id, distributor_id, quantity, purchase_price, transaction_date, payment_status, reference_number, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "iidsssss", 
            $product_id, $distributor_id, $quantity, $purchase_price, 
            $transaction_date, $payment_status, $reference_number, $notes);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error adding inventory: " . mysqli_error($conn));
        }

        // Update product stock
        $update = "UPDATE products SET stock_quantity = stock_quantity + $quantity WHERE id = $product_id";
        if (!mysqli_query($conn, $update)) {
            throw new Exception("Error updating product stock: " . mysqli_error($conn));
        }

        mysqli_commit($conn);
        $_SESSION['success'] = "Stock added successfully!";
        header("Location: index.php");
        exit;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error = $e->getMessage();
    }
}

$distributors = mysqli_query($conn, "SELECT id, name FROM distributors ORDER BY name");
?>

<div class="container-fluid py-4">
    <div class="card">
        <div class="card-header">
            <h5>Add Stock: <?= htmlspecialchars($product['name']) ?></h5>
            <p class="mb-0">Current Stock: <?= $product['stock_quantity'] ?></p>
        </div>
        <div class="card-body">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            
            <form method="post">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Quantity <span class="text-danger">*</span></label>
                        <input type="number" min="1" class="form-control" name="quantity" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Purchase Price (per unit) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" class="form-control" name="purchase_price" required>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Distributor</label>
                        <select class="form-select" name="distributor_id">
                            <option value="">Select Distributor</option>
                            <?php while ($distributor = mysqli_fetch_assoc($distributors)): ?>
                                <option value="<?= $distributor['id'] ?>"><?= htmlspecialchars($distributor['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Transaction Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="transaction_date" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Payment Status</label>
                        <select class="form-select" name="payment_status">
                            <option value="pending">Pending</option>
                            <option value="paid">Paid</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Reference Number</label>
                        <input type="text" class="form-control" name="reference_number">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Notes</label>
                    <textarea class="form-control" name="notes" rows="3"></textarea>
                </div>
                
                <div class="text-end">
                    <a href="index.php" class="btn btn-secondary me-2">Cancel</a>
                    <button type="submit" class="btn btn-primary">Add Stock</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once "../includes/footer.php"; ?>