# AUTO-TRANSITION TICKET WORKFLOW GUIDE

## 🎯 Core Concept: Operations Drive Status Changes

**Key Principle**: No manual status updates. Status transitions automatically when all required operations for current status are completed.

---

## 📋 How It Works

### Traditional Approach (OLD)
```
User clicks button → Status manually updated → Workflow broken if button clicked wrong time
```

### Auto-Transition Approach (NEW)
```
User completes operation → Operation marked complete → System checks requirements
→ If all done → Status auto-advances → Next operations created
```

---

## 🔄 Status Lifecycle with Operations

### PENDING Status
**Required Operations**: `assign_tech`

```
PENDING
├─ Operation: assign_tech
│  └─ Action: Select technician and assign
│  └─ Required: YES
│  └─ When complete: Ticket AUTO → ASSIGNED
└─ Auto-transition: YES (when above done)
```

**Workflow**:
1. User selects technician from dropdown
2. System executes `op_assign_tech()` operation
3. Operation marked complete
4. System checks: "Are all required ops done?" → YES
5. **AUTO-TRANSITION** → ASSIGNED status
6. ASSIGNED operations created automatically
7. Notification sent: "Ticket auto-advanced to ASSIGNED"

---

### ASSIGNED Status
**Required Operations**: `add_material`, `confirm_material`

```
ASSIGNED
├─ Operation: add_material (repeatable)
│  ├─ Action: Add item to ticket with supplier
│  ├─ Required: YES (at least 1)
│  ├─ Supplier selection: REQUIRED
│  ├─ Stock deduction: NOT YET
│  └─ Can be repeated: YES (add multiple items)
│
├─ Operation: confirm_material
│  ├─ Action: Lock materials and deduct stock
│  ├─ Required: YES
│  ├─ Stock deduction: YES (happens here)
│  └─ Supplier link: Already recorded
│
└─ Auto-transition: YES (when both done)
   └─ Triggered on: confirm_material completion
   └─ Pre-transition action: Stock already deducted
   └─ Next status: CONFIRMED
```

**Workflow**:
1. System shows empty materials list
2. User clicks "Add Material"
3. User selects:
   - Item from inventory
   - Supplier for that item (REQUIRED)
   - Quantity
4. System executes `op_add_material()`:
   - Validates supplier exists
   - Records supplier with item
   - Creates entry in `item_expenses` table
   - Does NOT deduct stock yet
5. User adds more materials (repeatable)
6. When ready, user clicks "Confirm Materials"
7. System executes `op_confirm_material()`:
   - Deducts stock from inventory
   - Records in `stock_movements` with supplier info
   - Creates entries in `item_expenses`
8. Operation marked complete
9. System checks: "Both add_material and confirm_material done?" → YES
10. **AUTO-TRANSITION** → CONFIRMED status
11. CONFIRMED operations created
12. Notification: "Materials confirmed, stock deducted. Awaiting client cost approval"

---

### CONFIRMED Status
**Required Operations**: `client_confirm_cost`

```
CONFIRMED
├─ Operation: client_confirm_cost
│  ├─ Action: Client confirms cost is acceptable
│  ├─ Required: YES
│  ├─ Cost locked: YES
│  ├─ Can modify cost: NO
│  └─ Shows: service + materials + any labor (if pre-calculated)
│
└─ Auto-transition: YES (when above done)
   └─ Next status: ONGOING
   └─ Note: Timer can be started from ONGOING
```

**Workflow**:
1. System shows locked cost breakdown:
   - Service Cost: [amount]
   - Material Cost: [amount]
   - Total: [amount]
   - (Profit NOT shown)
2. Technician presents cost to client
3. Client agrees cost is acceptable
4. User clicks "Client Confirms Cost"
5. System executes `op_client_confirm_cost()`:
   - Records client confirmation
   - Locks cost (read-only from now on)
6. Operation marked complete
7. System checks: "Is client_confirm_cost done?" → YES
8. **AUTO-TRANSITION** → ONGOING status
9. ONGOING operations created
10. Notification: "Ready to start service. Click Start Timer to begin."

---

### ONGOING Status
**Required Operations**: `start_timer`, `stop_timer`

```
ONGOING
├─ Operation: start_timer
│  ├─ Action: Start service timer
│  ├─ Required: YES
│  ├─ Can pause/resume: YES (flexible)
│  └─ When complete: Timer running
│
├─ Pause/Resume (optional, flexible)
│  ├─ Use: TimeTrackingManager pause/resume methods
│  ├─ Tracked: YES (all events logged)
│  └─ No operation mark: No status blocking
│
├─ Operation: stop_timer
│  ├─ Action: Stop timer, calculate labor cost
│  ├─ Required: YES
│  ├─ Labor calculated: Automatically (time × hourly_rate)
│  └─ Total updated: YES (service + material + labor)
│
└─ Auto-transition: YES (when both done)
   └─ Triggered on: stop_timer completion
   └─ Labor cost: Calculated automatically
   └─ Next status: COMPLETED
```

**Workflow**:
1. User clicks "Start Timer"
2. System executes `op_start_timer()`:
   - Records timer start time
   - Logs time event
3. Operation marked complete (but not blocking)
4. Technician works on service
5. User can pause/resume as needed (using TimeTrackingManager methods):
   - Pause: Accumulates time, clears start
   - Resume: Restarts timer
   - All events logged
6. When service complete, user clicks "Stop Timer"
7. System executes `op_stop_timer()`:
   - Calculates total time worked
   - Calculates labor cost: (time in hours) × (service hourly rate)
   - Updates ticket total_amount
   - Logs time event with labor cost
8. Operation marked complete
9. System checks: "Both start_timer and stop_timer done?" → YES
10. **AUTO-TRANSITION** → COMPLETED status
11. COMPLETED operations created
12. Notification: "Service completed. Total time: Xh Ym. Labor cost: FRW Z. Ready for payment."

---

### COMPLETED Status
**Required Operations**: `process_payment`

```
COMPLETED
├─ Operation: process_payment
│  ├─ Action: Process payment for service
│  ├─ Required: YES
│  ├─ Records: Transaction, financial tracking
│  ├─ Amount: Must match total_amount
│  └─ When complete: Revenue recorded
│
└─ Auto-transition: YES (when above done)
   └─ Pre-transition action: Financial tracking recorded
   └─ Profit calculated: Automatically
   └─ Next status: CLOSED
```

**Workflow**:
1. System shows:
   - Total amount due
   - Paid amount (if any)
   - Balance due
2. User enters payment amount and method
3. User clicks "Process Payment"
4. System executes `op_process_payment()`:
   - Records transaction
   - Calculates profit: Revenue - (Material Cost + Service Cost)
   - Records in financial_tracking table
   - Logs transaction
5. Operation marked complete
6. System checks: "Is process_payment done?" → YES
7. **AUTO-TRANSITION** → CLOSED status
8. No more operations (final state)
9. Notification: "Ticket closed. Revenue recorded on dashboard."

---

### CLOSED Status (Final)
```
CLOSED (No operations, no transitions)
├─ Read-only
├─ Cannot reopen
├─ Cannot modify
└─ Available in reports and analytics only
```

---

## 💾 Database Tables for Auto-Transitions

### ticket_operations Table
Tracks all operations and their completion status:

```sql
id                 INT
ticket_id          INT (FK to tickets)
status             VARCHAR(50) -- Which status these ops belong to
operation_type     ENUM(...) -- Type of operation
operation_name     VARCHAR(255) -- Display name
is_required        TINYINT -- 1=must complete
is_completed       TINYINT -- 1=done, 0=pending
completed_by       INT (FK to staff)
completed_at       DATETIME
notes              TEXT
```

**Usage**:
- Create entries when ticket enters status
- Mark complete when operation done
- Check completion to trigger auto-transition

---

## 🛒 Item Expenses & Supplier Tracking

### item_expenses Table
Links items to suppliers and tracks costs:

```sql
id                INT
item_id           INT (FK to items)
supplier_id       INT (FK to suppliers) -- REQUIRED
purchase_date     DATETIME
quantity          INT
unit_cost         DECIMAL(12,2) -- Item cost price
total_cost        DECIMAL(12,2) -- quantity × unit_cost
description       TEXT
reference_number  VARCHAR(100) -- Ticket reference
staff_id          INT (FK to staff)
```

**When Entries Created**:
1. When `add_material` operation executed (on ticket)
2. When `confirm_material` operation executed (confirmation)
3. When expense added to item (inventory purchase)

**Benefits**:
- Know which supplier provided item
- Track costs per supplier
- Analyze supplier performance
- Total expenses = sum of all item_expenses

---

## 💰 Expense Calculation

### Total Item Cost (with expenses):
```
items.total_cost = SUM(item_expenses.total_cost) for that item
```

### Example:
```
Item: Cable 2.5mm
├─ Expense 1: 50m @ 1.20 = 60 RWF (from Supplier A)
├─ Expense 2: 100m @ 1.25 = 125 RWF (from Supplier B)
├─ Expense 3: 75m @ 1.10 = 82.50 RWF (from Supplier A again)
└─ Total Cost for Item: 267.50 RWF

When added to ticket:
├─ User selects: Cable 2.5mm, Quantity: 30m, Supplier: Supplier A
├─ System uses: Supplier A's latest unit_cost (1.10) × 30 = 33 RWF
├─ Charged to customer: 30 × selling_price
└─ Profit: (30 × selling_price) - 33
```

---

## 🔧 Implementation for Developers

### How to Complete an Operation

```php
// Get auto-transition manager
$manager = new AutoTransitionTicketManager($pdo);

// Example: Assign technician (completes 'assign_tech' operation)
$result = $manager->completeOperation(
    $ticketId,
    'assign_tech',
    $staffId,
    ['technician_id' => $techId]
);

// Response includes auto-transition info
if ($result['auto_transitioned']) {
    echo "Status auto-advanced to: " . $result['new_status'];
}
```

### Example: Add Material with Supplier

```php
$result = $manager->op_add_material(
    $ticketId,
    $staffId,
    [
        'item_id' => 123,
        'quantity' => 5,
        'supplier_id' => 45,      // REQUIRED
        'unit_price' => null      // Uses item selling_price if null
    ]
);

if ($result['success']) {
    // Material added, item_expenses entry created
    // Stock NOT yet deducted (happens on confirm_material)
}
```

### Example: Check Operations Status

```php
$operations = $manager->getOperationsStatus($ticketId);

// Returns:
// [
//   ['operation_type' => 'assign_tech', 'is_completed' => 1, ...],
//   ['operation_type' => 'add_material', 'is_completed' => 1, ...],
//   ['operation_type' => 'confirm_material', 'is_completed' => 0, ...],
// ]

// Check if can advance
$canAdvance = $manager->areAllOperationsComplete($ticketId);
```

---

## 📊 UI Implementation Pattern

### Display Operations (Not Buttons)
```html
<!-- PENDING Status -->
<div class="operations-panel">
  <h3>Required Operations for PENDING</h3>
  <ul>
    <li>
      ☐ Assign Technician
      <select id="technicianSelect">
        <option>-- Select --</option>
        <?php foreach($technicians as $tech): ?>
          <option value="<?= $tech['id'] ?>"><?= $tech['name'] ?></option>
        <?php endforeach; ?>
      </select>
      <button onclick="completeOperation('assign_tech', {technician_id: selectedValue()})">
        Complete
      </button>
    </li>
  </ul>
</div>

<!-- Will auto-transition when assign_tech marked complete -->
```

### After Auto-Transition
```
✓ ASSIGNED Status
  Required Operations:
  ☐ Add Material (repeatable)
  ☐ Confirm Materials
  
  [Add Material] [Confirm]
  
  When both done → Auto-transition to CONFIRMED
```

---

## 🔐 Supplier Selection Enforcement

### Check if Supplier Required
```php
// items table: supplier_required TINYINT (1 = yes, 0 = no)

// Before allowing add_material:
if ($item['supplier_required'] && !$supplierId) {
    return "Supplier required for this item";
}
```

### Set Supplier Requirement
```sql
UPDATE items SET supplier_required = 1 WHERE category_id = 2;
```

---

## ⚡ Auto-Transition Flow Diagram

```
┌─ PENDING ─────────────┐
│                       │
│ Operations:           │
│ • assign_tech         │
│                       │
│ When all complete:    │
│ ↓ AUTO ↓              │
├─ ASSIGNED ───────────┤
│                       │
│ Operations:           │
│ • add_material (+S)   │ ← Supplier selection
│ • confirm_material    │
│                       │
│ Pre-transition:       │
│ • Deduct stock        │
│ ↓ AUTO ↓              │
├─ CONFIRMED ──────────┤
│                       │
│ Operations:           │
│ • client_confirm_cost │
│                       │
│ ↓ AUTO ↓              │
├─ ONGOING ────────────┤
│                       │
│ Operations:           │
│ • start_timer         │
│ • stop_timer          │
│ + Pause/Resume OK     │
│                       │
│ Pre-transition:       │
│ • Calculate labor     │
│ ↓ AUTO ↓              │
├─ COMPLETED ──────────┤
│                       │
│ Operations:           │
│ • process_payment     │
│                       │
│ Pre-transition:       │
│ • Record financial    │
│ ↓ AUTO ↓              │
├─ CLOSED ─────────────┤
│                       │
│ Final state           │
│ Read-only             │
└───────────────────────┘
```

---

## 🎯 Key Benefits

1. **No Manual Mistakes**: Can't change status at wrong time
2. **Automatic Progression**: No "next" button needed
3. **Clear Requirements**: See exactly what's needed for each status
4. **Supplier Tracking**: Know source of every item
5. **Expense Integration**: Total costs include all expenses
6. **Audit Trail**: Every operation recorded with timestamps
7. **Notifications**: Auto-notified of status changes
8. **Financial Accuracy**: Profit calculated at close time

---

## 📝 Example Complete Workflow

**Time**: 10:00 AM
1. **PENDING** - Admin creates ticket
2. Admin assigns technician → `completeOperation('assign_tech', ...)`
   - Auto-transitions → **ASSIGNED**

**Time**: 10:30 AM
3. **ASSIGNED** - Technician adds materials:
   - Adds Cable from Supplier A: `completeOperation('add_material', {item_id: 1, supplier_id: 5, qty: 50})`
     - item_expenses entry created
     - Stock NOT deducted
   - Adds Switch from Supplier B: `completeOperation('add_material', {item_id: 2, supplier_id: 6, qty: 2})`
     - item_expenses entry created
4. Technician confirms materials: `completeOperation('confirm_material', ...)`
   - Stock deducted (via deductStockFromInventory)
   - item_expenses updated with confirmation
   - Auto-transitions → **CONFIRMED**

**Time**: 11:00 AM
5. **CONFIRMED** - Client confirms cost
   - Client reviews: Service (150) + Materials (100) = 250 RWF
   - Client confirms OK: `completeOperation('client_confirm_cost', ...)`
   - Auto-transitions → **ONGOING**

**Time**: 11:15 AM
6. **ONGOING** - Service begins
   - Start timer: `completeOperation('start_timer', ...)`
   - Work proceeds
   - Pause for lunch (pause_timer, resume_timer)
   - Continue work

**Time**: 3:00 PM
7. Service completes
   - Stop timer: `completeOperation('stop_timer', ...)`
   - Labor calculated: 4.5 hours × 33.33 = 150 RWF
   - Total: 250 + 150 = 400 RWF
   - Auto-transitions → **COMPLETED**

**Time**: 3:15 PM
8. **COMPLETED** - Payment
   - Process payment: `completeOperation('process_payment', {amount: 400, method: 'cash', ...})`
   - Financial tracking recorded:
     - Revenue: 400
     - Cost: 60 (materials) + 150 (service) = 210
     - Profit: 190
     - Margin: 47.5%
   - Auto-transitions → **CLOSED**

**Time**: 3:16 PM
9. **CLOSED** - Done
   - Ticket archived
   - Revenue updated on dashboard
   - Profit recorded in financial_tracking

---

## ✅ Pre-Deployment Checklist

- [ ] SCHEMA_ENHANCEMENTS.sql run (includes ticket_operations, item_expenses tables)
- [ ] AutoTransitionTicketManager.php deployed
- [ ] ticket_view.php updated to use completeOperation()
- [ ] UI shows operations instead of manual status buttons
- [ ] Supplier selection required before add_material
- [ ] item_expenses linked to items
- [ ] Auto-transition notifications working
- [ ] Operations mark complete properly
- [ ] Stock deduction tested
- [ ] Financial tracking recorded
- [ ] Dashboard profit updates

---

**Status**: Ready for Implementation
**Key Change**: From manual status updates to automatic operation-driven transitions
**Benefit**: Prevents workflow errors, ensures data consistency, integrates expenses and suppliers
