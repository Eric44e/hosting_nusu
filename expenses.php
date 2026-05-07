<?php
require_once 'config.php';
requireLogin();

if (isAjax()) {
    $action  = $_POST['action'] ?? $_GET['action'] ?? '';
    $staffId = $_SESSION['staff_id'] ?? null;
    if (!$staffId) jsonResponse(false, 'Session expired.');

    // CREATE or UPDATE expense
    if ($action === 'create' || $action === 'update') {
        $title    = sanitize($_POST['title']   ?? '');
        $category = $_POST['category']         ?? 'other';
        $amount   = (float)($_POST['amount']   ?? 0);
        $desc     = sanitize($_POST['description'] ?? '');
        $receipt  = sanitize($_POST['receipt_number'] ?? '');
        $techId   = !empty($_POST['technician_id']) ? (int)$_POST['technician_id'] : null;
        $payMeth  = $_POST['payment_method']   ?? 'cash';

        if (!$title)      jsonResponse(false, 'Expense title is required.');
        if ($amount <= 0) jsonResponse(false, 'Amount must be greater than zero.');

        // Validate category against allowed ENUM values
        $validCats = ['marketing','branding','rent','office_consumables','inventory_purchase','salaries','maintenance','transportation','other'];
        if (!in_array($category, $validCats)) $category = 'other';

        if ($action === 'create') {
            $pdo->prepare("INSERT INTO expenses (title,category,amount,description,staff_id,receipt_number,technician_id,payment_method) VALUES(?,?,?,?,?,?,?,?)")
                ->execute([$title,$category,$amount,$desc,$staffId,$receipt,$techId,$payMeth]);
            jsonResponse(true, 'Expense added!', ['reload'=>true]);
        } else {
            $id = (int)$_POST['id'];
            $pdo->prepare("UPDATE expenses SET title=?,category=?,amount=?,description=?,receipt_number=?,technician_id=?,payment_method=? WHERE id=?")
                ->execute([$title,$category,$amount,$desc,$receipt,$techId,$payMeth,$id]);
            jsonResponse(true, 'Expense updated!', ['reload'=>true]);
        }
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM expenses WHERE id=?")->execute([$id]);
        jsonResponse(true, 'Expense deleted.', ['reload'=>true]);
    }

    if ($action === 'get' && isset($_GET['id'])) {
        $stmt = $pdo->prepare("SELECT * FROM expenses WHERE id=?");
        $stmt->execute([(int)$_GET['id']]);
        jsonResponse(true, 'OK', ['expense' => $stmt->fetch()]);
    }

    jsonResponse(false, 'Unknown action');
}

// ── Data ────────────────────────────────────────────────────
$technicians = $pdo->query("SELECT t.id, s.full_name FROM technicians t JOIN staff s ON s.id = t.staff_id")->fetchAll();

$expenses = $pdo->query("
    SELECT e.*, s.full_name staff_name, ts.full_name tech_name
    FROM expenses e
    LEFT JOIN staff s ON s.id=e.staff_id
    LEFT JOIN technicians t ON t.id=e.technician_id
    LEFT JOIN staff ts ON ts.id=t.staff_id
    ORDER BY e.created_at DESC")->fetchAll();

$categories = [
    'marketing'          => 'Marketing',
    'branding'           => 'Branding',
    'rent'               => 'Rent',
    'office_consumables' => 'Office Consumables',
    'inventory_purchase' => 'Inventory Purchase',
    'salaries'           => 'Salaries',
    'maintenance'        => 'Maintenance',
    'transportation'     => 'Transportation',
    'other'              => 'Other'
];

// Chart data
$chartData   = $pdo->query("SELECT category, SUM(amount) total FROM expenses GROUP BY category ORDER BY total DESC")->fetchAll();
$chartLabels = [];
$chartValues = [];
foreach ($chartData as $row) {
    $chartLabels[] = $categories[$row['category']] ?? ucfirst($row['category']);
    $chartValues[] = (float)$row['total'];
}

// Category breakdown
$catBreakdown = $pdo->query("SELECT category, COUNT(*) cnt, SUM(amount) total FROM expenses GROUP BY category ORDER BY total DESC")->fetchAll();

// Inventory value
$invValue = $pdo->query("SELECT COALESCE(SUM(quantity*cost_price),0) cost_val, COALESCE(SUM(quantity*selling_price),0) sell_val FROM items WHERE status='active'")->fetch();

$totalExpenses   = array_sum(array_column($expenses,'amount'));
$totalInvCost    = (float)$invValue['cost_val'];
$totalCostExpos  = $totalExpenses + $totalInvCost;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Expenses — NUSU Management System</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="app-wrapper">
<?php include 'includes/layout.php'; ?>
<main class="main-content fade-in">

  <div class="page-header">
    <div class="page-header-left"><h1>Expenses</h1><p>Manage and track company expenses</p></div>
    <div class="page-actions">
      <button class="btn btn-primary" onclick="openCreate()"><i class="fas fa-plus"></i> Add Expense</button>
    </div>
  </div>

  <!-- Top Stats -->
  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:1.5rem">
    <div class="card" style="text-align:center;padding:1.5rem">
      <div style="font-size:.78rem;color:var(--danger);text-transform:uppercase;letter-spacing:.5px;margin-bottom:.4rem">Total Expenses</div>
      <div style="font-size:2rem;font-weight:700;color:var(--danger)"><?= formatMoney($totalExpenses) ?></div>
    </div>
    <div class="card" style="text-align:center;padding:1.5rem">
      <div style="font-size:.78rem;color:var(--warning);text-transform:uppercase;letter-spacing:.5px;margin-bottom:.4rem">Inventory Cost Value</div>
      <div style="font-size:2rem;font-weight:700;color:var(--warning)"><?= formatMoney($totalInvCost) ?></div>
      <div style="font-size:.72rem;color:var(--muted)">Current stock at cost price</div>
    </div>
    <div class="card" style="text-align:center;padding:1.5rem">
      <div style="font-size:.78rem;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:.4rem">Total Cost Exposure</div>
      <div style="font-size:2rem;font-weight:700;color:var(--text)"><?= formatMoney($totalCostExpos) ?></div>
      <div style="font-size:.72rem;color:var(--muted)">Expenses + Inventory</div>
    </div>
  </div>

  <div class="grid-2">
    <!-- Chart -->
    <div class="card">
      <div class="card-header"><div class="card-title">Expenses by Category</div></div>
      <div class="card-body" style="position:relative;height:280px;display:flex;align-items:center;justify-content:center">
        <?php if(empty($chartValues)): ?>
        <div class="empty-state"><p>No data to display.</p></div>
        <?php else: ?>
        <canvas id="expensesChart"></canvas>
        <?php endif; ?>
      </div>
    </div>

    <!-- Category Breakdown Table -->
    <div class="card">
      <div class="card-header"><div class="card-title">Breakdown by Category</div></div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Category</th><th>Count</th><th>Total</th><th>% of Total</th></tr></thead>
          <tbody>
          <?php foreach($catBreakdown as $row): ?>
          <?php $pct = $totalExpenses > 0 ? round(($row['total']/$totalExpenses)*100,1) : 0; ?>
          <tr>
            <td><span class="badge badge-secondary"><?= $categories[$row['category']] ?? ucfirst($row['category']) ?></span></td>
            <td style="color:var(--muted)"><?= $row['cnt'] ?></td>
            <td style="font-weight:600;color:var(--danger)"><?= formatMoney($row['total']) ?></td>
            <td>
              <div style="display:flex;align-items:center;gap:.5rem">
                <div style="flex:1;background:var(--border);border-radius:4px;height:6px">
                  <div style="width:<?= $pct ?>%;background:var(--danger);border-radius:4px;height:6px"></div>
                </div>
                <span style="font-size:.78rem;color:var(--muted)"><?= $pct ?>%</span>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($catBreakdown)): ?><tr><td colspan="4" class="empty-state">No expenses yet.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Expenses Table -->
  <div class="card" style="margin-top:1.5rem">
    <div class="card-header"><div class="card-title">All Expenses</div></div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Title</th><th>Category</th><th>Amount</th><th>Pay Method</th><th>Technician</th><th>Added By</th><th>Date</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php foreach($expenses as $e): ?>
          <tr>
            <td style="font-weight:600"><?= htmlspecialchars($e['title']) ?></td>
            <td><span class="badge badge-secondary"><?= $categories[$e['category']] ?? ucfirst($e['category']) ?></span></td>
            <td style="font-weight:600;color:var(--danger)">-<?= formatMoney($e['amount']) ?></td>
            <td style="font-size:.85rem"><?= ucfirst($e['payment_method'] ?? 'cash') ?></td>
            <td style="font-size:.85rem"><?= $e['tech_name'] ? htmlspecialchars($e['tech_name']) : '—' ?></td>
            <td style="font-size:.85rem"><?= htmlspecialchars($e['staff_name'] ?? 'Unknown') ?></td>
            <td style="font-size:.85rem"><?= date('M j, Y', strtotime($e['created_at'])) ?></td>
            <td>
              <button class="btn btn-sm btn-outline btn-icon" onclick="editExpense(<?= $e['id'] ?>)"><i class="fas fa-edit"></i></button>
              <button class="btn btn-sm btn-danger btn-icon" onclick="deleteExpense(<?= $e['id'] ?>,'<?= htmlspecialchars($e['title'],ENT_QUOTES) ?>')"><i class="fas fa-trash"></i></button>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if(!$expenses): ?><tr><td colspan="8"><div class="empty-state"><p>No expenses found.</p></div></td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</main>
</div>

<!-- Expense Modal -->
<div class="modal-overlay" id="expenseModal">
<div class="modal">
  <div class="modal-header">
    <span class="modal-title" id="modalTitle">Add Expense</span>
    <button class="modal-close" onclick="Modal.close('expenseModal')"><i class="fas fa-times"></i></button>
  </div>
  <div class="modal-body">
    <form id="expenseForm">
      <input type="hidden" name="action" id="expenseAction" value="create">
      <input type="hidden" name="id" id="expenseId">
      <div class="form-group"><label>Expense Title *</label>
        <input type="text" name="title" id="e_title" class="form-control" required placeholder="e.g. Facebook Ads"></div>
      <div class="form-row">
        <div class="form-group"><label>Category</label>
          <select name="category" id="e_category" class="form-control" onchange="toggleTechRow()">
            <?php foreach($categories as $k=>$v): ?>
            <option value="<?= $k ?>"><?= $v ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="form-group"><label>Amount (FRW) *</label>
          <input type="number" step="0.01" name="amount" id="e_amount" class="form-control" required></div>
      </div>
      <div class="form-row" id="techRow" style="display:none">
        <div class="form-group"><label>Technician (if applicable)</label>
          <select name="technician_id" id="e_technician" class="form-control">
            <option value="">None</option>
            <?php foreach($technicians as $t): ?>
            <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['full_name']) ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="form-group"><label>Payment Method</label>
          <select name="payment_method" id="e_payment_method" class="form-control">
            <option value="cash">Cash</option>
            <option value="bank_transfer">Bank Transfer</option>
            <option value="mobile_money">Mobile Money</option>
            <option value="cheque">Cheque</option>
          </select></div>
      </div>
      <div class="form-group"><label>Receipt Number</label>
        <input type="text" name="receipt_number" id="e_receipt" class="form-control" placeholder="Optional"></div>
      <div class="form-group"><label>Description</label>
        <textarea name="description" id="e_desc" class="form-control" rows="2" placeholder="More details..."></textarea></div>
    </form>
  </div>
  <div class="modal-footer">
    <button class="btn btn-outline" onclick="Modal.close('expenseModal')">Cancel</button>
    <button class="btn btn-primary" onclick="submitExpense()"><i class="fas fa-save"></i> Save Expense</button>
  </div>
</div>
</div>

<script src="assets/js/main.js"></script>
<script>
<?php if(!empty($chartValues)): ?>
new Chart(document.getElementById('expensesChart').getContext('2d'), {
  type: 'doughnut',
  data: {
    labels: <?= json_encode($chartLabels) ?>,
    datasets: [{ data: <?= json_encode($chartValues) ?>, backgroundColor: ['#1a6cff','#f59e0b','#ef4444','#10b981','#8b5cf6','#ec4899','#06b6d4','#64748b','#f97316'], borderWidth: 0 }]
  },
  options: { responsive:true, maintainAspectRatio:false, plugins: { legend: { position:'right', labels: { color:'#94a3b8' } } } }
});
<?php endif; ?>

function toggleTechRow() {
  const cat = document.getElementById('e_category').value;
  document.getElementById('techRow').style.display = ['salaries','maintenance'].includes(cat) ? 'flex' : 'none';
}
function openCreate() {
  document.getElementById('modalTitle').textContent = 'Add Expense';
  document.getElementById('expenseAction').value = 'create';
  document.getElementById('expenseForm').reset();
  toggleTechRow();
  Modal.open('expenseModal');
}
async function editExpense(id) {
  const res = await Ajax.get(`expenses.php?action=get&id=${id}`);
  if(!res.success) return;
  const e = res.expense;
  document.getElementById('modalTitle').textContent = 'Edit Expense';
  document.getElementById('expenseAction').value = 'update';
  document.getElementById('expenseId').value = e.id;
  document.getElementById('e_title').value = e.title;
  document.getElementById('e_category').value = e.category;
  document.getElementById('e_amount').value = e.amount;
  document.getElementById('e_technician').value = e.technician_id || '';
  document.getElementById('e_payment_method').value = e.payment_method || 'cash';
  document.getElementById('e_receipt').value = e.receipt_number || '';
  document.getElementById('e_desc').value = e.description || '';
  toggleTechRow();
  Modal.open('expenseModal');
}
async function submitExpense() {
  const fd = new FormData(document.getElementById('expenseForm'));
  Notify.loading('Saving...');
  const res = await Ajax.post('expenses.php', fd, true);
  Notify.close();
  if (res.success) { Notify.success('Saved!'); setTimeout(()=>location.reload(),1000); }
  else Notify.error('Error', res.message);
}
async function deleteExpense(id, title) {
  if(!await Notify.confirmDelete(`Expense: ${title}`)) return;
  const fd = new FormData(); fd.append('action','delete'); fd.append('id',id);
  const res = await Ajax.post('expenses.php', fd, true);
  if (res.success) { Notify.success('Deleted!'); setTimeout(()=>location.reload(),1000); }
  else Notify.error('Error', res.message);
}
</script>
</body>
</html>
