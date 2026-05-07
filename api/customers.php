<?php
/**
 * API: CUSTOMER ENDPOINTS
 * ======================
 */

require_once '../config.php';
require_once '../modules/CustomerManager.php';

$action = $_GET['action'] ?? null;
$customer = new CustomerManager($pdo);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

requireLogin();

try {
    switch ($action) {
        case 'create':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('POST method required');
            }
            
            $data = [
                'full_name' => $_POST['full_name'] ?? null,
                'email' => $_POST['email'] ?? null,
                'phone' => $_POST['phone'] ?? null,
                'address' => $_POST['address'] ?? null,
                'city' => $_POST['city'] ?? null
            ];
            
            if (!$data['full_name']) {
                throw new Exception('Customer name required');
            }
            
            $customerId = $customer->createCustomer($data);
            if (!$customerId) {
                throw new Exception('Failed to create customer');
            }
            
            $newCustomer = $customer->getCustomer($customerId);
            jsonResponse(true, 'Customer created successfully', ['customer' => $newCustomer]);
            break;
            
        case 'get':
            $customerId = $_GET['id'] ?? null;
            if (!$customerId) {
                throw new Exception('Customer ID required');
            }
            
            $cust = $customer->getCustomerWithStats($customerId);
            if (!$cust) {
                throw new Exception('Customer not found');
            }
            
            jsonResponse(true, 'Success', ['customer' => $cust]);
            break;
            
        case 'list':
            $page = (int)($_GET['page'] ?? 1);
            $search = $_GET['search'] ?? null;
            
            $customers = $customer->getCustomers($page, 50, $search);
            $total = $customer->getCustomerCount($search);
            
            jsonResponse(true, 'Success', [
                'customers' => $customers,
                'total' => $total,
                'page' => $page,
                'pages' => ceil($total / 50)
            ]);
            break;
            
        case 'search':
            $query = $_GET['q'] ?? null;
            if (!$query || strlen($query) < 2) {
                jsonResponse(false, 'Search query too short');
            }
            
            $results = $customer->searchCustomers($query);
            jsonResponse(true, 'Success', ['customers' => $results]);
            break;
            
        case 'update':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('POST method required');
            }
            
            $customerId = $_POST['id'] ?? null;
            if (!$customerId) {
                throw new Exception('Customer ID required');
            }
            
            $data = [
                'full_name' => $_POST['full_name'] ?? null,
                'email' => $_POST['email'] ?? null,
                'phone' => $_POST['phone'] ?? null,
                'address' => $_POST['address'] ?? null,
                'city' => $_POST['city'] ?? null,
                'notes' => $_POST['notes'] ?? null
            ];
            
            if (!$customer->updateCustomer($customerId, $data)) {
                throw new Exception('Failed to update customer');
            }
            
            $updatedCustomer = $customer->getCustomer($customerId);
            jsonResponse(true, 'Customer updated successfully', ['customer' => $updatedCustomer]);
            break;
            
        default:
            jsonResponse(false, 'Invalid action');
    }
} catch (Exception $e) {
    jsonResponse(false, $e->getMessage());
}
?>
