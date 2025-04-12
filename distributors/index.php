<?php
require_once "../config/db_connect.php";
require_once "../includes/header.php";

// Handle search filters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Get summary statistics
$stats_query = "SELECT 
    COUNT(*) as total_distributors,
    SUM(total_goods_received) as total_goods,
    SUM(total_amount_paid) as total_paid,
    SUM(pending_amount) as total_pending
FROM distributors";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Build query
$query = "SELECT * FROM distributors WHERE 1=1";
if (!empty($search)) {
    $search = mysqli_real_escape_string($conn, $search);
    $query .= " AND (name LIKE '%$search%' OR contact_person LIKE '%$search%' OR phone LIKE '%$search%')";
}
if (!empty($status_filter)) {
    if ($status_filter === 'pending') {
        $query .= " AND pending_amount > 0";
    }
}
$query .= " ORDER BY name ASC";

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
                                <p class="text-sm mb-0 text-uppercase font-weight-bold">Total Distributors</p>
                                <h5 class="font-weight-bolder"><?= $stats['total_distributors'] ?></h5>
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
            <div class="card">
                <div class="card-body p-3">
                    <div class="row">
                        <div class="col-8">
                            <div class="numbers">
                                <p class="text-sm mb-0 text-uppercase font-weight-bold">Total Goods Received</p>
                                <h5 class="font-weight-bolder">₹<?= number_format($stats['total_goods'], 2) ?></h5>
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
            <div class="card">
                <div class="card-body p-3">
                    <div class="row">
                        <div class="col-8">
                            <div class="numbers">
                                <p class="text-sm mb-0 text-uppercase font-weight-bold">Total Amount Paid</p>
                                <h5 class="font-weight-bolder">₹<?= number_format($stats['total_paid'], 2) ?></h5>
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
            <div class="card">
                <div class="card-body p-3">
                    <div class="row">
                        <div class="col-8">
                            <div class="numbers">
                                <p class="text-sm mb-0 text-uppercase font-weight-bold">Total Pending Amount</p>
                                <h5 class="font-weight-bolder">₹<?= number_format($stats['total_pending'], 2) ?></h5>
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
                <div class="col-md-6">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name, contact or phone">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="">All</option>
                        <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending Payments</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">Filter</button>
                    <a href="index.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Distributors List -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Distributors</h5>
            <div>
                <a href="add.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus me-2"></i>Add Distributor
                </a>
            </div>
        </div>
        <div class="card-body">
            <?php if (mysqli_num_rows($result) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover datatable">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Contact Person</th>
                                <th>Phone</th>
                                <th>Email</th>
                                <th class="text-end">Goods Received</th>
                                <th class="text-end">Amount Paid</th>
                                <th class="text-end">Pending Amount</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['name']) ?></td>
                                    <td><?= htmlspecialchars($row['contact_person']) ?></td>
                                    <td><?= htmlspecialchars($row['phone']) ?></td>
                                    <td><?= htmlspecialchars($row['email']) ?></td>
                                    <td class="text-end">₹<?= number_format($row['total_goods_received'], 2) ?></td>
                                    <td class="text-end">₹<?= number_format($row['total_amount_paid'], 2) ?></td>
                                    <td class="text-end <?= $row['pending_amount'] > 0 ? 'text-danger fw-bold' : '' ?>">
                                        ₹<?= number_format($row['pending_amount'], 2) ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="edit.php?id=<?= $row['id'] ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-danger btn-sm" 
                                                onclick="deleteDistributor(<?= $row['id'] ?>, '<?= addslashes($row['name']) ?>')">
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
                    No distributors found. <a href="add.php" class="alert-link">Add your first distributor</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function deleteDistributor(id, name) {
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
            searchPlaceholder: "Search distributors..."
        },
        dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
             "<'row'<'col-sm-12'tr>>" +
             "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
    });
});
</script>

<?php require_once "../includes/footer.php"; ?>