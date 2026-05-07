<?php
require_once 'config.php';
requireLogin();

$page    = max(1,(int)($_GET['page']??1));
$perPage = 25; $offset = ($page-1)*$perPage;
$type    = $_GET['type'] ?? 'all';
$where   = '1=1'; $params = [];
if ($type !== 'all') { $where .= ' AND sm.type=?'; $params[] = $type; }

// Summary counts
$summary = $pdo->query("
  SELECT
    COUNT(*) total,
    COALESCE(SUM(type='in'),0) stock_in,
    COALESCE(SUM(type='out'),0) stock_out,
    COALESCE(SUM(type='ticket_used'),0) ticket_used,
    COALESCE(SUM(type='restock'),0) restock,
    COALESCE(SUM(CASE WHEN type='in' THEN quantity ELSE 0 END),0) qty_in,
    COALESCE(SUM(CASE WHEN type='ticket_used' THEN quantity ELSE 0 END),0) qty_used
  FROM stock_movements")->fetch();

$cnt = $pdo->prepare("SELECT COUNT(*) FROM stock_movements sm WHERE $where");
$cnt->execute($params); $totalRows = $cnt->fetchColumn();
$totalPages = ceil($totalRows / $perPage);

$moves = $pdo->prepare("
  SELECT sm.*,i.name item_name,i.item_code,i.unit,s.full_name staff_name,
         t.ticket_number
  FROM stock_movements sm
  LEFT JOIN items i ON i.id=sm.item_id
  LEFT JOIN staff s ON s.id=sm.staff_id
  LEFT JOIN tickets t ON t.id=sm.ticket_id
  WHERE $where ORDER BY sm.created_at DESC LIMIT $perPage OFFSET $offset");
$moves->execute($params); $moves = $moves->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Stock Movement — NUSU Management System</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app-wrapper">
<?php include 'includes/layout.php'; ?>
<main class="main-content fade-in">

  <div class="page-header">
    <div class="page-header-left"><h1>Stock Movement</h1><p>Real-time inventory transaction history</p></div>
    <div class="page-actions">
      <a href="inventory.php" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Back to Inventory</a>
    </div>
  </div>

  <!-- Summary Stats -->
  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem">
    <div class="card" style="text-align:center;padding:1rem">
      <div style="font-size:.75rem;color:var(--muted);text-transform:uppercase;letter-spacing:.5px">Total Movements</div>
      <div style="font-size:1.8rem;font-weight:700;color:var(--text)"><?= number_format($summary['total']) ?></div>
    </div>
    <div class="card" style="text-align:center;padding:1rem">
      <div style="font-size:.75rem;color:var(--success);text-transform:uppercase;letter-spacing:.5px">Stock In</div>
      <div style="font-size:1.8rem;font-weight:700;color:var(--success)"><?= number_format($summary['stock_in']) ?></div>
      <div style="font-size:.75rem;color:var(--muted)">+<?= number_format($summary['qty_in']) ?> units</div>
    </div>
    <div class="card" style="text-align:center;padding:1rem">
      <div style="font-size:.75rem;color:var(--danger);text-transform:uppercase;letter-spacing:.5px">Ticket Used</div>
      <div style="font-size:1.8rem;font-weight:700;color:var(--danger)"><?= number_format($summary['ticket_used']) ?></div>
      <div style="font-size:.75rem;color:var(--muted)"><?= number_format($summary['qty_used']) ?> units used</div>
    </div>
    <div class="card" style="text-align:center;padding:1rem">
      <div style="font-size:.75rem;color:var(--primary);text-transform:uppercase;letter-spacing:.5px">Stock Return</div>
      <div style="font-size:1.8rem;font-weight:700;color:var(--primary)"><?= number_format($summary['restock']) ?></div>
    </div>
  </div>

  <!-- Filter Tabs -->
  <div style="display:flex;gap:.5rem;margin-bottom:1.2rem;flex-wrap:wrap">
    <?php foreach(['all'=>'All','in'=>'Stock In','out'=>'Stock Out','ticket_used'=>'Ticket Used','restock'=>'Stock Return'] as $k=>$v): ?>
    <a href="?type=<?= $k ?>" class="btn btn-sm <?= $type===$k?'btn-primary':'btn-outline' ?>"><?= $v ?></a>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <div class="table-wrap">
      <table>
        <thead><tr>
          <th>#</th><th>Item</th><th>Type</th><th>Qty</th>
          <th>Ticket</th><th>Reference</th><th>Notes</th><th>By</th><th>Date</th>
        </tr></thead>
        <tbody>
        <?php if(empty($moves)): ?>
        <tr><td colspan="9"><div class="empty-state"><i class="fas fa-boxes"></i><p>No stock movements found.</p></div></td></tr>
        <?php else: foreach($moves as $i=>$m):
          $typeColor = match($m['type']) {
            'in'          => 'var(--success)',
            'out'         => 'var(--danger)',
            'ticket_used' => '#8b5cf6',
            default       => 'var(--warning)'
          };
          $typeLabel = match($m['type']) {
            'in'          => 'Stock In',
            'out'         => 'Stock Out',
            'ticket_used' => 'Ticket Used',
            'restock'     => 'Stock Return',
            default       => 'Other'
          };
          $typeIcon = match($m['type']) {
            'in'          => 'fa-arrow-down',
            'out'         => 'fa-arrow-up',
            'ticket_used' => 'fa-tools',
            'restock'     => 'fa-undo',
            default       => 'fa-info-circle'
          };
          $qtyPrefix = in_array($m['type'],['out','ticket_used']) ? '-' : ($m['type']==='in' ? '+' : '±');
        ?>
        <tr>
          <td style="color:var(--muted);font-size:.8rem"><?= $offset+$i+1 ?></td>
          <td>
            <div style="font-weight:500"><?= htmlspecialchars($m['item_name']??'—') ?></div>
            <div style="font-size:.75rem;color:var(--muted)"><?= $m['item_code']??'' ?></div>
          </td>
          <td>
            <span class="badge" style="background:rgba(<?= $m['type']==='in'?'34,197,94':($m['type']==='ticket_used'?'139,92,246':($m['type']==='out'?'239,68,68':'245,158,11')) ?>,.15);color:<?= $typeColor ?>;border:1px solid <?= $typeColor ?>">
              <i class="fas <?= $typeIcon ?>"></i> <?= $typeLabel ?>
            </span>
          </td>
          <td style="font-weight:700;color:<?= $typeColor ?>"><?= $qtyPrefix ?><?= abs($m['quantity']) ?> <?= $m['unit']??'' ?></td>
          <td style="font-size:.8rem">
            <?php if($m['ticket_number']): ?>
            <a href="ticket_view.php?id=<?= $m['ticket_id'] ?>" class="table-link" style="color:#8b5cf6"><?= htmlspecialchars($m['ticket_number']) ?></a>
            <?php else: ?>—<?php endif; ?>
          </td>
          <td style="font-size:.8rem;color:var(--muted)"><?= htmlspecialchars($m['reference']??'—') ?></td>
          <td style="font-size:.82rem"><?= htmlspecialchars($m['notes']??'—') ?></td>
          <td style="font-size:.82rem"><?= htmlspecialchars($m['staff_name']??'System') ?></td>
          <td style="font-size:.78rem;color:var(--muted)"><?= date('M j, Y H:i',strtotime($m['created_at'])) ?></td>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <?php if($totalPages>1): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:1rem 1.4rem;border-top:1px solid var(--border)">
      <span style="font-size:.82rem;color:var(--muted)">Showing <?= $offset+1 ?>–<?= min($offset+$perPage,$totalRows) ?> of <?= $totalRows ?></span>
      <div class="pagination">
        <?php for($p=1;$p<=$totalPages;$p++): ?>
        <a href="?type=<?= $type ?>&page=<?= $p ?>" class="page-btn <?= $p===$page?'active':'' ?>"><?= $p ?></a>
        <?php endfor; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>

</main>
</div>
<script src="assets/js/main.js"></script>
</body>
</html>
