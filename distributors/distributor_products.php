<?php
require_once "../config/db_connect.php";
require_once "../includes/header.php";
require_once "../includes/functions.php";

// Check if distributor ID is provided
if (!isset($_GET['distributor_id'])) {
    header("Location: manage_distributors.php");
    exit;
}

$distributor_id = intval($_GET['distributor_id']);

// Get distributor info
$stmt = mysqli_prepare($conn, "SELECT name FROM distributors WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $distributor_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$distributor = mysqli_fetch_assoc($result);

if (!$distributor) {
    header("Location: manage_distributors.php");
    exit;
}

// Get distributor's products
$query = "SELECT p.id, p.name, p.sku, p.price, dp.quantity, dp.purchase_price
          FROM distributor_products dp
          JOIN products p ON dp.product_id = p.id
          WHERE dp.distributor_id = $distributor_id
          ORDER BY p.name";
$products = mysqli_query($conn, $query);
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Products from <?= htmlspecialchars($distributor['name']) ?></h5>
                    <a href="manage_distributors.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left me-2"></i>Back to Distributors
                    </a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover datatable">
                            <thead class="table-light">
                                <tr>
                                    <th>Product Name</th>
                                    <th>SKU</th>
                                    <th class="text-end">Retail Price</th>
                                    <th class="text-end">Purchase Price</th>
                                    <th class="text-end">Quantity</th>
                                    <th class="text-end">Total Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = mysqli_fetch_assoc($products)): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['name']) ?></td>
                                        <td><?= htmlspecialchars($row['sku']) ?></td>
                                        <td class="text-end">₹<?= number_format($row['price'], 2) ?></td>
                                        <td class="text-end">₹<?= number_format($row['purchase_price'], 2) ?></td>
                                        <td class="text-end"><?= $row['quantity'] ?></td>
                                        <td class="text-end">₹<?= number_format($row['quantity'] * $row['purchase_price'], 2) ?></td>
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

<script>
$(document).ready(function() {
    $('.datatable').DataTable({
        dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
             "<'row'<'col-sm-12'tr>>" +
             "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
        pageLength: 25,
        order: [[0, 'asc']],
        responsive: true
    });
});
</script>

<?php require_once "../includes/footer.php"; ?>