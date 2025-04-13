<?php
session_start();
require_once "../config/db_connect.php";
require_once "../includes/functions.php";
require_once "../includes/header.php";

// Get date range from request
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// Prepare query for distributor report - UPDATED TO USE distributor_transactions
$query = "SELECT 
            d.*,
            COUNT(DISTINCT dt.id) as total_transactions,
            COALESCE(SUM(CASE WHEN dt.transaction_type = 'purchase' THEN dt.amount ELSE 0 END), 0) as total_purchases,
            COALESCE(SUM(CASE WHEN dt.transaction_type = 'payment' THEN dt.amount ELSE 0 END), 0) as total_payments,
            MAX(dt.transaction_date) as last_transaction_date
          FROM distributors d
          LEFT JOIN distributor_transactions dt ON d.id = dt.distributor_id 
            AND dt.transaction_date BETWEEN ? AND ?
          GROUP BY d.id
          ORDER BY total_purchases DESC";

$stmt = mysqli_prepare($conn, $query);
if (!$stmt) {
    die("Error preparing statement: " . mysqli_error($conn));
}

mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
if (!mysqli_stmt_execute($stmt)) {
    die("Error executing statement: " . mysqli_stmt_error($stmt));
}

$result = mysqli_stmt_get_result($stmt);
if (!$result) {
    die("Error getting result: " . mysqli_error($conn));
}

// Calculate totals
$total_distributors = mysqli_num_rows($result);
$total_purchases = 0;
$total_payments = 0;
$total_transactions = 0;
$distributors = [];

while ($row = mysqli_fetch_assoc($result)) {
    $total_purchases += $row['total_purchases'];
    $total_payments += $row['total_payments'];
    $total_transactions += $row['total_transactions'];
    $distributors[] = $row;
}

$avg_purchase_value = $total_transactions > 0 ? $total_purchases / $total_transactions : 0;
?>

<div class="container-fluid">
    <!-- Report Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="mb-0">Distributor Report</h5>
        <div>
            <button onclick="window.print()" class="btn btn-secondary">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <!-- Date Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Start Date</label>
                    <input type="date" class="form-control" name="start_date" 
                           value="<?php echo htmlspecialchars($start_date); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">End Date</label>
                    <input type="date" class="form-control" name="end_date" 
                           value="<?php echo htmlspecialchars($end_date); ?>">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">Apply Filter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title text-muted">Total Distributors</h6>
                    <h4 class="mb-0"><?php echo $total_distributors; ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title text-muted">Total Transactions</h6>
                    <h4 class="mb-0"><?php echo $total_transactions; ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title text-muted">Total Purchases</h6>
                    <h4 class="mb-0"><?php echo format_currency($total_purchases); ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title text-muted">Avg Purchase Value</h6>
                    <h4 class="mb-0"><?php echo format_currency($avg_purchase_value); ?></h4>
                </div>
            </div>
        </div>
    </div>

    <!-- Distributors Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover datatable">
                    <thead>
                        <tr>
                            <th>Distributor</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th class="text-end">Transactions</th>
                            <th class="text-end">Total Purchases</th>
                            <th class="text-end">Total Payments</th>
                            <th class="text-end">Balance</th>
                            <th>Last Activity</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($distributors as $distributor): 
                            $balance = $distributor['total_purchases'] - $distributor['total_payments'];
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($distributor['name']); ?></td>
                            <td><?php echo htmlspecialchars($distributor['email'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($distributor['phone'] ?? '-'); ?></td>
                            <td class="text-end"><?php echo $distributor['total_transactions']; ?></td>
                            <td class="text-end"><?php echo format_currency($distributor['total_purchases'] ?? 0); ?></td>
                            <td class="text-end"><?php echo format_currency($distributor['total_payments'] ?? 0); ?></td>
                            <td class="text-end <?php echo $balance > 0 ? 'text-danger' : 'text-success'; ?>">
                                <?php echo format_currency($balance); ?>
                            </td>
                            <td><?php echo $distributor['last_transaction_date'] ? format_date($distributor['last_transaction_date']) : '-'; ?></td>
                            <td>
                                <a href="../distributors/view.php?id=<?php echo $distributor['id']; ?>" 
                                   class="btn btn-sm btn-info text-white">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('.datatable').DataTable({
        dom: 'Bfrtip',
        buttons: [
            'copy', 'csv', 'excel', 'pdf', 'print'
        ],
        order: [[4, 'desc']] // Sort by total purchases by default
    });
});
</script>

<?php
require_once "../includes/footer.php";
?>