<?php
require_once __DIR__ . "/notifications.php";

/**
 * Customer Notifications Handler
 * Manages all customer-related notifications in a centralized way
 */

/**
 * Add a customer created notification
 * @param string $customer_name Name of the customer
 * @param string $email Customer's email
 */
function notify_customer_created($customer_name, $email = '') {
    $message = "Customer '{$customer_name}' has been successfully created";
    if ($email) {
        $message .= " (Email: {$email})";
    }
    add_notification($message, "success");
}

/**
 * Add a customer updated notification
 * @param string $customer_name Name of the customer
 * @param array $updated_fields Array of updated field names
 */
function notify_customer_updated($customer_name, $updated_fields = []) {
    $message = "Customer '{$customer_name}' has been successfully updated";
    if (!empty($updated_fields)) {
        $message .= " (Updated fields: " . implode(', ', $updated_fields) . ")";
    }
    add_notification($message, "success");
}

/**
 * Add a customer deleted notification
 * @param string $customer_name Name of the customer
 * @param string $email Customer's email
 */
function notify_customer_deleted($customer_name, $email = '') {
    $message = "Customer '{$customer_name}' has been successfully deleted";
    if ($email) {
        $message .= " (Email: {$email})";
    }
    add_notification($message, "warning");
}

/**
 * Add a customer import success notification
 * @param int $success_count Number of successfully imported customers
 * @param int $error_count Optional number of failed imports
 */
function notify_customer_import_success($success_count, $error_count = 0) {
    $message = "Successfully imported {$success_count} customer" . ($success_count !== 1 ? 's' : '');
    if ($error_count > 0) {
        $message .= " ({$error_count} error" . ($error_count !== 1 ? 's' : '') . " encountered)";
    }
    add_notification($message, "success");
}

/**
 * Add a customer import error notification
 * @param string $error Error message
 * @param string $row_data Optional row data where error occurred
 */
function notify_customer_import_error($error, $row_data = '') {
    $message = "Error importing customer: {$error}";
    if ($row_data) {
        $message .= " (Row data: {$row_data})";
    }
    add_notification($message, "error");
}

/**
 * Add a customer export success notification
 * @param int $count Number of exported customers
 */
function notify_customer_export_success($count) {
    add_notification(
        "Successfully exported {$count} customer" . ($count !== 1 ? 's' : ''),
        "success"
    );
}

/**
 * Add a customer export error notification
 * @param string $error Error message
 */
function notify_customer_export_error($error) {
    add_notification(
        "Error exporting customers: {$error}",
        "error"
    );
}

/**
 * Add a customer operation error notification
 * @param string $operation Operation being performed (create/update/delete)
 * @param string $error Error message
 * @param string $customer_info Optional customer information
 */
function notify_customer_error($operation, $error, $customer_info = '') {
    $message = "Error {$operation} customer";
    if ($customer_info) {
        $message .= " ({$customer_info})";
    }
    $message .= ": {$error}";
    add_notification($message, "error");
}

/**
 * Add a bulk customer operation notification
 * @param string $operation Operation performed (update/delete)
 * @param int $count Number of affected customers
 * @param string $details Optional details about the operation
 */
function notify_customer_bulk_operation($operation, $count, $details = '') {
    $message = "Successfully {$operation}d {$count} customer" . ($count !== 1 ? 's' : '');
    if ($details) {
        $message .= " ({$details})";
    }
    add_notification($message, "success");
}
