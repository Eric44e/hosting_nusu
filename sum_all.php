<?php
require_once 'config.php';
$stmt = $pdo->query("SELECT SUM(service_cost) as sc, SUM(material_cost) as mc, SUM(labor_cost) as lc, SUM(total_amount) as ta FROM tickets");
$row = $stmt->fetch();
print_r($row);
echo "Grand Total: " . number_format($row['sc'] + $row['mc'] + $row['lc'] + $row['ta']) . "\n";
