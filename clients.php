<?php
require_once 'config.php';
requireLogin();

if (isAjax()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'create' || $action === 'update') {
        $name   = sanitize($_POST['full_name'] ?? '');
        $email  = sanitize($_POST['email'] ?? '');
        $phone  = sanitize($_POST['phone'] ?? '');
        $addr   = sanitize($_POST['address'] ?? '');
        $city   = sanitize($_POST['city'] ?? '');
        $notes  = sanitize($_POST['notes'] ?? '');
        if (!$name) jsonResponse(false,'Client name is required.');
        if ($action === 'create') {
            $code = 'C-' . str_pad(rand(1000,9999),4,'0',STR_PAD_LEFT);
            $pdo->prepare("INSERT INTO clients(client_code,full_name,email,phone,address,city,notes) VALUES(?,?,?,?,?,?,?)")
                ->execute([$code,$name,$email,$phone,$addr,$city,$notes]);
            $newId = $pdo->lastInsertId();
            jsonResponse(true,"Client $code created!", ['reload'=>true, 'client_id'=>$newId]);
        } else {
            $id = (int)$_POST['id'];
            $pdo->prepare("UPDATE clients SET full_name=?,email=?,phone=?,address=?,city=?,notes=? WHERE id=?")
                ->execute([$name,$email,$phone,$addr,$city,$notes,$id]);
            jsonResponse(true,'Client updated!', ['reload'=>true]);
        }
    }
    if ($action === 'delete') {
        $pdo->prepare("DELETE FROM clients WHERE id=?")->execute([(int)$_POST['id']]);
        jsonResponse(true,'Client deleted.');
    }
    if ($action === 'get' && isset($_GET['id'])) {
        $c = $pdo->prepare("SELECT * FROM clients WHERE id=?"); $c->execute([(int)$_GET['id']]);
        jsonResponse(true,'OK',['client'=>$c->fetch()]);
    }
    jsonResponse(false,'Unknown action');
}

$search   = $_GET['q'] ?? '';
$page     = max(1,(int)($_GET['page']??1));
$perPage  = 15; $offset = ($page-1)*$perPage;
$where    = '1=1'; $params = [];
if ($search) { $where .= ' AND (full_name LIKE ? OR email LIKE ? OR client_code LIKE ? OR phone LIKE ?)'; $l="%$search%"; $params=[$l,$l,$l,$l]; }
$totalRows = $pdo->prepare("SELECT COUNT(*) FROM clients WHERE $where"); $totalRows->execute($params); $totalRows=$totalRows->fetchColumn();
$totalPages = ceil($totalRows/$perPage);
$stmt = $pdo->prepare("SELECT c.*,(SELECT COUNT(*) FROM tickets WHERE client_id=c.id) ticket_count FROM clients c WHERE $where ORDER BY c.created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params); $clients=$stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Clients — NUSU Management System</title>
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
    <div class="page-header-left"><h1>Clients</h1><p>Manage client database</p></div>
    <div class="page-actions">
      <button class="btn btn-primary" onclick="openCreate()"><i class="fas fa-plus"></i> New Client</button>
    </div>
  </div>

  <div class="filter-bar">
    <form method="GET" style="display:flex;gap:.75rem;align-items:center">
      <div class="filter-search">
        <i class="fas fa-search"></i>
        <input type="text" name="q" placeholder="Search clients..." value="<?= htmlspecialchars($search) ?>">
      </div>
      <button type="submit" class="btn btn-outline btn-sm"><i class="fas fa-search"></i></button>
      <?php if($search): ?><a href="clients.php" class="btn btn-sm btn-outline"><i class="fas fa-times"></i></a><?php endif; ?>
    </form>
    <span style="margin-left:auto;color:var(--muted);font-size:.82rem"><?= number_format($totalRows) ?> client(s)</span>
  </div>

  <div class="card">
    <div class="table-wrap">
      <table>
        <thead><tr><th>#</th><th>Client Code</th><th>Name</th><th>Email</th><th>Phone</th><th>City</th><th>Tickets</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php if(empty($clients)): ?>
        <tr><td colspan="9"><div class="empty-state"><i class="fas fa-users"></i><p>No clients found.</p></div></td></tr>
        <?php else: foreach($clients as $i=>$c): ?>
        <tr>
          <td style="color:var(--muted);font-size:.8rem"><?= $offset+$i+1 ?></td>
          <td><span class="badge badge-info"><?= $c['client_code'] ?></span></td>
          <td>
            <div style="display:flex;align-items:center;gap:.7rem">
              <img src="https://ui-avatars.com/api/?name=<?= urlencode($c['full_name']) ?>&background=FF7A00&color=fff&size=32&bold=true" class="avatar-sm">
              <strong><?= htmlspecialchars($c['full_name']) ?></strong>
            </div>
          </td>
          <td style="font-size:.85rem"><?= htmlspecialchars($c['email']??'—') ?></td>
          <td style="font-size:.85rem"><?= htmlspecialchars($c['phone']??'—') ?></td>
          <td style="font-size:.85rem"><?= htmlspecialchars($c['city']??'—') ?></td>
          <td><span class="badge badge-primary"><?= $c['ticket_count'] ?></span></td>
          <td><?= statusBadge($c['status']) ?></td>
          <td>
            <div style="display:flex;gap:.4rem">
              <button class="btn btn-sm btn-outline btn-icon" onclick="editClient(<?= $c['id'] ?>)" title="Edit"><i class="fas fa-edit"></i></button>
              <a href="tickets.php?client=<?= $c['id'] ?>" class="btn btn-sm btn-outline btn-icon" title="View Tickets"><i class="fas fa-ticket-alt"></i></a>
              <button class="btn btn-sm btn-danger btn-icon" onclick="deleteClient(<?= $c['id'] ?>, '<?= htmlspecialchars($c['full_name'],ENT_QUOTES) ?>')" title="Delete"><i class="fas fa-trash"></i></button>
            </div>
          </td>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <?php if($totalPages>1): ?>
    <div style="display:flex;align-items:center;justify-content:space-between;padding:1rem 1.4rem;border-top:1px solid var(--border)">
      <span style="font-size:.82rem;color:var(--muted)">Page <?= $page ?> of <?= $totalPages ?></span>
      <div class="pagination">
        <?php for($p=1;$p<=$totalPages;$p++): ?>
        <a href="?q=<?= urlencode($search) ?>&page=<?= $p ?>" class="page-btn <?= $p===$page?'active':'' ?>"><?= $p ?></a>
        <?php endfor; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</main>
</div>

<!-- Client Form Modal -->
<div class="modal-overlay" id="clientModal">
<div class="modal">
  <div class="modal-header">
    <span class="modal-title" id="clientModalTitle">New Client</span>
    <button class="modal-close"><i class="fas fa-times"></i></button>
  </div>
  <div class="modal-body">
    <form id="clientForm">
      <input type="hidden" name="action" id="clientAction" value="create">
      <input type="hidden" name="id" id="clientId">
      <div class="form-group">
        <label>Full Name *</label>
        <input type="text" name="full_name" id="cf_name" class="form-control" required placeholder="Client full name">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Email</label>
          <input type="email" name="email" id="cf_email" class="form-control" placeholder="email@example.com">
        </div>
        <div class="form-group">
          <label>Phone</label>
          <input type="text" name="phone" id="cf_phone" class="form-control" placeholder="+1 555 0000">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>City</label>
          <input type="text" name="city" id="cf_city" class="form-control" placeholder="City">
        </div>
        <div class="form-group">
          <label>Address</label>
          <input type="text" name="address" id="cf_address" class="form-control" placeholder="Street address">
        </div>
      </div>
      <div class="form-group">
        <label>Notes</label>
        <textarea name="notes" id="cf_notes" class="form-control" rows="2" placeholder="Additional notes..."></textarea>
      </div>
    </form>
  </div>
  <div class="modal-footer">
    <button class="btn btn-outline" onclick="Modal.close('clientModal')">Cancel</button>
    <button class="btn btn-primary" onclick="submitClient()"><i class="fas fa-save"></i> Save Client</button>
  </div>
</div>
</div>

<script src="assets/js/main.js"></script>
<script>
function openCreate() {
  document.getElementById('clientModalTitle').textContent = 'New Client';
  document.getElementById('clientAction').value = 'create';
  document.getElementById('clientForm').reset();
  Modal.open('clientModal');
}
async function editClient(id) {
  const res = await Ajax.get(`clients.php?action=get&id=${id}`);
  if (!res.success) return Notify.error('Error','Could not load client.');
  const c = res.client;
  document.getElementById('clientModalTitle').textContent = 'Edit Client';
  document.getElementById('clientAction').value = 'update';
  document.getElementById('clientId').value    = c.id;
  document.getElementById('cf_name').value     = c.full_name;
  document.getElementById('cf_email').value    = c.email ?? '';
  document.getElementById('cf_phone').value    = c.phone ?? '';
  document.getElementById('cf_city').value     = c.city ?? '';
  document.getElementById('cf_address').value  = c.address ?? '';
  document.getElementById('cf_notes').value    = c.notes ?? '';
  Modal.open('clientModal');
}
async function submitClient() {
  const fd = new FormData(document.getElementById('clientForm'));
  Notify.loading('Saving...');
  const res = await Ajax.post('clients.php', fd, true);
  Notify.close();
  if (res.success) { Notify.success('Saved!', res.message); setTimeout(()=>location.reload(),1200); }
  else Notify.error('Error', res.message);
}
async function deleteClient(id, name) {
  const ok = await Notify.confirmDelete(name);
  if (!ok) return;
  const fd = new FormData(); fd.append('action','delete'); fd.append('id',id);
  const res = await Ajax.post('clients.php', fd, true);
  if (res.success) { Notify.success('Deleted!'); setTimeout(()=>location.reload(),1200); }
  else Notify.error('Error', res.message);
}
</script>
</body>
</html>
