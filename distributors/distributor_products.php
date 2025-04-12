<?php
session_start();
require_once "../config/db_connect.php";
require_once "../includes/functions.php";
require_once "../includes/header.php";

// Handle search filters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$distributor_id = isset($_GET['distributor_id']) ? intval($_GET['distributor_id']) : 0;

// Build query
$query = "SELECT dp.id, p.id as product_id, p.name as product_name, p.sku, 
                 d.id as distributor_id, d.name as distributor_name,
                 dp.quantity, dp.purchase_price,
                 (dp.quantity * dp.purchase_price) as total_value
          FROM distributor_products dp
          JOIN products p ON dp.product_id = p.id
          JOIN distributors d ON dp.distributor_id = d.id
          WHERE 1=1";

if (!empty($search)) {
    $search = mysqli_real_escape_string($conn, $search);
    $query .= " AND (p.name LIKE '%$search%' OR p.sku LIKE '%$search%' OR d.name LIKE '%$search%')";
}

if ($distributor_id > 0) {
    $query .= " AND d.id = $distributor_id";
}

$query .= " ORDER BY d.name, p.name";

$result = mysqli_query($conn, $query);

// Get distributors for filter dropdown
$distributors = mysqli_query($conn, "SELECT id, name FROM distributors ORDER BY name");

// Calculate totals
$total_products = 0;
$total_value = 0;
while ($row = mysqli_fetch_assoc($result)) {
    $total_products++;
    $total_value += $row['total_value'];
}
$result = mysqli_query($conn, $query); // Reset pointer
?>

<div class="container-fluid py-4">
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-5">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($search) ?>" 
                           placeholder="Search products or distributors">
                </div>
                <div class="col-md-5">
                    <label class="form-label">Distributor</label>
                    <select class="form-select" name="distributor_id">
                        <option value="">All Distributors</option>
                        <?php while ($distributor = mysqli_fetch_assoc($distributors)): ?>
                            <option value="<?= $distributor['id'] ?>" <?= $distributor_id == $distributor['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($distributor['name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">Filter</button>
                    <a href="distributor_products.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6 class="card-title">Total Relationships</h6>
                    <h4 class="card-text"><?= number_format($total_products) ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="card-title">Total Value</h6>
                    <h4 class="card-text">₹<?= number_format($total_value, 2) ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6 class="card-title">Average per Product</h6>
                    <h4 class="card-text">₹<?= $total_products > 0 ? number_format($total_value/$total_products, 2) : '0.00' ?></h4>
                </div>
            </div>
        </div>
    </div>

    <!-- Distributor Products List -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Distributor Product Relationships</h5>
            <div>
                <a href="../products/index.php" class="btn btn-secondary btn-sm me-2">
                    <i class="fas fa-box me-2"></i>View Products
                </a>
                <a href="../distributors/index.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-truck me-2"></i>View Distributors
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
                                <th>Distributor</th>
                                <th class="text-end">Quantity</th>
                                <th class="text-end">Unit Price</th>
                                <th class="text-end">Total Value</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['product_name']) ?></td>
                                    <td><?= htmlspecialchars($row['sku']) ?></td>
                                    <td><?= htmlspecialchars($row['distributor_name']) ?></td>
                                    <td class="text-end"><?= number_format($row['quantity']) ?></td>
                                    <td class="text-end">₹<?= number_format($row['purchase_price'], 2) ?></td>
                                    <td class="text-end">₹<?= number_format($row['total_value'], 2) ?></td>
                                    <td class="text-end">
                                        <a href="../products/manage_distributors.php?id=<?= $row['product_id'] ?>" 
                                           class="btn btn-primary btn-sm" title="Manage">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    No distributor-product relationships found.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('.datatable').DataTable({
        pageLength: 25,
        order: [[2, 'asc']], // Default sort by distributor name
        responsive: true,
        dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
             "<'row'<'col-sm-12'tr>>" +
             "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
        language: {
            search: "",
            searchPlaceholder: "Search relationships..."
        }
    });
});
</script>

<?php require_once "../includes/footer.php"; ?>