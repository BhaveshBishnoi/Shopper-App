<?php
require_once "../config/db_connect.php";

if (isset($_GET['distributor_id'])) {
    $distributor_id = intval($_GET['distributor_id']);
    
    $query = "SELECT p.id, p.name 
              FROM products p
              LEFT JOIN distributor_products dp ON p.id = dp.product_id AND dp.distributor_id = ?
              ORDER BY p.name";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $distributor_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $options = '';
    while ($row = mysqli_fetch_assoc($result)) {
        $options .= "<option value='{$row['id']}'>{$row['name']}</option>";
    }
    
    echo $options;
}

mysqli_close($conn);
?>