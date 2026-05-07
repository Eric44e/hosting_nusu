<?php
/**
 * API: INVENTORY ENDPOINTS
 * ========================
 */

require_once '../config.php';
require_once '../modules/InventoryManager.php';
require_once '../modules/PricingEngine.php';

$action = $_GET['action'] ?? null;
$inventory = new InventoryManager($pdo);
$pricing = new PricingEngine($pdo);

// CORS headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

requireLogin();

try {
    switch ($action) {
        case 'get_subcategories':
            $categoryId = $_GET['category_id'] ?? null;
            if (!$categoryId) {
                throw new Exception('Category ID required');
            }
            
            $stmt = $pdo->prepare("
                SELECT id, name, profit_margin FROM sub_categories
                WHERE category_id = ? AND status = 'active'
            ");
            $stmt->execute([$categoryId]);
            jsonResponse(true, 'Success', ['data' => $stmt->fetchAll()]);
            break;
            
        case 'get_items_by_category':
            $subCategoryId = $_GET['subcategory_id'] ?? null;
            if (!$subCategoryId) {
                throw new Exception('Subcategory ID required');
            }
            
            $items = $inventory->getItemsBySubcategory($subCategoryId);
            jsonResponse(true, 'Success', ['data' => $items]);
            break;
            
        case 'get_low_stock':
            $items = $inventory->getLowStockItems(50);
            jsonResponse(true, 'Success', ['data' => $items]);
            break;
            
        case 'get_fast_moving':
            $items = $inventory->getFastMovingItems(30, 20);
            jsonResponse(true, 'Success', ['data' => $items]);
            break;
            
        case 'get_inventory_value':
            $value = $inventory->getInventoryValue();
            jsonResponse(true, 'Success', ['data' => $value]);
            break;
            
        case 'calculate_price':
            $costPrice = (float)($_POST['cost_price'] ?? 0);
            $margin = (float)($_POST['margin'] ?? 20);
            
            $sellingPrice = $pricing->calculateSellingPrice($costPrice, $margin);
            $profit = $pricing->calculateProfit($costPrice, $sellingPrice);
            
            jsonResponse(true, 'Success', [
                'selling_price' => round($sellingPrice, 2),
                'profit' => round($profit, 2),
                'margin_percent' => $margin
            ]);
            break;
            
        case 'get_all_margins':
            $margins = $pricing->getAllMargins();
            jsonResponse(true, 'Success', ['data' => $margins]);
            break;
            
        default:
            jsonResponse(false, 'Invalid action');
    }
} catch (Exception $e) {
    jsonResponse(false, $e->getMessage());
}
?>
