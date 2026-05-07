<?php
// POST /api/ratings    – submit a rating for a delivered order
// GET  /api/ratings?outlet_id={n} – get ratings for an outlet

require_once __DIR__ . '/../includes/helpers.php';

setCorsHeaders();
handlePreflight();

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $user = requireAuth(['customer']);
    $body = getBody();
    requireFields($body, ['order_id', 'rating']);

    $orderId = (int)$body['order_id'];
    $rating  = (int)$body['rating'];
    $review  = isset($body['review']) ? sanitise($body['review']) : null;

    if ($rating < 1 || $rating > 5) error('Rating must be between 1 and 5.');

    // Verify order belongs to customer and is delivered
    $stmt = $db->prepare(
        'SELECT id, outlet_id, status FROM orders WHERE id = ? AND customer_username = ?'
    );
    $stmt->execute([$orderId, $user['sub']]);
    $order = $stmt->fetch();

    if (!$order) error('Order not found.', 404);
    if ($order['status'] !== 'delivered') error('You can only rate delivered orders.');

    // Check not already rated
    $check = $db->prepare('SELECT id FROM ratings WHERE order_id = ?');
    $check->execute([$orderId]);
    if ($check->fetch()) error('You have already rated this order.');

    $ins = $db->prepare(
        'INSERT INTO ratings (order_id, customer_username, outlet_id, rating, review)
         VALUES (?, ?, ?, ?, ?)'
    );
    $ins->execute([$orderId, $user['sub'], $order['outlet_id'], $rating, $review]);

    success([], 'Rating submitted. Thank you!');
}

if ($method === 'GET') {
    if (!empty($_GET['outlet_id'])) {
        $outletId = (int)$_GET['outlet_id'];
        $stmt = $db->prepare(
            'SELECT r.id, r.order_id, r.customer_username, r.rating, r.review, r.created_at
             FROM ratings r WHERE r.outlet_id = ?
             ORDER BY r.created_at DESC LIMIT 100'
        );
        $stmt->execute([$outletId]);
        $rows = $stmt->fetchAll();

        // Summary
        $sum = $db->prepare(
            'SELECT ROUND(AVG(rating),1) AS avg_rating, COUNT(*) AS total
             FROM ratings WHERE outlet_id = ?'
        );
        $sum->execute([$outletId]);
        $summary = $sum->fetch();

        success(['summary' => $summary, 'reviews' => $rows]);
    } else {
        error('outlet_id is required.');
    }
}
