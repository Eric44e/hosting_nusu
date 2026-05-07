# ElectroServe ERP System

A full-featured Enterprise Resource Planning system for electrical & technical service companies.

## Tech Stack
- **Backend**: PHP 8+ (PDO, sessions, AJAX)
- **Database**: MySQL 8+
- **Frontend**: HTML5, CSS3 (custom design system), Vanilla JS
- **UI Library**: SweetAlert2 (modals & alerts)
- **Icons**: Font Awesome 6

## Installation

### Requirements
- PHP 8.0+
- MySQL 8.0+
- Apache with `mod_rewrite` enabled (or Nginx)

### Steps

1. **Copy files** to your web server root (e.g. `/var/www/html/electroserve` or `htdocs/electroserve`)

2. **Create database** and import the schema:
   ```sql
   mysql -u root -p < electroserve.sql
   ```

3. **Edit `config.php`** with your database credentials:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'your_db_user');
   define('DB_PASS', 'your_db_password');
   define('DB_NAME', 'electroserve_db');
   ```

4. **Visit** `http://localhost/electroserve/login.php`

5. **Login** with:
   - Email: `admin@electroserve.com`
   - Password: `password`

## System Modules

| Module | File | Description |
|--------|------|-------------|
| Dashboard | `dashboard.php` | KPI overview, charts, stats |
| Tickets | `tickets.php` | Service ticket management |
| New Ticket | `ticket_new.php` | 3-step wizard to create tickets |
| Ticket Detail | `ticket_view.php` | Full ticket view + materials + costs |
| Clients | `clients.php` | Client database CRUD |
| Inventory | `inventory.php` | Items, stock levels, alerts |
| Categories | `categories.php` | Item categories |
| Stock Movement | `stock.php` | In/out/adjustment history |
| Suppliers | `suppliers.php` | Supplier management |
| Technicians | `technicians.php` | Field team cards |
| Staff | `staff.php` | All staff CRUD + roles |
| Financial | `financial.php` | Transactions, expenses, invoices |
| Reports | `reports.php` | Analytics, charts, KPIs |
| Messages | `messages.php` | Real-time internal chat (polling) |
| Notifications | `notifications.php` | Alert center |
| Settings | `settings.php` | Profile + password change |

## Default Users (all password: `password`)

| Name | Email | Role |
|------|-------|------|
| Admin User | admin@electroserve.com | admin |
| John Doe | johndoe@electroserve.com | technician |
| Mike Brown | mike@electroserve.com | technician |
| Alex Turner | alex@electroserve.com | technician |
| Emma Clark | emma@electroserve.com | sales |
| Tom Harris | tom@electroserve.com | finance |
| Lisa Scott | lisa@electroserve.com | logistics |

## Key Features

- **No page reload** — all CRUD via AJAX + SweetAlert2 feedback
- **Dark mode UI** — professional dark theme throughout
- **Ticket workflow** — Pending → Assigned → Ongoing → Completed → Closed
- **Auto cost calculation** — service + labor + materials + profit %
- **Invoice generation** — from completed tickets
- **Live chat** — internal messaging with 4s poll
- **Stock alerts** — low stock warnings on dashboard
- **Role-based** — admin, sales, technician, finance, logistics

## Security Notes
- All inputs sanitized via `sanitize()` helper
- PDO prepared statements throughout (SQL injection safe)
- Session-based authentication
- `.htaccess` protects `config.php`
- Password hashing with `PASSWORD_BCRYPT`
