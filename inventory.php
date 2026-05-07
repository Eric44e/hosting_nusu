<?php
require_once 'config.php';
requireLogin();

if (isAjax()) {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    if ($action === 'create' || $action === 'update') {
        $name    = sanitize($_POST['name'] ?? '');
        $catId   = (int)$_POST['category_id'];
        $subId   = (int)$_POST['sub_category_id'];
        $unit    = sanitize($_POST['unit'] ?? 'piece');
        $cost    = (float)$_POST['cost_price'];
        $sell    = (float)$_POST['selling_price'];
        $qty     = (int)$_POST['quantity'];
        $minQty  = (int)$_POST['min_quantity'];
        $desc    = sanitize($_POST['description'] ?? '');
        if (!$name) jsonResponse(false,'Item name is required.');
        if ($action === 'create') {
            $lastNum = (int)$pdo->query("SELECT MAX(CAST(SUBSTRING(item_code,5) AS UNSIGNED)) FROM items WHERE item_code LIKE 'ITM-%'")->fetchColumn();
            $code = 'ITM-' . str_pad($lastNum + 1, 4, '0', STR_PAD_LEFT);
            $stmt = $pdo->prepare("INSERT INTO items(item_code,name,category_id,sub_category_id,unit,cost_price,selling_price,quantity,min_quantity,description) VALUES(?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$code,$name,$catId,$subId,$unit,$cost,$sell,$qty,$minQty,$desc]);
            $itemId = $pdo->lastInsertId();
            
            // Record initial stock movement if qty > 0
            if ($qty > 0) {
                $pdo->prepare("INSERT INTO stock_movements(item_id,type,quantity,notes,staff_id) VALUES(?,'in',?,'Initial stock',?)")
                    ->execute([$itemId, $qty, $_SESSION['staff_id']]);
            }
            jsonResponse(true,"Item $code added!", ['reload'=>true]);
        } else {
            $id = (int)$_POST['id'];
            $pdo->prepare("UPDATE items SET name=?,category_id=?,sub_category_id=?,unit=?,cost_price=?,selling_price=?,min_quantity=?,description=? WHERE id=?")
                ->execute([$name,$catId,$subId,$unit,$cost,$sell,$minQty,$desc,$id]);
            jsonResponse(true,'Item updated!', ['reload'=>true]);
        }
    }
    if ($action === 'adjust_stock') {
        $id   = (int)$_POST['id'];
        $type = $_POST['type']; // in, out, adjustment
        $qty  = (int)$_POST['quantity'];
        $note = sanitize($_POST['notes'] ?? '');
        if ($type === 'out') {
            $cur = $pdo->query("SELECT quantity FROM items WHERE id=$id")->fetchColumn();
            if ($cur < $qty) jsonResponse(false,"Insufficient stock. Available: $cur");
            $pdo->prepare("UPDATE items SET quantity=quantity-? WHERE id=?")->execute([$qty,$id]);
        } elseif ($type === 'in') {
            $pdo->prepare("UPDATE items SET quantity=quantity+? WHERE id=?")->execute([$qty,$id]);
        } else {
            $pdo->prepare("UPDATE items SET quantity=? WHERE id=?")->execute([$qty,$id]);
        }
        $pdo->prepare("INSERT INTO stock_movements(item_id,type,quantity,notes,staff_id) VALUES(?,?,?,?,?)")
            ->execute([$id,$type,$qty,$note,$_SESSION['staff_id']]);
        jsonResponse(true,'Stock adjusted!', ['reload'=>true]);
    }
    if ($action === 'delete') {
        $pdo->prepare("UPDATE items SET status='inactive' WHERE id=?")->execute([(int)$_POST['id']]);
        jsonResponse(true,'Item removed.');
    }
    if ($action === 'get' && isset($_GET['id'])) {
        $i = $pdo->prepare("SELECT * FROM items WHERE id=?"); $i->execute([(int)$_GET['id']]);
        jsonResponse(true,'OK',['item'=>$i->fetch()]);
    }
    if ($action === 'subs' && isset($_GET['cat'])) {
        $s = $pdo->prepare("SELECT id,name FROM sub_categories WHERE category_id=? AND status='active'");
        $s->execute([(int)$_GET['cat']]);
        jsonResponse(true,'OK',['subs'=>$s->fetchAll()]);
    }
    jsonResponse(false,'Unknown action');
}

$filter  = $_GET['filter'] ?? 'all';
$catF    = (int)($_GET['cat'] ?? 0);
$search  = $_GET['q'] ?? '';
$page    = max(1,(int)($_GET['page']??1));
$perPage = 15; $offset = ($page-1)*$perPage;
$where   = "i.status='active'"; $params = [];
if ($filter === 'low')     { $where .= ' AND i.quantity<=i.min_quantity AND i.quantity>0'; }
if ($filter === 'out')     { $where .= ' AND i.quantity=0'; }
if ($catF)                 { $where .= ' AND i.category_id=?'; $params[]=$catF; }
if ($search)               { $where .= ' AND (i.name LIKE ? OR i.item_code LIKE ?)'; $l="%$search%"; $params[]=$l;$params[]=$l; }
$cnt = $pdo->prepare("SELECT COUNT(*) FROM items i WHERE $where"); $cnt->execute($params); $totalRows=$cnt->fetchColumn();
$totalPages = ceil($totalRows/$perPage);
$stmt = $pdo->prepare("SELECT i.*,c.name cat_name,s.name sub_name FROM items i
    LEFT JOIN categories c ON c.id=i.category_id
    LEFT JOIN sub_categories s ON s.id=i.sub_category_id
    WHERE $where ORDER BY i.created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params); $items=$stmt->fetchAll();
$categories = $pdo->query("SELECT * FROM categories WHERE status='active' ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Inventory — NUSU Management System</title>
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
    <div class="page-header-left"><h1>Store / Inventory</h1><p>Manage stock and items</p></div>
    <div class="page-actions">
      <button class="btn btn-outline" onclick="Modal.open('stockModal')"><i class="fas fa-boxes"></i> Adjust Stock</button>
      <button class="btn btn-primary" onclick="openCreate()"><i class="fas fa-plus"></i> Add Item</button>
    </div>
  </div>

  <!-- Quick Filter Tabs -->
  <div style="display:flex;gap:.5rem;margin-bottom:1.2rem;flex-wrap:wrap">
    <?php
    $tabs = ['all'=>'All Items','low'=>'Low Stock','out'=>'Out of Stock'];
    foreach($tabs as $k=>$v): ?>
    <a href="?filter=<?= $k ?>&q=<?= urlencode($search) ?>" class="btn btn-sm <?= $filter===$k?'btn-primary':'btn-outline' ?>"><?= $v ?></a>
    <?php endforeach; ?>
  </div>

  <div class="filter-bar">
    <form method="GET" style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap">
      <input type="hidden" name="filter" value="<?= $filter ?>">
      <div class="filter-search">
        <i class="fas fa-search"></i>
        <input type="text" name="q" placeholder="Search items..." value="<?= htmlspecialchars($search) ?>">
      </div>
      <select name="cat" class="filter-select">
        <option value="">All Categories</option>
        <?php foreach($categories as $c): ?>
        <option value="<?= $c['id'] ?>" <?= $catF==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-outline btn-sm"><i class="fas fa-filter"></i> Filter</button>
    </form>
    <span style="margin-left:auto;font-size:.82rem;color:var(--muted)"><?= number_format($totalRows) ?> item(s)</span>
  </div>

  <div class="card">
    <div class="table-wrap">
      <table>
        <thead><tr><th>#</th><th>Code</th><th>Item Name</th><th>Category</th><th>Unit</th><th>Cost</th><th>Price</th><th>Stock</th><th>Min</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php if(empty($items)): ?>
        <tr><td colspan="11"><div class="empty-state"><i class="fas fa-box-open"></i><p>No items found.</p></div></td></tr>
        <?php else: foreach($items as $i=>$item):
          $stockStatus = $item['quantity'] == 0 ? 'out' : ($item['quantity'] <= $item['min_quantity'] ? 'low' : 'ok');
        ?>
        <tr>
          <td style="color:var(--muted);font-size:.8rem"><?= $offset+$i+1 ?></td>
          <td><span style="font-size:.78rem;color:var(--muted)"><?= $item['item_code'] ?></span></td>
          <td><strong><?= htmlspecialchars($item['name']) ?></strong></td>
          <td style="font-size:.82rem">
            <?= htmlspecialchars($item['cat_name']??'—') ?>
            <?php if($item['sub_name']): ?><br><small style="color:var(--muted)"><?= htmlspecialchars($item['sub_name']) ?></small><?php endif; ?>
          </td>
          <td style="font-size:.82rem"><?= $item['unit'] ?></td>
          <td style="font-size:.85rem"><?= formatMoney($item['cost_price']) ?></td>
          <td style="font-weight:600"><?= formatMoney($item['selling_price']) ?></td>
          <td>
            <?php if($stockStatus==='out'): ?>
              <span style="color:var(--danger);font-weight:700">0 <small style="font-size:.7rem"><?= $item['unit'] ?></small></span>
            <?php elseif($stockStatus==='low'): ?>
              <span style="color:var(--warning);font-weight:700"><?= $item['quantity'] ?> <small style="font-size:.7rem"><?= $item['unit'] ?></small> ⚠</span>
            <?php else: ?>
              <span style="color:var(--success);font-weight:600"><?= $item['quantity'] ?> <small style="font-size:.7rem"><?= $item['unit'] ?></small></span>
            <?php endif; ?>
          </td>
          <td style="font-size:.82rem;color:var(--muted)"><?= $item['min_quantity'] ?></td>
          <td>
            <?php if($stockStatus==='out'):    echo '<span class="badge badge-danger">Out of Stock</span>';
            elseif($stockStatus==='low'):      echo '<span class="badge badge-warning">Low Stock</span>';
            else:                              echo '<span class="badge badge-success">In Stock</span>';
            endif; ?>
          </td>
          <td>
            <div style="display:flex;gap:.4rem">
              <button class="btn btn-sm btn-outline btn-icon" onclick="editItem(<?= $item['id'] ?>)" title="Edit"><i class="fas fa-edit"></i></button>
              <button class="btn btn-sm btn-outline btn-icon" onclick="openStock(<?= $item['id'] ?>, '<?= htmlspecialchars($item['name'],ENT_QUOTES) ?>')" title="Adjust Stock"><i class="fas fa-boxes"></i></button>
              <button class="btn btn-sm btn-danger btn-icon" onclick="deleteItem(<?= $item['id'] ?>, '<?= htmlspecialchars($item['name'],ENT_QUOTES) ?>')" title="Delete"><i class="fas fa-trash"></i></button>
            </div>
          </td>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <?php if($totalPages>1): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:1rem 1.4rem;border-top:1px solid var(--border)">
      <span style="font-size:.82rem;color:var(--muted)">Page <?= $page ?> of <?= $totalPages ?></span>
      <div class="pagination">
        <?php for($p=1;$p<=$totalPages;$p++): ?>
        <a href="?filter=<?= $filter ?>&q=<?= urlencode($search) ?>&page=<?= $p ?>" class="page-btn <?= $p===$page?'active':'' ?>"><?= $p ?></a>
        <?php endfor; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</main>
</div>

<!-- Item Form Modal -->
<div class="modal-overlay" id="itemModal">
<div class="modal modal-lg">
  <div class="modal-header">
    <span class="modal-title" id="itemModalTitle">Add New Item</span>
    <button class="modal-close"><i class="fas fa-times"></i></button>
  </div>
  <div class="modal-body">
    <form id="itemForm">
      <input type="hidden" name="action" id="itemAction" value="create">
      <input type="hidden" name="id" id="itemId">
      <div class="form-group">
        <label>Item Name *</label>
        <input type="text" name="name" id="if_name" class="form-control" required placeholder="e.g. 2.5mm Copper Cable">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Category</label>
          <select name="category_id" id="if_cat" class="form-control" onchange="loadSubs()">
            <option value="">Select Category</option>
            <?php foreach($categories as $c): ?>
            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Sub-Category</label>
          <select name="sub_category_id" id="if_sub" class="form-control">
            <option value="">Select Sub-Category</option>
          </select>
        </div>
      </div>
      <div class="form-row-3">
        <div class="form-group">
          <label>Unit</label>
          <select name="unit" id="if_unit" class="form-control">
            <?php foreach(['piece','meter','kg','liter','box','roll','set','pair','bag'] as $u): ?>
            <option value="<?= $u ?>"><?= ucfirst($u) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Cost Price (FRW)</label>
          <input type="number" name="cost_price" id="if_cost" class="form-control" step="1" value="0">
        </div>
        <div class="form-group">
          <label>Selling Price (FRW)</label>
          <input type="number" name="selling_price" id="if_sell" class="form-control" step="1" value="0">
        </div>
      </div>
      <div class="form-row" id="stockRow">
        <div class="form-group">
          <label>Initial Quantity</label>
          <input type="number" name="quantity" id="if_qty" class="form-control" value="0" min="0">
        </div>
        <div class="form-group">
          <label>Min Quantity (Alert)</label>
          <input type="number" name="min_quantity" id="if_minqty" class="form-control" value="5" min="0">
        </div>
      </div>
      <div class="form-group">
        <label>Description</label>
        <textarea name="description" id="if_desc" class="form-control" rows="2" placeholder="Item description..."></textarea>
      </div>
    </form>
  </div>
  <div class="modal-footer">
    <button class="btn btn-outline" onclick="Modal.close('itemModal')">Cancel</button>
    <button class="btn btn-primary" onclick="submitItem()"><i class="fas fa-save"></i> Save Item</button>
  </div>
</div>
</div>

<!-- Stock Adjust Modal -->
<div class="modal-overlay" id="stockModal">
<div class="modal">
  <div class="modal-header">
    <span class="modal-title">Adjust Stock</span>
    <button class="modal-close"><i class="fas fa-times"></i></button>
  </div>
  <div class="modal-body">
    <form id="stockForm">
      <input type="hidden" name="action" value="adjust_stock">
      <input type="hidden" name="id" id="stockItemId">
      <div class="form-group">
        <label>Item</label>
        <input type="text" id="stockItemName" class="form-control" readonly style="opacity:.6">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Movement Type</label>
          <select name="type" class="form-control">
            <option value="in">Stock In (Add)</option>
            <option value="out">Stock Out (Remove)</option>
            <!-- Adjustment removed per request -->
          </select>
        </div>
        <div class="form-group">
          <label>Quantity</label>
          <input type="number" name="quantity" class="form-control" min="1" value="1" required>
        </div>
      </div>
      <div class="form-group">
        <label>Notes</label>
        <input type="text" name="notes" class="form-control" placeholder="Reason for adjustment">
      </div>
    </form>
  </div>
  <div class="modal-footer">
    <button class="btn btn-outline" onclick="Modal.close('stockModal')">Cancel</button>
    <button class="btn btn-success" onclick="submitStock()"><i class="fas fa-check"></i> Confirm</button>
  </div>
</div>
</div>

<script src="assets/js/main.js"></script>
<script>
function openCreate() {
  document.getElementById('itemModalTitle').textContent = 'Add New Item';
  document.getElementById('itemAction').value = 'create';
  document.getElementById('stockRow').style.display = '';
  document.getElementById('itemForm').reset();
  Modal.open('itemModal');
}
async function editItem(id) {
  const res = await Ajax.get(`inventory.php?action=get&id=${id}`);
  if (!res.success) return Notify.error('Error','Could not load item.');
  const it = res.item;
  document.getElementById('itemModalTitle').textContent = 'Edit Item';
  document.getElementById('itemAction').value   = 'update';
  document.getElementById('stockRow').style.display = 'none';
  document.getElementById('itemId').value       = it.id;
  document.getElementById('if_name').value      = it.name;
  document.getElementById('if_cat').value       = it.category_id ?? '';
  document.getElementById('if_unit').value      = it.unit;
  document.getElementById('if_cost').value      = it.cost_price;
  document.getElementById('if_sell').value      = it.selling_price;
  document.getElementById('if_minqty').value    = it.min_quantity;
  document.getElementById('if_desc').value      = it.description ?? '';
  await loadSubs(it.sub_category_id);
  Modal.open('itemModal');
}
async function loadSubs(selectedId = null) {
  const catId = document.getElementById('if_cat').value;
  if (!catId) return;
  const res = await Ajax.get(`inventory.php?action=subs&cat=${catId}`);
  const sel = document.getElementById('if_sub');
  sel.innerHTML = '<option value="">Select Sub-Category</option>';
  (res.subs || []).forEach(s => {
    const opt = document.createElement('option');
    opt.value = s.id; opt.textContent = s.name;
    if (selectedId && s.id == selectedId) opt.selected = true;
    sel.appendChild(opt);
  });
}
async function submitItem() {
  const fd = new FormData(document.getElementById('itemForm'));
  Notify.loading('Saving...');
  const res = await Ajax.post('inventory.php', fd, true);
  Notify.close();
  if (res.success) { Notify.success('Saved!', res.message); setTimeout(()=>location.reload(),1200); }
  else Notify.error('Error', res.message);
}
function openStock(id, name) {
  document.getElementById('stockItemId').value = id;
  document.getElementById('stockItemName').value = name;
  Modal.open('stockModal');
}
async function submitStock() {
  const fd = new FormData(document.getElementById('stockForm'));
  Notify.loading('Processing...');
  const res = await Ajax.post('inventory.php', fd, true);
  Notify.close();
  if (res.success) { Notify.success('Done!', res.message); setTimeout(()=>location.reload(),1200); }
  else Notify.error('Error', res.message);
}
async function deleteItem(id, name) {
  const ok = await Notify.confirmDelete(name);
  if (!ok) return;
  const fd = new FormData(); fd.append('action','delete'); fd.append('id',id);
  const res = await Ajax.post('inventory.php', fd, true);
  if (res.success) { Notify.success('Removed!'); setTimeout(()=>location.reload(),1200); }
  else Notify.error('Error', res.message);
}
</script>
</body>
</html>
