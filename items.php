<?php
require_once 'config.php';
requireLogin();

if (isAjax()) {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    if ($action === 'create' || $action === 'update') {
        $name = sanitize($_POST['name'] ?? '');
        $code = sanitize($_POST['item_code'] ?? '');
        $cat = (int)($_POST['category_id'] ?? 0);
        $sub_cat = (int)($_POST['sub_category_id'] ?? 0);
        $cost = (float)($_POST['cost_price'] ?? 0);
        
        // Smart Pricing Logic: get sub_category profit_margin
        $margin = 0;
        if ($sub_cat) {
            $stmt = $pdo->prepare("SELECT profit_margin FROM sub_categories WHERE id=?");
            $stmt->execute([$sub_cat]);
            $margin = (float)$stmt->fetchColumn();
        }
        $price = $cost + ($cost * ($margin / 100));
        
        $qty = (int)($_POST['quantity'] ?? 0);
        $min_qty = (int)($_POST['min_quantity'] ?? 5);

        if (!$name) jsonResponse(false,'Item name required.');
        if ($action === 'create') {
            $lastNum = (int)$pdo->query("SELECT MAX(CAST(SUBSTRING(item_code,5) AS UNSIGNED)) FROM items WHERE item_code LIKE 'ITM-%'")->fetchColumn();
            $code = 'ITM-' . str_pad($lastNum + 1, 4, '0', STR_PAD_LEFT);
            $stmt = $pdo->prepare("INSERT INTO items(item_code,name,category_id,sub_category_id,cost_price,selling_price,quantity,min_quantity) VALUES(?,?,?,?,?,?,?,?)");
            $stmt->execute([$code,$name,$cat,$sub_cat,$cost,$price,$qty,$min_qty]);
            $itemId = $pdo->lastInsertId();

            if ($qty > 0) {
                $pdo->prepare("INSERT INTO stock_movements(item_id,type,quantity,notes,staff_id) VALUES(?,'in',?,'Initial stock',?)")
                    ->execute([$itemId, $qty, $_SESSION['staff_id']]);
            }
            jsonResponse(true,'Item added!',['reload'=>true]);
        } else {
            $pdo->prepare("UPDATE items SET item_code=?,name=?,category_id=?,sub_category_id=?,cost_price=?,selling_price=?,quantity=?,min_quantity=? WHERE id=?")
                ->execute([$code,$name,$cat,$sub_cat,$cost,$price,$qty,$min_qty,(int)$_POST['id']]);
            jsonResponse(true,'Item updated!',['reload'=>true]);
        }
    }
    if ($action === 'delete') {
        $pdo->prepare("UPDATE items SET status='inactive' WHERE id=?")->execute([(int)$_POST['id']]);
        jsonResponse(true,'Removed.');
    }
    if ($action === 'get' && isset($_GET['id'])) {
        $c = $pdo->prepare("SELECT * FROM items WHERE id=?"); $c->execute([(int)$_GET['id']]);
        jsonResponse(true,'OK',['item'=>$c->fetch()]);
    }
    jsonResponse(false,'Unknown action');
}

$items = $pdo->query("
  SELECT i.*, c.name as cat_name 
  FROM items i 
  LEFT JOIN categories c ON c.id=i.category_id 
  WHERE i.status='active' 
  ORDER BY i.name
")->fetchAll();

$categories = $pdo->query("SELECT id, name FROM categories WHERE status='active' ORDER BY name")->fetchAll();
$sub_categories = $pdo->query("SELECT * FROM sub_categories r LEFT JOIN categories c ON r.category_id=c.id  ORDER BY r.name ")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Items — NUSU Management System</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<div class="app-wrapper">
<?php include 'includes/layout.php'; ?>
<main class="main-content fade-in">
  <div class="page-header">
    <div class="page-header-left"><h1>Inventory Items</h1><p>Manage products and stock levels</p></div>
    <div class="page-actions">
      <button class="btn btn-primary" onclick="openCreate()"><i class="fas fa-plus"></i> Add Item</button>
    </div>
  </div>
  
  <div class="card">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Code</th>
            <th>Name</th>
            <th>Category</th>
            <th>Cost</th>
            <th>Price</th>
            <th>Stock</th>
            <th>Min</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($items as $it): ?>
          <tr>
            <td style="font-weight:600;color:var(--primary)"><?= htmlspecialchars($it['item_code']) ?></td>
            <td><?= htmlspecialchars($it['name']) ?></td>
            <td><?= htmlspecialchars($it['cat_name'] ?? '—') ?></td>
            <td><?= formatMoney((float)$it['cost_price']) ?></td>
            <td><?= formatMoney((float)$it['selling_price']) ?></td>
            <td>
              <?php if($it['quantity'] <= $it['min_quantity']): ?>
                <span class="badge badge-danger"><?= $it['quantity'] ?></span>
              <?php else: ?>
                <span class="badge badge-success"><?= $it['quantity'] ?></span>
              <?php endif; ?>
            </td>
            <td><?= $it['min_quantity'] ?></td>
            <td>
              <button class="btn btn-sm btn-outline btn-icon" onclick="editItem(<?= $it['id'] ?>)"><i class="fas fa-edit"></i></button>
              <button class="btn btn-sm btn-danger btn-icon" onclick="delItem(<?= $it['id'] ?>, '<?= htmlspecialchars($it['name'],ENT_QUOTES) ?>')"><i class="fas fa-trash"></i></button>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if(!$items): ?>
          <tr><td colspan="8" class="empty-state">No active items found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>
</div>

<div class="modal-overlay" id="itemModal">
<div class="modal">
  <div class="modal-header"><span class="modal-title" id="itemModalTitle">Add Item</span><button class="modal-close" onclick="Modal.close('itemModal')"><i class="fas fa-times"></i></button></div>
  <div class="modal-body">
    <form id="itemForm">
      <input type="hidden" name="action" id="itemAction" value="create">
      <input type="hidden" name="id" id="itemId">
      
      <div class="form-row">
        <div class="form-group"><label>Item Code</label><input type="text" name="item_code" id="i_code" class="form-control" placeholder="Leave empty to auto-generate"></div>
        <div class="form-group"><label>Category</label>
          <select name="category_id" id="i_cat" class="form-control">
            <option value="">Select Category</option>
            <?php foreach($categories as $c): ?>
            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
          <div class="form-group"><label> Sub Category</label>
          <select name="sub_category_id" id="i_sub" class="form-control">
            <option value="">Select sub Category</option>
            <?php foreach($sub_categories as $d): ?>
            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      
      <div class="form-group"><label>Item Name *</label><input type="text" name="name" id="i_name" class="form-control" required></div>
      
      <div class="form-row-3">
        <div class="form-group"><label>Cost Price</label><input type="number" step="0.01" name="cost_price" id="i_cost" class="form-control" value="0"></div>
        <div class="form-group"><label>Selling Price (Auto calculated)</label><input type="number" step="0.01" name="selling_price" id="i_price" class="form-control" value="0" readonly></div>
        <div class="form-group"><label>Unit</label><input type="text" name="unit" id="i_unit" class="form-control" value="piece"></div>
      </div>
      
      <div class="form-row">
        <div class="form-group"><label>Current Qty</label><input type="number" name="quantity" id="i_qty" class="form-control" value="0"></div>
        <div class="form-group"><label>Min Qty (Alert)</label><input type="number" name="min_quantity" id="i_min" class="form-control" value="5"></div>
      </div>
      
    </form>
  </div>
  <div class="modal-footer">
    <button class="btn btn-outline" onclick="Modal.close('itemModal')">Cancel</button>
    <button class="btn btn-primary" onclick="submitItem()"><i class="fas fa-save"></i> Save</button>
  </div>
</div>
</div>

<script src="assets/js/main.js"></script>
<script>
function openCreate() {
  document.getElementById('itemModalTitle').textContent='Add Item';
  document.getElementById('itemAction').value='create';
  document.getElementById('itemForm').reset();
  Modal.open('itemModal');
}
async function editItem(id) {
  const res=await Ajax.get(`items.php?action=get&id=${id}`);
  if(!res.success) return;
  const it=res.item;
  document.getElementById('itemModalTitle').textContent='Edit Item';
  document.getElementById('itemAction').value='update';
  document.getElementById('itemId').value=it.id;
  document.getElementById('i_code').value=it.item_code;
  document.getElementById('i_name').value=it.name;
  
  // Set category and trigger change to load subcategories
  document.getElementById('i_cat').value=it.category_id||'';
  await loadSubCategories(it.category_id||'');
  
  document.getElementById('i_sub').value=it.sub_category_id||'';
  
  document.getElementById('i_cost').value=it.cost_price;
  document.getElementById('i_price').value=it.selling_price;
  document.getElementById('i_qty').value=it.quantity;
  document.getElementById('i_min').value=it.min_quantity;
  Modal.open('itemModal');
}

let currentSubCategories = [];
async function loadSubCategories(catId) {
    const subSelect = document.getElementById('i_sub');
    subSelect.innerHTML = '<option value="">Select sub Category</option>';
    currentSubCategories = [];
    if (!catId) return;
    const res = await Ajax.get(`sub_categories.php?action=by-category&category_id=${catId}`);
    if (res.success) {
        currentSubCategories = res.subs;
        res.subs.forEach(s => {
            const opt = document.createElement('option');
            opt.value = s.id;
            opt.textContent = s.name;
            subSelect.appendChild(opt);
        });
    }
}

document.getElementById('i_cat').addEventListener('change', async (e) => {
    await loadSubCategories(e.target.value);
    calculatePrice();
});

document.getElementById('i_sub').addEventListener('change', calculatePrice);
document.getElementById('i_cost').addEventListener('input', calculatePrice);

function calculatePrice() {
    const cost = parseFloat(document.getElementById('i_cost').value) || 0;
    const subId = document.getElementById('i_sub').value;
    let margin = 0;
    if (subId) {
        const sub = currentSubCategories.find(s => s.id == subId);
        if (sub && sub.profit_margin) margin = parseFloat(sub.profit_margin);
    }
    const price = cost + (cost * (margin / 100));
    document.getElementById('i_price').value = price.toFixed(2);
}
async function submitItem() {
  const fd=new FormData(document.getElementById('itemForm'));
  Notify.loading('Saving...');
  const res=await Ajax.post('items.php',fd,true);
  Notify.close();
  if(res.success){Notify.success('Saved!',res.message);setTimeout(()=>location.reload(),1200);}
  else Notify.error('Error',res.message);
}
async function delItem(id,name) {
  const ok=await Notify.confirmDelete(name);
  if(!ok) return;
  const fd=new FormData();fd.append('action','delete');fd.append('id',id);
  const res=await Ajax.post('items.php',fd,true);
  if(res.success){Notify.success('Removed!');setTimeout(()=>location.reload(),1200);}
  else Notify.error('Error',res.message);
}
</script>
</body>
</html>
