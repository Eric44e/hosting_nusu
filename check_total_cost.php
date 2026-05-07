<?php
require_once 'config.php';
$stmt = $pdo->query("SELECT SUM(total_cost) FROM items");
echo "SUM(total_cost): " . number_format($stmt->fetchColumn()) . "\n";
