<?php
/**
 * API: NOTIFICATIONS & MESSAGES ENDPOINTS
 * ======================================
 */

require_once '../config.php';
require_once '../modules/NotificationManager.php';

$action = $_GET['action'] ?? null;
$notif = new NotificationManager($pdo);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

requireLogin();

$userId = $_SESSION['staff_id'];

try {
    switch ($action) {
        case 'get_unread':
            $limit = (int)($_GET['limit'] ?? 50);
            $notifications = $notif->getUnreadNotifications($userId, $limit);
            jsonResponse(true, 'Success', ['notifications' => $notifications]);
            break;
            
        case 'get_all':
            $page = (int)($_GET['page'] ?? 1);
            $limit = (int)($_GET['limit'] ?? 20);
            
            $notifications = $notif->getNotifications($userId, $page, $limit);
            jsonResponse(true, 'Success', ['notifications' => $notifications]);
            break;
            
        case 'mark_read':
            $notificationId = $_POST['notification_id'] ?? null;
            if (!$notificationId) {
                throw new Exception('Notification ID required');
            }
            
            if (!$notif->markAsRead($notificationId)) {
                throw new Exception('Failed to mark as read');
            }
            
            jsonResponse(true, 'Marked as read');
            break;
            
        case 'mark_all_read':
            if (!$notif->markAllAsRead($userId)) {
                throw new Exception('Failed to mark all as read');
            }
            
            jsonResponse(true, 'All marked as read');
            break;
            
        case 'send_message':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('POST method required');
            }
            
            $receiverId = $_POST['receiver_id'] ?? null;
            $ticketId = $_POST['ticket_id'] ?? null;
            $message = $_POST['message'] ?? null;
            
            if (!$message) {
                throw new Exception('Message required');
            }
            
            if (!$receiverId && !$ticketId) {
                throw new Exception('Receiver or ticket required');
            }
            
            $messageId = $notif->sendMessage($userId, $message, $receiverId, $ticketId);
            if (!$messageId) {
                throw new Exception('Failed to send message');
            }
            
            jsonResponse(true, 'Message sent', ['message_id' => $messageId]);
            break;
            
        case 'get_chat':
            $otherUserId = $_GET['user_id'] ?? null;
            $ticketId = $_GET['ticket_id'] ?? null;
            $limit = (int)($_GET['limit'] ?? 100);
            
            if (!$otherUserId && !$ticketId) {
                throw new Exception('User or ticket ID required');
            }
            
            $messages = $notif->getChatHistory($userId, $otherUserId, $ticketId, $limit);
            jsonResponse(true, 'Success', ['messages' => $messages]);
            break;
            
        case 'mark_message_read':
            $messageId = $_POST['message_id'] ?? null;
            if (!$messageId) {
                throw new Exception('Message ID required');
            }
            
            if (!$notif->markMessageAsRead($messageId)) {
                throw new Exception('Failed to mark message as read');
            }
            
            jsonResponse(true, 'Message marked as read');
            break;
            
        case 'get_stats':
            $stats = $notif->getNotificationStats($userId);
            jsonResponse(true, 'Success', ['stats' => $stats]);
            break;
            
        default:
            jsonResponse(false, 'Invalid action');
    }
} catch (Exception $e) {
    jsonResponse(false, $e->getMessage());
}
?>
