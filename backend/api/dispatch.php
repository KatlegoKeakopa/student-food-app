<?php
// Dispatch Service
// POST /api/dispatch/assign
// POST /api/dispatch/accept
// POST /api/dispatch/reject
// PATCH /api/deliveries/{id}/status
// GET  /api/orders/{id}/tracking
// GET  /api/admin/dispatch/live

require_once __DIR__ . '/../includes/helpers.php';

setCorsHeaders();
handlePreflight();

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

if (preg_match('#/orders/(\d+)/tracking#', $uri, $m)) {
    if ($method !== 'GET') error('Method not allowed.', 405);
    $user = requireAuth(['customer', 'staff', 'admin']);
    $orderId = (int)$m[1];
    $stmt = $db->prepare('SELECT * FROM orders WHERE id = ?');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    if (!$order) error('Order not found.', 404);
    if ($user['role'] === 'customer' && $order['customer_username'] !== $user['sub']) error('Forbidden.', 403);
    if ($user['role'] === 'staff' && (int)$order['outlet_id'] !== (int)$user['outlet_id']) error('Forbidden.', 403);

    $stmt = $db->prepare(
        'SELECT d.*, dr.full_name AS driver_name, dr.vehicle_type,
                dl.lat, dl.lng, dl.accuracy_m, dl.heading, dl.created_at AS location_at
         FROM deliveries d
         LEFT JOIN drivers dr ON dr.id = d.driver_id
         LEFT JOIN driver_locations dl ON dl.id = (
             SELECT id FROM driver_locations
             WHERE delivery_id = d.id
             ORDER BY created_at DESC LIMIT 1
         )
         WHERE d.order_id = ?'
    );
    $stmt->execute([$orderId]);
    $delivery = $stmt->fetch();
    success(['order' => [
        'id' => (int)$order['id'],
        'status' => $order['status'],
        'delivery_address' => $order['delivery_address'],
        'estimated_ready_at' => $order['estimated_ready_at'],
    ], 'delivery' => $delivery]);
}

if (preg_match('#/dispatch/offers#', $uri)) {
    if ($method !== 'GET') error('Method not allowed.', 405);
    $driverId = isset($_GET['driver_id']) ? (int)$_GET['driver_id'] : 0;
    $token = isset($_GET['api_token']) ? trim((string)$_GET['api_token']) : '';
    $driver = requireDriverToken($db, $driverId, $token);
    $stmt = $db->prepare(
        'SELECT d.id AS delivery_id, d.order_id, d.status, d.dropoff_address, d.eta_minutes,
                o.total_amount, o.delivery_fee, o.tip_amount, o.created_at,
                fo.name AS outlet_name
         FROM deliveries d
         JOIN orders o ON o.id = d.order_id
         JOIN food_outlets fo ON fo.id = o.outlet_id
         WHERE o.status = "accepted"
           AND d.status IN ("unassigned","offered")
           AND (d.driver_id IS NULL OR d.driver_id = ?)
         ORDER BY o.created_at ASC
         LIMIT 25'
    );
    $stmt->execute([(int)$driver['id']]);
    $active = $db->prepare(
        'SELECT d.id AS delivery_id, d.order_id, d.status, d.dropoff_address, d.eta_minutes,
                o.status AS order_status, o.delivery_fee, o.tip_amount,
                fo.name AS outlet_name
         FROM deliveries d
         JOIN orders o ON o.id = d.order_id
         JOIN food_outlets fo ON fo.id = o.outlet_id
         WHERE d.driver_id = ?
           AND d.status NOT IN ("delivered","cancelled","disputed")
           AND o.status IN ("driver_assigned","picked_up","delivered_pending_confirmation")
         ORDER BY d.updated_at DESC
         LIMIT 1'
    );
    $active->execute([(int)$driver['id']]);
    success(['offers' => $stmt->fetchAll(), 'active_delivery' => $active->fetch() ?: null]);
}

if (preg_match('#/admin/dispatch/live#', $uri)) {
    if ($method !== 'GET') error('Method not allowed.', 405);
    requireAuth(['admin']);
    $stmt = $db->query(
        'SELECT d.id, d.order_id, d.status, d.eta_minutes, d.updated_at,
                dr.full_name AS driver_name, dr.phone,
                da.is_online, da.current_lat, da.current_lng, da.last_seen_at,
                o.customer_username, o.delivery_address
         FROM deliveries d
         LEFT JOIN drivers dr ON dr.id = d.driver_id
         LEFT JOIN driver_availability da ON da.driver_id = dr.id
         JOIN orders o ON o.id = d.order_id
         WHERE d.status NOT IN ("delivered","cancelled")
         ORDER BY d.updated_at DESC LIMIT 200'
    );
    success(['deliveries' => $stmt->fetchAll()]);
}

if (preg_match('#/deliveries/(\d+)/status#', $uri, $m)) {
    if ($method !== 'PATCH') error('Method not allowed.', 405);
    $body = getBody();
    requireFields($body, ['status']);
    $status = trim((string)$body['status']);
    $valid = ['accepted','heading_to_vendor','arrived_vendor','picked_up','heading_to_customer','arrived_customer','delivered','disputed','cancelled'];
    if (!in_array($status, $valid, true)) error('Invalid delivery status.', 422);
    $deliveryId = (int)$m[1];

    $driver = null;
    if (!empty($body['driver_id']) || !empty($body['api_token'])) {
        $driver = requireDriverToken($db, (int)($body['driver_id'] ?? 0), trim((string)($body['api_token'] ?? '')));
    } else {
        $adminOrStaff = requireAuth(['admin', 'staff']);
    }

    $stmt = $db->prepare(
        'SELECT d.*, o.customer_username, o.tip_amount, o.delivery_fee, o.status AS order_status
         FROM deliveries d
         JOIN orders o ON o.id = d.order_id
         WHERE d.id = ?'
    );
    $stmt->execute([$deliveryId]);
    $delivery = $stmt->fetch();
    if (!$delivery) error('Delivery not found.', 404);
    if ($driver && (int)$delivery['driver_id'] !== (int)$driver['id']) error('Delivery not assigned to this driver.', 403);

    $orderStatus = null;
    if ($status === 'picked_up' || $status === 'heading_to_customer') $orderStatus = 'picked_up';
    if ($status === 'delivered') $orderStatus = 'delivered_pending_confirmation';
    if ($status === 'cancelled') $orderStatus = 'cancelled';

    $db->beginTransaction();
    try {
        $db->prepare(
            'UPDATE deliveries
             SET status = ?,
                 picked_up_at = IF(? = "picked_up", NOW(), picked_up_at),
                 delivered_at = IF(? = "delivered", NOW(), delivered_at)
             WHERE id = ?'
        )->execute([$status, $status, $status, $deliveryId]);
        if ($orderStatus) {
            $db->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$orderStatus, (int)$delivery['order_id']]);
            $db->prepare(
                'INSERT INTO order_status_history (order_id, from_status, to_status, actor_username, actor_role, note)
                 VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([
                (int)$delivery['order_id'],
                $delivery['order_status'],
                $orderStatus,
                $driver ? ('driver:' . $driver['id']) : $adminOrStaff['sub'],
                $driver ? 'driver' : $adminOrStaff['role'],
                'Delivery status: ' . $status,
            ]);
        }
        if ($status === 'delivered' && (int)$delivery['driver_id'] > 0) {
            $db->prepare(
                'INSERT INTO driver_earnings
                 (driver_id, delivery_id, delivery_fee, tip_amount)
                 VALUES (?, ?, ?, ?)'
            )->execute([(int)$delivery['driver_id'], $deliveryId, (float)$delivery['delivery_fee'], (float)$delivery['tip_amount']]);
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        error('Could not update delivery status.', 500);
    }

    pushNotification($delivery['customer_username'], "Delivery update for order #{$delivery['order_id']}: {$status}.", (int)$delivery['order_id']);
    success(['delivery_id' => $deliveryId, 'status' => $status], 'Delivery status updated.');
}

if (preg_match('#/dispatch/assign#', $uri)) {
    if ($method !== 'POST') error('Method not allowed.', 405);
    $user = requireAuth(['admin', 'staff']);
    $body = getBody();
    requireFields($body, ['order_id']);
    $orderId = (int)$body['order_id'];

    $stmt = $db->prepare('SELECT * FROM orders WHERE id = ? AND order_type = "delivery"');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    if (!$order) error('Delivery order not found.', 404);
    if ($user['role'] === 'staff' && (int)$order['outlet_id'] !== (int)$user['outlet_id']) error('Forbidden.', 403);

    $driverId = isset($body['driver_id']) ? (int)$body['driver_id'] : 0;
    if ($driverId <= 0) {
        $sql = 'SELECT d.id
                FROM drivers d
                JOIN driver_availability da ON da.driver_id = d.id
                WHERE d.verification_status = "approved"
                  AND da.is_online = 1
                  AND da.current_delivery_id IS NULL
                ORDER BY ';
        $sql .= ($order['delivery_lat'] !== null && $order['delivery_lng'] !== null)
            ? 'ABS(da.current_lat - ?) + ABS(da.current_lng - ?) ASC, da.last_seen_at DESC'
            : 'da.last_seen_at DESC';
        $sql .= ' LIMIT 1';
        $ds = $db->prepare($sql);
        $params = ($order['delivery_lat'] !== null && $order['delivery_lng'] !== null)
            ? [(float)$order['delivery_lat'], (float)$order['delivery_lng']]
            : [];
        $ds->execute($params);
        $driverId = (int)($ds->fetch()['id'] ?? 0);
    }
    if ($driverId <= 0) error('No available driver found.', 409);

    $db->beginTransaction();
    try {
        $pin = (string)random_int(1000, 9999);
        $db->prepare(
            'INSERT INTO deliveries
             (order_id, driver_id, dropoff_address, status, eta_minutes, delivery_pin, assigned_at)
             VALUES (?, ?, ?, "offered", 25, ?, NOW())
             ON DUPLICATE KEY UPDATE
                driver_id = VALUES(driver_id),
                status = "offered",
                eta_minutes = VALUES(eta_minutes),
                assigned_at = NOW()'
        )->execute([$orderId, $driverId, $order['delivery_address'], $pin]);
        $deliveryId = (int)$db->lastInsertId();
        if ($deliveryId === 0) {
            $s = $db->prepare('SELECT id FROM deliveries WHERE order_id = ?');
            $s->execute([$orderId]);
            $deliveryId = (int)$s->fetch()['id'];
        }
        $db->prepare('UPDATE driver_availability SET current_delivery_id = ? WHERE driver_id = ?')
           ->execute([$deliveryId, $driverId]);
        $db->prepare('INSERT INTO dispatch_attempts (order_id, driver_id, response) VALUES (?, ?, "offered")')
           ->execute([$orderId, $driverId]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        error('Could not assign driver.', 500);
    }

    auditLog($user['sub'], $user['role'], 'assign_driver', 'order', (string)$orderId, ['driver_id' => $driverId]);
    success(['delivery_id' => $deliveryId, 'driver_id' => $driverId, 'delivery_pin' => $pin], 'Driver offered delivery.');
}

if (preg_match('#/dispatch/(accept|reject)#', $uri, $m)) {
    if ($method !== 'POST') error('Method not allowed.', 405);
    $body = getBody();
    requireFields($body, ['delivery_id','driver_id','api_token']);
    $driver = requireDriverToken($db, (int)$body['driver_id'], trim((string)$body['api_token']));
    $deliveryId = (int)$body['delivery_id'];
    $response = $m[1] === 'accept' ? 'accepted' : 'rejected';
    $active = $db->prepare(
        'SELECT current_delivery_id
         FROM driver_availability
         WHERE driver_id = ? AND is_online = 1'
    );
    $active->execute([(int)$driver['id']]);
    $availability = $active->fetch();
    if (!$availability) error('Driver must be online to accept deliveries.', 409);
    if ($response === 'accepted' && !empty($availability['current_delivery_id']) && (int)$availability['current_delivery_id'] !== $deliveryId) {
        error('Complete the active delivery before accepting another offer.', 409);
    }

    $stmt = $db->prepare(
        'SELECT d.*, o.status AS order_status, o.customer_username
         FROM deliveries d
         JOIN orders o ON o.id = d.order_id
         WHERE d.id = ? AND (d.driver_id IS NULL OR d.driver_id = ?)'
    );
    $stmt->execute([$deliveryId, (int)$driver['id']]);
    $delivery = $stmt->fetch();
    if (!$delivery) error('Delivery not found for this driver.', 404);
    if ($delivery['order_status'] !== 'accepted') error('This order is not ready for driver acceptance.', 409);

    $db->prepare('UPDATE deliveries SET status = ?, driver_id = ? WHERE id = ?')
       ->execute([$response === 'accepted' ? 'accepted' : 'unassigned', $response === 'accepted' ? (int)$driver['id'] : null, $deliveryId]);
    $db->prepare(
        'UPDATE dispatch_attempts SET response = ?, responded_at = NOW()
         WHERE order_id = ? AND driver_id = ? AND response = "offered"'
    )->execute([$response, (int)$delivery['order_id'], (int)$driver['id']]);
    if ($response === 'accepted') {
        $db->prepare('UPDATE driver_availability SET current_delivery_id = ? WHERE driver_id = ?')
           ->execute([$deliveryId, (int)$driver['id']]);
        $db->prepare('UPDATE orders SET status = "driver_assigned" WHERE id = ?')
           ->execute([(int)$delivery['order_id']]);
        $db->prepare(
            'INSERT INTO order_status_history (order_id, from_status, to_status, actor_username, actor_role, note)
             VALUES (?, ?, "driver_assigned", ?, "driver", "Driver accepted delivery")'
        )->execute([(int)$delivery['order_id'], $delivery['order_status'], 'driver:' . $driver['id']]);
        pushNotification($delivery['customer_username'], "A delivery driver has accepted order #{$delivery['order_id']}.", (int)$delivery['order_id']);
    } else {
        $db->prepare('UPDATE driver_availability SET current_delivery_id = NULL WHERE driver_id = ? AND current_delivery_id = ?')
           ->execute([(int)$driver['id'], $deliveryId]);
    }
    success(['delivery_id' => $deliveryId, 'response' => $response], 'Dispatch response recorded.');
}

error('Unknown dispatch endpoint.', 404);
