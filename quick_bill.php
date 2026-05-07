<?php
require_once 'config.php';
require_once 'includes/qr_helper.php';
requireLogin();

// AJAX handling for quick bill creation
if (isAjax()) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_quick_bill') {
        $client_name = sanitize($_POST['client_name'] ?? '');
        $client_phone = sanitize($_POST['client_phone'] ?? '');
        $client_email = sanitize($_POST['client_email'] ?? '');
        $items = json_decode($_POST['items'] ?? '[]', true);
        $tax_percent = (float)($_POST['tax_percent'] ?? 0);
        $discount = (float)($_POST['discount'] ?? 0);
        $notes = sanitize($_POST['notes'] ?? '');
        
        if (!$client_name || empty($items)) {
            jsonResponse(false, 'Client name and items are required.');
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
        
        // Generate bill number
        $bill_number = generateCode('QB-', 'invoices', 'invoice_number', 6);
        
        try {
            $pdo->beginTransaction();
            
            // Insert as invoice with type 'quick_bill'
            $stmt = $pdo->prepare("
                INSERT INTO invoices (invoice_number, client_id, subtotal, tax_percent, tax_amount, discount, total_amount, balance, type, status, notes, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'unpaid', ?, ?)
            ");
            
            // Use 0 or find matching client, or create temp record
            $stmt->execute([
                $bill_number, 0, $subtotal, $tax_percent, $tax_amount, $discount, $total_amount, $total_amount, 'quick_bill', $notes, $_SESSION['staff_id']
            ]);
            
            $bill_id = $pdo->lastInsertId();
            
            // Store client info in notes if no client_id
            $stmt = $pdo->prepare("UPDATE invoices SET notes = ? WHERE id = ?");
            $stmt->execute(["Client: {$client_name}\nPhone: {$client_phone}\nEmail: {$client_email}\n\n{$notes}", $bill_id]);
            
            // Add items
            foreach ($items as $item) {
                $qty = (int)$item['quantity'];
                $price = (float)$item['unit_price'];
                $total = $qty * $price;
                
                $itemStmt = $pdo->prepare("
                    INSERT INTO ticket_items (ticket_id, item_name, quantity, unit_price, total_price)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $itemStmt->execute([$bill_id, $item['name'], $qty, $price, $total]);
            }
            
            $pdo->commit();
            jsonResponse(true, 'Quick bill created successfully!', ['bill_id' => $bill_id, 'bill_number' => $bill_number]);
        } catch (Exception $e) {
            $pdo->rollBack();
            jsonResponse(false, 'Error creating quick bill: ' . $e->getMessage());
        }
    }
    
    jsonResponse(false, 'Unknown action');
}

// Get taxes
$taxes = [];
try {
    $pdo->query("SELECT 1 FROM taxes LIMIT 1");
    $taxes = $pdo->query("SELECT * FROM taxes WHERE status='active' ORDER BY name")->fetchAll();
} catch (PDOException $e) {
    $taxes = [];
}

// Get items for selection
$inventoryItems = $pdo->query("SELECT id, name, selling_price, quantity FROM items WHERE status='active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Quick Bill — NUSU Management System</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
.quick-bill-card {
  background: rgba(255,255,255,0.03);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 1.5rem;
  margin-bottom: 1.5rem;
}
.item-row {
  display: grid;
  grid-template-columns: 2fr 1fr 1fr 50px;
  gap: 0.75rem;
  margin-bottom: 0.75rem;
  align-items: center;
}
.item-row input {
  padding: 0.75rem;
  background: rgba(255,255,255,0.03);
  border: 1px solid var(--border);
  border-radius: 8px;
  color: var(--text);
  font-family: inherit;
  font-size: 0.9rem;
}
.item-row button {
  background: none;
  border: none;
  color: var(--error);
  cursor: pointer;
  font-size: 1rem;
  padding: 0.5rem;
}
.summary-box {
  background: rgba(255,255,255,0.02);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 1.5rem;
  width: 100%;
  max-width: 300px;
  margin-left: auto;
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
      <h1>Quick Bill</h1>
      <p>Create a fast bill for individual services or products</p>
    </div>
    <div class="page-actions">
      <a href="invoices.php" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
    </div>
  </div>

  <form id="quickBillForm">
    <div class="grid-2" style="gap:1.5rem;margin-bottom:1.5rem">
      <!-- Client Information -->
      <div class="quick-bill-card">
        <div style="font-size:1rem;font-weight:600;margin-bottom:1rem;color:var(--text)">Client Information</div>
        
        <div class="form-group">
          <label>Client Name *</label>
          <input type="text" name="client_name" id="clientName" class="form-control" required placeholder="Enter client name">
        </div>
        
        <div class="form-group">
          <label>Phone (Optional)</label>
          <input type="tel" name="client_phone" id="clientPhone" class="form-control" placeholder="Enter phone number">
        </div>
        
        <div class="form-group">
          <label>Email (Optional)</label>
          <input type="email" name="client_email" id="clientEmail" class="form-control" placeholder="Enter email address">
        </div>
      </div>

      <!-- Bill Details -->
      <div class="quick-bill-card">
        <div style="font-size:1rem;font-weight:600;margin-bottom:1rem;color:var(--text)">Bill Details</div>
        
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

    <!-- Items Section -->
    <div class="quick-bill-card" style="margin-bottom:1.5rem">
      <div style="font-size:1rem;font-weight:600;margin-bottom:1rem;color:var(--text);display:flex;justify-content:space-between;align-items:center">
        <span>Items / Services</span>
        <button type="button" class="btn btn-sm btn-outline" onclick="addItem()">
          <i class="fas fa-plus"></i> Add Item
        </button>
      </div>
      
      <div id="itemsContainer">
        <!-- Items will be added here -->
      </div>
      
      <div style="text-align:center;padding:1.5rem;color:var(--text-dim)" id="noItemsMsg">
        <i class="fas fa-package" style="font-size:1.5rem;margin-bottom:0.5rem;display:block"></i>
        No items added yet. Click "Add Item" to start.
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
          <i class="fas fa-file-alt" style="margin-right:0.5rem"></i> Create Quick Bill
        </button>
      </div>
    </div>
  </form>
</main>
</div>

<script>
const inventoryItems = <?= json_encode($inventoryItems) ?>;
let items = [];

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

function addItem() {
  items.push({
    name: '',
    quantity: 1,
    unit_price: 0,
    total_price: 0
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
  
  // If name changed, check if it matches an inventory item
  if (field === 'name') {
    const matched = inventoryItems.find(i => i.name === value);
    if (matched) {
      items[index].unit_price = matched.selling_price;
      items[index].total_price = items[index].quantity * items[index].unit_price;
    }
  }
  
  if (field === 'quantity' || field === 'unit_price') {
    items[index].total_price = items[index].quantity * items[index].unit_price;
  }
  renderItems();
  calculateTotals();
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
  
  let html = '<div style="overflow-x:auto">';
  html += '<datalist id="inventoryList">';
  inventoryItems.forEach(i => {
    html += `<option value="${i.name}">`;
  });
  html += '</datalist>';
  
  html += '<table class="items-table" style="width:100%;border-collapse:collapse">';
  html += '<thead><tr><th style="padding:0.75rem;text-align:left;border-bottom:2px solid var(--border);font-weight:600">Item/Service</th><th style="padding:0.75rem;text-align:center;border-bottom:2px solid var(--border);font-weight:600">Qty</th><th style="padding:0.75rem;text-align:right;border-bottom:2px solid var(--border);font-weight:600">Unit Price (FRW)</th><th style="padding:0.75rem;text-align:right;border-bottom:2px solid var(--border);font-weight:600">Total (FRW)</th><th></th></tr></thead><tbody>';
  
  items.forEach((item, index) => {
    html += `<tr>
      <td style="padding:0.75rem;border-bottom:1px solid var(--border)">
        <input type="text" value="${item.name}" placeholder="Search or type item..." list="inventoryList"
          onchange="updateItem(${index}, 'name', this.value)" 
          style="width:100%;padding:0.5rem;background:rgba(255,255,255,0.03);border:1px solid var(--border);border-radius:6px;color:var(--text)">
      </td>
      <td style="padding:0.75rem;border-bottom:1px solid var(--border);text-align:center">
        <input type="number" value="${item.quantity}" min="1" 
          onchange="updateItem(${index}, 'quantity', parseInt(this.value))" 
          style="width:60px;padding:0.5rem;background:rgba(255,255,255,0.03);border:1px solid var(--border);border-radius:6px;color:var(--text);text-align:center">
      </td>
      <td style="padding:0.75rem;border-bottom:1px solid var(--border);text-align:right">
        <input type="number" value="${item.unit_price}" min="0" step="100" 
          onchange="updateItem(${index}, 'unit_price', parseFloat(this.value))" 
          style="width:120px;padding:0.5rem;background:rgba(255,255,255,0.03);border:1px solid var(--border);border-radius:6px;color:var(--text);text-align:right">
      </td>
      <td style="padding:0.75rem;border-bottom:1px solid var(--border);text-align:right;font-weight:600">FRW ${parseInt(item.total_price).toLocaleString()}</td>
      <td style="padding:0.75rem;border-bottom:1px solid var(--border);text-align:center">
        <button type="button" onclick="removeItem(${index})" style="background:none;border:none;color:var(--error);cursor:pointer;font-size:1rem"><i class="fas fa-trash"></i></button>
      </td>
    </tr>`;
  });
  
  html += '</tbody></table></div>';
  container.innerHTML = html;
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
document.getElementById('quickBillForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  
  const clientName = document.getElementById('clientName').value;
  if (!clientName) {
    Swal.fire({icon:'error',title:'Error',text:'Please enter client name'});
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
  formData.append('action', 'create_quick_bill');
  formData.append('client_name', clientName);
  formData.append('client_phone', document.getElementById('clientPhone').value);
  formData.append('client_email', document.getElementById('clientEmail').value);
  formData.append('items', JSON.stringify(items));
  formData.append('tax_percent', document.getElementById('taxPercent').value);
  formData.append('discount', document.getElementById('discount').value);
  formData.append('notes', document.getElementById('notes').value);
  
  try {
    const res = await fetch('quick_bill.php', {
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
      window.location.href = 'invoice_print.php?id=' + data.bill_id;
    } else {
      Swal.fire({icon:'error',title:'Error',text:data.message,background:'#1e293b',color:'#f8fafc'});
    }
  } catch (err) {
    Swal.fire({icon:'error',title:'Error',text:'Connection error',background:'#1e293b',color:'#f8fafc'});
  }
  
  btn.disabled = false;
  btn.innerHTML = '<i class="fas fa-file-alt" style="margin-right:0.5rem"></i> Create Quick Bill';
});

calculateTotals();
</script>
</body>
</html>
