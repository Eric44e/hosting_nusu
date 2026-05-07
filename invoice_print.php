<?php
require_once 'config.php';
require_once 'includes/qr_helper.php';
requireLogin();

if (!isset($_GET['id'])) {
    die("Invoice ID required.");
}

$id = (int)$_GET['id'];
$invStmt = $pdo->prepare("
    SELECT i.*, c.full_name, c.email, c.phone, c.address, c.client_code, s.full_name as staff_name, 
           t.title as ticket_title, t.ticket_number, t.service_cost, t.labor_cost, t.time_total_minutes,
           st.name as service_name, st.base_rate as service_base_rate
    FROM invoices i 
    JOIN clients c ON c.id=i.client_id 
    LEFT JOIN staff s ON s.id=i.created_by
    LEFT JOIN tickets t ON t.id=i.ticket_id
    LEFT JOIN service_types st ON st.id=t.service_type_id
    WHERE i.id=?
");
$invStmt->execute([$id]);
$inv = $invStmt->fetch();

if (!$inv) {
    die("Invoice not found.");
}

$items = [];
if ($inv['ticket_id']) {
    $itmStmt = $pdo->prepare("SELECT item_name, quantity, unit_price, total_price FROM ticket_items WHERE ticket_id=?");
    $itmStmt->execute([$inv['ticket_id']]);
    $items = $itmStmt->fetchAll();
} else {
    $itmStmt = $pdo->prepare("SELECT item_name, quantity, unit_price, total_price FROM ticket_items WHERE ticket_id=?");
    $itmStmt->execute([$inv['id']]);
    $items = $itmStmt->fetchAll();
}

// Calculate Duration
$totalSecs = (int)($inv['time_total_minutes'] ?? 0);
$h = floor($totalSecs / 3600);
$m = floor(($totalSecs % 3600) / 60);
$durationStr = $h . "h" . ($m > 0 ? $m . "mins" : "");

// Calculate Totals for display
$grandTotal = (float)$inv['total_amount'];
$taxRate = 18;
$subtotal = round($grandTotal / (1 + ($taxRate/100)), 0);
$taxAmount = $grandTotal - $subtotal;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= strtoupper($inv['type']) ?> #<?= htmlspecialchars($inv['invoice_number']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=VT323&family=Inter:wght@400;700&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
    :root {
        --text: #000;
        --accent: #f58220; /* NUSU Orange */
    }
    * { box-sizing: border-box; }
    body {
        margin: 0;
        padding: 20px;
        background: #eee;
        font-family: 'Inter', sans-serif;
        color: var(--text);
    }
    .print-area {
        width: 600px;
        margin: 0 auto;
        background: #fff;
        padding: 0;
        box-shadow: 0 0 20px rgba(0,0,0,0.1);
    }
    
    /* Font styles requested */
    .font-mono { font-family: 'JetBrains Mono', monospace; }
    .font-dot { font-family: 'VT323', monospace; font-size: 1.2rem; }
    .font-sans { font-family: 'Inter', sans-serif; }

    .header-logo {
        background: #000;
        padding: 20px;
        color: #fff;
        text-align: center;
    }
    .header-logo img { max-width: 200px; }
    .logo-text {
        font-size: 60px;
        font-weight: 800;
        color: var(--accent);
        margin: 0;
        line-height: 1;
    }
    .logo-sub {
        font-size: 14px;
        letter-spacing: 2px;
        margin-top: 5px;
    }
    .tagline-bar {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        margin-top: 10px;
    }
    .tagline-icon {
        width: 20px;
        height: 30px;
        background: linear-gradient(to right, #6ab04c 33%, #fff 33%, #fff 66%, #f58220 66%);
    }
    .tagline-text {
        font-size: 24px;
        font-weight: 700;
    }

    .company-info {
        padding: 20px;
        font-size: 16px;
        border-bottom: 1px solid #ddd;
    }

    .doc-title {
        padding: 20px;
        font-size: 28px;
        font-weight: 800;
        text-transform: uppercase;
        margin: 0;
    }

    .details-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        padding: 0 20px 20px;
        font-size: 15px;
    }
    .detail-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 5px;
    }
    .detail-label { color: #555; }
    .detail-value { font-weight: 600; text-align: right; }

    .section-divider {
        border-top: 2px dotted #000;
        margin: 10px 20px;
    }

    .items-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
    }
    .items-table th {
        text-align: left;
        padding: 10px 20px;
        font-size: 14px;
        font-weight: 800;
        text-transform: uppercase;
    }
    .items-table td {
        padding: 8px 20px;
        font-size: 14px;
        vertical-align: top;
    }
    .text-right { text-align: right; }

    .totals-area {
        margin: 20px;
        border-top: 2px solid #000;
    }
    .total-row {
        display: grid;
        grid-template-columns: 1fr 150px;
        padding: 10px 0;
        font-size: 15px;
        font-weight: 700;
    }
    .total-row.grand {
        background: #eee;
        padding: 10px;
        font-size: 18px;
    }

    .bank-details {
        padding: 20px;
        font-size: 14px;
    }

    .momo-pay {
        background: #000;
        color: #fff;
        padding: 10px 20px;
        margin: 20px;
        font-weight: 800;
        font-size: 16px;
    }

    .footer-msg {
        padding: 20px;
        text-align: center;
        font-weight: 800;
        text-transform: uppercase;
        font-size: 14px;
    }

    .print-btn {
        position: fixed; top: 20px; left: 20px;
        padding: 15px 30px; background: #000; color: #fff;
        border: none; border-radius: 5px; cursor: pointer;
        font-weight: 700; z-index: 100;
    }

    .qr-section {
        text-align: center;
        padding: 20px;
        border-top: 1px dashed #ddd;
        margin-top: 20px;
    }
    .qr-box {
        display: inline-block;
        padding: 10px;
        background: #fff;
        border: 1px solid #eee;
    }

    @media print {
        body { background: #fff; padding: 0; }
        .print-area { box-shadow: none; width: 100%; }
        .print-btn { display: none; }
    }
</style>
</head>
<body>

<button class="print-btn" onclick="window.print()">PRINT DOCUMENT</button>

<div class="print-area font-sans">
    <!-- Header Logo -->
    <div class="header-logo">
        <div class="logo-text">NUSU<span style="font-size: 20px; vertical-align: middle;"> Ltd</span></div>
        <div class="tagline-bar">
            <div class="tagline-icon"></div>
            <div class="tagline-text">Your Project Partner</div>
        </div>
    </div>

    <!-- Company Info -->
    <div class="company-info font-mono">
        P.O.Box 2551<br>
        Kigali, Rwanda<br>
        Phone: 1828 (Call center)
    </div>

    <!-- Document Title -->
    <h2 class="doc-title"><?= strtoupper($inv['type']) ?></h2>

    <!-- Doc Details -->
    <div class="details-grid font-mono">
        <div style="grid-column: span 2;">
            <div class="detail-row">
                <span class="detail-label"><?= ucfirst($inv['type']) ?> ID</span>
                <span class="detail-value">#<?= $inv['id'] ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Date</span>
                <span class="detail-value"><?= date('d/m/Y', strtotime($inv['created_at'])) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Job Description</span>
                <span class="detail-value"><?= htmlspecialchars($inv['ticket_title'] ?? 'Technical Service') ?></span>
            </div>
        </div>
    </div>

    <div class="section-divider"></div>

    <!-- Customer Details -->
    <div class="details-grid font-mono">
        <div style="grid-column: span 2;">
            <div class="detail-row">
                <span class="detail-label">Customer ID</span>
                <span class="detail-value">#<?= $inv['client_id'] ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Names</span>
                <span class="detail-value"><?= htmlspecialchars($inv['full_name']) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Tel</span>
                <span class="detail-value"><?= htmlspecialchars($inv['phone']) ?></span>
            </div>
        </div>
    </div>

    <div class="section-divider" style="border-top-style: solid;"></div>

    <!-- Payment Terms & Duration -->
    <div class="details-grid font-mono">
        <div style="grid-column: span 2;">
            <div class="detail-row">
                <span class="detail-label"><strong>Payment Terms</strong></span>
                <span class="detail-value">0</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">100%</span>
                <span class="detail-value"></span>
            </div>
        </div>
    </div>

    <div class="section-divider"></div>

    <div class="details-grid font-mono">
        <div style="grid-column: span 2;">
            <div class="detail-row">
                <span class="detail-label">Duration</span>
                <span class="detail-value"><?= $durationStr ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Pay Mode</span>
                <span class="detail-value">MOBILE MONEY</span>
            </div>
        </div>
    </div>

    <!-- Items Table -->
    <table class="items-table font-mono">
        <thead>
            <tr>
                <th>ITEM</th>
                <th class="text-right">UNIT PRICE</th>
                <th class="text-right">AMOUNT</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $itemsSubtotal = 0;
            foreach($items as $item): 
                $itemsSubtotal += (float)$item['total_price'];
            ?>
            <tr>
                <td><?= $item['quantity'] ?> <?= htmlspecialchars($item['item_name']) ?></td>
                <td class="text-right">FRW <?= number_format($item['unit_price'], 0) ?></td>
                <td class="text-right">FRW <?= number_format($item['total_price'], 0) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="section-divider"></div>

    <!-- Labor Calculation Section -->
    <div style="padding: 10px 20px;" class="font-mono">
        <?php 
        $laborFee = (float)($inv['labor_cost'] ?? 0);
        $serviceFee = (float)($inv['service_cost'] ?? 0);
        $baseRate = (float)($inv['service_base_rate'] ?? 0);
        
        $rawTime = (float)($inv['time_total_minutes'] ?? 0);
        if ($rawTime > 0 && $rawTime < 10) {
            $totalSecs = $rawTime * 3600;
        } else {
            $totalSecs = $rawTime;
        }
        
        $decimalHours = round($totalSecs / 3600, 2);
        $h_leg = floor($totalSecs / 3600);
        $m_leg = floor(($totalSecs % 3600) / 60);
        $durationStr_leg = $h_leg . "h" . ($m_leg > 0 ? $m_leg . "mins" : "");

        if($laborFee > 0 || $totalSecs > 0): 
            $calculatedLabor = round(($totalSecs / 3600) * $baseRate, 2);
        ?>
        <div style="background: #f9f9f9; padding: 15px; border: 1px dashed #000;">
            <div style="font-weight: 800; text-transform: uppercase; margin-bottom: 5px;">Service & Labor Breakdown</div>
            
            <div class="detail-row">
                <span>Technical Duration:</span>
                <span><?= $durationStr_leg ?> (<?= round($totalSecs / 3600, 4) ?> hrs)</span>
            </div>
            <div class="detail-row">
                <span>Base Rate:</span>
                <span>FRW <?= number_format($baseRate, 0) ?> / hr</span>
            </div>
            <div class="detail-row" style="margin-top: 10px; padding-top: 5px; border-top: 2px solid #000;">
                <span><strong>TOTAL SERVICE COST:</strong></span>
                <span><strong>FRW <?= number_format($calculatedLabor, 0) ?></strong></span>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Totals -->
    <?php
    $finalLabor = isset($calculatedLabor) ? $calculatedLabor : 0;
    $finalSubtotal = $itemsSubtotal + $finalLabor;
    $taxRate = 18;
    $taxValue = round($finalSubtotal * ($taxRate / 100), 2);
    $totalWithTax = $finalSubtotal + $taxValue;
    ?>
    <div class="totals-area font-mono">
        <div class="total-row">
            <span>SUBTOTAL</span>
            <span class="text-right">FRW <?= number_format($finalSubtotal, 0) ?></span>
        </div>
        <div class="total-row">
            <span>SALES TAX (<?= $taxRate ?>%)</span>
            <span class="text-right">FRW <?= number_format($taxValue, 0) ?></span>
        </div>
        <div class="total-row grand">
            <span>GRAND TOTAL</span>
            <span class="text-right">FRW <?= number_format($totalWithTax, 0) ?></span>
        </div>
    </div>

    <!-- Bank Details -->
    <div class="bank-details font-sans">
        <strong>Cogebank Account Number:</strong><br>
        5-01390088522-74 Beneficiary: Nusu Limited
    </div>

    <div style="padding: 0 20px; font-size: 14px;">
        If you have any questions concerning this <?= strtolower($inv['type']) ?>,<br>
        Contact Gael kayiranga, 0788309827, nusultd@gmail.com
    </div>

    <!-- MOMO Pay -->
    <div class="momo-pay font-mono">
        MOMO Pay : *182*8*1*003340#
    </div>

    <!-- Footer -->
    <div class="footer-msg font-sans">
        THANK YOU FOR YOUR BUSINESS!
    </div>

    <!-- QR Code Section -->
    <div class="qr-section">
        <div class="qr-box">
            <?= QRCodeGenerator::generateForDocument($id, $inv['type'] ?? 'invoice', 120) ?>
        </div>
        <div style="font-size:10px;margin-top:5px;font-family:monospace;">
            SCAN TO VERIFY<br>
            <?= htmlspecialchars($inv['invoice_number']) ?>
        </div>
    </div>
</div>

</body>
</html>
