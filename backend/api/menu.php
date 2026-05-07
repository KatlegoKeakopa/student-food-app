<?php
// GET /api/menu?outlet_id={n}              – full menu for an outlet
// GET /api/menu?outlet_id={n}&category={c} – filtered by category

require_once __DIR__ . '/../includes/helpers.php';

setCorsHeaders();
handlePreflight();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') error('Method not allowed.', 405);

if (empty($_GET['outlet_id'])) error('outlet_id is required.');
$outletId = (int)$_GET['outlet_id'];

$db = getDB();

// Verify outlet exists
$check = $db->prepare('SELECT id FROM food_outlets WHERE id = ? AND is_active = 1');
$check->execute([$outletId]);
if (!$check->fetch()) error('Outlet not found.', 404);

$sql    = 'SELECT id, outlet_id, name, description, price, image_url, category,
                  dietary_tags, allergen_tags, prep_minutes, is_available
           FROM food_items
           WHERE outlet_id = ? AND is_available = 1 AND (stock_qty IS NULL OR stock_qty > 0)';
$params = [$outletId];

if (!empty($_GET['category'])) {
    $sql .= ' AND category = ?';
    $params[] = sanitise($_GET['category']);
}
if (!empty($_GET['q'])) {
    $q = '%' . sanitise($_GET['q']) . '%';
    $sql .= ' AND (name LIKE ? OR description LIKE ? OR category LIKE ?)';
    array_push($params, $q, $q, $q);
}
if (!empty($_GET['diet'])) {
    $sql .= ' AND dietary_tags LIKE ?';
    $params[] = '%' . sanitise($_GET['diet']) . '%';
}

$sort = isset($_GET['sort']) ? sanitise($_GET['sort']) : 'category';
switch ($sort) {
    case 'price_asc':
        $sortSql = 'price ASC, name';
        break;
    case 'price_desc':
        $sortSql = 'price DESC, name';
        break;
    case 'prep':
        $sortSql = 'prep_minutes ASC, name';
        break;
    default:
        $sortSql = 'category, name';
}
$sql .= ' ORDER BY ' . $sortSql;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();

// Also return unique categories for filter UI
$catStmt = $db->prepare(
    'SELECT DISTINCT category FROM food_items WHERE outlet_id = ? AND is_available = 1 ORDER BY category'
);
$catStmt->execute([$outletId]);
$categories = array_column($catStmt->fetchAll(), 'category');

success(['items' => $items, 'categories' => $categories]);
