-- ============================================================
-- ELECTROSERVE ERP SYSTEM - ENHANCED WORKFLOW
-- ============================================================
-- This file contains schema enhancements for improved ticket workflow,
-- stock tracking, and financial management

-- ============================================================
-- 1. ENHANCE TICKETS TABLE - Add workflow tracking fields
-- ============================================================

ALTER TABLE tickets ADD COLUMN IF NOT EXISTS loading_ticket TINYINT DEFAULT 0 COMMENT '1=ticket confirmed and loading, 0=not';
ALTER TABLE tickets ADD COLUMN IF NOT EXISTS confirmed_by_staff_id INT COMMENT 'Staff member who confirmed materials with client';
ALTER TABLE tickets ADD COLUMN IF NOT EXISTS client_cost_confirmed TINYINT DEFAULT 0 COMMENT '1=client confirmed cost, 0=not';
ALTER TABLE tickets ADD COLUMN IF NOT EXISTS cost_confirmation_date DATETIME COMMENT 'When client confirmed cost';

-- ============================================================
-- 2. ENHANCE STOCK_MOVEMENTS TABLE - Better tracking
-- ============================================================

ALTER TABLE stock_movements ADD COLUMN IF NOT EXISTS status ENUM('pending','confirmed','reversed') DEFAULT 'confirmed' COMMENT 'Track movement status';
ALTER TABLE stock_movements ADD COLUMN IF NOT EXISTS cost_per_unit DECIMAL(12,2) DEFAULT 0 COMMENT 'Cost price used at time of movement';
ALTER TABLE stock_movements ADD COLUMN IF NOT EXISTS selling_price_used DECIMAL(12,2) DEFAULT 0 COMMENT 'Selling price used for invoice';
ALTER TABLE stock_movements ADD COLUMN IF NOT EXISTS profit_margin_percent DECIMAL(5,2) DEFAULT 0 COMMENT 'Profit margin applied';

-- ============================================================
-- 3. CREATE TICKET_CONFIRMATIONS TABLE - Track confirmations
-- ============================================================

CREATE TABLE IF NOT EXISTS ticket_confirmations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    confirmation_type ENUM('material','cost','client_cost','payment') NOT NULL,
    confirmed_by INT,
    confirmation_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    details JSON COMMENT 'Additional confirmation details',
    notes TEXT,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (confirmed_by) REFERENCES staff(id) ON DELETE SET NULL,
    INDEX idx_ticket (ticket_id),
    INDEX idx_type (confirmation_type)
);

-- ============================================================
-- 4. CREATE TICKET_DENIAL_LOG TABLE - Track denial reasons
-- ============================================================

CREATE TABLE IF NOT EXISTS ticket_denial_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL UNIQUE,
    denied_by INT,
    denial_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    denial_reason TEXT NOT NULL,
    denial_category ENUM('no_materials','cost_issue','no_technician','client_unavailable','other') DEFAULT 'other',
    can_reopen TINYINT DEFAULT 1,
    reopened_by INT,
    reopen_date DATETIME,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (denied_by) REFERENCES staff(id) ON DELETE SET NULL,
    FOREIGN KEY (reopened_by) REFERENCES staff(id) ON DELETE SET NULL,
    INDEX idx_ticket (ticket_id),
    INDEX idx_denied_date (denial_date)
);

-- ============================================================
-- 5. ENHANCE EXPENSES TABLE - Track item-based expenses
-- ============================================================

ALTER TABLE expenses ADD COLUMN IF NOT EXISTS item_id INT COMMENT 'Reference to item if expense is for inventory';
ALTER TABLE expenses ADD COLUMN IF NOT EXISTS quantity_purchased INT COMMENT 'Qty if this is inventory expense';
ALTER TABLE expenses ADD COLUMN IF NOT EXISTS unit_cost DECIMAL(12,2) COMMENT 'Cost per unit for inventory expenses';
ALTER TABLE expenses ADD COLUMN IF NOT EXISTS supplier_id INT COMMENT 'Reference to supplier if applicable';
ALTER TABLE expenses ADD COLUMN IF NOT EXISTS approval_status ENUM('pending','approved','rejected') DEFAULT 'approved';

-- Drop constraints if they already exist to prevent duplicate key errors
ALTER TABLE expenses DROP FOREIGN KEY IF EXISTS fk_expense_item;
ALTER TABLE expenses DROP FOREIGN KEY IF EXISTS fk_expense_supplier;

-- Add constraints
ALTER TABLE expenses ADD CONSTRAINT fk_expense_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE SET NULL;
ALTER TABLE expenses ADD CONSTRAINT fk_expense_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL;

-- ============================================================
-- 6. CREATE FINANCIAL_TRACKING TABLE - Comprehensive profit tracking
-- ============================================================

CREATE TABLE IF NOT EXISTS financial_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT,
    invoice_id INT,
    transaction_id INT,
    revenue_type ENUM('service','material','labor') DEFAULT 'service',
    gross_amount DECIMAL(12,2) NOT NULL,
    cost_base DECIMAL(12,2) DEFAULT 0 COMMENT 'Base cost (item cost price)',
    profit_amount DECIMAL(12,2) DEFAULT 0 COMMENT 'Gross - Cost',
    profit_percent DECIMAL(5,2) DEFAULT 0,
    tax_amount DECIMAL(12,2) DEFAULT 0,
    net_profit DECIMAL(12,2) DEFAULT 0 COMMENT 'Profit - Tax',
    recorded_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE SET NULL,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL,
    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE SET NULL,
    INDEX idx_ticket (ticket_id),
    INDEX idx_date (recorded_date)
);

-- ============================================================
-- 7. CREATE TICKET_STATUS_TRANSITIONS TABLE - Audit trail
-- ============================================================

CREATE TABLE IF NOT EXISTS ticket_status_transitions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    from_status VARCHAR(50),
    to_status VARCHAR(50) NOT NULL,
    transitioned_by INT,
    transition_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    allowed TINYINT DEFAULT 1 COMMENT '1=allowed transition, 0=blocked',
    block_reason TEXT COMMENT 'Why transition was blocked if applicable',
    transition_data JSON COMMENT 'Additional data for transition',
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (transitioned_by) REFERENCES staff(id) ON DELETE SET NULL,
    INDEX idx_ticket (ticket_id),
    INDEX idx_date (transition_date)
);

-- ============================================================
-- 8. ENSURE ITEM CODES ARE 4-DIGIT FORMAT
-- ============================================================

-- Items already use 0001-0010 format from initial data
-- Code generation in application should use: str_pad($number, 4, '0', STR_PAD_LEFT)
-- Example item codes: 0001, 0042, 0500, 9999 etc.

-- ============================================================
-- 9. CREATE PROFIT_MARGIN_HISTORY TABLE - Track changes
-- ============================================================

CREATE TABLE IF NOT EXISTS profit_margin_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT,
    sub_category_id INT,
    old_margin DECIMAL(5,2),
    new_margin DECIMAL(5,2),
    changed_by INT,
    changed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    reason TEXT,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE SET NULL,
    FOREIGN KEY (sub_category_id) REFERENCES sub_categories(id) ON DELETE SET NULL,
    FOREIGN KEY (changed_by) REFERENCES staff(id) ON DELETE SET NULL,
    INDEX idx_changed_at (changed_at)
);

-- ============================================================
-- 10. CREATE TICKET_OPERATIONS TABLE - Required operations per status
-- ============================================================

CREATE TABLE IF NOT EXISTS ticket_operations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    status VARCHAR(50) NOT NULL COMMENT 'Status this operation belongs to',
    operation_type ENUM('assign_tech','add_material','confirm_material','client_confirm_cost','start_timer','stop_timer','process_payment') NOT NULL,
    operation_name VARCHAR(255),
    is_required TINYINT DEFAULT 1 COMMENT '1=must complete, 0=optional',
    is_completed TINYINT DEFAULT 0 COMMENT '1=completed, 0=pending',
    completed_by INT,
    completed_at DATETIME,
    notes TEXT,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (completed_by) REFERENCES staff(id) ON DELETE SET NULL,
    INDEX idx_ticket_status (ticket_id, status),
    INDEX idx_completed (is_completed)
);

-- ============================================================
-- 11. CREATE ITEM_EXPENSES TABLE - Link expenses to items and suppliers
-- ============================================================

CREATE TABLE IF NOT EXISTS item_expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    supplier_id INT NOT NULL,
    expense_id INT,
    purchase_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    quantity INT NOT NULL,
    unit_cost DECIMAL(12,2) NOT NULL,
    total_cost DECIMAL(12,2) NOT NULL,
    description TEXT,
    reference_number VARCHAR(100),
    staff_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT,
    FOREIGN KEY (expense_id) REFERENCES expenses(id) ON DELETE SET NULL,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE SET NULL,
    INDEX idx_item (item_id),
    INDEX idx_supplier (supplier_id),
    INDEX idx_purchase_date (purchase_date)
);

-- ============================================================
-- 12. ENHANCE ITEMS TABLE - Track total cost including expenses
-- ============================================================

ALTER TABLE items ADD COLUMN IF NOT EXISTS total_cost DECIMAL(12,2) DEFAULT 0 COMMENT 'Total cost including all expenses';
ALTER TABLE items ADD COLUMN IF NOT EXISTS last_supplier_id INT COMMENT 'Last supplier this item was purchased from';
ALTER TABLE items ADD COLUMN IF NOT EXISTS supplier_required TINYINT DEFAULT 1 COMMENT '1=supplier must be selected, 0=optional';
ALTER TABLE items ADD CONSTRAINT fk_items_supplier FOREIGN KEY (last_supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL;

-- ============================================================
-- WORKFLOW RULES REFERENCE - AUTO TRANSITIONS
-- ============================================================
/*
TICKET STATUS AUTO-FLOW (No manual status updates):

PENDING STATE → Auto-transitions to ASSIGNED when:
  ✓ assign_tech operation completed
  
ASSIGNED STATE → Auto-transitions to CONFIRMED when:
  ✓ add_material operation done (at least 1 material)
  ✓ confirm_material operation completed
  System automatically deducts stock on transition
  
CONFIRMED STATE → Auto-transitions to ONGOING when:
  ✓ client_confirm_cost operation completed
  System ready for timer to start
  
ONGOING STATE → Auto-transitions to COMPLETED when:
  ✓ start_timer operation done
  ✓ stop_timer operation completed
  Labor cost calculated automatically
  
COMPLETED STATE → Auto-transitions to CLOSED when:
  ✓ process_payment operation completed
  Financial tracking recorded automatically
  
DENIED STATE:
  ✓ Manual reset required (reopen to ASSIGNED)
  ✓ Can only happen from ASSIGNED/CONFIRMED

OPERATION TRACKING:
Each status has required operations in ticket_operations table.
When operation is marked complete, system checks if all required ops done.
If all done → Auto-transition to next status.
If not all done → Stay in current status until all complete.

OPERATIONS BY STATUS:
  
  PENDING:
    - assign_tech (required)
      └─ Assign technician to ticket
  
  ASSIGNED:
    - add_material (required, repeatable)
      └─ Add items to ticket (must add at least 1)
    - confirm_material (required)
      └─ Confirm all materials are selected (stock deducts)
  
  CONFIRMED:
    - client_confirm_cost (required)
      └─ Client confirms cost is acceptable
  
  ONGOING:
    - start_timer (required)
      └─ Start service timing
    - stop_timer (required)
      └─ Stop service timing and calculate labor
  
  COMPLETED:
    - process_payment (required)
      └─ Process payment for service
*/

-- ============================================================
-- AUTO-TRANSITION LOGIC (Implemented in PHP)
-- ============================================================
/*
PSEUDOCODE FOR AUTO-TRANSITION:

function checkAndAutoTransition($ticketId) {
    $currentStatus = getTicketStatus($ticketId);
    
    $requiredOps = getRequiredOperations($ticketId, $currentStatus);
    $completedOps = getCompletedOperations($ticketId, $currentStatus);
    
    if (count($completedOps) === count($requiredOps)) {
        // All required operations complete
        $nextStatus = getNextStatus($currentStatus);
        
        // Pre-transition actions based on status change
        if ($currentStatus === 'assigned' && $nextStatus === 'confirmed') {
            deductStockFromInventory($ticketId);
        }
        if ($currentStatus === 'ongoing' && $nextStatus === 'completed') {
            calculateLaborCost($ticketId);
        }
        if ($currentStatus === 'completed' && $nextStatus === 'closed') {
            recordFinancialTracking($ticketId);
        }
        
        // Perform auto-transition
        updateTicketStatus($ticketId, $nextStatus);
        logStatusTransition($ticketId, $currentStatus, $nextStatus, 'auto');
        createNotification($ticketId, "Auto-advanced to $nextStatus");
    }
}

// Call this function:
// - After each operation is marked complete
// - When viewing ticket page (to refresh status)
// - In a cron job periodically (every 1 minute)
*/

-- ============================================================
-- ADD INDEXES FOR PERFORMANCE
-- ============================================================

CREATE INDEX IF NOT EXISTS idx_tickets_status ON tickets(status);
CREATE INDEX IF NOT EXISTS idx_tickets_client ON tickets(client_id);
CREATE INDEX IF NOT EXISTS idx_tickets_technician ON tickets(technician_id);
CREATE INDEX IF NOT EXISTS idx_tickets_created ON tickets(created_at);
CREATE INDEX IF NOT EXISTS idx_ticket_items_ticket ON ticket_items(ticket_id);
CREATE INDEX IF NOT EXISTS idx_stock_movements_item ON stock_movements(item_id);
CREATE INDEX IF NOT EXISTS idx_stock_movements_ticket ON stock_movements(ticket_id);
CREATE INDEX IF NOT EXISTS idx_invoices_ticket ON invoices(ticket_id);
CREATE INDEX IF NOT EXISTS idx_invoices_client ON invoices(client_id);
