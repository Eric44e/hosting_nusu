<?php
require_once 'config.php';
require_once 'includes/ai_suggestions.php';
require_once 'includes/chart_generator.php';
requireLogin();

if (isAjax()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_expense') {
        $title  = sanitize($_POST['title'] ?? '');
        $cat    = $_POST['category'] ?? 'other';
        $amount = (float)$_POST['amount'];
        $desc   = sanitize($_POST['description'] ?? '');
        if (!$title || $amount <= 0) jsonResponse(false,'Title and amount are required.');
        $pdo->prepare("INSERT INTO expenses(title,category,amount,description,staff_id) VALUES(?,?,?,?,?)")
            ->execute([$title,$cat,$amount,$desc,$_SESSION['staff_id']]);
        jsonResponse(true,'Expense recorded!', ['reload'=>true]);
    }
    if ($action === 'add_transaction') {
        $type   = $_POST['type'] ?? 'income';
        $cat    = sanitize($_POST['category'] ?? 'service');
        $amount = (float)$_POST['amount'];
        $desc   = sanitize($_POST['description'] ?? '');
        $method = $_POST['payment_method'] ?? 'cash';
        if ($amount <= 0) jsonResponse(false,'Amount must be greater than 0.');
        $ref = 'TXN-' . strtoupper(substr(md5(uniqid()),0,8));
        $pdo->prepare("INSERT INTO transactions(reference,type,category,amount,description,payment_method,staff_id) VALUES(?,?,?,?,?,?,?)")
            ->execute([$ref,$type,$cat,$amount,$desc,$method,$_SESSION['staff_id']]);
        jsonResponse(true,'Transaction recorded!', ['reload'=>true]);
    }
    if ($action === 'delete_transaction') {
        // Only admin can delete transactions
        if ($_SESSION['role'] !== 'admin') {
            jsonResponse(false, 'You do not have permission to delete transactions.');
        }
        $txn_id = (int)$_POST['transaction_id'];
        $pdo->prepare("DELETE FROM transactions WHERE id = ?")->execute([$txn_id]);
        jsonResponse(true, 'Transaction deleted successfully!', ['reload' => true]);
    }
    jsonResponse(false,'Unknown action');
}

// Period filter
$period = $_GET['period'] ?? 'monthly';
$year = (int)($_GET['year'] ?? date('Y'));
$monthFilter = isset($_GET['month']) ? (int)$_GET['month'] : null;
$dateFilter = "1=1";
$intervalGroup = "YEAR(created_at), MONTH(created_at)";
$dateFormat = "'%b %Y'";

// Get available years
$availableYears = $pdo->query("
    SELECT DISTINCT YEAR(created_at) as year 
    FROM (
        SELECT created_at FROM transactions
        UNION ALL
        SELECT created_at FROM expenses
    ) combined
    WHERE created_at IS NOT NULL
    ORDER BY year DESC
")->fetchAll(PDO::FETCH_COLUMN);

if (empty($availableYears)) $availableYears = [date('Y')];

// Build date filter based on period
if ($period === 'daily') {
    $dateFilter = "YEAR(created_at) = $year AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $intervalGroup = "DATE(created_at)";
    $dateFormat = "'%b %d, %Y'";
} elseif ($period === 'monthly') {
    $dateFilter = "YEAR(created_at) = $year";
    $intervalGroup = "YEAR(created_at), MONTH(created_at)";
    $dateFormat = "'%b %Y'";
    if ($monthFilter) {
        $dateFilter = "YEAR(created_at) = $year AND MONTH(created_at) = $monthFilter";
    }
} elseif ($period === 'yearly') {
    $dateFilter = "1=1";
    $intervalGroup = "YEAR(created_at)";
    $dateFormat = "'%Y'";
}

// Summary stats
$income  = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type='income'")->fetchColumn();
$ticketIncome = (float)$pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM tickets WHERE status='closed'")->fetchColumn();
$totalRevenue = $income + $ticketIncome;
$expTotal= (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM expenses")->fetchColumn();
$profit  = $totalRevenue - $expTotal;
$unpaidInvoices = $pdo->query("SELECT COUNT(*),COALESCE(SUM(balance),0) FROM invoices WHERE status='unpaid'")->fetch(PDO::FETCH_NUM);

// Recent transactions
$txns = $pdo->query("SELECT t.*,s.full_name staff_name FROM transactions t LEFT JOIN staff s ON s.id=t.staff_id ORDER BY t.created_at DESC LIMIT 20")->fetchAll();
// Expenses
$expenses = $pdo->query("SELECT e.*,s.full_name staff_name FROM expenses e LEFT JOIN staff s ON s.id=e.staff_id ORDER BY e.created_at DESC LIMIT 15")->fetchAll();
// Invoices
$invoices = $pdo->query("SELECT i.*,c.full_name client_name FROM invoices i LEFT JOIN clients c ON c.id=i.client_id ORDER BY i.created_at DESC LIMIT 10")->fetchAll();

// Get period-based data for charts
$chartIncome = $pdo->query("
    SELECT DATE_FORMAT(created_at,$dateFormat) period, SUM(amount) total
    FROM transactions WHERE type='income' AND $dateFilter
    GROUP BY $intervalGroup ORDER BY created_at
")->fetchAll(PDO::FETCH_KEY_PAIR);

$chartExpenses = $pdo->query("
    SELECT DATE_FORMAT(created_at,$dateFormat) period, SUM(amount) total
    FROM expenses WHERE $dateFilter
    GROUP BY $intervalGroup ORDER BY created_at
")->fetchAll(PDO::FETCH_KEY_PAIR);

$expensesByCategory = $pdo->query("
    SELECT category, COALESCE(SUM(amount),0) total
    FROM expenses GROUP BY category ORDER BY total DESC
")->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Financial — NUSU Management System</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
.fin-tabs { display:flex; border-bottom:1px solid rgba(255,255,255,0.1); margin-bottom:1.4rem; }
.fin-tab { padding:.7rem 1.4rem; cursor:pointer; font-size:.875rem; font-weight:500; color:rgba(255,255,255,0.6); border-bottom:2px solid transparent; margin-bottom:-1px; transition:all .2s; }
.fin-tab.active { color:#FF7A00; border-bottom-color:#FF7A00; }
.fin-section { display:none; } .fin-section.active { display:block; }
.card { background: #000 !important; color: #fff !important; border: 1px solid rgba(255,255,255,0.1) !important; box-shadow: 0 10px 30px rgba(0,0,0,0.5) !important; }
.table-wrap { border: none !important; }
table th { color: #FF7A00 !important; border-bottom: 1px solid rgba(255,255,255,0.1) !important; }
table td { border-bottom: 1px solid rgba(255,255,255,0.05) !important; color: #fff !important; }
.badge-secondary { background: #333 !important; }
</style>
</head>
<body>
<div class="app-wrapper">
<?php include 'includes/layout.php'; ?>
<main class="main-content fade-in">
  <div class="page-header">
    <div class="page-header-left"><h1>Financial</h1><p>Revenue, expenses and invoices</p></div>
    <div class="page-actions">
      <a href="quick_bill.php" class="btn btn-outline" style="background:rgba(76,175,80,0.1);color:#4caf50;border:1px solid #4caf50"><i class="fas fa-file-alt"></i> Quick Bill</a>
      <button class="btn btn-outline" onclick="Modal.open('expenseModal')"><i class="fas fa-minus-circle"></i> Add Expense</button>
      <button class="btn btn-primary" onclick="Modal.open('txnModal')"><i class="fas fa-plus-circle"></i> Record Transaction</button>
    </div>
  </div>

  <!-- Summary Cards -->
  <div class="stat-cards" style="grid-template-columns:repeat(4,1fr);margin-bottom:1.8rem">
    <?php $fCards = [
      ['label'=>'Total Revenue',     'value'=>'FRW '.number_format($totalRevenue,0),   'icon'=>'fas fa-arrow-up',    'cls'=>'si-green'],
      ['label'=>'Total Expenses',    'value'=>'FRW '.number_format($expTotal,0),  'icon'=>'fas fa-arrow-down',  'cls'=>'si-red'],
      ['label'=>'Net Profit',        'value'=>'FRW '.number_format($profit,0),    'icon'=>'fas fa-sack-dollar', 'cls'=>($profit>=0?'si-cyan':'si-red')],
      ['label'=>'Unpaid Invoices',   'value'=>$unpaidInvoices[0].' (FRW '.number_format($unpaidInvoices[1],0).')', 'icon'=>'fas fa-file-invoice', 'cls'=>'si-orange'],
    ]; foreach($fCards as $c): ?>
    <div class="stat-card" style="padding:1.5rem; border-radius:12px; background: #000; color:#fff; border: 1px solid rgba(255,122,0,0.2); position:relative; overflow:hidden;">
      <div class="stat-card-top" style="display:flex; justify-content:space-between; align-items:flex-start;">
        <div>
          <h3 style="margin:0; font-size:0.85rem; color:#FF7A00; text-transform:uppercase; letter-spacing:1px;"><?= $c['label'] ?></h3>
          <div class="value" style="font-size:1.5rem; font-weight:800; margin-top:0.5rem; color:#fff;"><?= $c['value'] ?></div>
        </div>
        <div class="stat-icon" style="font-size:1.5rem; color:#FF7A00; opacity:0.8;"><i class="<?= $c['icon'] ?>"></i></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Tabs -->
  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;padding:1.5rem;border-bottom:1px solid var(--border)">
      <div>
        <h2 style="margin:0;margin-bottom:0.5rem">Financial Charts</h2>
        <p style="margin:0;font-size:0.85rem;color:var(--muted)">Period: <strong><?= ucfirst($period) ?></strong> • Year: <strong><?= $year ?></strong></p>
      </div>
      <div style="display:flex;gap:0.75rem;flex-wrap:wrap">
        <select id="yearSelect" class="form-control" style="width:100px;padding:0.5rem 0.75rem" onchange="changePeriod({year:this.value})">
          <?php foreach($availableYears as $y): ?>
          <option value="<?= $y ?>" <?= $year == $y ? 'selected' : '' ?>><?= $y ?></option>
          <?php endforeach; ?>
        </select>
        <a href="financial.php?period=daily&year=<?= $year ?>" class="btn btn-sm <?= $period==='daily'?'btn-primary':'btn-outline' ?>" style="white-space:nowrap"><i class="fas fa-calendar-day"></i> Daily</a>
        <a href="financial.php?period=monthly&year=<?= $year ?>" class="btn btn-sm <?= $period==='monthly'?'btn-primary':'btn-outline' ?>" style="white-space:nowrap"><i class="fas fa-calendar"></i> Monthly</a>
        <a href="financial.php?period=yearly&year=<?= $year ?>" class="btn btn-sm <?= $period==='yearly'?'btn-primary':'btn-outline' ?>" style="white-space:nowrap"><i class="fas fa-calendar-alt"></i> Yearly</a>
      </div>
    </div>

    <!-- Charts Section -->
    <div style="padding:1.5rem">
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(500px,1fr));gap:2rem;margin-bottom:2rem">
        <!-- Income vs Expenses Comparison Chart -->
        <div>
          <h3 style="margin:0 0 1rem 0;font-size:1.1rem;font-weight:600">Revenue & Expenses Comparison</h3>
          <div style="position:relative;width:100%;height:300px">
            <canvas id="incomeExpenseChart"></canvas>
          </div>
        </div>

        <!-- Monthly Expenses Chart -->
        <div>
          <h3 style="margin:0 0 1rem 0;font-size:1.1rem;font-weight:600">Monthly Expenses Trend</h3>
          <div style="position:relative;width:100%;height:300px">
            <canvas id="monthlyExpensesChart"></canvas>
          </div>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(500px,1fr));gap:2rem">
        <!-- Expenses by Category Pie Chart -->
        <div>
          <h3 style="margin:0 0 1rem 0;font-size:1.1rem;font-weight:600">Expenses by Category</h3>
          <div style="position:relative;width:100%;height:300px">
            <canvas id="expenseCategoryChart"></canvas>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Transactions Tabs -->
  <div class="card" style="margin-top:1.5rem">
    <div class="fin-tabs">
      <div class="fin-tab active" onclick="showTab('transactions',this)">Transactions</div>
      <div class="fin-tab" onclick="showTab('expenses',this)">Expenses</div>
      <div class="fin-tab" onclick="showTab('invoices',this)">Invoices</div>
    </div>

    <!-- Transactions -->
    <div class="fin-section active" id="tab-transactions">
      <div class="table-wrap">
        <table>
          <thead><tr><th>Reference</th><th>Type</th><th>Category</th><th>Amount</th><th>Method</th><th>By</th><th>Date</th></tr></thead>
          <tbody>
          <?php foreach($txns as $t): ?>
          <tr>
            <td style="font-size:.8rem"><?= $t['reference'] ?></td>
            <td><span class="badge <?= $t['type']==='income'?'badge-success':'badge-danger' ?>"><?= ucfirst($t['type']) ?></span></td>
            <td style="font-size:.82rem"><?= ucfirst(str_replace('_',' ',$t['category'])) ?></td>
            <td style="font-weight:700;color:<?= $t['type']==='income'?'var(--success)':'var(--danger)' ?>">
              <?= $t['type']==='income'?'+':'-' ?>FRW <?= number_format($t['amount'],0) ?>
            </td>
            <td style="font-size:.82rem"><?= ucfirst($t['payment_method']) ?></td>
            <td style="font-size:.82rem"><?= htmlspecialchars($t['staff_name']??'—') ?></td>
            <td style="font-size:.78rem;color:var(--muted)"><?= date('M j, Y',strtotime($t['created_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Expenses -->
    <div class="fin-section" id="tab-expenses">
      <div class="table-wrap">
        <table>
          <thead><tr><th>Title</th><th>Category</th><th>Amount</th><th>Recorded By</th><th>Date</th></tr></thead>
          <tbody>
          <?php
          $catLabels = ['inventory_purchase'=>'Inventory Purchase','salaries'=>'Salaries','maintenance'=>'Maintenance','transportation'=>'Transportation','other'=>'Other'];
          $catColors = ['inventory_purchase'=>'badge-primary','salaries'=>'badge-info','maintenance'=>'badge-warning','transportation'=>'badge-orange','other'=>'badge-secondary'];
          foreach($expenses as $e): ?>
          <tr>
            <td><?= htmlspecialchars($e['title']) ?></td>
            <td><span class="badge <?= $catColors[$e['category']]??'badge-secondary' ?>"><?= $catLabels[$e['category']]??$e['category'] ?></span></td>
            <td style="font-weight:700;color:var(--danger)">-FRW <?= number_format($e['amount'],0) ?></td>
            <td style="font-size:.82rem"><?= htmlspecialchars($e['staff_name']??'—') ?></td>
            <td style="font-size:.78rem;color:var(--muted)"><?= date('M j, Y',strtotime($e['created_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Invoices -->
    <div class="fin-section" id="tab-invoices">
      <div class="table-wrap">
        <table>
          <thead><tr><th>Invoice #</th><th>Client</th><th>Type</th><th>Subtotal</th><th>Tax</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th><th>Due Date</th></tr></thead>
          <tbody>
          <?php foreach($invoices as $inv): ?>
          <tr>
            <td class="table-link"><?= $inv['invoice_number'] ?></td>
            <td><?= htmlspecialchars($inv['client_name']) ?></td>
            <td><span class="badge badge-info"><?= ucfirst($inv['type']) ?></span></td>
            <td><?= formatMoney($inv['subtotal']) ?></td>
            <td><?= $inv['tax_percent'] ?>%</td>
            <td style="font-weight:700"><?= formatMoney($inv['total_amount']) ?></td>
            <td style="color:var(--success)"><?= formatMoney($inv['paid_amount']) ?></td>
            <td style="color:<?= $inv['balance']>0?'var(--danger)':'var(--success)' ?>;font-weight:700"><?= formatMoney($inv['balance']) ?></td>
            <td><?= statusBadge($inv['status']) ?></td>
            <td style="font-size:.78rem;color:var(--muted)"><?= $inv['due_date'] ? date('M j, Y',strtotime($inv['due_date'])) : '—' ?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</main>
</div>

<!-- Add Expense Modal -->
<div class="modal-overlay" id="expenseModal">
<div class="modal">
  <div class="modal-header"><span class="modal-title">Record Expense</span><button class="modal-close"><i class="fas fa-times"></i></button></div>
  <div class="modal-body">
    <form id="expenseForm">
      <input type="hidden" name="action" value="add_expense">
      <div class="form-group"><label>Title *</label><input type="text" name="title" class="form-control" required placeholder="Expense title"></div>
      <div class="form-row">
        <div class="form-group">
          <label>Category</label>
          <select name="category" class="form-control">
            <?php foreach(['inventory_purchase'=>'Inventory Purchase','salaries'=>'Salaries','maintenance'=>'Maintenance','transportation'=>'Transportation','utilities'=>'Utilities','office_supplies'=>'Office Supplies','professional_fees'=>'Professional Fees','other'=>'Other'] as $k=>$v): ?>
            <option value="<?= $k ?>"><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label>Amount (FRW) *</label><input type="number" name="amount" class="form-control" step="1" min="1" required></div>
      </div>
      <div class="form-group"><label>Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
    </form>
  </div>
  <div class="modal-footer">
    <button class="btn btn-outline" onclick="Modal.close('expenseModal')">Cancel</button>
    <button class="btn btn-danger" onclick="submitExpense()"><i class="fas fa-save"></i> Record Expense</button>
  </div>
</div>
</div>

<!-- Add Transaction Modal -->
<div class="modal-overlay" id="txnModal">
<div class="modal">
  <div class="modal-header"><span class="modal-title">Record Transaction</span><button class="modal-close"><i class="fas fa-times"></i></button></div>
  <div class="modal-body">
    <form id="txnForm">
      <input type="hidden" name="action" value="add_transaction">
      <div class="form-row">
        <div class="form-group">
          <label>Type</label>
          <select name="type" class="form-control">
            <option value="income">Income</option>
            <option value="expense">Expense</option>
            <option value="payment">Payment</option>
            <option value="refund">Refund</option>
          </select>
        </div>
        <div class="form-group"><label>Amount (FRW) *</label><input type="number" name="amount" class="form-control" step="1" min="1" required></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Category</label><input type="text" name="category" class="form-control" placeholder="service, supply, etc."></div>
        <div class="form-group">
          <label>Payment Method</label>
          <select name="payment_method" class="form-control">
            <?php foreach(['cash','card','transfer','cheque','mobile'] as $m): ?>
            <option value="<?= $m ?>"><?= ucfirst($m) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-group"><label>Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
    </form>
  </div>
  <div class="modal-footer">
    <button class="btn btn-outline" onclick="Modal.close('txnModal')">Cancel</button>
    <button class="btn btn-success" onclick="submitTxn()"><i class="fas fa-save"></i> Record</button>
  </div>
</div>
</div>

<script src="assets/js/main.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
<script>
// Chart data from PHP
const chartData = {
  income: <?= json_encode(array_values($chartIncome ?? [])) ?>,
  expenses: <?= json_encode(array_values($chartExpenses ?? [])) ?>,
  incomeLabels: <?= json_encode(array_keys($chartIncome ?? [])) ?>,
  expenseLabels: <?= json_encode(array_keys($chartExpenses ?? [])) ?>,
  categories: <?= json_encode(array_keys($expensesByCategory ?? [])) ?>,
  categoryValues: <?= json_encode(array_values($expensesByCategory ?? [])) ?>,
};

// Initialize Income vs Expense Chart
function initIncomeExpenseChart() {
  const ctx = document.getElementById('incomeExpenseChart');
  if (!ctx) return;
  
  // Merge labels from both datasets
  const allLabels = [...new Set([...chartData.incomeLabels, ...chartData.expenseLabels])];
  
  const incomeData = allLabels.map(label => 
    chartData.incomeLabels.includes(label) ? chartData.income[chartData.incomeLabels.indexOf(label)] : 0
  );
  
  const expenseData = allLabels.map(label => 
    chartData.expenseLabels.includes(label) ? chartData.expenses[chartData.expenseLabels.indexOf(label)] : 0
  );
  
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: allLabels,
      datasets: [
        {
          label: 'Revenue',
          data: incomeData,
          backgroundColor: 'rgba(76, 175, 80, 0.7)',
          borderColor: 'rgba(76, 175, 80, 1)',
          borderWidth: 2,
          borderRadius: 6,
          hoverBackgroundColor: 'rgba(76, 175, 80, 0.9)'
        },
        {
          label: 'Expenses',
          data: expenseData,
          backgroundColor: 'rgba(244, 67, 54, 0.7)',
          borderColor: 'rgba(244, 67, 54, 1)',
          borderWidth: 2,
          borderRadius: 6,
          hoverBackgroundColor: 'rgba(244, 67, 54, 0.9)'
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { 
          display: true, 
          position: 'top',
          labels: { usePointStyle: true, padding: 15 }
        },
        title: { 
          display: false 
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          ticks: { 
            callback: function(value) { 
              return 'FRW ' + (value/1000).toFixed(0) + 'K'; 
            }
          },
          title: { display: true, text: 'Amount (FRW)' }
        }
      }
    }
  });
}

// Initialize Expense Category Chart
function initExpenseCategoryChart() {
  const ctx = document.getElementById('expenseCategoryChart');
  if (!ctx || chartData.categories.length === 0) return;
  
  const categoryLabels = chartData.categories.map(c => 
    c.replace('_', ' ').charAt(0).toUpperCase() + c.replace('_', ' ').slice(1)
  );
  
  const colors = [
    'rgba(26, 108, 255, 0.7)',
    'rgba(76, 175, 80, 0.7)',
    'rgba(244, 67, 54, 0.7)',
    'rgba(255, 152, 0, 0.7)',
    'rgba(156, 39, 176, 0.7)',
    'rgba(0, 188, 212, 0.7)',
    'rgba(233, 30, 99, 0.7)',
    'rgba(255, 193, 7, 0.7)'
  ];
  
  new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: categoryLabels,
      datasets: [{
        data: chartData.categoryValues,
        backgroundColor: colors,
        borderColor: '#fff',
        borderWidth: 2
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { 
          display: true, 
          position: 'bottom',
          labels: { usePointStyle: true, padding: 15 }
        }
      }
    }
  });
}

// Initialize Monthly Expenses Chart
function initMonthlyExpensesChart() {
  const ctx = document.getElementById('monthlyExpensesChart');
  if (!ctx) return;
  
  new Chart(ctx, {
    type: 'line',
    data: {
      labels: chartData.expenseLabels,
      datasets: [{
        label: 'Monthly Expenses',
        data: chartData.expenses,
        fill: true,
        backgroundColor: 'rgba(244, 67, 54, 0.1)',
        borderColor: 'rgba(244, 67, 54, 1)',
        borderWidth: 3,
        pointBackgroundColor: 'rgba(244, 67, 54, 1)',
        pointBorderColor: '#fff',
        pointBorderWidth: 2,
        pointRadius: 5,
        pointHoverRadius: 7,
        tension: 0.4
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { 
          display: true, 
          position: 'top',
          labels: { usePointStyle: true, padding: 15 }
        },
        title: { 
          display: false 
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          ticks: { 
            callback: function(value) { 
              return 'FRW ' + (value/1000).toFixed(0) + 'K'; 
            }
          },
          title: { display: true, text: 'Amount (FRW)' }
        }
      }
    }
  });
}

// Change period function
function changePeriod(params) {
  const url = new URL(window.location);
  if (params.year) url.searchParams.set('year', params.year);
  if (params.period) url.searchParams.set('period', params.period);
  window.location = url.toString();
}

function showTab(name, el) {
  document.querySelectorAll('.fin-tab').forEach(t=>t.classList.remove('active'));
  document.querySelectorAll('.fin-section').forEach(s=>s.classList.remove('active'));
  el.classList.add('active');
  document.getElementById('tab-'+name).classList.add('active');
}

async function submitExpense() {
  const fd = new FormData(document.getElementById('expenseForm'));
  Notify.loading('Recording...');
  const res = await Ajax.post('financial.php', fd, true);
  Notify.close();
  if (res.success) { Notify.success('Recorded!', res.message); setTimeout(()=>location.reload(),1200); }
  else Notify.error('Error', res.message);
}

async function submitTxn() {
  const fd = new FormData(document.getElementById('txnForm'));
  Notify.loading('Recording...');
  const res = await Ajax.post('financial.php', fd, true);
  Notify.close();
  if (res.success) { Notify.success('Recorded!', res.message); setTimeout(()=>location.reload(),1200); }
  else Notify.error('Error', res.message);
}

// Initialize charts on page load
document.addEventListener('DOMContentLoaded', function() {
  initIncomeExpenseChart();
  initMonthlyExpensesChart();
  initExpenseCategoryChart();
});
</script>
</body>
</html>
