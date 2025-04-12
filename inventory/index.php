<?php
require_once "../config/db_connect.php";
require_once "../includes/header.php";

// Handle filters
$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
$distributor_id = isset($_GET['distributor_id']) ? intval($_GET['distributor_id']) : 0;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';

// Build the query with filters
$query = "SELECT i.*, 
          p.name as product_name, p.sku, 
          d.name as distributor_name,
          (i.quantity * i.purchase_price) as total_cost
          FROM inventory i
          JOIN products p ON i.product_id = p.id
          LEFT JOIN distributors d ON i.distributor_id = d.id
          WHERE 1=1";

if ($product_id > 0) {
    $query .= " AND i.product_id = $product_id";
}
if ($distributor_id > 0) {
    $query .= " AND i.distributor_id = $distributor_id";
}
if (!empty($date_from)) {
    $query .= " AND i.transaction_date >= '$date_from'";
}
if (!empty($date_to)) {
    $query .= " AND i.transaction_date <= '$date_to'";
}
if (!empty($status)) {
    $query .= " AND i.payment_status = '$status'";
}

$query .= " ORDER BY i.transaction_date DESC, i.created_at DESC";

$inventory = mysqli_query($conn, $query);

// Get products for filter dropdown
$products = mysqli_query($conn, "SELECT id, name, sku FROM products ORDER BY name");

// Get distributors for filter dropdown
$distributors = mysqli_query($conn, "SELECT id, name FROM distributors ORDER BY name");

// Calculate inventory summary
$summary_query = "SELECT 
    SUM(quantity) as total_quantity,
    SUM(quantity * purchase_price) as total_value,
    COUNT(*) as total_entries
    FROM inventory";
$summary_result = mysqli_query($conn, $summary_query);
$summary = mysqli_fetch_assoc($summary_result);
?>

<div class="container-fluid py-4">
    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-xl-4 col-sm-6 mb-xl-0 mb-4">
            <div class="card">
                <div class="card-body p-3">
                    <div class="row">
                        <div class="col-8">
                            <div class="numbers">
                                <p class="text-sm mb-0 text-uppercase font-weight-bold">Total Inventory Entries</p>
                                <h5 class="font-weight-bolder"><?= $summary['total_entries'] ?></h5>
                            </div>
                        </div>
                        <div class="col-4 text-end">
                            <div class="icon icon-shape bg-gradient-primary shadow-primary text-center rounded-circle">
                                <i class="fas fa-list text-lg opacity-10"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-sm-6 mb-xl-0 mb-4">
            <div class="card">
                <div class="card-body p-3">
                    <div class="row">
                        <div class="col-8">
                            <div class="numbers">
                                <p class="text-sm mb-0 text-uppercase font-weight-bold">Total Items</p>
                                <h5 class="font-weight-bolder"><?= $summary['total_quantity'] ?></h5>
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
        <div class="col-xl-4 col-sm-6">
            <div class="card">
                <div class="card-body p-3">
                    <div class="row">
                        <div class="col-8">
                            <div class="numbers">
                                <p class="text-sm mb-0 text-uppercase font-weight-bold">Total Inventory Value</p>
                                <h5 class="font-weight-bolder">₹<?= number_format($summary['total_value'], 2) ?></h5>
                            </div>
                        </div>
                        <div class="col-4 text-end">
                            <div class="icon icon-shape bg-gradient-info shadow-info text-center rounded-circle">
                                <i class="fas fa-rupee-sign text-lg opacity-10"></i>
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
                <div class="col-md-3">
                    <label class="form-label">Product</label>
                    <select class="form-select" name="product_id">
                        <option value="">All Products</option>
                        <?php while ($product = mysqli_fetch_assoc($products)): ?>
                            <option value="<?= $product['id'] ?>" <?= $product_id == $product['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($product['name']) ?> (<?= htmlspecialchars($product['sku']) ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-2">
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
                <div class="col-md-2">
                    <label class="form-label">Date From</label>
                    <input type="date" class="form-control" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Date To</label>
                    <input type="date" class="form-control" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Payment Status</label>
                    <select class="form-select" name="status">
                        <option value="">All Statuses</option>
                        <option value="paid" <?= $status === 'paid' ? 'selected' : '' ?>>Paid</option>
                        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                    </select>
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Inventory Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Inventory Records</h5>
            <div>
                <a href="../products/index.php" class="btn btn-secondary btn-sm me-2">
                    <i class="fas fa-arrow-left me-1"></i> Back to Products
                </a>
                <?php if ($product_id > 0): ?>
                    <a href="../products/add_stock.php?id=<?= $product_id ?>" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus me-1"></i> Add Stock
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <?php if (mysqli_num_rows($inventory) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover datatable">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Product</th>
                                <th>SKU</th>
                                <th>Distributor</th>
                                <th class="text-end">Quantity</th>
                                <th class="text-end">Unit Cost</th>
                                <th class="text-end">Total Cost</th>
                                <th>Payment Status</th>
                                <th>Reference</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($entry = mysqli_fetch_assoc($inventory)): ?>
                            <tr>
                                <td><?= date('d M Y', strtotime($entry['transaction_date'])) ?></td>
                                <td><?= htmlspecialchars($entry['product_name']) ?></td>
                                <td><?= htmlspecialchars($entry['sku']) ?></td>
                                <td><?= $entry['distributor_name'] ? htmlspecialchars($entry['distributor_name']) : 'N/A' ?></td>
                                <td class="text-end"><?= $entry['quantity'] ?></td>
                                <td class="text-end">₹<?= number_format($entry['purchase_price'], 2) ?></td>
                                <td class="text-end">₹<?= number_format($entry['total_cost'], 2) ?></td>
                                <td>
                                    <span class="badge bg-<?= $entry['payment_status'] === 'paid' ? 'success' : 'warning' ?>">
                                        <?= ucfirst($entry['payment_status']) ?>
                                    </span>
                                </td>
                                <td><?= $entry['reference_number'] ? htmlspecialchars($entry['reference_number']) : 'N/A' ?></td>
                                <td class="text-center">
                                    <div class="btn-group">
                                        <button class="btn btn-sm btn-info" title="View Details" 
                                                onclick="viewDetails(<?= $entry['id'] ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" title="Delete Record"
                                                onclick="confirmDelete(<?= $entry['id'] ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    No inventory records found. 
                    <?php if ($product_id > 0): ?>
                        <a href="../products/add_stock.php?id=<?= $product_id ?>" class="alert-link">Add stock for this product</a>
                    <?php else: ?>
                        <a href="../products/index.php" class="alert-link">View products</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Inventory Record Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="detailsContent">
                <!-- Content will be loaded via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable with export options
    $('.datatable').DataTable({
        dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
             "<'row'<'col-sm-12'tr>>" +
             "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
        pageLength: 25,
        order: [[0, 'desc']],
        responsive: true,
        buttons: [
            {
                extend: 'excel',
                text: '<i class="fas fa-file-excel"></i> Excel',
                className: 'btn btn-success btn-sm',
                exportOptions: {
                    columns: [0, 1, 2, 3, 4, 5, 6, 7, 8]
                }
            },
            {
                extend: 'pdf',
                text: '<i class="fas fa-file-pdf"></i> PDF',
                className: 'btn btn-danger btn-sm',
                exportOptions: {
                    columns: [0, 1, 2, 3, 4, 5, 6, 7, 8]
                }
            },
            {
                extend: 'print',
                text: '<i class="fas fa-print"></i> Print',
                className: 'btn btn-info btn-sm',
                exportOptions: {
                    columns: [0, 1, 2, 3, 4, 5, 6, 7, 8]
                }
            }
        ],
        initComplete: function() {
            this.api().buttons().container().appendTo($('.dataTables_wrapper .col-md-6:eq(0)'));
        }
    });
});

function viewDetails(id) {
    $.ajax({
        url: '../ajax/get_inventory_details.php',
        method: 'GET',
        data: { id: id },
        success: function(response) {
            $('#detailsContent').html(response);
            $('#detailsModal').modal('show');
        }
    });
}

function markAsPaid(id) {
    if (confirm('Mark this inventory record as paid?')) {
        $.ajax({
            url: '../ajax/update_inventory_status.php',
            method: 'POST',
            data: { id: id, status: 'paid' },
            success: function() {
                location.reload();
            }
        });
    }
}

function confirmDelete(id) {
    if (confirm('Are you sure you want to delete this inventory record?\nThis action cannot be undone.')) {
        $.ajax({
            url: '../ajax/delete_inventory.php',
            method: 'POST',
            data: { id: id },
            success: function() {
                location.reload();
            }
        });
    }
}
</script>

<?php require_once "../includes/footer.php"; ?>