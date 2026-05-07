# ElectroServe API Reference

## BASE ENDPOINT
All API calls use: `http://yourserver.com/api/[endpoint].php?action=[action]`

## AUTHENTICATION
- All endpoints require `requireLogin()` - user must be logged in
- Session-based authentication via `$_SESSION['staff_id']` and `$_SESSION['role']`

---

## 🛒 INVENTORY API (`/api/inventory.php`)

### 1. Get Subcategories for Category
```
GET: /api/inventory.php?action=get_subcategories&category_id=1

Response:
{
  "success": true,
  "message": "Success",
  "data": [
    {
      "id": 1,
      "name": "Cables & Wires",
      "profit_margin": "20.00"
    },
    ...
  ]
}
```

### 2. Get Items by Subcategory
```
GET: /api/inventory.php?action=get_items_by_category&subcategory_id=1

Response:
{
  "success": true,
  "data": [
    {
      "id": 1,
      "item_code": "ITM-001",
      "name": "2.5mm Copper Cable",
      "cost_price": "1.20",
      "selling_price": "2.50",
      "quantity": 450,
      "profit_margin": "20.00",
      "subcategory_name": "Cables & Wires",
      "stock_status": "ok"
    },
    ...
  ]
}
```

### 3. Get Low Stock Items
```
GET: /api/inventory.php?action=get_low_stock

Response:
{
  "success": true,
  "data": [
    {
      "id": 5,
      "name": "3KVA Generator",
      "quantity": 2,
      "min_quantity": 3,
      "shortage": 1,
      "coverage_ratio": 0.67
    },
    ...
  ]
}
```

### 4. Get Fast-Moving Items
```
GET: /api/inventory.php?action=get_fast_moving

Response:
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "2.5mm Copper Cable",
      "total_used": 120,
      "movement_count": 45
    },
    ...
  ]
}
```

### 5. Calculate Selling Price
```
POST: /api/inventory.php?action=calculate_price

POST Data:
  cost_price=100
  margin=20

Response:
{
  "success": true,
  "selling_price": 120,
  "profit": 20,
  "margin_percent": 20
}
```

### 6. Get All Margins
```
GET: /api/inventory.php?action=get_all_margins

Response:
{
  "success": true,
  "data": [
    {
      "id": 2,
      "name": "Generators",
      "profit_margin": "30.00"
    },
    ...
  ]
}
```

---

## 👥 CUSTOMERS API (`/api/customers.php`)

### 1. Create Customer
```
POST: /api/customers.php?action=create

POST Data (form-encoded):
  full_name=John Doe (required)
  phone=+250781234567 (optional)
  email=john@example.com (optional)
  address=123 Main St (optional)
  city=Kigali (optional)

Response:
{
  "success": true,
  "message": "Customer created successfully",
  "customer": {
    "id": 8,
    "client_code": "C-1008",
    "full_name": "John Doe",
    "email": "john@example.com",
    "phone": "+250781234567",
    "address": "123 Main St",
    "city": "Kigali",
    "status": "active",
    "created_at": "2024-04-27 10:30:00"
  }
}
```

### 2. Get Customer with Stats
```
GET: /api/customers.php?action=get&id=8

Response:
{
  "success": true,
  "customer": {
    "id": 8,
    "full_name": "John Doe",
    "email": "john@example.com",
    "phone": "+250781234567",
    "total_tickets": 5,
    "completed_tickets": 4,
    "total_spent": 2500.00,
    "last_ticket": {
      "id": 25,
      "ticket_number": "TK-01025",
      "status": "completed",
      "created_at": "2024-04-20 14:00:00"
    }
  }
}
```

### 3. List Customers
```
GET: /api/customers.php?action=list&page=1&search=John

Response:
{
  "success": true,
  "customers": [
    {
      "id": 8,
      "full_name": "John Doe",
      "email": "john@example.com",
      "phone": "+250781234567",
      "status": "active"
    },
    ...
  ],
  "total": 25,
  "page": 1,
  "pages": 1
}
```

### 4. Search Customers
```
GET: /api/customers.php?action=search&q=John

Response:
{
  "success": true,
  "customers": [
    {
      "id": 8,
      "full_name": "John Doe",
      "email": "john@example.com",
      "phone": "+250781234567"
    },
    ...
  ]
}
```

### 5. Update Customer
```
POST: /api/customers.php?action=update

POST Data:
  id=8
  full_name=John Doe Smith
  email=john.smith@example.com
  phone=+250781234568

Response:
{
  "success": true,
  "message": "Customer updated successfully",
  "customer": { ... }
}
```

---

## 🎫 TICKETS API (`/api/tickets.php`)

### 1. Create Ticket
```
POST: /api/tickets.php?action=create

POST Data:
  client_id=8 (required)
  service_type_id=1 (optional)
  title=Electrical Installation (required)
  description=Main panel installation...
  priority=high (low, medium, high, urgent)
  location=Kigali Downtown

Response:
{
  "success": true,
  "message": "Ticket created successfully",
  "ticket": {
    "id": 100,
    "ticket_number": "TK-01100",
    "client_id": 8,
    "client_name": "John Doe",
    "title": "Electrical Installation",
    "status": "pending",
    "priority": "high",
    "created_at": "2024-04-27 10:30:00"
  }
}
```

### 2. Get Ticket with Details
```
GET: /api/tickets.php?action=get&id=100

Response:
{
  "success": true,
  "ticket": {
    "id": 100,
    "ticket_number": "TK-01100",
    "client_name": "John Doe",
    "client_phone": "+250781234567",
    "title": "Electrical Installation",
    "description": "...",
    "status": "pending",
    "priority": "high",
    "total_amount": 1200.00,
    "material_cost": 500.00,
    "labor_cost": 400.00,
    "service_cost": 300.00,
    "items": [
      {
        "item_id": 1,
        "item_name": "Copper Cable",
        "quantity": 50,
        "unit_price": 2.50,
        "total_price": 125.00
      }
    ]
  }
}
```

### 3. Technician Self-Assign Ticket
```
POST: /api/tickets.php?action=self_assign

POST Data:
  ticket_id=100

Response:
{
  "success": true,
  "message": "Ticket assigned to you"
}
```

### 4. Add Item to Ticket
```
POST: /api/tickets.php?action=add_item

POST Data:
  ticket_id=100
  item_id=1
  quantity=5

Response:
{
  "success": true,
  "message": "Item added to ticket"
}
```

### 5. Update Ticket Status
```
POST: /api/tickets.php?action=update_status

POST Data:
  ticket_id=100
  status=completed (pending, assigned, ongoing, completed, closed, denied)
  notes=Job completed successfully

Response:
{
  "success": true,
  "message": "Ticket status updated"
}
```

---

## 📊 REPORTS API (`/api/reports.php`)

### 1. Financial Summary
```
GET: /api/reports.php?action=financial_summary&start_date=2024-04-01&end_date=2024-04-30

Response:
{
  "success": true,
  "summary": {
    "revenue": 50000.00,
    "expenses": 15000.00,
    "profit": 35000.00,
    "profit_percent": 70.00
  }
}
```

### 2. Revenue by Category
```
GET: /api/reports.php?action=revenue_by_category&start_date=2024-04-01&end_date=2024-04-30

Response:
{
  "success": true,
  "data": [
    {
      "category": "service",
      "total": 45000.00,
      "transaction_count": 15
    },
    {
      "category": "product",
      "total": 5000.00,
      "transaction_count": 8
    }
  ]
}
```

### 3. Revenue vs Expenses Report
```
GET: /api/reports.php?action=revenue_vs_expenses&period=monthly&year=2024

Response:
{
  "success": true,
  "data": [
    {
      "month": 1,
      "month_name": "January",
      "revenue": 45000.00,
      "expenses": 12000.00,
      "profit": 33000.00
    },
    ...
  ]
}
```

### 4. AI Insights
```
GET: /api/reports.php?action=insights

Response:
{
  "success": true,
  "insights": [
    {
      "type": "revenue_trend",
      "title": "💰 Revenue Trend",
      "insight_text": "Revenue increased by 15% ↑",
      "insight_value": 50000,
      "insight_percent": 15,
      "priority": "medium"
    },
    ...
  ]
}
```

### 5. Inventory Usage Report
```
GET: /api/reports.php?action=inventory_usage&start_date=2024-04-01&end_date=2024-04-30

Response:
{
  "success": true,
  "data": [
    {
      "item_code": "ITM-001",
      "name": "Copper Cable",
      "used": 150,
      "received": 200,
      "current_stock": 350,
      "inventory_value": 875.00
    },
    ...
  ]
}
```

---

## 🔔 NOTIFICATIONS API (`/api/notifications.php`)

### 1. Get Unread Notifications
```
GET: /api/notifications.php?action=get_unread&limit=50

Response:
{
  "success": true,
  "notifications": [
    {
      "id": 1,
      "type": "ticket",
      "title": "New Ticket Assigned",
      "body": "Ticket #TK-01100 assigned to you",
      "priority": "high",
      "is_read": 0,
      "created_at": "2024-04-27 10:30:00"
    },
    ...
  ]
}
```

### 2. Send Message
```
POST: /api/notifications.php?action=send_message

POST Data:
  receiver_id=5 (OR ticket_id=100)
  message=Great work on that ticket!

Response:
{
  "success": true,
  "message": "Message sent",
  "message_id": 456
}
```

### 3. Get Chat History
```
GET: /api/notifications.php?action=get_chat&user_id=5&limit=100
OR
GET: /api/notifications.php?action=get_chat&ticket_id=100&limit=100

Response:
{
  "success": true,
  "messages": [
    {
      "id": 450,
      "sender_id": 5,
      "sender_name": "John Doe",
      "message": "Starting the installation now",
      "is_read": 1,
      "created_at": "2024-04-27 10:00:00"
    },
    ...
  ]
}
```

### 4. Mark Notification as Read
```
POST: /api/notifications.php?action=mark_read

POST Data:
  notification_id=1

Response:
{
  "success": true,
  "message": "Marked as read"
}
```

---

## ERROR RESPONSES

All failed requests return:
```json
{
  "success": false,
  "message": "Error description"
}
```

Common errors:
- `Session expired. Please login.` - Not authenticated
- `Admin only` - Insufficient permissions
- `[Field] required` - Missing required data
- `Invalid action` - Unknown API action

---

## BEST PRACTICES

1. **Always check `success` field** before using response data
2. **Use POST for mutations** (create, update, delete)
3. **Use GET for queries** (list, get, search)
4. **Include error handling** in your frontend code
5. **Validate permissions** - check role before API call
6. **Cache responses** when appropriate for performance
7. **Rate limit** to prevent abuse

## EXAMPLE: Complete Workflow

```javascript
// 1. Create customer
const customerForm = new FormData();
customerForm.append('full_name', 'Jane Smith');
customerForm.append('phone', '+250781234567');
const customerRes = await fetch('api/customers.php?action=create', {
    method: 'POST',
    body: customerForm
});
const customer = await customerRes.json();
const customerId = customer.customer.id;

// 2. Create ticket for customer
const ticketForm = new FormData();
ticketForm.append('client_id', customerId);
ticketForm.append('title', 'Electrical Work');
ticketForm.append('priority', 'high');
const ticketRes = await fetch('api/tickets.php?action=create', {
    method: 'POST',
    body: ticketForm
});
const ticket = await ticketRes.json();
const ticketId = ticket.ticket.id;

// 3. Add materials to ticket
const itemForm = new FormData();
itemForm.append('ticket_id', ticketId);
itemForm.append('item_id', 1);
itemForm.append('quantity', 5);
await fetch('api/tickets.php?action=add_item', {
    method: 'POST',
    body: itemForm
});

// 4. Get financial report
const report = await fetch('api/reports.php?action=financial_summary');
const data = await report.json();
console.log('Profit:', data.summary.profit);
```

---

**API Version**: 2.0  
**Last Updated**: April 27, 2026
