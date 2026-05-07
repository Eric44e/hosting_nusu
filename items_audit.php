<?php
require_once 'config.php';
$tables = ['items'];
foreach ($tables as $t) {
    echo "--- $t ---\n";
    try {
        $stmt = $pdo->query("DESCRIBE $t");
        foreach($stmt->fetchAll() as $row) echo "  " . $row['Field'] . " (" . $row['Type'] . ")\n";
    } catch (Exception $e) { echo "  Error: " . $e->getMessage() . "\n"; }
}
