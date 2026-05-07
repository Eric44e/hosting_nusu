<?php
require_once 'config.php';
$stmt = $pdo->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
print_r($tables);

foreach ($tables as $table) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
    $count = $stmt->fetchColumn();
    echo "$table: $count records\n";
    
    // If table has 'amount' column, sum it
    try {
        $stmt = $pdo->query("SELECT SUM(amount) FROM $table");
        $sum = $stmt->fetchColumn();
        if ($sum > 0) echo "  SUM(amount): " . number_format($sum) . "\n";
    } catch (Exception $e) {}
    
    try {
        $stmt = $pdo->query("SELECT SUM(total_amount) FROM $table");
        $sum = $stmt->fetchColumn();
        if ($sum > 0) echo "  SUM(total_amount): " . number_format($sum) . "\n";
    } catch (Exception $e) {}
}
