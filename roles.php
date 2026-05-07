<?php
require_once 'config.php';
requireLogin();

$roles = [
    'admin' => [
        'label' => 'Administrator',
        'desc'  => 'Full system access, including staff management and financial reports.',
        'color' => 'var(--purple)',
        'perms' => ['Manage Staff', 'Financial Reports', 'Full Inventory', 'Ticket Management', 'Settings']
    ],
    'sales' => [
        'label' => 'Sales Representative',
        'desc'  => 'Manage clients, create tickets, and view sales reports.',
        'color' => 'var(--primary)',
        'perms' => ['Client Management', 'Create Tickets', 'View Sales', 'Inventory Check']
    ],
    'technician' => [
        'label' => 'Service Technician',
        'desc'  => 'Assigned to service tickets, update progress, and use materials.',
        'color' => 'var(--orange)',
        'perms' => ['My Tickets', 'Update Status', 'Material Request', 'Client Contact']
    ],
    'finance' => [
        'label' => 'Finance Officer',
        'desc'  => 'Handle billing, payments, and expense tracking.',
        'color' => 'var(--success)',
        'perms' => ['Invoicing', 'Payment Tracking', 'Expense Reports', 'Financial Analytics']
    ],
    'logistics' => [
        'label' => 'Logistics / Warehouse',
        'desc'  => 'Manage stock movements, suppliers, and purchase orders.',
        'color' => 'var(--accent)',
        'perms' => ['Inventory Control', 'Supplier Management', 'Purchase Orders', 'Stock Audit']
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>RBAC Overview — <?= APP_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app-wrapper">
<?php include 'includes/layout.php'; ?>
<main class="main-content fade-in">
  <div class="page-header">
    <div class="page-header-left"><h1>Role-Based Access Control</h1><p>System roles and permission levels</p></div>
  </div>

  <div class="grid-3">
    <?php foreach($roles as $key => $r): ?>
    <div class="card" style="border-top: 4px solid <?= $r['color'] ?>">
      <div class="card-body">
        <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
          <h3 style="color: <?= $r['color'] ?>"><?= $r['label'] ?></h3>
          <span class="badge" style="background: <?= $r['color'] ?>20; color: <?= $r['color'] ?>"><?= strtoupper($key) ?></span>
        </div>
        <p style="font-size: 0.9rem; color: var(--muted); margin-bottom: 1.5rem; height: 3.2rem; overflow: hidden;"><?= $r['desc'] ?></p>
        
        <h4 style="font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-main); margin-bottom: 0.8rem;">Permissions:</h4>
        <ul style="display: flex; flex-direction: column; gap: 0.5rem;">
          <?php foreach($r['perms'] as $p): ?>
          <li style="font-size: 0.85rem; display: flex; align-items: center; gap: 0.6rem;">
            <i class="fas fa-check-circle" style="color: var(--success); font-size: 0.8rem;"></i>
            <?= $p ?>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</main>
</div>
<script src="assets/js/main.js"></script>
</body>
</html>
