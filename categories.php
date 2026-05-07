<?php
require_once 'config.php';
requireLogin();

if (isAjax()) {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    if ($action === 'create' || $action === 'update') {
        $name = sanitize($_POST['name'] ?? '');
        $desc = sanitize($_POST['description'] ?? '');
        if (!$name) jsonResponse(false,'Category name required.');
        if ($action === 'create') {
            $pdo->prepare("INSERT INTO categories(name,description) VALUES(?,?)")->execute([$name,$desc]);
            jsonResponse(true,'Category added!',['reload'=>true]);
        } else {
            $pdo->prepare("UPDATE categories SET name=?,description=? WHERE id=?")->execute([$name,$desc,(int)$_POST['id']]);
            jsonResponse(true,'Category updated!',['reload'=>true]);
        }
    }
    if ($action === 'delete') {
        $pdo->prepare("UPDATE categories SET status='inactive' WHERE id=?")->execute([(int)$_POST['id']]);
        jsonResponse(true,'Removed.');
    }
    if ($action === 'get' && isset($_GET['id'])) {
        $c = $pdo->prepare("SELECT * FROM categories WHERE id=?"); $c->execute([(int)$_GET['id']]);
        jsonResponse(true,'OK',['category'=>$c->fetch()]);
    }
    jsonResponse(false,'Unknown action');
}

$cats = $pdo->query("
  SELECT categories.*,
         (SELECT COUNT(*) FROM items 
          WHERE category_id = categories.id 
          AND status='active') AS item_count
  FROM categories 
  WHERE status='active'
  ORDER BY name
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Categories — NUSU Management System</title>
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
    <div class="page-header-left"><h1>Item Categories</h1><p>Manage inventory categories</p></div>
    <div class="page-actions">
      <button class="btn btn-primary" onclick="openCreate()"><i class="fas fa-plus"></i> Add Category</button>
    </div>
  </div>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:1rem">
    <?php
    $icons = ['fas fa-bolt','fas fa-tv','fas fa-faucet','fas fa-tools','fas fa-hard-hat'];
    $cls   = ['si-yellow','si-blue','si-cyan','si-orange','si-green'];
    foreach($cats as $i=>$c): ?>
    <div class="stat-card" style="cursor:pointer">
      <div class="stat-card-top">
        <div>
          <h3><?= htmlspecialchars($c['name']) ?></h3>
          <div class="value" style="font-size:1.8rem"><?= $c['item_count'] ?></div>
          <div style="font-size:.78rem;color:var(--muted);margin-top:.3rem"><?= htmlspecialchars($c['description']??'') ?></div>
        </div>
        <div class="stat-icon <?= $cls[$i%5] ?>"><i class="<?= $icons[$i%5] ?>"></i></div>
      </div>
      <div style="display:flex;gap:.5rem;margin-top:1rem">
        <button class="btn btn-sm btn-outline" style="flex:1" onclick="editCat(<?= $c['id'] ?>)"><i class="fas fa-edit"></i> Edit</button>
        <button class="btn btn-sm btn-danger btn-icon" onclick="delCat(<?= $c['id'] ?>, '<?= htmlspecialchars($c['name'],ENT_QUOTES) ?>')"><i class="fas fa-trash"></i></button>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</main>
</div>

<div class="modal-overlay" id="catModal">
<div class="modal">
  <div class="modal-header"><span class="modal-title" id="catModalTitle">Add Category</span><button class="modal-close"><i class="fas fa-times"></i></button></div>
  <div class="modal-body">
    <form id="catForm">
      <input type="hidden" name="action" id="catAction" value="create">
      <input type="hidden" name="id" id="catId">
      <div class="form-group"><label>Category Name *</label><input type="text" name="name" id="cat_name" class="form-control" required placeholder="e.g. Electrical Supplies"></div>
      <div class="form-group"><label>Description</label><textarea name="description" id="cat_desc" class="form-control" rows="2" placeholder="Brief description"></textarea></div>
    </form>
  </div>
  <div class="modal-footer">
    <button class="btn btn-outline" onclick="Modal.close('catModal')">Cancel</button>
    <button class="btn btn-primary" onclick="submitCat()"><i class="fas fa-save"></i> Save</button>
  </div>
</div>
</div>

<script src="assets/js/main.js"></script>
<script>
function openCreate() {
  document.getElementById('catModalTitle').textContent='Add Category';
  document.getElementById('catAction').value='create';
  document.getElementById('catForm').reset();
  Modal.open('catModal');
}
async function editCat(id) {
  const res=await Ajax.get(`categories.php?action=get&id=${id}`);
  if(!res.success) return;
  const c=res.category;
  document.getElementById('catModalTitle').textContent='Edit Category';
  document.getElementById('catAction').value='update';
  document.getElementById('catId').value=c.id;
  document.getElementById('cat_name').value=c.name;
  document.getElementById('cat_desc').value=c.description??'';
  Modal.open('catModal');
}
async function submitCat() {
  const fd=new FormData(document.getElementById('catForm'));
  Notify.loading('Saving...');
  const res=await Ajax.post('categories.php',fd,true);
  Notify.close();
  if(res.success){Notify.success('Saved!',res.message);setTimeout(()=>location.reload(),1200);}
  else Notify.error('Error',res.message);
}
async function delCat(id,name) {
  const ok=await Notify.confirmDelete(name);
  if(!ok) return;
  const fd=new FormData();fd.append('action','delete');fd.append('id',id);
  const res=await Ajax.post('categories.php',fd,true);
  if(res.success){Notify.success('Removed!');setTimeout(()=>location.reload(),1200);}
  else Notify.error('Error',res.message);
}
</script>
</body>
</html>
