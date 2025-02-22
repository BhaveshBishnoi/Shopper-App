<?php
session_start();
require_once "../config/db_connect.php";
require_once "../includes/functions.php";
require_once "../includes/header.php";

// Get date range from request
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// Prepare query for customer report
$query = "SELECT 
            c.*,
            COUNT(DISTINCT s.id) as total_orders,
            COALESCE(SUM(s.total_amount), 0) as total_spent,
            MAX(s.created_at) as last_order_date
          FROM customers c
          LEFT JOIN sales s ON c.id = s.customer_id 
            AND s.created_at BETWEEN ? AND ?
          GROUP BY c.id
          ORDER BY total_spent DESC";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Calculate totals
$total_customers = mysqli_num_rows($result);
$total_revenue = 0;
$total_orders = 0;
$customers = [];

while ($row = mysqli_fetch_assoc($result)) {
    $total_revenue += $row['total_spent'];
    $total_orders += $row['total_orders'];
    $customers[] = $row;
}

$avg_order_value = $total_orders > 0 ? $total_revenue / $total_orders : 0;
?>

<div class="container-fluid">
    <!-- Report Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="mb-0">Customer Report</h5>
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
                           value="<?php echo $start_date; ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">End Date</label>
                    <input type="date" class="form-control" name="end_date" 
                           value="<?php echo $end_date; ?>">
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
                    <h6 class="card-title text-muted">Total Customers</h6>
                    <h4 class="mb-0"><?php echo $total_customers; ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title text-muted">Total Orders</h6>
                    <h4 class="mb-0"><?php echo $total_orders; ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title text-muted">Total Revenue</h6>
                    <h4 class="mb-0"><?php echo format_currency($total_revenue); ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title text-muted">Average Order Value</h6>
                    <h4 class="mb-0"><?php echo format_currency($avg_order_value); ?></h4>
                </div>
            </div>
        </div>
    </div>

    <!-- Customers Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover datatable">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th class="text-end">Total Orders</th>
                            <th class="text-end">Total Spent</th>
                            <th>Last Order</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $customer): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($customer['name']); ?></td>
                            <td><?php echo htmlspecialchars($customer['email']); ?></td>
                            <td><?php echo htmlspecialchars($customer['phone']); ?></td>
                            <td class="text-end"><?php echo $customer['total_orders']; ?></td>
                            <td class="text-end"><?php echo format_currency($customer['total_spent'] ?? 0); ?></td>
                            <td><?php echo $customer['last_order_date'] ? format_date($customer['last_order_date']) : '-'; ?></td>
                            <td>
                                <a href="../customers/view.php?id=<?php echo $customer['id']; ?>" 
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
        ]
    });
});
</script>

<?php
require_once "../includes/footer.php";
?>
