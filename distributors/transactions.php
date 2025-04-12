<?php
require_once "../config/db_connect.php";
require_once "../includes/header.php";

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Add new transaction
        if (isset($_POST['add_transaction'])) {
            $distributor_id = intval($_POST['distributor_id']);
            $transaction_date = $_POST['transaction_date'];
            $amount = floatval($_POST['amount']);
            $transaction_type = $_POST['transaction_type'];
            $payment_method = $_POST['payment_method'];
            $description = sanitize_input($_POST['description']);
            
            // Validate inputs
            if ($distributor_id <= 0) {
                throw new Exception("Invalid distributor selected");
            }
            if ($amount <= 0) {
                throw new Exception("Amount must be greater than 0");
            }
            if (!in_array($transaction_type, ['payment', 'purchase'])) {
                throw new Exception("Invalid transaction type");
            }

            // Start transaction
            mysqli_begin_transaction($conn);

            // Insert transaction
            $stmt = mysqli_prepare($conn, "INSERT INTO distributor_transactions 
                (distributor_id, transaction_date, amount, transaction_type, payment_method, description) 
                VALUES (?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "isddss", $distributor_id, $transaction_date, $amount, 
                $transaction_type, $payment_method, $description);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Error adding transaction: " . mysqli_error($conn));
            }

            // Update distributor balance
            $update_query = "UPDATE distributors SET ";
            if ($transaction_type === 'payment') {
                $update_query .= "total_amount_paid = total_amount_paid + $amount";
            } else {
                $update_query .= "total_goods_received = total_goods_received + $amount";
            }
            $update_query .= ", pending_amount = total_goods_received - total_amount_paid WHERE id = $distributor_id";
            
            if (!mysqli_query($conn, $update_query)) {
                throw new Exception("Error updating distributor balance: " . mysqli_error($conn));
            }

            mysqli_commit($conn);
            $_SESSION['success'] = "Transaction added successfully";
            header("Location: transactions.php");
            exit;
        }
        
        // Delete transaction
        if (isset($_POST['delete_transaction'])) {
            $transaction_id = intval($_POST['transaction_id']);
            
            // Get transaction details first
            $stmt = mysqli_prepare($conn, "SELECT distributor_id, amount, transaction_type 
                                         FROM distributor_transactions WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $transaction_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if (mysqli_num_rows($result) === 0) {
                throw new Exception("Transaction not found");
            }
            
            $transaction = mysqli_fetch_assoc($result);
            $distributor_id = $transaction['distributor_id'];
            $amount = $transaction['amount'];
            $transaction_type = $transaction['transaction_type'];
            
            // Start transaction
            mysqli_begin_transaction($conn);
            
            // Delete the transaction
            $stmt = mysqli_prepare($conn, "DELETE FROM distributor_transactions WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $transaction_id);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Error deleting transaction: " . mysqli_error($conn));
            }
            
            // Update distributor balance
            $update_query = "UPDATE distributors SET ";
            if ($transaction_type === 'payment') {
                $update_query .= "total_amount_paid = total_amount_paid - $amount";
            } else {
                $update_query .= "total_goods_received = total_goods_received - $amount";
            }
            $update_query .= ", pending_amount = total_goods_received - total_amount_paid WHERE id = $distributor_id";
            
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

// Get all distributors for dropdown
$distributors = mysqli_query($conn, "SELECT id, name FROM distributors ORDER BY name");

// Get all transactions with distributor info
$query = "SELECT t.*, d.name as distributor_name 
          FROM distributor_transactions t
          JOIN distributors d ON t.distributor_id = d.id
          ORDER BY t.transaction_date DESC, t.id DESC";
$transactions = mysqli_query($conn, $query);

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
                        <i class="fas fa-plus me-2"></i>Add Transaction
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
                                    <th>Type</th>
                                    <th class="text-end">Amount</th>
                                    <th>Payment Method</th>
                                    <th>Description</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = mysqli_fetch_assoc($transactions)): ?>
                                    <tr>
                                        <td><?= date('d M Y', strtotime($row['transaction_date'])) ?></td>
                                        <td><?= htmlspecialchars($row['distributor_name']) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $row['transaction_type'] === 'payment' ? 'success' : 'primary' ?>">
                                                <?= ucfirst($row['transaction_type']) ?>
                                            </span>
                                        </td>
                                        <td class="text-end">₹<?= number_format($row['amount'], 2) ?></td>
                                        <td><?= ucfirst($row['payment_method']) ?></td>
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
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Distributor <span class="text-danger">*</span></label>
                            <select class="form-select" name="distributor_id" required>
                                <option value="">Select Distributor</option>
                                <?php while ($distributor = mysqli_fetch_assoc($distributors)): ?>
                                    <option value="<?= $distributor['id'] ?>"><?= htmlspecialchars($distributor['name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="transaction_date" 
                                   value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Transaction Type <span class="text-danger">*</span></label>
                            <select class="form-select" name="transaction_type" required>
                                <option value="purchase">Purchase (Goods Received)</option>
                                <option value="payment">Payment (Amount Paid)</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Amount <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" class="form-control" 
                                   name="amount" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                            <select class="form-select" name="payment_method" required>
                                <option value="cash">Cash</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="cheque">Cheque</option>
                                <option value="upi">UPI</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Description</label>
                            <input type="text" class="form-control" name="description">
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
                    columns: [0, 1, 2, 3, 4, 5]
                }
            },
            {
                extend: 'pdf',
                text: '<i class="fas fa-file-pdf"></i> PDF',
                className: 'btn btn-danger btn-sm',
                exportOptions: {
                    columns: [0, 1, 2, 3, 4, 5]
                }
            },
            {
                extend: 'print',
                text: '<i class="fas fa-print"></i> Print',
                className: 'btn btn-info btn-sm',
                exportOptions: {
                    columns: [0, 1, 2, 3, 4, 5]
                }
            }
        ],
        initComplete: function() {
            this.api().buttons().container().appendTo($('.dataTables_wrapper .col-md-6:eq(0)'));
        }
    });
    
    // Show modal if there was an error
    <?php if (isset($_SESSION['error']) && strpos($_SESSION['error'], 'transaction') !== false): ?>
        $('#addTransactionModal').modal('show');
    <?php endif; ?>
});
</script>

<?php require_once "../includes/footer.php"; ?>