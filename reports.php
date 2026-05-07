<?php
// =====================================
// REPORT FILTERING (CLEAN + FIXED)
// =====================================

require_once 'config.php';
require_once 'includes/ai_suggestions.php';
require_once 'includes/chart_generator.php';
requireLogin();

$userRole = $_SESSION['role'] ?? 'guest';
$userId   = $_SESSION['staff_id'] ?? 0;

$reportType = $_GET['type'] ?? 'sales';

/* =========================
   ROLE ACCESS
========================= */
$allowedReports = [
    'admin'     => ['sales', 'inventory', 'financial', 'technician'],
    'manager'   => ['sales', 'inventory', 'financial', 'technician'],
    'finance'   => ['financial', 'sales'],
    'logistics' => ['inventory'],
    'sales'     => ['sales'],
    'technician'=> ['technician'],
];

$allowedTypes = $allowedReports[$userRole] ?? [];

if (empty($allowedTypes)) {
    header('Location: dashboard.php');
    exit;
}

if (!in_array($reportType, $allowedTypes)) {
    $reportType = $allowedTypes[0];
}

/* =========================
   DATE FILTER (FIXED CORE)
========================= */
$period = $_GET['period'] ?? 'monthly';

$year  = (int)($_GET['year'] ?? date('Y'));
$month = (int)($_GET['month'] ?? date('m'));
$date  = $_GET['date'] ?? date('Y-m-d');

$dateFilter = "1=1";
$intervalGroup = "DATE(created_at)";
$dateFormat = "'%b %d'";

switch ($period) {

    case 'daily':
        $dateFilter = "DATE(created_at) = '$date'";
        $intervalGroup = "DATE(created_at)";
        $dateFormat = "'%b %d'";
        break;

    case 'monthly':
        $dateFilter = "YEAR(created_at) = $year AND MONTH(created_at) = $month";
        $intervalGroup = "YEAR(created_at), MONTH(created_at)";
        $dateFormat = "'%b %Y'";
        break;

    case 'yearly':
        $dateFilter = "YEAR(created_at) = $year";
        $intervalGroup = "YEAR(created_at)";
        $dateFormat = "'%Y'";
        break;

    case 'custom':
        if (!empty($_GET['start']) && !empty($_GET['end'])) {
            $start = $_GET['start'];
            $end   = $_GET['end'];
            $dateFilter = "DATE(created_at) BETWEEN '$start' AND '$end'";
        }
        break;
}

/* =========================
   AVAILABLE YEARS
========================= */
$availableYears = $pdo->query("
    SELECT DISTINCT YEAR(created_at) as year 
    FROM (
        SELECT created_at FROM transactions
        UNION ALL
        SELECT created_at FROM tickets
        UNION ALL
        SELECT created_at FROM invoices
    ) combined
    WHERE created_at IS NOT NULL
    ORDER BY year DESC
")->fetchAll(PDO::FETCH_COLUMN);

if (empty($availableYears)) {
    $availableYears = [date('Y')];
}

/* =========================
   DATA CONTAINER
========================= */
$reportData = [];

/* =========================
   SALES REPORT
========================= */
if ($reportType === 'sales') {

    $reportData['title'] = 'Sales Report';

    $reportData['revByMonth'] = $pdo->query("
        SELECT DATE_FORMAT(created_at,$dateFormat) mon, SUM(amount) total
        FROM transactions
        WHERE type='income' AND $dateFilter
        GROUP BY $intervalGroup
        ORDER BY created_at
    ")->fetchAll();

    $reportData['ticketsByMonth'] = $pdo->query("
        SELECT DATE_FORMAT(created_at,$dateFormat) mon,
               COUNT(*) total,
               SUM(status='completed') completed
        FROM tickets
        WHERE $dateFilter
        GROUP BY $intervalGroup
        ORDER BY created_at
    ")->fetchAll();

    $reportData['serviceStats'] = $pdo->query("
        SELECT st.name,
               COUNT(t.id) count,
               COALESCE(SUM(t.total_amount),0) revenue
        FROM service_types st
        LEFT JOIN tickets t ON t.service_type_id=st.id
        GROUP BY st.id
        ORDER BY count DESC
    ")->fetchAll();

    $reportData['clientStats'] = $pdo->query("
        SELECT c.full_name,
               COUNT(t.id) ticket_count,
               COALESCE(SUM(t.total_amount),0) total_spent
        FROM clients c
        LEFT JOIN tickets t ON t.client_id=c.id
        GROUP BY c.id
        ORDER BY total_spent DESC
        LIMIT 10
    ")->fetchAll();

    // 🔥 FIXED SUMMARY (NOW FILTERED)
    $reportData['summary'] = [
        'total_tickets' => $pdo->query("SELECT COUNT(*) FROM tickets WHERE $dateFilter")->fetchColumn(),
        'closed_tickets' => $pdo->query("SELECT COUNT(*) FROM tickets WHERE status='closed' AND $dateFilter")->fetchColumn(),
        'denied_tickets' => $pdo->query("SELECT COUNT(*) FROM tickets WHERE status='denied' AND $dateFilter")->fetchColumn(),
        'total_revenue' => $pdo->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type='income' AND $dateFilter")->fetchColumn(),
        'total_clients' => $pdo->query("SELECT COUNT(*) FROM clients WHERE status='active'")->fetchColumn(),
    ];
}

/* =========================
   INVENTORY REPORT
========================= */
if ($reportType === 'inventory') {

    $reportData['stockByCategory'] = $pdo->query("
        SELECT c.name,
               COUNT(i.id) item_count,
               COALESCE(SUM(i.quantity),0) total_qty,
               COALESCE(SUM(i.quantity * i.cost_price),0) stock_value
        FROM categories c
        LEFT JOIN items i ON i.category_id=c.id AND i.status='active'
        GROUP BY c.id
        ORDER BY stock_value DESC
    ")->fetchAll();

    $reportData['lowStock'] = $pdo->query("
        SELECT i.*, c.name category_name
        FROM items i
        LEFT JOIN categories c ON c.id=i.category_id
        WHERE i.status='active' AND i.quantity <= i.min_quantity
        ORDER BY i.quantity ASC
        LIMIT 10
    ")->fetchAll();

    $reportData['summary'] = [
        'total_items' => $pdo->query("SELECT COUNT(*) FROM items WHERE status='active'")->fetchColumn(),
        'total_qty' => $pdo->query("SELECT COALESCE(SUM(quantity),0) FROM items WHERE status='active'")->fetchColumn(),
        'low_stock' => $pdo->query("SELECT COUNT(*) FROM items WHERE status='active' AND quantity <= min_quantity")->fetchColumn(),
        'out_of_stock' => $pdo->query("SELECT COUNT(*) FROM items WHERE status='active' AND quantity = 0")->fetchColumn(),
        'stock_value' => $pdo->query("SELECT COALESCE(SUM(quantity * cost_price),0) FROM items WHERE status='active'")->fetchColumn(),
    ];
}

/* =========================
   FINANCIAL REPORT
========================= */
if ($reportType === 'financial') {

    $reportData['revByMonth'] = $pdo->query("
        SELECT DATE_FORMAT(created_at,$dateFormat) mon, SUM(amount) total
        FROM transactions
        WHERE type='income' AND $dateFilter
        GROUP BY $intervalGroup
        ORDER BY created_at
    ")->fetchAll();

    $reportData['expByCategory'] = $pdo->query("
        SELECT category, COALESCE(SUM(amount),0) total
        FROM expenses
        GROUP BY category
        ORDER BY total DESC
    ")->fetchAll();

    $reportData['invoiceStatus'] = $pdo->query("
        SELECT status,
               COUNT(*) count,
               COALESCE(SUM(total_amount),0) total,
               COALESCE(SUM(balance),0) balance
        FROM invoices
        GROUP BY status
    ")->fetchAll();

    $reportData['summary'] = [
        'total_revenue' => $pdo->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type='income' AND $dateFilter")->fetchColumn(),
        'total_expenses' => $pdo->query("SELECT COALESCE(SUM(amount),0) FROM expenses")->fetchColumn(),
        'outstanding' => $pdo->query("SELECT COALESCE(SUM(balance),0) FROM invoices WHERE status IN ('unpaid','partial')")->fetchColumn(),
        'paid_invoices' => $pdo->query("SELECT COUNT(*) FROM invoices WHERE status='paid'")->fetchColumn(),
    ];

    $reportData['summary']['profit'] =
        $reportData['summary']['total_revenue'] - $reportData['summary']['total_expenses'];
}



// TECHNICIAN REPORT
if ($reportType === 'technician') {
    if ($userRole === 'technician') {
        // Get technician ID
        $techRow = $pdo->prepare("SELECT id FROM technicians WHERE staff_id = ?");
        $techRow->execute([$userId]);
        $techId = $techRow->fetchColumn();
        
        $reportData['title'] = 'My Performance Report';
        $reportData['description'] = 'Your job performance and ratings';
        
        // My stats
        $myStats = $pdo->prepare("
            SELECT tech.*, s.full_name
            FROM technicians tech
            JOIN staff s ON s.id=tech.staff_id
            WHERE tech.id = ?
        ");
        $myStats->execute([$techId]);
        $reportData['myStats'] = $myStats->fetch();
        
        // My tickets
        $myTickets = $pdo->prepare("
            SELECT t.*, c.full_name client_name, st.name service_name
            FROM tickets t
            LEFT JOIN clients c ON c.id=t.client_id
            LEFT JOIN service_types st ON st.id=t.service_type_id
            WHERE t.technician_id = ?
            ORDER BY t.created_at DESC LIMIT 20
        ");
        $myTickets->execute([$techId]);
        $reportData['myTickets'] = $myTickets->fetchAll();
        
        // Performance by period
        $myMonthly = $pdo->prepare("
            SELECT DATE_FORMAT(created_at,$dateFormat) mon, COUNT(*) total,
                   SUM(status='completed') completed
            FROM tickets WHERE technician_id = ?
            AND $dateFilter
            GROUP BY $intervalGroup ORDER BY created_at
        ");
        $myMonthly->execute([$techId]);
        $reportData['myMonthly'] = $myMonthly->fetchAll();
        
    } else {
        $reportData['title'] = 'Technician Report';
        $reportData['description'] = 'Technician performance and job analysis';
        
        // All technicians
        $reportData['topTechs'] = $pdo->query("
            SELECT s.full_name, tech.*,
            COUNT(CASE WHEN t.status='completed' THEN 1 END) completed,
            COUNT(CASE WHEN t.status='ongoing' THEN 1 END) ongoing
            FROM technicians tech
            JOIN staff s ON s.id=tech.staff_id
            LEFT JOIN tickets t ON t.technician_id=tech.id
            GROUP BY tech.id ORDER BY tech.rating DESC, completed DESC LIMIT 10
        ")->fetchAll();
        
        // Performance by period
        $techDateFilter = str_replace('created_at', 't.created_at', $dateFilter);
        $techIntervalGroup = str_replace('created_at', 't.created_at', $intervalGroup);
        $techDateFormat = str_replace('created_at', 't.created_at', $dateFormat);
        
        $reportData['techMonthly'] = $pdo->query("
            SELECT DATE_FORMAT(t.created_at,$techDateFormat) mon, 
                   s.full_name tech_name, COUNT(*) total,
                   SUM(t.status='completed') completed
            FROM tickets t
            JOIN technicians tech ON tech.id=t.technician_id
            JOIN staff s ON s.id=tech.staff_id
            WHERE $techDateFilter
            GROUP BY tech.id, $techIntervalGroup
            ORDER BY t.created_at DESC
        ")->fetchAll();
        
        // Summary with error handling
        $reportData['summary'] = [
            'total_techs' => $pdo->query("SELECT COUNT(*) FROM technicians")->fetchColumn() ?? 0,
            'active_techs' => $pdo->query("SELECT COUNT(*) FROM technicians WHERE status='active'")->fetchColumn() ?? 0,
            'total_jobs' => $pdo->query("SELECT COALESCE(SUM(total_jobs),0) FROM technicians")->fetchColumn() ?? 0,
            'avg_rating' => round((float)($pdo->query("SELECT COALESCE(AVG(rating),0) FROM technicians")->fetchColumn() ?? 0), 2),
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $reportData['title'] ?? 'Reports' ?> — NUSU Management System</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="assets/css/nusu-branding.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
    .fin-tabs { display:flex; border-bottom:1px solid rgba(255,255,255,0.1); margin-bottom:1.4rem; }
    .fin-tab { padding:.7rem 1.4rem; cursor:pointer; font-size:.875rem; font-weight:500; color:rgba(255,255,255,0.6); border-bottom:2px solid transparent; margin-bottom:-1px; transition:all .2s; }
    .fin-tab.active { color:#FF7A00; border-bottom-color:#FF7A00; }
    .card { background: #000 !important; color: #fff !important; border: 1px solid rgba(255,255,255,0.1) !important; box-shadow: 0 10px 30px rgba(0,0,0,0.5) !important; }
    .card-header { border-bottom: 1px solid rgba(255,255,255,0.1) !important; }
    .card-title { color: #FF7A00 !important; font-weight: 700 !important; }
    .table-wrap { border: none !important; background: transparent !important; }
    table th { color: #FF7A00 !important; border-bottom: 1px solid rgba(255,255,255,0.1) !important; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 1px; }
    table td { border-bottom: 1px solid rgba(255,255,255,0.05) !important; color: #fff !important; }
    .stat-card { background: #000 !important; border: 1px solid rgba(255,122,0,0.3) !important; color: #fff !important; padding: 1.5rem; border-radius: 12px; }
    .stat-card h3 { color: #FF7A00 !important; font-size: 0.8rem; margin: 0; text-transform: uppercase; }
    .stat-card .value { font-weight: 800; font-size: 1.2rem; margin-top: 0.5rem; }
    .stat-icon { background: rgba(255,122,0,0.1) !important; color: #FF7A00 !important; }
    .btn-outline { border-color: rgba(255,255,255,0.2) !important; color: #fff !important; }
    .btn-outline:hover { background: #FF7A00 !important; border-color: #FF7A00 !important; }
    .form-control, select, input { background: #111 !important; color: #fff !important; border: 1px solid rgba(255,255,255,0.1) !important; }
    @media print {
        .app-wrapper { position: relative; }
        .app-wrapper::before {
            content: 'NUSU';
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-25deg);
            font-size: 15vw;
            font-weight: 900;
            letter-spacing: 0.2em;
            color: rgba(255, 122, 0, 0.05);
            z-index: 0;
            pointer-events: none;
            user-select: none;
        }
    }
</style>
</head>
<body>
<div class="app-wrapper">
<?php include 'includes/layout.php'; ?>
<main class="main-content fade-in">

  <div class="page-header">
    <div class="page-header-left">
        <h1><?= $reportData['title'] ?? 'Reports & Analytics' ?></h1>
        <p><?= $reportData['description'] ?? 'Business performance insights' ?></p>
    </div>
    <div class="page-actions">
      <button class="btn btn-outline btn-sm" onclick="window.print()"><i class="fas fa-print"></i> Print Report</button>
    </div>
  </div>

  <!-- Report Type Tabs -->
  <div class="doc-type-tabs" style="margin-bottom:1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
      <?php foreach($allowedTypes as $type): ?>
      <a href="reports.php?type=<?= $type ?>&period=<?= $period ?>&year=<?= $year ?>" class="<?= $reportType === $type ? 'active' : '' ?>" 
         style="padding:0.75rem 1.5rem;background:<?= $reportType === $type ? 'var(--primary)' : 'transparent' ?>;border:none;border-radius:10px;color:<?= $reportType === $type ? '#fff' : 'var(--text-muted)' ?>;text-decoration:none;display:inline-flex;align-items:center;gap:0.5rem;font-weight:600;transition:all 0.2s">
          <i class="fas fa-<?= $type === 'sales' ? 'chart-line' : ($type === 'inventory' ? 'boxes' : ($type === 'financial' ? 'wallet' : 'tools')) ?>"></i>
          <?= ucfirst($type) ?> Report
      </a>
      <?php endforeach; ?>
    </div>
    <div style="display: flex; gap: 0.5rem; align-items: center;">
      <select id="yearSelect" class="form-control" style="width:120px;padding:0.5rem 0.75rem" onchange="changeYear(this.value)">
        <?php foreach($availableYears as $y): ?>
        <option value="<?= $y ?>" <?= $year == $y ? 'selected' : '' ?>><?= $y ?></option>
        <?php endforeach; ?>
      </select>
      <a href="reports.php?type=<?= $reportType ?>&period=daily&year=<?= $year ?>" class="btn btn-sm <?= $period==='daily'?'btn-primary':'btn-outline' ?>">Daily</a>
      <a href="reports.php?type=<?= $reportType ?>&period=monthly&year=<?= $year ?>" class="btn btn-sm <?= $period==='monthly'?'btn-primary':'btn-outline' ?>">Monthly</a>
      <a href="reports.php?type=<?= $reportType ?>&period=yearly&year=<?= $year ?>" class="btn btn-sm <?= $period==='yearly'?'btn-primary':'btn-outline' ?>">Yearly</a>

      <?php if ($period === 'monthly'): ?>
        <?php
        // Find months with data for the selected year
        $monthsWithData = [];
        $monthQuery = $pdo->query("SELECT DISTINCT MONTH(created_at) as month FROM (\n"
          . "SELECT created_at FROM transactions WHERE YEAR(created_at) = $year\n"
          . " UNION ALL SELECT created_at FROM tickets WHERE YEAR(created_at) = $year\n"
          . " UNION ALL SELECT created_at FROM invoices WHERE YEAR(created_at) = $year\n"
          . ") combined WHERE created_at IS NOT NULL ORDER BY month");
        foreach($monthQuery->fetchAll(PDO::FETCH_COLUMN) as $m) $monthsWithData[] = (int)$m;
        
        // Get current month selection (if any)
        $selectedMonth = (int)($_GET['month'] ?? date('m'));
        $dateFilterMonth = "MONTH(created_at) = $selectedMonth";
        
        // Update dateFilter to use the selected month
        if (in_array($selectedMonth, $monthsWithData)) {
          $dateFilter = "YEAR(created_at) = $year AND $dateFilterMonth";
        }
        ?>
        <form method="get" style="display:inline-flex;gap:0.5rem;align-items:center;margin-left:0.5rem">
          <input type="hidden" name="type" value="<?= $reportType ?>">
          <input type="hidden" name="period" value="monthly">
          <input type="hidden" name="year" value="<?= $year ?>">
          <input type="month" name="month-year" id="monthPicker" value="<?= $year ?>-<?= str_pad($selectedMonth, 2, '0', STR_PAD_LEFT) ?>" style="padding:0.5rem 0.75rem;border:1px solid rgba(255,255,255,0.1);border-radius:8px;background:#000;color:#fff">
          <button type="submit" class="btn btn-sm btn-primary">View Month</button>
        </form>
        <script>
        document.getElementById('monthPicker').addEventListener('change', function() {
          const [year, month] = this.value.split('-');
          window.location.href = 'reports.php?type=<?= $reportType ?>&period=monthly&year=' + year + '&month=' + month;
        });
        </script>
      <?php elseif ($period === 'daily'): ?>
        <form id="dateForm" method="get" style="display:inline-block;margin-left:0.5rem">
          <input type="hidden" name="type" value="<?= $reportType ?>">
          <input type="hidden" name="period" value="daily">
          <input type="hidden" name="year" value="<?= $year ?>">
          <input type="date" name="date" value="<?= htmlspecialchars($_GET['date'] ?? '') ?>" style="padding:0.3rem 0.5rem">
          <button type="submit" class="btn btn-sm btn-primary" style="margin-left:0.3rem">Apply</button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <script>
  function changeYear(year) {
    window.location.href = 'reports.php?type=<?= $reportType ?>&period=<?= $period ?>&year=' + year;
  }
  </script>

  <!-- KPI Cards based on report type -->
  <div class="stat-cards" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr));margin-bottom:1.8rem">
    <?php 
    if ($reportType === 'sales' && isset($reportData['summary'])) {
        $kpis = [
            ['Total Tickets',$reportData['summary']['total_tickets'],'fas fa-ticket-alt','si-blue'],
            ['Closed Tickets',$reportData['summary']['closed_tickets'],'fas fa-check-circle','si-green'],
            ['Denied Tickets',$reportData['summary']['denied_tickets'],'fas fa-times-circle','si-red'],
            ['Total Revenue','FRW '.number_format($reportData['summary']['total_revenue'],0),'fas fa-franc-sign','si-purple'],
            ['Active Clients',$reportData['summary']['total_clients'],'fas fa-users','si-cyan'],
        ];
    } elseif ($reportType === 'inventory' && isset($reportData['summary'])) {
        $kpis = [
            ['Total Items',$reportData['summary']['total_items'],'fas fa-boxes','si-blue'],
            ['Total Quantity',number_format($reportData['summary']['total_qty']),'fas fa-cubes','si-purple'],
            ['Low Stock',$reportData['summary']['low_stock'],'fas fa-exclamation-triangle','si-orange'],
            ['Out of Stock',$reportData['summary']['out_of_stock'],'fas fa-times-circle','si-red'],
            ['Stock Value','FRW '.number_format($reportData['summary']['stock_value'],0),'fas fa-franc-sign','si-green'],
        ];
    } elseif ($reportType === 'financial' && isset($reportData['summary'])) {
        $kpis = [
            ['Total Revenue','FRW '.number_format($reportData['summary']['total_revenue'],0),'fas fa-arrow-up','si-green'],
            ['Total Expenses','FRW '.number_format($reportData['summary']['total_expenses'],0),'fas fa-arrow-down','si-red'],
            ['Net Profit','FRW '.number_format($reportData['summary']['profit'],0),'fas fa-chart-line','si-cyan'],
            ['Outstanding','FRW '.number_format($reportData['summary']['outstanding'],0),'fas fa-exclamation-circle','si-orange'],
            ['Paid Invoices',$reportData['summary']['paid_invoices'],'fas fa-check-double','si-blue'],
        ];
    } elseif ($reportType === 'technician') {
        if ($userRole === 'technician' && isset($reportData['myStats'])) {
            $rating = (float)$reportData['myStats']['rating'];
            $stars = str_repeat('<i class="fas fa-star" style="color:#fbbf24"></i>', floor($rating));
            $stars .= ($rating - floor($rating)) >= 0.5 ? '<i class="fas fa-star-half-alt" style="color:#fbbf24"></i>' : '';
            $kpis = [
                ['Total Jobs',$reportData['myStats']['total_jobs'],'fas fa-tasks','si-blue'],
                ['Rating',number_format($rating,1) . ' ' . $stars,'fas fa-star','si-yellow'],
                ['Status',ucfirst($reportData['myStats']['status']),'fas fa-user-check','si-green'],
                ['Specialization',$reportData['myStats']['specialization'] ?? 'N/A','fas fa-tools','si-purple'],
            ];
        } elseif (isset($reportData['summary'])) {
            $avgRating = number_format($reportData['summary']['avg_rating'],1);
            $stars = str_repeat('<i class="fas fa-star" style="color:#fbbf24"></i>', floor($avgRating));
            $kpis = [
                ['Total Technicians',$reportData['summary']['total_techs'],'fas fa-users','si-blue'],
                ['Active',$reportData['summary']['active_techs'],'fas fa-user-check','si-green'],
                ['Total Jobs Done',$reportData['summary']['total_jobs'],'fas fa-tasks','si-purple'],
                ['Avg Rating',$avgRating . ' ' . $stars,'fas fa-star','si-yellow'],
            ];
        }
    }
    
    if (isset($kpis)) {
        foreach($kpis as [$lbl,$val,$icon,$cls]): ?>
        <div class="stat-card">
            <div class="stat-card-top">
            <div><h3><?= $lbl ?></h3><div class="value" style="font-size:1.1rem"><?= $val ?></div></div>
            <div class="stat-icon <?= $cls ?>"><i class="<?= $icon ?>"></i></div>
            </div>
        </div>
        <?php endforeach; 
    }
    ?>
  </div>

  <!-- AI Suggestions Section -->
  <?php 
  $aiEngine = new AISuggestions($pdo, $reportData);
  $insights = [];
  
  if ($reportType === 'financial') {
    $insights = $aiEngine->getFinancialInsights();
  } elseif ($reportType === 'sales') {
    $insights = $aiEngine->getSalesRecommendations();
  } elseif ($reportType === 'inventory') {
    $insights = $aiEngine->getInventoryRecommendations();
  }
  
  if (!empty($insights)): ?>
  <div class="card" style="margin-bottom:1.8rem">
    <div class="card-header">
      <div class="card-title"><i class="fas fa-lightbulb" style="color:#fbbf24;margin-right:0.5rem"></i>AI Insights & Recommendations</div>
    </div>
    <div class="card-body">
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:1rem">
        <?php foreach ($insights as $insight): ?>
          <div><?= AISuggestions::renderInsightsCard($insight) ?></div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- No Data Found Check -->
  <?php 
  $dataCount = 0;
  if ($reportType === 'sales' && isset($reportData['revByMonth'])) {
    $dataCount = count($reportData['revByMonth']);
  } elseif ($reportType === 'financial' && isset($reportData['revByMonth'])) {
    $dataCount = count($reportData['revByMonth']);
  } elseif ($reportType === 'inventory' && isset($reportData['stockByCategory'])) {
    $dataCount = count($reportData['stockByCategory']);
  }
  
  ?>
  <?php if ($reportType === 'sales'): ?>
  <div class="grid-2" style="margin-bottom:1.2rem">
    <div class="card">
      <div class="card-header"><div class="card-title">Revenue (<?= ucfirst($period) ?>)</div></div>
      <div class="card-body" style="position:relative;height:300px"><canvas id="revMonthChart"></canvas></div>
    </div>
    <div class="card">
      <div class="card-header"><div class="card-title">Ticket Volume (<?= ucfirst($period) ?>)</div></div>
      <div class="card-body" style="position:relative;height:300px"><canvas id="ticketMonthChart"></canvas></div>
    </div>
  </div>

  <div class="grid-2" style="margin-bottom:1.2rem">
    <div class="card">
      <div class="card-header"><div class="card-title">Service Type Statistics</div></div>
      <div class="card-body">
        <?php
        $serviceStats = $reportData['serviceStats'] ?? [];
        $maxSvc = max(array_column($serviceStats,'count') ?: [1]);
        $svcColors = ['#1a6cff','#22c55e','#f59e0b','#a855f7','#ef4444','#f97316'];
        foreach($serviceStats as $i=>$svc): ?>
        <div class="sell-bar-wrap">
          <div class="sell-bar-label">
            <span><?= htmlspecialchars($svc['name']) ?></span>
            <span><?= $svc['count'] ?> tickets — FRW <?= number_format($svc['revenue'],0) ?></span>
          </div>
          <div class="progress">
            <div class="progress-bar" style="width:<?= $maxSvc?($svc['count']/$maxSvc*100):0 ?>%;background:<?= $svcColors[$i%6] ?>"></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="card">
      <div class="card-header"><div class="card-title">Most Sellable Services (Top 6)</div></div>
      <div class="card-body" style="position:relative;height:300px"><canvas id="mostSellableChart"></canvas></div>
    </div>
  </div>

  <div class="grid-2" style="margin-bottom:1.2rem">
    <div class="card">
      <div class="card-header"><div class="card-title">Top Clients by Revenue</div></div>
      <div class="card-body" style="position:relative;height:300px"><canvas id="topClientsChart"></canvas></div>
    </div>
    <div class="card">
      <div class="card-header"><div class="card-title">Client Breakdown</div></div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Client</th><th>Tickets</th><th>Total Spent</th></tr></thead>
          <tbody>
          <?php foreach(array_slice($reportData['clientStats'] ?? [], 0, 8) as $client): ?>
          <tr>
            <td><?= htmlspecialchars($client['full_name']) ?></td>
            <td><?= $client['ticket_count'] ?></td>
            <td style="font-weight:600">FRW <?= number_format($client['total_spent'],0) ?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- INVENTORY REPORT CONTENT -->
  <?php elseif ($reportType === 'inventory'): ?>
  <div class="grid-2" style="margin-bottom:1.2rem">
    <div class="card">
      <div class="card-header"><div class="card-title">Stock by Category</div></div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Category</th><th>Items</th><th>Quantity</th><th>Value (FRW)</th></tr></thead>
          <tbody>
          <?php foreach($reportData['stockByCategory'] ?? [] as $cat): ?>
          <tr>
            <td><?= htmlspecialchars($cat['name']) ?></td>
            <td><?= $cat['item_count'] ?></td>
            <td><?= number_format($cat['total_qty']) ?></td>
            <td style="font-weight:600"><?= number_format($cat['stock_value'],0) ?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <div class="card">
      <div class="card-header"><div class="card-title">Low Stock Items</div></div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Item</th><th>Category</th><th>Qty</th><th>Min</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach($reportData['lowStock'] ?? [] as $item): ?>
          <tr>
            <td><?= htmlspecialchars($item['name']) ?></td>
            <td><?= htmlspecialchars($item['category_name'] ?? 'N/A') ?></td>
            <td><?= $item['quantity'] ?></td>
            <td><?= $item['min_quantity'] ?></td>
            <td><span class="badge <?= $item['quantity'] == 0 ? 'badge-danger' : 'badge-warning' ?>"><?= $item['quantity'] == 0 ? 'Out of Stock' : 'Low Stock' ?></span></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="grid-2" style="margin-bottom:1.2rem">
    <div class="card">
      <div class="card-header"><div class="card-title">Stock Value by Category</div></div>
      <div class="card-body" style="position:relative;height:300px"><canvas id="stockCategoryChart"></canvas></div>
    </div>
    <div class="card">
      <div class="card-header"><div class="card-title">Top Items by Quantity</div></div>
      <div class="card-body" style="position:relative;height:300px"><canvas id="topItemsChart"></canvas></div>
    </div>
  </div>

  <!-- FINANCIAL REPORT CONTENT -->
  <?php elseif ($reportType === 'financial'): ?>
  <div class="grid-2" style="margin-bottom:1.2rem">
    <div class="card">
      <div class="card-header"><div class="card-title">Revenue (<?= ucfirst($period) ?>)</div></div>
      <div class="card-body" style="position:relative;height:300px"><canvas id="revMonthChart"></canvas></div>
    </div>
    <div class="card">
      <div class="card-header"><div class="card-title">Expenses by Category</div></div>
      <div class="card-body" style="position:relative;height:300px"><canvas id="expenseCategoryChart"></canvas></div>
    </div>
  </div>

  <div class="card" style="margin-bottom:1.2rem">
    <div class="card-header"><div class="card-title">Invoice Status</div></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Status</th><th>Count</th><th>Total Amount</th><th>Balance Due</th></tr></thead>
        <tbody>
        <?php foreach($reportData['invoiceStatus'] ?? [] as $inv): ?>
        <tr>
          <td><span class="badge <?= $inv['status']=='paid'?'badge-success':($inv['status']=='partial'?'badge-warning':'badge-danger') ?>"><?= ucfirst($inv['status']) ?></span></td>
          <td><?= $inv['count'] ?></td>
          <td>FRW <?= number_format($inv['total'],0) ?></td>
          <td>FRW <?= number_format($inv['balance'],0) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="grid-2" style="margin-bottom:1.2rem">
    <div class="card">
      <div class="card-header"><div class="card-title">Profit Trend</div></div>
      <div class="card-body" style="position:relative;height:300px"><canvas id="profitTrendChart"></canvas></div>
    </div>
    <div class="card">
      <div class="card-header"><div class="card-title">Invoice Status Distribution</div></div>
      <div class="card-body" style="position:relative;height:300px"><canvas id="invoiceStatusChart"></canvas></div>
    </div>
  </div>

  <!-- TECHNICIAN REPORT CONTENT -->
  <?php elseif ($reportType === 'technician'): ?>
  <?php if ($userRole === 'technician'): ?>
  <div class="card" style="margin-bottom:1.2rem">
    <div class="card-header"><div class="card-title">My Recent Jobs</div></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Ticket</th><th>Client</th><th>Service</th><th>Status</th><th>Amount</th></tr></thead>
        <tbody>
        <?php foreach($reportData['myTickets'] ?? [] as $ticket): ?>
        <tr>
          <td><?= htmlspecialchars($ticket['ticket_number']) ?></td>
          <td><?= htmlspecialchars($ticket['client_name'] ?? 'N/A') ?></td>
          <td><?= htmlspecialchars($ticket['service_name'] ?? 'N/A') ?></td>
          <td><?php 
$badgeClass = match($ticket['status']) {
    'completed' => 'badge-success',
    'ongoing', 'in_progress' => 'badge-primary',
    'pending' => 'badge-warning',
    'cancelled' => 'badge-danger',
    default => 'badge-secondary'
};
echo '<span class="badge ' . $badgeClass . '">' . ucfirst($ticket['status']) . '</span>';
?></td>
          <td>FRW <?= number_format($ticket['total_amount'],0) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

          
    
  <?php endif; ?>

  <?php endif; ?>

</main>
</div>

<script src="assets/js/main.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
<script>
<?php 
// Chart data based on report type
$chartDataForJS = [
    'revData' => [],
    'revLabels' => [],
    'tkData' => [],
    'tkLabels' => [],
    'expByCategory' => [],
    'expCategoryLabels' => [],
    'serviceNames' => [],
    'serviceCounts' => [],
    'serviceRevenue' => [],
    'clientNames' => [],
    'clientRevenue' => [],
    'stockCategories' => [],
    'stockValues' => [],
    'topItemNames' => [],
    'topItemQty' => [],
    'invoiceStatus' => [],
    'invoiceStatusCounts' => [],
    'profitData' => [],
    'profitLabels' => []
];

if ($reportType === 'sales' && isset($reportData['revByMonth'])): 
    $chartDataForJS['revData'] = array_map(fn($r)=>(float)$r['total'], $reportData['revByMonth']);
    $chartDataForJS['revLabels'] = array_column($reportData['revByMonth'],'mon');
    $chartDataForJS['tkData'] = array_map(fn($r)=>(int)$r['total'], $reportData['ticketsByMonth'] ?? []);
    $chartDataForJS['tkLabels'] = array_column($reportData['ticketsByMonth'] ?? [],'mon');
    
    // Service data for bar chart (top 6)
    $topServices = array_slice($reportData['serviceStats'] ?? [], 0, 6);
    $chartDataForJS['serviceNames'] = array_column($topServices, 'name');
    $chartDataForJS['serviceCounts'] = array_column($topServices, 'count');
    $chartDataForJS['serviceRevenue'] = array_column($topServices, 'revenue');
    
    // Top clients data (top 8)
    $topClients = array_slice($reportData['clientStats'] ?? [], 0, 8);
    $chartDataForJS['clientNames'] = array_column($topClients, 'full_name');
    $chartDataForJS['clientRevenue'] = array_map(fn($c)=>(float)$c['total_spent'], $topClients);
elseif ($reportType === 'inventory' && isset($reportData['stockByCategory'])):
    $chartDataForJS['stockCategories'] = array_column($reportData['stockByCategory'], 'name');
    $chartDataForJS['stockValues'] = array_map(fn($s)=>(float)$s['stock_value'], $reportData['stockByCategory']);
    
    // Top items by quantity
    $topItems = array_slice($reportData['topItems'] ?? [], 0, 8);
    $chartDataForJS['topItemNames'] = array_column($topItems, 'name');
    $chartDataForJS['topItemQty'] = array_column($topItems, 'quantity');
elseif ($reportType === 'financial' && isset($reportData['revByMonth'])):
    $chartDataForJS['revData'] = array_map(fn($r)=>(float)$r['total'], $reportData['revByMonth']);
    $chartDataForJS['revLabels'] = array_column($reportData['revByMonth'],'mon');
    $chartDataForJS['expByCategory'] = array_map(fn($r)=>(float)$r['total'], $reportData['expByCategory'] ?? []);
    $chartDataForJS['expCategoryLabels'] = array_column($reportData['expByCategory'] ?? [],'category');
    
    // Invoice status data
    $chartDataForJS['invoiceStatus'] = array_column($reportData['invoiceStatus'] ?? [], 'status');
    $chartDataForJS['invoiceStatusCounts'] = array_map(fn($inv)=>(int)$inv['count'], $reportData['invoiceStatus'] ?? []);
    
    // Profit trend (calculate from revenue and expenses)
    $profitTrend = [];
    foreach($reportData['revByMonth'] ?? [] as $mon) {
        $profitTrend[$mon['mon']] = (float)$mon['total'];
    }
    $chartDataForJS['profitLabels'] = array_keys($profitTrend);
    $chartDataForJS['profitData'] = array_values($profitTrend);
endif;
?>

const chartData = <?= json_encode($chartDataForJS) ?>;

// Initialize Revenue Chart (Line Chart)
function initRevenueChart() {
    const container = document.getElementById('revMonthChart');
    if (!container || !chartData.revData.length) return;
    
    const ctx = container.getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: chartData.revLabels,
            datasets: [{
                label: 'Revenue',
                data: chartData.revData,
                fill: true,
                backgroundColor: 'rgba(26, 108, 255, 0.1)',
                borderColor: 'rgba(26, 108, 255, 1)',
                borderWidth: 3,
                pointBackgroundColor: 'rgba(26, 108, 255, 1)',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { display: true, position: 'top' }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: function(value) { return 'FRW ' + (value/1000).toFixed(0) + 'K'; } }
                }
            }
        }
    });
}

// Initialize Ticket Chart (Bar Chart)
function initTicketChart() {
    const container = document.getElementById('ticketMonthChart');
    if (!container || !chartData.tkData.length) return;
    
    const ctx = container.getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: chartData.tkLabels,
            datasets: [{
                label: 'Tickets',
                data: chartData.tkData,
                backgroundColor: 'rgba(0, 212, 170, 0.7)',
                borderColor: 'rgba(0, 212, 170, 1)',
                borderWidth: 2,
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { display: true, position: 'top' }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: function(value) { return Math.round(value); } }
                }
            }
        }
    });
}

// Initialize Expenses Category Chart (Pie Chart)
function initExpenseCategoryChart() {
    const container = document.getElementById('expenseCategoryChart');
    if (!container || !chartData.expByCategory.length) return;
    
    const ctx = container.getContext('2d');
    const categoryLabels = chartData.expCategoryLabels.map(c => 
        c.replace('_', ' ').charAt(0).toUpperCase() + c.replace('_', ' ').slice(1)
    );
    
    const colors = [
        'rgba(26, 108, 255, 0.7)',
        'rgba(76, 175, 80, 0.7)',
        'rgba(244, 67, 54, 0.7)',
        'rgba(255, 152, 0, 0.7)',
        'rgba(156, 39, 176, 0.7)',
        'rgba(0, 188, 212, 0.7)',
        'rgba(233, 30, 99, 0.7)',
        'rgba(255, 193, 7, 0.7)'
    ];
    
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: categoryLabels,
            datasets: [{
                data: chartData.expByCategory,
                backgroundColor: colors,
                borderColor: '#fff',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { display: true, position: 'bottom' }
            }
        }
    });
}

// Initialize Most Sellable Services Chart
function initMostSellableChart() {
    const container = document.getElementById('mostSellableChart');
    if (!container || !chartData.serviceNames.length) return;
    
    const ctx = container.getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: chartData.serviceNames,
            datasets: [{
                label: 'Number of Tickets',
                data: chartData.serviceCounts,
                backgroundColor: [
                    'rgba(26, 108, 255, 0.7)',
                    'rgba(34, 197, 94, 0.7)',
                    'rgba(245, 158, 11, 0.7)',
                    'rgba(168, 85, 247, 0.7)',
                    'rgba(239, 68, 68, 0.7)',
                    'rgba(249, 115, 22, 0.7)'
                ],
                borderColor: [
                    'rgba(26, 108, 255, 1)',
                    'rgba(34, 197, 94, 1)',
                    'rgba(245, 158, 11, 1)',
                    'rgba(168, 85, 247, 1)',
                    'rgba(239, 68, 68, 1)',
                    'rgba(249, 115, 22, 1)'
                ],
                borderWidth: 2,
                borderRadius: 8
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { display: false }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: { callback: function(value) { return value + ' tickets'; } }
                }
            }
        }
    });
}

// Initialize Top Clients Chart
function initTopClientsChart() {
    const container = document.getElementById('topClientsChart');
    if (!container || !chartData.clientNames.length) return;
    
    const ctx = container.getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: chartData.clientNames,
            datasets: [{
                label: 'Revenue (FRW)',
                data: chartData.clientRevenue,
                backgroundColor: 'rgba(34, 197, 94, 0.7)',
                borderColor: 'rgba(34, 197, 94, 1)',
                borderWidth: 2,
                borderRadius: 6
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: true,
            plugins: { legend: { display: false } },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: { callback: function(value) { return 'FRW ' + (value/1000).toFixed(0) + 'K'; } }
                }
            }
        }
    });
}

// Initialize Stock Category Chart
function initStockCategoryChart() {
    const container = document.getElementById('stockCategoryChart');
    if (!container || !chartData.stockCategories.length) return;
    
    const ctx = container.getContext('2d');
    const colors = ['rgba(26, 108, 255, 0.7)', 'rgba(76, 175, 80, 0.7)', 'rgba(244, 67, 54, 0.7)', 'rgba(255, 152, 0, 0.7)', 'rgba(156, 39, 176, 0.7)', 'rgba(0, 188, 212, 0.7)'];
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: chartData.stockCategories,
            datasets: [{
                data: chartData.stockValues,
                backgroundColor: colors.slice(0, chartData.stockCategories.length),
                borderColor: '#fff',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: { legend: { display: true, position: 'bottom' } }
        }
    });
}

// Initialize Top Items Chart
function initTopItemsChart() {
    const container = document.getElementById('topItemsChart');
    if (!container || !chartData.topItemNames.length) return;
    
    const ctx = container.getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: chartData.topItemNames,
            datasets: [{
                label: 'Quantity in Stock',
                data: chartData.topItemQty,
                backgroundColor: 'rgba(156, 39, 176, 0.7)',
                borderColor: 'rgba(156, 39, 176, 1)',
                borderWidth: 2,
                borderRadius: 6
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: true,
            plugins: { legend: { display: false } },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: { callback: function(value) { return Math.round(value) + ' units'; } }
                }
            }
        }
    });
}

// Initialize Profit Trend Chart
function initProfitTrendChart() {
    const container = document.getElementById('profitTrendChart');
    if (!container || !chartData.profitData.length) return;
    
    const ctx = container.getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: chartData.profitLabels,
            datasets: [{
                label: 'Profit',
                data: chartData.profitData,
                fill: true,
                backgroundColor: 'rgba(34, 197, 94, 0.1)',
                borderColor: 'rgba(34, 197, 94, 1)',
                borderWidth: 3,
                pointBackgroundColor: 'rgba(34, 197, 94, 1)',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 5,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: { legend: { display: true, position: 'top' } },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: function(value) { return 'FRW ' + (value/1000).toFixed(0) + 'K'; } }
                }
            }
        }
    });
}

// Initialize Invoice Status Chart
function initInvoiceStatusChart() {
    const container = document.getElementById('invoiceStatusChart');
    if (!container || !chartData.invoiceStatus.length) return;
    
    const ctx = container.getContext('2d');
    const colors = { paid: 'rgba(34, 197, 94, 0.7)', partial: 'rgba(245, 158, 11, 0.7)', unpaid: 'rgba(239, 68, 68, 0.7)' };
    new Chart(ctx, {
        type: 'pie',
        data: {
            labels: chartData.invoiceStatus.map(s => s.charAt(0).toUpperCase() + s.slice(1)),
            datasets: [{
                data: chartData.invoiceStatusCounts,
                backgroundColor: chartData.invoiceStatus.map(s => colors[s] || 'rgba(100, 100, 100, 0.7)'),
                borderColor: '#fff',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: { legend: { display: true, position: 'bottom' } }
        }
    });
}

// Initialize all charts
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(() => {
        if (document.getElementById('revMonthChart')) {
            try {
                initRevenueChart();
            } catch(e) { console.log('Revenue chart error:', e); }
        }
        if (document.getElementById('ticketMonthChart')) {
            try {
                initTicketChart();
            } catch(e) { console.log('Ticket chart error:', e); }
        }
        if (document.getElementById('expenseCategoryChart')) {
            try {
                initExpenseCategoryChart();
            } catch(e) { console.log('Expense chart error:', e); }
        }
        if (document.getElementById('mostSellableChart')) {
            try {
                initMostSellableChart();
            } catch(e) { console.log('Most sellable chart error:', e); }
        }
        if (document.getElementById('topClientsChart')) {
            try {
                initTopClientsChart();
            } catch(e) { console.log('Top clients chart error:', e); }
        }
        if (document.getElementById('stockCategoryChart')) {
            try {
                initStockCategoryChart();
            } catch(e) { console.log('Stock category chart error:', e); }
        }
        if (document.getElementById('topItemsChart')) {
            try {
                initTopItemsChart();
            } catch(e) { console.log('Top items chart error:', e); }
        }
        if (document.getElementById('profitTrendChart')) {
            try {
                initProfitTrendChart();
            } catch(e) { console.log('Profit trend chart error:', e); }
        }
        if (document.getElementById('invoiceStatusChart')) {
            try {
                initInvoiceStatusChart();
            } catch(e) { console.log('Invoice status chart error:', e); }
        }
    }, 100);
});
</script>
</body>
</html>
