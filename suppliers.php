<?php
require_once 'config.php';
requireLogin();

if (isAjax()) {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    if ($action === 'create' || $action === 'update') {
        $company = sanitize($_POST['company_name'] ?? '');
        $contact = sanitize($_POST['contact_person'] ?? '');
        $email   = sanitize($_POST['email'] ?? '');
        $phone   = sanitize($_POST['phone'] ?? '');
        $addr    = sanitize($_POST['address'] ?? '');
        if (!$company) jsonResponse(false,'Company name required.');
        if ($action === 'create') {
            $code = 'SUP-'.str_pad(rand(100,999),3,'0',STR_PAD_LEFT);
            $pdo->prepare("INSERT INTO suppliers(supplier_code,company_name,contact_person,email,phone,address) VALUES(?,?,?,?,?,?)")
                ->execute([$code,$company,$contact,$email,$phone,$addr]);
            jsonResponse(true,"Supplier $code added!", ['reload'=>true]);
        } else {
            $id = (int)$_POST['id'];
            $pdo->prepare("UPDATE suppliers SET company_name=?,contact_person=?,email=?,phone=?,address=? WHERE id=?")
                ->execute([$company,$contact,$email,$phone,$addr,$id]);
            jsonResponse(true,'Supplier updated!', ['reload'=>true]);
        }
    }
    if ($action === 'delete') {
        $pdo->prepare("UPDATE suppliers SET status='inactive' WHERE id=?")->execute([(int)$_POST['id']]);
        jsonResponse(true,'Supplier removed.');
    }
    if ($action === 'get' && isset($_GET['id'])) {
        $s = $pdo->prepare("SELECT * FROM suppliers WHERE id=?"); $s->execute([(int)$_GET['id']]);
        jsonResponse(true,'OK',['supplier'=>$s->fetch()]);
    }
    jsonResponse(false,'Unknown action');
}

$suppliers = $pdo->query("SELECT * FROM suppliers WHERE status='active' ORDER BY company_name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Suppliers — NUSU Management System</title>
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
    <div class="page-header-left"><h1>Suppliers</h1><p>Manage supply partners</p></div>
    <div class="page-actions">
      <button class="btn btn-primary" onclick="openCreate()"><i class="fas fa-plus"></i> Add Supplier</button>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <div class="card-title">All Suppliers</div>
      <div class="filter-search"><i class="fas fa-search"></i><input type="text" id="supSearch" placeholder="Search suppliers..."></div>
    </div>
    <div class="table-wrap">
      <table id="supTable">
        <thead><tr><th>#</th><th>Code</th><th>Company</th><th>Contact Person</th><th>Email</th><th>Phone</th><th>Actions</th></tr></thead>
        <tbody>
        <?php if(empty($suppliers)): ?>
        <tr><td colspan="7"><div class="empty-state"><i class="fas fa-truck"></i><p>No suppliers added yet.</p></div></td></tr>
        <?php else: foreach($suppliers as $i=>$s): ?>
        <tr>
          <td style="color:var(--muted)"><?= $i+1 ?></td>
          <td><span class="badge badge-info"><?= $s['supplier_code'] ?></span></td>
          <td><strong><?= htmlspecialchars($s['company_name']) ?></strong></td>
          <td><?= htmlspecialchars($s['contact_person']??'—') ?></td>
          <td style="font-size:.85rem"><?= htmlspecialchars($s['email']??'—') ?></td>
          <td style="font-size:.85rem"><?= htmlspecialchars($s['phone']??'—') ?></td>
          <td>
            <div style="display:flex;gap:.4rem">
              <button class="btn btn-sm btn-outline btn-icon" onclick="editSup(<?= $s['id'] ?>)"><i class="fas fa-edit"></i></button>
              <button class="btn btn-sm btn-danger btn-icon" onclick="delSup(<?= $s['id'] ?>,'<?= htmlspecialchars($s['company_name'],ENT_QUOTES) ?>')"><i class="fas fa-trash"></i></button>
            </div>
          </td>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>
</div>

<div class="modal-overlay" id="supModal">
<div class="modal">
  <div class="modal-header"><span class="modal-title" id="supModalTitle">Add Supplier</span><button class="modal-close"><i class="fas fa-times"></i></button></div>
  <div class="modal-body">
    <form id="supForm">
      <input type="hidden" name="action" id="supAction" value="create">
      <input type="hidden" name="id" id="supId">
      <div class="form-group"><label>Company Name *</label><input type="text" name="company_name" id="sf_company" class="form-control" required></div>
      <div class="form-row">
        <div class="form-group"><label>Contact Person</label><input type="text" name="contact_person" id="sf_contact" class="form-control"></div>
        <div class="form-group"><label>Phone</label><input type="text" name="phone" id="sf_phone" class="form-control"></div>
      </div>
      <div class="form-group"><label>Email</label><input type="email" name="email" id="sf_email" class="form-control"></div>
      <div class="form-group"><label>Address</label><textarea name="address" id="sf_addr" class="form-control" rows="2"></textarea></div>
    </form>
  </div>
  <div class="modal-footer">
    <button class="btn btn-outline" onclick="Modal.close('supModal')">Cancel</button>
    <button class="btn btn-primary" onclick="submitSup()"><i class="fas fa-save"></i> Save</button>
  </div>
</div>
</div>

<script src="assets/js/main.js"></script>
<script>
tableSearch('supSearch','supTable');
function openCreate() {
  document.getElementById('supModalTitle').textContent='Add Supplier';
  document.getElementById('supAction').value='create';
  document.getElementById('supForm').reset();
  Modal.open('supModal');
}
async function editSup(id) {
  const res = await Ajax.get(`suppliers.php?action=get&id=${id}`);
  if (!res.success) return;
  const s = res.supplier;
  document.getElementById('supModalTitle').textContent='Edit Supplier';
  document.getElementById('supAction').value='update';
  document.getElementById('supId').value=s.id;
  document.getElementById('sf_company').value=s.company_name;
  document.getElementById('sf_contact').value=s.contact_person??'';
  document.getElementById('sf_phone').value=s.phone??'';
  document.getElementById('sf_email').value=s.email??'';
  document.getElementById('sf_addr').value=s.address??'';
  Modal.open('supModal');
}
async function submitSup() {
  const fd=new FormData(document.getElementById('supForm'));
  Notify.loading('Saving...');
  const res=await Ajax.post('suppliers.php',fd,true);
  Notify.close();
  if(res.success){Notify.success('Saved!',res.message);setTimeout(()=>location.reload(),1200);}
  else Notify.error('Error',res.message);
}
async function delSup(id,name) {
  const ok=await Notify.confirmDelete(name);
  if(!ok) return;
  const fd=new FormData();fd.append('action','delete');fd.append('id',id);
  const res=await Ajax.post('suppliers.php',fd,true);
  if(res.success){Notify.success('Removed!');setTimeout(()=>location.reload(),1200);}
  else Notify.error('Error',res.message);
}
</script>
</body>
</html>
