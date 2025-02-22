<?php
session_start();
require_once "../includes/functions.php";
require_once "../config/db_connect.php";

$page_title = "View Sale";

if (!isset($_GET['id'])) {
    handleError("No sale specified", "index.php");
}

$id = intval($_GET['id']);

// Get sale details with customer info
$query = "SELECT s.*, c.name as customer_name, c.email, c.phone, c.address, c.is_walkin
          FROM sales s
          LEFT JOIN customers c ON s.customer_id = c.id
          WHERE s.id = ?";

if ($stmt = mysqli_prepare($conn, $query)) {
    mysqli_stmt_bind_param($stmt, "i", $id);
    
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        
        if ($sale = mysqli_fetch_assoc($result)) {
            // Get sale items
            $items_query = "SELECT si.*, p.name as product_name, p.sku
                           FROM sale_items si
                           JOIN products p ON si.product_id = p.id
                           WHERE si.sale_id = ?";
            
            $items_stmt = mysqli_prepare($conn, $items_query);
            mysqli_stmt_bind_param($items_stmt, "i", $id);
            mysqli_stmt_execute($items_stmt);
            $items_result = mysqli_stmt_get_result($items_stmt);
            
        } else {
            handleError("Sale not found", "index.php");
        }
    } else {
        handleError("Error retrieving sale", "index.php");
    }
    mysqli_stmt_close($stmt);
} else {
    handleError("Database error", "index.php");
}

// Get shop details from settings
$settings_query = "SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('company_name', 'company_address', 'company_phone', 'company_email', 'company_gstin')";
$settings_result = mysqli_query($conn, $settings_query);
$settings = [];
while ($row = mysqli_fetch_assoc($settings_result)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Calculate payment status variables
$status_class = $sale['payment_status'] === 'paid' ? 'success' : 'danger';
$status_text = ucfirst($sale['payment_status']);
$bg_color = $sale['payment_status'] === 'paid' ? '#DEF7EC' : '#FDE8E8';
$text_color = $sale['payment_status'] === 'paid' ? '#046C4E' : '#DC2626';

// Check if this is a print request
$is_print = isset($_GET['print']);

if ($is_print) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Invoice #<?= htmlspecialchars($sale['invoice_number']) ?></title>
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap');
            
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: 'Inter', sans-serif;
                line-height: 1.5;
                color: #333;
                margin: 0;
                padding: 20px;
                background: #fff;
            }
            
            .invoice {
                max-width: 800px;
                margin: 0 auto;
                padding: 30px;
            }
            
            .header {
                margin-bottom: 30px;
                padding-bottom: 20px;
                border-bottom: 1px solid #eee;
            }
            
            .title {
                font-size: 24px;
                color: #333;
                margin-bottom: 5px;
                text-align: right;
            }
            
            .invoice-number {
                color: #666;
                font-size: 14px;
                text-align: right;
            }
            
            .info-section {
                display: flex;
                justify-content: space-between;
                margin-bottom: 30px;
            }
            
            .info-block {
                max-width: 250px;
            }
            
            .info-block h3 {
                font-size: 12px;
                text-transform: uppercase;
                color: #666;
                margin-bottom: 10px;
                letter-spacing: 0.5px;
                text-align: left;
            }
            
            .info-block p {
                font-size: 14px;
                margin-bottom: 5px;
                color: #333;
                text-align: left;
            }
            
            table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 30px;
                font-size: 14px;
            }
            
            th {
                text-align: left;
                padding: 12px 10px;
                background: #f8f8f8;
                border-bottom: 2px solid #eee;
                font-weight: 500;
                color: #666;
            }
            
            td {
                padding: 12px 10px;
                border-bottom: 1px solid #eee;
            }
            
            .amount-col {
                text-align: right;
            }
            
            .totals {
                width: 300px;
                margin-left: auto;
            }
            
            .total-row {
                display: flex;
                justify-content: space-between;
                padding: 8px 0;
                font-size: 14px;
            }
            
            .total-row.final {
                font-weight: 600;
                font-size: 16px;
                border-top: 2px solid #eee;
                margin-top: 8px;
                padding-top: 12px;
            }
            
            .status-badge {
                display: inline-block;
                padding: 5px 10px;
                border-radius: 4px;
                font-size: 12px;
                font-weight: 500;
                text-transform: uppercase;
            }
            
            .status-badge.paid {
                background: #e8f5e9;
                color: #2e7d32;
            }
            
            .status-badge.pending {
                background: #fff3e0;
                color: #e65100;
            }
            
            .notes {
                margin-top: 30px;
                padding-top: 20px;
                border-top: 1px solid #eee;
                font-size: 14px;
                color: #666;
            }
            
            @media print {
                body {
                    padding: 0;
                }
                
                .invoice {
                    padding: 20px;
                }
            }
        </style>
        <script>
            window.onload = function() {
                window.print();
            }
        </script>
    </head>
    <body>
        <div class="invoice">
            <div class="header">
                <div class="info-section">
                    <div class="info-block" style="flex: 1;">
                        <h3>From</h3>
                        <p><strong><?= htmlspecialchars($settings['company_name']) ?></strong></p>
                        <?php if ($settings['company_address']): ?>
                            <p><?= nl2br(htmlspecialchars($settings['company_address'])) ?></p>
                        <?php endif; ?>
                        <?php if ($settings['company_phone']): ?>
                            <p>Phone: <?= htmlspecialchars($settings['company_phone']) ?></p>
                        <?php endif; ?>
                        <?php if ($settings['company_email']): ?>
                            <p>Email: <?= htmlspecialchars($settings['company_email']) ?></p>
                        <?php endif; ?>
                        <?php if ($settings['company_gstin']): ?>
                            <p>GSTIN: <?= htmlspecialchars($settings['company_gstin']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div style="text-align: right; min-width: 200px;">
                        <div class="title">INVOICE</div>
                        <div class="invoice-number">#<?= htmlspecialchars($sale['invoice_number']) ?></div>
                    </div>
                </div>
            </div>

            <div class="info-section">
                <div class="info-block">
                    <h3>Bill To</h3>
                    <p><strong><?= htmlspecialchars($sale['customer_name']) ?></strong></p>
                    <?php if ($sale['phone']): ?>
                        <p><?= htmlspecialchars($sale['phone']) ?></p>
                    <?php endif; ?>
                    <?php if ($sale['email']): ?>
                        <p><?= htmlspecialchars($sale['email']) ?></p>
                    <?php endif; ?>
                    <?php if ($sale['address']): ?>
                        <p><?= nl2br(htmlspecialchars($sale['address'])) ?></p>
                    <?php endif; ?>
                </div>
                
                <div class="info-block">
                    <h3>Invoice Details</h3>
                    <p>Date: <?= date('d M Y', strtotime($sale['sale_date'])) ?></p>
                    <p>Payment: <?= ucfirst($sale['payment_method']) ?></p>
                    <p>Status: <span class="status-badge <?= $sale['payment_status'] ?>"><?= ucfirst($sale['payment_status']) ?></span></p>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>SKU</th>
                        <th class="amount-col">Price</th>
                        <th class="amount-col">Qty</th>
                        <th class="amount-col">GST</th>
                        <th class="amount-col">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php mysqli_data_seek($items_result, 0); ?>
                    <?php while ($item = mysqli_fetch_assoc($items_result)): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['product_name']) ?></td>
                            <td><?= htmlspecialchars($item['sku']) ?></td>
                            <td class="amount-col"><?= format_currency($item['price']) ?></td>
                            <td class="amount-col"><?= $item['quantity'] ?></td>
                            <td class="amount-col">
                                <?= format_currency($item['gst_amount']) ?>
                                <small>(<?= $item['gst_rate'] ?>%)</small>
                            </td>
                            <td class="amount-col"><?= format_currency($item['total_amount']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

            <div class="totals">
                <div class="total-row">
                    <span>Subtotal</span>
                    <span><?= format_currency($sale['subtotal']) ?></span>
                </div>
                <div class="total-row">
                    <span>GST</span>
                    <span><?= format_currency($sale['gst_amount']) ?></span>
                </div>
                <div class="total-row final">
                    <span>Total</span>
                    <span><?= format_currency($sale['total_amount']) ?></span>
                </div>
            </div>

            <?php if ($sale['notes']): ?>
                <div class="notes">
                    <h3>Notes</h3>
                    <p><?= nl2br(htmlspecialchars($sale['notes'])) ?></p>
                </div>
            <?php endif; ?>
        </div>
    </body>
    </html>
<?php } else { ?>
    <?php require_once "../includes/header.php"; ?>
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header pb-0">
                        <div class="row">
                            <div class="col-6 d-flex align-items-center">
                                <h6 class="mb-0">Invoice #<?= htmlspecialchars($sale['invoice_number']) ?></h6>
                            </div>
                            <div class="col-6 text-end">
                                <div class="status-badge" style="display: inline-block; background-color: <?= $bg_color ?>; color: <?= $text_color ?>; padding: 8px 12px; border-radius: 8px; font-weight: 600; font-size: 0.75rem; text-transform: uppercase; margin-right: 10px;">
                                    <?= $status_text ?>
                                </div>
                                <?php if ($sale['payment_status'] !== 'paid'): ?>
                                    <button onclick="markAsPaid(<?= $sale['id'] ?>)" class="btn btn-sm bg-gradient-success mb-0">
                                        <i class="fas fa-check me-2"></i>Mark as Paid
                                    </button>
                                <?php endif; ?>
                                <a href="?id=<?= $id ?>&print=1" target="_blank" class="btn btn-sm bg-gradient-dark mb-0">
                                    <i class="fas fa-print me-2"></i>Print Invoice
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h6 class="mb-3">Customer Information</h6>
                                <div class="d-flex flex-column">
                                    <span class="mb-2">
                                        <strong><?= htmlspecialchars($sale['customer_name']) ?></strong>
                                        <?php if ($sale['is_walkin']): ?>
                                            <span class="badge badge-sm bg-gradient-secondary">Walk-in</span>
                                        <?php endif; ?>
                                    </span>
                                    <?php if ($sale['phone']): ?>
                                        <span class="text-sm mb-2">Phone: <?= htmlspecialchars($sale['phone']) ?></span>
                                    <?php endif; ?>
                                    <?php if ($sale['email']): ?>
                                        <span class="text-sm mb-2">Email: <?= htmlspecialchars($sale['email']) ?></span>
                                    <?php endif; ?>
                                    <?php if ($sale['address']): ?>
                                        <span class="text-sm"><?= nl2br(htmlspecialchars($sale['address'])) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <h6 class="mb-3">Sale Information</h6>
                                <div class="d-flex flex-column align-items-end">
                                    <span class="mb-2">Invoice: #<?= htmlspecialchars($sale['invoice_number']) ?></span>
                                    <span class="mb-2">Date: <?= date('d M Y', strtotime($sale['sale_date'])) ?></span>
                                    <span class="mb-2">Payment Method: <?= ucfirst($sale['payment_method']) ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table align-items-center mb-0">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>SKU</th>
                                        <th>Price</th>
                                        <th>Quantity</th>
                                        <th>GST</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php mysqli_data_seek($items_result, 0); ?>
                                    <?php while ($item = mysqli_fetch_assoc($items_result)): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($item['product_name']) ?></td>
                                            <td><?= htmlspecialchars($item['sku']) ?></td>
                                            <td><?= format_currency($item['price']) ?></td>
                                            <td><?= $item['quantity'] ?></td>
                                            <td>
                                                <?= format_currency($item['gst_amount']) ?>
                                                (<?= $item['gst_rate'] ?>%)
                                            </td>
                                            <td><?= format_currency($item['total_amount']) ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="5" class="text-end pe-2">Subtotal:</td>
                                        <td><?= format_currency($sale['subtotal']) ?></td>
                                    </tr>
                                    <tr>
                                        <td colspan="5" class="text-end pe-2">GST:</td>
                                        <td><?= format_currency($sale['gst_amount']) ?></td>
                                    </tr>
                                    <tr>
                                        <td colspan="5" class="text-end pe-2">Total:</td>
                                        <td><?= format_currency($sale['total_amount']) ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <?php if ($sale['notes']): ?>
                            <div class="mt-4">
                                <h6 class="mb-3">Notes</h6>
                                <p class="text-sm mb-0"><?= nl2br(htmlspecialchars($sale['notes'])) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    function markAsPaid(saleId) {
        if (confirm('Are you sure you want to mark this sale as paid?')) {
            window.location.href = `mark_paid.php?id=${saleId}`;
        }
    }
    </script>

    <?php 
    require_once "../includes/footer.php"; 
}
?>
