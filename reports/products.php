<?php
session_start();
require_once "../includes/functions.php";
require_once "../config/db_connect.php";

// Get date range from request
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

try {
    // Prepare query for product report
    $query = "SELECT 
                p.*,
                COALESCE(SUM(si.quantity), 0) as total_sold,
                COALESCE(SUM(si.total_amount), 0) as total_revenue,
                COUNT(DISTINCT s.id) as times_sold,
                COALESCE(AVG(si.price), p.price) as avg_selling_price
              FROM products p
              LEFT JOIN sale_items si ON p.id = si.product_id
              LEFT JOIN sales s ON si.sale_id = s.id AND s.created_at BETWEEN ? AND ?
              GROUP BY p.id
              ORDER BY total_revenue DESC";

    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        throw new Exception("Error preparing statement: " . mysqli_error($conn));
    }

    if (!mysqli_stmt_bind_param($stmt, "ss", $start_date, $end_date)) {
        throw new Exception("Error binding parameters: " . mysqli_error($conn));
    }

    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Error executing query: " . mysqli_error($conn));
    }

    $result = mysqli_stmt_get_result($stmt);
    if (!$result) {
        throw new Exception("Error getting result: " . mysqli_error($conn));
    }

    // Calculate totals
    $total_products = mysqli_num_rows($result);
    $total_revenue = 0;
    $total_units_sold = 0;
    $products = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $total_revenue += $row['total_revenue'];
        $total_units_sold += $row['total_sold'];
        $products[] = $row;
    }

    $page_title = "Product Report";
    require_once "../includes/header.php";
    ?>

    <div class="container-fluid py-4">
        <!-- Date Range Filter -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label for="start_date" class="form-label">Start Date</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" 
                               value="<?php echo $start_date; ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="end_date" class="form-label">End Date</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" 
                               value="<?php echo $end_date; ?>" required>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">Apply Filter</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-title text-muted">Total Products</h6>
                        <h4 class="mb-0"><?php echo $total_products; ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-title text-muted">Total Revenue</h6>
                        <h4 class="mb-0"><?php echo format_currency($total_revenue); ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-title text-muted">Total Units Sold</h6>
                        <h4 class="mb-0"><?php echo $total_units_sold; ?></h4>
                    </div>
                </div>
            </div>
        </div>

        <!-- Products Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Product Performance</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover datatable">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>SKU</th>
                                <th>Category</th>
                                <th class="text-end">Price</th>
                                <th class="text-end">Stock</th>
                                <th class="text-end">Units Sold</th>
                                <th class="text-end">Revenue</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                            <tr>
                                <td>
                                    <a href="../products/edit.php?id=<?php echo $product['id']; ?>">
                                        <?php echo htmlspecialchars($product['name']); ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars($product['sku']); ?></td>
                                <td><?php echo htmlspecialchars(isset($product['category']) ? $product['category'] : 'General'); ?></td>
                                <td class="text-end"><?php echo format_currency($product['price']); ?></td>
                                <td class="text-end"><?php echo $product['stock_quantity']; ?></td>
                                <td class="text-end"><?php echo $product['total_sold']; ?></td>
                                <td class="text-end"><?php echo format_currency($product['total_revenue']); ?></td>
                                <td class="text-center">
                                    <?php 
                                    $threshold = isset($product['low_stock_threshold']) ? $product['low_stock_threshold'] : 10;
                                    if ($product['stock_quantity'] <= $threshold && $product['stock_quantity'] > 0): 
                                    ?>
                                        <span class="badge bg-warning">Low Stock</span>
                                    <?php elseif ($product['stock_quantity'] == 0): ?>
                                        <span class="badge bg-danger">Out of Stock</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">In Stock</span>
                                    <?php endif; ?>
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
            buttons: ['copy', 'csv', 'excel', 'pdf', 'print'],
            order: [[6, 'desc']], // Sort by revenue by default
            pageLength: 25
        });
    });
    </script>

    <?php
    require_once "../includes/footer.php";

} catch (Exception $e) {
    handleError($e->getMessage());
}
?>
