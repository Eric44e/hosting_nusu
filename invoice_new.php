<?php
require_once 'config.php';
requireLogin();

// Handle AJAX requests
$isAjax = isAjax() || isset($_POST['action']);
if ($isAjax) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_invoice') {
        $client_id = (int)$_POST['client_id'];
        $type = sanitize($_POST['type'] ?? 'invoice');
        $items = json_decode($_POST['items'] ?? '[]', true);
        $tax_percent = (float)($_POST['tax_percent'] ?? 0);
        $discount = (float)($_POST['discount'] ?? 0);
        $notes = sanitize($_POST['notes'] ?? '');
        $due_date = $_POST['due_date'] ?? null;
        
        if (!$client_id || empty($items)) {
            jsonResponse(false, 'Please select a client and add at least one item.');
        }
        
        // Calculate totals
        $subtotal = 0;
        foreach ($items as $item) {
            $qty = (int)$item['quantity'];
            $price = (float)$item['unit_price'];
            $subtotal += $qty * $price;
        }
        
        $tax_amount = $subtotal * ($tax_percent / 100);
        $total_amount = $subtotal + $tax_amount - $discount;
        
        // Generate invoice number
        $prefix = strtoupper(substr($type, 0, 3));
        $invoice_number = generateCode($prefix . '-', 'invoices', 'invoice_number', 5);
        
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("
                INSERT INTO invoices (invoice_number, client_id, subtotal, tax_percent, tax_amount, discount, total_amount, balance, type, status, due_date, notes, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'unpaid', ?, ?, ?)
            ");
            $stmt->execute([
                $invoice_number, $client_id, $subtotal, $tax_percent, $tax_amount, $discount, $total_amount, $total_amount, $type, $due_date, $notes, $_SESSION['staff_id']
            ]);
            
            $invoice_id = $pdo->lastInsertId();
            
            // Add invoice items if it's a quotation (for invoices, items come from tickets)
            if ($type === 'quotation') {
                foreach ($items as $item) {
                    $qty = (int)$item['quantity'];
                    $price = (float)$item['unit_price'];
                    $total = $qty * $price;
                    
                    $itemStmt = $pdo->prepare("
                        INSERT INTO ticket_items (ticket_id, item_name, quantity, unit_price, total_price)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    // For quotations, we use invoice_id as a reference (stored in ticket_id temporarily)
                    $itemStmt->execute([$invoice_id, $item['name'], $qty, $price, $total]);
                }
            }
            
            $pdo->commit();
            jsonResponse(true, ucfirst($type) . ' created successfully!', ['invoice_id' => $invoice_id, 'invoice_number' => $invoice_number]);
        } catch (Exception $e) {
            $pdo->rollBack();
            jsonResponse(false, 'Error creating ' . $type . ': ' . $e->getMessage());
        }
    }
    
    if ($action === 'get_clients') {
        $clients = $pdo->query("SELECT id, client_code, full_name, email, phone FROM clients WHERE status='active' ORDER BY full_name")->fetchAll();
        jsonResponse(true, 'OK', ['clients' => $clients]);
    }
    
    if ($action === 'get_items') { 
        $items = $pdo->query(" SELECT i.*, c.name as category_name, sc.name as sub_category_name
            FROM items i
            LEFT JOIN categories c ON c.id=i.category_id
            LEFT JOIN sub_categories sc ON sc.id=i.sub_category_id
            WHERE i.status='active' AND i.quantity > 0
            ORDER BY i.name
        ")->fetchAll();
        jsonResponse(true, 'OK', ['items' => $items]);
    }
    
    if ($action === 'get_tickets') {
        $client_id = (int)($_POST['client_id'] ?? 0);
        $sql = "
            SELECT t.id, t.ticket_number, t.title, t.total_amount, c.full_name client_name
            FROM tickets t
            JOIN clients c ON c.id=t.client_id
            WHERE t.status IN ('completed','closed') AND t.id NOT IN (SELECT COALESCE(ticket_id,0) FROM invoices WHERE ticket_id IS NOT NULL)
        ";
        if ($client_id > 0) {
            $sql .= " AND t.client_id = " . $client_id;
        }
        $sql .= " ORDER BY t.created_at DESC";
        $tickets = $pdo->query($sql)->fetchAll();
        jsonResponse(true, 'OK', ['tickets' => $tickets]);
    }
    
    if ($action === 'get_ticket_items') {
        $ticket_id = (int)$_POST['ticket_id'];
        $items = $pdo->prepare("
            SELECT ti.*, i.cost_price, i.category_id
            FROM ticket_items ti
            LEFT JOIN items i ON i.name=ti.item_name
            WHERE ti.ticket_id = ?
        ");
        $items->execute([$ticket_id]);
        jsonResponse(true, 'OK', ['items' => $items->fetchAll()]);
    }
    
    jsonResponse(false, 'Unknown action');
}

// Get data for page
$clients = $pdo->query("SELECT * FROM clients WHERE status='active' ORDER BY full_name")->fetchAll();
$items = $pdo->query("
  SELECT i.*, c.name as category_name, sc.name as sub_category_name
  FROM items i
  LEFT JOIN categories c ON c.id=i.category_id
  LEFT JOIN sub_categories sc ON sc.id=i.sub_category_id
  WHERE i.status='active'
  ORDER BY c.name, i.name
")->fetchAll();

// Get categories and sub-categories
$categories = $pdo->query("SELECT id, name FROM categories WHERE status='active' ORDER BY name")->fetchAll();
$subCategories = $pdo->query("SELECT id, category_id, name, profit_margin FROM sub_categories WHERE status='active' ORDER BY name")->fetchAll();

// Check if taxes table exists
$taxes = [];
try {
  $pdo->query("SELECT 1 FROM taxes LIMIT 1");
  $taxes = $pdo->query("SELECT * FROM taxes WHERE status='active' ORDER BY name")->fetchAll();
} catch (PDOException $e) {
  // Table does not exist, fallback to empty taxes
  $taxes = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Create Bill — NUSU Management System</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
.doc-type-tabs {
  display: flex; gap: 0.5rem; margin-bottom: 1.5rem;
  background: rgba(255,255,255,0.03);
  padding: 0.5rem; border-radius: 12px;
  width: fit-content;
}
.doc-type-tabs button {
  padding: 0.75rem 1.5rem;
  background: transparent;
  border: none;
  border-radius: 10px;
  color: var(--text-muted);
  font-family: inherit;
  font-size: 0.9rem; font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}
.doc-type-tabs button.active {
  background: var(--primary);
  color: #fff;
  box-shadow: 0 4px 15px rgba(37, 99, 235, 0.3);
}
.doc-type-tabs button:hover:not(.active) {
  background: rgba(255,255,255,0.05);
  color: var(--text);
}
.items-table-wrap {
  max-height: 300px;
  overflow-y: auto;
  border: 1px solid var(--border);
  border-radius: 12px;
  margin-bottom: 1rem;
}
.items-table {
  width: 100%;
  border-collapse: collapse;
}
.items-table th {
  position: sticky; top: 0;
  background: var(--card-light);
  padding: 0.75rem 1rem;
  text-align: left;
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.items-table td {
  padding: 0.75rem 1rem;
  border-bottom: 1px solid var(--border);
  font-size: 0.9rem;
}
.items-table tr:hover {
  background: rgba(255,255,255,0.02);
}
.item-search {
  position: relative;
  margin-bottom: 1rem;
}
.item-search input {
  width: 100%;
  padding: 0.75rem 1rem 0.75rem 2.5rem;
  background: rgba(255,255,255,0.03);
  border: 1px solid var(--border);
  border-radius: 10px;
  color: var(--text);
  font-family: inherit;
  font-size: 0.9rem;
}
.item-search i {
  position: absolute; left: 1rem; top: 50%;
  transform: translateY(-50%);
  color: var(--text-dim);
}
.item-row {
  display: grid;
  grid-template-columns: 2fr 1fr 1fr 1fr 40px;
  gap: 0.5rem;
  align-items: center;
  padding: 0.5rem;
  background: rgba(255,255,255,0.02);
  border-radius: 8px;
  margin-bottom: 0.5rem;
}
.item-row input, .item-row select {
  padding: 0.5rem 0.75rem;
  background: rgba(255,255,255,0.03);
  border: 1px solid var(--border);
  border-radius: 8px;
  color: var(--text);
  font-family: inherit;
  font-size: 0.85rem;
}
.item-row input:focus, .item-row select:focus {
  border-color: var(--primary);
  outline: none;
}
.item-row .remove-btn {
  background: none;
  border: none;
  color: var(--error);
  cursor: pointer;
  font-size: 1rem;
  padding: 0.5rem;
}
.summary-box {
  background: rgba(255,255,255,0.03);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 1.5rem;
}
.summary-row {
  display: flex;
  justify-content: space-between;
  padding: 0.5rem 0;
  font-size: 0.95rem;
}
.summary-row.total {
  border-top: 2px solid var(--border);
  margin-top: 0.5rem;
  padding-top: 1rem;
  font-size: 1.2rem;
  font-weight: 700;
}
.summary-row.total .val {
  color: var(--success);
}
</style>
</head>
<body>
<div class="app-wrapper">
<?php include 'includes/layout.php'; ?>

<main class="main-content fade-in">
  <div class="page-header">
    <div class="page-header-left">
      <h1>Create Document</h1>
      <p>Generate Bill or Quotation</p>
    </div>
    <div class="page-actions">
      <a href="invoices.php" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Back to Documents</a>
    </div>
  </div>

  <form id="docForm">
    <!-- Document Type -->
    <div class="doc-type-tabs">
      <button type="button" class="active" data-type="invoice" onclick="setDocType('invoice')">
        <i class="fas fa-file-invoice" style="margin-right:0.5rem"></i> Bill
      </button>
      <!-- <button type="button" data-type="bill" onclick="setDocType('bill')">
        <i class="fas fa-file-alt" style="margin-right:0.5rem"></i> Bill
      </button> -->
      <button type="button" data-type="quotation" onclick="setDocType('quotation')">
        <i class="fas fa-file-signature" style="margin-right:0.5rem"></i> Quotation
      </button>
    </div>
    <input type="hidden" name="type" id="docType" value="invoice">

    <div class="grid-2" style="gap:1.5rem;margin-bottom:1.5rem">
      <!-- Client Selection -->
      <div class="card">
        <div class="card-header">
          <div class="card-title">Client Information</div>
        </div>
        <div class="card-body">
          <div class="form-group">
            <label>Select Client *</label>
            <select name="client_id" id="clientSelect" class="form-control" required onchange="loadClientTickets()">
              <option value="">— Select Client —</option>
              <?php foreach($clients as $c): ?>
              <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['full_name']) ?> (<?= htmlspecialchars($c['client_code']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          
          <div class="form-group" id="ticketGroup" style="display:none">
            <label>Link to Completed Ticket (Optional)</label>
            <select id="ticketSelect" class="form-control" onchange="loadTicketItems()">
              <option value="">— Select Ticket —</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Document Details -->
      <div class="card">
        <div class="card-header">
          <div class="card-title">Document Details</div>
        </div>
        <div class="card-body">
          <div class="grid-2" style="gap:1rem">
            <div class="form-group">
              <label>Due Date</label>
              <input type="date" name="due_date" id="dueDate" class="form-control">
            </div>
          <div class="form-group">
            <label>Tax Type</label>
            <select name="tax_id" id="taxSelect" class="form-control" onchange="setTaxRate()">
              <option value="">— No Tax —</option>
              <?php foreach($taxes as $t): ?>
              <option value="<?= $t['id'] ?>" data-rate="<?= $t['rate'] ?>"><?= htmlspecialchars($t['name']) ?> (<?= $t['rate'] ?>%)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Tax Rate (%)</label>
            <input type="number" name="tax_percent" id="taxPercent" class="form-control" value="0" min="0" max="100" step="0.1" onchange="calculateTotals()">
          </div>
          </div>
          <div class="form-group">
            <label>Discount (FRW)</label>
            <input type="number" name="discount" id="discount" class="form-control" value="0" min="0" step="100" onchange="calculateTotals()">
          </div>
          <div class="form-group">
            <label>Notes</label>
            <textarea name="notes" id="notes" class="form-control" rows="2" placeholder="Additional notes..."></textarea>
          </div>
        </div>
      </div>
    </div>

    <!-- Items Section -->
    <div class="card" style="margin-bottom:1.5rem">
      <div class="card-header">
        <div class="card-title">Items / Services</div>
        <button type="button" class="btn btn-sm btn-outline" onclick="addItem()">
          <i class="fas fa-plus"></i> Add Item
        </button>
      </div>
      <div class="card-body">
        <div class="grid-3" style="gap:1rem;margin-bottom:1rem">
          <div class="form-group" style="margin-bottom:0">
            <label>Category</label>
            <select id="filterCategory" class="form-control" onchange="filterItemsByCategory()">
              <option value="">— All Categories —</option>
              <?php foreach($categories as $cat): ?>
              <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="margin-bottom:0">
            <label>Sub-Category</label>
            <select id="filterSubCategory" class="form-control" onchange="filterItemsByCategory()">
              <option value="">— All Sub-Categories —</option>
              <?php foreach($subCategories as $sc): ?>
              <option value="<?= $sc['id'] ?>" data-cat="<?= $sc['category_id'] ?>"><?= htmlspecialchars($sc['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="margin-bottom:0">
            <label>Search Item</label>
            <input type="text" id="itemSearch" class="form-control" placeholder="Search items..." onkeyup="filterItemsByCategory()">
          </div>
        </div>
        
        <div id="itemsContainer">
          <!-- Items will be added here -->
        </div>
        
        <div style="text-align:center;padding:2rem;color:var(--text-dim)" id="noItemsMsg">
          <i class="fas fa-box-open" style="font-size:2rem;margin-bottom:0.5rem;display:block"></i>
          No items added yet. Click "Add Item" to start.
        </div>
      </div>
    </div>

    <!-- Summary -->
    <div class="grid-2" style="gap:1.5rem">
      <div></div>
      <div class="summary-box">
        <div class="summary-row">
          <span>Subtotal</span>
          <span class="val" id="summarySubtotal">FRW 0</span>
        </div>
        <div class="summary-row">
          <span>Tax (<span id="taxPercentDisplay">0</span>%)</span>
          <span class="val" id="summaryTax">FRW 0</span>
        </div>
        <div class="summary-row">
          <span>Discount</span>
          <span class="val" id="summaryDiscount" style="color:var(--error)">- FRW 0</span>
        </div>
        <div class="summary-row total">
          <span>Total Amount</span>
          <span class="val" id="summaryTotal">FRW 0</span>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;margin-top:1rem;padding:1rem">
          <i class="fas fa-save" style="margin-right:0.5rem"></i> Create <span id="docTypeLabel">Bill</span>
        </button>
      </div>
    </div>
  </form>
</main>
</div>

<script>
let docType = 'invoice';
let items = [];
let itemOptions = <?= json_encode($items) ?>;
let categoriesData = <?= json_encode($categories) ?>;
let subCategoriesData = <?= json_encode($subCategories) ?>;
let taxesData = <?= json_encode($taxes) ?>;

function setDocType(type) {
  docType = type;
  document.getElementById('docType').value = type;
  document.getElementById('docTypeLabel').textContent = type.charAt(0).toUpperCase() + type.slice(1);
  document.querySelectorAll('.doc-type-tabs button').forEach(b => b.classList.remove('active'));
  document.querySelector(`.doc-type-tabs button[data-type="${type}"]`).classList.add('active');
}

function setTaxRate() {
  const taxSelect = document.getElementById('taxSelect');
  const taxPercent = document.getElementById('taxPercent');
  const selectedOption = taxSelect.options[taxSelect.selectedIndex];
  const rate = selectedOption.getAttribute('data-rate');
  if (rate) {
    taxPercent.value = rate;
    calculateTotals();
  }
}

function filterItemsByCategory() {
  const categoryId = document.getElementById('filterCategory').value;
  const subCategoryId = document.getElementById('filterSubCategory').value;
  const search = document.getElementById('itemSearch').value.toLowerCase();
  
  // Filter sub-categories when category changes
  if (categoryId) {
    const subCatSelect = document.getElementById('filterSubCategory');
    Array.from(subCatSelect.options).forEach(opt => {
      if (opt.value === '') opt.style.display = 'block';
      else opt.style.display = (opt.getAttribute('data-cat') === categoryId) ? 'block' : 'none';
    });
  }
}

function loadClientTickets() {
  const clientId = document.getElementById('clientSelect').value;
  const ticketGroup = document.getElementById('ticketGroup');
  const ticketSelect = document.getElementById('ticketSelect');
  
  if (!clientId) {
    ticketGroup.style.display = 'none';
    return;
  }
  
  fetch('invoice_new.php', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
    body: 'action=get_tickets&client_id=' + clientId
  })
  .then(r => r.json())
  .then(data => {
    const tickets = data.tickets || [];
    
    ticketSelect.innerHTML = '<option value="">— Select Ticket —</option>';
    tickets.forEach(t => {
      ticketSelect.innerHTML += `<option value="${t.id}">${t.ticket_number} - ${t.title} (FRW ${parseInt(t.total_amount).toLocaleString()})</option>`;
    });
    ticketGroup.style.display = 'block';
  })
  .catch(err => {
    console.error('Error loading tickets:', err);
    ticketGroup.style.display = 'none';
  });
}

function loadTicketItems() {
  const ticketId = document.getElementById('ticketSelect').value;
  if (!ticketId) return;
  
  fetch('invoice_new.php', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
    body: 'action=get_ticket_items&ticket_id=' + ticketId
  })
  .then(r => r.json())
  .then(data => {
    items = data.items.map(i => ({
      name: i.item_name,
      quantity: i.quantity,
      unit_price: i.unit_price || 0,
      total_price: i.total_price || (i.quantity * i.unit_price)
    }));
    renderItems();
    calculateTotals();
  });
}

function addItem() {
  const categoryId = document.getElementById('filterCategory').value;
  const subCategoryId = document.getElementById('filterSubCategory').value;
  const search = document.getElementById('itemSearch').value.toLowerCase();
  
  items.push({
    name: '',
    quantity: 1,
    unit_price: 0,
    total_price: 0,
    category_id: categoryId || null,
    sub_category_id: subCategoryId || null
  });
  renderItems();
  calculateTotals();
}

function removeItem(index) {
  items.splice(index, 1);
  renderItems();
  calculateTotals();
}

function updateItem(index, field, value) {
  items[index][field] = value;
  if (field === 'quantity' || field === 'unit_price') {
    items[index].total_price = items[index].quantity * items[index].unit_price;
  }
  calculateTotals();
  renderItems();
}

function renderItems() {
  const container = document.getElementById('itemsContainer');
  const noItemsMsg = document.getElementById('noItemsMsg');
  
  if (items.length === 0) {
    container.innerHTML = '';
    noItemsMsg.style.display = 'block';
    return;
  }
  
  noItemsMsg.style.display = 'none';
  
  let html = '<table class="items-table"><thead><tr><th>Item/Service</th><th>Qty</th><th>Unit Price (FRW)</th><th>Total (FRW)</th><th></th></tr></thead><tbody>';
  
  items.forEach((item, index) => {
    // Build item options HTML
    let itemOptions = '<datalist id="items-list-' + index + '">';
    itemOptions.filter(i => {
      const categoryId = document.getElementById('filterCategory').value;
      const subCategoryId = document.getElementById('filterSubCategory').value;
      if (categoryId && i.category_id != categoryId) return false;
      if (subCategoryId && i.sub_category_id != subCategoryId) return false;
      return true;
    }).forEach(i => {
      itemOptions += `<option value="${i.name}" data-price="${i.selling_price}" label="${i.category_name}${i.sub_category_name ? ' - ' + i.sub_category_name : ''}">`;
    });
    itemOptions += '</datalist>';
    
    html += `<tr>
      <td>
        <input type="text" value="${item.name}" placeholder="Item name or select from list" 
          list="items-list-${index}"
          onchange="updateItem(${index}, 'name', this.value); updateItemPrice(${index})" 
          style="width:100%;padding:0.5rem;background:rgba(255,255,255,0.03);border:1px solid var(--border);border-radius:8px;color:var(--text)">
        ${itemOptions}
      </td>
      <td>
        <input type="number" value="${item.quantity}" min="1" 
          onchange="updateItem(${index}, 'quantity', parseInt(this.value))" 
          style="width:60px;padding:0.5rem;background:rgba(255,255,255,0.03);border:1px solid var(--border);border-radius:8px;color:var(--text)">
      </td>
      <td>
        <input type="number" value="${item.unit_price}" min="0" step="100" 
          onchange="updateItem(${index}, 'unit_price', parseFloat(this.value))" 
          style="width:120px;padding:0.5rem;background:rgba(255,255,255,0.03);border:1px solid var(--border);border-radius:8px;color:var(--text)">
      </td>
      <td style="font-weight:600">FRW ${parseInt(item.total_price).toLocaleString()}</td>
      <td>
        <button type="button" class="remove-btn" onclick="removeItem(${index})"><i class="fas fa-trash"></i></button>
      </td>
    </tr>`;
  });
  
  html += '</tbody></table>';
  container.innerHTML = html;
}

function updateItemPrice(index) {
  const itemName = items[index].name;
  const matchedItem = itemOptions.find(i => i.name === itemName);
  if (matchedItem && !items[index].unit_price) {
    items[index].unit_price = matchedItem.selling_price || 0;
    items[index].total_price = items[index].quantity * items[index].unit_price;
    calculateTotals();
    renderItems();
  }
}

function calculateTotals() {
  const subtotal = items.reduce((sum, item) => sum + (parseInt(item.quantity) * parseFloat(item.unit_price)), 0);
  const taxPercent = parseFloat(document.getElementById('taxPercent').value) || 0;
  const discount = parseFloat(document.getElementById('discount').value) || 0;
  
  const taxAmount = subtotal * (taxPercent / 100);
  const total = subtotal + taxAmount - discount;
  
  document.getElementById('summarySubtotal').textContent = 'FRW ' + Math.round(subtotal).toLocaleString();
  document.getElementById('taxPercentDisplay').textContent = taxPercent;
  document.getElementById('summaryTax').textContent = 'FRW ' + Math.round(taxAmount).toLocaleString();
  document.getElementById('summaryDiscount').textContent = '- FRW ' + Math.round(discount).toLocaleString();
  document.getElementById('summaryTotal').textContent = 'FRW ' + Math.round(total).toLocaleString();
}

// Form submission
document.getElementById('docForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  
  const clientId = document.getElementById('clientSelect').value;
  if (!clientId) {
    Swal.fire({icon:'error',title:'Error',text:'Please select a client'});
    return;
  }
  
  if (items.length === 0 || !items[0].name) {
    Swal.fire({icon:'error',title:'Error',text:'Please add at least one item'});
    return;
  }
  
  const btn = this.querySelector('button[type="submit"]');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';
  
  const formData = new FormData();
  formData.append('action', 'create_invoice');
  formData.append('client_id', clientId);
  formData.append('type', docType);
  formData.append('items', JSON.stringify(items));
  formData.append('tax_percent', document.getElementById('taxPercent').value);
  formData.append('discount', document.getElementById('discount').value);
  formData.append('due_date', document.getElementById('dueDate').value);
  formData.append('notes', document.getElementById('notes').value);
  
  try {
    const res = await fetch('invoice_new.php', {
      method: 'POST',
      body: formData,
      headers: {'X-Requested-With':'XMLHttpRequest'}
    });
    const data = await res.json();
    
    if (data.success) {
      await Swal.fire({
        icon:'success',
        title:'Success!',
        text:data.message,
        background:'#1e293b',
        color:'#f8fafc'
      });
      window.location.href = 'invoices.php';
    } else {
      Swal.fire({icon:'error',title:'Error',text:data.message,background:'#1e293b',color:'#f8fafc'});
    }
  } catch (err) {
    Swal.fire({icon:'error',title:'Error',text:'Connection error',background:'#1e293b',color:'#f8fafc'});
  }
  
  btn.disabled = false;
  btn.innerHTML = '<i class="fas fa-save" style="margin-right:0.5rem"></i> Create ' + docType.charAt(0).toUpperCase() + docType.slice(1);
});

// Initialize
calculateTotals();
</script>
</body>
</html>