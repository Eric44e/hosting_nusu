# ElectroServe Enhanced System - Quick Reference Guide

## 🎯 Ticket Workflow at a Glance

```
┌─────────────┐
│   PENDING   │  → Awaiting assignment
└──────┬──────┘
       │ [Assign Tech]
       ↓
┌─────────────┐
│  ASSIGNED   │  → Select & confirm materials
└──────┬──────┘
       │ [Add Materials + Confirm]
       │ [Stock deducted HERE]
       ↓
┌──────────────┐
│ CONFIRMED    │  → Awaiting client approval
└──────┬───────┘
       │ [Client confirms cost]
       │ [Timer ready]
       ↓
┌─────────────┐
│  ONGOING    │  → Service in progress
└──────┬──────┘   [Start/Pause/Resume/Stop]
       │
       ↓
┌──────────────┐
│ COMPLETED    │  → Ready for payment
└──────┬───────┘
       │ [Process Payment]
       │ [Revenue recorded]
       ↓
┌─────────────┐
│   CLOSED    │  → Final state
└─────────────┘
```

### Side Path: Denied Tickets
```
[assigned/confirmed] → [Deny with REASON] → [DENIED]
                                               ↓ [Reopen]
                                         Back to [ASSIGNED]
```

---

## 📋 What's New & Enhanced

### 1. ✅ 4-Digit Item Codes
- All items now use format: 0001, 0042, 0999, etc.
- Auto-generated in application code
- Database schema already supports this

### 2. ✅ Material Confirmation Workflow
- **Before**: Materials added, stock deducted immediately
- **Now**: 
  - Add materials in 'assigned' status (preview only)
  - Confirm materials → moves to 'confirmed' → **stock deducted**
  - Two-step process prevents mistakes

### 3. ✅ Profit Hidden from UI
- **Customer sees**: Service + Materials + Labor = Total
- **Hidden**: 
  - Profit margin percentages
  - Cost prices
  - Internal markup calculations
- **Admin sees**: Full financial breakdown in reports only

### 4. ✅ Cost Confirmation Before Service
- **Step 1**: Technician selects materials (Assigned)
- **Step 2**: Confirm materials & costs (Assigned → Confirmed)
- **Step 3**: Client confirms cost is acceptable
- **Step 4**: Start service timer
- **Result**: Client never surprised by final cost

### 5. ✅ Time Tracking Integration
- Start → Pause → Resume → Stop sequence
- Labor cost calculated automatically
- Labor = (Service base_rate ÷ hours) × time_worked
- Flexible for breaks and interruptions

### 6. ✅ Denied Tickets with Reasons
- **Required**: Every denial must have a reason
- **Logged**: ticket_denial_log tracks all denials
- **Reopenable**: Can reopen with new technician
- **Categories**: no_materials, cost_issue, no_technician, client_unavailable, other

### 7. ✅ Stock Movement Tracking
- **Before**: Basic in/out tracking
- **Now**: 
  - Tracks cost_per_unit at time of movement
  - Tracks selling_price_used
  - Tracks profit_margin_percent
  - Linked to specific tickets

### 8. ✅ Financial Tracking System
- **New Table**: financial_tracking
- **Records**: Revenue, cost basis, profit, tax
- **Triggers**: Automatically when ticket closed
- **Use**: Dashboard profit reporting, financial analysis

### 9. ✅ Strict Status Transitions
- **Before**: Could change status manually
- **Now**: 
  - Transitions validated at database level
  - Transition audit trail in ticket_status_transitions
  - Required fields checked before transition
  - System prevents invalid sequences

### 10. ✅ Comprehensive Audit Trail
- **ticket_status_transitions**: Every status change logged
- **ticket_confirmations**: Material, cost, and payment confirmations
- **ticket_denial_log**: Denial reasons and reopen tracking
- **ticket_time_logs**: All timer events (start/pause/resume/stop)
- **ticket_logs**: General activity log

---

## 🚀 Implementation Quick Start

### Phase 1: Database (30 minutes)
```bash
# 1. Run the schema enhancements
mysql -u root -p electroserve_db < SCHEMA_ENHANCEMENTS.sql

# 2. Verify tables created
SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = 'electroserve_db' 
ORDER BY TABLE_NAME;
```

### Phase 2: Deploy Managers (10 minutes)
```bash
# 1. Backup originals
cp modules/TicketManager.php modules/TicketManager.php.backup
cp modules/TimeTrackingManager.php modules/TimeTrackingManager.php.backup

# 2. Deploy enhanced versions
cp modules/TicketManager_Enhanced.php modules/TicketManager.php
cp modules/TimeTrackingManager_Enhanced.php modules/TimeTrackingManager.php
cp modules/WorkflowValidator.php modules/WorkflowValidator.php
```

### Phase 3: Update ticket_view.php (60 minutes)
Key changes needed:
```php
// Include new classes
require_once 'modules/WorkflowValidator.php';

// Use new transition method
$result = (new TicketManager($pdo))->transitionStatus(
    $ticketId, 
    'confirmed', 
    $staffId, 
    ['details' => 'data']
);

// Validate before showing UI elements
$validator = new WorkflowValidator($pdo);
$canAddItems = $validator->canPerformAction($ticketId, 'add_item')['allowed'];
```

### Phase 4: Test (120 minutes)
- [ ] Create ticket (pending)
- [ ] Assign technician (→ assigned)
- [ ] Add material (while assigned)
- [ ] Confirm materials (→ confirmed, stock deducted)
- [ ] Check stock moved
- [ ] Start timer (→ ongoing)
- [ ] Pause/Resume timer
- [ ] Stop timer
- [ ] Check labor cost calculated
- [ ] Process payment (→ closed)
- [ ] Verify financial_tracking record created
- [ ] Test denied ticket with reason
- [ ] Test reopen denied ticket

---

## 📊 Key Database Tables

### Core Tables
- `tickets` - Main ticket records
- `ticket_items` - Materials in each ticket
- `ticket_time_logs` - Time tracking events
- `stock_movements` - Stock deduction records

### Audit Tables (NEW)
- `ticket_status_transitions` - Status change audit
- `ticket_confirmations` - Confirmation events
- `ticket_denial_log` - Denial tracking
- `financial_tracking` - Profit/revenue records

---

## 🔑 Key Methods

### TicketManager
```php
// Create ticket
createTicket($data, $staffId) → ticketId

// Transition status (validates automatically)
transitionStatus($ticketId, $toStatus, $staffId, $data) → array

// Manage materials
addTicketItem($ticketId, $itemId, $quantity) → array
removeTicketItem($itemId, $ticketId) → array

// Process payment
processPaymentAndClose($ticketId, $amount, $method, $staffId) → array
```

### TimeTrackingManager
```php
// Timer control
startTimer($ticketId, $staffId) → array
pauseTimer($ticketId, $staffId) → array
resumeTimer($ticketId, $staffId) → array
stopTimer($ticketId, $staffId) → array

// Get info
getTimerStatus($ticketId) → array
getTimeTrackingHistory($ticketId) → array
```

### WorkflowValidator
```php
// Validate transitions
validateTransition($ticketId, $from, $to) → array

// Check actions
canPerformAction($ticketId, $action) → array

// Get workflow info
getWorkflowStatus($ticketId) → array
getPossibleNextStatuses($currentStatus) → array
```

---

## 💰 Cost Calculation Examples

### Example 1: Complete Workflow
```
Service Type: Electrical Installation (base_rate: 150 RWF)
Materials: 
  - Cable 2.5mm: 50m @ 2.50 = 125 RWF
  - Switch 16A: 2 @ 7.00 = 14 RWF
Total Materials: 139 RWF

Time Worked: 3 hours
Labor Cost: 3 × (150/8) = 56.25 RWF
(Assuming 8-hour workday for base_rate)

TOTAL COST TO CUSTOMER:
  Service: 150 RWF
  Materials: 139 RWF
  Labor: 56.25 RWF
  ─────────────────
  TOTAL: 345.25 RWF
```

### Example 2: Internal Profit Tracking
```
CUSTOMER CHARGES:
  Service: 150 RWF
  Materials: 139 RWF (selling price)
  Labor: 56.25 RWF
  ─────────────────
  Total Revenue: 345.25 RWF

ACTUAL COSTS (internal only):
  Cable cost: 30m @ 1.20 = 36 RWF
  Switch cost: 2 @ 3.50 = 7 RWF
  Labor base cost: negligible (salaried)
  ─────────────────
  Total Cost: 43 RWF

PROFIT (NOT shown to customer):
  Profit: 302.25 RWF
  Margin: 87.6%
```

---

## 🔐 Security & Access

### Who Can See What
```
                          | Customer | Tech | Sales | Admin | Finance
─────────────────────────|────────────────────────────────────────────
Total Cost               |    ✓     |  ✓   |   ✓   |   ✓   |    ✓
Service Cost             |    ✓     |  ✓   |   ✓   |   ✓   |    ✓
Material Cost            |    ✓     |  ✓   |   ✓   |   ✓   |    ✓
Labor Cost               |    ✓     |  ✓   |   ✓   |   ✓   |    ✓
─────────────────────────────────────────────────────────────────────
Profit Amount            |    ✗     |  ✗   |   ✗   |   ✓   |    ✓
Profit Margin %          |    ✗     |  ✗   |   ✗   |   ✓   |    ✓
Cost Price               |    ✗     |  ✗   |   ✗   |   ✓   |    ✓
─────────────────────────────────────────────────────────────────────
```

---

## 📈 Reporting & Analytics

### Revenue Reports (Everyone)
- Total revenue this month
- Revenue by service type
- Revenue by technician
- Revenue by client

### Profit Reports (Admin/Finance Only)
- Total profit this month
- Profit margin by service type
- Profit margin by technician
- Cost analysis by category
- Profitability trends

---

## 🆘 Troubleshooting

### Issue: "Cannot transition from assigned to confirmed"
**Cause**: Missing materials or other requirement
**Solution**: Use WorkflowValidator to check requirements
```php
$validator = new WorkflowValidator($pdo);
$result = $validator->validateTransition($ticketId, 'assigned', 'confirmed');
if (!$result['valid']) {
    echo "Missing: " . implode(', ', $result['missing_fields']);
}
```

### Issue: Stock not deducting on confirmation
**Cause**: Stock deduction only happens on transition, not manual status update
**Solution**: Use `transitionStatus()` method instead of direct UPDATE

### Issue: Labor cost is zero
**Cause**: Time not tracked or service_type missing base_rate
**Solution**: Verify time_total_minutes > 0 and service_types.base_rate is set

### Issue: Profit not appearing on dashboard
**Cause**: financial_tracking not populated
**Solution**: Verify transition.completed→closed calls recordFinancialTracking()

---

## 📚 Documentation Files

- `SCHEMA_ENHANCEMENTS.sql` - Database changes
- `IMPLEMENTATION_GUIDE_ENHANCED.md` - Full implementation details
- `COST_VISIBILITY_GUIDE.md` - Profit hiding strategy
- `WORKFLOW_QUICK_REFERENCE.md` - This file

---

## 🎓 Staff Training Topics

1. **Technicians**:
   - Material selection and confirmation
   - Time tracking workflow
   - Cost confirmation with customers

2. **Admin/Sales**:
   - Ticket assignment and follow-up
   - Managing denied tickets
   - Financial tracking overview

3. **Finance**:
   - Profit analysis reports
   - Financial tracking interpretation
   - Cost vs. revenue analysis

4. **All Staff**:
   - New status meanings
   - Permission restrictions
   - Workflow diagram understanding

---

## ✅ Pre-Launch Checklist

Database:
- [ ] Schema enhancements applied
- [ ] All new tables created
- [ ] Indexes created
- [ ] No data corruption

Code:
- [ ] New managers deployed
- [ ] ticket_view.php updated
- [ ] ticket_new.php updated
- [ ] api/tickets.php updated
- [ ] Workflow validation in place

UI:
- [ ] Profit hidden from customer views
- [ ] Status badges updated
- [ ] Timer controls functional
- [ ] Denial reason modal works

Testing:
- [ ] Full workflow tested
- [ ] Denied ticket tested
- [ ] Reopen ticket tested
- [ ] Stock deduction verified
- [ ] Financial tracking verified

Documentation:
- [ ] Staff trained
- [ ] Procedures documented
- [ ] Troubleshooting guide available
- [ ] Change log prepared

---

**System Version**: 2.0 (Enhanced Workflow)
**Last Updated**: April 2026
**Status**: Ready for Implementation
