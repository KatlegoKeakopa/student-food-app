<?php
// GET /api/outlets          – list all active outlets with avg rating
// GET /api/outlets?id={n}   – single outlet detail

require_once __DIR__ . '/../includes/helpers.php';

setCorsHeaders();
handlePreflight();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') error('Method not allowed.', 405);

$db = getDB();

if (!empty($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $db->prepare(
        'SELECT o.id, o.name, o.description, o.image_url,
                ROUND(AVG(r.rating), 1) AS avg_rating,
                COUNT(r.id)             AS review_count
         FROM food_outlets o
         LEFT JOIN ratings r ON r.outlet_id = o.id
         WHERE o.id = ? AND o.is_active = 1
         GROUP BY o.id'
    );
    $stmt->execute([$id]);
    $outlet = $stmt->fetch();
    if (!$outlet) error('Outlet not found.', 404);
    success($outlet);
} else {
    $stmt = $db->query(
        'SELECT o.id, o.name, o.description, o.image_url,
                ROUND(AVG(r.rating), 1) AS avg_rating,
                COUNT(r.id)             AS review_count
         FROM food_outlets o
         LEFT JOIN ratings r ON r.outlet_id = o.id
         WHERE o.is_active = 1
         GROUP BY o.id
         ORDER BY o.id'
    );
    success($stmt->fetchAll());
}
