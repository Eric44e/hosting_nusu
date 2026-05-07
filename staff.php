<?php
require_once 'config.php';
requireLogin();

if (isAjax()) {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    if ($action === 'create' || $action === 'update') {
        $name   = sanitize($_POST['full_name'] ?? '');
        $email  = sanitize($_POST['email'] ?? '');
        $phone  = sanitize($_POST['phone'] ?? '');
        $role   = $_POST['role'] ?? 'sales';
        $dept   = sanitize($_POST['department'] ?? '');
        $status = $_POST['status'] ?? 'active';
        if (!$name || !$email) jsonResponse(false,'Name and email required.');
        if ($action === 'create') {
            $pfx  = strtoupper(substr($role,0,3));
            $code = "$pfx-".str_pad(rand(100,999),3,'0',STR_PAD_LEFT);
            $pass = password_hash('password', PASSWORD_BCRYPT);
            $pdo->prepare("INSERT INTO staff(staff_code,full_name,email,phone,password,role,department,status) VALUES(?,?,?,?,?,?,?,?)")
                ->execute([$code,$name,$email,$phone,$pass,$role,$dept,$status]);
            jsonResponse(true,"Staff $code added! Default password: password",['reload'=>true]);
        } else {
            $id = (int)$_POST['id'];
            $pdo->prepare("UPDATE staff SET full_name=?,email=?,phone=?,role=?,department=?,status=? WHERE id=?")
                ->execute([$name,$email,$phone,$role,$dept,$status,$id]);
            jsonResponse(true,'Staff updated!',['reload'=>true]);
        }
    }
    if ($action === 'delete') {
        $pdo->prepare("UPDATE staff SET status='inactive' WHERE id=? AND id!=1")->execute([(int)$_POST['id']]);
        jsonResponse(true,'Staff deactivated.');
    }
    if ($action === 'get' && isset($_GET['id'])) {
        $s = $pdo->prepare("SELECT * FROM staff WHERE id=?"); $s->execute([(int)$_GET['id']]);
        jsonResponse(true,'OK',['staff'=>$s->fetch()]);
    }
    jsonResponse(false,'Unknown action');
}

$staffList = $pdo->query("SELECT * FROM staff ORDER BY role,full_name")->fetchAll();
$roleCounts = [];
foreach($staffList as $s) $roleCounts[$s['role']] = ($roleCounts[$s['role']] ?? 0) + 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Staff — NUSU Management System</title>
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
    <div class="page-header-left"><h1>Staff Management</h1><p>Team members and roles</p></div>
    <div class="page-actions">
      <button class="btn btn-primary" onclick="openCreate()"><i class="fas fa-plus"></i> Add Staff</button>
    </div>
  </div>

  <!-- Role summary -->
  <div class="stat-cards" style="grid-template-columns:repeat(5,1fr);margin-bottom:1.8rem">
    <?php $roleData=[['admin','Admin','fas fa-crown','si-purple'],['sales','Sales','fas fa-headset','si-blue'],['technician','Technician','fas fa-hard-hat','si-orange'],['finance','Finance','fas fa-coins','si-green'],['logistics','Logistics','fas fa-boxes','si-cyan']];
    foreach($roleData as [$key,$lbl,$icon,$cls]): ?>
    <div class="stat-card">
      <div class="stat-card-top">
        <div><h3><?= $lbl ?></h3><div class="value" style="font-size:1.6rem"><?= $roleCounts[$key] ?? 0 ?></div></div>
        <div class="stat-icon <?= $cls ?>"><i class="<?= $icon ?>"></i></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <div class="card-header">
      <div class="card-title">All Staff Members</div>
      <div class="filter-search" style="min-width:200px">
        <i class="fas fa-search"></i>
        <input type="text" id="staffSearch" placeholder="Search staff...">
      </div>
    </div>
    <div class="table-wrap">
      <table id="staffTable">
        <thead><tr><th>#</th><th>Staff Code</th><th>Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Department</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php $roleBadges=['admin'=>'badge-purple','sales'=>'badge-primary','technician'=>'badge-orange','finance'=>'badge-success','logistics'=>'badge-info'];
        foreach($staffList as $i=>$s): ?>
        <tr>
          <td style="color:var(--muted);font-size:.8rem"><?= $i+1 ?></td>
          <td><span class="badge badge-secondary"><?= $s['staff_code'] ?></span></td>
          <td>
            <div style="display:flex;align-items:center;gap:.7rem">
              <img src="https://ui-avatars.com/api/?name=<?= urlencode($s['full_name']) ?>&background=FF7A00&color=fff&size=32&bold=true" class="avatar-sm">
              <strong><?= htmlspecialchars($s['full_name']) ?></strong>
            </div>
          </td>
          <td style="font-size:.85rem"><?= htmlspecialchars($s['email']) ?></td>
          <td style="font-size:.85rem"><?= htmlspecialchars($s['phone']??'—') ?></td>
          <td><span class="badge <?= $roleBadges[$s['role']]??'badge-secondary' ?>"><?= ucfirst($s['role']) ?></span></td>
          <td style="font-size:.82rem"><?= htmlspecialchars($s['department']??'—') ?></td>
          <td><?= statusBadge($s['status']) ?></td>
          <td>
            <div style="display:flex;gap:.4rem">
              <button class="btn btn-sm btn-outline btn-icon" onclick="editStaff(<?= $s['id'] ?>)" title="Edit"><i class="fas fa-edit"></i></button>
              <?php if($s['id']!==1): ?>
              <button class="btn btn-sm btn-danger btn-icon" onclick="deleteStaff(<?= $s['id'] ?>,'<?= htmlspecialchars($s['full_name'],ENT_QUOTES) ?>')" title="Deactivate"><i class="fas fa-user-slash"></i></button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>
</div>

<div class="modal-overlay" id="staffModal">
<div class="modal">
  <div class="modal-header"><span class="modal-title" id="staffModalTitle">Add Staff</span><button class="modal-close"><i class="fas fa-times"></i></button></div>
  <div class="modal-body">
    <form id="staffForm">
      <input type="hidden" name="action" id="staffAction" value="create">
      <input type="hidden" name="id" id="staffId">
      <div class="form-group"><label>Full Name *</label><input type="text" name="full_name" id="sf_name" class="form-control" required></div>
      <div class="form-row">
        <div class="form-group"><label>Email *</label><input type="email" name="email" id="sf_email" class="form-control" required></div>
        <div class="form-group"><label>Phone</label><input type="text" name="phone" id="sf_phone" class="form-control"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Role</label>
          <select name="role" id="sf_role" class="form-control">
            <?php foreach(['admin','sales','technician','finance','logistics'] as $r): ?>
            <option value="<?= $r ?>"><?= ucfirst($r) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label>Department</label><input type="text" name="department" id="sf_dept" class="form-control" placeholder="e.g. Field Operations"></div>
      </div>
      <div class="form-group"><label>Status</label>
        <select name="status" id="sf_status" class="form-control">
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </select>
      </div>
      <div id="createNotice" style="padding:.7rem;background:var(--accent-light);border-radius:8px;font-size:.8rem;color:var(--accent)">
        <i class="fas fa-info-circle"></i> New staff will get default password: <strong>password</strong>
      </div>
    </form>
  </div>
  <div class="modal-footer">
    <button class="btn btn-outline" onclick="Modal.close('staffModal')">Cancel</button>
    <button class="btn btn-primary" onclick="submitStaff()"><i class="fas fa-save"></i> Save</button>
  </div>
</div>
</div>

<script src="assets/js/main.js"></script>
<script>
tableSearch('staffSearch','staffTable');
function openCreate() {
  document.getElementById('staffModalTitle').textContent='Add Staff';
  document.getElementById('staffAction').value='create';
  document.getElementById('staffForm').reset();
  document.getElementById('createNotice').style.display='';
  Modal.open('staffModal');
}
async function editStaff(id) {
  const res = await Ajax.get(`staff.php?action=get&id=${id}`);
  if (!res.success) return Notify.error('Error');
  const s = res.staff;
  document.getElementById('staffModalTitle').textContent='Edit Staff';
  document.getElementById('staffAction').value='update';
  document.getElementById('staffId').value  =s.id;
  document.getElementById('sf_name').value  =s.full_name;
  document.getElementById('sf_email').value =s.email;
  document.getElementById('sf_phone').value =s.phone??'';
  document.getElementById('sf_role').value  =s.role;
  document.getElementById('sf_dept').value  =s.department??'';
  document.getElementById('sf_status').value=s.status;
  document.getElementById('createNotice').style.display='none';
  Modal.open('staffModal');
}
async function submitStaff() {
  const fd = new FormData(document.getElementById('staffForm'));
  Notify.loading('Saving...');
  const res = await Ajax.post('staff.php', fd, true);
  Notify.close();
  if (res.success) { Notify.success('Saved!', res.message); setTimeout(()=>location.reload(),1400); }
  else Notify.error('Error', res.message);
}
async function deleteStaff(id, name) {
  const ok = await Notify.confirmDelete(name);
  if (!ok) return;
  const fd = new FormData(); fd.append('action','delete'); fd.append('id',id);
  const res = await Ajax.post('staff.php', fd, true);
  if (res.success) { Notify.success('Deactivated!'); setTimeout(()=>location.reload(),1200); }
  else Notify.error('Error', res.message);
}
</script>
</body>
</html>
