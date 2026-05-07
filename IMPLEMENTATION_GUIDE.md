# ElectroServe Pro - Implementation Guide

## ✅ COMPLETED FEATURES

### 1. DATABASE SCHEMA ✓
- Added `profit_margin` to `sub_categories` (inherited by items)
- Added `contracts` table for contract management
- Added `ai_insights` table for AI predictions
- Updated `messages` table for client-support chat
- Updated `notifications` table with read tracking
- Updated `expenses` categories: marketing, branding, rent, office_consumables, inventory_purchase, salaries, maintenance, transportation, other

### 2. CORE MODULES ✓

#### PricingEngine.php
- Calculate selling price: `selling_price = cost_price + (cost_price × margin%)`
- Validate discounts: `discount ≤ margin`
- Calculate service labor: `1 hour = 200 RWF`
- Get item pricing with margin info

#### InventoryManager.php
- Inventory hierarchy: Category → Subcategory → Items
- Get low stock items
- Get fast-moving items (most used)
- Update stock after transactions
- Calculate inventory value
- Create purchase orders

#### CustomerManager.php
- Create customer (quick-add modal)
- Search customers
- Get customer with stats (total tickets, spent, etc.)
- Update customer info

#### TicketManager.php
- Create tickets with full details
- Ticket lifecycle: Pending → Assigned → Ongoing → Completed → Closed/Denied
- Add materials to tickets
- Calculate ticket costs & totals
- Self-assign to pending (technician only)
- Update ticket status with logging

#### TechnicianManager.php
- Get technician stats & performance
- Rating system (1-5 based on completion & performance)
- Get workload & availability
- Get technician schedule
- Top performers list

#### FinancialManager.php
- Revenue & expense tracking
- Financial summary (revenue, expenses, profit, margin%)
- Revenue by category
- Expenses by category
- Sales by user
- Transaction history

#### ContractManager.php
- Create contracts (linked to clients/tickets)
- Contract lifecycle: draft → active → completed → terminated
- Get expiring contracts
- Generate unique contract numbers

#### ReportGenerator.php
- Daily, monthly, yearly reporting
- Revenue vs Expenses report
- Ticket statistics
- Inventory usage report
- Period-based filtering (custom date range)
- Performance metrics

#### AIAnalysisEngine.php
- Revenue trend analysis
- Top category detection
- Expense analysis
- Low stock alerts
- Sales trend predictions
- Technician performance analysis
- Store & retrieve insights

#### NotificationManager.php
- Create notifications (real-time)
- Unread notification tracking
- Mark as read
- Send messages (staff-to-staff, client support)
- Chat history
- Notification stats

### 3. API ENDPOINTS ✓

#### `/api/inventory.php`
- `get_subcategories?category_id=X` - Get subcategories for category
- `get_items_by_category?subcategory_id=X` - Get items for subcategory
- `get_low_stock` - Get items with low stock
- `get_fast_moving` - Get fast-moving items
- `calculate_price` - Calculate selling price from cost & margin
- `get_all_margins` - Get all subcategory margins

#### `/api/customers.php`
- `create` (POST) - Create new customer
- `get?id=X` - Get customer with stats
- `list?page=X&search=Q` - List customers
- `search?q=QUERY` - Search customers
- `update` (POST) - Update customer info

#### `/api/tickets.php`
- `create` (POST) - Create new ticket
- `get?id=X` - Get ticket with details
- `self_assign` (POST) - Technician self-assigns pending ticket
- `assign` (POST) - Admin assigns to technician
- `update_status` (POST) - Update ticket status
- `add_item` (POST) - Add material to ticket
- `get_pending` - Get all pending tickets

#### `/api/reports.php`
- `financial_summary` - Get revenue/expenses/profit summary
- `revenue_by_category` - Revenue breakdown
- `expenses_by_category` - Expense breakdown
- `sales_by_user` - Sales per staff member
- `revenue_vs_expenses` - Period report (daily/monthly/yearly)
- `ticket_statistics` - Ticket status breakdown
- `inventory_usage` - Inventory usage report
- `performance_metrics` - Technician performance
- `insights` - Generate AI insights
- `latest_insights` - Get stored insights

#### `/api/notifications.php`
- `get_unread?limit=50` - Get unread notifications
- `get_all?page=1&limit=20` - Get all notifications
- `mark_read` (POST) - Mark notification as read
- `mark_all_read` (POST) - Mark all as read
- `send_message` (POST) - Send message to user/ticket
- `get_chat?user_id=X` or `?ticket_id=X` - Get chat history
- `get_stats` - Get notification stats

### 4. FRONTEND COMPONENTS ✓

#### Dashboard Pro (`dashboard_pro.php`)
- KPI cards (revenue, expenses, profit, margin%)
- 🤖 Smart Insights panel (4 auto-generated insights)
- 📈 Financial charts (bar chart, doughnut chart)
- ⚠️ Low stock alerts
- 🔥 Fast-moving items
- ⭐ Top technicians list
- 🔔 Recent notifications

#### Customer Modal (`includes/customer_modal.php`)
- Fast input: Name, Phone, Address (required)
- Optional fields: Email, City
- No page reload - modal popup
- Auto-generates client code
- Success/error notifications

## 🔐 SECURITY FEATURES

### Role-Based Access Control
- **Admin**: Full access to all features
- **Technician**: View only own tickets, can self-assign pending
- **Sales**: Manage customers, create tickets
- **Finance**: View financial reports, manage invoices
- **Logistics**: Manage inventory, stock

### Data Protection
- Profit margin hidden from non-admin users
- User tracking on all transactions
- Audit logs for sensitive operations

## 🚀 USAGE EXAMPLES

### 1. Calculate Selling Price
```php
$pricing = getModule('pricing');
$sellingPrice = $pricing->calculateSellingPrice(100, 20); // FRW 120
```

### 2. Create Customer
```php
$customer = getModule('customer');
$customerId = $customer->createCustomer([
    'full_name' => 'John Doe',
    'phone' => '+250781234567',
    'address' => '123 Main St',
    'email' => 'john@email.com'
]);
```

### 3. Create & Manage Ticket
```php
$ticket = getModule('ticket');

// Create
$ticketId = $ticket->createTicket([
    'client_id' => 1,
    'service_type_id' => 1,
    'title' => 'Electrical Installation',
    'priority' => 'high'
], $userId);

// Add material
$ticket->addTicketItem($ticketId, $itemId, 5); // 5 units

// Technician self-assigns
$ticket->selfAssignTicket($ticketId, $technicianStaffId);

// Update status
$ticket->updateTicketStatus($ticketId, 'completed', $userId, 'Job finished successfully');
```

### 4. Get Financial Report
```php
$financial = getModule('financial');
$summary = $financial->getFinancialSummary('2024-01-01', '2024-01-31');
echo "Revenue: " . $summary['revenue'];
echo "Expenses: " . $summary['expenses'];
echo "Profit: " . $summary['profit'];
```

### 5. Generate AI Insights
```php
$ai = getModule('ai');
$insights = $ai->generateInsights();
foreach ($insights as $insight) {
    echo $insight['title'] . ": " . $insight['insight_text'];
}
```

## 📊 KEY BUSINESS RULES

### Pricing System
- Margin set at **subcategory level** (inherited by all items)
- Selling price = Cost × (1 + Margin%)
- Max discount = Margin%
- Example: Cost=100, Margin=20% → Selling=120, Max Discount=20

### Service Labor
- Fixed rate: **200 RWF per hour**
- Calculated automatically from hours

### Technician Rating
- Base: 5.0/5.0
- -0.5 for each denied job
- +0.1 for each completed job
- +0.5 bonus if completion rate > 90%
- Capped at 1.0-5.0 range

### Ticket Lifecycle
1. **Pending** - Created, awaiting assignment
2. **Assigned** - Assigned to technician
3. **Ongoing** - Work in progress
4. **Completed** - Work finished, awaiting closure
5. **Closed** - Finalized, paid
6. **Denied** - Cancelled/rejected

## 📱 HOW TO USE FRONTEND COMPONENTS

### Open Customer Modal
```html
<button onclick="openCustomerModal()">Add Customer</button>
<?php include 'includes/customer_modal.php'; ?>
```

### Listen for Customer Created Event
```javascript
window.addEventListener('customerCreated', (e) => {
    const customer = e.detail;
    console.log('New customer:', customer);
    // Update dropdown, refresh list, etc.
});
```

### Fetch Data via API
```javascript
// Get low stock items
fetch('api/inventory.php?action=get_low_stock')
    .then(r => r.json())
    .then(data => console.log(data.data));

// Create customer
const form = new FormData();
form.append('full_name', 'John Doe');
form.append('phone', '+250781234567');
fetch('api/customers.php?action=create', { method: 'POST', body: form })
    .then(r => r.json())
    .then(data => console.log(data.customer));
```

## 🔄 WORKFLOW EXAMPLES

### Typical Ticket Workflow
1. Sales creates ticket for customer
2. Admin assigns to available technician
3. Technician accepts → status changes to "ongoing"
4. Technician adds materials used (with costs)
5. Ticket auto-calculates total cost
6. Technician marks as "completed"
7. Generates invoice (separate system)
8. Payment recorded → ticket marked "closed"

### Inventory Management
1. Low stock alert triggers automatically
2. Logistics creates purchase order
3. Items received → stock updated
4. Stock movement logged
5. Fast-moving items highlighted in dashboard

## 📈 REPORTING & ANALYTICS

### Available Reports
- Daily/Monthly/Yearly revenue vs expenses
- Revenue by category
- Expense breakdown
- Inventory usage
- Technician performance
- Ticket statistics
- Custom date ranges

### AI Insights Generated
- Revenue trends (month-over-month)
- Top performing category
- Highest expenses
- Low stock alerts
- Sales trend predictions
- Technician performance analysis

## 🎯 NEXT STEPS

1. **Update existing pages** to use new modules and APIs
2. **Create reports page** using Report Generator
3. **Create contracts UI** linked to tickets
4. **Integrate charts** into inventory & financial pages
5. **Create mobile-friendly** notification dashboard
6. **Add batch operations** for inventory management
7. **Create admin settings** for margin configuration

---

**Last Updated**: April 27, 2026
**System Version**: Pro 2.0
**Database**: electroserve_db
