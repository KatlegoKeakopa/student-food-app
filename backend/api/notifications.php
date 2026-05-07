<?php
// GET   /api/notifications – customer's unread notifications
// PATCH /api/notifications – mark all as read

require_once __DIR__ . '/../includes/helpers.php';

setCorsHeaders();
handlePreflight();

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$user   = requireAuth(['customer']);

if ($method === 'GET') {
    $stmt = $db->prepare(
        'SELECT id, order_id, message, is_read, created_at
         FROM notifications WHERE customer_username = ?
         ORDER BY created_at DESC LIMIT 50'
    );
    $stmt->execute([$user['sub']]);
    $notifs = $stmt->fetchAll();

    $countStmt = $db->prepare(
        'SELECT COUNT(*) AS unread_count
         FROM notifications
         WHERE customer_username = ? AND is_read = 0'
    );
    $countStmt->execute([$user['sub']]);
    $unread = (int)($countStmt->fetch()['unread_count'] ?? 0);

    success(['notifications' => $notifs, 'unread_count' => $unread]);
}

if ($method === 'PATCH') {
    $db->prepare(
        'UPDATE notifications SET is_read = 1 WHERE customer_username = ?'
    )->execute([$user['sub']]);
    success([], 'Notifications marked as read.');
}
