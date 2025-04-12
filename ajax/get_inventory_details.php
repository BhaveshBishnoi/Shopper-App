<?php
require_once "../config/db_connect.php";

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid request");
}

$id = intval($_GET['id']);

$query = "SELECT i.*, 
          p.name as product_name, p.sku, p.description as product_description,
          d.name as distributor_name, d.contact_person, d.email as distributor_email, d.phone as distributor_phone,
          (i.quantity * i.purchase_price) as total_cost
          FROM inventory i
          JOIN products p ON i.product_id = p.id
          LEFT JOIN distributors d ON i.distributor_id = d.id
          WHERE i.id = $id";

$result = mysqli_query($conn, $query);
$record = mysqli_fetch_assoc($result);

if (!$record) {
    die("Record not found");
}
?>

<div class="row">
    <div class="col-md-6">
        <h6>Product Information</h6>
        <p><strong>Name:</strong> <?= htmlspecialchars($record['product_name']) ?></p>
        <p><strong>SKU:</strong> <?= htmlspecialchars($record['sku']) ?></p>
        <p><strong>Description:</strong> <?= htmlspecialchars($record['product_description']) ?></p>
    </div>
    <div class="col-md-6">
        <h6>Transaction Details</h6>
        <p><strong>Date:</strong> <?= date('d M Y', strtotime($record['transaction_date'])) ?></p>
        <p><strong>Quantity:</strong> <?= $record['quantity'] ?></p>
        <p><strong>Unit Price:</strong> ₹<?= number_format($record['purchase_price'], 2) ?></p>
        <p><strong>Total Cost:</strong> ₹<?= number_format($record['total_cost'], 2) ?></p>
    </div>
</div>

<div class="row mt-3">
    <div class="col-md-6">
        <h6>Distributor Information</h6>
        <?php if ($record['distributor_name']): ?>
            <p><strong>Name:</strong> <?= htmlspecialchars($record['distributor_name']) ?></p>
            <p><strong>Contact:</strong> <?= htmlspecialchars($record['contact_person']) ?></p>
            <p><strong>Email:</strong> <?= htmlspecialchars($record['distributor_email']) ?></p>
            <p><strong>Phone:</strong> <?= htmlspecialchars($record['distributor_phone']) ?></p>
        <?php else: ?>
            <p>No distributor information available</p>
        <?php endif; ?>
    </div>
    <div class="col-md-6">
        <h6>Payment Information</h6>
        <p>
            <strong>Status:</strong> 
            <span class="badge bg-<?= $record['payment_status'] === 'paid' ? 'success' : 'warning' ?>">
                <?= ucfirst($record['payment_status']) ?>
            </span>
        </p>
        <p><strong>Reference:</strong> <?= $record['reference_number'] ? htmlspecialchars($record['reference_number']) : 'N/A' ?></p>
        <p><strong>Notes:</strong> <?= $record['notes'] ? htmlspecialchars($record['notes']) : 'N/A' ?></p>
    </div>
</div>