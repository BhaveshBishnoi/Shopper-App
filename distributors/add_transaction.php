<?php
require_once "../config/db_connect.php";
require_once "../includes/header.php";
require_once "../includes/functions.php";

// Get distributor ID from query string
$distributor_id = isset($_GET['distributor_id']) ? (int)$_GET['distributor_id'] : 0;

// Get distributor details
$distributor = null;
if ($distributor_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM distributors WHERE id = ?");
    $stmt->bind_param("i", $distributor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $distributor = $result->fetch_assoc();
    
    if (!$distributor) {
        $_SESSION['error'] = "Distributor not found";
        header("Location: distributors.php");
        exit();
    }
}
?>

<div class="container-fluid py-4">
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <?= $distributor ? "Add Transaction for ".htmlspecialchars($distributor['name']) : "Add New Transaction" ?>
            </h5>
        </div>
        <div class="card-body">
            <form method="POST" action="transactions.php" onsubmit="return validateTransactionForm()">
                <input type="hidden" name="distributor_id" value="<?= $distributor_id ?>">
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Transaction Type*</label>
                        <select class="form-select" name="transaction_type" required>
                            <option value="payment">Payment (Amount Paid)</option>
                            <option value="purchase">Purchase (Goods Received)</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Amount (₹)*</label>
                        <input type="number" step="0.01" min="0.01" class="form-control" 
                               name="amount" id="transactionAmount" required>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Date*</label>
                        <input type="date" class="form-control" name="transaction_date" 
                               value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Payment Method*</label>
                        <select class="form-select" name="payment_method" required>
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="cheque">Cheque</option>
                            <option value="upi">UPI</option>
                        </select>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea class="form-control" name="description" rows="3"></textarea>
                </div>
                
                <div class="d-flex justify-content-between">
                    <a href="<?= $distributor_id ? 'transactions.php?distributor_id='.$distributor_id : 'distributors.php' ?>" 
                       class="btn btn-secondary">
                        Cancel
                    </a>
                    <button type="submit" name="add_transaction" class="btn btn-primary">
                        Save Transaction
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function validateTransactionForm() {
    const amount = parseFloat(document.getElementById('transactionAmount').value);
    if (isNaN(amount) || amount <= 0) {
        alert('Please enter a valid positive amount');
        return false;
    }
    return true;
}
</script>

<?php require_once "../includes/footer.php"; ?>