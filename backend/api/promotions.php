<?php
// POST /api/promotions/apply
// Body: { code, order_subtotal }
// Auth: customer required
// Returns: { valid, discount_amount, description } or { valid: false, reason }

require_once __DIR__ . '/../includes/helpers.php';

setCorsHeaders();
handlePreflight();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error('Method not allowed.', 405);
}

$user = requireAuth(['customer']);
$body = getBody();
requireFields($body, ['code', 'order_subtotal']);

$code        = strtoupper(trim((string)$body['code']));
$subtotal    = max(0.0, (float)$body['order_subtotal']);

if (strlen($code) < 2 || strlen($code) > 32) {
    error('Invalid promo code format.', 422);
}

$db = getDB();

// Fetch promotion
$stmt = $db->prepare(
    'SELECT id, code, discount_type, discount_value, min_order_amount,
            max_redemptions, max_uses_per_customer,
            valid_from, valid_until, is_active
     FROM promotions
     WHERE code = ?'
);
$stmt->execute([$code]);
$promo = $stmt->fetch();

if (!$promo || !(int)$promo['is_active']) {
    respond(['valid' => false, 'reason' => 'Promo code not found or no longer active.']);
}

// Date range check
$now = new DateTime('now', new DateTimeZone(TIMEZONE));
if ($promo['valid_from'] && new DateTime($promo['valid_from']) > $now) {
    respond(['valid' => false, 'reason' => 'This promo code is not yet active.']);
}
if ($promo['valid_until'] && new DateTime($promo['valid_until']) < $now) {
    respond(['valid' => false, 'reason' => 'This promo code has expired.']);
}

// Minimum order check
if ($promo['min_order_amount'] !== null && $subtotal < (float)$promo['min_order_amount']) {
    $min = number_format((float)$promo['min_order_amount'], 2);
    respond(['valid' => false, 'reason' => "Minimum order of P{$min} required for this code."]);
}

// Global redemption cap
if ($promo['max_redemptions'] !== null) {
    $usedStmt = $db->prepare(
        'SELECT COUNT(*) AS used FROM order_promotions WHERE promotion_id = ?'
    );
    $usedStmt->execute([(int)$promo['id']]);
    if ((int)$usedStmt->fetch()['used'] >= (int)$promo['max_redemptions']) {
        respond(['valid' => false, 'reason' => 'This promo code has reached its usage limit.']);
    }
}

// Per-customer cap
if ($promo['max_uses_per_customer'] !== null && (int)$promo['max_uses_per_customer'] > 0) {
    $perUserStmt = $db->prepare(
        'SELECT COUNT(*) AS used FROM order_promotions op
         JOIN orders o ON o.id = op.order_id
         WHERE op.promotion_id = ? AND o.customer_username = ?'
    );
    $perUserStmt->execute([(int)$promo['id'], $user['sub']]);
    if ((int)$perUserStmt->fetch()['used'] >= (int)$promo['max_uses_per_customer']) {
        respond(['valid' => false, 'reason' => "You have already used this promo code the maximum number of times."]);
    }
}

// Compute discount
$discountAmount = 0.0;
if ($promo['discount_type'] === 'flat') {
    $discountAmount = min((float)$promo['discount_value'], $subtotal);
} elseif ($promo['discount_type'] === 'percent') {
    $discountAmount = round($subtotal * ((float)$promo['discount_value'] / 100), 2);
}

respond([
    'valid'           => true,
    'discount_amount' => $discountAmount,
    'description'     => "Code {$code}: " . ($promo['discount_type'] === 'flat'
        ? 'P' . number_format($discountAmount, 2) . ' off your order'
        : $promo['discount_value'] . '% off your order'),
]);
