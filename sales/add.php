<?php
session_start();
require_once "../includes/functions.php";
require_once "../config/db_connect.php";

// Debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$page_title = "Add Sale";

// Get customers for dropdown
$customers_query = "SELECT id, name, email, phone FROM customers ORDER BY name";
$customers_result = mysqli_query($conn, $customers_query);

// Get products for dropdown
$products_query = "SELECT id, name, sku, price, stock_quantity, gst_rate FROM products WHERE stock_quantity > 0 ORDER BY name";
$products_result = mysqli_query($conn, $products_query);

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Debug form data
        error_log("Form submitted: " . print_r($_POST, true));
        
        mysqli_begin_transaction($conn);
        
        // Get form data
        $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : null;
        $customer_name = isset($_POST['customer_name']) ? trim($_POST['customer_name']) : '';
        $customer_phone = isset($_POST['customer_phone']) ? trim($_POST['customer_phone']) : '';
        
        error_log("Customer data - ID: $customer_id, Name: $customer_name, Phone: $customer_phone");
        
        // If walk-in customer details provided, create a temporary record
        if (empty($customer_id) && !empty($customer_name)) {
            $stmt = mysqli_prepare($conn, "INSERT INTO customers (name, phone) VALUES (?, ?)");
            if (!$stmt) {
                throw new Exception("Error preparing customer statement: " . mysqli_error($conn));
            }
            mysqli_stmt_bind_param($stmt, "ss", $customer_name, $customer_phone);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Error creating walk-in customer: " . mysqli_error($conn));
            }
            
            $customer_id = mysqli_insert_id($conn);
            error_log("Created walk-in customer with ID: $customer_id");
        }
        
        // Validate products data
        if (empty($_POST['product_ids']) || !is_array($_POST['product_ids']) || 
            empty($_POST['quantities']) || !is_array($_POST['quantities']) ||
            empty($_POST['prices']) || !is_array($_POST['prices'])) {
            throw new Exception("Please add at least one product");
        }
        
        error_log("Products data: " . print_r($_POST['product_ids'], true));
        error_log("Quantities data: " . print_r($_POST['quantities'], true));
        error_log("Prices data: " . print_r($_POST['prices'], true));
        
        $invoice_number = !empty($_POST['invoice_number']) ? trim($_POST['invoice_number']) : date('Ymd') . strtoupper(generate_random_string(4));
        $sale_date = !empty($_POST['sale_date']) ? $_POST['sale_date'] : date('Y-m-d');
        $payment_status = $_POST['payment_status'];
        $payment_method = $payment_status === 'pending' ? 'none' : $_POST['payment_method'];
        $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
        
        error_log("Sale data - Invoice: $invoice_number, Date: $sale_date, Status: $payment_status, Method: $payment_method");
        
        // Calculate totals
        $subtotal = 0;
        $total_gst = 0;
        $sale_items = [];
        
        for ($i = 0; $i < count($_POST['product_ids']); $i++) {
            $product_id = intval($_POST['product_ids'][$i]);
            $quantity = intval($_POST['quantities'][$i]);
            $price = floatval($_POST['prices'][$i]);
            
            error_log("Processing product $i - ID: $product_id, Quantity: $quantity, Price: $price");
            
            // Get product details
            $product_query = "SELECT gst_rate, stock_quantity FROM products WHERE id = ?";
            $stmt = mysqli_prepare($conn, $product_query);
            if (!$stmt) {
                throw new Exception("Error preparing product query: " . mysqli_error($conn));
            }
            mysqli_stmt_bind_param($stmt, "i", $product_id);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Error executing product query: " . mysqli_error($conn));
            }
            
            $product_result = mysqli_stmt_get_result($stmt);
            $product = mysqli_fetch_assoc($product_result);
            
            if (!$product) {
                throw new Exception("Invalid product selected (ID: $product_id)");
            }
            
            error_log("Product details: " . print_r($product, true));
            
            if ($quantity > $product['stock_quantity']) {
                throw new Exception("Insufficient stock for product ID $product_id (Requested: $quantity, Available: {$product['stock_quantity']})");
            }
            
            $row_total = $quantity * $price;
            $gst_amount = $row_total * ($product['gst_rate'] / 100);
            
            $subtotal += $row_total;
            $total_gst += $gst_amount;
            
            $sale_items[] = [
                'product_id' => $product_id,
                'quantity' => $quantity,
                'price' => $price,
                'gst_rate' => $product['gst_rate'],
                'gst_amount' => $gst_amount,
                'total_amount' => $row_total + $gst_amount
            ];
        }
        
        $total_amount = $subtotal + $total_gst;
        error_log("Calculated totals - Subtotal: $subtotal, GST: $total_gst, Total: $total_amount");
        
        // Insert sale
        $sale_query = "INSERT INTO sales (invoice_number, customer_id, sale_date, subtotal, gst_amount, total_amount, payment_method, payment_status, notes) VALUES (?, ?, STR_TO_DATE(?, '%Y-%m-%d'), ?, ?, ?, ?, ?, ?)";
        error_log("Sale query: $sale_query");
        
        $stmt = mysqli_prepare($conn, $sale_query);
        if (!$stmt) {
            throw new Exception("Error preparing sale statement: " . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($stmt, "sisdddsss", 
            $invoice_number, 
            $customer_id, 
            $sale_date,
            $subtotal, 
            $total_gst, 
            $total_amount,
            $payment_method, 
            $payment_status, 
            $notes
        );
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error creating sale: " . mysqli_error($conn));
        }
        
        $sale_id = mysqli_insert_id($conn);
        error_log("Created sale with ID: $sale_id");
        
        // Insert sale items
        foreach ($sale_items as $item) {
            $stmt = mysqli_prepare($conn, "INSERT INTO sale_items (sale_id, product_id, quantity, price, gst_rate, gst_amount, total_amount) VALUES (?, ?, ?, ?, ?, ?, ?)");
            if (!$stmt) {
                throw new Exception("Error preparing sale item statement: " . mysqli_error($conn));
            }
            
            mysqli_stmt_bind_param($stmt, "iiidddd", 
                $sale_id, 
                $item['product_id'], 
                $item['quantity'],
                $item['price'], 
                $item['gst_rate'], 
                $item['gst_amount'],
                $item['total_amount']
            );
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Error creating sale item: " . mysqli_error($conn));
            }
            error_log("Created sale item for product ID: {$item['product_id']}");
            
            // Update stock
            $new_stock = $product['stock_quantity'] - $item['quantity'];
            $update_stock = mysqli_prepare($conn, "UPDATE products SET stock_quantity = ? WHERE id = ?");
            if (!$update_stock) {
                throw new Exception("Error preparing stock update: " . mysqli_error($conn));
            }
            
            mysqli_stmt_bind_param($update_stock, "ii", $new_stock, $item['product_id']);
            if (!mysqli_stmt_execute($update_stock)) {
                throw new Exception("Error updating stock: " . mysqli_error($conn));
            }
            error_log("Updated stock for product ID: {$item['product_id']} to: $new_stock");
        }
        
        mysqli_commit($conn);
        error_log("Transaction committed successfully");
        
        handleSuccess("Sale created successfully");
        header("Location: view.php");
        exit;
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        error_log("Error in sales/add.php: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        handleError($e->getMessage());
    }
}

require_once "../includes/header.php";
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Add New Sale</h5>
                </div>
                <div class="card-body">
                    <form method="post" id="sale-form">
                        <div class="row">
                            <!-- Customer Selection -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Customer</label>
                                <select class="form-select mb-2" name="customer_id" id="customer-select">
                                    <option value="">Walk-in Customer</option>
                                    <?php while ($customer = mysqli_fetch_assoc($customers_result)): ?>
                                        <option value="<?= htmlspecialchars($customer['id']) ?>">
                                            <?= htmlspecialchars($customer['name']) ?> 
                                            <?= $customer['phone'] ? '(' . htmlspecialchars($customer['phone']) . ')' : '' ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                                
                                <!-- Walk-in Customer Details -->
                                <div id="walkin-details" class="border rounded p-3 mb-3">
                                    <div class="mb-2">
                                        <label class="form-label">Customer Name</label>
                                        <input type="text" class="form-control" name="customer_name" placeholder="Walk-in Customer Name">
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label">Phone (Optional)</label>
                                        <input type="text" class="form-control" name="customer_phone" placeholder="Phone Number">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Invoice and Date -->
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Invoice Number</label>
                                    <input type="text" class="form-control" name="invoice_number" value="<?= date('Ymd') . strtoupper(generate_random_string(4)) ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Sale Date</label>
                                    <input type="date" class="form-control" name="sale_date" value="<?= date('Y-m-d') ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Products Table -->
                        <div class="table-responsive mb-3">
                            <table class="table" id="products-table">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Quantity</th>
                                        <th>Price</th>
                                        <th>GST</th>
                                        <th>Total</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="product-row">
                                        <td>
                                            <select class="form-select product-select" name="product_ids[]" required>
                                                <option value="">Select Product</option>
                                                <?php 
                                                mysqli_data_seek($products_result, 0);
                                                while ($product = mysqli_fetch_assoc($products_result)): 
                                                ?>
                                                    <option value="<?= $product['id'] ?>" 
                                                            data-price="<?= $product['price'] ?>"
                                                            data-stock="<?= $product['stock_quantity'] ?>"
                                                            data-gst="<?= $product['gst_rate'] ?>">
                                                        <?= $product['name'] ?> (<?= $product['sku'] ?>)
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="number" class="form-control quantity-input" name="quantities[]" min="1" required>
                                        </td>
                                        <td>
                                            <input type="number" class="form-control price-input" name="prices[]" min="0" step="0.01" required>
                                        </td>
                                        <td class="gst-amount">0.00</td>
                                        <td class="row-total">0.00</td>
                                        <td>
                                            <button type="button" class="btn btn-danger btn-sm remove-row">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="6">
                                            <button type="button" class="btn btn-success btn-sm" id="add-row">
                                                <i class="fas fa-plus"></i> Add Product
                                            </button>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="3" class="text-end">Subtotal:</td>
                                        <td colspan="2" class="subtotal">0.00</td>
                                        <td></td>
                                    </tr>
                                    <tr>
                                        <td colspan="3" class="text-end">Total GST:</td>
                                        <td colspan="2" class="total-gst">0.00</td>
                                        <td></td>
                                    </tr>
                                    <tr>
                                        <td colspan="3" class="text-end"><strong>Total Amount:</strong></td>
                                        <td colspan="2" class="total-amount"><strong>0.00</strong></td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        
                        <!-- Payment Details -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Payment Status</label>
                                    <select class="form-select" name="payment_status" id="payment-status" required>
                                        <option value="pending" data-color="danger">Pending</option>
                                        <option value="paid" data-color="success">Paid</option>
                                        <option value="cancelled" data-color="secondary">Cancelled</option>
                                    </select>
                                    <div id="status-indicator" class="mt-2"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Payment Method</label>
                                    <select class="form-select" name="payment_method" id="payment-method" required>
                                        <option value="none">None</option>
                                        <option value="cash">Cash</option>
                                        <option value="card">Card</option>
                                        <option value="upi">UPI</option>
                                        <option value="bank_transfer">Bank Transfer</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Notes -->
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="3"></textarea>
                        </div>
                        
                        <!-- Submit Button -->
                        <div class="text-end">
                            <button type="submit" class="btn btn-primary">Create Sale</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const customerSelect = document.getElementById('customer-select');
    const walkinDetails = document.getElementById('walkin-details');
    const productsTable = document.getElementById('products-table');
    const addRowBtn = document.getElementById('add-row');
    const paymentStatus = document.getElementById('payment-status');
    const paymentMethod = document.getElementById('payment-method');
    const statusIndicator = document.getElementById('status-indicator');

    // Toggle walk-in customer details
    customerSelect.addEventListener('change', function() {
        walkinDetails.style.display = this.value ? 'none' : 'block';
    });
    
    // Initialize walk-in details visibility
    walkinDetails.style.display = customerSelect.value ? 'none' : 'block';
    
    // Add new product row
    addRowBtn.addEventListener('click', function() {
        const newRow = document.querySelector('.product-row').cloneNode(true);
        newRow.querySelector('.product-select').value = '';
        newRow.querySelector('.quantity-input').value = '';
        newRow.querySelector('.price-input').value = '';
        newRow.querySelector('.gst-amount').textContent = '0.00';
        newRow.querySelector('.row-total').textContent = '0.00';
        productsTable.querySelector('tbody').appendChild(newRow);
        attachRowEvents(newRow);
    });
    
    // Attach events to initial row
    document.querySelectorAll('.product-row').forEach(attachRowEvents);
    
    function attachRowEvents(row) {
        const productSelect = row.querySelector('.product-select');
        const quantityInput = row.querySelector('.quantity-input');
        const priceInput = row.querySelector('.price-input');
        const removeBtn = row.querySelector('.remove-row');
        
        // Product selection
        productSelect.addEventListener('change', function() {
            const option = this.options[this.selectedIndex];
            if (option.value) {
                priceInput.value = option.dataset.price;
                quantityInput.max = option.dataset.stock;
                updateRowCalculations(row);
            }
        });
        
        // Quantity change
        quantityInput.addEventListener('input', function() {
            updateRowCalculations(row);
        });
        
        // Price change
        priceInput.addEventListener('input', function() {
            updateRowCalculations(row);
        });
        
        // Remove row
        removeBtn.addEventListener('click', function() {
            if (document.querySelectorAll('.product-row').length > 1) {
                row.remove();
                updateTotals();
            }
        });
    }
    
    function updateRowCalculations(row) {
        const quantity = parseFloat(row.querySelector('.quantity-input').value) || 0;
        const price = parseFloat(row.querySelector('.price-input').value) || 0;
        const gstRate = parseFloat(row.querySelector('.product-select').options[row.querySelector('.product-select').selectedIndex].dataset.gst) || 0;
        
        const rowTotal = quantity * price;
        const gstAmount = rowTotal * (gstRate / 100);
        
        row.querySelector('.gst-amount').textContent = gstAmount.toFixed(2);
        row.querySelector('.row-total').textContent = (rowTotal + gstAmount).toFixed(2);
        
        updateTotals();
    }
    
    function updateTotals() {
        let subtotal = 0;
        let totalGst = 0;
        
        document.querySelectorAll('.product-row').forEach(row => {
            const quantity = parseFloat(row.querySelector('.quantity-input').value) || 0;
            const price = parseFloat(row.querySelector('.price-input').value) || 0;
            const gstRate = parseFloat(row.querySelector('.product-select').options[row.querySelector('.product-select').selectedIndex].dataset.gst) || 0;
            
            const rowTotal = quantity * price;
            const gstAmount = rowTotal * (gstRate / 100);
            
            subtotal += rowTotal;
            totalGst += gstAmount;
        });
        
        const totalAmount = subtotal + totalGst;
        
        document.querySelector('.subtotal').textContent = subtotal.toFixed(2);
        document.querySelector('.total-gst').textContent = totalGst.toFixed(2);
        document.querySelector('.total-amount').textContent = totalAmount.toFixed(2);
    }
    
    // Payment Status Indicator
    function updateStatusIndicator() {
        const selectedOption = paymentStatus.options[paymentStatus.selectedIndex];
        const color = selectedOption.dataset.color;
        const text = selectedOption.text;
        statusIndicator.innerHTML = `<span class="badge badge-sm bg-gradient-${color}">${text}</span>`;
    }

    paymentStatus.addEventListener('change', updateStatusIndicator);
    updateStatusIndicator(); // Initial state

    // Handle payment status change
    paymentStatus.addEventListener('change', function() {
        if (this.value === 'pending') {
            paymentMethod.value = 'none';
            paymentMethod.disabled = true;
        } else {
            paymentMethod.disabled = false;
            if (paymentMethod.value === 'none') {
                paymentMethod.value = 'cash';
            }
        }
    });

    // Initial state
    if (paymentStatus.value === 'pending') {
        paymentMethod.value = 'none';
        paymentMethod.disabled = true;
    }
});
</script>

<?php require_once "../includes/footer.php"; ?>
