<?php
session_start();
require_once "../config/db_connect.php";
require_once "../includes/functions.php";
require_once "../includes/notifications.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $gst_rate = floatval($_POST['gst_rate']);
        $products = isset($_POST['products']) ? $_POST['products'] : [];

        if ($gst_rate < 0) {
            throw new Exception("GST rate cannot be negative");
        }

        mysqli_begin_transaction($conn);

        if (!empty($products)) {
            $sql = "UPDATE products SET gst_rate = ? WHERE id IN (" . implode(',', array_fill(0, count($products), '?')) . ")";
            $stmt = mysqli_prepare($conn, $sql);
            
            if (!$stmt) {
                throw new Exception(mysqli_error($conn));
            }

            $types = "d" . str_repeat("i", count($products));
            $params = array_merge([$gst_rate], $products);
            $refs = array();
            foreach($params as $key => $value) $refs[$key] = &$params[$key];
            
            mysqli_stmt_bind_param($stmt, $types, ...$refs);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception(mysqli_stmt_error($stmt));
            }
        }

        mysqli_commit($conn);
        add_notification("GST rate updated successfully!", "success");
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        add_notification($e->getMessage(), "error");
    }
}

header("Location: index.php");
exit;
?>
