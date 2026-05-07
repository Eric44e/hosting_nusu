<?php
// ============================================================
// ElectroServe ERP - Database Configuration
// ============================================================

// Database Settings
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'electroserve_db');

// App Settings
define('APP_NAME', 'ElectroServe ERP');
define('APP_VERSION', '1.0.0');
define('CURRENCY', 'RWF');
define('CURRENCY_SYMBOL', 'FRW');

// Company Information
define('COMPANY_NAME', 'ElectroServe Ltd');
define('COMPANY_PHONE', '+250 788 309 827');
define('COMPANY_EMAIL', 'niyitangaeric77@gmail.com');
define('COMPANY_ADDRESS', 'Kigali, Rwanda');
define('COMPANY_WEBSITE', 'www.electroserve.rw');
define('PRIMARY_COLOR', '#FF7A00');
define('LOGO_PATH', 'assets/img/logo.png');

// Timezone (Rwanda)
date_default_timezone_set('Africa/Kigali');

// ============================================================
// Database Connection (PDO)
// ============================================================

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+02:00'"
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    die(json_encode([
        'success' => false,
        'message' => 'Database connection failed.'
    ]));
}

// ============================================================
// Session
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================================
// Helper Functions
// ============================================================

function isLoggedIn(): bool {
    return isset($_SESSION['staff_id']) && !empty($_SESSION['staff_id']);
}

function isAjax(): bool {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        if (isAjax()) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Session expired. Please login.',
                'redirect' => 'login.php'
            ]);
            exit;
        }
        header('Location: login.php');
        exit;
    }
}

function hasRole(string ...$roles): bool {
    return isset($_SESSION['role']) && in_array($_SESSION['role'], $roles);
}

function currentUser(): array {
    return $_SESSION['user'] ?? [];
}

function jsonResponse(bool $success, string $message, array $data = []): void {
    header('Content-Type: application/json');
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $data));
    exit;
}

// ============================================================
// MODULE AUTOLOADER
// ============================================================

function getModule($moduleName) {
    $modules = [
        'pricing' => 'PricingEngine',
        'inventory' => 'InventoryManager',
        'customer' => 'CustomerManager',
        'ticket' => 'TicketManager',
        'technician' => 'TechnicianManager',
        'financial' => 'FinancialManager',
        'contract' => 'ContractManager',
        'report' => 'ReportGenerator',
        'ai' => 'AIAnalysisEngine',
        'notification' => 'NotificationManager'
    ];
    
    if (!isset($modules[$moduleName])) {
        throw new Exception("Module $moduleName not found");
    }
    
    $className = $modules[$moduleName];
    $filePath = __DIR__ . "/modules/$className.php";
    
    if (!file_exists($filePath)) {
        throw new Exception("Module file not found: $filePath");
    }
    
    require_once $filePath;
    return new $className($GLOBALS['pdo']);
}

// ============================================================
// Utility Functions
// ============================================================

function generateCode(string $prefix, string $table, string $column, int $pad = 4): string {
    global $pdo;

    $maxRetries = 5;
    for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
        try {
            $stmt = $pdo->query("SELECT MAX($column) as max_code FROM `$table`");
            $max = $stmt->fetchColumn();
            $num = $max ? (int) filter_var($max, FILTER_SANITIZE_NUMBER_INT) + 1 : 1;
            $code = $prefix . str_pad($num, $pad, '0', STR_PAD_LEFT);
            
            // Verify the code doesn't exist (for safety)
            $check = $pdo->prepare("SELECT 1 FROM `$table` WHERE `$column` = ?");
            $check->execute([$code]);
            if (!$check->fetch()) {
                return $code;
            }
        } catch (Exception $e) {
            // If there's an error, retry
            if ($attempt < $maxRetries - 1) {
                usleep(100 * 1000); // Sleep 100ms before retry
                continue;
            }
        }
    }
    
    // Fallback: generate with timestamp suffix for uniqueness
    return $prefix . time() . mt_rand(100, 999);
}

function formatMoney(float $amount): string {
    return CURRENCY_SYMBOL . ' ' . number_format($amount, 0);
}

function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);

    if ($diff < 60) return $diff . 's ago';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';

    return floor($diff / 86400) . 'd ago';
}

function sanitize(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// ============================================================
// UI Helpers
// ============================================================

function statusBadge(string $status): string {
    $map = [
        'pending'   => ['class' => 'badge-warning',  'label' => 'Pending'],
        'assigned'  => ['class' => 'badge-info',     'label' => 'Assigned'],
        'confirmed' => ['class' => 'badge-purple',   'label' => 'Confirmed'],
        'ongoing'   => ['class' => 'badge-primary',  'label' => 'In Progress'],
        'completed' => ['class' => 'badge-success',  'label' => 'Completed'],
        'closed'    => ['class' => 'badge-secondary','label' => 'Closed'],
        'denied'    => ['class' => 'badge-danger',   'label' => 'Denied'],
        'active'    => ['class' => 'badge-success',  'label' => 'Active'],
        'inactive'  => ['class' => 'badge-secondary','label' => 'Inactive'],
        'on_leave'  => ['class' => 'badge-warning',  'label' => 'On Leave'],
        'unpaid'    => ['class' => 'badge-danger',   'label' => 'Unpaid'],
        'partial'   => ['class' => 'badge-warning',  'label' => 'Partial'],
        'paid'      => ['class' => 'badge-success',  'label' => 'Paid'],
        'cancelled' => ['class' => 'badge-secondary','label' => 'Cancelled'],
    ];

    $s = $map[$status] ?? ['class'=>'badge-secondary','label'=>ucfirst($status)];

    if ($status === 'confirmed') {
        return "<span class=\"badge\" style=\"background:rgba(139,92,246,.18);color:#8b5cf6;border:1px solid #8b5cf6\">{$s['label']}</span>";
    }
    return "<span class=\"badge {$s['class']}\">{$s['label']}</span>";
}

function priorityBadge(string $priority): string {
    $map = [
        'low'    => 'badge-info',
        'medium' => 'badge-warning',
        'high'   => 'badge-orange',
        'urgent' => 'badge-danger',
    ];

    $cls = $map[$priority] ?? 'badge-secondary';

    return "<span class=\"badge $cls\">" . ucfirst($priority) . "</span>";
}

// ============================================================
// Role-Based Access Control (RBAC)
// ============================================================

// Define role permissions
function getRolePermissions(string $role): array {
    $permissions = [
        'admin' => [
            'dashboard' => 'full',
            'inventory' => 'full',
            'tickets' => 'full',
            'clients' => 'full',
            'technicians' => 'full',
            'staffing' => 'full',
            'financial' => 'full',
            'reports' => 'full',
            'messaging' => 'full',
            'notifications' => 'full',
            'settings' => 'full',
            'stock_view' => true,
            'stock_edit' => true,
            'stock_delete' => true,
            'reports_view' => true,
            'reports_export' => true,
            'payments_process' => true,
            'users_manage' => true,
        ],
        'logistics' => [
            'dashboard' => 'limited',
            'inventory' => 'full',
            'tickets' => 'view',
            'clients' => 'view',
            'technicians' => 'view',
            'staffing' => 'none',
            'financial' => 'view',
            'reports' => 'inventory',
            'messaging' => 'full',
            'notifications' => 'full',
            'settings' => 'view',
            'stock_view' => true,
            'stock_edit' => true,
            'stock_delete' => false,
            'reports_view' => true,
            'reports_export' => false,
            'payments_process' => false,
            'users_manage' => false,
        ],
        'sales' => [
            'dashboard' => 'limited',
            'inventory' => 'view',
            'tickets' => 'full',
            'clients' => 'full',
            'technicians' => 'view',
            'staffing' => 'none',
            'financial' => 'view',
            'reports' => 'sales',
            'messaging' => 'full',
            'notifications' => 'full',
            'settings' => 'view',
            'stock_view' => true,
            'stock_edit' => false,
            'stock_delete' => false,
            'reports_view' => true,
            'reports_export' => false,
            'payments_process' => true,
            'users_manage' => false,
        ],
        'technician' => [
            'dashboard' => 'limited',
            'inventory' => 'view_own',
            'tickets' => 'assigned',
            'clients' => 'view',
            'technicians' => 'view_own',
            'staffing' => 'none',
            'financial' => 'none',
            'reports' => 'none',
            'messaging' => 'full',
            'notifications' => 'full',
            'settings' => 'view',
            'stock_view' => true,
            'stock_edit' => false,
            'stock_delete' => false,
            'reports_view' => false,
            'reports_export' => false,
            'payments_process' => false,
            'users_manage' => false,
        ],
        'finance' => [
            'dashboard' => 'limited',
            'inventory' => 'view',
            'tickets' => 'view',
            'clients' => 'view',
            'technicians' => 'view',
            'staffing' => 'none',
            'financial' => 'full',
            'reports' => 'financial',
            'messaging' => 'full',
            'notifications' => 'full',
            'settings' => 'view',
            'stock_view' => true,
            'stock_edit' => false,
            'stock_delete' => false,
            'reports_view' => true,
            'reports_export' => true,
            'payments_process' => true,
            'users_manage' => false,
        ],
    ];

    return $permissions[$role] ?? [];
}

// Check if user has specific permission
function hasPermission(string $permission): bool {
    $role = $_SESSION['role'] ?? 'guest';
    $perms = getRolePermissions($role);
    return $perms[$permission] ?? false;
}

// Check if user can access a module
function canAccess(string $module, string $level = 'view'): bool {
    $role = $_SESSION['role'] ?? 'guest';
    $perms = getRolePermissions($role);
    $access = $perms[$module] ?? 'none';
    
    if ($access === 'full') return true;
    if ($access === $level) return true;
    if ($level === 'view' && in_array($access, ['limited', 'view', 'assigned', 'inventory', 'sales', 'financial'])) return true;
    if ($level === 'edit' && $access === 'full') return true;
    
    return false;
}

// Require specific permission or redirect
function requirePermission(string $permission): void {
    if (!hasPermission($permission)) {
        if (isAjax()) {
            jsonResponse(false, 'You do not have permission to perform this action.');
        }
        header('Location: dashboard.php?error=unauthorized');
        exit;
    }
}

// Require specific role
function requireRole(string ...$roles): void {
    if (!hasRole(...$roles)) {
        if (isAjax()) {
            jsonResponse(false, 'You do not have permission to access this resource.');
        }
        header('Location: dashboard.php?error=unauthorized');
        exit;
    }
}

// Get dashboard data based on role
function getRoleBasedDashboardData(): array {
    global $pdo;
    $role = $_SESSION['role'] ?? 'guest';
    $staffId = $_SESSION['staff_id'] ?? 0;
    $data = [];
    
    if ($role === 'admin') {
        // Admin sees all data
        $data = getAdminDashboardData($pdo);
    } elseif ($role === 'logistics') {
        $data = getLogisticsDashboardData($pdo);
    } elseif ($role === 'sales') {
        $data = getSalesDashboardData($pdo);
    } elseif ($role === 'technician') {
        $data = getTechnicianDashboardData($pdo, $staffId);
    } elseif ($role === 'finance') {
        $data = getFinanceDashboardData($pdo);
    }
    
    return $data;
}

function getAdminDashboardData($pdo) {
    $stats = [];
    
    // Tickets
    $row = $pdo->query("
      SELECT
        COUNT(*) total,
        COALESCE(SUM(status='pending'),0) pending,
        COALESCE(SUM(status='assigned'),0) assigned,
        COALESCE(SUM(status='confirmed'),0) confirmed,
        COALESCE(SUM(status='ongoing'),0) ongoing,
        COALESCE(SUM(status='completed'),0) completed,
        COALESCE(SUM(status='closed'),0) closed,
        COALESCE(SUM(status='denied'),0) denied
      FROM tickets
    ")->fetch();
    
    $stats['tickets'] = [
      'total'     => (int)($row['total']     ?? 0),
      'pending'   => (int)($row['pending']   ?? 0),
      'assigned'  => (int)($row['assigned']  ?? 0),
      'confirmed' => (int)($row['confirmed'] ?? 0),
      'ongoing'   => (int)($row['ongoing']   ?? 0),
      'completed' => (int)($row['completed'] ?? 0),
      'closed'    => (int)($row['closed']    ?? 0),
      'denied'    => (int)($row['denied']    ?? 0),
    ];
    
    // Revenue
    $revRow = $pdo->query("
      SELECT COALESCE(SUM(amount),0) revenue
      FROM transactions WHERE type='income'
      AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())
    ")->fetch();
    $stats['revenue'] = (float)($revRow['revenue'] ?? 0);
    
    // Expenses
    $expRow = $pdo->query("
      SELECT COALESCE(SUM(amount),0) total FROM expenses
      WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())
    ")->fetch();
    $stats['expenses'] = (float)($expRow['total'] ?? 0);
    
    // Stock
    $stockRow = $pdo->query("
      SELECT
        COUNT(*) total_items,
        COALESCE(SUM(quantity),0) total_qty,
        COALESCE(SUM(quantity>0 AND quantity<=min_quantity),0) low_stock,
        COALESCE(SUM(quantity=0),0) out_stock
      FROM items WHERE status='active'
    ")->fetch();
    $stats['stock'] = [
      'total_items' => (int)($stockRow['total_items'] ?? 0),
      'total_qty' => (int)($stockRow['total_qty'] ?? 0),
      'low_stock' => (int)($stockRow['low_stock'] ?? 0),
      'out_stock' => (int)($stockRow['out_stock'] ?? 0),
    ];
    
    // Clients
    $clientRow = $pdo->query("SELECT COUNT(*) total FROM clients WHERE status='active'")->fetch();
    $stats['clients'] = (int)($clientRow['total'] ?? 0);
    
    // Technicians
    $techRow = $pdo->query("SELECT COUNT(*) total, COALESCE(SUM(status='active'),0) active FROM technicians")->fetch();
    $stats['technicians'] = [
      'total' => (int)($techRow['total'] ?? 0),
      'active' => (int)($techRow['active'] ?? 0),
    ];
    
    // Invoices
    $invRow = $pdo->query("
      SELECT COUNT(*) total,
        COALESCE(SUM(status='unpaid'),0) unpaid,
        COALESCE(SUM(status='partial'),0) partial,
        COALESCE(SUM(status='paid'),0) paid
      FROM invoices
    ")->fetch();
    $stats['invoices'] = [
      'total' => (int)($invRow['total'] ?? 0),
      'unpaid' => (int)($invRow['unpaid'] ?? 0),
      'partial' => (int)($invRow['partial'] ?? 0),
      'paid' => (int)($invRow['paid'] ?? 0),
    ];
    
    // Profit
    $stats['profit'] = $stats['revenue'] - $stats['expenses'];
    
    return $stats;
}

function getLogisticsDashboardData($pdo) {
    $stats = [];
    
    // Stock overview
    $stockRow = $pdo->query("
      SELECT
        COUNT(*) total_items,
        COALESCE(SUM(quantity),0) total_qty,
        COALESCE(SUM(quantity>0 AND quantity<=min_quantity),0) low_stock,
        COALESCE(SUM(quantity=0),0) out_stock,
        COALESCE(SUM(cost_price * quantity),0) stock_value
      FROM items WHERE status='active'
    ")->fetch();
    $stats['stock'] = [
      'total_items' => (int)($stockRow['total_items'] ?? 0),
      'total_qty' => (int)($stockRow['total_qty'] ?? 0),
      'low_stock' => (int)($stockRow['low_stock'] ?? 0),
      'out_stock' => (int)($stockRow['out_stock'] ?? 0),
      'stock_value' => (float)($stockRow['stock_value'] ?? 0),
    ];
    
    // Pending purchase orders
    $poRow = $pdo->query("
      SELECT COUNT(*) total, COALESCE(SUM(total_amount),0) total_value
      FROM purchase_orders WHERE status='pending'
    ")->fetch();
    $stats['purchase_orders'] = [
      'pending' => (int)($poRow['total'] ?? 0),
      'total_value' => (float)($poRow['total_value'] ?? 0),
    ];
    
    // Recent stock movements
    $movements = $pdo->query("
      SELECT sm.*, i.name item_name, s.full_name staff_name
      FROM stock_movements sm
      LEFT JOIN items i ON i.id=sm.item_id
      LEFT JOIN staff s ON s.id=sm.staff_id
      ORDER BY sm.created_at DESC LIMIT 10
    ")->fetchAll();
    $stats['recent_movements'] = $movements;
    
    // Low stock items
    $lowStock = $pdo->query("
      SELECT * FROM items
      WHERE status='active' AND quantity <= min_quantity
      ORDER BY quantity ASC LIMIT 10
    ")->fetchAll();
    $stats['low_stock_items'] = $lowStock;
    
    return $stats;
}

function getSalesDashboardData($pdo) {
    $stats = [];
    
    // Tickets created by this user
    $ticketsRow = $pdo->query("
      SELECT
        COUNT(*) total,
        COALESCE(SUM(status='pending'),0) pending,
        COALESCE(SUM(status='ongoing'),0) ongoing,
        COALESCE(SUM(status='completed'),0) completed
      FROM tickets
    ")->fetch();
    $stats['tickets'] = [
      'total' => (int)($ticketsRow['total'] ?? 0),
      'pending' => (int)($ticketsRow['pending'] ?? 0),
      'ongoing' => (int)($ticketsRow['ongoing'] ?? 0),
      'completed' => (int)($ticketsRow['completed'] ?? 0),
    ];
    
    // Clients
    $clientRow = $pdo->query("SELECT COUNT(*) total FROM clients WHERE status='active'")->fetch();
    $stats['clients'] = (int)($clientRow['total'] ?? 0);
    
    // Revenue this month
    $revRow = $pdo->query("
      SELECT COALESCE(SUM(amount),0) revenue
      FROM transactions WHERE type='income'
      AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())
    ")->fetch();
    $stats['revenue'] = (float)($revRow['revenue'] ?? 0);
    
    // Invoices
    $invRow = $pdo->query("
      SELECT COUNT(*) total, COALESCE(SUM(total_amount),0) total_value,
        COALESCE(SUM(balance),0) receivable
      FROM invoices WHERE status IN ('unpaid','partial')
    ")->fetch();
    $stats['invoices'] = [
      'total' => (int)($invRow['total'] ?? 0),
      'total_value' => (float)($invRow['total_value'] ?? 0),
      'receivable' => (float)($invRow['receivable'] ?? 0),
    ];
    
    return $stats;
}

function getTechnicianDashboardData($pdo, $staffId) {
    $stats = [];
    
    // Get technician ID
    $techRow = $pdo->prepare("SELECT id FROM technicians WHERE staff_id = ?");
    $techRow->execute([$staffId]);
    $techId = $techRow->fetchColumn();
    
    if ($techId) {
        // Assigned tickets
        $ticketsRow = $pdo->prepare("
          SELECT
            COUNT(*) total,
            COALESCE(SUM(status='pending'),0) pending,
            COALESCE(SUM(status='ongoing'),0) ongoing,
            COALESCE(SUM(status='completed'),0) completed,
            COALESCE(SUM(status='closed'),0) closed
          FROM tickets WHERE technician_id = ?
        ");
        $ticketsRow->execute([$techId]);
        $row = $ticketsRow->fetch();
        
        $stats['tickets'] = [
          'total' => (int)($row['total'] ?? 0),
          'pending' => (int)($row['pending'] ?? 0),
          'ongoing' => (int)($row['ongoing'] ?? 0),
          'completed' => (int)($row['completed'] ?? 0),
          'closed' => (int)($row['closed'] ?? 0),
        ];
        
        // Performance rating
        $ratingRow = $pdo->prepare("SELECT rating, total_jobs FROM technicians WHERE id = ?");
        $ratingRow->execute([$techId]);
        $rating = $ratingRow->fetch();
        $stats['performance'] = [
          'rating' => (float)($rating['rating'] ?? 0),
          'total_jobs' => (int)($rating['total_jobs'] ?? 0),
        ];
        
        // My assigned tickets
        $myTickets = $pdo->prepare("
          SELECT t.*, c.full_name client_name, st.name service_name
          FROM tickets t
          LEFT JOIN clients c ON c.id=t.client_id
          LEFT JOIN service_types st ON st.id=t.service_type_id
          WHERE t.technician_id = ?
          ORDER BY t.created_at DESC LIMIT 10
        ");
        $myTickets->execute([$techId]);
        $stats['my_tickets'] = $myTickets->fetchAll();
    } else {
        $stats['tickets'] = ['total'=>0,'pending'=>0,'ongoing'=>0,'completed'=>0,'closed'=>0];
        $stats['performance'] = ['rating'=>0,'total_jobs'=>0];
        $stats['my_tickets'] = [];
    }
    
    return $stats;
}

function getFinanceDashboardData($pdo) {
    $stats = [];
    
    // Revenue
    $revRow = $pdo->query("
      SELECT COALESCE(SUM(amount),0) revenue
      FROM transactions WHERE type='income'
      AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())
    ")->fetch();
    $stats['revenue'] = (float)($revRow['revenue'] ?? 0);
    
    // Expenses
    $expRow = $pdo->query("
      SELECT COALESCE(SUM(amount),0) total FROM expenses
      WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())
    ")->fetch();
    $stats['expenses'] = (float)($expRow['total'] ?? 0);
    
    // Profit
    $stats['profit'] = $stats['revenue'] - $stats['expenses'];
    
    // Receivables
    $recRow = $pdo->query("
      SELECT COALESCE(SUM(balance),0) receivable FROM invoices WHERE status IN ('unpaid','partial')
    ")->fetch();
    $stats['receivable'] = (float)($recRow['receivable'] ?? 0);
    
    // Invoices
    $invRow = $pdo->query("
      SELECT COUNT(*) total,
        COALESCE(SUM(status='unpaid'),0) unpaid,
        COALESCE(SUM(status='partial'),0) partial,
        COALESCE(SUM(status='paid'),0) paid
      FROM invoices
    ")->fetch();
    $stats['invoices'] = [
      'total' => (int)($invRow['total'] ?? 0),
      'unpaid' => (int)($invRow['unpaid'] ?? 0),
      'partial' => (int)($invRow['partial'] ?? 0),
      'paid' => (int)($invRow['paid'] ?? 0),
    ];
    
    // Recent transactions
    $transactions = $pdo->query("
      SELECT t.*, i.invoice_number, s.full_name staff_name
      FROM transactions t
      LEFT JOIN invoices i ON i.id=t.invoice_id
      LEFT JOIN staff s ON s.id=t.staff_id
      ORDER BY t.created_at DESC LIMIT 10
    ")->fetchAll();
    $stats['recent_transactions'] = $transactions;
    
    return $stats;
}