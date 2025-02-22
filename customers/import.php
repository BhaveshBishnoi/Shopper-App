<?php
session_start();
require_once "../config/db_connect.php";
require_once "../includes/functions.php";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["csv_file"])) {
    try {
        mysqli_begin_transaction($conn);

        $file = $_FILES["csv_file"];
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

        // Validate required columns
        $required_columns = ['name', 'email', 'phone', 'address'];
        $header = array_map('strtolower', $header);
        foreach ($required_columns as $column) {
            if (!in_array($column, $header)) {
                throw new Exception("Missing required column: " . $column);
            }
        }

        // Get column indexes
        $indexes = array_flip($header);
        $row_number = 1;
        $imported = 0;
        $errors = [];

        // Read and process each row
        while (($row = fgetcsv($handle)) !== false) {
            $row_number++;
            try {
                // Get values from correct columns
                $name = sanitize_input($row[$indexes['name']]);
                $email = sanitize_input($row[$indexes['email']]);
                $phone = sanitize_input($row[$indexes['phone']]);
                $address = sanitize_input($row[$indexes['address']]);

                // Validate required fields
                if (empty($name)) throw new Exception("Name is required");
                if (empty($email)) throw new Exception("Email is required");
                if (empty($phone)) throw new Exception("Phone is required");
                if (empty($address)) throw new Exception("Address is required");

                // Validate email format
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception("Invalid email format");
                }

                // Check if email already exists
                $check_sql = "SELECT id FROM customers WHERE email = ? AND id != ?";
                $check_stmt = mysqli_prepare($conn, $check_sql);
                $zero = 0;
                mysqli_stmt_bind_param($check_stmt, "si", $email, $zero);
                if (!mysqli_stmt_execute($check_stmt)) {
                    throw new Exception("Database error: " . mysqli_error($conn));
                }
                mysqli_stmt_store_result($check_stmt);
                if (mysqli_stmt_num_rows($check_stmt) > 0) {
                    throw new Exception("Email already exists");
                }
                mysqli_stmt_close($check_stmt);

                // Insert customer
                $sql = "INSERT INTO customers (name, email, phone, address) VALUES (?, ?, ?, ?)";
                $stmt = mysqli_prepare($conn, $sql);
                if (!$stmt) {
                    throw new Exception("Database error: " . mysqli_error($conn));
                }

                mysqli_stmt_bind_param($stmt, "ssss", $name, $email, $phone, $address);
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception("Error inserting customer: " . mysqli_stmt_error($stmt));
                }
                mysqli_stmt_close($stmt);
                $imported++;

            } catch (Exception $e) {
                $errors[] = "Row $row_number: " . $e->getMessage();
            }
        }

        fclose($handle);

        if (count($errors) > 0) {
            $_SESSION['import_errors'] = $errors;
            $_SESSION['import_success'] = "Imported $imported customers successfully.";
        } else {
            $_SESSION['success'] = "Successfully imported $imported customers.";
        }
        
        mysqli_commit($conn);
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;

    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['error'] = "Import failed: " . $e->getMessage();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Include header after processing POST request
require_once "../includes/header.php";
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Import Customers</h5>
                </div>
                <div class="card-body">
                    <form action="" method="post" enctype="multipart/form-data">
                        <div class="mb-4">
                            <h6>CSV Format Requirements:</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Column</th>
                                            <th>Required</th>
                                            <th>Description</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>name</td>
                                            <td><span class="badge bg-danger">Yes</span></td>
                                            <td>Customer's full name</td>
                                        </tr>
                                        <tr>
                                            <td>email</td>
                                            <td><span class="badge bg-danger">Yes</span></td>
                                            <td>Valid email address (must be unique)</td>
                                        </tr>
                                        <tr>
                                            <td>phone</td>
                                            <td><span class="badge bg-danger">Yes</span></td>
                                            <td>Contact phone number</td>
                                        </tr>
                                        <tr>
                                            <td>address</td>
                                            <td><span class="badge bg-danger">Yes</span></td>
                                            <td>Full postal address</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="alert alert-info mt-3">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Tips:</strong>
                                <ul class="mb-0">
                                    <li>File must be in CSV format</li>
                                    <li>First row must contain column headers</li>
                                    <li>Column names are case-insensitive</li>
                                    <li>Each email address must be unique</li>
                                </ul>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="csv_file" class="form-label">Choose CSV File</label>
                            <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv" required>
                        </div>
                        <div class="text-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-upload me-2"></i>Import Customers
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Export Customers</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Download customer data as CSV</p>
                    <a href="export.php" class="btn btn-secondary">
                        <i class="fas fa-download me-2"></i>Export CSV
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once "../includes/footer.php"; ?>
