<?php
/**
 * API: FINANCIAL & REPORTS ENDPOINTS
 * ==================================
 */

require_once '../config.php';
require_once '../modules/FinancialManager.php';
require_once '../modules/ReportGenerator.php';
require_once '../modules/AIAnalysisEngine.php';

$action = $_GET['action'] ?? null;
$financial = new FinancialManager($pdo);
$reports = new ReportGenerator($pdo);
$ai = new AIAnalysisEngine($pdo);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

requireLogin();

try {
    switch ($action) {
        case 'financial_summary':
            $startDate = $_GET['start_date'] ?? null;
            $endDate = $_GET['end_date'] ?? null;
            
            $summary = $financial->getFinancialSummary($startDate, $endDate);
            jsonResponse(true, 'Success', ['summary' => $summary]);
            break;
            
        case 'revenue_by_category':
            $startDate = $_GET['start_date'] ?? null;
            $endDate = $_GET['end_date'] ?? null;
            
            $revenue = $financial->getRevenueByCategory($startDate, $endDate);
            jsonResponse(true, 'Success', ['data' => $revenue]);
            break;
            
        case 'expenses_by_category':
            $startDate = $_GET['start_date'] ?? null;
            $endDate = $_GET['end_date'] ?? null;
            
            $expenses = $financial->getExpensesByCategory($startDate, $endDate);
            jsonResponse(true, 'Success', ['data' => $expenses]);
            break;
            
        case 'sales_by_user':
            $startDate = $_GET['start_date'] ?? null;
            $endDate = $_GET['end_date'] ?? null;
            
            $sales = $financial->getSalesByUser($startDate, $endDate);
            jsonResponse(true, 'Success', ['data' => $sales]);
            break;
            
        case 'revenue_vs_expenses':
            $period = $_GET['period'] ?? 'monthly';
            $year = $_GET['year'] ?? null;
            $month = $_GET['month'] ?? null;
            
            $data = $reports->getRevenueVsExpensesReport($period, $year, $month);
            jsonResponse(true, 'Success', ['data' => $data]);
            break;
            
        case 'ticket_statistics':
            $startDate = $_GET['start_date'] ?? null;
            $endDate = $_GET['end_date'] ?? null;
            
            $stats = $reports->getTicketStatisticsReport($startDate, $endDate);
            jsonResponse(true, 'Success', ['data' => $stats]);
            break;
            
        case 'inventory_usage':
            $startDate = $_GET['start_date'] ?? null;
            $endDate = $_GET['end_date'] ?? null;
            
            $usage = $reports->getInventoryUsageReport($startDate, $endDate);
            jsonResponse(true, 'Success', ['data' => $usage]);
            break;
            
        case 'performance_metrics':
            $startDate = $_GET['start_date'] ?? null;
            $endDate = $_GET['end_date'] ?? null;
            
            $metrics = $reports->getPerformanceMetricsReport($startDate, $endDate);
            jsonResponse(true, 'Success', ['data' => $metrics]);
            break;
            
        case 'insights':
            $insights = $ai->generateInsights();
            
            // Store insights
            foreach ($insights as $insight) {
                $ai->storeInsight(
                    $insight['type'],
                    $insight['title'],
                    $insight['insight_text'],
                    $insight['insight_value'] ?? null,
                    $insight['insight_percent'] ?? null
                );
            }
            
            jsonResponse(true, 'Success', ['insights' => $insights]);
            break;
            
        case 'latest_insights':
            $limit = (int)($_GET['limit'] ?? 10);
            $insights = $ai->getLatestInsights($limit);
            jsonResponse(true, 'Success', ['insights' => $insights]);
            break;
            
        default:
            jsonResponse(false, 'Invalid action');
    }
} catch (Exception $e) {
    jsonResponse(false, $e->getMessage());
}
?>
