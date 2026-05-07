<?php
require_once 'config.php';
requireLogin();

if (isAjax()) {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    if ($action === 'create' || $action === 'update') {
        $name = sanitize($_POST['name'] ?? '');
        $catId = (int)$_POST['category_id'];
        $desc = sanitize($_POST['description'] ?? '');
        
        if (!$name) jsonResponse(false,'Sub-category name required.');
        if (!$catId) jsonResponse(false,'Please select a category.');
        
        if ($action === 'create') {
            $margin = hasRole('admin') ? (float)($_POST['profit_margin'] ?? 20.00) : 20.00;
            $pdo->prepare("INSERT INTO sub_categories(name,category_id,description,profit_margin) VALUES(?,?,?,?)")->execute([$name,$catId,$desc,$margin]);
            jsonResponse(true,'Sub-category added!',['reload'=>true]);
        } else {
            if (hasRole('admin')) {
                $margin = (float)($_POST['profit_margin'] ?? 20.00);
                $pdo->prepare("UPDATE sub_categories SET name=?,category_id=?,description=?,profit_margin=? WHERE id=?")->execute([$name,$catId,$desc,$margin,(int)$_POST['id']]);
            } else {
                $pdo->prepare("UPDATE sub_categories SET name=?,category_id=?,description=? WHERE id=?")->execute([$name,$catId,$desc,(int)$_POST['id']]);
            }
            jsonResponse(true,'Sub-category updated!',['reload'=>true]);
        }
    }
    if ($action === 'delete') {
        $pdo->prepare("UPDATE sub_categories SET status='inactive' WHERE id=?")->execute([(int)$_POST['id']]);
        jsonResponse(true,'Removed.');
    }
    if ($action === 'get' && isset($_GET['id'])) {
        $c = $pdo->prepare("SELECT * FROM sub_categories WHERE id=?"); $c->execute([(int)$_GET['id']]);
        jsonResponse(true,'OK',['sub'=>$c->fetch()]);
    }
    if ($action === 'by-category' && isset($_GET['category_id'])) {
        $s = $pdo->prepare("SELECT id,name,profit_margin FROM sub_categories WHERE category_id=? AND status='active' ORDER BY name");
        $s->execute([(int)$_GET['category_id']]);
        jsonResponse(true,'OK',['subs'=>$s->fetchAll()]);
    }
    jsonResponse(false,'Unknown action');
}

$subs = $pdo->query("
  SELECT sc.*, c.name as category_name,
         (SELECT COUNT(*) FROM items 
          WHERE sub_category_id = sc.id 
          AND status='active') AS item_count
  FROM sub_categories sc
  LEFT JOIN categories c ON c.id = sc.category_id
  WHERE sc.status='active'
  ORDER BY c.name, sc.name
")->fetchAll();
$categories = $pdo->query("SELECT * FROM categories WHERE status='active' ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sub-Categories — NUSU Management System</title>
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
    <div class="page-header-left"><h1>Sub-Categories</h1><p>Manage item sub-categories</p></div>
    <div class="page-actions">
      <a href="categories.php" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Categories</a>
      <button class="btn btn-primary" onclick="openCreate()"><i class="fas fa-plus"></i> Add Sub-Category</button>
    </div>
  </div>
  
  <?php if(empty($categories)): ?>
  <div class="card">
    <div class="empty-state">
      <i class="fas fa-folder-open"></i>
      <p>No categories found. <a href="categories.php">Create categories first</a></p>
    </div>
  </div>
  <?php else: ?>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1rem">
    <?php
    $icons = ['fas fa-layer-group','fas fa-tags','fas fa-boxes','fas fa-cube','fas fa-sitemap'];
    $cls   = ['si-blue','si-cyan','si-yellow','si-green','si-orange'];
    foreach($subs as $i=>$s): ?>
    <div class="stat-card">
      <div class="stat-card-top">
        <div>
          <div style="font-size:.75rem;color:var(--primary);text-transform:uppercase;letter-spacing:.5px;margin-bottom:.3rem">
            <?= htmlspecialchars($s['category_name']) ?>
          </div>
          <h3><?= htmlspecialchars($s['name']) ?></h3>
          <div class="value" style="font-size:1.8rem"><?= $s['item_count'] ?></div>
          <div style="font-size:.78rem;color:var(--muted);margin-top:.3rem"><?= htmlspecialchars($s['description']??'') ?></div>
        </div>
        <div class="stat-icon <?= $cls[$i%5] ?>"><i class="<?= $icons[$i%5] ?>"></i></div>
      </div>
      <div style="display:flex;gap:.5rem;margin-top:1rem">
        <button class="btn btn-sm btn-outline" style="flex:1" onclick="editSub(<?= $s['id'] ?>)"><i class="fas fa-edit"></i> Edit</button>
        <button class="btn btn-sm btn-danger btn-icon" onclick="delSub(<?= $s['id'] ?>, '<?= htmlspecialchars($s['name'],ENT_QUOTES) ?>')"><i class="fas fa-trash"></i></button>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if(empty($subs)): ?>
    <div class="card" style="grid-column: 1/-1">
      <div class="empty-state">
        <i class="fas fa-tags"></i>
        <p>No sub-categories yet. Click "Add Sub-Category" to create one.</p>
      </div>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</main>
</div>

<div class="modal-overlay" id="subModal">
<div class="modal">
  <div class="modal-header"><span class="modal-title" id="subModalTitle">Add Sub-Category</span><button class="modal-close"><i class="fas fa-times"></i></button></div>
  <div class="modal-body">
    <form id="subForm">
      <input type="hidden" name="action" id="subAction" value="create">
      <input type="hidden" name="id" id="subId">
      <div class="form-group">
        <label>Category *</label>
        <select name="category_id" id="sub_cat_id" class="form-control" required>
          <option value="">Select Category</option>
          <?php foreach($categories as $c): ?>
          <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Sub-Category Name *</label><input type="text" name="name" id="sub_name" class="form-control" required placeholder="e.g. Power Cables"></div>
      <?php if (hasRole('admin')): ?>
      <div class="form-group"><label>Profit Margin (%)</label><input type="number" step="0.01" name="profit_margin" id="sub_margin" class="form-control" value="20.00" required></div>
      <?php endif; ?>
      <div class="form-group"><label>Description</label><textarea name="description" id="sub_desc" class="form-control" rows="2" placeholder="Brief description"></textarea></div>
    </form>
  </div>
  <div class="modal-footer">
    <button class="btn btn-outline" onclick="Modal.close('subModal')">Cancel</button>
    <button class="btn btn-primary" onclick="submitSub()"><i class="fas fa-save"></i> Save</button>
  </div>
</div>
</div>

<script src="assets/js/main.js"></script>
<script>
function openCreate() {
  document.getElementById('subModalTitle').textContent='Add Sub-Category';
  document.getElementById('subAction').value='create';
  document.getElementById('subForm').reset();
  Modal.open('subModal');
}
async function editSub(id) {
  const res=await Ajax.get(`sub_categories.php?action=get&id=${id}`);
  if(!res.success) return;
  const s=res.sub;
  document.getElementById('subModalTitle').textContent='Edit Sub-Category';
  document.getElementById('subAction').value='update';
  document.getElementById('subId').value=s.id;
  document.getElementById('sub_cat_id').value=s.category_id;
  document.getElementById('sub_name').value=s.name;
  if(document.getElementById('sub_margin')) document.getElementById('sub_margin').value=s.profit_margin || '20.00';
  document.getElementById('sub_desc').value=s.description??'';
  Modal.open('subModal');
}
async function submitSub() {
  const fd=new FormData(document.getElementById('subForm'));
  Notify.loading('Saving...');
  const res=await Ajax.post('sub_categories.php',fd,true);
  Notify.close();
  if(res.success){Notify.success('Saved!',res.message);setTimeout(()=>location.reload(),1200);}
  else Notify.error('Error',res.message);
}
async function delSub(id,name) {
  const ok=await Notify.confirmDelete(name);
  if(!ok) return;
  const fd=new FormData();fd.append('action','delete');fd.append('id',id);
  const res=await Ajax.post('sub_categories.php',fd,true);
  if(res.success){Notify.success('Removed!');setTimeout(()=>location.reload(),1200);}
  else Notify.error('Error',res.message);
}
</script>
</body>
</html>