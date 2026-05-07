<?php
/**
 * ELECTROSERVE DASHBOARD - PRO VERSION
 * ====================================
 * Includes: Real-time cards, smart insights, AI analysis, financials
 */

require_once 'config.php';
require_once 'modules/FinancialManager.php';
require_once 'modules/AIAnalysisEngine.php';
require_once 'modules/InventoryManager.php';
require_once 'modules/TechnicianManager.php';
require_once 'modules/TicketManager.php';
require_once 'modules/NotificationManager.php';
requireLogin();

// AJAX Handlers
if (isAjax() && isset($_POST['action']) && $_POST['action'] === 'delete_ticket') {
    $ticketId = (int)$_POST['id'];
    $staffId = $_SESSION['staff_id'];
    $ticketMgr = new TicketManager($pdo);
    $result = $ticketMgr->deleteTicket($ticketId, $staffId);
    jsonResponse($result, $result ? 'Ticket deleted successfully.' : 'Failed to delete ticket.');
}

$userRole = $_SESSION['role'] ?? 'guest';
$userId = $_SESSION['staff_id'] ?? 0;

// Initialize managers
$financial = new FinancialManager($pdo);
$ai = new AIAnalysisEngine($pdo);
$inventory = new InventoryManager($pdo);
$techMgr = new TechnicianManager($pdo);
$notificationMgr = new NotificationManager($pdo);

// Get redefined KPIs
$kpis = $financial->getKpiStats();

// Get financial summary (for Other Expenses part)
$finSummary = $financial->getLifetimeFinancialSummary();
$totalCostExposure = $financial->getTotalCostExposure();

// Get AI insights
$insights = $ai->generateInsights();

// Get low stock items
$lowStockItems = $inventory->getLowStockItems(10);

// Get technician stats
$topTechs = $techMgr->getTopTechnicians(5);

// Get fast-moving items
$fastMoving = $inventory->getFastMovingItems(30, 10);

// Get notifications
$userNotifications = $notificationMgr->getUnreadNotifications($userId, 10);

// Get financial data for charts
$yearlyFinData = $financial->getYearlyFinancials();
$months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
$revenueData = array_values($yearlyFinData['revenue']);
$salesData = array_values($yearlyFinData['sales']);
$expenseData = array_values($yearlyFinData['expenses']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>ElectroServe — Pro Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; }
        
        .kpi-card { 
            background: rgba(28, 28, 28, 0.8); 
            backdrop-filter: blur(12px);
            border-radius: 16px; 
            padding: 2rem; 
            border: 1px solid rgba(255, 255, 255, 0.05); 
            border-top: 4px solid var(--primary);
            transition: all .4s cubic-bezier(.4,0,.2,1); 
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 20px rgba(0,0,0,0.4);
        }
        .kpi-card:hover { 
            transform: translateY(-8px); 
            box-shadow: 0 20px 40px rgba(255,122,0,0.2); 
            border-top-color: #fff;
            background: #262626;
        }
        
        .kpi-card.success { border-top-color: var(--success); }
        .kpi-card.warning { border-top-color: var(--warning); }
        .kpi-card.danger { border-top-color: var(--danger); }
        
        .kpi-icon { 
            font-size: 2.2rem; 
            margin-bottom: 1.5rem;
            opacity: 0.9;
        }
        .kpi-label { font-size: .75rem; color: var(--muted); margin-bottom: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; }
        .kpi-value { font-size: 1.8rem; font-weight: 800; color: #fff; letter-spacing: -0.5px; }
        .kpi-change { font-size: .8rem; margin-top: 1rem; font-weight: 600; display: flex; align-items: center; gap: .4rem; }
        .kpi-change.positive { color: var(--success); }
        .kpi-change.negative { color: var(--danger); }
        
        .insight-box { background: linear-gradient(135deg,  #FF7A00 0%, #ff9a00 100%); color: white; padding: 1.5rem; border-radius: 12px; margin-bottom: 1rem; box-shadow: 0 8px 20px rgba(0,0,0,0.3); }
        .insight-icon { font-size: 1.5rem; margin-bottom: .5rem; }
        .insight-title { font-weight: 700; margin-bottom: .25rem; }
        .insight-text { font-size: .875rem; opacity: .95; line-height: 1.5; }
        
        .chart-container { position: relative; height: 300px; margin-bottom: 2rem; background: #000; border-radius: 12px; padding: 1.5rem; border: 1px solid rgba(255,255,255,0.1); }
        .low-stock-item { display: flex; justify-content: space-between; align-items: center; padding: 1rem; border-bottom: 1px solid rgba(255,255,255,0.1); color: #fff; }
        .low-stock-item:last-child { border-bottom: none; }
        .stock-status { display: inline-block; padding: .35rem .75rem; border-radius: 6px; font-size: .75rem; font-weight: 600; background: var(--danger); color: white; }
        
        .tech-card { display: flex; align-items: center; gap: 1rem; padding: 1rem; background: #000; border-radius: 8px; margin-bottom: .5rem; border: 1px solid rgba(255,255,255,0.1); transition: all .3s; }
        .tech-card:hover { background: #111; transform: translateX(4px); }
        .tech-avatar { width: 44px; height: 44px; border-radius: 50%; background: #FF7A00; color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1.1rem; }
        .tech-info { flex: 1; }
        .tech-name { font-weight: 700; font-size: .95rem; color: #fff; }
        .tech-rating { color: var(--warning); font-size: .875rem; font-weight: 600; }
        .tech-jobs { color: rgba(255,255,255,0.6); font-size: .8rem; }
        
        .section-title { font-size: 1.4rem; font-weight: 800; margin-bottom: 2rem; display: flex; align-items: center; gap: .75rem; color: #fff; }
        .section-title::before { content: ''; width: 5px; height: 1.8rem; background: #FF7A00; border-radius: 3px; }
        .card { background: #000 !important; color: #fff !important; border: 1px solid rgba(255,255,255,0.1) !important; }
        table th { color: #FF7A00 !important; }
        table td { color: #fff !important; }
    </style>
</head>
<body>
<div class="app-wrapper">
    <?php include 'includes/layout.php'; ?>
    
    <main class="main-content fade-in">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-header-left">
                <h1>Dashboard Pro</h1>
                <p>Real-time business intelligence & analytics</p>
            </div>
            <div class="page-actions">
                <span style="color:var(--muted);font-size:.85rem">
                    <i class="fas fa-calendar-alt" style="margin-right:.4rem"></i><?= date('F j, Y') ?>
                </span>
                <?php if ($userRole !== 'technician'): ?>
                <button class="btn btn-primary btn-sm" onclick="window.location.href='ticket_new.php'">
                    <i class="fas fa-plus"></i> New Ticket
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- KEY PERFORMANCE INDICATORS -->
        <section class="mb-5">
            <div class="dashboard-grid" style="grid-template-columns: repeat(4, 1fr);">
                
                <!-- Total Stock Investment -->
                <div class="kpi-card info">
                    <div class="kpi-icon" style="color: #0ea5e9;"><i class="fas fa-hand-holding-usd"></i></div>
                    <div class="kpi-label">Total Stock Investment 🏗️</div>
                    <div class="kpi-value">FRW <?= number_format($kpis['total_stock_investment'], 0) ?></div>
                    <div class="kpi-change">
                        <i class="fas fa-info-circle"></i> Total capital spent on stock
                    </div>
                </div>

                <!-- Total Revenue -->
                <div class="kpi-card">
                    <div class="kpi-icon" style="color: #FF7A00;"><i class="fas fa-money-bill-wave"></i></div>
                    <div class="kpi-label">Total Revenue 💰</div>
                    <div class="kpi-value">FRW <?= number_format($kpis['total_revenue'], 0) ?></div>
                    <div class="kpi-change positive">
                        <i class="fas fa-globe"></i> Overall generated value
                    </div>
                </div>

                <!-- Total Sales -->
                <div class="kpi-card">
                    <div class="kpi-icon" style="color: #6366f1;"><i class="fas fa-shopping-cart"></i></div>
                    <div class="kpi-label">Total Sales 🛒</div>
                    <div class="kpi-value"><?= number_format($kpis['total_sales_count']) ?></div>
                    <div class="kpi-change">
                        <i class="fas fa-box"></i> All customer orders made
                    </div>
                </div>

                <!-- Completed Revenue -->
                <div class="kpi-card success">
                    <div class="kpi-icon" style="color: #22c55e;"><i class="fas fa-check-circle"></i></div>
                    <div class="kpi-label">Completed Revenue ✅</div>
                    <div class="kpi-value"><?= formatMoney($kpis['completed_revenue']) ?></div>
                    <div class="kpi-change positive">
                        <i class="fas fa-wallet"></i> Money actually received
                    </div>
                </div>

                <!-- Net Profit -->
                <?php 
                $netProfit = $kpis['completed_revenue'] - $totalCostExposure;
                $profitPercent = $kpis['completed_revenue'] > 0 ? ($netProfit / $kpis['completed_revenue']) * 100 : 0;
                ?>
                <div class="kpi-card <?= $netProfit >= 0 ? 'success' : 'danger' ?>">
                    <div class="kpi-icon" style="color: <?= $netProfit >= 0 ? '#22c55e' : '#ef4444' ?>;"><i class="fas fa-chart-line"></i></div>
                    <div class="kpi-label">Net Profit</div>
                    <div class="kpi-value"><?= formatMoney($netProfit) ?></div>
                    <div class="kpi-change <?= $netProfit >= 0 ? 'positive' : 'negative' ?>">
                        <i class="fas <?= $netProfit >= 0 ? 'fa-smile' : 'fa-frown' ?>"></i> <?= round($profitPercent, 1) ?>% Margin
                    </div>
                </div>

                <!-- Total Expenses (Inventory + Other) -->
                <div class="kpi-card danger">
                    <div class="kpi-icon" style="color: #ef4444;"><i class="fas fa-receipt"></i></div>
                    <div class="kpi-label">Total Expenses</div>
                    <div class="kpi-value"><?= formatMoney($totalCostExposure) ?></div>
                    <div class="kpi-change negative">
                        <i class="fas fa-minus-circle"></i> Inventory + Other Costs
                    </div>
                </div>

                <!-- Other Expenses -->
                <div class="kpi-card">
                    <div class="kpi-icon" style="color: #f87171;"><i class="fas fa-file-invoice-dollar"></i></div>
                    <div class="kpi-label">Other Expenses</div>
                    <div class="kpi-value"><?= formatMoney($finSummary['expenses']) ?></div>
                    <div class="kpi-change">
                        <i class="fas fa-arrow-right"></i> Operational Outflow
                    </div>
                </div>

                <!-- Inventory Cost -->
                <div class="kpi-card warning">
                    <div class="kpi-icon" style="color: #f59e0b;"><i class="fas fa-boxes"></i></div>
                    <div class="kpi-label">Inventory Cost Value</div>
                    <?php 
                    $currInvValue = $pdo->query("SELECT SUM(COALESCE(cost_price, 0) * COALESCE(quantity, 0)) FROM items WHERE status = 'active'")->fetchColumn();
                    ?>
                    <div class="kpi-value"><?= formatMoney((float)$currInvValue) ?></div>
                    <div class="kpi-change">
                        <i class="fas fa-warehouse"></i> Warehouse Asset Value
                    </div>
                </div>

                <!-- Denied Tickets -->
                <div class="kpi-card danger" style="cursor:pointer" onclick="window.location.href='tickets.php?status=denied'">
                    <div class="kpi-icon" style="color: #ef4444;"><i class="fas fa-ban"></i></div>
                    <div class="kpi-label">Denied Tickets</div>
                    <?php
                    $deniedCount = $pdo->query("SELECT COUNT(*) FROM tickets WHERE status='denied'")->fetchColumn();
                    ?>
                    <div class="kpi-value"><?= number_format($deniedCount) ?></div>
                    <div class="kpi-change negative">
                        <i class="fas fa-exclamation-circle"></i> Rejected Requests
                    </div>
                </div>

            </div>
        </section>

        <!-- DASHBOARD CHARTS -->
        <section class="mb-5">
            <div class="grid-2">
                <!-- Yearly Sales Bar Chart -->
                <div class="card">
                    <div class="card-header">
                    <div class="card-title">Yearly Financial Performance</div>
                    <a href="financial.php" style="color: var(--primary); font-size: .8rem; font-weight: 700;">View Report</a>
                    </div>
                    <div class="card-body">
                        <div class="chart-container" style="height: 300px;">
                            <canvas id="yearlySalesChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Yearly Sales & Revenue Line Chart -->
                <div class="card">
                    <div class="card-header">
                    <div class="card-title">Operational Efficiency</div>
                    <a href="financial.php" style="color: var(--primary); font-size: .8rem; font-weight: 700;">View Report</a>
                    </div>
                    <div class="card-body">
                        <div class="chart-container" style="height: 300px;">
                            <canvas id="salesRevenueChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- AI INSIGHTS PANEL (Simplified) -->
        <section class="mb-5">
            <h2 class="section-title">🤖 Smart Insights</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
                <?php 
                if (!empty($insights)) {
                    foreach (array_slice($insights, 0, 2) as $insight):
                ?>
                <div class="insight-box">
                    <div class="insight-title"><?= $insight['title'] ?></div>
                    <div class="insight-text"><?= $insight['insight_text'] ?? ($insight['message'] ?? 'No insight available')?></div>
                </div>
                <?php endforeach;
                } ?>
            </div>
        </section>

        <!-- TICKETS SUMMARY TABLE -->
        <section class="mb-5">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
                <h2 class="section-title" style="margin-bottom:0;">TICKETS SUMMARY</h2>
                <a href="tickets.php" style="color: var(--primary); font-weight: 600;">Show All</a>
            </div>
            <div class="card">
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Ticket #</th><th>Client</th><th>Service</th><th>Status</th><th>Technician</th><th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $stmt = $pdo->query("SELECT t.*, c.full_name as client_name, st.name as service_name, s.full_name as tech_name 
                                               FROM tickets t 
                                               LEFT JOIN clients c ON c.id = t.client_id 
                                               LEFT JOIN service_types st ON st.id = t.service_type_id
                                               LEFT JOIN technicians tech ON tech.id = t.technician_id
                                               LEFT JOIN staff s ON s.id = tech.staff_id
                                               WHERE t.status != 'denied'
                                               ORDER BY t.created_at DESC LIMIT 5");
                            while($t = $stmt->fetch()): 
                            ?>
                            <tr>
                                <td><a href="ticket_view.php?id=<?= $t['id'] ?>" style="color:var(--primary); font-weight:600;"><?= $t['ticket_number'] ?></a></td>
                                <td><?= htmlspecialchars($t['client_name']) ?></td>
                                <td><?= htmlspecialchars($t['service_name']) ?></td>
                                <td><?= statusBadge($t['status']) ?></td>
                                <td><?= $t['tech_name'] ?? '—' ?></td>
                                <td>
                                    <a href="ticket_view.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-outline"><i class="fas fa-eye"></i></a>
                                    <?php if($userRole === 'admin'): ?>
                                    <button class="btn btn-sm btn-outline btn-danger" onclick="deleteTicket(<?= $t['id'] ?>, '<?= $t['ticket_number'] ?>')" style="margin-left:5px; color:#ef4444; border-color:rgba(239,68,68,0.2)"><i class="fas fa-trash"></i></button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- DENIED TICKETS SECTION -->
        <section class="mb-5">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
                <h2 class="section-title" style="margin-bottom:0; color:#ef4444 !important;">DENIED TICKETS</h2>
                <a href="tickets.php?status=denied" style="color: #ef4444; font-weight: 600;">View All Denied</a>
            </div>
            <div class="card" style="background: rgba(239, 68, 68, 0.05); border: 1px solid rgba(239, 68, 68, 0.2);">
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr style="border-bottom: 1px solid rgba(239, 68, 68, 0.1);">
                                <th>Ticket #</th><th>Client</th><th>Reason for Denial</th><th>Date</th><th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $stmt = $pdo->query("SELECT t.*, c.full_name as client_name 
                                               FROM tickets t 
                                               LEFT JOIN clients c ON c.id = t.client_id 
                                               WHERE t.status = 'denied'
                                               ORDER BY t.updated_at DESC LIMIT 5");
                            $hasDenied = false;
                            while($t = $stmt->fetch()): 
                                $hasDenied = true;
                            ?>
                            <tr style="border-bottom: 1px solid rgba(239, 68, 68, 0.05);">
                                <td><a href="ticket_view.php?id=<?= $t['id'] ?>" style="color:#ef4444; font-weight:600;"><?= $t['ticket_number'] ?></a></td>
                                <td><?= htmlspecialchars($t['client_name']) ?></td>
                                <td style="color:#f87171; font-style:italic; font-size:.85rem; max-width:300px;"><?= htmlspecialchars($t['denial_reason'] ?? 'No reason provided') ?></td>
                                <td style="font-size:.8rem; color:rgba(255,255,255,0.5);"><?= date('M j, Y', strtotime($t['updated_at'])) ?></td>
                                <td>
                                    <a href="ticket_view.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-outline" style="border-color:rgba(239, 68, 68, 0.3); color:#ef4444;"><i class="fas fa-eye"></i></a>
                                    <?php if($userRole === 'admin'): ?>
                                    <button class="btn btn-sm btn-outline btn-danger" onclick="deleteTicket(<?= $t['id'] ?>, '<?= $t['ticket_number'] ?>')" style="margin-left:5px; color:#ef4444; border-color:rgba(239,68,68,0.2)"><i class="fas fa-trash"></i></button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; 
                            if(!$hasDenied): ?>
                            <tr><td colspan="5" style="text-align:center; padding:2rem; color:rgba(255,255,255,0.3);">No denied tickets found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

    </main>
</div>

<script>
Chart.defaults.color = '#a3a3a3';
Chart.defaults.borderColor = 'rgba(255,255,255,0.05)';
Chart.defaults.font.family = "'Inter', sans-serif";

// Yearly Sales Bar Chart
const yearlySalesCtx = document.getElementById('yearlySalesChart')?.getContext('2d');
if (yearlySalesCtx) {
    new Chart(yearlySalesCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($months) ?>,
            datasets: [
                { 
                    label: 'Revenue (FRW)', 
                    data: <?= json_encode($revenueData) ?>, 
                    backgroundColor: '#FF7A00',
                    borderRadius: 4
                },
                { 
                    label: 'Expenses (FRW)', 
                    data: <?= json_encode($expenseData) ?>, 
                    backgroundColor: '#FFB366',
                    borderRadius: 4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            layout: { padding: { left: 10, right: 10, top: 20, bottom: 0 } },
            scales: {
                y: { 
                    beginAtZero: true, 
                    grid: { color: 'rgba(255,255,255,0.05)' },
                    ticks: {
                        color: '#a3a3a3',
                        callback: function(value) {
                            if (value >= 1000000) return (value/1000000).toFixed(1) + 'M';
                            if (value >= 1000) return (value/1000).toFixed(0) + 'k';
                            return value;
                        }
                    }
                },
                x: { 
                    grid: { display: false },
                    ticks: { color: '#a3a3a3' }
                }
            },
            plugins: { 
                legend: { position: 'top', labels: { boxWidth: 12, color: '#f5f5f5', font: { weight: '600' } } }
            }
        }
    });
}

// Yearly Sales & Revenue Line Chart
const salesRevenueCtx = document.getElementById('salesRevenueChart')?.getContext('2d');
if (salesRevenueCtx) {
    new Chart(salesRevenueCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode($months) ?>,
            datasets: [
                { 
                    label: 'Service Tickets', 
                    data: <?= json_encode($salesData) ?>, 
                    borderColor: '#FF7A00', 
                    backgroundColor: 'rgba(255, 122, 0, 0.2)',
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#FF7A00',
                    pointBorderColor: '#fff'
                },
                { 
                    label: 'Revenue Trend', 
                    data: <?= json_encode($revenueData) ?>, 
                    borderColor: '#fff', 
                    backgroundColor: 'rgba(255, 255, 255, 0.05)',
                    fill: false,
                    tension: 0.4,
                    yAxisID: 'y1',
                    pointBackgroundColor: '#fff'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            layout: { padding: { left: 10, right: 10, top: 20, bottom: 0 } },
            scales: {
                y: { 
                    beginAtZero: true, 
                    grid: { color: 'rgba(255,255,255,0.05)' },
                    title: { display: true, text: 'Ticket Count', color: '#a3a3a3' },
                    ticks: { color: '#a3a3a3' }
                },
                y1: {
                    position: 'right',
                    beginAtZero: true,
                    grid: { display: false },
                    title: { display: true, text: 'Revenue (FRW)', color: '#a3a3a3' },
                    ticks: {
                        color: '#a3a3a3',
                        callback: function(value) {
                            if (value >= 1000) return (value/1000).toFixed(0) + 'k';
                            return value;
                        }
                    }
                },
                x: { 
                    grid: { display: false },
                    ticks: { color: '#a3a3a3' }
                }
            },
            plugins: { 
                legend: { position: 'top', labels: { boxWidth: 12, color: '#f5f5f5', font: { weight: '600' } } }
            }
        }
    });
}

function markNotifRead(id) {
    fetch(`api/notifications.php?action=mark_read`, {
        method: 'POST',
        body: new FormData(Object.assign(document.createElement('form'), {
            elements: { notification_id: { value: id } }
        }))
    }).then(() => location.reload());
}


function deleteTicket(id, num) {
    Swal.fire({
        title: 'Delete Ticket?',
        text: `Are you sure you want to delete Ticket ${num}? This will also return any associated materials to stock and delete all related logs.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#333',
        confirmButtonText: 'Yes, Delete It',
        background: '#1a1a1a',
        color: '#fff'
    }).then((result) => {
        if (result.isConfirmed) {
            const fd = new FormData();
            fd.append('action', 'delete_ticket');
            fd.append('id', id);
            
            fetch('dashboard.php', {
                method: 'POST',
                body: fd,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    Swal.fire({ icon: 'success', title: 'Deleted!', text: res.message, background: '#1a1a1a', color: '#fff' });
                    setTimeout(() => location.reload(), 1200);
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.message, background: '#1a1a1a', color: '#fff' });
                }
            });
        }
    });
}
</script>
<script src="assets/js/main.js"></script>
</body>
</html>
