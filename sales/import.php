<?php
session_start();
require_once "../includes/functions.php";
require_once "../config/db_connect.php";
require_once "../includes/sale_notifications.php";

$page_title = "Import Sales";

// Handle file upload
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["file"])) {
    try {
        mysqli_begin_transaction($conn);

        $file = $_FILES["file"];
        $allowed_types = ['text/csv', 'application/vnd.ms-excel'];
        
        // Validate file
        if (!in_array($file['type'], $allowed_types)) {
            throw new Exception("Invalid file type. Please upload a CSV file.");
        }

        // Open file
        $handle = fopen($file['tmp_name'], "r");
        if ($handle === false) {
            throw new Exception("Failed to open file.");
        }

        // Read header row
        $header = fgetcsv($handle);
        if ($header === false) {
            throw new Exception("Failed to read CSV header.");
        }

        // Define required columns and their indexes
        $required_columns = [
            'invoice_number', 'customer_name', 'customer_email',
            'product_sku', 'quantity', 'unit_price',
            'payment_method', 'payment_status'
        ];
        
        // Get column indexes
        $indexes = [];
        foreach ($required_columns as $column) {
            $index = array_search($column, array_map('strtolower', $header));
            if ($index === false) {
                throw new Exception("Missing required column: $column");
            }
            $indexes[$column] = $index;
        }

        // Optional columns
        $indexes['customer_phone'] = array_search('customer_phone', array_map('strtolower', $header));

        $success_count = 0;
        $error_count = 0;
        $row_number = 1;
        $errors = [];

        // Process each row
        while (($row = fgetcsv($handle)) !== false) {
            $row_number++;
            try {
                // Get values from correct columns
                $invoice_number = sanitize_input($row[$indexes['invoice_number']]);
                $customer_name = sanitize_input($row[$indexes['customer_name']]);
                $customer_email = sanitize_input($row[$indexes['customer_email']]);
                $customer_phone = sanitize_input($row[$indexes['customer_phone']] ?? '');
                $product_sku = sanitize_input($row[$indexes['product_sku']]);
                $quantity = intval($row[$indexes['quantity']]);
                $unit_price = floatval($row[$indexes['unit_price']]);
                $payment_method = strtolower(sanitize_input($row[$indexes['payment_method']]));
                $payment_status = strtolower(sanitize_input($row[$indexes['payment_status']]));

                // Validate data
                if (empty($invoice_number) || empty($customer_name) || empty($product_sku)) {
                    throw new Exception("Missing required data");
                }

                // Get or create customer
                $customer_id = null;
                $customer_sql = "SELECT id FROM customers WHERE email = ?";
                $stmt = mysqli_prepare($conn, $customer_sql);
                mysqli_stmt_bind_param($stmt, "s", $customer_email);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_store_result($stmt);

                if (mysqli_stmt_num_rows($stmt) > 0) {
                    mysqli_stmt_bind_result($stmt, $customer_id);
                    mysqli_stmt_fetch($stmt);
                } else {
                    $customer_sql = "INSERT INTO customers (name, email, phone) VALUES (?, ?, ?)";
                    $stmt = mysqli_prepare($conn, $customer_sql);
                    mysqli_stmt_bind_param($stmt, "sss", $customer_name, $customer_email, $customer_phone);
                    mysqli_stmt_execute($stmt);
                    $customer_id = mysqli_insert_id($conn);
                }
                mysqli_stmt_close($stmt);

                // Get product details
                $product_sql = "SELECT id, gst_rate, stock_quantity FROM products WHERE sku = ?";
                $stmt = mysqli_prepare($conn, $product_sql);
                mysqli_stmt_bind_param($stmt, "s", $product_sku);
                mysqli_stmt_execute($stmt);
                $product_result = mysqli_stmt_get_result($stmt);
                $product = mysqli_fetch_assoc($product_result);
                mysqli_stmt_close($stmt);

                if (!$product) {
                    throw new Exception("Product with SKU '$product_sku' not found");
                }

                if ($product['stock_quantity'] < $quantity) {
                    throw new Exception("Insufficient stock for product SKU '$product_sku'");
                }

                // Calculate amounts
                $subtotal = $quantity * $unit_price;
                $gst_amount = $subtotal * ($product['gst_rate'] / 100);
                $total_amount = $subtotal + $gst_amount;

                // Create sale
                $sale_sql = "INSERT INTO sales (invoice_number, customer_id, subtotal, gst_amount, total_amount, payment_method, payment_status) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $sale_stmt = mysqli_prepare($conn, $sale_sql);
                mysqli_stmt_bind_param($sale_stmt, "siddiss", 
                    $invoice_number, $customer_id, $subtotal, 
                    $gst_amount, $total_amount, $payment_method, $payment_status
                );
                mysqli_stmt_execute($sale_stmt);
                $sale_id = mysqli_insert_id($conn);
                mysqli_stmt_close($sale_stmt);

                // Create sale item
                $item_sql = "INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, gst_rate, gst_amount, total_amount) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $item_stmt = mysqli_prepare($conn, $item_sql);
                mysqli_stmt_bind_param($item_stmt, "iiidddd", 
                    $sale_id, $product['id'], $quantity, 
                    $unit_price, $product['gst_rate'], 
                    $gst_amount, $total_amount
                );
                mysqli_stmt_execute($item_stmt);
                mysqli_stmt_close($item_stmt);

                // Update stock
                $new_stock = $product['stock_quantity'] - $quantity;
                $stock_sql = "UPDATE products SET stock_quantity = ? WHERE id = ?";
                $stock_stmt = mysqli_prepare($conn, $stock_sql);
                mysqli_stmt_bind_param($stock_stmt, "ii", $new_stock, $product['id']);
                mysqli_stmt_execute($stock_stmt);
                mysqli_stmt_close($stock_stmt);

                $success_count++;

            } catch (Exception $e) {
                $error_count++;
                $errors[] = "Row $row_number: " . $e->getMessage();
                notify_sale_import_error($e->getMessage(), implode(", ", $row));
            }
        }

        fclose($handle);

        if (count($errors) > 0) {
            if ($success_count > 0) {
                mysqli_commit($conn);
                notify_sale_import_success($success_count, $error_count);
            } else {
                mysqli_rollback($conn);
                throw new Exception(implode("\n", $errors));
            }
        } else {
            mysqli_commit($conn);
            notify_sale_import_success($success_count);
        }

        header("Location: " . $_SERVER['PHP_SELF']);
        exit;

    } catch (Exception $e) {
        mysqli_rollback($conn);
        notify_sale_import_error($e->getMessage());
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

require_once "../includes/header.php";
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Import Sales</h5>
                    <div>
                        <a href="export.php" class="btn btn-info btn-sm">
                            <i class="fas fa-file-export me-2"></i>Export Sales
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <form method="post" enctype="multipart/form-data" class="mb-4">
                        <div class="mb-3">
                            <label class="form-label">Choose CSV File</label>
                            <input type="file" name="file" class="form-control" accept=".csv" required>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-file-import me-2"></i>Import Sales
                        </button>
                    </form>

                    <div class="mt-4">
                        <h6 class="mb-3">CSV Format Guide</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Column</th>
                                        <th>Description</th>
                                        <th>Required</th>
                                        <th>Example</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>invoice_number</td>
                                        <td>Unique invoice number</td>
                                        <td>Yes</td>
                                        <td>INV001</td>
                                    </tr>
                                    <tr>
                                        <td>customer_name</td>
                                        <td>Customer's full name</td>
                                        <td>Yes</td>
                                        <td>John Doe</td>
                                    </tr>
                                    <tr>
                                        <td>customer_email</td>
                                        <td>Customer's email address</td>
                                        <td>Yes</td>
                                        <td>john@example.com</td>
                                    </tr>
                                    <tr>
                                        <td>customer_phone</td>
                                        <td>Customer's phone number</td>
                                        <td>No</td>
                                        <td>1234567890</td>
                                    </tr>
                                    <tr>
                                        <td>product_sku</td>
                                        <td>Product SKU</td>
                                        <td>Yes</td>
                                        <td>SKU001</td>
                                    </tr>
                                    <tr>
                                        <td>quantity</td>
                                        <td>Quantity of product</td>
                                        <td>Yes</td>
                                        <td>2</td>
                                    </tr>
                                    <tr>
                                        <td>unit_price</td>
                                        <td>Price per unit</td>
                                        <td>Yes</td>
                                        <td>999.99</td>
                                    </tr>
                                    <tr>
                                        <td>payment_method</td>
                                        <td>Method of payment (cash/card)</td>
                                        <td>Yes</td>
                                        <td>cash</td>
                                    </tr>
                                    <tr>
                                        <td>payment_status</td>
                                        <td>Status of payment (paid/pending)</td>
                                        <td>Yes</td>
                                        <td>paid</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-3">
                            <h6>Example CSV Content:</h6>
                            <pre><code>invoice_number,customer_name,customer_email,customer_phone,product_sku,quantity,unit_price,payment_method,payment_status
INV001,John Doe,john@example.com,1234567890,SKU001,2,999.99,cash,paid
INV002,Jane Smith,jane@example.com,9876543210,SKU002,1,499.99,card,paid</code></pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once "../includes/footer.php"; ?>
