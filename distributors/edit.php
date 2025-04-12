<?php
session_start();
require_once "../config/db_connect.php";
require_once "../includes/functions.php";

// Check if distributor ID is provided
if (!isset($_GET['id'])) {
    $_SESSION['error'] = "Distributor ID not provided";
    header("Location: index.php");
    exit;
}

$id = intval($_GET['id']);

// Get distributor details
$stmt = mysqli_prepare($conn, "SELECT * FROM distributors WHERE id = ?");
if (!$stmt) {
    $_SESSION['error'] = "Database error: " . mysqli_error($conn);
    header("Location: index.php");
    exit;
}

mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    $_SESSION['error'] = "Distributor not found";
    header("Location: index.php");
    exit;
}

$distributor = mysqli_fetch_assoc($result);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize_input($_POST['name']);
    $contact_person = sanitize_input($_POST['contact_person']);
    $phone = sanitize_input($_POST['phone']);
    $email = sanitize_input($_POST['email']);
    $address = sanitize_input($_POST['address']);
    $total_goods = floatval($_POST['total_goods_received']);
    $total_paid = floatval($_POST['total_amount_paid']);
    
    try {
        // Calculate pending amount
        $pending_amount = $total_goods - $total_paid;
        
        // Validate inputs
        if (empty($name)) throw new Exception("Distributor name is required");
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format");
        }
        if ($total_goods < 0) throw new Exception("Total goods received cannot be negative");
        if ($total_paid < 0) throw new Exception("Total amount paid cannot be negative");
        if ($total_paid > $total_goods) throw new Exception("Amount paid cannot exceed total goods value");
        
        // Update distributor
        $stmt = mysqli_prepare($conn, "UPDATE distributors SET 
            name = ?, 
            contact_person = ?, 
            phone = ?, 
            email = ?, 
            address = ?, 
            total_goods_received = ?, 
            total_amount_paid = ?, 
            pending_amount = ?,
            updated_at = NOW() 
            WHERE id = ?");
        
        mysqli_stmt_bind_param($stmt, "sssssdddi", 
            $name, $contact_person, $phone, $email, $address,
            $total_goods, $total_paid, $pending_amount, $id);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success'] = "Distributor updated successfully";
            header("Location: index.php");
            exit;
        } else {
            throw new Exception("Error updating distributor: " . mysqli_error($conn));
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

require_once "../includes/header.php";
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header pb-0">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Edit Distributor</h5>
                        <a href="index.php" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left me-2"></i>Back to Distributors
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <form method="post">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Distributor Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name" required 
                                           value="<?= htmlspecialchars($distributor['name']) ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="contact_person" class="form-label">Contact Person</label>
                                    <input type="text" class="form-control" id="contact_person" name="contact_person"
                                           value="<?= htmlspecialchars($distributor['contact_person']) ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone</label>
                                    <input type="text" class="form-control" id="phone" name="phone"
                                           value="<?= htmlspecialchars($distributor['phone']) ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email"
                                           value="<?= htmlspecialchars($distributor['email']) ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="address" class="form-label">Address</label>
                                    <textarea class="form-control" id="address" name="address" rows="3"><?= htmlspecialchars($distributor['address']) ?></textarea>
                                </div>
                                <div class="mb-3">
                                    <label for="total_goods_received" class="form-label">Total Goods Received (₹)</label>
                                    <input type="number" step="0.01" class="form-control" id="total_goods_received" name="total_goods_received" 
                                           value="<?= $distributor['total_goods_received'] ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="total_amount_paid" class="form-label">Total Amount Paid (₹)</label>
                                    <input type="number" step="0.01" class="form-control" id="total_amount_paid" name="total_amount_paid" 
                                           value="<?= $distributor['total_amount_paid'] ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Pending Amount (₹)</label>
                                    <input type="text" class="form-control" readonly 
                                           value="<?= number_format($distributor['pending_amount'], 2) ?>">
                                </div>
                            </div>
                        </div>
                        <div class="text-end mt-4">
                            <button type="submit" class="btn btn-primary">Update Distributor</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once "../includes/footer.php"; ?>