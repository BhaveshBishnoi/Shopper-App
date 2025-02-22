<?php
require_once "includes/functions.php";
require_once "config/db_connect.php";
require_once "includes/header.php";

// Get dashboard stats
$stats = [
    'revenue' => 0,
    'products' => 0,
    'sales' => 0,
    'low_stock' => 0
];

try {
    // Get total revenue
    $sql = "SELECT COALESCE(SUM(total_amount), 0) as total_revenue FROM sales";
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        throw new Exception("Error getting revenue: " . mysqli_error($conn));
    }
    if ($row = mysqli_fetch_assoc($result)) {
        $stats['revenue'] = $row['total_revenue'];
    }

    // Get total products
    $sql = "SELECT COUNT(*) as total_products FROM products";
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        throw new Exception("Error getting products count: " . mysqli_error($conn));
    }
    if ($row = mysqli_fetch_assoc($result)) {
        $stats['products'] = $row['total_products'];
    }

    // Get total sales
    $sql = "SELECT COUNT(*) as total_sales FROM sales";
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        throw new Exception("Error getting sales count: " . mysqli_error($conn));
    }
    if ($row = mysqli_fetch_assoc($result)) {
        $stats['sales'] = $row['total_sales'];
    }

    // Get low stock items count
    $sql = "SELECT COUNT(*) as low_stock FROM products WHERE stock_quantity <= low_stock_threshold";
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        throw new Exception("Error getting low stock count: " . mysqli_error($conn));
    }
    if ($row = mysqli_fetch_assoc($result)) {
        $stats['low_stock'] = $row['low_stock'];
    }

    // Get recent sales with error handling
    $sql = "SELECT s.*, c.name as customer_name, 
            (SELECT GROUP_CONCAT(p.name SEPARATOR ', ') 
             FROM sale_items si 
             JOIN products p ON si.product_id = p.id 
             WHERE si.sale_id = s.id) as products
            FROM sales s
            LEFT JOIN customers c ON s.customer_id = c.id
            ORDER BY s.created_at DESC
            LIMIT 5";
    $recent_sales_result = mysqli_query($conn, $sql);
    if (!$recent_sales_result) {
        throw new Exception("Error getting recent sales: " . mysqli_error($conn));
    }

    // Get low stock products
    $sql = "SELECT p.*, 
            COALESCE(SUM(si.quantity), 0) as total_sold
            FROM products p
            LEFT JOIN sale_items si ON p.id = si.product_id
            WHERE p.stock_quantity <= p.low_stock_threshold
            GROUP BY p.id
            ORDER BY p.stock_quantity ASC
            LIMIT 5";
    $low_stock_result = mysqli_query($conn, $sql);
    if (!$low_stock_result) {
        throw new Exception("Error getting low stock products: " . mysqli_error($conn));
    }

} catch (Exception $e) {
    handleError($e->getMessage());
}

?>

<!-- Stats Cards -->
<div class="row g-4 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="card bg-primary text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0">Total Revenue</h6>
                        <h3 class="mb-0"><?php echo format_currency($stats['revenue']); ?></h3>
                    </div>
                    <div class="rounded-circle bg-white bg-opacity-25 p-3">
                        <i class="fas fa-indian-rupee-sign fa-2x text-white"></i>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-primary border-0">
                <small><a href="reports/sales.php" class="text-white text-decoration-none">View Report <i class="fas fa-arrow-right ms-1"></i></a></small>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card bg-success text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0">Total Products</h6>
                        <h3 class="mb-0"><?php echo $stats['products']; ?></h3>
                    </div>
                    <div class="rounded-circle bg-white bg-opacity-25 p-3">
                        <i class="fas fa-box fa-2x text-white"></i>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-success border-0">
                <small><a href="products/index.php" class="text-white text-decoration-none">Manage Products <i class="fas fa-arrow-right ms-1"></i></a></small>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card bg-info text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0">Total Sales</h6>
                        <h3 class="mb-0"><?php echo $stats['sales']; ?></h3>
                    </div>
                    <div class="rounded-circle bg-white bg-opacity-25 p-3">
                        <i class="fas fa-shopping-cart fa-2x text-white"></i>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-info border-0">
                <small><a href="sales/index.php" class="text-white text-decoration-none">View Sales <i class="fas fa-arrow-right ms-1"></i></a></small>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card bg-warning text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0">Low Stock Items</h6>
                        <h3 class="mb-0"><?php echo $stats['low_stock']; ?></h3>
                    </div>
                    <div class="rounded-circle bg-white bg-opacity-25 p-3">
                        <i class="fas fa-exclamation-triangle fa-2x text-white"></i>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-warning border-0">
                <small><a href="#lowStockTable" class="text-white text-decoration-none">View Details <i class="fas fa-arrow-right ms-1"></i></a></small>
            </div>
        </div>
    </div>
</div>

<!-- Recent Sales & Low Stock -->
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Recent Sales</h5>
                <a href="sales/index.php" class="btn btn-primary btn-sm">View All</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Invoice</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recent_sales_result && mysqli_num_rows($recent_sales_result) > 0): ?>
                                <?php $index = 0; ?>
                                <?php while ($sale = mysqli_fetch_assoc($recent_sales_result)): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><?php echo isset($sale['invoice_number']) ? htmlspecialchars($sale['invoice_number']) : 'N/A'; ?></td>
                                        <td><?php echo htmlspecialchars($sale['customer_name'] ?? 'Walk-in Customer'); ?></td>
                                        <td class="text-end"><?php echo format_currency($sale['total_amount']); ?></td>
                                        <td><?php echo format_date($sale['created_at']); ?></td>
                                        <td>
                                            <?php if ($sale['payment_status'] === 'paid'): ?>
                                                <span class="badge bg-success">Paid</span>
                                            <?php elseif ($sale['payment_status'] === 'partial'): ?>
                                                <span class="badge bg-warning">Partial</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Unpaid</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php $index++; ?>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center">No recent sales found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Low Stock Alert</h5>
                <a href="products/index.php?filter=low_stock" class="btn btn-primary btn-sm">View All</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="lowStockTable">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Stock</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($low_stock_result && mysqli_num_rows($low_stock_result) > 0): ?>
                                <?php while ($product = mysqli_fetch_assoc($low_stock_result)): ?>
                                    <tr>
                                        <td>
                                            <a href="products/edit.php?id=<?php echo $product['id']; ?>" class="text-decoration-none">
                                                <?php echo $product['name']; ?>
                                            </a>
                                        </td>
                                        <td><?php echo $product['stock_quantity']; ?></td>
                                        <td>
                                            <?php
                                            $stock_percentage = ($product['stock_quantity'] / $product['low_stock_threshold']) * 100;
                                            $status_class = $stock_percentage <= 25 ? 'danger' : ($stock_percentage <= 50 ? 'warning' : 'info');
                                            ?>
                                            <div class="progress" style="height: 5px;">
                                                <div class="progress-bar bg-<?php echo $status_class; ?>" 
                                                     role="progressbar" 
                                                     style="width: <?php echo $stock_percentage; ?>%"
                                                     aria-valuenow="<?php echo $stock_percentage; ?>" 
                                                     aria-valuemin="0" 
                                                     aria-valuemax="100">
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="text-center">No low stock items</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once "includes/footer.php"; ?>
