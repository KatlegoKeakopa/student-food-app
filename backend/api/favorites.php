<?php
// GET /api/favorites
// POST /api/favorites        Body: { outlet_id?, food_item_id? }
// DELETE /api/favorites?id=n

require_once __DIR__ . '/../includes/helpers.php';

setCorsHeaders();
handlePreflight();

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$user = requireAuth(['customer']);

if ($method === 'GET') {
    $stmt = $db->prepare(
        'SELECT f.id, f.outlet_id, f.food_item_id, f.created_at,
                o.name AS outlet_name, o.description AS outlet_description,
                fi.name AS item_name, fi.price AS item_price
         FROM favorites f
         LEFT JOIN food_outlets o ON o.id = f.outlet_id
         LEFT JOIN food_items fi ON fi.id = f.food_item_id
         WHERE f.customer_username = ?
         ORDER BY f.created_at DESC'
    );
    $stmt->execute([$user['sub']]);
    success(['favorites' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    $body = getBody();
    $outletId = isset($body['outlet_id']) ? (int)$body['outlet_id'] : null;
    $foodItemId = isset($body['food_item_id']) ? (int)$body['food_item_id'] : null;
    if (!$outletId && !$foodItemId) error('outlet_id or food_item_id is required.');
    if ($outletId) {
        $exists = $db->prepare('SELECT id FROM food_outlets WHERE id = ? AND is_active = 1');
        $exists->execute([$outletId]);
        if (!$exists->fetch()) error('Outlet not found.', 404);
    }
    if ($foodItemId) {
        $exists = $db->prepare('SELECT id FROM food_items WHERE id = ? AND is_available = 1');
        $exists->execute([$foodItemId]);
        if (!$exists->fetch()) error('Menu item not found.', 404);
    }

    $check = $db->prepare(
        'SELECT id FROM favorites
         WHERE customer_username = ? AND outlet_id <=> ? AND food_item_id <=> ?
         LIMIT 1'
    );
    $check->execute([$user['sub'], $outletId, $foodItemId]);
    if (!$check->fetch()) {
        $stmt = $db->prepare(
            'INSERT INTO favorites (customer_username, outlet_id, food_item_id) VALUES (?, ?, ?)'
        );
        $stmt->execute([$user['sub'], $outletId, $foodItemId]);
    }
    auditLog($user['sub'], 'customer', 'add_favorite', 'favorite', null, $body);
    success([], 'Favorite saved.');
}

if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) error('Favorite id is required.');
    $stmt = $db->prepare('DELETE FROM favorites WHERE id = ? AND customer_username = ?');
    $stmt->execute([$id, $user['sub']]);
    auditLog($user['sub'], 'customer', 'remove_favorite', 'favorite', (string)$id);
    success([], 'Favorite removed.');
}

error('Method not allowed.', 405);
