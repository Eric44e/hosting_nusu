<?php
require_once 'config.php';
requireLogin();

if (isAjax()) {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    if ($action === 'create' || $action === 'update') {
        $name   = sanitize($_POST['full_name'] ?? '');
        $email  = sanitize($_POST['email'] ?? '');
        $phone  = sanitize($_POST['phone'] ?? '');
        $spec   = sanitize($_POST['specialization'] ?? '');
        $dept   = sanitize($_POST['department'] ?? 'Field Operations');
        $status = $_POST['status'] ?? 'active';
        if (!$name || !$email) jsonResponse(false,'Name and email are required.');
        if ($action === 'create') {
            try {
                $code     = 'TCH-' . str_pad(rand(100,999),3,'0',STR_PAD_LEFT);
                $passHash = password_hash('password', PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("INSERT INTO staff(staff_code,full_name,email,phone,password,role,department,status) VALUES(?,?,?,?,?,?,?,?)");
                $stmt->execute([$code,$name,$email,$phone,$passHash,'technician',$dept,$status]);
                $staffId = $pdo->lastInsertId();
                $stmt2 = $pdo->prepare("INSERT INTO technicians(staff_id,specialization,status) VALUES(?,?,?)");
                $stmt2->execute([$staffId,$spec,$status]);
                jsonResponse(true,"Technician $code added!", ['reload'=>true]);
            } catch (Exception $e) {
                jsonResponse(false,'Error adding technician: '.$e->getMessage());
            }
        } else {
            $id = (int)$_POST['id'];
            $pdo->prepare("UPDATE staff SET full_name=?,email=?,phone=?,department=?,status=? WHERE id=?")
                ->execute([$name,$email,$phone,$dept,$status,$id]);
            $pdo->prepare("UPDATE technicians SET specialization=?,status=? WHERE staff_id=?")
                ->execute([$spec,$status,$id]);
            jsonResponse(true,'Technician updated!', ['reload'=>true]);
        }
    }
    if ($action === 'delete') {
        $pdo->prepare("UPDATE staff SET status='inactive' WHERE id=?")->execute([(int)$_POST['id']]);
        jsonResponse(true,'Technician deactivated.');
    }
    if ($action === 'get' && isset($_GET['id'])) {
        $t = $pdo->prepare("SELECT s.*,tech.specialization,tech.rating,tech.total_jobs FROM staff s LEFT JOIN technicians tech ON tech.staff_id=s.id WHERE s.id=?");
        $t->execute([(int)$_GET['id']]);
        jsonResponse(true,'OK',['tech'=>$t->fetch()]);
    }
    jsonResponse(false,'Unknown action');
}

$techs = $pdo->query("SELECT s.*,tech.id tech_id,tech.specialization,tech.rating,tech.total_jobs,tech.status tech_status,
    COALESCE((SELECT COUNT(*) FROM tickets WHERE technician_id=tech.id AND status='ongoing' LIMIT 1),0) active_tickets
    FROM staff s JOIN technicians tech ON tech.staff_id=s.id WHERE s.status='active' ORDER BY s.full_name LIMIT 100")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Technicians — NUSU Management System</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
.tech-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(260px,1fr)); gap:1.2rem; }
.tech-card { background:var(--card); border:1px solid var(--border); border-radius:16px; padding:1.4rem; transition:all .2s; }
.tech-card:hover { transform:translateY(-3px); box-shadow:0 12px 32px rgba(0,0,0,.3); border-color:var(--primary); }
.tech-card-top { display:flex; align-items:center; gap:1rem; margin-bottom:1rem; }
.tech-avatar { width:52px; height:52px; border-radius:14px; object-fit:cover; }
.tech-stars { color:var(--warning); font-size:.75rem; }
.tech-stats { display:grid; grid-template-columns:1fr 1fr 1fr; gap:.5rem; margin-top:1rem; }
.tech-stat { text-align:center; padding:.5rem; background:rgba(255,255,255,.04); border-radius:8px; }
.tech-stat-num { font-family:'Syne',sans-serif; font-size:1.1rem; font-weight:700; }
.tech-stat-lbl { font-size:.68rem; color:var(--muted); }
</style>
</head>
<body>
<div class="app-wrapper">
<?php include 'includes/layout.php'; ?>
<main class="main-content fade-in">
  <div class="page-header">
    <div class="page-header-left"><h1>Technicians</h1><p>Field team management</p></div>
    <div class="page-actions">
      <button class="btn btn-primary" onclick="openCreate()"><i class="fas fa-plus"></i> Add Technician</button>
    </div>
  </div>

  <div class="tech-grid">
  <?php foreach($techs as $t):
    $statusColors = ['active'=>'var(--success)','on_leave'=>'var(--warning)','inactive'=>'var(--muted)'];
    $sc = $statusColors[$t['tech_status']] ?? 'var(--muted)';
    $stars = round($t['rating'] ?? 5);
  ?>
  <div class="tech-card">
    <div class="tech-card-top">
      <div style="position:relative">
        <img src="https://ui-avatars.com/api/?name=<?= urlencode($t['full_name']) ?>&background=FF7A00&color=fff&size=52&bold=true" class="tech-avatar">
        <div style="position:absolute;bottom:-3px;right:-3px;width:14px;height:14px;border-radius:50%;background:<?= $sc ?>;border:2px solid var(--card)"></div>
      </div>
      <div>
        <div style="font-weight:700;font-size:.95rem"><?= htmlspecialchars($t['full_name']) ?></div>
        <div style="font-size:.78rem;color:var(--muted)"><?= htmlspecialchars($t['specialization']??'—') ?></div>
        <div class="tech-stars">
          <?php for($i=0;$i<5;$i++) echo $i < $stars ? '★' : '☆'; ?>
          <span style="color:var(--muted);font-size:.72rem"> <?= number_format($t['rating'],1) ?></span>
        </div>
      </div>
      <?= statusBadge($t['tech_status']) ?>
    </div>
    <div style="font-size:.8rem;color:var(--muted)"><i class="fas fa-envelope"></i> <?= htmlspecialchars($t['email']) ?></div>
    <div style="font-size:.8rem;color:var(--muted);margin-top:.2rem"><i class="fas fa-phone"></i> <?= htmlspecialchars($t['phone']??'—') ?></div>
    <div class="tech-stats">
      <div class="tech-stat"><div class="tech-stat-num" style="color:var(--primary)"><?= $t['active_tickets'] ?></div><div class="tech-stat-lbl">Active</div></div>
      <div class="tech-stat"><div class="tech-stat-num" style="color:var(--success)"><?= $t['total_jobs'] ?></div><div class="tech-stat-lbl">Total Jobs</div></div>
      <div class="tech-stat"><div class="tech-stat-num" style="color:var(--warning)"><?= number_format($t['rating'],1) ?></div><div class="tech-stat-lbl">Rating</div></div>
    </div>
    <div style="display:flex;gap:.5rem;margin-top:1rem">
      <button class="btn btn-sm btn-outline" style="flex:1" onclick="editTech(<?= $t['id'] ?>)"><i class="fas fa-edit"></i> Edit</button>
      <button class="btn btn-sm btn-danger btn-icon" onclick="deleteTech(<?= $t['id'] ?>, '<?= htmlspecialchars($t['full_name'],ENT_QUOTES) ?>')"><i class="fas fa-trash"></i></button>
    </div>
  </div>
  <?php endforeach; ?>
  </div>
</main>
</div>

<!-- Tech Form Modal -->
<div class="modal-overlay" id="techModal">
<div class="modal">
  <div class="modal-header">
    <span class="modal-title" id="techModalTitle">Add Technician</span>
    <button class="modal-close"><i class="fas fa-times"></i></button>
  </div>
  <div class="modal-body">
    <form id="techForm">
      <input type="hidden" name="action" id="techAction" value="create">
      <input type="hidden" name="id" id="techId">
      <div class="form-group"><label>Full Name *</label><input type="text" name="full_name" id="tf_name" class="form-control" required></div>
      <div class="form-row">
        <div class="form-group"><label>Email *</label><input type="email" name="email" id="tf_email" class="form-control" required></div>
        <div class="form-group"><label>Phone</label><input type="text" name="phone" id="tf_phone" class="form-control"></div>
      </div>
      <div class="form-group"><label>Specialization</label><input type="text" name="specialization" id="tf_spec" class="form-control" placeholder="e.g. Electrical & Wiring"></div>
      <div class="form-row">
        <div class="form-group"><label>Department</label><input type="text" name="department" id="tf_dept" class="form-control" value="Field Operations"></div>
        <div class="form-group"><label>Status</label>
          <select name="status" id="tf_status" class="form-control">
            <option value="active">Active</option>
            <option value="on_leave">On Leave</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
      </div>
    </form>
  </div>
  <div class="modal-footer">
    <button class="btn btn-outline" onclick="Modal.close('techModal')">Cancel</button>
    <button class="btn btn-primary" onclick="submitTech()"><i class="fas fa-save"></i> Save</button>
  </div>
</div>
</div>

<script src="assets/js/main.js"></script>
<script>
function openCreate() {
  document.getElementById('techModalTitle').textContent = 'Add Technician';
  document.getElementById('techAction').value = 'create';
  document.getElementById('techForm').reset();
  document.getElementById('tf_dept').value = 'Field Operations';
  Modal.open('techModal');
}
async function editTech(id) {
  const res = await Ajax.get(`technicians.php?action=get&id=${id}`);
  if (!res.success) return Notify.error('Error');
  const t = res.tech;
  document.getElementById('techModalTitle').textContent = 'Edit Technician';
  document.getElementById('techAction').value = 'update';
  document.getElementById('techId').value   = t.id;
  document.getElementById('tf_name').value  = t.full_name;
  document.getElementById('tf_email').value = t.email;
  document.getElementById('tf_phone').value = t.phone ?? '';
  document.getElementById('tf_spec').value  = t.specialization ?? '';
  document.getElementById('tf_dept').value  = t.department ?? '';
  document.getElementById('tf_status').value= t.status;
  Modal.open('techModal');
}
async function submitTech() {
  const fd = new FormData(document.getElementById('techForm'));
  Notify.loading('Saving...');
  const res = await Ajax.post('technicians.php', fd, true);
  Notify.close();
  if (res.success) { Notify.success('Saved!', res.message); setTimeout(()=>location.reload(),1200); }
  else Notify.error('Error', res.message);
}
async function deleteTech(id, name) {
  const ok = await Notify.confirmDelete(name);
  if (!ok) return;
  const fd = new FormData(); fd.append('action','delete'); fd.append('id',id);
  const res = await Ajax.post('technicians.php', fd, true);
  if (res.success) { Notify.success('Deactivated!'); setTimeout(()=>location.reload(),1200); }
  else Notify.error('Error', res.message);
}
</script>
</body>
</html>
