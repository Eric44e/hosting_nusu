<?php
/**
 * API: TICKET ENDPOINTS
 * ====================
 */

require_once '../config.php';
require_once '../modules/TicketManager.php';
require_once '../modules/TechnicianManager.php';
require_once '../modules/NotificationManager.php';

$action = $_GET['action'] ?? null;
$ticket = new TicketManager($pdo);
$tech = new TechnicianManager($pdo);
$notif = new NotificationManager($pdo);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

requireLogin();

$userId = $_SESSION['staff_id'];
$userRole = $_SESSION['role'];

try {
    switch ($action) {
        case 'create':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('POST method required');
            }
            
            $data = [
                'client_id' => $_POST['client_id'] ?? null,
                'service_type_id' => $_POST['service_type_id'] ?? null,
                'title' => $_POST['title'] ?? null,
                'description' => $_POST['description'] ?? null,
                'priority' => $_POST['priority'] ?? 'medium',
                'location' => $_POST['location'] ?? null
            ];
            
            if (!$data['client_id'] || !$data['title']) {
                throw new Exception('Client and title required');
            }
            
            $ticketId = $ticket->createTicket($data, $userId);
            if (!$ticketId) {
                throw new Exception('Failed to create ticket');
            }
            
            $newTicket = $ticket->getTicketWithDetails($ticketId);
            jsonResponse(true, 'Ticket created successfully', ['ticket' => $newTicket]);
            break;
            
        case 'get':
            $ticketId = $_GET['id'] ?? null;
            if (!$ticketId) {
                throw new Exception('Ticket ID required');
            }
            
            $tkx = $ticket->getTicketWithDetails($ticketId);
            if (!$tkx) {
                throw new Exception('Ticket not found');
            }
            
            // Get items
            $items = $ticket->getTicketItems($ticketId);
            $tkx['items'] = $items;
            
            jsonResponse(true, 'Success', ['ticket' => $tkx]);
            break;
            
        case 'self_assign':
            $ticketId = $_POST['ticket_id'] ?? null;
            if (!$ticketId) {
                throw new Exception('Ticket ID required');
            }
            
            if ($userRole !== 'technician') {
                throw new Exception('Only technicians can self-assign');
            }
            
            if (!$ticket->selfAssignTicket($ticketId, $userId)) {
                throw new Exception('Failed to assign ticket');
            }
            
            jsonResponse(true, 'Ticket assigned to you');
            break;
            
        case 'assign':
            if ($userRole !== 'admin') {
                throw new Exception('Admin only');
            }
            
            $ticketId = $_POST['ticket_id'] ?? null;
            $technicianId = $_POST['technician_id'] ?? null;
            
            if (!$ticketId || !$technicianId) {
                throw new Exception('Ticket and technician required');
            }
            
            if (!$ticket->assignTicket($ticketId, $technicianId, $userId)) {
                throw new Exception('Failed to assign ticket');
            }
            
            jsonResponse(true, 'Ticket assigned successfully');
            break;
            
        case 'update_status':
            $ticketId = $_POST['ticket_id'] ?? null;
            $status = $_POST['status'] ?? null;
            $notes = $_POST['notes'] ?? null;
            
            if (!$ticketId || !$status) {
                throw new Exception('Ticket and status required');
            }
            
            if (!$ticket->updateTicketStatus($ticketId, $status, $userId, $notes)) {
                throw new Exception('Failed to update status');
            }
            
            jsonResponse(true, 'Ticket status updated');
            break;
            
        case 'add_item':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('POST method required');
            }
            
            $ticketId = $_POST['ticket_id'] ?? null;
            $itemId = $_POST['item_id'] ?? null;
            $quantity = (int)($_POST['quantity'] ?? 1);
            
            if (!$ticketId || !$itemId || $quantity < 1) {
                throw new Exception('Valid ticket, item, and quantity required');
            }
            
            if (!$ticket->addTicketItem($ticketId, $itemId, $quantity)) {
                throw new Exception('Failed to add item');
            }
            
            jsonResponse(true, 'Item added to ticket');
            break;
            
        case 'get_pending':
            $tickets = $ticket->getTickets(['status' => 'pending']);
            jsonResponse(true, 'Success', ['tickets' => $tickets]);
            break;
            
        default:
            jsonResponse(false, 'Invalid action');
    }
} catch (Exception $e) {
    jsonResponse(false, $e->getMessage());
}
?>
