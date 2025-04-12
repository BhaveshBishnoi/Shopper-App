<?php
require_once "../config/db_connect.php";
require_once "../includes/header.php";
require_once "../includes/functions.php";

// Check if ID is provided
if (!isset($_GET['id'])) {
    header("Location: manage_distributors.php");
    exit;
}

$id = intval($_GET['id']);
$errors = [];

// Get distributor data
$stmt = mysqli_prepare($conn, "SELECT * FROM distributors WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$distributor = mysqli_fetch_assoc($result);

if (!$distributor) {
    header("Location: manage_distributors.php");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs
    $distributor = [
        'id' => $id,
        'name' => sanitize_input($_POST['name'] ?? ''),
        'contact_person' => sanitize_input($_POST['contact_person'] ?? ''),
        'phone' => sanitize_input($_POST['phone'] ?? ''),
        'email' => sanitize_input($_POST['email'] ?? ''),
        'address' => sanitize_input($_POST['address'] ?? ''),
        'gst_number' => sanitize_input($_POST['gst_number'] ?? '')
    ];

    // Validate inputs
    if (empty($distributor['name'])) {
        $errors[] = "Distributor name is required";
    }
    if (!empty($distributor['email']) && !filter_var($distributor['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }

    // If no errors, update database
    if (empty($errors)) {
        $stmt = mysqli_prepare($conn, "UPDATE distributors SET 
            name = ?, contact_person = ?, phone = ?, email = ?, address = ?, gst_number = ?
            WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "ssssssi", 
            $distributor['name'], 
            $distributor['contact_person'], 
            $distributor['phone'], 
            $distributor['email'], 
            $distributor['address'], 
            $distributor['gst_number'],
            $distributor['id']);

        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success'] = "Distributor updated successfully";
            header("Location: manage_distributors.php");
            exit;
        } else {
            $errors[] = "Error updating distributor: " . mysqli_error($conn);
        }
    }
}
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header pb-0">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Edit Distributor</h5>
                        <a href="manage_distributors.php" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left me-2"></i>Back to Distributors
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <h5>Please fix the following errors:</h5>
                            <ul>
                                <?php foreach ($errors as $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
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
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email"
                                           value="<?= htmlspecialchars($distributor['email']) ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="address" class="form-label">Address</label>
                                    <textarea class="form-control" id="address" name="address" rows="3"><?= htmlspecialchars($distributor['address']) ?></textarea>
                                </div>
                                <div class="mb-3">
                                    <label for="gst_number" class="form-label">GST Number</label>
                                    <input type="text" class="form-control" id="gst_number" name="gst_number"
                                           value="<?= htmlspecialchars($distributor['gst_number']) ?>">
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