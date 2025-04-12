<?php
require_once "../config/db_connect.php";
require_once "../includes/header.php";
require_once "../includes/functions.php";

// Handle search filters
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$stock_status = isset($_GET['stock_status']) ? sanitize_input($_GET['stock_status']) : '';

// Get summary statistics
$stats_query = "SELECT 
    COUNT(*) as total_products,
    SUM(stock_quantity) as total_stock,
    SUM(CASE WHEN stock_quantity <= low_stock_threshold THEN 1 ELSE 0 END) as low_stock_items,
    SUM(CASE WHEN stock_quantity = 0 THEN 1 ELSE 0 END) as out_of_stock_items
    FROM products";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Build query
$query = "SELECT p.*, 
          (SELECT COUNT(*) FROM inventory WHERE product_id = p.id) as inventory_entries
          FROM products p WHERE 1=1";
if (!empty($search)) {
    $search = mysqli_real_escape_string($conn, $search);
    $query .= " AND (p.name LIKE '%$search%' OR p.sku LIKE '%$search%')";
}
if (!empty($stock_status)) {
    if ($stock_status === 'low') {
        $query .= " AND p.stock_quantity <= p.low_stock_threshold AND p.stock_quantity > 0";
    } elseif ($stock_status === 'out') {
        $query .= " AND p.stock_quantity = 0";
    }
}
$query .= " ORDER BY p.name ASC";

$result = mysqli_query($conn, $query);
?>

<div class="container-fluid py-4">
    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
            <div class="card">
                <div class="card-body p-3">
                    <div class="row">
                        <div class="col-8">
                            <div class="numbers">
                                <p class="text-sm mb-0 text-uppercase font-weight-bold">Total Products</p>
                                <h5 class="font-weight-bolder"><?= $stats['total_products'] ?></h5>
                            </div>
                        </div>
                        <div class="col-4 text-end">
                            <div class="icon icon-shape bg-gradient-primary shadow-primary text-center rounded-circle">
                                <i class="fas fa-box text-lg opacity-10"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
            <div class="card">
                <div class="card-body p-3">
                    <div class="row">
                        <div class="col-8">
                            <div class="numbers">
                                <p class="text-sm mb-0 text-uppercase font-weight-bold">Total Stock</p>
                                <h5 class="font-weight-bolder"><?= $stats['total_stock'] ?></h5>
                            </div>
                        </div>
                        <div class="col-4 text-end">
                            <div class="icon icon-shape bg-gradient-success shadow-success text-center rounded-circle">
                                <i class="fas fa-cubes text-lg opacity-10"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
            <div class="card">
                <div class="card-body p-3">
                    <div class="row">
                        <div class="col-8">
                            <div class="numbers">
                                <p class="text-sm mb-0 text-uppercase font-weight-bold">Low Stock</p>
                                <h5 class="font-weight-bolder"><?= $stats['low_stock_items'] ?></h5>
                            </div>
                        </div>
                        <div class="col-4 text-end">
                            <div class="icon icon-shape bg-gradient-warning shadow-warning text-center rounded-circle">
                                <i class="fas fa-exclamation-triangle text-lg opacity-10"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6">
            <div class="card">
                <div class="card-body p-3">
                    <div class="row">
                        <div class="col-8">
                            <div class="numbers">
                                <p class="text-sm mb-0 text-uppercase font-weight-bold">Out of Stock</p>
                                <h5 class="font-weight-bolder"><?= $stats['out_of_stock_items'] ?></h5>
                            </div>
                        </div>
                        <div class="col-4 text-end">
                            <div class="icon icon-shape bg-gradient-danger shadow-danger text-center rounded-circle">
                                <i class="fas fa-times-circle text-lg opacity-10"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name or SKU">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Stock Status</label>
                    <select class="form-select" name="stock_status">
                        <option value="">All Products</option>
                        <option value="low" <?= $stock_status === 'low' ? 'selected' : '' ?>>Low Stock</option>
                        <option value="out" <?= $stock_status === 'out' ? 'selected' : '' ?>>Out of Stock</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">Filter</button>
                    <a href="index.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Products List -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Products Management</h5>
            <div>
                <a href="add.php" class="btn btn-primary btn-sm me-2">
                    <i class="fas fa-plus me-1"></i> Add Product
                </a>
                <a href="../inventory/index.php" class="btn btn-info btn-sm">
                    <i class="fas fa-boxes me-1"></i> View Inventory
                </a>
            </div>
        </div>
        <div class="card-body">
            <?php if (mysqli_num_rows($result) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover datatable">
                        <thead class="table-light">
                            <tr>
                                <th>Product</th>
                                <th>SKU</th>
                                <th>Category</th>
                                <th class="text-end">Price</th>
                                <th class="text-end">Stock</th>
                                <th class="text-end">Inventory</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($row['name']) ?></strong>
                                    <?php if (!empty($row['description'])): ?>
                                        <p class="text-muted small mb-0"><?= mb_strimwidth(htmlspecialchars($row['description']), 0, 50, '...') ?></p>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($row['sku']) ?></td>
                                <td><?= $row['category'] ? htmlspecialchars($row['category']) : 'N/A' ?></td>
                                <td class="text-end">₹<?= number_format($row['price'], 2) ?></td>
                                <td class="text-end <?= $row['stock_quantity'] <= $row['low_stock_threshold'] ? 'text-warning fw-bold' : '' ?>">
                                    <?= $row['stock_quantity'] ?>
                                </td>
                                <td class="text-end">
                                    <a href="../inventory/index.php?product_id=<?= $row['id'] ?>" class="badge bg-info text-white">
                                        <?= $row['inventory_entries'] ?> entries
                                    </a>
                                </td>
                                <td class="text-center">
                                    <?php if ($row['stock_quantity'] <= $row['low_stock_threshold'] && $row['stock_quantity'] > 0): ?>
                                        <span class="badge bg-warning">Low Stock</span>
                                    <?php elseif ($row['stock_quantity'] == 0): ?>
                                        <span class="badge bg-danger">Out of Stock</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">In Stock</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group">
                                        <a href="edit.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-primary" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-danger" 
                                                onclick="deleteProduct(<?= $row['id'] ?>, '<?= addslashes($row['name']) ?>')" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <a href="add_stock.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-success" title="Add Stock">
                                            <i class="fas fa-plus-circle"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    No products found matching your criteria. 
                    <a href="add.php" class="alert-link">Add your first product</a> or 
                    <a href="index.php" class="alert-link">clear your filters</a>.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function deleteProduct(id, name) {
    if (confirm('Are you sure you want to delete "' + name + '"?\nThis will also delete all inventory records for this product.')) {
        window.location.href = 'delete.php?id=' + id;
    }
}

// Initialize DataTable with export options
$(document).ready(function() {
    $('.datatable').DataTable({
        dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
             "<'row'<'col-sm-12'tr>>" +
             "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
        pageLength: 25,
        order: [[0, 'asc']],
        responsive: true,
        buttons: [
            {
                extend: 'excel',
                text: '<i class="fas fa-file-excel"></i> Excel',
                className: 'btn btn-success btn-sm',
                exportOptions: {
                    columns: [0, 1, 2, 3, 4, 5, 6]
                }
            },
            {
                extend: 'pdf',
                text: '<i class="fas fa-file-pdf"></i> PDF',
                className: 'btn btn-danger btn-sm',
                exportOptions: {
                    columns: [0, 1, 2, 3, 4, 5, 6]
                }
            },
            {
                extend: 'print',
                text: '<i class="fas fa-print"></i> Print',
                className: 'btn btn-info btn-sm',
                exportOptions: {
                    columns: [0, 1, 2, 3, 4, 5, 6]
                }
            }
        ],
        initComplete: function() {
            this.api().buttons().container().appendTo($('.dataTables_wrapper .col-md-6:eq(0)'));
        }
    });
});
</script>

<?php require_once "../includes/footer.php"; ?>