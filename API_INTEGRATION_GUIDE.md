# API INTEGRATION GUIDE - AUTO-TRANSITION WORKFLOW

## 🔌 Integration Overview

This guide shows how to update your `ticket_view.php`, `api/tickets.php`, and AJAX handlers to use the new `AutoTransitionTicketManager` class for automatic status transitions.

---

## 📋 Files to Update

1. `ticket_view.php` - Ticket display and operation UI
2. `api/tickets.php` - AJAX endpoints for operations
3. Any other pages calling old TicketManager/TimeTrackingManager

---

## 1️⃣ Update ticket_view.php

### BEFORE (Old Manager)
```php
<?php
require_once 'modules/TicketManager.php';
require_once 'modules/TimeTrackingManager.php';

$ticketManager = new TicketManager($pdo);
$timeManager = new TimeTrackingManager($pdo);
?>
```

### AFTER (New Manager)
```php
<?php
require_once 'modules/AutoTransitionTicketManager.php';

$manager = new AutoTransitionTicketManager($pdo);
?>
```

---

## 2️⃣ Update HTML - Operations Display

### BEFORE: Manual Status Buttons
```html
<!-- OLD: Manual status buttons -->
<div class="ticket-actions">
    <button onclick="updateStatus('assigned')">Mark as Assigned</button>
    <button onclick="updateStatus('confirmed')">Mark as Confirmed</button>
    <button onclick="updateStatus('ongoing')">Mark as Ongoing</button>
</div>
```

### AFTER: Operations Panel
```html
<!-- NEW: Operations for current status -->
<div class="operations-panel" id="operationsPanel">
    <h3>Required Operations</h3>
    
    <?php
    // Get ticket status
    $stmt = $pdo->prepare("SELECT status FROM tickets WHERE id = ?");
    $stmt->execute([$_GET['id'] ?? 0]);
    $ticket = $stmt->fetch();
    
    // Get operations for current status
    $operations = $manager->getOperationsStatus($_GET['id'] ?? 0, $ticket['status'] ?? 'pending');
    ?>
    
    <div class="operation-list">
        <?php foreach ($operations as $op): ?>
            <div class="operation-item <?= $op['is_completed'] ? 'completed' : 'pending' ?>">
                <span class="status-icon">
                    <?= $op['is_completed'] ? '✓' : '○' ?>
                </span>
                <span class="operation-name"><?= htmlspecialchars($op['operation_name']) ?></span>
                
                <!-- Operation-specific UI -->
                <?php if ($op['operation_type'] === 'assign_tech' && !$op['is_completed']): ?>
                    <div class="operation-content">
                        <select id="technicianSelect" class="form-control">
                            <option value="">-- Select Technician --</option>
                            <?php 
                            $techStmt = $pdo->query("SELECT id, name FROM technicians WHERE status = 'active' ORDER BY name");
                            while ($tech = $techStmt->fetch()): ?>
                                <option value="<?= $tech['id'] ?>"><?= htmlspecialchars($tech['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                        <button class="btn btn-primary btn-sm" 
                                onclick="completeOperation('assign_tech', {technician_id: document.getElementById('technicianSelect').value})">
                            Assign
                        </button>
                    </div>
                <?php elseif ($op['is_completed']): ?>
                    <span class="completion-info">
                        Completed by: <?= htmlspecialchars($op['completed_by'] ?? 'System') ?>
                        at <?= $op['completed_at'] ?? 'N/A' ?>
                    </span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Show pending operations status -->
    <div class="pending-operations" id="pendingOps">
        <?php
        $pending = array_filter($operations, fn($op) => !$op['is_completed']);
        $pendingNames = array_map(fn($op) => $op['operation_name'], $pending);
        if ($pending):
            echo "<p class='alert alert-info'>";
            echo "Waiting for: " . implode(", ", $pendingNames);
            echo "</p>";
        else:
            echo "<p class='alert alert-success'>All operations complete. System will auto-advance status.</p>";
        endif;
        ?>
    </div>
</div>
```

---

## 3️⃣ Update AJAX Endpoints

### api/tickets.php - Complete Rewrite

```php
<?php
// api/tickets.php
header('Content-Type: application/json');
require_once '../includes/auth.php';
require_once '../modules/AutoTransitionTicketManager.php';

// Check if staff logged in
if (!isset($_SESSION['staff_id'])) {
    http_response_code(401);
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

$manager = new AutoTransitionTicketManager($pdo);
$action = $_GET['action'] ?? $_POST['action'] ?? null;
$ticketId = $_POST['ticket_id'] ?? $_GET['ticket_id'] ?? null;
$staffId = $_SESSION['staff_id'];

try {
    switch ($action) {
        // ===== PENDING Status Operations =====
        
        case 'assign_tech':
            $techId = $_POST['technician_id'] ?? null;
            $result = $manager->completeOperation(
                $ticketId,
                'assign_tech',
                $staffId,
                ['technician_id' => $techId]
            );
            echo json_encode($result);
            break;
        
        // ===== ASSIGNED Status Operations =====
        
        case 'add_material':
            $result = $manager->op_add_material(
                $ticketId,
                $staffId,
                [
                    'item_id' => $_POST['item_id'] ?? null,
                    'quantity' => $_POST['quantity'] ?? null,
                    'supplier_id' => $_POST['supplier_id'] ?? null,  // REQUIRED
                    'unit_price' => $_POST['unit_price'] ?? null     // Optional
                ]
            );
            echo json_encode($result);
            break;
        
        case 'confirm_material':
            $result = $manager->completeOperation(
                $ticketId,
                'confirm_material',
                $staffId,
                []
            );
            echo json_encode($result);
            break;
        
        // ===== CONFIRMED Status Operations =====
        
        case 'client_confirm_cost':
            $result = $manager->completeOperation(
                $ticketId,
                'client_confirm_cost',
                $staffId,
                []
            );
            echo json_encode($result);
            break;
        
        // ===== ONGOING Status Operations =====
        
        case 'start_timer':
            $result = $manager->completeOperation(
                $ticketId,
                'start_timer',
                $staffId,
                []
            );
            echo json_encode($result);
            break;
        
        case 'stop_timer':
            $result = $manager->completeOperation(
                $ticketId,
                'stop_timer',
                $staffId,
                []
            );
            echo json_encode($result);
            break;
        
        // ===== COMPLETED Status Operations =====
        
        case 'process_payment':
            $result = $manager->completeOperation(
                $ticketId,
                'process_payment',
                $staffId,
                [
                    'amount' => $_POST['amount'] ?? 0,
                    'payment_method' => $_POST['payment_method'] ?? 'cash'
                ]
            );
            echo json_encode($result);
            break;
        
        // ===== Helper Endpoints =====
        
        case 'get_operations':
            // Get operations for current ticket
            $stmt = $pdo->prepare("SELECT status FROM tickets WHERE id = ?");
            $stmt->execute([$ticketId]);
            $ticket = $stmt->fetch();
            
            $operations = $manager->getOperationsStatus($ticketId, $ticket['status'] ?? 'pending');
            echo json_encode([
                'success' => true,
                'current_status' => $ticket['status'] ?? 'unknown',
                'operations' => $operations
            ]);
            break;
        
        case 'get_ticket_materials':
            // Get materials for current ticket (before stock deduction)
            $stmt = $pdo->prepare("
                SELECT ti.*, s.name as supplier_name
                FROM ticket_items ti
                LEFT JOIN suppliers s ON s.id = (
                    SELECT supplier_id FROM item_expenses 
                    WHERE item_id = ti.item_id 
                    ORDER BY id DESC LIMIT 1
                )
                WHERE ti.ticket_id = ?
                ORDER BY ti.id
            ");
            $stmt->execute([$ticketId]);
            $materials = $stmt->fetchAll();
            echo json_encode([
                'success' => true,
                'materials' => $materials
            ]);
            break;
        
        case 'get_ticket_suppliers':
            // Get suppliers for items in inventory
            $stmt = $pdo->query("SELECT id, name FROM suppliers WHERE status = 'active' ORDER BY name");
            $suppliers = $stmt->fetchAll();
            echo json_encode([
                'success' => true,
                'suppliers' => $suppliers
            ]);
            break;
        
        case 'get_ticket_items':
            // Get available items for adding to ticket
            $search = $_GET['search'] ?? '';
            $query = "SELECT id, name, quantity, selling_price, cost_price, supplier_required 
                      FROM items WHERE status = 'active'";
            if ($search) {
                $query .= " AND (name LIKE ? OR code LIKE ?)";
                $searchTerm = "%$search%";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$searchTerm, $searchTerm]);
            } else {
                $stmt = $pdo->prepare($query);
                $stmt->execute();
            }
            $items = $stmt->fetchAll();
            echo json_encode([
                'success' => true,
                'items' => $items
            ]);
            break;
        
        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Unknown action: ' . htmlspecialchars($action)
            ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
```

---

## 4️⃣ Update JavaScript Handlers

### In ticket_view.php or separate script file

```javascript
// Complete operation and handle auto-transition
function completeOperation(operationType, details = {}) {
    const ticketId = getTicketIdFromPage(); // Your method to get ID
    
    // Validate operation-specific requirements
    if (operationType === 'assign_tech' && !details.technician_id) {
        alert('Please select a technician');
        return;
    }
    
    if (operationType === 'add_material') {
        if (!details.item_id) {
            alert('Please select an item');
            return;
        }
        if (!details.quantity || details.quantity <= 0) {
            alert('Please enter valid quantity');
            return;
        }
        if (!details.supplier_id) {
            alert('Supplier selection is required');
            return;
        }
    }
    
    // Call API
    fetch('api/tickets.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: operationType,
            ticket_id: ticketId,
            ...details
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('success', data.message);
            
            // Handle auto-transition
            if (data.auto_transitioned) {
                showNotification('info', `Status auto-advanced to: ${data.new_status}`);
                setTimeout(() => {
                    location.reload();  // Reload to show new operations
                }, 1500);
            } else {
                // Just refresh operations panel
                refreshOperationsPanel();
            }
        } else {
            showNotification('error', data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('error', 'Request failed');
    });
}

// Refresh operations list
function refreshOperationsPanel() {
    const ticketId = getTicketIdFromPage();
    
    fetch(`api/tickets.php?action=get_operations&ticket_id=${ticketId}`)
    .then(response => response.json())
    .then(data => {
        // Update operations panel dynamically
        if (data.success) {
            document.getElementById('operationsPanel').innerHTML = 
                renderOperationsPanel(data.operations);
            document.getElementById('pendingOps').innerHTML = 
                renderPendingOpsStatus(data.operations);
        }
    });
}

// Render operations panel HTML
function renderOperationsPanel(operations) {
    let html = '<div class="operation-list">';
    
    operations.forEach(op => {
        const completed = op.is_completed ? 'completed' : 'pending';
        const icon = op.is_completed ? '✓' : '○';
        
        html += `
            <div class="operation-item ${completed}">
                <span class="status-icon">${icon}</span>
                <span class="operation-name">${escapeHtml(op.operation_name)}</span>
        `;
        
        if (op.is_completed) {
            html += `<span class="completion-info">
                Completed at ${op.completed_at}
            </span>`;
        }
        
        html += '</div>';
    });
    
    html += '</div>';
    return html;
}

// Render pending operations status
function renderPendingOpsStatus(operations) {
    const pending = operations.filter(op => !op.is_completed);
    
    if (pending.length > 0) {
        const names = pending.map(op => op.operation_name).join(', ');
        return `<p class="alert alert-info">Waiting for: ${escapeHtml(names)}</p>`;
    }
    
    return `<p class="alert alert-success">All operations complete. Auto-advancing...</p>`;
}

// Utility: Show notification
function showNotification(type, message) {
    // Bootstrap toast or custom notification
    const alertClass = {
        success: 'alert-success',
        error: 'alert-danger',
        info: 'alert-info',
        warning: 'alert-warning'
    }[type] || 'alert-info';
    
    const alert = document.createElement('div');
    alert.className = `alert ${alertClass} alert-dismissible`;
    alert.innerHTML = `
        ${escapeHtml(message)}
        <button class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.prepend(alert);
    
    setTimeout(() => alert.remove(), 5000);
}

// Utility: Escape HTML
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

// Utility: Get ticket ID
function getTicketIdFromPage() {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get('id') || document.querySelector('[data-ticket-id]')?.dataset.ticketId;
}
```

---

## 5️⃣ UI Component Examples

### Material Selection Modal

```html
<!-- Modal for adding material -->
<div class="modal fade" id="addMaterialModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Material to Ticket</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Select Item *</label>
                    <input type="text" id="itemSearch" class="form-control" 
                           placeholder="Search items by name or code..."
                           onkeyup="searchItems(this.value)">
                    <div id="itemSearchResults" class="list-group mt-2"></div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Quantity *</label>
                    <input type="number" id="materialQty" class="form-control" 
                           min="1" placeholder="Quantity needed">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">
                        Select Supplier 
                        <span id="supplierRequired" class="text-danger" style="display:none;">*</span>
                    </label>
                    <select id="supplierSelect" class="form-select">
                        <option value="">-- Select Supplier --</option>
                    </select>
                    <small class="form-text text-muted" id="supplierHint"></small>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Unit Price (Optional)</label>
                    <input type="number" id="unitPrice" class="form-control" 
                           step="0.01" placeholder="Uses item price if empty">
                </div>
                
                <div id="itemInfo" class="alert alert-info" style="display:none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveMaterial()">Add Material</button>
            </div>
        </div>
    </div>
</div>

<script>
// Load suppliers on page load
function loadSuppliers() {
    fetch('api/tickets.php?action=get_ticket_suppliers')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const select = document.getElementById('supplierSelect');
            data.suppliers.forEach(sup => {
                const option = document.createElement('option');
                option.value = sup.id;
                option.textContent = sup.name;
                select.appendChild(option);
            });
        }
    });
}

// Search items
function searchItems(query) {
    if (!query || query.length < 2) {
        document.getElementById('itemSearchResults').innerHTML = '';
        return;
    }
    
    fetch(`api/tickets.php?action=get_ticket_items&search=${encodeURIComponent(query)}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const resultsDiv = document.getElementById('itemSearchResults');
            resultsDiv.innerHTML = '';
            
            data.items.forEach(item => {
                const div = document.createElement('div');
                div.className = 'list-group-item list-group-item-action cursor-pointer';
                div.innerHTML = `
                    <div class="d-flex justify-content-between">
                        <strong>${escapeHtml(item.name)}</strong>
                        <span class="badge bg-info">${item.quantity} in stock</span>
                    </div>
                    <small>Price: FRW ${item.selling_price} | Cost: FRW ${item.cost_price}
                        ${item.supplier_required ? '<span class="badge bg-warning">Supplier Required</span>' : ''}
                    </small>
                `;
                
                div.onclick = () => selectItem(item);
                resultsDiv.appendChild(div);
            });
        }
    });
}

// Select item
function selectItem(item) {
    document.getElementById('itemSearch').value = item.name;
    document.getElementById('itemSearchResults').innerHTML = '';
    
    // Show item info
    const infoDiv = document.getElementById('itemInfo');
    infoDiv.innerHTML = `
        <strong>Selected:</strong> ${escapeHtml(item.name)}<br>
        <strong>In Stock:</strong> ${item.quantity}<br>
        <strong>Selling Price:</strong> FRW ${item.selling_price}<br>
        <strong>Cost Price:</strong> FRW ${item.cost_price}
    `;
    infoDiv.style.display = 'block';
    
    // Set supplier requirement
    if (item.supplier_required) {
        document.getElementById('supplierRequired').style.display = 'inline';
        document.getElementById('supplierHint').textContent = 'Supplier is required for this item';
    } else {
        document.getElementById('supplierRequired').style.display = 'none';
        document.getElementById('supplierHint').textContent = '';
    }
    
    // Store item ID for later
    document.getElementById('itemSearch').dataset.itemId = item.id;
}

// Save material
function saveMaterial() {
    const itemId = document.getElementById('itemSearch').dataset.itemId;
    const qty = document.getElementById('materialQty').value;
    const supplierId = document.getElementById('supplierSelect').value;
    const unitPrice = document.getElementById('unitPrice').value;
    
    if (!itemId || !qty || !supplierId) {
        alert('Please fill in all required fields');
        return;
    }
    
    completeOperation('add_material', {
        item_id: itemId,
        quantity: qty,
        supplier_id: supplierId,
        unit_price: unitPrice || null
    });
    
    // Close modal
    const modal = bootstrap.Modal.getInstance(document.getElementById('addMaterialModal'));
    modal.hide();
    
    // Reset form
    document.getElementById('itemSearch').value = '';
    document.getElementById('materialQty').value = '';
    document.getElementById('supplierSelect').value = '';
    document.getElementById('unitPrice').value = '';
    document.getElementById('itemInfo').style.display = 'none';
}

// Load on page load
document.addEventListener('DOMContentLoaded', loadSuppliers);
</script>
```

---

## 6️⃣ Status Display Component

```html
<!-- Show current ticket status -->
<div class="ticket-status-section">
    <?php
    $stmt = $pdo->prepare("SELECT id, status FROM tickets WHERE id = ?");
    $stmt->execute([$_GET['id'] ?? 0]);
    $ticket = $stmt->fetch();
    
    $statusColors = [
        'pending' => 'secondary',
        'assigned' => 'primary',
        'confirmed' => 'info',
        'ongoing' => 'warning',
        'completed' => 'success',
        'closed' => 'dark',
        'denied' => 'danger'
    ];
    
    $statusColor = $statusColors[$ticket['status'] ?? 'unknown'] ?? 'secondary';
    ?>
    
    <div class="alert alert-<?= $statusColor ?> mb-3">
        <h5 class="alert-heading">Current Status</h5>
        <p class="mb-0">
            <strong>Ticket #<?= htmlspecialchars($ticket['id']) ?></strong> is in 
            <span class="badge bg-<?= $statusColor ?>"><?= strtoupper($ticket['status']) ?></span>
            status
        </p>
    </div>
</div>
```

---

## 7️⃣ Testing Checklist

```javascript
// Test complete workflow in browser console

// 1. Create ticket (assumes ID = 1)
const ticketId = 1;

// 2. Test: Assign technician
completeOperation('assign_tech', {technician_id: 5})
  .then(() => console.log('✓ Tech assigned, should auto-transition to ASSIGNED'))

// 3. Test: Add first material
completeOperation('add_material', {
  item_id: 10,
  quantity: 5,
  supplier_id: 3,
  unit_price: null
})
  .then(() => console.log('✓ Material 1 added'))

// 4. Test: Add second material
completeOperation('add_material', {
  item_id: 12,
  quantity: 2,
  supplier_id: 4,
  unit_price: 150
})
  .then(() => console.log('✓ Material 2 added'))

// 5. Test: Confirm materials (should deduct stock + auto-transition to CONFIRMED)
completeOperation('confirm_material', {})
  .then(() => console.log('✓ Materials confirmed, stock deducted, auto-transition to CONFIRMED'))

// 6. Test: Client confirm cost
completeOperation('client_confirm_cost', {})
  .then(() => console.log('✓ Cost confirmed, auto-transition to ONGOING'))

// 7. Test: Start timer
completeOperation('start_timer', {})
  .then(() => console.log('✓ Timer started'))

// 8. Wait 10 seconds, then stop timer
setTimeout(() => {
  completeOperation('stop_timer', {})
    .then(() => console.log('✓ Timer stopped, labor calculated, auto-transition to COMPLETED'))
}, 10000)

// 9. Test: Process payment (should record financial tracking + auto-transition to CLOSED)
completeOperation('process_payment', {
  amount: 500,
  payment_method: 'cash'
})
  .then(() => console.log('✓ Payment processed, auto-transition to CLOSED'))
```

---

## ✅ Verification Steps

1. **Operations Panel Shows Correctly**
   - [ ] Navigate to ticket
   - [ ] Verify operations list appears for current status
   - [ ] Verify pending operations marked with ○
   - [ ] Verify completed operations marked with ✓

2. **Assign Technician Works**
   - [ ] Select technician dropdown
   - [ ] Click "Assign"
   - [ ] Verify notification appears
   - [ ] Verify auto-transition (page reloads showing new status)

3. **Add Material Works with Supplier**
   - [ ] Click "Add Material"
   - [ ] Search and select item
   - [ ] Verify supplier_required flag shown if needed
   - [ ] Select supplier (required)
   - [ ] Enter quantity and click "Add"
   - [ ] Verify material appears in list

4. **Stock Deduction Works**
   - [ ] Check inventory before confirm
   - [ ] Confirm materials
   - [ ] Verify auto-transition to CONFIRMED
   - [ ] Check inventory after - should be decreased

5. **Timer Works**
   - [ ] Click "Start Timer"
   - [ ] Wait a few seconds
   - [ ] Click "Stop Timer"
   - [ ] Verify labor cost calculated
   - [ ] Verify auto-transition to COMPLETED

6. **Payment & Financial Tracking**
   - [ ] Enter payment amount
   - [ ] Click "Process Payment"
   - [ ] Verify financial_tracking table populated
   - [ ] Verify profit calculated correctly
   - [ ] Verify auto-transition to CLOSED

---

**Status**: Ready for Implementation
**Integration Level**: Deep (touches multiple system components)
**Testing Priority**: High (workflow-critical changes)
