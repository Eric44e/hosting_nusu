<?php
require_once 'config.php';
$stmt = $pdo->query("SELECT SUM(amount) FROM transactions WHERE type='expense'");
echo "Transaction Expenses: " . number_format($stmt->fetchColumn()) . "\n";
