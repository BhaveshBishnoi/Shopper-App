<?php
require_once "../config/db_connect.php";
require_once "../includes/header.php";

// Handle search filters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$order_status = isset($_GET['order_status']) ? $_GET['order_status'] : '';
$date_range = isset($_GET['date_range']) ? $_GET['date_range'] : '';

// Build query
$query = "SELECT c.*, 
         COUNT(DISTINCT s.id) as total_orders,
         COALESCE(SUM(s.total_amount), 0) as total_spent,
         MAX(s.created_at) as last_order_date
         FROM customers c
         LEFT JOIN sales s ON c.id = s.customer_id
         WHERE 1=1";

if (!empty($search)) {
    $search = mysqli_real_escape_string($conn, $search);
    $query .= " AND (c.name LIKE '%$search%' OR c.email LIKE '%$search%' OR c.phone LIKE '%$search%')";
}

if (!empty($date_range)) {
    $today = date('Y-m-d');
    switch ($date_range) {
        case '30days':
            $query .= " AND s.created_at >= DATE_SUB('$today', INTERVAL 30 DAY)";
            break;
        case '90days':
            $query .= " AND s.created_at >= DATE_SUB('$today', INTERVAL 90 DAY)";
            break;
        case '1year':
            $query .= " AND s.created_at >= DATE_SUB('$today', INTERVAL 1 YEAR)";
            break;
    }
}

$query .= " GROUP BY c.id";

// Add HAVING clause after GROUP BY
if (!empty($order_status)) {
    if ($order_status === 'with_orders') {
        $query .= " HAVING total_orders > 0";
    } elseif ($order_status === 'no_orders') {
        $query .= " HAVING total_orders = 0";
    }
}

$query .= " ORDER BY c.name ASC";
$result = mysqli_query($conn, $query);
?>

<div class="container-fluid py-4">
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by name, email or phone">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Order Status</label>
                    <select class="form-select" name="order_status">
                        <option value="">All Customers</option>
                        <option value="with_orders" <?php echo $order_status === 'with_orders' ? 'selected' : ''; ?>>With Orders</option>
                        <option value="no_orders" <?php echo $order_status === 'no_orders' ? 'selected' : ''; ?>>No Orders</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date Range</label>
                    <select class="form-select" name="date_range">
                        <option value="">All Time</option>
                        <option value="30days" <?php echo $date_range === '30days' ? 'selected' : ''; ?>>Last 30 Days</option>
                        <option value="90days" <?php echo $date_range === '90days' ? 'selected' : ''; ?>>Last 90 Days</option>
                        <option value="1year" <?php echo $date_range === '1year' ? 'selected' : ''; ?>>Last Year</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">Filter</button>
                    <a href="index.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Customers List -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Customers</h5>
            <div>
                <a href="add.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus me-2"></i>Add Customer
                </a>
            </div>
        </div>
        <div class="card-body">
            <?php if (mysqli_num_rows($result) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover datatable">
                        <thead class="table-light">
                            <tr>
                                <th>Customer</th>
                                <th>Contact Info</th>
                                <th>Orders</th>
                                <th>Total Spent</th>
                                <th>Last Order</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-initial rounded-circle bg-primary text-white me-2">
                                                <?php echo strtoupper(substr($row['name'], 0, 1)); ?>
                                            </div>
                                            <?php echo htmlspecialchars($row['name']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div><i class="fas fa-envelope me-1"></i> <?php echo htmlspecialchars($row['email']); ?></div>
                                        <div><i class="fas fa-phone me-1"></i> <?php echo htmlspecialchars($row['phone']); ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?php echo $row['total_orders']; ?> orders</span>
                                    </td>
                                    <td>
                                        <h6 class="mb-0">₹<?php echo number_format($row['total_spent'], 2); ?></h6>
                                    </td>
                                    <td>
                                        <?php if ($row['last_order_date']): ?>
                                            <?php echo date('d M Y', strtotime($row['last_order_date'])); ?>
                                        <?php else: ?>
                                            <span class="text-muted">No orders yet</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="view.php?id=<?php echo $row['id']; ?>" class="btn btn-info btn-sm text-white" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit.php?id=<?php echo $row['id']; ?>" class="btn btn-primary btn-sm" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-danger btn-sm" 
                                                onclick="deleteCustomer(<?php echo $row['id']; ?>, '<?php echo addslashes($row['name']); ?>')"
                                                title="Delete">
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
                    No customers found. <a href="add.php" class="alert-link">Add your first customer</a>
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
                <h5 class="modal-title">Import Customers</h5>
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
                            CSV file should have headers: name,email,phone,address
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

<style>
.avatar-initial {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}
</style>

<script>
function deleteCustomer(id, name) {
    if (confirm('Are you sure you want to delete "' + name + '"?\nThis action cannot be undone and will remove all associated orders.')) {
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
            searchPlaceholder: "Search customers..."
        },
        dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
             "<'row'<'col-sm-12'tr>>" +
             "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
    });
});
</script>

<?php require_once "../includes/footer.php"; ?>
