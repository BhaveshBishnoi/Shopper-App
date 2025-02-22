<?php
session_start();
require_once "../config/db_connect.php";
require_once "../includes/functions.php";
require_once "../includes/notifications.php";

$page_title = "GST Management";

// Get all unique GST rates and their usage
$sql = "SELECT 
    p.gst_rate,
    COUNT(p.id) as products_count,
    COUNT(DISTINCT si.sale_id) as sales_count,
    SUM(si.quantity * si.price) as total_sales_amount,
    SUM(si.gst_amount) as total_gst_collected
FROM products p
LEFT JOIN sale_items si ON p.id = si.product_id
GROUP BY p.gst_rate
ORDER BY p.gst_rate";

try {
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        throw new Exception(mysqli_error($conn));
    }
    $gst_data = mysqli_fetch_all($result, MYSQLI_ASSOC);
    
    // Calculate summary statistics
    $total_gst_earned = 0;
    $total_gst_products = 0;
    $total_sales_amount = 0;
    
    foreach ($gst_data as $row) {
        $total_gst_earned += $row['total_gst_collected'];
        $total_gst_products += $row['products_count'];
        $total_sales_amount += $row['total_sales_amount'];
    }
    
} catch (Exception $e) {
    add_notification($e->getMessage(), "error");
}

require_once "../includes/header.php";
?>

<div class="container-fluid py-4">
    <!-- GST Summary Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
            <div class="card">
                <div class="card-body p-3">
                    <div class="row">
                        <div class="col-8">
                            <div class="numbers">
                                <p class="text-sm mb-0 text-capitalize font-weight-bold">Total GST Earned</p>
                                <h5 class="font-weight-bolder mb-0">
                                    ₹<?= number_format($total_gst_earned, 2) ?>
                                </h5>
                            </div>
                        </div>
                        <div class="col-4 text-end">
                            <div class="icon icon-shape bg-gradient-primary shadow text-center border-radius-md">
                                <i class="fas fa-rupee-sign text-lg opacity-10" aria-hidden="true"></i>
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
                                <p class="text-sm mb-0 text-capitalize font-weight-bold">Total Products with GST</p>
                                <h5 class="font-weight-bolder mb-0">
                                    <?= number_format($total_gst_products) ?>
                                </h5>
                            </div>
                        </div>
                        <div class="col-4 text-end">
                            <div class="icon icon-shape bg-gradient-success shadow text-center border-radius-md">
                                <i class="fas fa-box text-lg opacity-10" aria-hidden="true"></i>
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
                                <p class="text-sm mb-0 text-capitalize font-weight-bold">Total Sales Amount</p>
                                <h5 class="font-weight-bolder mb-0">
                                    ₹<?= number_format($total_sales_amount, 2) ?>
                                </h5>
                            </div>
                        </div>
                        <div class="col-4 text-end">
                            <div class="icon icon-shape bg-gradient-warning shadow text-center border-radius-md">
                                <i class="fas fa-chart-bar text-lg opacity-10" aria-hidden="true"></i>
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
                                <p class="text-sm mb-0 text-capitalize font-weight-bold">Average GST Rate</p>
                                <h5 class="font-weight-bolder mb-0">
                                    <?= number_format(($total_gst_earned / $total_sales_amount) * 100, 1) ?>%
                                </h5>
                            </div>
                        </div>
                        <div class="col-4 text-end">
                            <div class="icon icon-shape bg-gradient-info shadow text-center border-radius-md">
                                <i class="fas fa-percentage text-lg opacity-10" aria-hidden="true"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header pb-0">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">GST Management</h5>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addGstModal">
                            <i class="fas fa-plus me-2"></i>Add New GST Rate
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="gstTable">
                            <thead>
                                <tr>
                                    <th>GST Rate (%)</th>
                                    <th>Products Using Rate</th>
                                    <th>Number of Sales</th>
                                    <th>Total Sales Amount</th>
                                    <th>Total GST Collected</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($gst_data as $gst): ?>
                                <tr>
                                    <td><?= htmlspecialchars($gst['gst_rate']) ?>%</td>
                                    <td><?= htmlspecialchars($gst['products_count']) ?></td>
                                    <td><?= htmlspecialchars($gst['sales_count']) ?></td>
                                    <td>₹<?= number_format($gst['total_sales_amount'], 2) ?></td>
                                    <td>₹<?= number_format($gst['total_gst_collected'], 2) ?></td>
                                    <td>
                                        <button class="btn btn-info btn-sm view-products" data-gst-rate="<?= htmlspecialchars($gst['gst_rate']) ?>" title="View Products">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($gst['products_count'] == 0): ?>
                                        <button class="btn btn-danger btn-sm delete-gst" data-gst-rate="<?= htmlspecialchars($gst['gst_rate']) ?>" title="Delete GST Rate">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-primary">
                                    <th>Total</th>
                                    <th><?= number_format($total_gst_products) ?></th>
                                    <th><?= array_sum(array_column($gst_data, 'sales_count')) ?></th>
                                    <th>₹<?= number_format($total_sales_amount, 2) ?></th>
                                    <th>₹<?= number_format($total_gst_earned, 2) ?></th>
                                    <th></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add GST Modal -->
<div class="modal fade" id="addGstModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New GST Rate</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addGstForm" action="update_gst.php" method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="gst_rate" class="form-label">GST Rate (%)</label>
                        <input type="number" step="0.01" class="form-control" id="gst_rate" name="gst_rate" required>
                    </div>
                    <div class="mb-3">
                        <label for="products" class="form-label">Apply to Products (Optional)</label>
                        <select class="form-select" id="products" name="products[]" multiple>
                            <?php
                            $products_sql = "SELECT id, name, sku FROM products ORDER BY name";
                            $products_result = mysqli_query($conn, $products_sql);
                            while ($product = mysqli_fetch_assoc($products_result)) {
                                echo "<option value='" . $product['id'] . "'>" . htmlspecialchars($product['name']) . " (" . $product['sku'] . ")</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Add GST Rate</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Products Modal -->
<div class="modal fade" id="viewProductsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Products with GST Rate: <span id="modalGstRate"></span>%</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table" id="productsTable">
                        <thead>
                            <tr>
                                <th>SKU</th>
                                <th>Name</th>
                                <th>Price</th>
                                <th>GST Amount</th>
                                <th>Total Price</th>
                            </tr>
                        </thead>
                        <tbody id="productsTableBody">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTable
    $('#gstTable').DataTable({
        order: [[0, 'asc']]
    });

    // Handle View Products button click
    $('.view-products').click(function() {
        const gstRate = $(this).data('gst-rate');
        $('#modalGstRate').text(gstRate);
        
        // Fetch products with this GST rate
        fetch(`get_products_by_gst.php?gst_rate=${gstRate}`)
            .then(response => response.json())
            .then(products => {
                const tbody = $('#productsTableBody');
                tbody.empty();
                
                products.forEach(product => {
                    const gstAmount = (product.price * product.gst_rate / 100).toFixed(2);
                    const totalPrice = (parseFloat(product.price) + parseFloat(gstAmount)).toFixed(2);
                    
                    tbody.append(`
                        <tr>
                            <td>${product.sku}</td>
                            <td>${product.name}</td>
                            <td>₹${product.price}</td>
                            <td>₹${gstAmount}</td>
                            <td>₹${totalPrice}</td>
                        </tr>
                    `);
                });
                
                $('#viewProductsModal').modal('show');
            })
            .catch(error => {
                notificationManager.showError('Error fetching products: ' + error.message);
            });
    });
});
</script>

<?php require_once "../includes/footer.php"; ?>
