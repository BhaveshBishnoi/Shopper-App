<?php
require_once "../config/db_connect.php";
require_once "../includes/header.php";
require_once "../includes/functions.php";

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Add new transaction handler
        if (isset($_POST['add_transaction'])) {
            $distributor_id = intval($_POST['distributor_id']);
            $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : null;
            $transaction_date = mysqli_real_escape_string($conn, $_POST['transaction_date']);
            $amount = floatval($_POST['amount']);
            $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : null;
            $transaction_type = mysqli_real_escape_string($conn, $_POST['transaction_type']);
            $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
            $reference_number = mysqli_real_escape_string($conn, $_POST['reference_number']);
            $description = mysqli_real_escape_string($conn, $_POST['description']);
            
            // Start transaction
            mysqli_begin_transaction($conn);
            
            // Insert the transaction
            $stmt = mysqli_prepare($conn, "INSERT INTO distributor_transactions 
                (distributor_id, product_id, transaction_date, amount, quantity, 
                transaction_type, payment_method, reference_number, description) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "iisdissss", $distributor_id, $product_id, $transaction_date, 
                $amount, $quantity, $transaction_type, $payment_method, $reference_number, $description);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Error adding transaction: " . mysqli_error($conn));
            }
            
            // Update distributor balance
            $update_query = "UPDATE distributors SET ";
            if ($transaction_type === 'payment') {
                $update_query .= "total_amount_paid = total_amount_paid + $amount";
            } else {
                $update_query .= "total_goods_received = total_goods_received + $amount";
                
                // Update product stock if this was a purchase transaction
                if ($product_id && $quantity) {
                    $update_product = "UPDATE products SET stock_quantity = stock_quantity + $quantity 
                                      WHERE id = $product_id";
                    if (!mysqli_query($conn, $update_product)) {
                        throw new Exception("Error updating product stock: " . mysqli_error($conn));
                    }
                    
                    // Update distributor-product relationship
                    $dist_prod_query = "INSERT INTO distributor_products 
                                      (distributor_id, product_id, quantity, purchase_price)
                                      VALUES ($distributor_id, $product_id, $quantity, " . ($amount/$quantity) . ")
                                      ON DUPLICATE KEY UPDATE 
                                      quantity = quantity + $quantity,
                                      purchase_price = " . ($amount/$quantity);
                    if (!mysqli_query($conn, $dist_prod_query)) {
                        throw new Exception("Error updating distributor products: " . mysqli_error($conn));
                    }
                }
            }
            $update_query .= ", pending_amount = total_goods_received - total_amount_paid 
                            WHERE id = $distributor_id";
            
            if (!mysqli_query($conn, $update_query)) {
                throw new Exception("Error updating distributor balance: " . mysqli_error($conn));
            }
            
            mysqli_commit($conn);
            $_SESSION['success'] = "Transaction added successfully";
            header("Location: transactions.php");
            exit;
        }
        
        // Delete transaction handler
        if (isset($_POST['delete_transaction'])) {
            $transaction_id = intval($_POST['transaction_id']);
            
            // Get transaction details first
            $stmt = mysqli_prepare($conn, "SELECT distributor_id, product_id, amount, quantity, transaction_type 
                                         FROM distributor_transactions WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $transaction_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if (mysqli_num_rows($result) === 0) {
                throw new Exception("Transaction not found");
            }
            
            $transaction = mysqli_fetch_assoc($result);
            $distributor_id = $transaction['distributor_id'];
            $product_id = $transaction['product_id'];
            $amount = $transaction['amount'];
            $quantity = $transaction['quantity'];
            $transaction_type = $transaction['transaction_type'];
            
            // Start transaction
            mysqli_begin_transaction($conn);
            
            // Delete the transaction
            $stmt = mysqli_prepare($conn, "DELETE FROM distributor_transactions WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $transaction_id);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Error deleting transaction: " . mysqli_error($conn));
            }
            

            $check_column = mysqli_query($conn, "SHOW COLUMNS FROM distributor_transactions LIKE 'quantity'");
if (mysqli_num_rows($check_column) == 0) {
    mysqli_query($conn, "ALTER TABLE distributor_transactions ADD COLUMN quantity INT NULL AFTER amount");
}
            // Update distributor balance
            $update_query = "UPDATE distributors SET ";
            if ($transaction_type === 'payment') {
                $update_query .= "total_amount_paid = total_amount_paid - $amount";
            } else {
                $update_query .= "total_goods_received = total_goods_received - $amount";
                
                // Update product stock if this was a purchase transaction
                if ($product_id && $quantity) {
                    $update_product = "UPDATE products SET stock_quantity = stock_quantity - $quantity 
                                      WHERE id = $product_id";
                    if (!mysqli_query($conn, $update_product)) {
                        throw new Exception("Error updating product stock: " . mysqli_error($conn));
                    }
                    
                    // Update distributor-product relationship
                    $dist_prod_query = "UPDATE distributor_products 
                                      SET quantity = quantity - $quantity
                                      WHERE distributor_id = $distributor_id AND product_id = $product_id";
                    if (!mysqli_query($conn, $dist_prod_query)) {
                        throw new Exception("Error updating distributor products: " . mysqli_error($conn));
                    }
                }
            }
            $update_query .= ", pending_amount = total_goods_received - total_amount_paid 
                            WHERE id = $distributor_id";
            
            if (!mysqli_query($conn, $update_query)) {
                throw new Exception("Error updating distributor balance: " . mysqli_error($conn));
            }
            
            mysqli_commit($conn);
            $_SESSION['success'] = "Transaction deleted successfully";
            header("Location: transactions.php");
            exit;
        }
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['error'] = $e->getMessage();
        header("Location: transactions.php");
        exit;
    }
}

// Get all transactions with distributor and product info
$query = "SELECT 
            t.id,
            t.transaction_date,
            t.amount,
            t.transaction_type,
            t.payment_method,
            t.reference_number,
            t.description,
            t.created_at,
            d.id as distributor_id,
            d.name as distributor_name,
            d.contact_person as distributor_contact,
            d.phone as distributor_phone,
            p.id as product_id,
            p.name as product_name,
            p.sku as product_sku,
            t.amount as total_amount,
            CASE 
                WHEN t.transaction_type = 'purchase' THEN 'Goods Received'
                WHEN t.transaction_type = 'payment' THEN 'Amount Paid'
                ELSE t.transaction_type
            END as transaction_type_label,
            CONCAT('₹', FORMAT(t.amount, 2)) as formatted_amount
          FROM distributor_transactions t
          INNER JOIN distributors d ON t.distributor_id = d.id
          LEFT JOIN products p ON t.product_id = p.id
          ORDER BY t.transaction_date DESC, t.id DESC";

$transactions = mysqli_query($conn, $query);

// Get distributors for dropdown
$distributors = mysqli_query($conn, "SELECT id, name FROM distributors ORDER BY name");

// Get products for dropdown
$products = mysqli_query($conn, "SELECT id, name, sku FROM products ORDER BY name");

// Calculate totals
$total_purchases = 0;
$total_payments = 0;
while ($row = mysqli_fetch_assoc($transactions)) {
    if ($row['transaction_type'] === 'purchase') {
        $total_purchases += $row['amount'];
    } else {
        $total_payments += $row['amount'];
    }
}
$transactions = mysqli_query($conn, $query); // Reset pointer
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Distributor Transactions</h5>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addTransactionModal">
                        <i class="fas fa-plus"></i> Add Transaction
                    </button>
                </div>
                <div class="card-body">
                    <!-- Summary Cards -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="card bg-primary text-white">
                                <div class="card-body">
                                    <h6 class="card-title">Total Purchases</h6>
                                    <h4 class="card-text">₹<?= number_format($total_purchases, 2) ?></h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-success text-white">
                                <div class="card-body">
                                    <h6 class="card-title">Total Payments</h6>
                                    <h4 class="card-text">₹<?= number_format($total_payments, 2) ?></h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-<?= ($total_purchases - $total_payments) > 0 ? 'warning' : 'info' ?> text-white">
                                <div class="card-body">
                                    <h6 class="card-title">Pending Balance</h6>
                                    <h4 class="card-text">₹<?= number_format($total_purchases - $total_payments, 2) ?></h4>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Transactions Table -->
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover datatable">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Distributor</th>
                                    <th>Product</th>
                                    <th>Type</th>
                                    <th class="text-end">Amount</th>
                                    <th>Qty</th>
                                    <th>Payment Method</th>
                                    <th>Reference</th>
                                    <th>Description</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = mysqli_fetch_assoc($transactions)): ?>
                                    <tr>
                                        <td><?= date('d M Y', strtotime($row['transaction_date'])) ?></td>
                                        <td><?= htmlspecialchars($row['distributor_name']) ?></td>
                                        <td><?= $row['product_name'] ? htmlspecialchars($row['product_name']) : 'N/A' ?></td>
                                        <td>
                                            <span class="badge bg-<?= $row['transaction_type'] === 'payment' ? 'success' : 'primary' ?>">
                                                <?= $row['transaction_type_label'] ?>
                                            </span>
                                        </td>
                                        <td class="text-end">₹<?= number_format($row['amount'], 2) ?></td>
                                        <td><?= $row['quantity'] ?? 'N/A' ?></td>
                                        <td><?= ucfirst(str_replace('_', ' ', $row['payment_method'])) ?></td>
                                        <td><?= $row['reference_number'] ? htmlspecialchars($row['reference_number']) : 'N/A' ?></td>
                                        <td><?= htmlspecialchars($row['description']) ?></td>
                                        <td class="text-end">
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="transaction_id" value="<?= $row['id'] ?>">
                                                <button type="submit" name="delete_transaction" class="btn btn-danger btn-sm" 
                                                    onclick="return confirm('Are you sure you want to delete this transaction?')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Transaction Modal -->
<div class="modal fade" id="addTransactionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Transaction</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Distributor *</label>
                            <select class="form-select" name="distributor_id" required>
                                <option value="">Select Distributor</option>
                                <?php while ($distributor = mysqli_fetch_assoc($distributors)): ?>
                                    <option value="<?= $distributor['id'] ?>"><?= htmlspecialchars($distributor['name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Transaction Type *</label>
                            <select class="form-select" name="transaction_type" id="transactionType" required>
                                <option value="purchase">Goods Received (Purchase)</option>
                                <option value="payment">Amount Paid</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Transaction Date *</label>
                            <input type="date" class="form-control" name="transaction_date" 
                                value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Amount (₹) *</label>
                            <input type="number" step="0.01" class="form-control" name="amount" required>
                        </div>
                        <div class="col-md-6" id="productField">
                            <label class="form-label">Product</label>
                            <select class="form-select" name="product_id">
                                <option value="">Select Product</option>
                                <?php while ($product = mysqli_fetch_assoc($products)): ?>
                                    <option value="<?= $product['id'] ?>">
                                        <?= htmlspecialchars($product['name']) ?> (<?= htmlspecialchars($product['sku']) ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6" id="quantityField">
                            <label class="form-label">Quantity</label>
                            <input type="number" class="form-control" name="quantity">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Payment Method *</label>
                            <select class="form-select" name="payment_method" required>
                                <option value="cash">Cash</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="cheque">Cheque</option>
                                <option value="upi">UPI</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Reference Number</label>
                            <input type="text" class="form-control" name="reference_number">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="add_transaction" class="btn btn-primary">Save Transaction</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable with export buttons
    $('.datatable').DataTable({
        dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
             "<'row'<'col-sm-12'tr>>" +
             "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
        pageLength: 25,
        order: [[0, 'desc']],
        responsive: true,
        buttons: [
            {
                extend: 'excel',
                text: '<i class="fas fa-file-excel"></i> Excel',
                className: 'btn btn-success btn-sm',
                exportOptions: {
                    columns: [0, 1, 2, 3, 4, 5, 6, 7, 8]
                }
            },
            {
                extend: 'pdf',
                text: '<i class="fas fa-file-pdf"></i> PDF',
                className: 'btn btn-danger btn-sm',
                exportOptions: {
                    columns: [0, 1, 2, 3, 4, 5, 6, 7, 8]
                }
            },
            {
                extend: 'print',
                text: '<i class="fas fa-print"></i> Print',
                className: 'btn btn-info btn-sm',
                exportOptions: {
                    columns: [0, 1, 2, 3, 4, 5, 6, 7, 8]
                }
            }
        ],
        initComplete: function() {
            this.api().buttons().container().appendTo($('.dataTables_wrapper .col-md-6:eq(0)'));
        }
    });
    
    // Show/hide product and quantity fields based on transaction type
    $('#transactionType').change(function() {
        if ($(this).val() === 'purchase') {
            $('#productField, #quantityField').show();
            $('[name="product_id"], [name="quantity"]').attr('required', true);
        } else {
            $('#productField, #quantityField').hide();
            $('[name="product_id"], [name="quantity"]').attr('required', false);
        }
    }).trigger('change');
});
</script>

<?php 
// Clear messages
unset($_SESSION['error']);
unset($_SESSION['success']);
require_once "../includes/footer.php"; 
?>