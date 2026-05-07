<?php
// GET /api/support
// POST /api/support        Body: { subject, message, order_id? }

require_once __DIR__ . '/../includes/helpers.php';

setCorsHeaders();
handlePreflight();

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$user = requireAuth(['customer']);

if ($method === 'GET') {
    $stmt = $db->prepare(
        'SELECT id, order_id, subject, message, status, created_at, updated_at
         FROM support_tickets
         WHERE customer_username = ?
         ORDER BY created_at DESC'
    );
    $stmt->execute([$user['sub']]);
    success(['tickets' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    $body = getBody();
    requireFields($body, ['subject', 'message']);
    $orderId = isset($body['order_id']) && $body['order_id'] !== '' ? (int)$body['order_id'] : null;

    $stmt = $db->prepare(
        'INSERT INTO support_tickets (customer_username, order_id, subject, message)
         VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([
        $user['sub'],
        $orderId,
        sanitise($body['subject']),
        sanitise($body['message']),
    ]);
    $id = (int)$db->lastInsertId();
    auditLog($user['sub'], 'customer', 'create_support_ticket', 'support_ticket', (string)$id);
    success(['id' => $id], 'Support ticket created.');
}

error('Method not allowed.', 405);
