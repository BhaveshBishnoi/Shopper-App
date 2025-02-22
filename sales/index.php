<?php
session_start();
require_once "../includes/functions.php";
require_once "../config/db_connect.php";

$page_title = "Sales";

try {
    // Check if sales table exists
    $check_table = mysqli_query($conn, "SHOW TABLES LIKE 'sales'");
    if (mysqli_num_rows($check_table) == 0) {
        // Create tables if they don't exist
        $schema_file = file_get_contents("../database/update_schema.sql");
        if ($schema_file === false) {
            throw new Exception("Error reading schema file");
        }
        
        $queries = explode(';', $schema_file);
        
        foreach ($queries as $query) {
            $query = trim($query);
            if (!empty($query)) {
                if (!mysqli_query($conn, $query)) {
                    throw new Exception("Error creating database tables: " . mysqli_error($conn));
                }
            }
        }
    }

    // Check if payment_status column exists
    $check_column = mysqli_query($conn, "SHOW COLUMNS FROM sales LIKE 'payment_status'");
    if (mysqli_num_rows($check_column) == 0) {
        // Add payment_status column if it doesn't exist
        $alter_query = "ALTER TABLE sales ADD COLUMN payment_status ENUM('pending', 'paid') DEFAULT 'pending' AFTER payment_method";
        if (!mysqli_query($conn, $alter_query)) {
            throw new Exception("Error adding payment_status column: " . mysqli_error($conn));
        }
    }

    // Update all records based on payment_method
    $update_query = "UPDATE sales SET payment_status = 
                    CASE 
                        WHEN payment_method IN ('cash', 'card', 'upi', 'bank_transfer') THEN 'paid'
                        ELSE 'pending'
                    END";
    if (!mysqli_query($conn, $update_query)) {
        throw new Exception("Error updating payment status: " . mysqli_error($conn));
    }

    // Get all sales with customer info
    $query = "SELECT s.*, 
                    c.name as customer_name, 
                    c.phone as customer_phone, 
                    c.is_walkin,
                    DATE_FORMAT(s.sale_date, '%d-%m-%Y') as formatted_date,
                    DATE_FORMAT(s.created_at, '%h:%i %p') as formatted_time
             FROM sales s
             LEFT JOIN customers c ON s.customer_id = c.id
             ORDER BY s.created_at DESC";

    $result = mysqli_query($conn, $query);
    if (!$result) {
        throw new Exception("Error loading sales: " . mysqli_error($conn));
    }

    require_once "../includes/header.php";
    ?>

    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header pb-0">
                        <div class="row">
                            <div class="col-6 d-flex align-items-center">
                                <h6 class="mb-0">Sales</h6>
                            </div>
                            <div class="col-6 text-end">
                                <a class="btn bg-gradient-dark mb-0" href="add.php">
                                    <i class="fas fa-plus"></i>&nbsp;&nbsp;Add New Sale
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body px-0 pt-0 pb-2">
                        <div class="table-responsive p-0">
                            <table class="table align-items-center mb-0">
                                <thead>
                                    <tr>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Invoice</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Customer</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Date</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Amount</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Payment Status</th>
                                        <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Payment Method</th>
                                        <th class="text-secondary opacity-7"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (mysqli_num_rows($result) > 0): ?>
                                        <?php while ($sale = mysqli_fetch_assoc($result)): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex px-2 py-1">
                                                        <div class="d-flex flex-column justify-content-center">
                                                            <h6 class="mb-0 text-sm"><?= htmlspecialchars($sale['invoice_number']) ?></h6>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <p class="text-xs font-weight-bold mb-0">
                                                        <?= htmlspecialchars($sale['customer_name']) ?>
                                                        <?php if (isset($sale['is_walkin']) && $sale['is_walkin']): ?>
                                                            <span class="badge badge-sm bg-gradient-secondary">Walk-in</span>
                                                        <?php endif; ?>
                                                    </p>
                                                    <?php if (!empty($sale['customer_phone'])): ?>
                                                        <p class="text-xs text-secondary mb-0"><?= htmlspecialchars($sale['customer_phone']) ?></p>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <p class="text-xs font-weight-bold mb-0"><?= htmlspecialchars($sale['formatted_date']) ?></p>
                                                    <p class="text-xs text-secondary mb-0"><?= htmlspecialchars($sale['formatted_time']) ?></p>
                                                </td>
                                                <td>
                                                    <p class="text-xs font-weight-bold mb-0"><?= format_currency($sale['total_amount']) ?></p>
                                                </td>
                                                <td>
                                                    <?php
                                                    if ($sale['payment_method'] === 'none' || empty($sale['payment_method'])) {
                                                        $status = 'pending';
                                                        $bg_color = '#FDE8E8';
                                                        $text_color = '#DC2626';
                                                    } else {
                                                        $status = 'paid';
                                                        $bg_color = '#DEF7EC';
                                                        $text_color = '#046C4E';
                                                    }
                                                    ?>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <div style="background-color: <?= $bg_color ?>; color: <?= $text_color ?>; padding: 8px 12px; border-radius: 8px; font-weight: 600; font-size: 0.75rem; text-transform: uppercase;">
                                                            <?= ucfirst($status) ?>
                                                        </div>
                                                        <?php if ($status === 'pending'): ?>
                                                            <a href="mark_paid.php?id=<?= $sale['id'] ?>" 
                                                               class="btn btn-link text-success p-1 ms-2"
                                                               title="Mark as Paid">
                                                                <i class="fas fa-check-circle"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <p class="text-xs font-weight-bold mb-0"><?= $sale['payment_method'] === 'none' ? '-' : ucfirst($sale['payment_method']) ?></p>
                                                </td>
                                                <td class="align-middle">
                                                    <a href="view.php?id=<?= $sale['id'] ?>" class="text-secondary font-weight-bold text-xs" data-toggle="tooltip" data-original-title="View sale">
                                                        View
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4">
                                                <p class="text-sm mb-0">No sales found</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php
    require_once "../includes/footer.php";

} catch (Exception $e) {
    error_log("Sales Error: " . $e->getMessage());
    die("An error occurred while loading sales. Please try again later.");
}
?>

<?php 
// Helper function for status colors
function get_status_color($status) {
    switch ($status) {
        case 'paid':
            return 'success';
        case 'partial':
            return 'warning';
        case 'pending':
            return 'danger';
        default:
            return 'secondary';
    }
}
?>
