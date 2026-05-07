<?php
require_once 'config.php';
$stmt = $pdo->query("SELECT SUM(amount) FROM transactions WHERE type='income'");
echo "Total Income: " . number_format($stmt->fetchColumn()) . "\n";

$stmt = $pdo->query("SELECT SUM(amount) FROM transactions WHERE type='expense'");
echo "Total Expense: " . number_format($stmt->fetchColumn()) . "\n";

$stmt = $pdo->query("SELECT SUM(amount) FROM expenses");
echo "Total Expenses Table: " . number_format($stmt->fetchColumn()) . "\n";

$stmt = $pdo->query("SELECT SUM(total_amount) FROM tickets");
echo "Total Ticket Amount: " . number_format($stmt->fetchColumn()) . "\n";

// Maybe materials?
$stmt = $pdo->query("SHOW TABLES LIKE 'ticket_items'");
if ($stmt->rowCount() > 0) {
    $stmt = $pdo->query("SELECT SUM(price * quantity) FROM ticket_items");
    echo "Total Ticket Items: " . number_format($stmt->fetchColumn()) . "\n";
}
