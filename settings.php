<?php
session_start();
require_once "config/db_connect.php";
require_once "includes/functions.php";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $error = null;
    $success = null;
    
    try {
        // Start transaction
        mysqli_begin_transaction($conn);

        // Define default values for settings
        $default_settings = [
            'company_name' => '',
            'company_address' => '',
            'company_phone' => '',
            'company_email' => '',
            'company_gstin' => '',
            'currency_symbol' => '₹',
            'date_format' => 'Y-m-d',
            'low_stock_threshold' => '10'
        ];

        // Get POST data with defaults
        $settings = [
            'company_name' => sanitize_input($_POST['company_name'] ?? ''),
            'company_address' => sanitize_input($_POST['company_address'] ?? ''),
            'company_phone' => sanitize_input($_POST['company_phone'] ?? ''),
            'company_email' => sanitize_input($_POST['company_email'] ?? ''),
            'company_gstin' => sanitize_input($_POST['company_gstin'] ?? ''),
            'currency_symbol' => sanitize_input($_POST['currency_symbol'] ?? '₹'),
            'date_format' => sanitize_input($_POST['date_format'] ?? 'Y-m-d'),
            'low_stock_threshold' => intval($_POST['low_stock_threshold'] ?? 10)
        ];

        foreach ($settings as $key => $value) {
            $query = "INSERT INTO settings (setting_key, setting_value) 
                     VALUES (?, ?) 
                     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
            
            $stmt = mysqli_prepare($conn, $query);
            if (!$stmt) {
                throw new Exception("Error preparing statement: " . mysqli_error($conn));
            }
            
            mysqli_stmt_bind_param($stmt, "ss", $key, $value);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Error updating setting '$key': " . mysqli_stmt_error($stmt));
            }
            
            mysqli_stmt_close($stmt);
        }

        // Commit transaction
        mysqli_commit($conn);
        $success = "Settings updated successfully";

    } catch (Exception $e) {
        mysqli_rollback($conn);
        error_log("Settings Error: " . $e->getMessage());
        $error = "Error updating settings: " . $e->getMessage();
    }
}

// Get current settings
try {
    $query = "SELECT setting_key, setting_value FROM settings";
    $result = mysqli_query($conn, $query);
    if (!$result) {
        throw new Exception(mysqli_error($conn));
    }

    $settings = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }

    // Set default values for missing settings
    $default_settings = [
        'company_name' => '',
        'company_address' => '',
        'company_phone' => '',
        'company_email' => '',
        'company_gstin' => '',
        'currency_symbol' => '₹',
        'date_format' => 'Y-m-d',
        'low_stock_threshold' => '10'
    ];

    foreach ($default_settings as $key => $value) {
        if (!isset($settings[$key])) {
            $settings[$key] = $value;
        }
    }

} catch (Exception $e) {
    error_log("Settings Error: " . $e->getMessage());
    $error = "Error loading settings: " . $e->getMessage();
}

require_once "includes/header.php";
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header pb-0">
                    <h6 class="mb-0">System Settings</h6>
                </div>
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger">
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($success)): ?>
                        <div class="alert alert-success">
                            <?= htmlspecialchars($success) ?>
                        </div>
                    <?php endif; ?>

                    <form method="post">
                        <!-- Company Information -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-control-label">Company Name</label>
                                    <input type="text" class="form-control" name="company_name" 
                                           value="<?= htmlspecialchars($settings['company_name']) ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-control-label">GSTIN</label>
                                    <input type="text" class="form-control" name="company_gstin" 
                                           value="<?= htmlspecialchars($settings['company_gstin']) ?>">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-control-label">Company Address</label>
                            <textarea class="form-control" name="company_address" rows="3"><?= htmlspecialchars($settings['company_address']) ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-control-label">Phone Number</label>
                                    <input type="tel" class="form-control" name="company_phone" 
                                           value="<?= htmlspecialchars($settings['company_phone']) ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-control-label">Email Address</label>
                                    <input type="email" class="form-control" name="company_email" 
                                           value="<?= htmlspecialchars($settings['company_email']) ?>">
                                </div>
                            </div>
                        </div>

                        <hr class="horizontal dark my-4">

                        <!-- System Settings -->
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="form-control-label">Currency Symbol</label>
                                    <input type="text" class="form-control" name="currency_symbol" 
                                           value="<?= htmlspecialchars($settings['currency_symbol']) ?>" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="form-control-label">Date Format</label>
                                    <select class="form-control" name="date_format">
                                        <option value="Y-m-d" <?= $settings['date_format'] === 'Y-m-d' ? 'selected' : '' ?>>YYYY-MM-DD</option>
                                        <option value="d-m-Y" <?= $settings['date_format'] === 'd-m-Y' ? 'selected' : '' ?>>DD-MM-YYYY</option>
                                        <option value="m/d/Y" <?= $settings['date_format'] === 'm/d/Y' ? 'selected' : '' ?>>MM/DD/YYYY</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="form-control-label">Low Stock Threshold</label>
                                    <input type="number" class="form-control" name="low_stock_threshold" 
                                           value="<?= htmlspecialchars($settings['low_stock_threshold']) ?>" 
                                           min="1" required>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-4">
                            <div class="col-12 text-end">
                                <button type="submit" class="btn bg-gradient-primary">Save Settings</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once "includes/footer.php"; ?>
