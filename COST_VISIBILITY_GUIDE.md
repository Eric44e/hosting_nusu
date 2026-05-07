# Cost Visibility & Profit Hiding Strategy

## Overview
The enhanced system hides profit margins from customer-facing interfaces while maintaining complete profit tracking for internal financial analysis.

---

## Cost Components

### 1. Service Cost
- **What it is**: Base rate for the service type (e.g., "Electrical Installation")
- **Who sees it**: Everyone (transparent)
- **Example**: FRW 150 for electrical installation
- **Visibility**: ALWAYS shown

### 2. Material Cost (Actual Selling Price)
- **What it is**: Cost of items used in service (at selling_price from inventory)
- **Who sees it**: Everyone (transparent)
- **Example**: FRW 120 for cables + switches
- **Visibility**: ALWAYS shown after material selection
- **Note**: This is the price charged to customer, not cost price

### 3. Labor Cost
- **What it is**: Calculated based on time worked (hours × hourly rate)
- **Who sees it**: Everyone (transparent)
- **Example**: 2.5 hours × FRW 34/hour = FRW 85
- **Visibility**: Shown ONLY after time is saved
- **Note**: Derived from service_type base_rate

### 4. Profit Margin (HIDDEN)
- **What it is**: Difference between selling price and cost price of items
- **Who sees it**: Internal only (reports, financial analysis)
- **Example**: Item costs FRW 50, sells for FRW 100 = FRW 50 profit
- **Visibility**: NEVER shown in UI
- **Storage**: In financial_tracking table after ticket closes

### 5. Total Amount (SHOWN)
- **What it is**: Service + Material + Labor
- **Calculation**: service_cost + material_total_price + labor_cost
- **Example**: FRW 150 + FRW 120 + FRW 85 = FRW 355
- **Note**: Does NOT include profit margin calculation

---

## Implementation: Where to Hide Profit

### 1. IN TICKET_ITEMS Table
The `total_price` should use:
```php
$total_price = $quantity * $unit_price; // Selling price, not cost price
```

Do NOT store or display:
- Cost price of items
- Profit per item
- Margin percentage

### 2. IN COST SUMMARY UI

#### SHOW:
```
Service Cost:        FRW 150
Material Cost:       FRW 120
Labor Cost:          FRW 85 (if applicable)
─────────────────────────
TOTAL:               FRW 355
```

#### DO NOT SHOW:
```
Profit Margin:       [HIDDEN]
Cost Price:          [HIDDEN]
Gross Profit:        [HIDDEN]
Profit %:            [HIDDEN]
```

### 3. IN DATABASE QUERIES

When retrieving ticket costs for UI:
```sql
-- CORRECT - Customer facing
SELECT 
    t.service_cost,
    SUM(ti.total_price) as material_cost,
    t.labor_cost,
    (t.service_cost + COALESCE(SUM(ti.total_price), 0) + t.labor_cost) as total
FROM tickets t
LEFT JOIN ticket_items ti ON ti.ticket_id = t.id
WHERE t.id = ?
GROUP BY t.id;

-- DO NOT expose these columns to customers:
-- - sub_categories.profit_margin
-- - items.cost_price
-- - financial_tracking.profit_amount
-- - financial_tracking.profit_percent
```

### 4. IN INVOICES

**Customer Invoice**:
```
Item 1:        FRW 100
Item 2:        FRW  50
Service:       FRW 150
────────────────────────
TOTAL:         FRW 300
```

**Internal Financial Report**:
```
Item 1:        FRW 100 (selling) - FRW 60 (cost) = FRW 40 profit
Item 2:        FRW  50 (selling) - FRW 30 (cost) = FRW 20 profit
Service:       FRW 150 (margin varies)
────────────────────────
TOTAL:         FRW 300
PROFIT:        FRW 60 (20% margin)
```

### 5. IN DASHBOARD

#### Public Dashboard:
- Total Revenue
- Active Tickets
- Completed Tickets
- Client Count

#### Internal/Admin Dashboard:
- Revenue (same as public)
- Total Profit (FROM financial_tracking)
- Profit Margin % (FROM financial_tracking)
- Cost Breakdown
- Category Analysis

---

## Code Implementation Examples

### API Response - Customer Facing
```php
// ticket_view.php - Calculate costs WITHOUT profit
$stmt = $pdo->prepare("
    SELECT 
        t.service_cost,
        SUM(ti.total_price) as material_cost,
        t.labor_cost
    FROM tickets t
    LEFT JOIN ticket_items ti ON ti.ticket_id = t.id
    WHERE t.id = ?
    GROUP BY t.id
");
$stmt->execute([$ticketId]);
$costs = $stmt->fetch();

$total = $costs['service_cost'] + $costs['material_cost'] + $costs['labor_cost'];

// OUTPUT (NO profit data):
echo "Service: " . formatMoney($costs['service_cost']);
echo "Materials: " . formatMoney($costs['material_cost']);
echo "Labor: " . formatMoney($costs['labor_cost']);
echo "TOTAL: " . formatMoney($total);
```

### Database - Profit Tracking (Internal Only)
```php
// When ticket is CLOSED - Record profit (internal use only)
$stmt = $pdo->prepare("
    INSERT INTO financial_tracking 
    (ticket_id, revenue_type, gross_amount, cost_base, profit_amount, profit_percent, recorded_date)
    SELECT 
        ?,
        'service',
        ? as gross_amount,
        SUM(sm.cost_per_unit * sm.quantity) as cost_base,
        (? - SUM(sm.cost_per_unit * sm.quantity)) as profit_amount,
        ((? - SUM(sm.cost_per_unit * sm.quantity)) / ? * 100) as profit_percent,
        NOW()
    FROM stock_movements sm
    WHERE sm.ticket_id = ?
");
```

### Dashboard Query - Admin Only
```php
// INTERNAL ONLY - NOT exposed to customers
$stmt = $pdo->prepare("
    SELECT 
        SUM(gross_amount) as total_revenue,
        SUM(profit_amount) as total_profit,
        AVG(profit_percent) as avg_margin,
        SUM(cost_base) as total_costs
    FROM financial_tracking
    WHERE recorded_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND profit_percent > 0
");
```

---

## Access Control Strategy

### Role-Based Access
```php
// In config.php or security function

function canViewProfitData($userRole) {
    $allowedRoles = ['admin', 'finance'];
    return in_array($userRole, $allowedRoles);
}

// Usage:
if (canViewProfitData($_SESSION['role'])) {
    // Show financial tracking data
    // Show profit calculations
    // Show cost analysis
} else {
    // Show only customer-facing costs
}
```

### Data Retrieval Strategy
```php
function getTicketCosts($ticketId, $includeProfit = false) {
    global $pdo;
    
    // Base query - always available
    $query = "SELECT service_cost, SUM(ti.total_price) as material_cost, labor_cost FROM tickets...";
    
    // If admin/finance:
    if ($includeProfit) {
        $query .= " UNION SELECT profit_amount, profit_percent FROM financial_tracking...";
    }
    
    return $pdo->prepare($query)->execute([$ticketId])->fetch();
}
```

---

## UI Components Update Required

### 1. ticket_view.php - Cost Summary Card
```php
// REMOVE these lines if present:
// $profitMargin = $ticket['profit_percent'];
// $profitAmount = $ticket['profit_amount'];
// Display profit on UI

// KEEP only:
$serviceCost = $ticket['service_cost'];
$materialCost = $mat; // sum of ticket_items total_price
$laborCost = $ticket['labor_cost'];
$total = $serviceCost + $materialCost + $laborCost;
```

### 2. invoice_print.php - Customer Invoice
```php
// Do NOT print:
// Profit Line Item
// Profit Percentage
// Cost Prices

// ONLY print:
// Service Cost
// Material Prices (selling price)
// Labor (if applicable)
// Tax
// Total Due
```

### 3. dashboard.php - Financial Cards
```php
// For ADMIN/FINANCE ONLY:
if (hasRole('admin', 'finance')) {
    // Show Profit Card
    // Show Margin Analysis
    // Show Cost Breakdown
}

// For Everyone:
// Show Revenue Card (total amount, not profit)
// Show Active Tickets Card
// Show Completed Tickets Card
```

### 4. reports.php - Financial Reports
```php
// Create separate report pages:
// - reports/revenue-report.php (everyone)
// - reports/profit-analysis.php (admin/finance only)
```

---

## Database Schema - Profit Tracking Structure

```sql
-- STOCK MOVEMENTS - Tracks item costs at time of use
stock_movements:
- cost_per_unit (visible to admin only)
- selling_price_used (visible to everyone)

-- FINANCIAL_TRACKING - Records profit after ticket closes
financial_tracking:
- gross_amount (revenue)
- cost_base (total item costs - admin only)
- profit_amount (admin only)
- profit_percent (admin only)
- net_profit (after tax - admin only)

-- TICKET_ITEMS - Materials in ticket
ticket_items:
- total_price (uses selling_price from items table)
- Does NOT store cost_price
```

---

## Migration Steps

### Step 1: Audit Current Code
Search for:
```bash
grep -r "profit" --include="*.php" .
grep -r "cost_price" --include="*.php" .
grep -r "margin" --include="*.php" .
```

Remove profit display from:
- Cost summary sections
- Customer invoices
- Public dashboards
- Any customer-facing page

### Step 2: Add Financial Tracking
- Implement financial_tracking table population
- Create admin-only financial reports
- Add role-based access to profit data

### Step 3: Test Visibility
- Verify customers don't see profit data
- Verify admin sees complete financial data
- Check all API endpoints
- Verify invoice output

### Step 4: Update Documentation
- Document profit data location (financial_tracking table)
- Document access restrictions
- Document report generation
- Document financial analysis process

---

## Testing Checklist

### Visibility Tests
- [ ] Customer sees total cost (no profit shown)
- [ ] Admin sees profit on dashboard
- [ ] Finance role sees detailed financial report
- [ ] Regular sales staff cannot access profit data
- [ ] Invoices don't show profit breakdown

### Data Accuracy Tests
- [ ] Financial tracking records created on ticket close
- [ ] Profit calculations correct (revenue - cost)
- [ ] Margin percentages accurate
- [ ] Stock movements track cost_per_unit

### Performance Tests
- [ ] Financial tracking queries fast
- [ ] No impact on customer-facing pages
- [ ] Dashboard loads within acceptable time
- [ ] Reports generate without timeout

---

## Troubleshooting

**Issue**: Profit data visible to customers
**Solution**: Check role-based access control, add visibility checks

**Issue**: Financial tracking not recording profit
**Solution**: Ensure transition.completed→closed calls recordFinancialTracking()

**Issue**: Cost calculations don't match selling prices
**Solution**: Verify ticket_items uses item.selling_price, not cost_price

**Issue**: Dashboard shows incorrect profit
**Solution**: Check financial_tracking query filters and date ranges
