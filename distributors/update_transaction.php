<?php
require_once "../config/db_connect.php";
require_once "../includes/functions.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_transaction'])) {
    $transaction_id = (int)$_POST['transaction_id'];
    $distributor_id = (int)$_POST['distributor_id'];
    $transaction_type = sanitize_input($_POST['transaction_type']);
    $amount = (float)$_POST['amount'];
    $transaction_date = sanitize_input($_POST['transaction_date']);
    $payment_method = sanitize_input($_POST['payment_method']);
    $description = sanitize_input($_POST['description']);

    // Begin transaction
    $conn->begin_transaction();

    try {
        // First get the original transaction details
        $stmt = $conn->prepare("SELECT * FROM transactions WHERE id = ?");
        $stmt->bind_param("i", $transaction_id);
        $stmt->execute();
        $original_txn = $stmt->get_result()->fetch_assoc();

        // Update transaction record
        $update_stmt = $conn->prepare("UPDATE transactions SET
                                    transaction_type = ?,
                                    amount = ?,
                                    transaction_date = ?,
                                    payment_method = ?,
                                    description = ?
                                    WHERE id = ?");
        $update_stmt->bind_param("sdsssi", $transaction_type, $amount, $transaction_date, 
                               $payment_method, $description, $transaction_id);
        $update_stmt->execute();

        // Calculate difference between old and new amounts
        $amount_diff = $amount - $original_txn['amount'];

        // Update distributor balance
        if ($original_txn['transaction_type'] === 'payment') {
            // Reverse original payment effect
            $conn->query("UPDATE distributors SET
                        total_amount_paid = total_amount_paid - {$original_txn['amount']},
                        pending_amount = pending_amount + {$original_txn['amount']}
                        WHERE id = $distributor_id");
        } else {
            // Reverse original purchase effect
            $conn->query("UPDATE distributors SET
                        total_goods_received = total_goods_received - {$original_txn['amount']},
                        pending_amount = pending_amount - {$original_txn['amount']}
                        WHERE id = $distributor_id");
        }

        // Apply new transaction effect
        if ($transaction_type === 'payment') {
            $conn->query("UPDATE distributors SET
                        total_amount_paid = total_amount_paid + $amount,
                        pending_amount = GREATEST(0, pending_amount - $amount)
                        WHERE id = $distributor_id");
        } else {
            $conn->query("UPDATE distributors SET
                        total_goods_received = total_goods_received + $amount,
                        pending_amount = pending_amount + $amount
                        WHERE id = $distributor_id");
        }

        $conn->commit();
        $_SESSION['success'] = "Transaction updated successfully!";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Error updating transaction: " . $e->getMessage();
    }

    header("Location: transactions.php?distributor_id=".$distributor_id);
    exit();
}
?>