# ElectroServe ERP - Enhanced Ticket Workflow Implementation Guide

## Overview
This document details the complete implementation of the enhanced ticket workflow system for ElectroServe, including strict status transitions, material confirmation, time tracking, and comprehensive financial tracking.

---

## Phase 1: Database Enhancements

### Step 1: Apply Schema Changes
Run the following SQL file to update the database:
```sql
-- From: c:\xampp\htdocs\electroserve\SCHEMA_ENHANCEMENTS.sql
SOURCE SCHEMA_ENHANCEMENTS.sql;
```

### Changes Made:
1. **Tickets Table** - Added fields:
   - `loading_ticket` - Track confirmed loading tickets
   - `confirmed_by_staff_id` - Track who confirmed
   - `client_cost_confirmed` - Flag for client cost confirmation
   - `cost_confirmation_date` - When client confirmed

2. **Stock Movements Table** - Enhanced tracking:
   - `status` - Track movement status (pending/confirmed/reversed)
   - `cost_per_unit` - Cost price at time of movement
   - `selling_price_used` - Selling price for invoice
   - `profit_margin_percent` - Profit margin applied

3. **New Tables**:
   - `ticket_confirmations` - Track all confirmation events
   - `ticket_denial_log` - Mandatory denial reasons
   - `financial_tracking` - Comprehensive profit tracking
   - `ticket_status_transitions` - Audit trail for all transitions
   - `profit_margin_history` - Track margin changes

---

## Phase 2: Application Integration

### Step 1: Replace/Update Manager Classes

#### Option A: Complete Replacement (Recommended)
```bash
# Backup existing files
cp modules/TicketManager.php modules/TicketManager.php.backup
cp modules/TimeTrackingManager.php modules/TimeTrackingManager.php.backup

# Use new enhanced versions
cp modules/TicketManager_Enhanced.php modules/TicketManager.php
cp modules/TimeTrackingManager_Enhanced.php modules/TimeTrackingManager.php
```

#### Option B: Gradual Migration
Keep existing files but include enhanced versions for new functionality:
- Include both old and new classes with different namespaces
- Create wrapper functions for backward compatibility
- Phase out old code gradually

### Step 2: Update API Endpoints

Update `ticket_view.php` and `api/tickets.php` to use new methods:

#### Key Changes:
1. **Material Confirmation**:
   - OLD: Direct status change from 'assigned' to 'confirmed'
   - NEW: `TicketManager->transitionStatus($id, 'confirmed', $staffId, [])`
   - Validates materials exist before transition
   - Triggers stock deduction
   - Records confirmation event

2. **Denied Tickets**:
   - REQUIRED: Denial reason must be provided
   - Stored in `ticket_denial_log` table
   - Can be reopened with new technician

3. **Time Tracking**:
   - Must transition to 'ongoing' before starting timer
   - Start → Pause → Resume → Stop sequence
   - Labor cost calculated at stop

4. **Payment Processing**:
   - Only in 'completed' status
   - Automatically transitions to 'closed'
   - Records financial tracking data

---

## Phase 3: Workflow Implementation

### Ticket Status Lifecycle

```
PENDING (Initial State)
  ↓ [Admin/Sales assigns technician]
  ↓ Transition.pending→assigned + assign_tech

ASSIGNED (Material Selection)
  ├─→ [Add materials to ticket]
  ├─→ [Confirm materials & cost]
  ├─→ Transition.assigned→confirmed + deduct_stock
  │   (Stock deducted HERE, not during selection)
  │
  └─→ Deny [REQUIRES REASON] → DENIED

CONFIRMED (Awaiting Client Approval)
  ├─→ [Client confirms cost is acceptable]
  ├─→ Transition.confirmed→ongoing + set_client_confirmed
  │   (Timer will start from here)
  │
  └─→ Deny [REQUIRES REASON] → DENIED

ONGOING (Service In Progress)
  ├─→ [Start Timer] → time_start = NOW()
  ├─→ [Pause] → accumulate time + pause logging
  ├─→ [Resume] → restart timer
  ├─→ [Stop & Save] → finalize time + calculate labor
  │   ↓
  └─→ Transition.ongoing→completed + record_labor

COMPLETED (Ready for Payment)
  ├─→ [Process Payment]
  ├─→ Transition.completed→closed + record_transaction
  │   ↓
  └─→ Record financial tracking (profit, tax, net)

CLOSED (Final State)
  └─→ No further transitions
  └─→ Revenue recorded on dashboard
```

### Denied Ticket Workflow

```
ASSIGNED or CONFIRMED
  ├─→ [Deny with Reason]
  ├─→ Transition.X→denied + record_reason
  │   (Reason stored in ticket_denial_log)
  │   (ticket_id set to UNIQUE for reason reference)
  │
DENIED
  ├─→ [Reopen with new technician]
  ├─→ Transition.denied→assigned + set_reopened_by
  │   (Clear denial_reason from tickets table)
  │   (Record reopened_by in ticket_denial_log)
  │
  └─→ Can try service again
```

---

## Phase 4: UI Updates

### Cost Display Changes

#### BEFORE Confirmation (Assigned Status)
```
Service Cost:     FRW 150
Materials:        [Being added]
Material Total:   FRW 0 (updates as added)
─────────────────────────
Expected Total:   FRW 150
```

#### AFTER Confirmation (Confirmed Status)
```
Service Cost:     FRW 150
Material Cost:    FRW 120 (locked)
─────────────────────────
Total (Locked):   FRW 270
[Client Confirmation Required]
```

#### DURING Service (Ongoing Status)
```
Service Cost:     FRW 150
Material Cost:    FRW 120 (locked)
Labor Cost:       [Calculated when timer stops]
─────────────────────────
Total:            FRW 270 + Labor
```

#### READY FOR PAYMENT (Completed Status)
```
Service Cost:     FRW 150
Material Cost:    FRW 120
Labor Cost:       FRW 85 (2.5 hours @ FRW 34/hr)
─────────────────────────
Total:            FRW 355
[Payment Processing]
```

### Profit Margin Handling
- **Hidden from**: Customer-facing UI, Cost confirmation screens
- **Shown only in**: 
  - Admin dashboard (financial analysis)
  - Internal financial reports
  - Profit calculation engine
  - Invoice generation (internal reference)

---

## Phase 5: Stock Movement Tracking

### Timeline of Stock Changes

#### Material Selection (Assigned Status)
- Item added to ticket_items table
- Stock NOT yet deducted
- Status: PENDING (in ticket_items, not stock_movements)

#### Material Confirmation (Assigned→Confirmed Transition)
- Stock deducted from items table
- Entry created in stock_movements with:
  - `type`: 'ticket_used'
  - `status`: 'confirmed'
  - `cost_per_unit`: item's cost_price
  - `reference`: ticket_number
- Real-time stock reflects deduction

#### Stock Query Impact
```sql
-- Real stock available = Inventory quantity after deductions
-- Stock movements log = Audit trail of all changes
-- Ticket items = Materials allocated to specific ticket (may not be confirmed)
```

---

## Phase 6: Financial Tracking

### Profit Calculation

**When Ticket is CLOSED:**
1. Get total amount billed
2. Calculate item costs:
   - Sum all items' cost_price * quantity
   - From stock_movements with matching ticket_id
3. Calculate gross profit = Total Amount - Item Costs
4. Apply tax if applicable
5. Record in financial_tracking table

### Dashboard Revenue Update

The `financial_tracking` table is now the source of truth for:
- Revenue reporting
- Profit calculations
- Tax tracking
- Financial analysis

Query closed tickets' financial_tracking for dashboard metrics:
```sql
SELECT 
    SUM(gross_amount) as total_revenue,
    SUM(profit_amount) as total_profit,
    AVG(profit_percent) as avg_profit_margin
FROM financial_tracking
WHERE recorded_date >= DATE_SUB(NOW(), INTERVAL 30 DAY);
```

---

## Phase 7: Expenses Tracking

### Enhanced Expense Categories

The `expenses` table now tracks:

1. **Inventory Purchases** (category = 'inventory_purchase')
   - Links to item_id
   - Tracks quantity_purchased
   - Records unit_cost
   - Links to supplier

2. **Service Expenses** (category = 'other')
   - Marketing, branding, rent, etc.

3. **Operational** (category = 'salaries', 'maintenance', etc.)

### Monthly Expense Report

```sql
SELECT 
    category,
    SUM(amount) as total_by_category
FROM expenses
WHERE MONTH(created_at) = MONTH(NOW())
GROUP BY category;
```

---

## Implementation Checklist

### Database Setup
- [ ] Run SCHEMA_ENHANCEMENTS.sql
- [ ] Verify all new tables created
- [ ] Add indexes for performance
- [ ] Test migration doesn't break existing data

### Code Integration
- [ ] Deploy TicketManager_Enhanced.php → TicketManager.php
- [ ] Deploy TimeTrackingManager_Enhanced.php → TimeTrackingManager.php
- [ ] Update ticket_view.php to use new transition methods
- [ ] Update ticket_new.php for new workflow
- [ ] Update tickets.php list for new status badges
- [ ] Update API endpoints (api/tickets.php)

### UI Updates
- [ ] Remove profit display from cost confirmation screens
- [ ] Update status badges for new workflow states
- [ ] Add "Denied Reason" display in denied tickets
- [ ] Update timer display for new control flow
- [ ] Add confirmation dialogs before sensitive transitions
- [ ] Update activity logs to show detailed transition info

### Testing
- [ ] Test complete ticket workflow (pending→assigned→confirmed→ongoing→completed→closed)
- [ ] Test denied ticket with reason
- [ ] Test reopen denied ticket
- [ ] Test material add/remove only in assigned
- [ ] Test stock deduction on confirmation
- [ ] Test time tracking (start/pause/resume/stop)
- [ ] Test labor cost calculation
- [ ] Test payment processing
- [ ] Test financial tracking recording
- [ ] Test dashboard profit updates

### Documentation
- [ ] Update staff training materials
- [ ] Document new workflow steps
- [ ] Create troubleshooting guide
- [ ] Document API changes for third-party integrations

---

## Support & Troubleshooting

### Common Issues & Solutions

#### Issue: Stock not deducting on confirmation
**Solution**: Ensure transition.assigned→confirmed is called, not direct status update

#### Issue: Labor cost not calculated
**Solution**: Verify service_type_id exists and has base_rate set

#### Issue: Denied tickets missing reason
**Solution**: Check ticket_denial_log for entries and display reason from there

#### Issue: Dashboard profit not updating
**Solution**: Verify financial_tracking records created when closing tickets

---

## Rollback Procedure

If issues occur:
```bash
# 1. Restore backup managers
cp modules/TicketManager.php.backup modules/TicketManager.php
cp modules/TimeTrackingManager.php.backup modules/TimeTrackingManager.php

# 2. Revert database changes (create backup first!)
# Run restore script with old schema

# 3. Clear any incomplete transitions
UPDATE tickets SET status = 'pending' WHERE status = 'unknown';
```

---

## Version History

**v1.0** - Initial Enhanced Workflow Implementation
- Strict status transition validation
- Material confirmation workflow
- Stock deduction on confirmation only
- Time tracking with labor calculation
- Financial tracking system
- Denied ticket reason logging
