<?php
session_start();
require_once "../config/db_connect.php";
require_once "../includes/functions.php";
require_once "../includes/header.php";

// Get inventory status filter
$status = isset($_GET['status']) ? $_GET['status'] : 'all';

// Prepare query for inventory report
$query = "SELECT 
            p.*,
            COALESCE(SUM(si.quantity), 0) as total_sold,
            (p.stock_quantity * p.price) as stock_value
          FROM products p
          LEFT JOIN sale_items si ON p.id = si.product_id
          GROUP BY p.id, p.name, p.sku, p.stock_quantity, p.price
          HAVING 1=1 ";

if ($status === 'low') {
    $query .= " AND p.stock_quantity <= COALESCE(p.low_stock_threshold, 10)";
} elseif ($status === 'out') {
    $query .= " AND p.stock_quantity = 0";
} elseif ($status === 'overstock') {
    $query .= " AND p.stock_quantity > (COALESCE(p.low_stock_threshold, 10) * 2)";
}

$query .= " ORDER BY p.stock_quantity ASC";

$result = mysqli_query($conn, $query);

// Calculate totals
$total_products = 0;
$total_stock_value = 0;
$low_stock_items = 0;
$out_of_stock_items = 0;

$inventory_data = [];
while ($product = mysqli_fetch_assoc($result)) {
    $total_products++;
    $total_stock_value += $product['stock_value'];
    
    if ($product['stock_quantity'] <= 0) {
        $out_of_stock_items++;
    } elseif ($product['stock_quantity'] <= (isset($product['low_stock_threshold']) ? $product['low_stock_threshold'] : 10)) {
        $low_stock_items++;
    }
    
    $inventory_data[] = $product;
}
?>

<div class="container-fluid py-4">
    <!-- Report Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="mb-0">Inventory Report</h5>
        <div>
            <button onclick="window.print()" class="btn btn-secondary">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <!-- Status Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Inventory Status</label>
                    <select class="form-select" name="status" onchange="this.form.submit()">
                        <option value="all" <?php echo $status == 'all' ? 'selected' : ''; ?>>All Products</option>
                        <option value="low" <?php echo $status == 'low' ? 'selected' : ''; ?>>Low Stock</option>
                        <option value="out" <?php echo $status == 'out' ? 'selected' : ''; ?>>Out of Stock</option>
                        <option value="overstock" <?php echo $status == 'overstock' ? 'selected' : ''; ?>>Overstock</option>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6 class="card-title">Total Products</h6>
                    <h3 class="mb-0"><?php echo $total_products; ?></h3>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="card-title">Stock Value</h6>
                    <h3 class="mb-0"><?php echo format_currency($total_stock_value); ?></h3>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h6 class="card-title">Low Stock Items</h6>
                    <h3 class="mb-0"><?php echo $low_stock_items; ?></h3>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h6 class="card-title">Out of Stock</h6>
                    <h3 class="mb-0"><?php echo $out_of_stock_items; ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Inventory Table -->
    <div class="table-responsive">
        <table class="table table-hover datatable">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>SKU</th>
                    <th class="text-end">Price</th>
                    <th class="text-end">Stock</th>
                    <th class="text-end">Low Stock Threshold</th>
                    <th class="text-end">Stock Value</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($inventory_data as $product): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                        <td><?php echo htmlspecialchars($product['sku']); ?></td>
                        <td class="text-end"><?php echo format_currency($product['price']); ?></td>
                        <td class="text-end"><?php echo $product['stock_quantity']; ?></td>
                        <td class="text-end"><?php echo isset($product['low_stock_threshold']) ? $product['low_stock_threshold'] : 10; ?></td>
                        <td class="text-end"><?php echo format_currency($product['stock_value']); ?></td>
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
                        <td>
                            <a href="../products/edit.php?id=<?php echo $product['id']; ?>" 
                               class="btn btn-sm btn-primary">
                                <i class="fas fa-edit"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
$(document).ready(function() {
    $('.datatable').DataTable({
        dom: 'Bfrtip',
        buttons: [
            'copy', 'csv', 'pdf', 'print'
        ]
    });
});
</script>

<?php require_once "../includes/footer.php"; ?>
