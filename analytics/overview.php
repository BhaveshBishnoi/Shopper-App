<?php
session_start();
require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../config/db_connect.php";

// Get date range from request
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// Sales Overview
$sales_query = "SELECT 
    COUNT(DISTINCT s.id) as total_sales,
    SUM(s.total_amount) as total_revenue,
    SUM(si.gst_amount) as total_gst,
    AVG(s.total_amount) as average_sale
FROM sales s
LEFT JOIN sale_items si ON s.id = si.sale_id
WHERE s.created_at BETWEEN ? AND ?";

$stmt = mysqli_prepare($conn, $sales_query);
mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
mysqli_stmt_execute($stmt);
$sales_result = mysqli_stmt_get_result($stmt);
$sales_overview = mysqli_fetch_assoc($sales_result);

// Get top products
$products_query = "SELECT 
    p.name,
    p.sku,
    SUM(si.quantity) as total_quantity,
    SUM(si.quantity * si.price) as total_revenue,
    COUNT(DISTINCT s.id) as num_orders,
    MAX(s.created_at) as last_sold
FROM products p
LEFT JOIN sale_items si ON p.id = si.product_id
LEFT JOIN sales s ON si.sale_id = s.id AND s.created_at BETWEEN ? AND ?
GROUP BY p.id, p.name, p.sku
HAVING total_quantity > 0
ORDER BY total_revenue DESC
LIMIT 5";

$stmt = mysqli_prepare($conn, $products_query);
mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
mysqli_stmt_execute($stmt);
$top_products_result = mysqli_stmt_get_result($stmt);

// Get top customers with more details
$customers_query = "SELECT 
    c.name,
    c.email,
    COUNT(DISTINCT s.id) as total_orders,
    SUM(s.total_amount) as total_spent,
    MAX(s.created_at) as last_purchase,
    COUNT(DISTINCT si.product_id) as unique_products
FROM customers c
LEFT JOIN sales s ON c.id = s.customer_id AND s.created_at BETWEEN ? AND ?
LEFT JOIN sale_items si ON s.id = si.sale_id
GROUP BY c.id, c.name, c.email
HAVING total_orders > 0
ORDER BY total_spent DESC
LIMIT 5";

$stmt = mysqli_prepare($conn, $customers_query);
mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
mysqli_stmt_execute($stmt);
$top_customers_result = mysqli_stmt_get_result($stmt);

// Get daily sales trend with more metrics
$daily_sales_query = "SELECT 
    DATE(s.created_at) as sale_date,
    COUNT(DISTINCT s.id) as num_sales,
    SUM(s.total_amount) as daily_revenue,
    SUM(si.quantity) as items_sold,
    COUNT(DISTINCT s.customer_id) as unique_customers,
    AVG(s.total_amount) as average_order_value,
    SUM(si.gst_amount) as daily_gst,
    COUNT(DISTINCT si.product_id) as unique_products,
    SUM(CASE WHEN s.payment_status = 'paid' THEN s.total_amount ELSE 0 END) as paid_amount,
    SUM(CASE WHEN s.payment_status = 'pending' THEN s.total_amount ELSE 0 END) as pending_amount
FROM sales s
LEFT JOIN sale_items si ON s.id = si.sale_id
WHERE s.created_at BETWEEN ? AND ?
GROUP BY DATE(s.created_at)
ORDER BY sale_date";

$stmt = mysqli_prepare($conn, $daily_sales_query);
mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
mysqli_stmt_execute($stmt);
$daily_sales_result = mysqli_stmt_get_result($stmt);

$dates = [];
$daily_revenues = [];
$daily_counts = [];
$items_sold = [];
$unique_customers = [];
$avg_order_values = [];
$daily_gst = [];
$unique_products = [];
$paid_amounts = [];
$pending_amounts = [];

while ($row = mysqli_fetch_assoc($daily_sales_result)) {
    $dates[] = date('M d', strtotime($row['sale_date']));
    $daily_revenues[] = round($row['daily_revenue'], 2);
    $daily_counts[] = $row['num_sales'];
    $items_sold[] = $row['items_sold'];
    $unique_customers[] = $row['unique_customers'];
    $avg_order_values[] = round($row['average_order_value'], 2);
    $daily_gst[] = round($row['daily_gst'], 2);
    $unique_products[] = $row['unique_products'];
    $paid_amounts[] = round($row['paid_amount'], 2);
    $pending_amounts[] = round($row['pending_amount'], 2);
}

// Get payment status totals
$payment_status_query = "SELECT 
    SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END) as total_paid,
    SUM(CASE WHEN payment_status = 'pending' THEN total_amount ELSE 0 END) as total_pending
FROM sales 
WHERE created_at BETWEEN ? AND ?";

$stmt = mysqli_prepare($conn, $payment_status_query);
mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date);
mysqli_stmt_execute($stmt);
$payment_status_result = mysqli_stmt_get_result($stmt);
$payment_status = mysqli_fetch_assoc($payment_status_result);

$page_title = "Analytics Overview";
require_once __DIR__ . "/../includes/header.php";
?>

<div class="container-fluid py-4">
    <!-- Analytics Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="mb-0">Analytics Overview</h5>
        <div>
            <button onclick="window.print()" class="btn btn-secondary btn-sm">
                <i class="fas fa-print me-2"></i>Print Report
            </button>
        </div>
    </div>

    <!-- Date Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3 align-items-end" id="dateFilterForm">
                <div class="col-md-3">
                    <label class="form-label">Start Date</label>
                    <input type="date" class="form-control" name="start_date" id="start_date" value="<?php echo $start_date; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">End Date</label>
                    <input type="date" class="form-control" name="end_date" id="end_date" value="<?php echo $end_date; ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Quick Filters</label>
                    <select class="form-select" id="quickDateFilter">
                        <option value="">Custom Range</option>
                        <option value="today">Today</option>
                        <option value="yesterday">Yesterday</option>
                        <option value="last7">Last 7 Days</option>
                        <option value="last30">Last 30 Days</option>
                        <option value="thisMonth">This Month</option>
                        <option value="lastMonth">Last Month</option>
                    </select>
                </div>
                <div class="col-md-2">
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
                                <p class="text-sm mb-0 text-capitalize font-weight-bold">Total Sales</p>
                                <h5 class="font-weight-bolder mb-0">
                                    <?php echo number_format($sales_overview['total_sales']); ?>
                                </h5>
                            </div>
                        </div>
                        <div class="col-4 text-end">
                            <div class="icon icon-shape bg-gradient-primary shadow text-center border-radius-md">
                                <i class="fas fa-shopping-cart text-lg opacity-10" aria-hidden="true"></i>
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
                                <p class="text-sm mb-0 text-capitalize font-weight-bold">Total Revenue</p>
                                <h5 class="font-weight-bolder mb-0">
                                    <?php echo format_currency($sales_overview['total_revenue']); ?>
                                </h5>
                            </div>
                        </div>
                        <div class="col-4 text-end">
                            <div class="icon icon-shape bg-gradient-success shadow text-center border-radius-md">
                                <i class="fas fa-dollar-sign text-lg opacity-10" aria-hidden="true"></i>
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
                                <p class="text-sm mb-0 text-capitalize font-weight-bold">Total GST</p>
                                <h5 class="font-weight-bolder mb-0">
                                    <?php echo format_currency($sales_overview['total_gst']); ?>
                                </h5>
                            </div>
                        </div>
                        <div class="col-4 text-end">
                            <div class="icon icon-shape bg-gradient-warning shadow text-center border-radius-md">
                                <i class="fas fa-percentage text-lg opacity-10" aria-hidden="true"></i>
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
                                <p class="text-sm mb-0 text-capitalize font-weight-bold">Average Sale</p>
                                <h5 class="font-weight-bolder mb-0">
                                    <?php echo format_currency($sales_overview['average_sale']); ?>
                                </h5>
                            </div>
                        </div>
                        <div class="col-4 text-end">
                            <div class="icon icon-shape bg-gradient-info shadow text-center border-radius-md">
                                <i class="fas fa-chart-line text-lg opacity-10" aria-hidden="true"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row mb-4">
        <div class="col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header pb-0">
                    <h6 class="mb-0">Revenue Distribution</h6>
                </div>
                <div class="card-body p-3">
                    <div class="chart">
                        <canvas id="revenueDistributionPie" class="chart-canvas" height="260"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header pb-0">
                    <h6 class="mb-0">Payment Status</h6>
                </div>
                <div class="card-body p-3">
                    <div class="chart">
                        <canvas id="paymentStatusPie" class="chart-canvas" height="260"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 mb-4">
            <div class="card h-100">
                <div class="card-header pb-0">
                    <h6 class="mb-0">Sales Metrics</h6>
                </div>
                <div class="card-body p-3">
                    <div class="chart">
                        <canvas id="salesMetricsPie" class="chart-canvas" height="260"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tables Row -->
    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header pb-0">
                    <h6 class="mb-3 text-capitalize text-center h4">Top Selling Products</h6>
                </div>
                <div class="card-body px-0 pt-0 pb-2">
                    <div class="table-responsive p-0">
                        <table class="table align-items-center mb-0">
                            <thead>
                                <tr>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Product</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">SKU</th>
                                    <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Quantity</th>
                                    <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($product = mysqli_fetch_assoc($top_products_result)): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex px-3 py-1">
                                            <div class="d-flex flex-column justify-content-center">
                                                <h6 class="mb-0 text-sm"><?php echo htmlspecialchars($product['name']); ?></h6>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <p class="text-sm font-weight-bold mb-0"><?php echo htmlspecialchars($product['sku']); ?></p>
                                    </td>
                                    <td class="align-middle text-center text-sm">
                                        <span class="text-xs font-weight-bold"><?php echo number_format($product['total_quantity']); ?></span>
                                    </td>
                                    <td class="align-middle text-center text-sm">
                                        <span class="text-xs font-weight-bold"><?php echo format_currency($product['total_revenue']); ?></span>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header pb-0">
                    <h6 class="mb-3 text-capitalize text-center h4">Top Customers</h6>
                </div>
                <div class="card-body px-0 pt-0 pb-2">
                    <div class="table-responsive p-0">
                        <table class="table align-items-center mb-0">
                            <thead>
                                <tr>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Customer</th>
                                    <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Orders</th>
                                    <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Total Spent</th>
                                    <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Last Purchase</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($customer = mysqli_fetch_assoc($top_customers_result)): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex px-3 py-1">
                                            <div class="d-flex flex-column justify-content-center">
                                                <h6 class="mb-0 text-sm"><?php echo htmlspecialchars($customer['name']); ?></h6>
                                                <p class="text-xs text-secondary mb-0"><?php echo htmlspecialchars($customer['email']); ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="align-middle text-center text-sm">
                                        <span class="text-xs font-weight-bold"><?php echo number_format($customer['total_orders']); ?></span>
                                    </td>
                                    <td class="align-middle text-center text-sm">
                                        <span class="text-xs font-weight-bold"><?php echo format_currency($customer['total_spent']); ?></span>
                                    </td>
                                    <td class="align-middle text-center text-sm">
                                        <span class="text-xs font-weight-bold"><?php echo date('M d, Y', strtotime($customer['last_purchase'])); ?></span>
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

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Register Chart.js plugins
    Chart.register(ChartDataLabels);

    // Format currency
    function formatCurrency(value) {
        return '₹' + value.toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    // Calculate totals for pie charts
    const totalRevenue = <?php echo array_sum($daily_revenues); ?>;
    const totalGST = <?php echo array_sum($daily_gst); ?>;
    const totalNetRevenue = totalRevenue - totalGST;
    const totalItems = <?php echo array_sum($items_sold); ?>;
    const totalProducts = <?php echo array_sum($unique_products); ?>;
    const totalSales = <?php echo array_sum($daily_counts); ?>;

    // Common pie chart options
    const pieChartOptions = {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '60%',
        plugins: {
            datalabels: {
                color: '#fff',
                font: { size: 11, weight: 'bold' },
                formatter: (value, ctx) => {
                    const dataset = ctx.chart.data.datasets[0].data;
                    const total = dataset.reduce((acc, data) => acc + data, 0);
                    const percentage = ((value / total) * 100).toFixed(1);
                    return percentage + '%';
                }
            },
            legend: {
                position: 'bottom',
                labels: {
                    padding: 15,
                    usePointStyle: true,
                    pointStyle: 'circle',
                    font: { size: 11 }
                }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const value = context.raw;
                        const total = context.dataset.data.reduce((acc, data) => acc + data, 0);
                        const percentage = ((value / total) * 100).toFixed(1);
                        return `${context.label}: ${formatCurrency(value)} (${percentage}%)`;
                    }
                }
            }
        }
    };

    // Revenue Distribution Pie Chart
    new Chart(document.getElementById('revenueDistributionPie').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: ['Net Revenue', 'GST'],
            datasets: [{
                data: [totalNetRevenue, totalGST],
                backgroundColor: [
                    'rgba(66, 135, 245, 0.8)',
                    'rgba(243, 156, 18, 0.8)'
                ],
                borderColor: [
                    'rgba(66, 135, 245, 1)',
                    'rgba(243, 156, 18, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            ...pieChartOptions,
            plugins: {
                ...pieChartOptions.plugins,
                subtitle: {
                    display: true,
                    text: `Total Revenue: ${formatCurrency(totalRevenue)}`,
                    position: 'bottom',
                    padding: { top: 10 }
                }
            }
        }
    });

    // Payment Status Pie Chart
    new Chart(document.getElementById('paymentStatusPie').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: ['Paid', 'Pending'],
            datasets: [{
                data: [
                    <?php echo $payment_status['total_paid']; ?>,
                    <?php echo $payment_status['total_pending']; ?>
                ],
                backgroundColor: [
                    'rgba(45, 206, 137, 0.8)',
                    'rgba(251, 99, 64, 0.8)'
                ],
                borderColor: [
                    'rgba(45, 206, 137, 1)',
                    'rgba(251, 99, 64, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: pieChartOptions
    });

    // Sales Metrics Pie Chart
    new Chart(document.getElementById('salesMetricsPie').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: ['Total Items Sold', 'Unique Products', 'Total Orders'],
            datasets: [{
                data: [totalItems, totalProducts, totalSales],
                backgroundColor: [
                    'rgba(251, 99, 64, 0.8)',
                    'rgba(52, 152, 219, 0.8)',
                    'rgba(45, 206, 137, 0.8)'
                ],
                borderColor: [
                    'rgba(251, 99, 64, 1)',
                    'rgba(52, 152, 219, 1)',
                    'rgba(45, 206, 137, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            ...pieChartOptions,
            plugins: {
                ...pieChartOptions.plugins,
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const value = context.raw;
                            const total = context.dataset.data.reduce((acc, data) => acc + data, 0);
                            const percentage = ((value / total) * 100).toFixed(1);
                            return `${context.label}: ${value.toLocaleString()} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
