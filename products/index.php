<?php
require_once "../config/db_connect.php";
require_once "../includes/header.php";

// Handle search filters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$stock_status = isset($_GET['stock_status']) ? $_GET['stock_status'] : '';

// Build query
$query = "SELECT * FROM products WHERE 1=1";
if (!empty($search)) {
    $search = mysqli_real_escape_string($conn, $search);
    $query .= " AND (name LIKE '%$search%' OR sku LIKE '%$search%')";
}
if (!empty($stock_status)) {
    if ($stock_status === 'low') {
        $query .= " AND stock_quantity <= low_stock_threshold";
    } elseif ($stock_status === 'out') {
        $query .= " AND stock_quantity = 0";
    }
}
$query .= " ORDER BY name ASC";

$result = mysqli_query($conn, $query);
?>

<div class="container-fluid py-4">
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by name or SKU">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Stock Status</label>
                    <select class="form-select" name="stock_status">
                        <option value="">All</option>
                        <option value="low" <?php echo $stock_status === 'low' ? 'selected' : ''; ?>>Low Stock</option>
                        <option value="out" <?php echo $stock_status === 'out' ? 'selected' : ''; ?>>Out of Stock</option>
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
            <h5 class="mb-0">Products</h5>
            <div>
                <a href="add.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus me-2"></i>Add Product
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
                                <th>Description</th>
                                <th class="text-end">Price</th>
                                <th class="text-end">Stock</th>
                                <th class="text-center">Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if (!empty($row['image'])): ?>
                                                <img src="<?php echo htmlspecialchars($row['image']); ?>" class="rounded me-2" style="width: 32px; height: 32px; object-fit: cover;">
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($row['name']); ?>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['sku']); ?></td>
                                    <td><?php echo mb_strimwidth(htmlspecialchars($row['description']), 0, 50, "..."); ?></td>
                                    <td class="text-end">₹<?php echo number_format($row['price'], 2); ?></td>
                                    <td class="text-end"><?php echo $row['stock_quantity']; ?></td>
                                    <td class="text-center">
                                        <?php 
                                        $threshold = isset($row['low_stock_threshold']) ? $row['low_stock_threshold'] : 10;
                                        if ($row['stock_quantity'] <= $threshold && $row['stock_quantity'] > 0): 
                                        ?>
                                            <span class="badge bg-warning">Low Stock</span>
                                        <?php elseif ($row['stock_quantity'] == 0): ?>
                                            <span class="badge bg-danger">Out of Stock</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">In Stock</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="edit.php?id=<?php echo $row['id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-danger btn-sm" 
                                                onclick="deleteProduct(<?php echo $row['id']; ?>, '<?php echo addslashes($row['name']); ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    No products found. <a href="add.php" class="alert-link">Add your first product</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Import Products</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="import.php" method="post" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">CSV File</label>
                        <input type="file" class="form-control" name="csv_file" accept=".csv" required>
                    </div>
                    <div class="alert alert-info">
                        <small>
                            CSV file should have headers: name,sku,description,price,stock_quantity,low_stock_threshold,gst_rate
                            <br>Download a <a href="template.csv">sample template</a>
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Import</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function deleteProduct(id, name) {
    if (confirm('Are you sure you want to delete "' + name + '"?\nThis action cannot be undone.')) {
        window.location.href = 'delete.php?id=' + id;
    }
}

// Initialize DataTable with custom options
$(document).ready(function() {
    $('.datatable').DataTable({
        pageLength: 25,
        order: [[0, 'asc']],
        language: {
            search: "",
            searchPlaceholder: "Search products..."
        },
        dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
             "<'row'<'col-sm-12'tr>>" +
             "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
    });
});
</script>

<?php require_once "../includes/footer.php"; ?>
