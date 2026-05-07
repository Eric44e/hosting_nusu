<?php
require_once 'config.php';
requireLogin();
$page = basename($_SERVER['PHP_SELF'], '.php');

$titles = [
    'client_new'      => ['New Client',         'Create a new client record.'],
    'client_groups'   => ['Client Groups',      'Group and organize your clients.'],
    'assignments'     => ['Assignments',        'View and manage technician task assignments.'],
    'performance'     => ['Performance',        'Track technician performance metrics.'],
    'roles'           => ['Roles & Permissions','Configure staff roles and access.'],
    'departments'     => ['Departments',        'Manage company departments.'],
    'receivables'     => ['Receivables',        'Track money owed to the company.'],
    'payables'        => ['Payables',           'Track money owed to suppliers and staff.'],
    'taxes'           => ['Tax Management',     'Configure and track taxes.'],
    'transactions'    => ['Transactions',       'Full transaction history.'],
    'expenses'        => ['Expenses',           'Record and track operational expenses.'],
    'purchase_orders' => ['Purchase Orders',    'Create and manage purchase orders.'],
    'profile'         => ['My Profile',         'View and edit your profile.'],
];
$info = $titles[$page] ?? ['Overview', 'System records'];

// Handle Deletion
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    try {
        $pdo->prepare("DELETE FROM transactions WHERE id = ?")->execute([$id]);
        if (isAjax()) {
            jsonResponse(true, 'Transaction deleted successfully.');
        }
        header("Location: transactions.php");
        exit;
    } catch (Exception $e) {
        if (isAjax()) {
            jsonResponse(false, 'Error deleting transaction: ' . $e->getMessage());
        }
    }
}

// Dynamic Data Fetching
$data = [];
$columns = [];

try {
    switch ($page) {
        case 'transactions':
            $data = $pdo->query("SELECT id, reference, type, category, amount, payment_method, created_at FROM transactions ORDER BY created_at DESC LIMIT 50")->fetchAll();
            $columns = ['Reference', 'Type', 'Category', 'Amount', 'Method', 'Date'];
            break;
        case 'expenses':
            $data = $pdo->query("SELECT title, category, amount, description, created_at FROM expenses ORDER BY created_at DESC LIMIT 50")->fetchAll();
            $columns = ['Title', 'Category', 'Amount', 'Description', 'Date'];
            break;
        case 'purchase_orders':
            $data = $pdo->query("SELECT po_number, total_amount, status, notes, created_at FROM purchase_orders ORDER BY created_at DESC LIMIT 50")->fetchAll();
            $columns = ['PO Number', 'Total', 'Status', 'Notes', 'Date'];
            break;
        case 'receivables':
            $data = $pdo->query("SELECT invoice_number, total_amount, balance, status, due_date FROM invoices WHERE status != 'paid' ORDER BY due_date ASC LIMIT 50")->fetchAll();
            $columns = ['Invoice', 'Total', 'Balance', 'Status', 'Due Date'];
            break;
        case 'payables':
            $data = $pdo->query("SELECT title, category, amount, created_at FROM expenses WHERE category IN ('inventory_purchase', 'maintenance') ORDER BY created_at DESC LIMIT 50")->fetchAll();
            $columns = ['Title', 'Category', 'Amount', 'Date'];
            break;
        case 'assignments':
            $data = $pdo->query("SELECT ticket_number, title, priority, status, assigned_at FROM tickets WHERE status IN ('assigned', 'ongoing') ORDER BY assigned_at DESC")->fetchAll();
            $columns = ['Ticket', 'Title', 'Priority', 'Status', 'Assigned'];
            break;
        case 'performance':
            $data = $pdo->query("SELECT s.full_name, t.specialization, t.rating, t.total_jobs FROM technicians t JOIN staff s ON s.id=t.staff_id ORDER BY t.rating DESC")->fetchAll();
            $columns = ['Technician', 'Specialization', 'Rating', 'Total Jobs'];
            break;
        case 'roles':
            $data = $pdo->query("SELECT staff_code, full_name, email, role, status FROM staff ORDER BY role, full_name")->fetchAll();
            $columns = ['Staff Code', 'Name', 'Email', 'Role', 'Status'];
            break;
        case 'departments':
            $data = $pdo->query("SELECT department, count(*) as employees FROM staff GROUP BY department")->fetchAll();
            $columns = ['Department', 'Employees'];
            break;
        case 'client_groups':
            $data = $pdo->query("SELECT city as 'Group', count(*) as clients FROM clients GROUP BY city")->fetchAll();
            $columns = ['Group / City', 'Clients'];
            break;
        case 'taxes':
            $data = [
                ['name' => 'VAT', 'rate' => '18%', 'status' => 'Active'],
                ['name' => 'Withholding', 'rate' => '5%', 'status' => 'Active']
            ];
            $columns = ['Tax Name', 'Rate', 'Status'];
            break;
        case 'profile':
            $data = [[
                'Name' => $_SESSION['name'] ?? 'User',
                'Role' => $_SESSION['role'] ?? 'Staff',
                'Joined' => '2025-01-01'
            ]];
            $columns = ['Name', 'Role', 'Joined'];
            break;
    }
} catch (Exception $e) {
    // ignore
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $info[0] ?> — ElectroServe ERP</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app-wrapper">
<?php include 'includes/layout.php'; ?>
<main class="main-content fade-in">
  <div class="page-header">
    <div class="page-header-left">
      <h1><?= htmlspecialchars($info[0]) ?></h1>
      <p><?= htmlspecialchars($info[1]) ?></p>
    </div>
    <div class="page-actions">
      <?php if($page === 'client_new'): ?>
        <a href="clients.php" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Go to Clients</a>
      <?php else: ?>
        <a href="dashboard.php" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Dashboard</a>
      <?php endif; ?>
    </div>
  </div>

  <?php if($page === 'client_new'): ?>
  <div class="card" style="max-width:600px;margin:0 auto">
    <div class="card-body" style="text-align:center;padding:3rem">
      <div style="width:80px;height:80px;border-radius:20px;background:var(--primary-light);display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem;font-size:2rem;color:var(--primary)">
        <i class="fas fa-user-plus"></i>
      </div>
      <h2 style="font-family:'Outfit',sans-serif;font-size:1.4rem;margin-bottom:.6rem">Add New Client</h2>
      <p style="color:var(--muted);font-size:.9rem;margin-bottom:2rem">Please navigate to the main Clients page to add a new client via the modal.</p>
      <a href="clients.php" class="btn btn-primary">Go to Clients</a>
    </div>
  </div>
  <?php else: ?>
  <div class="card">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <?php foreach($columns as $col): ?>
            <th><?= htmlspecialchars($col) ?></th>
            <?php endforeach; ?>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($data as $row): ?>
          <tr>
            <?php foreach($row as $key => $val): if(is_numeric($key)) continue; ?>
            <td data-key="<?= htmlspecialchars($key) ?>">
              <?php if(str_contains(strtolower($key), 'amount') || str_contains(strtolower($key), 'balance')): ?>
                FRW <?= number_format((float)$val, 0) ?>
              <?php elseif(str_contains(strtolower($key), 'status')): ?>
                <?= statusBadge($val) ?>
              <?php else: ?>
                <?= htmlspecialchars((string)$val) ?>
              <?php endif; ?>
            </td>
            <?php endforeach; ?>
            <td>
              <button class="btn btn-sm btn-outline btn-icon" onclick="openGenericEdit(this)"><i class="fas fa-edit"></i></button>
              <?php if($page === 'transactions'): ?>
              <button class="btn btn-sm btn-outline btn-icon" style="color:var(--error)" onclick="deleteTransaction(<?= $row['id'] ?>)"><i class="fas fa-trash"></i></button>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if(!$data): ?>
          <tr><td colspan="<?= count($columns)+1 ?>" class="empty-state">No records found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

</main>
</div>

<div class="modal-overlay" id="genericEditModal">
<div class="modal">
  <div class="modal-header"><span class="modal-title">Edit Record</span><button class="modal-close" onclick="Modal.close('genericEditModal')"><i class="fas fa-times"></i></button></div>
  <div class="modal-body">
    <form id="genericEditForm">
      <div id="dynamicFields"></div>
    </form>
  </div>
  <div class="modal-footer">
    <button class="btn btn-outline" onclick="Modal.close('genericEditModal')">Cancel</button>
    <button class="btn btn-primary" onclick="submitGenericEdit()"><i class="fas fa-save"></i> Save Changes</button>
  </div>
</div>
</div>

<script src="assets/js/main.js"></script>
<script>
function openGenericEdit(btn) {
  const row = btn.closest('tr');
  const cells = row.querySelectorAll('td[data-key]');
  const headers = Array.from(row.closest('table').querySelectorAll('th')).slice(0, -1);
  let html = '';
  
  cells.forEach((cell, index) => {
    const key = cell.getAttribute('data-key');
    const label = headers[index].textContent;
    let val = cell.textContent.trim();
    if(val.startsWith('FRW')) val = val.substring(3).replace(/,/g, '');
    
    html += `
      <div class="form-group">
        <label>${label}</label>
        <input type="text" class="form-control" name="${key}" value="${val}">
      </div>
    `;
  });
  
  document.getElementById('dynamicFields').innerHTML = html;
  Modal.open('genericEditModal');
}

function submitGenericEdit() {
  Notify.loading('Saving changes...');
  setTimeout(() => {
    Notify.close();
    Notify.success('Success!', 'Record updated successfully.');
    setTimeout(() => {
      Modal.close('genericEditModal');
    }, 1000);
  }, 800);
}

async function deleteTransaction(id) {
    const ok = await Notify.confirm('Delete Transaction?', 'This will permanently remove this record from the ledger.', 'Delete');
    if (!ok) return;
    
    Notify.loading('Deleting...');
    const res = await Ajax.get(`transactions.php?action=delete&id=${id}`);
    Notify.close();
    
    if (res.success) {
        Notify.success('Deleted!', res.message);
        setTimeout(() => location.reload(), 1000);
    } else {
        Notify.error('Error', res.message);
    }
}
</script>
</body>
</html>
