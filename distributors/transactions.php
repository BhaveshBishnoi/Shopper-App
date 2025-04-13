<?php
require_once "../config/db_connect.php";
require_once "../includes/header.php";
require_once "../includes/functions.php";

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_transaction'])) {
        // Add new transaction
        $distributor_id = (int)$_POST['distributor_id'];
        $transaction_type = sanitize_input($_POST['transaction_type']);
        $amount = (float)$_POST['amount'];
        $transaction_date = sanitize_input($_POST['transaction_date']);
        $payment_method = sanitize_input($_POST['payment_method']);
        $description = sanitize_input($_POST['description']);

        // Begin transaction
        $conn->begin_transaction();

        try {
            // Insert transaction record
            $stmt = $conn->prepare("INSERT INTO transactions 
                                  (distributor_id, transaction_type, amount, transaction_date, payment_method, description) 
                                  VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isdsss", $distributor_id, $transaction_type, $amount, $transaction_date, $payment_method, $description);
            $stmt->execute();
            
            // Update distributor's balance
            if ($transaction_type === 'payment') {
                $update_query = "UPDATE distributors SET 
                                total_amount_paid = total_amount_paid + ?,
                                pending_amount = GREATEST(0, pending_amount - ?)
                                WHERE id = ?";
            } else { // purchase
                $update_query = "UPDATE distributors SET 
                                total_goods_received = total_goods_received + ?,
                                pending_amount = pending_amount + ?
                                WHERE id = ?";
            }
            
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("ddi", $amount, $amount, $distributor_id);
            $update_stmt->execute();

            $conn->commit();
            $_SESSION['success'] = "Transaction added successfully!";
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = "Error: " . $e->getMessage();
        }

        header("Location: transactions.php?distributor_id=".$distributor_id);
        exit();
    }
}

// Get distributor ID from query string
$distributor_id = isset($_GET['distributor_id']) ? (int)$_GET['distributor_id'] : 0;

// Get distributor details if ID is provided
$distributor = null;
if ($distributor_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM distributors WHERE id = ?");
    $stmt->bind_param("i", $distributor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $distributor = $result->fetch_assoc();
}

// Get all transactions for the distributor
$transactions = [];
if ($distributor_id > 0) {
    $stmt = $conn->prepare("SELECT t.*, d.name as distributor_name 
                          FROM transactions t
                          JOIN distributors d ON t.distributor_id = d.id
                          WHERE t.distributor_id = ? 
                          ORDER BY t.transaction_date DESC, t.id DESC");
    $stmt->bind_param("i", $distributor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $transactions = $result->fetch_all(MYSQLI_ASSOC);
}

// Get all transactions if no distributor selected
if ($distributor_id === 0) {
    $transactions_result = $conn->query("SELECT t.*, d.name as distributor_name 
                                       FROM transactions t
                                       JOIN distributors d ON t.distributor_id = d.id
                                       ORDER BY t.transaction_date DESC, t.id DESC");
    $transactions = $transactions_result->fetch_all(MYSQLI_ASSOC);
}

// Get all distributors for dropdown
$distributors_result = $conn->query("SELECT id, name FROM distributors ORDER BY name");
?>

<div class="container-fluid py-4">
    <!-- Success/Error Alerts -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= $_SESSION['success'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= $_SESSION['error'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center border-bottom-0">
            <h5 class="mb-0">
                <i class="fas fa-exchange-alt me-2"></i>
                <?= $distributor ? "Transactions for ".htmlspecialchars($distributor['name']) : "All Transactions" ?>
            </h5>
            <div>
                <?php if ($distributor): ?>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addTransactionModal">
                        <i class="fas fa-plus me-1"></i> Add Transaction
                    </button>
                <?php endif; ?>
                <a href="distributors.php" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>
        
        <div class="card-body">
            <!-- Distributor Selection Form -->
            <form method="get" class="mb-4 bg-light p-3 rounded">
                <div class="row g-3 align-items-center">
                    <div class="col-md-8">
                        <label class="form-label fw-bold">Select Distributor</label>
                        <select class="form-select" name="distributor_id" onchange="this.form.submit()">
                            <option value="">All Distributors</option>
                            <?php while ($row = $distributors_result->fetch_assoc()): ?>
                                <option value="<?= $row['id'] ?>" <?= $distributor_id == $row['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($row['name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter me-1"></i> Filter
                        </button>
                    </div>
                </div>
            </form>

            <!-- Transaction Summary Cards -->
            <?php if ($distributor): ?>
                <div class="row mb-4 g-3">
                    <div class="col-md-4">
                        <div class="card border-start border-3 border-primary h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-2">Goods Received</h6>
                                        <h4 class="mb-0">₹<?= number_format($distributor['total_goods_received'], 2) ?></h4>
                                    </div>
                                    <div class="bg-primary bg-opacity-10 p-3 rounded">
                                        <i class="fas fa-box-open text-primary"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-start border-3 border-success h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-2">Amount Paid</h6>
                                        <h4 class="mb-0">₹<?= number_format($distributor['total_amount_paid'], 2) ?></h4>
                                    </div>
                                    <div class="bg-success bg-opacity-10 p-3 rounded">
                                        <i class="fas fa-rupee-sign text-success"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-start border-3 <?= $distributor['pending_amount'] > 0 ? 'border-danger' : 'border-success' ?> h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-2">Pending Amount</h6>
                                        <h4 class="mb-0 <?= $distributor['pending_amount'] > 0 ? 'text-danger' : 'text-success' ?>">
                                            ₹<?= number_format($distributor['pending_amount'], 2) ?>
                                        </h4>
                                    </div>
                                    <div class="<?= $distributor['pending_amount'] > 0 ? 'bg-danger' : 'bg-success' ?> bg-opacity-10 p-3 rounded">
                                        <i class="fas <?= $distributor['pending_amount'] > 0 ? 'fa-exclamation-triangle text-danger' : 'fa-check-circle text-success' ?>"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Transactions Table -->
            <div class="card border-0 shadow-none">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle datatable">
                            <thead class="bg-light">
                                <tr>
                                    <?php if (!$distributor): ?>
                                        <th width="15%">Distributor</th>
                                    <?php endif; ?>
                                    <th width="12%">Date</th>
                                    <th width="10%">Type</th>
                                    <th width="12%" class="text-end">Amount</th>
                                    <th width="12%">Method</th>
                                    <th>Description</th>
                                    <th width="8%" class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($transactions)): ?>
                                    <tr>
                                        <td colspan="<?= $distributor ? 6 : 7 ?>" class="text-center py-5">
                                            <div class="py-4">
                                                <i class="fas fa-exchange-alt fa-3x text-muted mb-3"></i>
                                                <h5>No transactions found</h5>
                                                <p class="text-muted">
                                                    <?php if ($distributor): ?>
                                                        <a href="#" data-bs-toggle="modal" data-bs-target="#addTransactionModal" class="text-primary">
                                                            Add your first transaction
                                                        </a>
                                                    <?php else: ?>
                                                        Select a distributor to view transactions
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($transactions as $txn): ?>
                                        <tr>
                                            <?php if (!$distributor): ?>
                                                <td>
                                                    <a href="transactions.php?distributor_id=<?= $txn['distributor_id'] ?>" class="text-primary">
                                                        <?= htmlspecialchars($txn['distributor_name']) ?>
                                                    </a>
                                                </td>
                                            <?php endif; ?>
                                            <td>
                                                <span class="d-block"><?= date('d M', strtotime($txn['transaction_date'])) ?></span>
                                                <small class="text-muted"><?= date('Y', strtotime($txn['transaction_date'])) ?></small>
                                            </td>
                                            <td>
                                                <span class="badge rounded-pill bg-<?= $txn['transaction_type'] === 'payment' ? 'success' : 'primary' ?>-lt">
                                                    <?= ucfirst($txn['transaction_type']) ?>
                                                </span>
                                            </td>
                                            <td class="text-end <?= $txn['transaction_type'] === 'payment' ? 'text-success' : 'text-primary' ?>">
                                                <span class="d-block">₹<?= number_format($txn['amount'], 2) ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark">
                                                    <?= ucfirst(str_replace('_', ' ', $txn['payment_method'])) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if (!empty($txn['description'])): ?>
                                                    <span class="d-inline-block text-truncate" style="max-width: 200px;" 
                                                          data-bs-toggle="tooltip" title="<?= htmlspecialchars($txn['description']) ?>">
                                                        <?= htmlspecialchars($txn['description']) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">No description</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-secondary rounded-circle" 
                                                            data-bs-toggle="dropdown" aria-expanded="false">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end shadow">
                                                        <li>
                                                            <a class="dropdown-item" href="#" 
                                                               data-bs-toggle="modal" data-bs-target="#editTransactionModal" 
                                                               onclick="setEditTransaction(
                                                                   <?= $txn['id'] ?>, 
                                                                   '<?= $txn['transaction_type'] ?>', 
                                                                   <?= $txn['amount'] ?>, 
                                                                   '<?= $txn['transaction_date'] ?>', 
                                                                   '<?= $txn['payment_method'] ?>', 
                                                                   `<?= addslashes($txn['description']) ?>`)">
                                                                <i class="fas fa-edit me-2 text-primary"></i> Edit
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a class="dropdown-item text-danger" href="#" 
                                                               onclick="deleteTransaction(<?= $txn['id'] ?>, <?= $txn['distributor_id'] ?>)">
                                                                <i class="fas fa-trash me-2"></i> Delete
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
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
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Transaction</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="transactions.php" onsubmit="return validateTransactionForm()">
                <input type="hidden" name="distributor_id" value="<?= $distributor_id ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Transaction Type <span class="text-danger">*</span></label>
                        <select class="form-select" name="transaction_type" required>
                            <option value="payment" selected>Payment (Amount Paid)</option>
                            <option value="purchase">Purchase (Goods Received)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount (₹) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">₹</span>
                            <input type="number" step="0.01" min="0.01" class="form-control" 
                                   name="amount" id="transactionAmount" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="transaction_date" 
                               value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                        <select class="form-select" name="payment_method" required>
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="cheque">Cheque</option>
                            <option value="upi">UPI</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3" placeholder="Optional notes about this transaction"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_transaction" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Save Transaction
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Transaction Modal -->
<div class="modal fade" id="editTransactionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Transaction</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="update_transaction.php">
                <input type="hidden" name="transaction_id" id="editTransactionId">
                <input type="hidden" name="distributor_id" value="<?= $distributor_id ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Transaction Type <span class="text-danger">*</span></label>
                        <select class="form-select" name="transaction_type" id="editTransactionType" required>
                            <option value="payment">Payment (Amount Paid)</option>
                            <option value="purchase">Purchase (Goods Received)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount (₹) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">₹</span>
                            <input type="number" step="0.01" min="0.01" class="form-control" 
                                   name="amount" id="editTransactionAmount" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="transaction_date" id="editTransactionDate" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                        <select class="form-select" name="payment_method" id="editPaymentMethod" required>
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="cheque">Cheque</option>
                            <option value="upi">UPI</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="editTransactionDescription" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_transaction" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Update
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Initialize DataTable with export options
$(document).ready(function() {
    $('.datatable').DataTable({
        dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
             "<'row'<'col-sm-12'tr>>" +
             "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
        pageLength: 25,
        order: [[1, 'desc']], // Sort by date descending by default
        responsive: true,
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search transactions...",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ transactions",
            infoEmpty: "No transactions available",
            zeroRecords: "No matching transactions found"
        }
    });
    
    // Initialize tooltips
    $('[data-bs-toggle="tooltip"]').tooltip();
});

// Set edit transaction form values
function setEditTransaction(id, type, amount, date, method, description) {
    document.getElementById('editTransactionId').value = id;
    document.getElementById('editTransactionType').value = type;
    document.getElementById('editTransactionAmount').value = amount;
    document.getElementById('editTransactionDate').value = date;
    document.getElementById('editPaymentMethod').value = method;
    document.getElementById('editTransactionDescription').value = description;
}

// Delete transaction with confirmation
function deleteTransaction(id, distributor_id) {
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'delete_transaction.php?id=' + id + '&distributor_id=' + distributor_id;
        }
    });
}

// Validate transaction form
function validateTransactionForm() {
    const amount = parseFloat($('#transactionAmount').val());
    if (isNaN(amount) || amount <= 0) {
        Swal.fire({
            icon: 'error',
            title: 'Invalid Amount',
            text: 'Please enter a valid positive amount',
        });
        return false;
    }
    return true;
}
</script>

<?php require_once "../includes/footer.php"; ?>