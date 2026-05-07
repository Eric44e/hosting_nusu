# 🚀 ELECTROSERVE PRO - QUICK START GUIDE

## 📍 QUICK NAVIGATION

### Core Files & Locations

| Feature | File | Access |
|---------|------|--------|
| **Pro Dashboard** | `dashboard_pro.php` | [dashboard_pro.php](/dashboard_pro.php) |
| **Pricing Module** | `modules/PricingEngine.php` | `getModule('pricing')` |
| **Inventory** | `modules/InventoryManager.php` | `getModule('inventory')` |
| **Customers** | `modules/CustomerManager.php` | `getModule('customer')` |
| **Tickets** | `modules/TicketManager.php` | `getModule('ticket')` |
| **Technicians** | `modules/TechnicianManager.php` | `getModule('technician')` |
| **Financial** | `modules/FinancialManager.php` | `getModule('financial')` |
| **Contracts** | `modules/ContractManager.php` | `getModule('contract')` |
| **Reports** | `modules/ReportGenerator.php` | `getModule('report')` |
| **AI Insights** | `modules/AIAnalysisEngine.php` | `getModule('ai')` |
| **Notifications** | `modules/NotificationManager.php` | `getModule('notification')` |

---

## 🔑 KEY FEATURES AT A GLANCE

### Inventory Management
```php
// Get items in subcategory
$inventory = getModule('inventory');
$items = $inventory->getItemsBySubcategory($subCategoryId);

// Get low stock items
$lowStock = $inventory->getLowStockItems(50);

// Get fast-moving items
$fastMoving = $inventory->getFastMovingItems(30, 20);
```

### Customer Management
```php
// Create customer
$customer = getModule('customer');
$customerId = $customer->createCustomer([
    'full_name' => 'John Doe',
    'phone' => '+250781234567',
    'address' => '123 Main St'
]);

// Search customer
$results = $customer->searchCustomers('John');

// Get customer stats
$stats = $customer->getCustomerWithStats($customerId);
```

### Ticket Management
```php
// Create ticket
$ticket = getModule('ticket');
$ticketId = $ticket->createTicket([
    'client_id' => 8,
    'title' => 'Electrical Work',
    'priority' => 'high'
], $userId);

// Add material to ticket
$ticket->addTicketItem($ticketId, $itemId, 5);

// Update status
$ticket->updateTicketStatus($ticketId, 'completed', $userId);
```

### Financial Tracking
```php
// Get summary
$financial = getModule('financial');
$summary = $financial->getFinancialSummary();
// Returns: revenue, expenses, profit, profit_percent

// Get revenue by category
$byCategory = $financial->getRevenueByCategory();
```

### AI Insights
```php
// Generate insights
$ai = getModule('ai');
$insights = $ai->generateInsights();
// Returns array of insights with title, text, value

// Get latest stored insights
$latest = $ai->getLatestInsights(10);
```

---

## 🎨 FRONTEND SNIPPETS

### Add Customer Modal to Any Page
```html
<!-- Add button -->
<button onclick="openCustomerModal()" class="btn btn-primary">
    <i class="fas fa-plus"></i> New Customer
</button>

<!-- Include modal -->
<?php include 'includes/customer_modal.php'; ?>

<!-- Listen for customer created -->
<script>
window.addEventListener('customerCreated', (e) => {
    const customer = e.detail;
    console.log('New customer:', customer);
    // Update your UI
});
</script>
```

### Calculate Selling Price
```html
<script>
async function calculatePrice() {
    const cost = 100;
    const margin = 20;
    
    const form = new FormData();
    form.append('cost_price', cost);
    form.append('margin', margin);
    
    const res = await fetch('api/inventory.php?action=calculate_price', {
        method: 'POST',
        body: form
    });
    const data = await res.json();
    console.log('Selling price:', data.selling_price); // 120
}
</script>
```

### Display Dynamic Subcategory Filter
```html
<select id="categorySelect" onchange="loadSubcategories()">
    <option value="">Select Category...</option>
    <?php foreach ($categories as $cat): ?>
    <option value="<?= $cat['id'] ?>"><?= $cat['name'] ?></option>
    <?php endforeach; ?>
</select>

<select id="subcategorySelect">
    <option value="">Select Subcategory...</option>
</select>

<script>
async function loadSubcategories() {
    const categoryId = document.getElementById('categorySelect').value;
    if (!categoryId) return;
    
    const res = await fetch(`api/inventory.php?action=get_subcategories&category_id=${categoryId}`);
    const data = await res.json();
    
    const select = document.getElementById('subcategorySelect');
    select.innerHTML = '<option value="">Select Subcategory...</option>';
    
    data.data.forEach(sub => {
        const opt = document.createElement('option');
        opt.value = sub.id;
        opt.textContent = `${sub.name} (Margin: ${sub.profit_margin}%)`;
        select.appendChild(opt);
    });
}
</script>
```

---

## 📊 COMMON WORKFLOWS

### Workflow 1: Create Ticket with Items
```php
$ticket = getModule('ticket');
$pricing = getModule('pricing');

// 1. Create ticket
$ticketId = $ticket->createTicket([
    'client_id' => $customerId,
    'title' => 'Electrical Installation',
    'priority' => 'high'
], $userId);

// 2. Add materials
$ticket->addTicketItem($ticketId, $itemId1, 10); // 10 units
$ticket->addTicketItem($ticketId, $itemId2, 5);  // 5 units

// 3. Assign to technician
$ticket->assignTicket($ticketId, $technicianId, $userId);

// 4. Technician updates status
$ticket->updateTicketStatus($ticketId, 'ongoing', $technicianId);
$ticket->updateTicketStatus($ticketId, 'completed', $technicianId);
```

### Workflow 2: Generate Financial Report
```php
$report = getModule('report');

// Get monthly revenue vs expenses
$data = $report->getRevenueVsExpensesReport('monthly', 2024);

// Get inventory usage
$usage = $report->getInventoryUsageReport(
    date('Y-m-01'),
    date('Y-m-t')
);

// Get technician performance
$perf = $report->getPerformanceMetricsReport(
    date('Y-m-01'),
    date('Y-m-t')
);
```

### Workflow 3: Get AI Insights
```php
$ai = getModule('ai');

// Generate fresh insights
$insights = $ai->generateInsights();

// Store them
foreach ($insights as $insight) {
    $ai->storeInsight(
        $insight['type'],
        $insight['title'],
        $insight['insight_text'],
        $insight['insight_value'] ?? null,
        $insight['insight_percent'] ?? null
    );
}

// Use in dashboard
foreach ($insights as $insight) {
    echo $insight['title'] . ": " . $insight['insight_text'];
}
```

---

## 🔌 API QUICK CALLS

### Inventory API
```bash
# Get subcategories
GET api/inventory.php?action=get_subcategories&category_id=1

# Calculate price
POST api/inventory.php?action=calculate_price
  cost_price=100&margin=20

# Get low stock
GET api/inventory.php?action=get_low_stock

# Get fast-moving
GET api/inventory.php?action=get_fast_moving
```

### Customers API
```bash
# Create
POST api/customers.php?action=create
  full_name=John&phone=+250781234567&address=Main%20St

# Search
GET api/customers.php?action=search?q=John

# Get with stats
GET api/customers.php?action=get&id=8

# List
GET api/customers.php?action=list&page=1&search=John
```

### Tickets API
```bash
# Create
POST api/tickets.php?action=create
  client_id=8&title=Work&priority=high

# Get details
GET api/tickets.php?action=get&id=100

# Self-assign
POST api/tickets.php?action=self_assign
  ticket_id=100

# Add item
POST api/tickets.php?action=add_item
  ticket_id=100&item_id=1&quantity=5

# Update status
POST api/tickets.php?action=update_status
  ticket_id=100&status=completed
```

### Reports API
```bash
# Financial summary
GET api/reports.php?action=financial_summary

# Revenue vs expenses
GET api/reports.php?action=revenue_vs_expenses?period=monthly&year=2024

# Insights
GET api/reports.php?action=insights
```

### Notifications API
```bash
# Get unread
GET api/notifications.php?action=get_unread&limit=50

# Send message
POST api/notifications.php?action=send_message
  receiver_id=5&message=Hello

# Get chat
GET api/notifications.php?action=get_chat&user_id=5
```

---

## 💡 PRO TIPS

### Tip 1: Load Multiple Modules
```php
$inventory = getModule('inventory');
$pricing = getModule('pricing');
$ticket = getModule('ticket');
```

### Tip 2: Error Handling
```php
try {
    $customer = getModule('customer');
    $id = $customer->createCustomer($data);
    if (!$id) {
        throw new Exception('Failed to create customer');
    }
} catch (Exception $e) {
    error_log($e->getMessage());
    jsonResponse(false, 'Error: ' . $e->getMessage());
}
```

### Tip 3: Batch Operations
```php
// Create multiple items at once
$inventory = getModule('inventory');
foreach ($items as $item) {
    // Update stock
    $inventory->updateStock($item['id'], 10, 'in', 'PO-001');
}
```

### Tip 4: Use Transactions
```php
// Create PO with items
try {
    $pdo->beginTransaction();
    $poId = $inventory->createPurchaseOrder($supplierId, $items, $userId);
    // ... do more work
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
}
```

---

## 🐛 DEBUGGING

### Enable Debug Mode
```php
// In config.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

### Check Module Loading
```php
try {
    $module = getModule('pricing');
    echo "Module loaded successfully";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

### Test API
```bash
# Test inventory API
curl "http://localhost/electroserve/api/inventory.php?action=get_low_stock"

# Test with auth
# (Make sure you're logged in via session)
```

---

## 📚 DOCUMENTATION FILES

- **COMPLETION_SUMMARY.md** - What was built
- **IMPLEMENTATION_GUIDE.md** - How to use features
- **API_REFERENCE.md** - Complete API docs
- **This file** - Quick reference

---

## 🎯 NEXT INTEGRATION TASKS

1. Update existing `ticket_new.php` to use `TicketManager`
2. Update `tickets.php` to use `TicketManager`
3. Add reports page using `ReportGenerator`
4. Integrate charts into inventory & financial pages
5. Add contract management UI
6. Create admin settings page for margins

---

## 📞 NEED HELP?

1. Check **API_REFERENCE.md** for endpoint documentation
2. Check **IMPLEMENTATION_GUIDE.md** for feature details
3. Review module class files for method signatures
4. Check code comments for examples
5. Test APIs using provided CURL examples

---

**Last Updated**: April 27, 2026  
**Version**: 2.0 Pro  
**Status**: ✅ Complete & Ready to Integrate
