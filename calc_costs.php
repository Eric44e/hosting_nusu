<?php
require_once 'config.php';
$stmt = $pdo->query("SELECT SUM(material_cost) as mc, SUM(labor_cost) as lc FROM tickets");
$row = $stmt->fetch();
echo "Total Material Cost: " . number_format($row['mc']) . "\n";
echo "Total Labor Cost: " . number_format($row['lc']) . "\n";
echo "Sum: " . number_format($row['mc'] + $row['lc']) . "\n";

$stmt = $pdo->query("SELECT SUM(amount) FROM expenses");
echo "Total Expenses: " . number_format($stmt->fetchColumn()) . "\n";
