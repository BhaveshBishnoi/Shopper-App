<?php
require_once __DIR__ . "/notifications.php";

/**
 * Product Notifications Handler
 * Manages all product-related notifications in a centralized way
 */

/**
 * Add a product created notification
 * @param string $product_name Name of the product
 * @param string $sku Product SKU
 * @param float $price Optional product price
 */
function notify_product_created($product_name, $sku, $price = null) {
    $message = "Product '{$product_name}' (SKU: {$sku}) has been successfully created";
    if ($price !== null) {
        $message .= sprintf(" at %s", format_currency($price));
    }
    add_notification($message, "success");
}

/**
 * Add a product updated notification
 * @param string $product_name Name of the product
 * @param string $sku Product SKU
 * @param array $updated_fields Array of updated field names
 */
function notify_product_updated($product_name, $sku, $updated_fields = []) {
    $message = "Product '{$product_name}' (SKU: {$sku}) has been successfully updated";
    if (!empty($updated_fields)) {
        $message .= " (Updated fields: " . implode(', ', $updated_fields) . ")";
    }
    add_notification($message, "success");
}

/**
 * Add a product deleted notification
 * @param string $product_name Name of the product
 * @param string $sku Product SKU
 * @param int $stock Optional remaining stock quantity
 */
function notify_product_deleted($product_name, $sku, $stock = null) {
    $message = "Product '{$product_name}' (SKU: {$sku}) has been successfully deleted";
    if ($stock !== null) {
        $message .= " (Stock at deletion: {$stock})";
    }
    add_notification($message, "warning");
}

/**
 * Add a product import success notification
 * @param int $success_count Number of successfully imported products
 * @param int $error_count Optional number of failed imports
 */
function notify_product_import_success($success_count, $error_count = 0) {
    $message = "Successfully imported {$success_count} product" . ($success_count !== 1 ? 's' : '');
    if ($error_count > 0) {
        $message .= " ({$error_count} error" . ($error_count !== 1 ? 's' : '') . " encountered)";
    }
    add_notification($message, "success");
}

/**
 * Add a product import error notification
 * @param string $error Error message
 * @param string $row_data Optional row data where error occurred
 */
function notify_product_import_error($error, $row_data = '') {
    $message = "Error importing product: {$error}";
    if ($row_data) {
        $message .= " (Row data: {$row_data})";
    }
    add_notification($message, "error");
}

/**
 * Add a product export success notification
 * @param int $count Number of exported products
 * @param string $category Optional category filter used
 */
function notify_product_export_success($count, $category = '') {
    $message = "Successfully exported {$count} product" . ($count !== 1 ? 's' : '');
    if ($category) {
        $message .= " in category '{$category}'";
    }
    add_notification($message, "success");
}

/**
 * Add a product export error notification
 * @param string $error Error message
 */
function notify_product_export_error($error) {
    add_notification(
        "Error exporting products: {$error}",
        "error"
    );
}

/**
 * Add a product stock update notification
 * @param string $product_name Name of the product
 * @param int $new_quantity New stock quantity
 * @param string $type Type of update (add/subtract/set)
 * @param string $sku Optional product SKU
 */
function notify_product_stock_update($product_name, $new_quantity, $type = 'set', $sku = '') {
    $message = "Stock updated for product '{$product_name}'";
    if ($sku) {
        $message .= " (SKU: {$sku})";
    }
    $message .= ": New quantity is {$new_quantity}";
    add_notification($message, "info");
}

/**
 * Add a product low stock notification
 * @param string $product_name Name of the product
 * @param int $current_stock Current stock quantity
 * @param int $threshold Low stock threshold
 * @param string $sku Optional product SKU
 */
function notify_product_low_stock($product_name, $current_stock, $threshold, $sku = '') {
    $message = "Low stock alert for product '{$product_name}'";
    if ($sku) {
        $message .= " (SKU: {$sku})";
    }
    $message .= ": Current stock ({$current_stock}) is below threshold ({$threshold})";
    add_notification($message, "warning");
}

/**
 * Add a product operation error notification
 * @param string $operation Operation being performed (create/update/delete)
 * @param string $error Error message
 * @param array $product_info Optional product information array
 */
function notify_product_error($operation, $error, $product_info = []) {
    $message = "Error {$operation} product";
    if (!empty($product_info)) {
        if (isset($product_info['name'])) {
            $message .= " '{$product_info['name']}'";
        }
        if (isset($product_info['sku'])) {
            $message .= " (SKU: {$product_info['sku']})";
        }
    }
    $message .= ": {$error}";
    add_notification($message, "error");
}

/**
 * Add a bulk product operation notification
 * @param string $operation Operation performed (update/delete)
 * @param int $count Number of affected products
 * @param string $details Optional details about the operation
 */
function notify_product_bulk_operation($operation, $count, $details = '') {
    $message = "Successfully {$operation}d {$count} product" . ($count !== 1 ? 's' : '');
    if ($details) {
        $message .= " ({$details})";
    }
    add_notification($message, "success");
}

/**
 * Add a product GST update notification
 * @param float $old_rate Old GST rate
 * @param float $new_rate New GST rate
 * @param int $affected_products Number of affected products
 */
function notify_product_gst_update($old_rate, $new_rate, $affected_products) {
    $message = sprintf(
        "GST rate updated from %.1f%% to %.1f%% for %d product%s",
        $old_rate,
        $new_rate,
        $affected_products,
        $affected_products !== 1 ? 's' : ''
    );
    add_notification($message, "info");
}
