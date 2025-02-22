<?php
// Start session and include required files before any output
session_start();
require_once "../config/db_connect.php";
require_once "../includes/functions.php";
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
                    } else {
                        // Check if email exists but ignore current customer
                        $email_check = mysqli_query($conn, "SELECT id FROM customers WHERE email = '$email' AND id != $id");
                        if (mysqli_num_rows($email_check) > 0) {
                            $errors[] = "Email already exists";
                        }
                    }
                }

                if (empty($errors)) {
                    $update_query = "UPDATE customers SET name=?, email=?, phone=?, address=? WHERE id=?";
                    
                    if ($update_stmt = mysqli_prepare($conn, $update_query)) {
                        mysqli_stmt_bind_param($update_stmt, "ssssi", $name, $email, $phone, $address, $id);
                        
                        if (mysqli_stmt_execute($update_stmt)) {
                            handleSuccess("Customer updated successfully", "index.php");
                        } else {
                            handleError("Error updating customer");
                        }
                        mysqli_stmt_close($update_stmt);
                    }
                } else {
                    $_SESSION['error'] = implode("<br>", $errors);
                }
            }
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
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Edit Customer</h5>
        </div>
        <div class="card-body">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . "?id=" . $id; ?>" method="post">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" class="form-control" name="name" 
                                   value="<?php echo htmlspecialchars($customer['name']); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" 
                                   value="<?php echo htmlspecialchars($customer['email']); ?>">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input type="tel" class="form-control" name="phone" 
                                   value="<?php echo htmlspecialchars($customer['phone']); ?>">
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Address</label>
                    <textarea class="form-control" name="address" rows="3"><?php echo htmlspecialchars($customer['address']); ?></textarea>
                </div>
                
                <div class="text-end">
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Update Customer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
require_once "../includes/footer.php";
?>
