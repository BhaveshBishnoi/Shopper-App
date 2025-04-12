<?php
require_once "../config/db_connect.php";
require_once "../includes/header.php";
require_once "../includes/functions.php";

// Get all distributors
$query = "SELECT * FROM distributors ORDER BY name";
$result = mysqli_query($conn, $query);
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Manage Distributors</h5>
                    <a href="add.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus me-2"></i>Add Distributor
                    </a>
                </div>
                <div class="card-body">
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success">
                            <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger">
                            <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover datatable">
                            <thead class="table-light">
                                <tr>
                                    <th>Name</th>
                                    <th>Contact Person</th>
                                    <th>Phone</th>
                                    <th>Email</th>
                                    <th>GST Number</th>
                                    <th>Total Purchases</th>
                                    <th>Total Payments</th>
                                    <th>Pending Amount</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['name']) ?></td>
                                        <td><?= htmlspecialchars($row['contact_person']) ?></td>
                                        <td><?= htmlspecialchars($row['phone']) ?></td>
                                        <td><?= htmlspecialchars($row['email']) ?></td>
                                        <td><?= htmlspecialchars($row['gst_number']) ?></td>
                                        <td class="text-end">₹<?= number_format($row['total_goods_received'], 2) ?></td>
                                        <td class="text-end">₹<?= number_format($row['total_amount_paid'], 2) ?></td>
                                        <td class="text-end <?= $row['pending_amount'] > 0 ? 'text-danger' : 'text-success' ?>">
                                            ₹<?= number_format($row['pending_amount'], 2) ?>
                                        </td>
                                        <td class="text-center">
                                            <a href="edit.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-primary" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="delete.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-danger" title="Delete"
                                               onclick="return confirm('Are you sure you want to delete this distributor?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                            <a href="transactions.php?distributor_id=<?= $row['id'] ?>" class="btn btn-sm btn-info" title="Transactions">
                                                <i class="fas fa-exchange-alt"></i>
                                            </a>
                                            <a href="distributor_products.php?distributor_id=<?= $row['id'] ?>" class="btn btn-sm btn-warning" title="Products">
                                                <i class="fas fa-boxes"></i>
                                            </a>
                                        </td>
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
        responsive: true,
        buttons: [
            {
                extend: 'excel',
                text: '<i class="fas fa-file-excel"></i> Excel',
                className: 'btn btn-success btn-sm',
                exportOptions: {
                    columns: [0, 1, 2, 3, 4, 5, 6, 7]
                }
            },
            {
                extend: 'pdf',
                text: '<i class="fas fa-file-pdf"></i> PDF',
                className: 'btn btn-danger btn-sm',
                exportOptions: {
                    columns: [0, 1, 2, 3, 4, 5, 6, 7]
                }
            },
            {
                extend: 'print',
                text: '<i class="fas fa-print"></i> Print',
                className: 'btn btn-info btn-sm',
                exportOptions: {
                    columns: [0, 1, 2, 3, 4, 5, 6, 7]
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