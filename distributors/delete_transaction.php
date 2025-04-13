<?php
require_once "../config/db_connect.php";
require_once "../includes/functions.php";

if (isset($_GET['id']) && isset($_GET['distributor_id'])) {
    $transaction_id = (int)$_GET['id'];
    $distributor_id = (int)$_GET['distributor_id'];

    // Begin transaction
    $conn->begin_transaction();

    try {
        // First get the transaction details
        $stmt = $conn->prepare("SELECT * FROM transactions WHERE id = ?");
        $stmt->bind_param("i", $transaction_id);
        $stmt->execute();
        $txn = $stmt->get_result()->fetch_assoc();

        if ($txn) {
            // Update distributor balance
            if ($txn['transaction_type'] === 'payment') {
                $update_query = "UPDATE distributors SET
                               total_amount_paid = total_amount_paid - ?,
                               pending_amount = pending_amount + ?
                               WHERE id = ?";
            } else {
                $update_query = "UPDATE distributors SET
                               total_goods_received = total_goods_received - ?,
                               pending_amount = pending_amount - ?
                               WHERE id = ?";
            }
            
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("ddi", $txn['amount'], $txn['amount'], $distributor_id);
            $update_stmt->execute();

            // Delete transaction
            $delete_stmt = $conn->prepare("DELETE FROM transactions WHERE id = ?");
            $delete_stmt->bind_param("i", $transaction_id);
            $delete_stmt->execute();

            $conn->commit();
            $_SESSION['success'] = "Transaction deleted successfully!";
        } else {
            $_SESSION['error'] = "Transaction not found!";
        }
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Error deleting transaction: " . $e->getMessage();
    }
}

header("Location: transactions.php?distributor_id=".$distributor_id);
exit();
?>