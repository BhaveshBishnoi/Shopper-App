<?php
session_start();
require_once "../includes/functions.php";
require_once "../config/db_connect.php";
require_once "../includes/header.php";

if (!isset($_GET['id'])) {
    handleError("No customer specified", "index.php");
}

$id = intval($_GET['id']);
$query = "SELECT * FROM customers WHERE id = ?";

if ($stmt = mysqli_prepare($conn, $query)) {
    mysqli_stmt_bind_param($stmt, "i", $id);
    
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        
        if ($customer = mysqli_fetch_assoc($result)) {
            // Get customer's sales history
            $sales_query = "SELECT s.*, 
                           COUNT(si.id) as total_items,
                           GROUP_CONCAT(CONCAT(p.name, ' (', si.quantity, ')') SEPARATOR ', ') as products
                           FROM sales s
                           LEFT JOIN sale_items si ON s.id = si.sale_id
                           LEFT JOIN products p ON si.product_id = p.id
                           WHERE s.customer_id = ?
                           GROUP BY s.id
                           ORDER BY s.created_at DESC";
            
            $sales_stmt = mysqli_prepare($conn, $sales_query);
            mysqli_stmt_bind_param($sales_stmt, "i", $id);
            mysqli_stmt_execute($sales_stmt);
            $sales_result = mysqli_stmt_get_result($sales_stmt);
            
            // Calculate total statistics
            $total_spent = 0;
            $total_orders = mysqli_num_rows($sales_result);
            while ($sale = mysqli_fetch_assoc($sales_result)) {
                $total_spent += $sale['total_amount'];
            }
            mysqli_data_seek($sales_result, 0);
            
        } else {
            handleError("Customer not found", "index.php");
        }
    } else {
        handleError("Error retrieving customer", "index.php");
    }
    mysqli_stmt_close($stmt);
} else {
    handleError("Database error", "index.php");
}
?>

<div class="container-fluid">
    <!-- Customer Details Card -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Customer Details</h5>
                </div>
                <div class="card-body">
                    <table class="table">
                        <tr>
                            <th width="30%">Name</th>
                            <td><?php echo htmlspecialchars($customer['name']); ?></td>
                        </tr>
                        <tr>
                            <th>Email</th>
                            <td><?php echo htmlspecialchars($customer['email']); ?></td>
                        </tr>
                        <tr>
                            <th>Phone</th>
                            <td><?php echo htmlspecialchars($customer['phone']); ?></td>
                        </tr>
                        <tr>
                            <th>Address</th>
                            <td><?php echo nl2br(htmlspecialchars($customer['address'])); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Customer Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="border rounded p-3 text-center">
                                <h6>Total Orders</h6>
                                <h3><?php echo $total_orders; ?></h3>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded p-3 text-center">
                                <h6>Total Spent</h6>
                                <h3><?php echo format_currency($total_spent); ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Order History -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Order History</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover datatable">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Date</th>
                            <th>Products</th>
                            <th>Total Items</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($sale = mysqli_fetch_assoc($sales_result)): ?>
                        <tr>
                            <td>#<?php echo $sale['id']; ?></td>
                            <td><?php echo format_date($sale['created_at']); ?></td>
                            <td><?php echo htmlspecialchars($sale['products']); ?></td>
                            <td><?php echo $sale['total_items']; ?></td>
                            <td><?php echo format_currency($sale['total_amount']); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $sale['payment_status'] == 'paid' ? 'success' : 'warning'; ?>">
                                    <?php echo ucfirst($sale['payment_status']); ?>
                                </span>
                            </td>
                            <td>
                                <a href="../sales/view.php?id=<?php echo $sale['id']; ?>" 
                                   class="btn btn-info btn-sm text-white">
                                    <i class="fas fa-eye"></i>
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

<?php
require_once "../includes/footer.php";
?>
