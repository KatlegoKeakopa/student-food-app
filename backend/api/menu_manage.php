<?php
// Menu management for outlet managers/admins
// GET    /api/menu_manage?outlet_id=n  – list items (staff/admin)
// POST   /api/menu_manage              – add item (manager/admin)
// PATCH  /api/menu_manage?id=n         – update item (manager/admin)
// DELETE /api/menu_manage?id=n         – remove item (manager/admin)

require_once __DIR__ . '/../includes/helpers.php';

setCorsHeaders();
handlePreflight();

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$user   = requireAuth(['staff', 'admin']);

// Only managers and admins can modify menu
if (in_array($method, ['POST', 'PATCH', 'DELETE'], true)) {
    if ($user['role'] === 'staff' && $user['staff_role'] !== 'manager') {
        error('Only outlet managers or admins can modify the menu.', 403);
    }
}

if ($method === 'GET') {
    $outletId = $user['role'] === 'staff' ? (int)$user['outlet_id'] : (int)($_GET['outlet_id'] ?? 0);
    if (!$outletId) error('outlet_id required.');
    $stmt = $db->prepare(
        'SELECT id, name, description, price, image_url, category, is_available
         FROM food_items WHERE outlet_id = ? ORDER BY category, name'
    );
    $stmt->execute([$outletId]);
    success($stmt->fetchAll());
}

if ($method === 'POST') {
    $body = getBody();
    requireFields($body, ['name', 'price', 'category']);
    $outletId = $user['role'] === 'staff' ? (int)$user['outlet_id'] : (int)($body['outlet_id'] ?? 0);
    if (!$outletId) error('outlet_id required.');
    $price = (float)$body['price'];
    if ($price <= 0) error('Price must be greater than zero.');

    $stmt = $db->prepare(
        'INSERT INTO food_items
         (outlet_id, name, description, price, image_url, category, dietary_tags, allergen_tags, stock_qty, prep_minutes, is_available)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $outletId,
        sanitise($body['name']),
        sanitise($body['description'] ?? ''),
        $price,
        sanitise($body['image_url'] ?? ''),
        sanitise($body['category']),
        sanitise($body['dietary_tags'] ?? ''),
        sanitise($body['allergen_tags'] ?? ''),
        isset($body['stock_qty']) && $body['stock_qty'] !== '' ? max(0, (int)$body['stock_qty']) : null,
        isset($body['prep_minutes']) ? max(1, (int)$body['prep_minutes']) : 10,
        isset($body['is_available']) ? (int)$body['is_available'] : 1,
    ]);
    $itemId = (int)$db->lastInsertId();
    auditLog($user['sub'], $user['role'], 'add_menu_item', 'food_item', (string)$itemId);
    success(['id' => $itemId], 'Menu item added.');
}

if ($method === 'PATCH') {
    $itemId = (int)($_GET['id'] ?? 0);
    if (!$itemId) error('Item ID required.');
    $body = getBody();

    // Build dynamic update
    $fields = [];
    $params = [];
    $allowed = ['name', 'description', 'price', 'category', 'image_url', 'dietary_tags', 'allergen_tags', 'stock_qty', 'prep_minutes', 'is_available'];
    foreach ($allowed as $f) {
        if (array_key_exists($f, $body)) {
            $fields[] = "$f = ?";
            if ($f === 'price') {
                $price = (float)$body[$f];
                if ($price <= 0) error('Price must be greater than zero.');
                $params[] = $price;
            } elseif ($f === 'is_available') {
                $params[] = (int)$body[$f];
            } elseif ($f === 'stock_qty') {
                $params[] = $body[$f] === null || $body[$f] === '' ? null : max(0, (int)$body[$f]);
            } elseif ($f === 'prep_minutes') {
                $params[] = max(1, (int)$body[$f]);
            } else {
                $params[] = sanitise((string)$body[$f]);
            }
        }
    }
    if (!$fields) error('No fields to update.');

    // Restrict staff to their outlet
    $where = 'id = ?';
    $params[] = $itemId;
    if ($user['role'] === 'staff') {
        $where .= ' AND outlet_id = ?';
        $params[] = (int)$user['outlet_id'];
    }
    $db->prepare('UPDATE food_items SET ' . implode(', ', $fields) . ' WHERE ' . $where)->execute($params);
    auditLog($user['sub'], $user['role'], 'update_menu_item', 'food_item', (string)$itemId);
    success([], 'Menu item updated.');
}

if ($method === 'DELETE') {
    $itemId = (int)($_GET['id'] ?? 0);
    if (!$itemId) error('Item ID required.');

    $where = 'id = ?';
    $params = [$itemId];
    if ($user['role'] === 'staff') {
        $where .= ' AND outlet_id = ?';
        $params[] = (int)$user['outlet_id'];
    }
    $db->prepare('UPDATE food_items SET is_available = 0 WHERE ' . $where)->execute($params);
    auditLog($user['sub'], $user['role'], 'disable_menu_item', 'food_item', (string)$itemId);
    success([], 'Menu item disabled.');
}
