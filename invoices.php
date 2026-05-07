<?php
require_once 'config.php';
requireLogin();

if (isAjax()) {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    if ($action === 'update_status') {
        $id = (int)$_POST['id'];
        $status = sanitize($_POST['status']);
        $pdo->prepare("UPDATE invoices SET status=? WHERE id=?")->execute([$status, $id]);
        jsonResponse(true, 'Bill status updated!');
    }
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM invoices WHERE id=?")->execute([$id]);
        jsonResponse(true, 'Bill deleted!');
    }
    if ($action === 'get_full' && isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $invStmt = $pdo->prepare("SELECT i.*, c.full_name, c.email, c.phone, c.address FROM invoices i JOIN clients c ON c.id=i.client_id WHERE i.id=?");
        $invStmt->execute([$id]);
        $inv = $invStmt->fetch();
        if(!$inv) jsonResponse(false, 'Bill not found');
        
        $items = [];
        if($inv['ticket_id']) {
            $itmStmt = $pdo->prepare("SELECT item_name, quantity, unit_price, total_price FROM ticket_items WHERE ticket_id=?");
            $itmStmt->execute([$inv['ticket_id']]);
            $items = $itmStmt->fetchAll();
        } else {
            // For quotations, items are stored with invoice_id as ticket_id
            $itmStmt = $pdo->prepare("SELECT item_name, quantity, unit_price, total_price FROM ticket_items WHERE ticket_id=?");
            $itmStmt->execute([$id]);
            $items = $itmStmt->fetchAll();
        }
        jsonResponse(true, 'OK', ['invoice' => $inv, 'items' => $items]);
    }
    if ($action === 'get' && isset($_GET['id'])) {
        $inv = $pdo->prepare("SELECT i.*, c.full_name as client_name FROM invoices i JOIN clients c ON c.id=i.client_id WHERE i.id=?");
        $inv->execute([(int)$_GET['id']]);
        jsonResponse(true, 'OK', ['invoice' => $inv->fetch()]);
    }
    
    if ($action === 'record_payment') {
        $invoice_id = (int)$_POST['invoice_id'];
        $amount = (float)$_POST['amount'];
        $payment_method = sanitize($_POST['payment_method'] ?? 'cash');
        $notes = sanitize($_POST['notes'] ?? '');
        
        if (!$invoice_id || $amount <= 0) {
            jsonResponse(false, 'Invalid payment amount.');
        }
        
        try {
            $pdo->beginTransaction();
            
            // Get invoice details
            $invStmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
            $invStmt->execute([$invoice_id]);
            $invoice = $invStmt->fetch();
            
            if (!$invoice) {
                jsonResponse(false, 'Bill not found.');
            }
            
            // Calculate new balance
            $new_balance = max(0, $invoice['balance'] - $amount);
            $new_paid = $invoice['paid_amount'] + $amount;
            $new_status = $new_balance <= 0 ? 'paid' : ($new_paid > 0 ? 'partial' : 'unpaid');
            
            // Update invoice
            $updateStmt = $pdo->prepare("UPDATE invoices SET balance = ?, paid_amount = ?, status = ? WHERE id = ?");
            $updateStmt->execute([$new_balance, $new_paid, $new_status, $invoice_id]);
            
            // Record transaction - with retry on duplicate
            $ref = null;
            $maxRetries = 5;
            for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
                try {
                    $ref = generateCode('TXN-', 'transactions', 'reference', 5);
                    $transStmt = $pdo->prepare("
                        INSERT INTO transactions (reference, type, category, amount, description, payment_method, staff_id, created_at)
                        VALUES (?, 'payment', 'invoice_payment', ?, ?, ?, ?, NOW())
                    ");
                    $transStmt->execute([$ref, $amount, "Payment for bill {$invoice['invoice_number']}" . ($notes ? " - $notes" : ''), $payment_method, $_SESSION['staff_id']]);
                    break; // Success
                } catch (PDOException $te) {
                    if (strpos($te->getMessage(), 'Duplicate entry') !== false && $attempt < $maxRetries - 1) {
                        continue; // Retry
                    } else {
                        throw $te;
                    }
                }
            }
            
            $pdo->commit();
            jsonResponse(true, 'Payment recorded successfully!', ['new_balance' => $new_balance, 'status' => $new_status]);
        } catch (Exception $e) {
            $pdo->rollBack();
            jsonResponse(false, 'Error recording payment: ' . $e->getMessage());
        }
    }
    
    jsonResponse(false, 'Unknown action');
}

$invoices = $pdo->query("
  SELECT i.*, c.full_name as client_name 
  FROM invoices i 
  LEFT JOIN clients c ON c.id=i.client_id 
  ORDER BY i.created_at DESC
")->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Bills — NUSU LTD</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<style>
    .card { background: #000 !important; color: #fff !important; border: 1px solid rgba(255,255,255,0.1) !important; }
    .table-wrap { border: none !important; }
    table th { color: #FF7A00 !important; border-bottom: 1px solid rgba(255,255,255,0.1) !important; }
    table td { border-bottom: 1px solid rgba(255,255,255,0.05) !important; color: #fff !important; }
</style>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<div class="app-wrapper">
<?php include 'includes/layout.php'; ?>
<main class="main-content fade-in">
  <div class="page-header">
    <div class="page-header-left"><h1>Bills</h1><p>Manage client billing and tracking</p></div>
    <div class="page-actions">
      <a href="invoice_new.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Create Document</a>
      <a href="dashboard.php" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Dashboard</a>
    </div>
  </div>
  
  <div class="card">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Bill #</th>
            <th>Client</th>
            <th>Due Date</th>
            <th>Total Amount</th>
            <th>Balance</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($invoices as $inv): ?>
          <tr>
            <td style="font-weight:600;color:var(--primary)"><?= htmlspecialchars($inv['invoice_number']) ?></td>
            <td><?= htmlspecialchars($inv['client_name']) ?></td>
            <td><?= $inv['due_date'] ? date('M j, Y', strtotime($inv['due_date'])) : '—' ?></td>
            <td style="font-weight:600"><?= formatMoney($inv['total_amount']) ?></td>
            <td style="color:var(--danger)"><?= formatMoney($inv['balance']) ?></td>
            <td><?= statusBadge($inv['status']) ?></td>
            <td>
              <button class="btn btn-sm btn-outline btn-icon" title="View" onclick="viewInvoice(<?= $inv['id'] ?>)"><i class="fas fa-eye"></i></button>
              <a href="invoice_print.php?id=<?= $inv['id'] ?>" target="_blank" class="btn btn-sm btn-outline btn-icon" title="Download/Print"><i class="fas fa-download"></i></a>
              <?php if($inv['balance'] > 0): ?>
              <button class="btn btn-sm btn-success btn-icon" title="Record Payment" onclick="recordPayment(<?= $inv['id'] ?>, '<?= htmlspecialchars($inv['invoice_number'],ENT_QUOTES) ?>', <?= $inv['balance'] ?>)"><i class="fas fa-money-bill-wave"></i></button>
              <?php endif; ?>
              <button class="btn btn-sm btn-outline btn-icon" title="Edit Status" onclick="editInvoice(<?= $inv['id'] ?>)"><i class="fas fa-edit"></i></button>
              <button class="btn btn-sm btn-danger btn-icon" title="Delete" onclick="delInvoice(<?= $inv['id'] ?>, '<?= htmlspecialchars($inv['invoice_number'],ENT_QUOTES) ?>')"><i class="fas fa-trash"></i></button>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if(!$invoices): ?>
          <tr><td colspan="7" class="empty-state">No bills found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>
</div>

<div class="modal-overlay" id="invModal">
<div class="modal">
  <div class="modal-header"><span class="modal-title" id="invModalTitle">Edit Bill Status</span><button class="modal-close" onclick="Modal.close('invModal')"><i class="fas fa-times"></i></button></div>
  <div class="modal-body">
    <form id="invForm">
      <input type="hidden" name="action" value="update_status">
      <input type="hidden" name="id" id="invId">
      
      <div class="form-group"><label>Bill Number</label><input type="text" id="i_num" class="form-control" disabled></div>
      <div class="form-group"><label>Client</label><input type="text" id="i_client" class="form-control" disabled></div>
      
      <div class="form-group"><label>Status *</label>
        <select name="status" id="i_status" class="form-control" required>
          <option value="unpaid">Unpaid</option>
          <option value="partial">Partial</option>
          <option value="paid">Paid</option>
          <option value="cancelled">Cancelled</option>
        </select>
      </div>
      
    </form>
  </div>
  <div class="modal-footer">
    <button class="btn btn-outline" onclick="Modal.close('invModal')">Cancel</button>
    <button class="btn btn-primary" onclick="submitInv()"><i class="fas fa-save"></i> Save</button>
  </div>
</div>
</div>

<div class="modal-overlay" id="viewInvModal">
<div class="modal" style="width:700px">
  <div class="modal-header"><span class="modal-title">Bill Details</span><button class="modal-close" onclick="Modal.close('viewInvModal')"><i class="fas fa-times"></i></button></div>
  <div class="modal-body" id="viewInvContent" style="padding:2rem">
    <!-- Content injected via JS -->
  </div>
</div>
</div>

<!-- Payment Modal -->
<div class="modal-overlay" id="payModal">
<div class="modal">
  <div class="modal-header"><span class="modal-title">Record Payment</span><button class="modal-close" onclick="Modal.close('payModal')"><i class="fas fa-times"></i></button></div>
  <div class="modal-body">
    <form id="payForm">
      <input type="hidden" name="action" value="record_payment">
      <input type="hidden" name="invoice_id" id="payInvoiceId">
      
      <div class="form-group"><label>Bill Number</label><input type="text" id="payInvNum" class="form-control" disabled></div>
      <div class="form-group"><label>Balance Due</label><input type="text" id="payBalance" class="form-control" disabled></div>
      
      <div class="form-group"><label>Payment Amount (FRW) *</label>
        <input type="number" name="amount" id="payAmount" class="form-control" min="1" step="100" required>
      </div>
      
      <div class="form-group"><label>Payment Method *</label>
        <select name="payment_method" id="payMethod" class="form-control" required>
          <option value="cash">Cash</option>
          <option value="card">Card</option>
          <option value="transfer">Bank Transfer</option>
          <option value="mobile">Mobile Money</option>
          <option value="cheque">Cheque</option>
        </select>
      </div>
      
      <div class="form-group"><label>Notes</label>
        <textarea name="notes" id="payNotes" class="form-control" rows="2" placeholder="Optional payment notes"></textarea>
      </div>
    </form>
  </div>
  <div class="modal-footer">
    <button class="btn btn-outline" onclick="Modal.close('payModal')">Cancel</button>
    <button class="btn btn-success" onclick="submitPayment()"><i class="fas fa-check"></i> Record Payment</button>
  </div>
</div>
</div>

<script src="assets/js/main.js"></script>
<script>
async function viewInvoice(id) {
  Notify.loading('Loading bill...');
  const res = await Ajax.get(`invoices.php?action=get_full&id=${id}`);
  Notify.close();
  if(!res.success) return Notify.error('Error', res.message);
  
  const inv = res.invoice;
  const items = res.items || [];
  
  let html = `
    <div style="display:flex; justify-content:space-between; margin-bottom: 2rem; border-bottom: 2px solid var(--border); padding-bottom: 1rem;">
      <div>
        <h2 style="font-family:'Outfit',sans-serif; color:var(--primary); margin:0;">BILL</h2>
        <div style="color:var(--muted); font-size: 0.9rem;">#${inv.invoice_number}</div>
      </div>
      <div style="text-align:right">
        <h3 style="margin:0; font-family:'Outfit',sans-serif;">NUSU Management System</h3>
        <p style="color:var(--muted); margin:0; font-size: 0.85rem;">Status: <span style="font-weight:bold; color:var(--text)">${inv.status.toUpperCase()}</span></p>
      </div>
    </div>
    
    <div style="display:flex; justify-content:space-between; margin-bottom: 2rem;">
      <div>
        <div style="font-weight:600; color:var(--muted); font-size:0.8rem; text-transform:uppercase; letter-spacing:1px; margin-bottom:0.5rem">Billed To</div>
        <div style="font-weight:600; font-size:1.1rem">${inv.full_name}</div>
        <div style="color:var(--muted); font-size:0.9rem">${inv.email}</div>
        <div style="color:var(--muted); font-size:0.9rem">${inv.phone || ''}</div>
        <div style="color:var(--muted); font-size:0.9rem">${inv.address || ''}</div>
      </div>
      <div style="text-align:right">
        <div style="font-weight:600; color:var(--muted); font-size:0.8rem; text-transform:uppercase; letter-spacing:1px; margin-bottom:0.5rem">Bill Details</div>
        <div style="font-size:0.9rem"><strong>Date Issued:</strong> ${new Date(inv.created_at).toLocaleDateString()}</div>
        <div style="font-size:0.9rem"><strong>Due Date:</strong> ${inv.due_date ? new Date(inv.due_date).toLocaleDateString() : 'N/A'}</div>
      </div>
    </div>
    
    <table style="width:100%; border-collapse:collapse; margin-bottom: 2rem;">
      <thead>
        <tr style="border-bottom: 2px solid var(--border);">
          <th style="text-align:left; padding:0.8rem 0.5rem; color:var(--muted)">Item Description</th>
          <th style="text-align:center; padding:0.8rem 0.5rem; color:var(--muted)">Qty</th>
          <th style="text-align:right; padding:0.8rem 0.5rem; color:var(--muted)">Unit Price</th>
          <th style="text-align:right; padding:0.8rem 0.5rem; color:var(--muted)">Total</th>
        </tr>
      </thead>
      <tbody>
        ${items.map(item => `
          <tr style="border-bottom: 1px solid rgba(0,0,0,0.05);">
            <td style="padding:0.8rem 0.5rem;">${item.item_name}</td>
            <td style="text-align:center; padding:0.8rem 0.5rem;">${item.quantity}</td>
            <td style="text-align:right; padding:0.8rem 0.5rem;">FRW ${parseFloat(item.unit_price).toLocaleString()}</td>
            <td style="text-align:right; padding:0.8rem 0.5rem; font-weight:600">FRW ${parseFloat(item.total_price).toLocaleString()}</td>
          </tr>
        `).join('')}
        ${items.length === 0 ? `<tr><td colspan="4" style="text-align:center; padding:1rem; color:var(--muted)">No items associated with this invoice.</td></tr>` : ''}
      </tbody>
    </table>
    
    <div style="display:flex; justify-content:flex-end;">
      <div style="width:250px;">
        <div style="display:flex; justify-content:space-between; margin-bottom:0.5rem; padding: 0.2rem 0;">
          <span style="color:var(--muted)">Subtotal:</span>
          <span>FRW ${parseFloat(inv.subtotal).toLocaleString()}</span>
        </div>
        <div style="display:flex; justify-content:space-between; margin-bottom:0.5rem; padding: 0.2rem 0;">
          <span style="color:var(--muted)">Discount:</span>
          <span>-FRW ${parseFloat(inv.discount).toLocaleString()}</span>
        </div>
        <div style="display:flex; justify-content:space-between; margin-bottom:0.5rem; padding: 0.2rem 0;">
          <span style="color:var(--muted)">Tax (${parseFloat(inv.tax_percent)}%):</span>
          <span>FRW ${parseFloat(inv.tax_amount).toLocaleString()}</span>
        </div>
        <div style="display:flex; justify-content:space-between; margin-top:0.5rem; padding-top: 0.8rem; border-top: 2px solid var(--border); font-size:1.2rem; font-weight:bold; font-family:'Outfit',sans-serif">
          <span>Total:</span>
          <span style="color:var(--primary)">FRW ${parseFloat(inv.total_amount).toLocaleString()}</span>
        </div>
        <div style="display:flex; justify-content:space-between; margin-top:0.5rem; font-size:0.95rem;">
          <span style="color:var(--muted)">Paid Amount:</span>
          <span style="color:var(--success)">FRW ${parseFloat(inv.paid_amount).toLocaleString()}</span>
        </div>
        <div style="display:flex; justify-content:space-between; margin-top:0.5rem; font-size:0.95rem;">
          <span style="color:var(--muted)">Balance Due:</span>
          <span style="color:var(--danger); font-weight:bold">FRW ${parseFloat(inv.balance).toLocaleString()}</span>
        </div>
      </div>
    </div>
  `;
  document.getElementById('viewInvContent').innerHTML = html;
  Modal.open('viewInvModal');
}

async function editInvoice(id) {
  const res=await Ajax.get(`invoices.php?action=get&id=${id}`);
  if(!res.success) return;
  const i=res.invoice;
  document.getElementById('invId').value=i.id;
  document.getElementById('i_num').value=i.invoice_number;
  document.getElementById('i_client').value=i.client_name;
  document.getElementById('i_status').value=i.status;
  Modal.open('invModal');
}
async function submitInv() {
  const fd=new FormData(document.getElementById('invForm'));
  Notify.loading('Saving...');
  const res=await Ajax.post('invoices.php',fd,true);
  Notify.close();
  if(res.success){Notify.success('Saved!',res.message);setTimeout(()=>location.reload(),1200);}
  else Notify.error('Error',res.message);
}
async function delInvoice(id,num) {
  const ok=await Notify.confirmDelete('Bill '+num);
  if(!ok) return;
  const fd=new FormData();fd.append('action','delete');fd.append('id',id);
  const res=await Ajax.post('invoices.php',fd,true);
  if(res.success){Notify.success('Deleted!');setTimeout(()=>location.reload(),1200);}
  else Notify.error('Error',res.message);
}

async function recordPayment(id, num, balance) {
  document.getElementById('payInvoiceId').value = id;
  document.getElementById('payInvNum').value = num;
  document.getElementById('payBalance').value = 'FRW ' + parseFloat(balance).toLocaleString();
  document.getElementById('payAmount').value = balance;
  document.getElementById('payAmount').max = balance;
  document.getElementById('payNotes').value = '';
  Modal.open('payModal');
}

async function submitPayment() {
  const amount = parseFloat(document.getElementById('payAmount').value);
  const balanceValue = document.getElementById('payBalance').value || '0';
  const balance = parseFloat(balanceValue.replace(/[^0-9.-]/g, ''));
  
  if (!amount || amount <= 0) {
    Notify.error('Error', 'Please enter a valid payment amount.');
    return;
  }
  if (amount > balance) {
    Notify.error('Error', 'Payment amount cannot exceed balance due.');
    return;
  }

  if (window._isProcessingPayment) return;
  window._isProcessingPayment = true;
  
  const fd = new FormData(document.getElementById('payForm'));
  Notify.loading('Recording payment...');
  const res = await Ajax.post('invoices.php', fd, true);
  Notify.close();
  
  if (res.success) {
    Notify.success('Payment Recorded!', `New balance: FRW ${parseFloat(res.new_balance).toLocaleString()}`);
    setTimeout(() => location.reload(), 1500);
  } else {
    Notify.error('Error', res.message);
  }
}
</script>
</body>
</html>
