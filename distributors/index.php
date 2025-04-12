<?php
require_once "../config/db_connect.php";
require_once "../includes/header.php";
require_once "../includes/functions.php";

// Handle search filters
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
$sort_by = isset($_GET['sort']) ? sanitize_input($_GET['sort']) : 'name';
$sort_order = isset($_GET['order']) ? sanitize_input($_GET['order']) : 'asc';

// Validate sort parameters
$valid_sort_columns = ['name', 'total_goods_received', 'total_amount_paid', 'pending_amount'];
$valid_orders = ['asc', 'desc'];

if (!in_array($sort_by, $valid_sort_columns)) {
    $sort_by = 'name';
}
if (!in_array($sort_order, $valid_orders)) {
    $sort_order = 'asc';
}

// Get summary statistics
$stats_query = "SELECT 
    COUNT(*) as total_distributors,
    SUM(total_goods_received) as total_goods,
    SUM(total_amount_paid) as total_paid,
    SUM(pending_amount) as total_pending,
    SUM(CASE WHEN pending_amount > 0 THEN 1 ELSE 0 END) as pending_count
FROM distributors";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Build query
$query = "SELECT * FROM distributors WHERE 1=1";
if (!empty($search)) {
    $search = mysqli_real_escape_string($conn, $search);
    $query .= " AND (name LIKE '%$search%' OR contact_person LIKE '%$search%' OR phone LIKE '%$search%' OR email LIKE '%$search%')";
}
if (!empty($status_filter)) {
    if ($status_filter === 'pending') {
        $query .= " AND pending_amount > 0";
    } elseif ($status_filter === 'clear') {
        $query .= " AND pending_amount <= 0";
    }
}
$query .= " ORDER BY $sort_by $sort_order";

$result = mysqli_query($conn, $query);

// Get all products for quick view
$products_query = "SELECT id, name FROM products ORDER BY name";
$products_result = mysqli_query($conn, $products_query);
?>

<div class="container-fluid py-4">
    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
            <div class="card card-hover">
                <div class="card-body p-3">
                    <div class="row">
                        <div class="col-8">
                            <div class="numbers">
                                <p class="text-sm mb-0 text-uppercase font-weight-bold">Total Distributors</p>
                                <h5 class="font-weight-bolder"><?= $stats['total_distributors'] ?? 0 ?></h5>
                                <p class="mb-0">
                                    <span class="text-success text-sm font-weight-bolder"><?= $stats['pending_count'] ?? 0 ?></span>
                                    with pending payments
                                </p>
                            </div>
                        </div>
                        <div class="col-4 text-end">
                            <div class="icon icon-shape bg-gradient-primary shadow-primary text-center rounded-circle">
                                <i class="fas fa-users text-lg opacity-10"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
            <div class="card card-hover">
                <div class="card-body p-3">
                    <div class="row">
                        <div class="col-8">
                            <div class="numbers">
                                <p class="text-sm mb-0 text-uppercase font-weight-bold">Total Goods Received</p>
                                <h5 class="font-weight-bolder">₹<?= number_format($stats['total_goods'] ?? 0, 2) ?></h5>
                                <p class="mb-0">
                                    <span class="text-success text-sm font-weight-bolder">
                                        <?= ($stats['total_distributors'] ?? 0) > 0 ? number_format(($stats['total_goods'] ?? 0) / ($stats['total_distributors'] ?? 1), 2) : '0.00' ?>
                                    </span>
                                    avg per distributor
                                </p>
                            </div>
                        </div>
                        <div class="col-4 text-end">
                            <div class="icon icon-shape bg-gradient-danger shadow-danger text-center rounded-circle">
                                <i class="fas fa-boxes text-lg opacity-10"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
            <div class="card card-hover">
                <div class="card-body p-3">
                    <div class="row">
                        <div class="col-8">
                            <div class="numbers">
                                <p class="text-sm mb-0 text-uppercase font-weight-bold">Total Amount Paid</p>
                                <h5 class="font-weight-bolder">₹<?= number_format($stats['total_paid'] ?? 0, 2) ?></h5>
                                <p class="mb-0">
                                    <span class="text-success text-sm font-weight-bolder">
                                        <?= ($stats['total_goods'] ?? 0) > 0 ? round(($stats['total_paid'] ?? 0) / ($stats['total_goods'] ?? 1) * 100, 2) : '0.00' ?>%
                                    </span>
                                    of total goods
                                </p>
                            </div>
                        </div>
                        <div class="col-4 text-end">
                            <div class="icon icon-shape bg-gradient-success shadow-success text-center rounded-circle">
                                <i class="fas fa-rupee-sign text-lg opacity-10"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6">
            <div class="card card-hover">
                <div class="card-body p-3">
                    <div class="row">
                        <div class="col-8">
                            <div class="numbers">
                                <p class="text-sm mb-0 text-uppercase font-weight-bold">Total Pending Amount</p>
                                <h5 class="font-weight-bolder <?= ($stats['total_pending'] ?? 0) > 0 ? 'text-danger' : 'text-success' ?>">
                                    ₹<?= number_format($stats['total_pending'] ?? 0, 2) ?>
                                </h5>
                                <p class="mb-0">
                                    <span class="text-danger text-sm font-weight-bolder"><?= $stats['pending_count'] ?? 0 ?></span>
                                    distributors pending
                                </p>
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
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-5">
                    <label class="form-label">Search</label>
                    <div class="input-group">
                        <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name, contact, phone or email">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="">All Distributors</option>
                        <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending Payments</option>
                        <option value="clear" <?= $status_filter === 'clear' ? 'selected' : '' ?>>Clear Accounts</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Sort By</label>
                    <select class="form-select" name="sort">
                        <option value="name" <?= $sort_by === 'name' ? 'selected' : '' ?>>Name</option>
                        <option value="total_goods_received" <?= $sort_by === 'total_goods_received' ? 'selected' : '' ?>>Goods Received</option>
                        <option value="total_amount_paid" <?= $sort_by === 'total_amount_paid' ? 'selected' : '' ?>>Amount Paid</option>
                        <option value="pending_amount" <?= $sort_by === 'pending_amount' ? 'selected' : '' ?>>Pending Amount</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Order</label>
                    <select class="form-select" name="order">
                        <option value="asc" <?= $sort_order === 'asc' ? 'selected' : '' ?>>Ascending</option>
                        <option value="desc" <?= $sort_order === 'desc' ? 'selected' : '' ?>>Descending</option>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary me-2">Apply Filters</button>
                    <a href="index.php" class="btn btn-outline-secondary">Reset Filters</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Distributors List -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Distributors List</h5>
            <div>
                <a href="add.php" class="btn btn-primary btn-sm me-2">
                    <i class="fas fa-plus me-1"></i> Add Distributor
                </a>
                <a href="transactions.php" class="btn btn-outline-info btn-sm">
                    <i class="fas fa-exchange-alt me-1"></i> Transactions
                </a>
            </div>
        </div>
        <div class="card-body">
            <?php if (mysqli_num_rows($result) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle datatable">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Contact</th>
                                <th>Phone</th>
                                <th>Email</th>
                                <th class="text-end">Goods Received</th>
                                <th class="text-end">Amount Paid</th>
                                <th class="text-end">Pending Amount</th>
                                <th class="text-center" style="width: 120px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($row['name']) ?></strong>
                                        <?php if (!empty($row['gst_number'])): ?>
                                            <br><small class="text-muted">GST: <?= htmlspecialchars($row['gst_number']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($row['contact_person']) ?></td>
                                    <td>
                                        <?= htmlspecialchars($row['phone']) ?>
                                        <?php if (!empty($row['phone'])): ?>
                                            <br>
                                            <a href="tel:<?= htmlspecialchars($row['phone']) ?>" class="btn btn-sm btn-outline-primary mt-1">
                                                <i class="fas fa-phone"></i> Call
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($row['email']) ?>
                                        <?php if (!empty($row['email'])): ?>
                                            <br>
                                            <a href="mailto:<?= htmlspecialchars($row['email']) ?>" class="btn btn-sm btn-outline-info mt-1">
                                                <i class="fas fa-envelope"></i> Email
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">₹<?= number_format($row['total_goods_received'], 2) ?></td>
                                    <td class="text-end">₹<?= number_format($row['total_amount_paid'], 2) ?></td>
                                    <td class="text-end <?= $row['pending_amount'] > 0 ? 'text-danger fw-bold' : 'text-success' ?>">
                                        ₹<?= number_format($row['pending_amount'], 2) ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-sm btn-outline-primary rounded" data-bs-toggle="dropdown" aria-expanded="false">
                                                <i class="fas fa-ellipsis-h"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow">
                                                <li>
                                                    <h6 class="dropdown-header"><?= htmlspecialchars($row['name']) ?></h6>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <a class="dropdown-item" href="edit.php?id=<?= $row['id'] ?>">
                                                        <i class="fas fa-edit me-2 text-primary"></i> Edit
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="transactions.php?distributor_id=<?= $row['id'] ?>">
                                                        <i class="fas fa-exchange-alt me-2 text-info"></i> Transactions
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="distributor_products.php?distributor_id=<?= $row['id'] ?>">
                                                        <i class="fas fa-boxes me-2 text-warning"></i> Products
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#addTransactionModal" onclick="setDistributor(<?= $row['id'] ?>)">
                                                        <i class="fas fa-plus-circle me-2 text-success"></i> Add Transaction
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#addProductModal" onclick="setDistributor(<?= $row['id'] ?>)">
                                                        <i class="fas fa-cart-plus me-2 text-success"></i> Add Product
                                                    </a>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <a class="dropdown-item text-danger" href="#" onclick="deleteDistributor(<?= $row['id'] ?>, '<?= addslashes($row['name']) ?>')">
                                                        <i class="fas fa-trash me-2"></i> Delete
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> No distributors found matching your criteria. 
                    <a href="add.php" class="alert-link">Add your first distributor</a> or 
                    <a href="index.php" class="alert-link">clear your filters</a>.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Transaction Modal -->
<div class="modal fade" id="addTransactionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Transaction</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="transactions.php">
                <div class="modal-body">
                    <input type="hidden" name="distributor_id" id="modalDistributorId">
                    <div class="mb-3">
                        <label class="form-label">Transaction Type</label>
                        <select class="form-select" name="transaction_type" required>
                            <option value="payment">Payment (Amount Paid)</option>
                            <option value="purchase">Purchase (Goods Received)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount</label>
                        <input type="number" step="0.01" min="0.01" class="form-control" name="amount" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Date</label>
                        <input type="date" class="form-control" name="transaction_date" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment Method</label>
                        <select class="form-select" name="payment_method" required>
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="cheque">Cheque</option>
                            <option value="upi">UPI</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="add_transaction" class="btn btn-primary">Save Transaction</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Product Modal -->
<div class="modal fade" id="addProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Product to Distributor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="distributor_products.php">
                <input type="hidden" name="distributor_id" id="modalDistributorIdProduct">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Product</label>
                        <select class="form-select" name="product_id" required>
                            <option value="">Select Product</option>
                            <?php while ($product = mysqli_fetch_assoc($products_result)): ?>
                                <option value="<?= $product['id'] ?>"><?= htmlspecialchars($product['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Quantity</label>
                        <input type="number" min="1" class="form-control" name="quantity" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Purchase Price (per unit)</label>
                        <input type="number" step="0.01" min="0.01" class="form-control" name="purchase_price" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="add_product" class="btn btn-primary">Add Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function deleteDistributor(id, name) {
    if (confirm('Are you sure you want to delete "' + name + '"?\nThis will also delete all related transactions and product associations.')) {
        window.location.href = 'delete.php?id=' + id;
    }
}

function setDistributor(id) {
    document.getElementById('modalDistributorId').value = id;
    document.getElementById('modalDistributorIdProduct').value = id;
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
                text: '<i class="fas fa-file-excel me-1"></i> Excel',
                className: 'btn btn-success btn-sm',
                exportOptions: {
                    columns: [0, 1, 2, 3, 4, 5, 6]
                }
            },
            {
                extend: 'pdf',
                text: '<i class="fas fa-file-pdf me-1"></i> PDF',
                className: 'btn btn-danger btn-sm',
                exportOptions: {
                    columns: [0, 1, 2, 3, 4, 5, 6]
                }
            },
            {
                extend: 'print',
                text: '<i class="fas fa-print me-1"></i> Print',
                className: 'btn btn-info btn-sm',
                exportOptions: {
                    columns: [0, 1, 2, 3, 4, 5, 6]
                }
            }
        ],
        initComplete: function() {
            this.api().buttons().container().appendTo($('.dataTables_wrapper .col-md-6:eq(0)'));
        },
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search...",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            infoEmpty: "Showing 0 to 0 of 0 entries",
            infoFiltered: "(filtered from _MAX_ total entries)"
        }
    });
    
    // Add hover effect to cards
    $('.card-hover').hover(
        function() {
            $(this).addClass('shadow-sm');
        },
        function() {
            $(this).removeClass('shadow-sm');
        }
    );
});
</script>

<?php require_once "../includes/footer.php"; ?>