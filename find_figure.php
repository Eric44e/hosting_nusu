<?php
require_once 'config.php';
$stmt = $pdo->query("SHOW TABLES");
foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $table) {
    try {
        $stmt2 = $pdo->query("SELECT * FROM $table WHERE 1");
        while ($row = $stmt2->fetch(PDO::FETCH_ASSOC)) {
            foreach ($row as $val) {
                if ($val == 480300000) {
                    echo "FOUND 480,300,000 in table: $table\n";
                    print_r($row);
                }
            }
        }
    } catch (Exception $e) {}
}
