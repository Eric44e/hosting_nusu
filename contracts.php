<?php
require_once 'config.php';
requireLogin();

if (isAjax()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $clientId = (int)$_POST['client_id'];
        $ticketId = !empty($_POST['ticket_id']) ? (int)$_POST['ticket_id'] : null;
        $title = sanitize($_POST['title'] ?? '');
        $desc = sanitize($_POST['description'] ?? '');
        $agrmt = sanitize($_POST['agreement_details'] ?? '');
        $start = $_POST['start_date'] ?? date('Y-m-d');
        $end = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        $terms = sanitize($_POST['terms_and_conditions'] ?? '');
        $amount = (float)($_POST['amount'] ?? 0);
        $status = $_POST['status'] ?? 'draft';

        if (!$clientId || !$title) jsonResponse(false, 'Client and Title are required.');

        if ($action === 'create') {
            $num = generateCode('CTR-', 'contracts', 'contract_number', 4);
            $pdo->prepare("INSERT INTO contracts(contract_number,client_id,ticket_id,title,description,agreement_details,start_date,end_date,terms_and_conditions,amount,status,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$num,$clientId,$ticketId,$title,$desc,$agrmt,$start,$end,$terms,$amount,$status,$_SESSION['staff_id']]);
            jsonResponse(true, 'Contract created successfully!', ['reload'=>true]);
        } else {
            $id = (int)$_POST['id'];
            $pdo->prepare("UPDATE contracts SET client_id=?,ticket_id=?,title=?,description=?,agreement_details=?,start_date=?,end_date=?,terms_and_conditions=?,amount=?,status=? WHERE id=?")
                ->execute([$clientId,$ticketId,$title,$desc,$agrmt,$start,$end,$terms,$amount,$status,$id]);
            jsonResponse(true, 'Contract updated successfully!', ['reload'=>true]);
        }
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM contracts WHERE id=?")->execute([$id]);
        jsonResponse(true, 'Contract deleted.', ['reload'=>true]);
    }

    if ($action === 'get' && isset($_GET['id'])) {
        $stmt = $pdo->prepare("SELECT * FROM contracts WHERE id=?");
        $stmt->execute([(int)$_GET['id']]);
        jsonResponse(true, 'OK', ['contract' => $stmt->fetch()]);
    }

    jsonResponse(false, 'Unknown action');
}

$contracts = $pdo->query("SELECT c.*, cl.full_name as client_name, t.ticket_number FROM contracts c LEFT JOIN clients cl ON cl.id=c.client_id LEFT JOIN tickets t ON t.id=c.ticket_id ORDER BY c.created_at DESC")->fetchAll();
$clients = $pdo->query("SELECT id, full_name, client_code FROM clients WHERE status='active' ORDER BY full_name")->fetchAll();
$tickets = $pdo->query("SELECT id, ticket_number, title FROM tickets ORDER BY id DESC LIMIT 100")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Contracts — NUSU Management System</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<div class="app-wrapper">
<?php include 'includes/layout.php'; ?>
<main class="main-content fade-in">
  <div class="page-header">
    <div class="page-header-left">
      <h1>Contracts</h1>
      <p>Manage client agreements and terms</p>
    </div>
    <div class="page-actions">
      <button class="btn btn-primary" onclick="openCreate()"><i class="fas fa-plus"></i> New Contract</button>
    </div>
  </div>

  <div class="card">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Contract #</th><th>Client</th><th>Ticket</th><th>Title</th><th>Start Date</th><th>Amount</th><th>Status</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($contracts as $c): ?>
          <tr>
            <td style="font-weight:600;color:var(--primary)"><?= $c['contract_number'] ?></td>
            <td><?= htmlspecialchars($c['client_name']) ?></td>
            <td><?= $c['ticket_number'] ? '<a href="ticket_view.php?id='.$c['ticket_id'].'">'.$c['ticket_number'].'</a>' : '—' ?></td>
            <td><?= htmlspecialchars($c['title']) ?></td>
            <td><?= date('M j, Y', strtotime($c['start_date'])) ?></td>
            <td><?= formatMoney($c['amount']) ?></td>
            <td><?= statusBadge($c['status']) ?></td>
            <td>
              <button class="btn btn-sm btn-outline btn-icon" onclick="editContract(<?= $c['id'] ?>)"><i class="fas fa-edit"></i></button>
              <button class="btn btn-sm btn-danger btn-icon" onclick="deleteContract(<?= $c['id'] ?>, '<?= $c['contract_number'] ?>')"><i class="fas fa-trash"></i></button>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if(!$contracts): ?>
          <tr><td colspan="8" class="empty-state">No contracts found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>
</div>

<!-- Modal -->
<div class="modal-overlay" id="contractModal">
<div class="modal modal-lg">
  <div class="modal-header">
    <span class="modal-title" id="modalTitle">New Contract</span>
    <button class="modal-close" onclick="Modal.close('contractModal')"><i class="fas fa-times"></i></button>
  </div>
  <div class="modal-body">
    <form id="contractForm">
      <input type="hidden" name="action" id="contractAction" value="create">
      <input type="hidden" name="id" id="contractId">
      
      <div class="form-row">
        <div class="form-group">
          <label>Client *</label>
          <select name="client_id" id="c_client" class="form-control" required>
            <option value="">Select Client</option>
            <?php foreach($clients as $cl): ?>
            <option value="<?= $cl['id'] ?>"><?= htmlspecialchars($cl['full_name']) ?> (<?= $cl['client_code'] ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Ticket (Optional)</label>
          <select name="ticket_id" id="c_ticket" class="form-control">
            <option value="">None</option>
            <?php foreach($tickets as $t): ?>
            <option value="<?= $t['id'] ?>"><?= $t['ticket_number'] ?> - <?= htmlspecialchars($t['title']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      
      <div class="form-group">
        <label>Title *</label>
        <input type="text" name="title" id="c_title" class="form-control" required>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Start Date</label>
          <input type="date" name="start_date" id="c_start" class="form-control" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="form-group">
          <label>End Date</label>
          <input type="date" name="end_date" id="c_end" class="form-control">
        </div>
        <div class="form-group">
          <label>Amount</label>
          <input type="number" step="0.01" name="amount" id="c_amount" class="form-control" value="0">
        </div>
        <div class="form-group">
          <label>Status</label>
          <select name="status" id="c_status" class="form-control">
            <option value="draft">Draft</option>
            <option value="active">Active</option>
            <option value="completed">Completed</option>
            <option value="terminated">Terminated</option>
            <option value="archived">Archived</option>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label>Description</label>
        <textarea name="description" id="c_desc" class="form-control" rows="2"></textarea>
      </div>
      <div class="form-group">
        <label>Agreement Details</label>
        <textarea name="agreement_details" id="c_agree" class="form-control" rows="3"></textarea>
      </div>
      <div class="form-group">
        <label>Terms and Conditions</label>
        <textarea name="terms_and_conditions" id="c_terms" class="form-control" rows="3"></textarea>
      </div>
    </form>
  </div>
  <div class="modal-footer">
    <button class="btn btn-outline" onclick="Modal.close('contractModal')">Cancel</button>
    <button class="btn btn-primary" onclick="submitContract()"><i class="fas fa-save"></i> Save</button>
  </div>
</div>
</div>

<script src="assets/js/main.js"></script>
<script>
function openCreate() {
  document.getElementById('modalTitle').textContent = 'New Contract';
  document.getElementById('contractAction').value = 'create';
  document.getElementById('contractForm').reset();
  Modal.open('contractModal');
}
async function editContract(id) {
  const res = await Ajax.get(`contracts.php?action=get&id=${id}`);
  if(!res.success) return;
  const c = res.contract;
  document.getElementById('modalTitle').textContent = 'Edit Contract';
  document.getElementById('contractAction').value = 'update';
  document.getElementById('contractId').value = c.id;
  document.getElementById('c_client').value = c.client_id;
  document.getElementById('c_ticket').value = c.ticket_id || '';
  document.getElementById('c_title').value = c.title;
  document.getElementById('c_start').value = c.start_date;
  document.getElementById('c_end').value = c.end_date || '';
  document.getElementById('c_amount').value = c.amount;
  document.getElementById('c_status').value = c.status;
  document.getElementById('c_desc').value = c.description || '';
  document.getElementById('c_agree').value = c.agreement_details || '';
  document.getElementById('c_terms').value = c.terms_and_conditions || '';
  Modal.open('contractModal');
}
async function submitContract() {
  const fd = new FormData(document.getElementById('contractForm'));
  Notify.loading('Saving...');
  const res = await Ajax.post('contracts.php', fd, true);
  Notify.close();
  if (res.success) { Notify.success('Saved!'); setTimeout(()=>location.reload(),1000); }
  else Notify.error('Error', res.message);
}
async function deleteContract(id, num) {
  if(!await Notify.confirmDelete(`Contract ${num}`)) return;
  const fd = new FormData(); fd.append('action', 'delete'); fd.append('id', id);
  const res = await Ajax.post('contracts.php', fd, true);
  if (res.success) { Notify.success('Deleted!'); setTimeout(()=>location.reload(),1000); }
  else Notify.error('Error', res.message);
}
</script>
</body>
</html>
