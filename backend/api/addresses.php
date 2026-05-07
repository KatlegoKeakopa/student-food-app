<?php
// GET /api/addresses
// POST /api/addresses       Body: { label, address_line, lat?, lng?, is_default? }
// DELETE /api/addresses?id=n

require_once __DIR__ . '/../includes/helpers.php';

setCorsHeaders();
handlePreflight();

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$user = requireAuth(['customer']);

if ($method === 'GET') {
    $stmt = $db->prepare(
        'SELECT id, label, address_line, lat, lng, is_default, created_at
         FROM customer_addresses
         WHERE customer_username = ?
         ORDER BY is_default DESC, created_at DESC'
    );
    $stmt->execute([$user['sub']]);
    success(['addresses' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    $body = getBody();
    requireFields($body, ['label', 'address_line']);
    $isDefault = !empty($body['is_default']) ? 1 : 0;

    $db->beginTransaction();
    try {
        if ($isDefault) {
            $db->prepare('UPDATE customer_addresses SET is_default = 0 WHERE customer_username = ?')
                ->execute([$user['sub']]);
        }
        $stmt = $db->prepare(
            'INSERT INTO customer_addresses (customer_username, label, address_line, lat, lng, is_default)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $user['sub'],
            sanitise($body['label']),
            sanitise($body['address_line']),
            isset($body['lat']) ? (float)$body['lat'] : null,
            isset($body['lng']) ? (float)$body['lng'] : null,
            $isDefault,
        ]);
        $id = (int)$db->lastInsertId();
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        error('Failed to save address.', 500);
    }

    auditLog($user['sub'], 'customer', 'add_address', 'address', (string)$id);
    success(['id' => $id], 'Address saved.');
}

if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) error('Address id is required.');
    $stmt = $db->prepare('DELETE FROM customer_addresses WHERE id = ? AND customer_username = ?');
    $stmt->execute([$id, $user['sub']]);
    auditLog($user['sub'], 'customer', 'delete_address', 'address', (string)$id);
    success([], 'Address deleted.');
}

error('Method not allowed.', 405);
