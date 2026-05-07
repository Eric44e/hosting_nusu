<?php
require_once 'config.php';
$stmt = $pdo->query("SELECT SUM(cost_price * quantity) FROM items");
$val = $stmt->fetchColumn();
echo "Inventory Cost Value: " . number_format($val) . "\n";
