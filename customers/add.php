<?php
session_start();
require_once "../includes/functions.php";
require_once "../config/db_connect.php";
require_once "../includes/customer_notifications.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = sanitize_input($_POST['name']);
    $email = sanitize_input($_POST['email']);
    $phone = sanitize_input($_POST['phone']);
    $address = sanitize_input($_POST['address']);

    // Validate input
    $errors = [];
    if (empty($name)) $errors[] = "Name is required";
    if (!empty($email)) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        } elseif (customer_exists($email)) {
            $errors[] = "Email already exists";
        }
    }

    if (empty($errors)) {
        $query = "INSERT INTO customers (name, email, phone, address) VALUES (?, ?, ?, ?)";
        
        if ($stmt = mysqli_prepare($conn, $query)) {
            mysqli_stmt_bind_param($stmt, "ssss", $name, $email, $phone, $address);
            
            if (mysqli_stmt_execute($stmt)) {
                notify_customer_created($name);
                $_SESSION['success'] = "Customer added successfully";
                header("Location: index.php");
                exit;
            } else {
                notify_customer_error('creating', mysqli_error($conn));
                $errors[] = "Error adding customer: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        } else {
            notify_customer_error('creating', mysqli_error($conn));
            $errors[] = "Error preparing statement: " . mysqli_error($conn);
        }
    } else {
        foreach ($errors as $error) {
            add_notification($error, "error");
        }
    }
}

// Include header after all possible redirects
require_once "../includes/header.php";
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header pb-0">
                    <div class="row">
                        <div class="col-6 d-flex align-items-center">
                            <h6 class="mb-0">Add Customer</h6>
                        </div>
                        <div class="col-6 text-end">
                            <a class="btn bg-gradient-dark mb-0" href="index.php">
                                <i class="fas fa-arrow-left"></i>&nbsp;&nbsp;Back to List
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="<?= htmlspecialchars($_SERVER["PHP_SELF"]) ?>">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="name" class="form-control-label">Name <span class="text-danger">*</span></label>
                                    <input class="form-control" type="text" id="name" name="name" value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="email" class="form-control-label">Email</label>
                                    <input class="form-control" type="email" id="email" name="email" value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="phone" class="form-control-label">Phone</label>
                                    <input class="form-control" type="tel" id="phone" name="phone" value="<?= isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '' ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="address" class="form-control-label">Address</label>
                                    <textarea class="form-control" id="address" name="address" rows="3"><?= isset($_POST['address']) ? htmlspecialchars($_POST['address']) : '' ?></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-4">
                            <div class="col-12 text-end">
                                <button type="submit" class="btn bg-gradient-primary">Save Customer</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once "../includes/footer.php"; ?>
