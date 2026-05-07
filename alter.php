<?php
require_once 'config.php';

$migrations = [
    // ── Tickets: workflow columns ────────────────────────────
    ["ALTER TABLE tickets ADD COLUMN confirmed_at DATETIME DEFAULT NULL AFTER assigned_at",
     "tickets.confirmed_at"],
    ["ALTER TABLE tickets ADD COLUMN denial_reason TEXT DEFAULT NULL",
     "tickets.denial_reason"],
    ["ALTER TABLE tickets ADD COLUMN material_confirmed TINYINT DEFAULT 0",
     "tickets.material_confirmed"],
    ["ALTER TABLE tickets ADD COLUMN time_start DATETIME DEFAULT NULL",
     "tickets.time_start"],
    ["ALTER TABLE tickets ADD COLUMN time_end DATETIME DEFAULT NULL",
     "tickets.time_end"],
    ["ALTER TABLE tickets ADD COLUMN time_total_minutes INT DEFAULT 0",
     "tickets.time_total_minutes"],
    // Update status ENUM to include 'confirmed'
    ["ALTER TABLE tickets MODIFY COLUMN status ENUM('pending','assigned','confirmed','ongoing','completed','closed','denied') DEFAULT 'pending'",
     "tickets.status ENUM updated"],

    // ── Stock movements: ticket reference ───────────────────
    ["ALTER TABLE stock_movements ADD COLUMN ticket_id INT DEFAULT NULL",
     "stock_movements.ticket_id"],
    ["ALTER TABLE stock_movements MODIFY COLUMN type ENUM('in','out','adjustment','ticket_used') NOT NULL",
     "stock_movements.type ENUM updated"],

    // ── Expenses: extra columns ─────────────────────────────
    ["ALTER TABLE expenses ADD COLUMN technician_id INT NULL AFTER staff_id",
     "expenses.technician_id"],
    ["ALTER TABLE expenses ADD COLUMN payment_method VARCHAR(50) DEFAULT 'cash' AFTER technician_id",
     "expenses.payment_method"],

    // ── Ticket time logs table ───────────────────────────────
    ["CREATE TABLE IF NOT EXISTS ticket_time_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ticket_id INT NOT NULL,
        action ENUM('start','pause','resume','stop') NOT NULL,
        action_time DATETIME DEFAULT CURRENT_TIMESTAMP,
        staff_id INT,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
        FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE SET NULL
    )", "ticket_time_logs table"],
];

echo "<h2 style='font-family:monospace'>NUSU DB Migration Runner</h2><pre>";
$ok = 0; $skip = 0;
foreach ($migrations as [$sql, $label]) {
    try {
        $pdo->exec($sql);
        echo "  ✅  $label\n";
        $ok++;
    } catch (PDOException $e) {
        // 1060 = Duplicate column, 1061 = Duplicate key, 1005 = FK already exists — all safe to skip
        $code = $e->errorInfo[1] ?? 0;
        if (in_array($code, [1060, 1061, 1005, 1068])) {
            echo "  ⏭️  $label (already exists — skipped)\n";
        } else {
            echo "  ❌  $label — " . $e->getMessage() . "\n";
        }
        $skip++;
    }
}
echo "\n─────────────────────────────────────\n";
echo "Done. Applied: $ok | Skipped/Error: $skip\n";
echo "</pre>";
