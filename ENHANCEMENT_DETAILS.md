# NUSU Ltd Electroserve - 2026 Enhancement Details

## Overview
This document details the complete enhancement implementation for the NUSU Ltd Electroserve ticket management system, with focus on material confirmation workflow, time tracking, stock management, and expense tracking.

---

## 1. DATABASE SCHEMA CHANGES

### Items Table - ID Format Update
**File**: `electroserve.sql`

```sql
-- Old Format: ITM-001, ITM-002, ...
-- New Format: 0001, 0002, ...

-- Rationale: 
- Simpler auto-generation (no need for prefix)
- Cleaner database design
- Easier reporting and filtering
```

**Updated Sample Data**:
```
0001 - 2.5mm Copper Cable
0002 - 16A Single Switch
0003 - 20A Circuit Breaker
... (continuing with 4-digit format)
```

### Tickets Table - Enhanced with Workflow Fields

**New Columns**:

```sql
-- Material Confirmation Tracking
confirmed_at DATETIME              -- When customer confirmed materials
material_confirmed TINYINT DEFAULT 0 -- Flag: 0=pending, 1=confirmed

-- Service Time Tracking
time_start DATETIME                -- When service started
time_end DATETIME                  -- When service ended  
time_total_minutes INT DEFAULT 0   -- Total service minutes worked
```

**Status Enum Update**:

```sql
-- Old: 'pending','assigned','ongoing','completed','closed','denied'
-- New: 'pending','assigned','confirmed','ongoing','completed','closed','denied'

-- Workflow Flow:
pending → assigned → confirmed → ongoing → completed → closed
                  ↓
                denied
```

### New Table: ticket_time_logs

```sql
CREATE TABLE ticket_time_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    action ENUM('start','pause','resume','stop'),
    action_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    staff_id INT,
    notes TEXT,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id),
    FOREIGN KEY (staff_id) REFERENCES staff(id)
);
```

**Purpose**: Track every time tracking event for audit trail

**Sample Records**:
```
1 | 100 | start  | 2026-04-29 09:00:00 | 2 | Start service
2 | 100 | pause  | 2026-04-29 09:30:00 | 2 | 30 minutes worked
3 | 100 | resume | 2026-04-29 10:00:00 | 2 | Resume after break
4 | 100 | stop   | 2026-04-29 12:00:00 | 2 | Total: 2 hours
```

### Stock Movements Table Enhancement

```sql
-- Added Fields:
ticket_id INT              -- Link to ticket
type ENUM(..., 'ticket_used') -- New type for ticket deductions

-- Example Entry When Materials Confirmed:
id  | item_id | ticket_id | type        | quantity | reference
103 | 5       | 100       | ticket_used | -2       | TK-1254
```

---

## 2. PHP CLASSES & METHODS

### TicketManager.php - Enhanced Methods

#### Method: `confirmMaterials($ticketId, $staffId, $customerConfirmation)`

**Purpose**: Move ticket from 'assigned' to 'confirmed' status and deduct stock

**Logic Flow**:
1. Verify ticket is in 'assigned' status
2. Get total material cost
3. Update ticket:
   - `status` → 'confirmed'
   - `confirmed_at` → NOW()
   - `material_confirmed` → 1
4. Call `deductTicketStock()` to remove from inventory
5. Log confirmation to ticket_logs
6. Send notification to technician

**Database Changes**:
```sql
UPDATE tickets SET status='confirmed', confirmed_at=NOW(), material_confirmed=1 
WHERE id=? AND status='assigned'

INSERT INTO stock_movements (item_id, ticket_id, type, quantity, ...)
VALUES (?, ?, 'ticket_used', -qty, ...)

INSERT INTO ticket_logs (ticket_id, status, notes) 
VALUES (?, 'confirmed', 'Materials confirmed by technician...')
```

**Key Feature**: Stock is deducted HERE, not during material selection!

#### Method: `denyTicket($ticketId, $denialReason, $staffId)`

**Purpose**: Deny ticket with mandatory reason

**Logic Flow**:
1. Validate denial_reason is not empty
2. Verify ticket is in 'assigned' or 'confirmed'
3. Update ticket:
   - `status` → 'denied'
   - `denial_reason` → provided reason
   - `technician_id` → NULL
4. Log denial with reason

**Validation**:
```php
if (empty($denialReason)) {
    error_log("Denial reason is required");
    return false;
}

// Only allow from these statuses
if (!in_array($current, ['assigned', 'confirmed'])) {
    error_log("Cannot deny from $current status");
    return false;
}
```

**Database Changes**:
```sql
UPDATE tickets SET status='denied', denial_reason=?, technician_id=NULL 
WHERE id=? AND status IN ('assigned','confirmed')

INSERT INTO ticket_logs (ticket_id, status, notes) 
VALUES (?, 'denied', 'Ticket denied. Reason: ' . $denialReason)
```

#### Method: `updateTicketStatus($ticketId, $status, $staffId, $notes)`

**Enhanced Validation**:
```php
$statusMap = [
    'pending'   => ['pending'],
    'assigned'  => ['pending', 'denied'],
    'confirmed' => ['assigned'],           // NEW
    'ongoing'   => ['confirmed'],          // Changed from ['assigned', 'pending']
    'completed' => ['ongoing'],
    'closed'    => ['completed'],
    'denied'    => ['pending', 'assigned', 'confirmed']
];

// NEW: Enforce material confirmation before ongoing
if ($status === 'ongoing' && !$ticket['material_confirmed']) {
    error_log("Cannot move to ongoing without material confirmation");
    return false;
}
```

---

### TimeTrackingManager.php - NEW CLASS

**File Location**: `modules/TimeTrackingManager.php`

#### Method: `startTimer($ticketId, $staffId)`

**Prerequisites**:
- Ticket status must be 'ongoing'
- time_start must be NULL (not already started)

**Actions**:
1. Insert 'start' action to ticket_time_logs
2. Set `time_start = NOW()` in tickets table
3. Return success with message

**Database**:
```sql
INSERT INTO ticket_time_logs (ticket_id, action, action_time, staff_id)
VALUES (?, 'start', NOW(), ?)

UPDATE tickets SET time_start = NOW() WHERE id = ?
```

#### Method: `pauseTimer($ticketId, $staffId)`

**Calculation**:
```php
$minutesWorked = calculateMinutesBetween($ticket['time_start']); // Time worked since last start
$newTotal = ($ticket['time_total_minutes'] ?? 0) + $minutesWorked; // Add to accumulated
```

**Actions**:
1. Calculate minutes since last start
2. Insert 'pause' action with notes
3. Update `time_total_minutes` with accumulated time
4. Set `time_start = NULL` (pause the timer)
5. Return minutes worked and new total

**Database**:
```sql
INSERT INTO ticket_time_logs (ticket_id, action, notes, staff_id)
VALUES (?, 'pause', 'Minutes worked: 30', ?)

UPDATE tickets SET time_total_minutes=?, time_start=NULL WHERE id=?
```

#### Method: `resumeTimer($ticketId, $staffId)`

**Actions**:
1. Verify timer not already running (time_start must be NULL)
2. Insert 'resume' action
3. Set `time_start = NOW()` to restart timer
4. Return success

**Database**:
```sql
INSERT INTO ticket_time_logs (ticket_id, action, action_time, staff_id)
VALUES (?, 'resume', NOW(), ?)

UPDATE tickets SET time_start = NOW() WHERE id = ?
```

#### Method: `stopTimer($ticketId, $staffId)`

**Calculation**:
```php
$finalMinutes = ($ticket['time_total_minutes'] ?? 0) + 
                calculateMinutesBetween($ticket['time_start']); // Add final segment

$laborCost = calculateLaborCost($ticketId, $finalMinutes);
// Formula: (Service Hourly Rate × Hours Worked)
```

**Actions**:
1. Calculate total minutes including running time
2. Calculate labor cost from hours
3. Insert 'stop' action with total time note
4. Update ticket:
   - `time_total_minutes` → final total
   - `time_end = NOW()`
   - `time_start = NULL`
   - `labor_cost` → calculated amount
5. Return total time and labor cost

**Database**:
```sql
INSERT INTO ticket_time_logs (ticket_id, action, notes, staff_id)
VALUES (?, 'stop', 'Total service time: 02:30', ?)

UPDATE tickets SET 
    time_total_minutes=?,
    time_end=NOW(),
    time_start=NULL,
    labor_cost=?
WHERE id=?
```

#### Method: `getTimerStatus($ticketId)`

**Returns**: Current timer state with running calculation

```php
return [
    'is_timer_running' => true/false,
    'total_minutes' => 150,
    'formatted_time' => '02:30',
    'service_cost' => 150.00,
    'labor_cost' => 75.00
];
```

---

### StockMovementManager.php - NEW CLASS

**File Location**: `modules/StockMovementManager.php`

#### Method: `recordStockIn($itemId, $quantity, $reference, $notes, $staffId)`

**Purpose**: Record incoming stock (purchases)

**Actions**:
1. Validate quantity > 0
2. Insert movement record with type='in'
3. Update items.quantity += quantity

**Database**:
```sql
INSERT INTO stock_movements (item_id, type, quantity, reference, notes, staff_id)
VALUES (?, 'in', ?, ?, ?, ?)

UPDATE items SET quantity = quantity + ? WHERE id = ?
```

#### Method: `recordStockOut($itemId, $quantity, $reference, $notes, $staffId)`

**Purpose**: Manual stock removal (sales, waste)

**Validation**:
```php
if ($item['quantity'] < $quantity) {
    return ['success' => false, 'message' => 'Insufficient stock'];
}
```

**Actions**:
1. Validate sufficient stock
2. Insert movement with type='out'
3. Update items.quantity -= quantity

#### Method: `recordAdjustment($itemId, $newQuantity, $reason, $staffId)`

**Purpose**: Inventory correction (count discrepancies)

**Actions**:
1. Get current quantity
2. Calculate difference
3. Insert adjustment movement
4. Update to new quantity

#### Method: `getTicketStockMovements($ticketId)`

**Purpose**: Get all stock changes for a specific ticket

**Returns**: Array of movements with item details

```php
[
    ['id' => 103, 'type' => 'ticket_used', 'quantity' => -2, 'item_name' => '...', ...]
]
```

#### Method: `getRealTimeStockData()`

**Purpose**: Dashboard integration with current stock metrics

**Returns**:
```php
[
    'total_cost_value' => 50000.00,      // Sum of (qty × cost_price)
    'total_selling_value' => 75000.00,   // Sum of (qty × selling_price)
    'total_items' => 1250,                // Total quantity of all items
    'total_unique_items' => 42,           // Number of different items
    'low_stock_count' => 5,               // Number of items below min
    'low_stock_items' => [...]            // Details of low stock items
]
```

---

## 3. WORKFLOW VISUAL

### Material Confirmation Phase

```
┌─────────────────────────────────────────────────────────┐
│ TICKET ASSIGNED TO TECHNICIAN                            │
│ Status: assigned                                          │
│ material_confirmed: 0 (false)                             │
└─────────────────────────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────────────┐
│ TECHNICIAN SELECTS MATERIALS                             │
│ • Add Item 1 (qty: 2, price: 100)  → No stock deducted   │
│ • Add Item 2 (qty: 1, price: 200)  → No stock deducted   │
│ • Add Item 3 (qty: 5, price: 50)   → No stock deducted   │
│                                                            │
│ Material Cost: 550                                         │
│ Service Cost: 150                                          │
│ PROFIT: HIDDEN (not yet shown)                           │
│ Total: Shows only Material + Service = 700               │
└─────────────────────────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────────────┐
│ CUSTOMER APPROVES COST                                   │
│ Click: "Confirm Materials & Cost"                         │
└─────────────────────────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────────────┐
│ MATERIALS CONFIRMED                                      │
│ Status: confirmed                                        │
│ material_confirmed: 1 (true)                             │
│ confirmed_at: 2026-04-29 14:30:00                        │
│                                                            │
│ STOCK DEDUCTED HERE:                                     │
│ • Item 1: qty 2 → stock -2                               │
│ • Item 2: qty 1 → stock -1                               │
│ • Item 3: qty 5 → stock -5                               │
│                                                            │
│ PROFIT NOW VISIBLE:                                      │
│ Material Cost:    550                                     │
│ Service Cost:     150                                     │
│ Subtotal:         700                                     │
│ Profit (20%):     140                                     │
│ TOTAL:            840                                     │
└─────────────────────────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────────────┐
│ READY TO START SERVICE                                   │
│ Click: "Start Service" → Status: ongoing                │
└─────────────────────────────────────────────────────────┘
```

### Time Tracking Phase

```
┌─────────────────────────────────────────────────────────┐
│ SERVICE ONGOING                                           │
│ Status: ongoing                                           │
│ time_start: 2026-04-29 15:00:00                           │
│ time_total_minutes: 0                                     │
└─────────────────────────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────────────┐
│ TIME TRACKING CONTROLS                                   │
│                                                            │
│ [START] Click to begin timer                              │
│         Inserts 'start' action                            │
│         Sets time_start = NOW()                           │
└─────────────────────────────────────────────────────────┘
                        ↓
          (Service work happening... ⏱️ 30 min)
                        ↓
┌─────────────────────────────────────────────────────────┐
│ [PAUSE] Click to pause                                   │
│         Calculates: 30 min since last start                │
│         Adds to total: 0 + 30 = 30 min                   │
│         Inserts 'pause' action                            │
│         Sets time_start = NULL                            │
└─────────────────────────────────────────────────────────┘
                        ↓
         (Break... no timer running... ⏸️)
                        ↓
┌─────────────────────────────────────────────────────────┐
│ [RESUME] Click to resume                                 │
│          Inserts 'resume' action                          │
│          Sets time_start = NOW()                          │
└─────────────────────────────────────────────────────────┘
                        ↓
          (More service work... ⏱️ 45 min)
                        ↓
┌─────────────────────────────────────────────────────────┐
│ [STOP & SAVE] Click to finalize                          │
│              Calculates: 45 min since resume             │
│              Total: 30 + 45 = 75 minutes (1h 15m)       │
│              Labor Cost: 150 RWF/hr × 1.25 hrs = 187.50  │
│              Inserts 'stop' action                        │
│              Updates: time_total_minutes = 75             │
│              Updates: labor_cost = 187.50                 │
│              Sets: time_end = NOW()                       │
└─────────────────────────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────────────┐
│ SERVICE COMPLETED                                        │
│ Status: completed (auto-move or manual)                 │
│ time_total_minutes: 75                                   │
│ labor_cost: 187.50                                       │
│                                                            │
│ FINAL COST CALCULATION:                                  │
│ Material Cost:    550.00                                 │
│ Service Cost:     150.00                                 │
│ Labor Cost:       187.50                                 │
│ Subtotal:         887.50                                 │
│ Profit (20%):     177.50                                 │
│ TOTAL:            1065.00                                │
└─────────────────────────────────────────────────────────┘
```

---

## 4. EXPENSE TRACKING

### Category Structure

```sql
Category                 | Description
------------------------|--------------------------------------------------
marketing               | Advertising, promotions, campaigns
branding                | Logo design, brand materials
rent                     | Office/warehouse rental
office_consumables      | Printer ink, paper, stationery
inventory_purchase      | Stock purchases from suppliers
salaries                | Employee salaries
maintenance             | Equipment maintenance, repairs
transportation          | Fuel, vehicle costs, shipping
other                   | Miscellaneous expenses
```

### Financial Summary Formula

```
REVENUE:
  ├─ Closed Tickets Only (status='closed')
  └─ Total = Sum of (total_amount) for all closed tickets

EXPENSES:
  ├─ Direct Item Costs = Sum of (quantity × cost_price) for items used
  └─ Expense Categories = Sum of amounts by category from expenses table

PROFIT:
  └─ Total Revenue - Total Expenses

PROFIT MARGIN %:
  └─ (Profit / Revenue) × 100
```

---

## 5. TESTING CHECKLIST

### Material Confirmation Flow
- [ ] Create ticket and assign technician
- [ ] Verify status changes to 'assigned'
- [ ] Add materials to ticket
- [ ] Verify stock NOT deducted yet
- [ ] Verify profit is hidden
- [ ] Click "Confirm Materials"
- [ ] Verify status changes to 'confirmed'
- [ ] Verify stock IS deducted
- [ ] Verify profit now shows
- [ ] Check ticket_logs for confirmation entry
- [ ] Check stock_movements for 'ticket_used' entry

### Time Tracking Flow
- [ ] Move ticket to 'ongoing'
- [ ] Verify time tracking card appears
- [ ] Click "Start" button
- [ ] Verify timer running
- [ ] Wait 2 minutes, click "Pause"
- [ ] Verify time accumulated
- [ ] Click "Resume"
- [ ] Verify timer continues
- [ ] Click "Stop & Save"
- [ ] Verify labor cost calculated
- [ ] Check ticket_time_logs has all events

### Denial Workflow
- [ ] Open ticket in 'assigned' status
- [ ] Click "Deny Ticket"
- [ ] Try submit without reason → Should fail
- [ ] Enter reason
- [ ] Submit denial
- [ ] Verify status = 'denied'
- [ ] Verify denial_reason saved
- [ ] Check ticket_logs for reason

---

## 6. DEPLOYMENT STEPS

### Step 1: Database Migration
```bash
# Backup current database
mysqldump -u root electroserve_db > backup_$(date +%Y%m%d).sql

# Apply schema changes
mysql -u root electroserve_db < electroserve.sql
```

### Step 2: File Updates
```bash
# Copy new classes to modules/
cp modules/TimeTrackingManager.php /path/to/modules/
cp modules/StockMovementManager.php /path/to/modules/

# Update existing class
# (Merge changes into TicketManager.php)

# Update ticket_view.php
# (Replace with enhanced version)
```

### Step 3: Test in Staging
- Run all testing checklist items
- Test with different user roles
- Test edge cases

### Step 4: Go Live
- Backup production database
- Deploy files
- Test in production
- Monitor logs

---

## Files Modified/Created

| File | Status | Changes |
|------|--------|---------|
| electroserve.sql | ✅ Modified | Schema updates |
| TicketManager.php | ✅ Modified | New methods |
| TimeTrackingManager.php | ✅ Created | New class |
| StockMovementManager.php | ✅ Created | New class |
| ticket_view.php | ✅ Modified | AJAX handlers + UI |
| ENHANCEMENT_SUMMARY.md | ✅ Created | Feature docs |
| This File | ✅ Created | Technical details |

---

**Implementation Date**: April 29, 2026
**Status**: ✅ Complete and Ready for Testing

