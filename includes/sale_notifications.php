<?php
require_once __DIR__ . "/notifications.php";

/**
 * Sales Notifications Handler
 * Manages all sales-related notifications in a centralized way
 */

/**
 * Add a sale created notification
 * @param string $invoice_number Invoice number of the sale
 * @param float $total_amount Total amount of the sale
 * @param string $customer_name Optional customer name
 */
function notify_sale_created($invoice_number, $total_amount, $customer_name = '') {
    $message = "Sale created successfully - Invoice #{$invoice_number}";
    if ($customer_name) {
        $message .= " for customer '{$customer_name}'";
    }
    $message .= " for " . format_currency($total_amount);
    add_notification($message, "success");
}

/**
 * Add a sale updated notification
 * @param string $invoice_number Invoice number of the sale
 * @param array $updated_fields Array of updated field names
 * @param string $customer_name Optional customer name
 */
function notify_sale_updated($invoice_number, $updated_fields = [], $customer_name = '') {
    $message = "Sale Invoice #{$invoice_number}";
    if ($customer_name) {
        $message .= " for customer '{$customer_name}'";
    }
    $message .= " has been successfully updated";
    if (!empty($updated_fields)) {
        $message .= " (Updated fields: " . implode(', ', $updated_fields) . ")";
    }
    add_notification($message, "success");
}

/**
 * Add a sale deleted notification
 * @param string $invoice_number Invoice number of the sale
 * @param float $total_amount Total amount of the deleted sale
 * @param string $customer_name Optional customer name
 */
function notify_sale_deleted($invoice_number, $total_amount, $customer_name = '') {
    $message = "Sale Invoice #{$invoice_number}";
    if ($customer_name) {
        $message .= " for customer '{$customer_name}'";
    }
    $message .= " for " . format_currency($total_amount) . " has been successfully deleted";
    add_notification($message, "warning");
}

/**
 * Add a sale import success notification
 * @param int $success_count Number of successfully imported sales
 * @param int $error_count Optional number of failed imports
 */
function notify_sale_import_success($success_count, $error_count = 0) {
    $message = "Successfully imported {$success_count} sale" . ($success_count !== 1 ? 's' : '');
    if ($error_count > 0) {
        $message .= " ({$error_count} error" . ($error_count !== 1 ? 's' : '') . " encountered)";
    }
    add_notification($message, "success");
}

/**
 * Add a sale import error notification
 * @param string $error Error message
 * @param string $row_data Optional row data where error occurred
 */
function notify_sale_import_error($error, $row_data = '') {
    $message = "Error importing sale: {$error}";
    if ($row_data) {
        $message .= " (Row data: {$row_data})";
    }
    add_notification($message, "error");
}

/**
 * Add a sale export success notification
 * @param int $count Number of exported sales
 * @param string $date_range Optional date range if export was filtered
 */
function notify_sale_export_success($count, $date_range = '') {
    $message = "Successfully exported {$count} sale" . ($count !== 1 ? 's' : '');
    if ($date_range) {
        $message .= " for period {$date_range}";
    }
    add_notification($message, "success");
}

/**
 * Add a sale export error notification
 * @param string $error Error message
 */
function notify_sale_export_error($error) {
    add_notification(
        "Error exporting sales: {$error}",
        "error"
    );
}

/**
 * Add a sale payment status update notification
 * @param string $invoice_number Invoice number of the sale
 * @param string $status New payment status
 * @param string $customer_name Optional customer name
 */
function notify_sale_payment_update($invoice_number, $status, $customer_name = '') {
    $message = "Payment status for Invoice #{$invoice_number}";
    if ($customer_name) {
        $message .= " ({$customer_name})";
    }
    $message .= " updated to '{$status}'";
    add_notification($message, "info");
}

/**
 * Add a sale operation error notification
 * @param string $operation Operation being performed (create/update/delete)
 * @param string $error Error message
 * @param array $sale_info Optional sale information array
 */
function notify_sale_error($operation, $error, $sale_info = []) {
    $message = "Error {$operation} sale";
    if (!empty($sale_info)) {
        if (isset($sale_info['invoice'])) {
            $message .= " (Invoice #{$sale_info['invoice']})";
        }
        if (isset($sale_info['customer'])) {
            $message .= " for customer '{$sale_info['customer']}'";
        }
    }
    $message .= ": {$error}";
    add_notification($message, "error");
}

/**
 * Add a bulk sale operation notification
 * @param string $operation Operation performed (update/delete)
 * @param int $count Number of affected sales
 * @param string $details Optional details about the operation
 */
function notify_sale_bulk_operation($operation, $count, $details = '') {
    $message = "Successfully {$operation}d {$count} sale" . ($count !== 1 ? 's' : '');
    if ($details) {
        $message .= " ({$details})";
    }
    add_notification($message, "success");
}
