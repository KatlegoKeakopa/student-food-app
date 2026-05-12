<?php
// Driver runtime API
// POST /api/drivers/availability
// POST /api/drivers/location
// GET  /api/drivers/earnings

require_once __DIR__ . '/../includes/helpers.php';

setCorsHeaders();
handlePreflight();

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

function driverAuthFromBody(PDO $db, array $body): array {
    $driverId = isset($body['driver_id']) ? (int)$body['driver_id'] : 0;
    $token = isset($body['api_token']) ? trim((string)$body['api_token']) : '';
    return requireDriverToken($db, $driverId, $token);
}

if (preg_match('#/drivers/availability#', $uri)) {
    if ($method !== 'POST') error('Method not allowed.', 405);
    $body = getBody();
    $driver = driverAuthFromBody($db, $body);
    $online = !empty($body['is_online']) ? 1 : 0;
    $lat = isset($body['lat']) ? (float)$body['lat'] : null;
    $lng = isset($body['lng']) ? (float)$body['lng'] : null;
    $stmt = $db->prepare(
        'INSERT INTO driver_availability (driver_id, is_online, current_lat, current_lng, last_seen_at)
         VALUES (?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE
            is_online = VALUES(is_online),
            current_lat = COALESCE(VALUES(current_lat), current_lat),
            current_lng = COALESCE(VALUES(current_lng), current_lng),
            last_seen_at = NOW()'
    );
    $stmt->execute([(int)$driver['id'], $online, $lat, $lng]);
    success(['driver_id' => (int)$driver['id'], 'is_online' => $online], 'Availability updated.');
}

if (preg_match('#/drivers/location#', $uri)) {
    if ($method !== 'POST') error('Method not allowed.', 405);
    $body = getBody();
    $driver = driverAuthFromBody($db, $body);
    requireFields($body, ['lat','lng']);
    $lat = (float)$body['lat'];
    $lng = (float)$body['lng'];
    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) error('Invalid GPS coordinates.', 422);

    $deliveryId = isset($body['delivery_id']) ? (int)$body['delivery_id'] : null;
    if ($deliveryId) {
        $ds = $db->prepare('SELECT id FROM deliveries WHERE id = ? AND driver_id = ?');
        $ds->execute([$deliveryId, (int)$driver['id']]);
        if (!$ds->fetch()) error('Delivery not assigned to this driver.', 403);
    }

    $db->prepare(
        'INSERT INTO driver_locations (driver_id, delivery_id, lat, lng, accuracy_m, heading, speed_mps)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        (int)$driver['id'], $deliveryId, $lat, $lng,
        isset($body['accuracy_m']) ? (float)$body['accuracy_m'] : null,
        isset($body['heading']) ? (int)$body['heading'] : null,
        isset($body['speed_mps']) ? (float)$body['speed_mps'] : null,
    ]);
    $db->prepare(
        'UPDATE driver_availability
         SET current_lat = ?, current_lng = ?, last_seen_at = NOW()
         WHERE driver_id = ?'
    )->execute([$lat, $lng, (int)$driver['id']]);
    success([], 'Location updated.');
}

if (preg_match('#/drivers/earnings#', $uri)) {
    if ($method !== 'GET') error('Method not allowed.', 405);
    $driverId = isset($_GET['driver_id']) ? (int)$_GET['driver_id'] : 0;
    $token = isset($_GET['api_token']) ? trim((string)$_GET['api_token']) : '';
    $driver = requireDriverToken($db, $driverId, $token);
    $stmt = $db->prepare(
        'SELECT de.id, de.delivery_id, de.delivery_fee, de.tip_amount, de.bonus_amount,
                de.payout_status, de.created_at, d.order_id
         FROM driver_earnings de
         JOIN deliveries d ON d.id = de.delivery_id
         WHERE de.driver_id = ?
         ORDER BY de.created_at DESC LIMIT 100'
    );
    $stmt->execute([(int)$driver['id']]);
    success(['earnings' => $stmt->fetchAll()]);
}

error('Unknown driver endpoint.', 404);
