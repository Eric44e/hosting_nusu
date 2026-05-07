<?php
require_once 'config.php';
$stmt = $pdo->query("SELECT SUM(amount) as total FROM expenses");
$totalExpenses = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT SUM(amount) as total FROM transactions WHERE type='expense'");
$totalTransExpenses = $stmt->fetch()['total'];

echo "Total from expenses table: " . number_format($totalExpenses) . "\n";
echo "Total from transactions table (type=expense): " . number_format($totalTransExpenses) . "\n";
echo "Combined Total: " . number_format($totalExpenses + $totalTransExpenses) . "\n";
