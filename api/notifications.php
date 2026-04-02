<?php
/**
 * API de Notificações
 */
define('BASE_PATH', dirname(__DIR__) . '/');
require_once BASE_PATH . 'includes/init.php';

header('Content-Type: application/json; charset=utf-8');

requireAuth();

$db = Database::getInstance();
$userId = $currentUser['id'];

// Apenas contagem
if (isset($_GET['count'])) {
    $unread = $db->fetch(
        "SELECT COUNT(*) as total FROM notifications WHERE user_id = ? AND read_at IS NULL",
        [$userId]
    )['total'];
    
    echo json_encode([
        'success' => true,
        'unread' => $unread
    ]);
    exit;
}

// POST - Marcar como lida
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    if ($action === 'mark_read') {
        $notificationId = $input['id'] ?? 0;
        $db->update(
            'notifications',
            ['read_at' => date('Y-m-d H:i:s')],
            'id = :id AND user_id = :user_id',
            ['id' => $notificationId, 'user_id' => $userId]
        );
        
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($action === 'mark_all_read') {
        $db->query(
            "UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL",
            [$userId]
        );
        
        echo json_encode(['success' => true]);
        exit;
    }
}

// GET - Listar notificações
$notifications = $db->fetchAll(
    "SELECT * FROM notifications 
     WHERE user_id = ? 
     ORDER BY created_at DESC 
     LIMIT 20",
    [$userId]
);

echo json_encode([
    'success' => true,
    'notifications' => $notifications
]);
