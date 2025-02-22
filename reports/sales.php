<?php
session_start();
require_once "../config/db_connect.php";
require_once "../includes/functions.php";

$page_title = "Sales Report";
require_once "../includes/header.php";

// Get date range from request
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// Enhanced query for sales report with more details
$query = "SELECT 
            s.*,
            c.name as customer_name,
            c.email as customer_email,
            COUNT(si.id) as total_items,
            COUNT(DISTINCT si.product_id) as unique_products,
            GROUP_CONCAT(
                CONCAT_WS(' ', 
                    p.name,
                    CONCAT('(', 
                        CAST(si.quantity AS CHAR), 
                        ' × ₹', 
                        CAST(si.price AS CHAR), 
                        ')'
                    )
                )
                SEPARATOR ', '
            ) as products
          FROM sales s
          LEFT JOIN customers c ON s.customer_id = c.id
          LEFT JOIN sale_items si ON s.id = si.sale_id
          LEFT JOIN products p ON si.product_id = p.id
          WHERE s.created_at BETWEEN ? AND ?
          GROUP BY s.id
          ORDER BY s.created_at DESC";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Calculate totals with more metrics
$total_sales = 0;
$total_revenue = 0;
$total_gst = 0;
$total_items = 0;
$total_unique_products = 0;
$payment_methods = [];
$sales = [];

while ($row = mysqli_fetch_assoc($result)) {
    $total_sales++;
    $total_revenue += $row['total_amount'];
    $total_gst += $row['gst_amount'];
    $total_items += $row['total_items'];
    $total_unique_products += $row['unique_products'];
    $payment_methods[$row['payment_method']][] = $row['total_amount'];
    $sales[] = $row;
}

$avg_sale_value = $total_sales > 0 ? $total_revenue / $total_sales : 0;
$avg_items_per_sale = $total_sales > 0 ? $total_items / $total_sales : 0;
?>

<div class="container-fluid py-4">
    <!-- Report Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h5 class="mb-1">Sales Report</h5>
            <p class="text-sm mb-0 text-muted">
                Detailed analysis from <?php echo date('M d, Y', strtotime($start_date)); ?> 
                to <?php echo date('M d, Y', strtotime($end_date)); ?>
            </p>
        </div>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-secondary">
                <i class="fas fa-print me-2"></i>Print Report
            </button>
            <a href="../sales/export.php" class="btn btn-primary">
                <i class="fas fa-download me-2"></i>Export Data
            </a>
        </div>
    </div>

    <!-- Date Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small text-muted">Start Date</label>
                    <input type="date" class="form-control" name="start_date" 
                           value="<?php echo $start_date; ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-muted">End Date</label>
                    <input type="date" class="form-control" name="end_date" 
                           value="<?php echo $end_date; ?>">
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-2"></i>Apply Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-sm-6 mb-4">
            <div class="card">
                <div class="card-body p-3">
                    <div class="row">
                        <div class="col-8">
                            <div class="numbers">
                                <p class="text-sm mb-0 text-capitalize font-weight-bold text-muted">Total Sales</p>
                                <h5 class="mb-0 font-weight-bold">
                                    <?php echo number_format($total_sales); ?>
                                    <small class="text-sm text-muted">orders</small>
                                </h5>
                            </div>
                        </div>
                        <div class="col-4 text-end">
                            <div class="icon icon-shape bg-gradient-primary shadow text-center border-radius-md">
                                <i class="fas fa-shopping-cart opacity-10"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6 mb-4">
            <div class="card">
                <div class="card-body p-3">
                    <div class="row">
                        <div class="col-8">
                            <div class="numbers">
                                <p class="text-sm mb-0 text-capitalize font-weight-bold text-muted">Total Revenue</p>
                                <h5 class="mb-0 font-weight-bold">
                                    ₹<?php echo number_format($total_revenue, 2); ?>
                                </h5>
                            </div>
                        </div>
                        <div class="col-4 text-end">
                            <div class="icon icon-shape bg-gradient-success shadow text-center border-radius-md">
                                <i class="fas fa-dollar-sign opacity-10"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6 mb-4">
            <div class="card">
                <div class="card-body p-3">
                    <div class="row">
                        <div class="col-8">
                            <div class="numbers">
                                <p class="text-sm mb-0 text-capitalize font-weight-bold text-muted">Average Order</p>
                                <h5 class="mb-0 font-weight-bold">
                                    ₹<?php echo number_format($avg_sale_value, 2); ?>
                                    <small class="text-sm text-muted">/order</small>
                                </h5>
                            </div>
                        </div>
                        <div class="col-4 text-end">
                            <div class="icon icon-shape bg-gradient-warning shadow text-center border-radius-md">
                                <i class="fas fa-chart-line opacity-10"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6 mb-4">
            <div class="card">
                <div class="card-body p-3">
                    <div class="row">
                        <div class="col-8">
                            <div class="numbers">
                                <p class="text-sm mb-0 text-capitalize font-weight-bold text-muted">Items Sold</p>
                                <h5 class="mb-0 font-weight-bold">
                                    <?php echo number_format($total_items); ?>
                                    <small class="text-sm text-muted">items</small>
                                </h5>
                            </div>
                        </div>
                        <div class="col-4 text-end">
                            <div class="icon icon-shape bg-gradient-info shadow text-center border-radius-md">
                                <i class="fas fa-box opacity-10"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sales Table -->
    <div class="card">
        <div class="card-header pb-0">
            <h6 class="mb-0">Sales Details</h6>
            <p class="text-sm mb-0 text-muted">
                Comprehensive list of all sales transactions
            </p>
        </div>
        <div class="card-body px-0 pt-0 pb-2">
            <div class="table-responsive p-0">
                <table class="table align-items-center mb-0 datatable">
                    <thead>
                        <tr>
                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Invoice</th>
                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Customer</th>
                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Items</th>
                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 text-center">Amount</th>
                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 text-center">GST</th>
                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 text-center">Total</th>
                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 text-center">Status</th>
                            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sales as $sale): ?>
                        <tr>
                            <td>
                                <div class="d-flex px-3 py-1">
                                    <div class="d-flex flex-column justify-content-center">
                                        <h6 class="mb-0 text-sm">#<?php echo $sale['id']; ?></h6>
                                        <p class="text-xs text-secondary mb-0">
                                            <?php echo date('M d, Y', strtotime($sale['created_at'])); ?>
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex flex-column">
                                    <h6 class="mb-0 text-sm"><?php echo htmlspecialchars($sale['customer_name']); ?></h6>
                                    <p class="text-xs text-secondary mb-0"><?php echo htmlspecialchars($sale['customer_email']); ?></p>
                                </div>
                            </td>
                            <td>
                                <p class="text-sm mb-0">
                                    <span class="d-inline-block text-truncate" style="max-width: 200px;" 
                                          title="<?php echo htmlspecialchars($sale['products']); ?>">
                                        <?php echo htmlspecialchars($sale['products']); ?>
                                    </span>
                                </p>
                                <p class="text-xs text-secondary mb-0">
                                    <?php echo $sale['total_items']; ?> items
                                </p>
                            </td>
                            <td class="text-center">
                                <p class="text-sm mb-0 font-weight-bold">
                                    ₹<?php echo number_format($sale['total_amount'] - $sale['gst_amount'], 2); ?>
                                </p>
                            </td>
                            <td class="text-center">
                                <p class="text-sm mb-0">
                                    ₹<?php echo number_format($sale['gst_amount'], 2); ?>
                                </p>
                            </td>
                            <td class="text-center">
                                <p class="text-sm mb-0 font-weight-bold">
                                    ₹<?php echo number_format($sale['total_amount'], 2); ?>
                                </p>
                            </td>
                            <td class="text-center">
                                <span class="badge badge-sm bg-gradient-<?php echo $sale['payment_status'] == 'paid' ? 'success' : 'warning'; ?>">
                                    <?php echo ucfirst($sale['payment_status']); ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <a href="../sales/view.php?id=<?php echo $sale['id']; ?>" 
                                   class="btn btn-link text-dark px-3 mb-0">
                                    <i class="fas fa-eye text-dark me-2"></i>View
                                </a>
                                <a href="../sales/view.php?id=<?php echo $sale['id']; ?>&print=1" 
                                   class="btn btn-link text-dark px-3 mb-0" target="_blank">
                                    <i class="fas fa-print text-dark me-2"></i>Print
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
    // Initialize DataTable with enhanced features
    $('.datatable').DataTable({
        dom: '<"d-flex justify-content-between align-items-center mb-3"Bf>rt<"d-flex justify-content-between align-items-center"lip>',
        buttons: [
            {
                extend: 'collection',
                text: '<i class="fas fa-download me-2"></i>Export',
                buttons: ['copy', 'csv', 'excel', 'pdf', 'print']
            }
        ],
        pageLength: 10,
        order: [[0, 'desc']],
        language: {
            search: "",
            searchPlaceholder: "Search sales..."
        }
    });
});
</script>

<?php require_once "../includes/footer.php"; ?>
