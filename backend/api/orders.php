<?php
// POST /api/orders              – place a new order (customer)
// GET  /api/orders              – customer's own orders
// GET  /api/orders?outlet_id=n  – outlet's orders (staff)
// GET  /api/orders/{id}         – single order detail
// PATCH /api/orders/{id}        – update order status (staff)
// DELETE /api/orders/{id}       – cancel order (customer, only if pending_vendor)

require_once __DIR__ . '/../includes/helpers.php';

setCorsHeaders();
handlePreflight();

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

// Extract optional order ID from URL e.g. /api/orders/5
$orderId = null;
if (preg_match('/\/orders\/(\d+)/', $_SERVER['REQUEST_URI'], $m)) {
    $orderId = (int)$m[1];
}

function addOrderHistory(PDO $db, int $orderId, ?string $from, string $to, string $actor, string $role, ?string $note = null): void {
    $db->prepare(
        'INSERT INTO order_status_history (order_id, from_status, to_status, actor_username, actor_role, note)
         VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([$orderId, $from, $to, $actor, $role, $note]);
}

function loadOrderForAction(PDO $db, int $orderId): array {
    $stmt = $db->prepare(
        'SELECT o.*, fo.name AS outlet_name
         FROM orders o JOIN food_outlets fo ON fo.id = o.outlet_id
         WHERE o.id = ?'
    );
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    if (!$order) error('Order not found.', 404);
    return $order;
}

// ---- POST: vendor accepts / declines, customer confirms receipt --------
if ($method === 'POST' && $orderId && preg_match('#/orders/\d+/(vendor/accept|vendor/decline|confirm-received)$#', $_SERVER['REQUEST_URI'], $route)) {
    $action = $route[1];
    $body = getBody();
    $order = loadOrderForAction($db, $orderId);

    if ($action === 'vendor/accept' || $action === 'vendor/decline') {
        $user = requireAuth(['staff', 'admin']);
        if ($user['role'] === 'staff' && (int)$order['outlet_id'] !== (int)$user['outlet_id']) {
            error('Forbidden: this order belongs to a different outlet.', 403);
        }
        if ($order['status'] !== 'pending_vendor') {
            error('Only pending vendor orders can be accepted or declined.', 409);
        }
        if (in_array($order['payment_method'], ['card','mobile_money'], true) && $order['payment_status'] !== 'paid') {
            error('Online payment must be completed before accepting this order.', 409);
        }

        $newStatus = $action === 'vendor/accept' ? 'accepted' : 'declined_by_vendor';
        $reason = $action === 'vendor/decline'
            ? trim((string)($body['reason'] ?? 'Vendor declined the order.'))
            : null;

        $db->beginTransaction();
        try {
            $db->prepare('UPDATE orders SET status = ?, cancellation_reason = COALESCE(?, cancellation_reason) WHERE id = ?')
               ->execute([$newStatus, $reason, $orderId]);
            addOrderHistory($db, $orderId, $order['status'], $newStatus, $user['sub'], $user['role'], $reason);
            if ($newStatus === 'accepted' && $order['order_type'] === 'delivery') {
                $db->prepare(
                    'INSERT INTO deliveries (order_id, dropoff_address, status, eta_minutes, delivery_pin)
                     VALUES (?, ?, "unassigned", 30, ?)
                     ON DUPLICATE KEY UPDATE status = IF(status = "cancelled", "unassigned", status)'
                )->execute([$orderId, $order['delivery_address'], (string)random_int(1000, 9999)]);
            }
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            error('Could not update vendor decision.', 500);
        }

        if ($newStatus === 'accepted') {
            pushNotification($order['customer_username'], "Your order #{$orderId} was accepted by {$order['outlet_name']}.", $orderId);
            enqueueNotification('approved_drivers', 'push', 'driver_assigned', ['order_id' => $orderId], 'driver');
            auditLog($user['sub'], $user['role'], 'accept_order', 'order', (string)$orderId);
            success(['order_id' => $orderId, 'status' => $newStatus], 'Order accepted.');
        }

        pushNotification($order['customer_username'], "Your order #{$orderId} was declined by {$order['outlet_name']}. {$reason}", $orderId);
        auditLog($user['sub'], $user['role'], 'decline_order', 'order', (string)$orderId, ['reason' => $reason]);
        success(['order_id' => $orderId, 'status' => $newStatus], 'Order declined.');
    }

    if ($action === 'confirm-received') {
        $user = requireAuth(['customer']);
        if ($order['customer_username'] !== $user['sub']) error('Forbidden.', 403);
        if ($order['status'] !== 'delivered_pending_confirmation') {
            error('This order is not waiting for receipt confirmation.', 409);
        }
        $db->beginTransaction();
        try {
            $db->prepare('UPDATE orders SET status = "completed" WHERE id = ?')->execute([$orderId]);
            addOrderHistory($db, $orderId, $order['status'], 'completed', $user['sub'], 'customer', 'Customer confirmed receipt');
            $dstmt = $db->prepare('SELECT id, driver_id FROM deliveries WHERE order_id = ?');
            $dstmt->execute([$orderId]);
            $delivery = $dstmt->fetch();
            if ($delivery) {
                $db->prepare('UPDATE deliveries SET status = "delivered", delivered_at = COALESCE(delivered_at, NOW()) WHERE id = ?')
                   ->execute([(int)$delivery['id']]);
                if (!empty($delivery['driver_id'])) {
                    $db->prepare('UPDATE driver_availability SET current_delivery_id = NULL WHERE driver_id = ?')
                       ->execute([(int)$delivery['driver_id']]);
                }
            }
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            error('Could not confirm receipt.', 500);
        }
        auditLog($user['sub'], 'customer', 'confirm_received', 'order', (string)$orderId);
        success(['order_id' => $orderId, 'status' => 'completed'], 'Receipt confirmed.');
    }
}

// ---- POST: place order -----------------------------------------------
if ($method === 'POST' && !$orderId) {
    $user = requireAuth(['customer']);
    $body = getBody();
    requireFields($body, ['outlet_id', 'order_type', 'items']);

    $outletId      = (int)$body['outlet_id'];
    $orderType     = $body['order_type'];
    $items         = $body['items']; // [{ food_item_id, quantity }]
    // Audit fix: accept idempotency key to prevent duplicate orders on retry.
    $idempotencyKey = isset($body['idempotency_key']) ? trim((string)$body['idempotency_key']) : null;

    if (!in_array($orderType, ['delivery', 'pickup'], true)) {
        error('order_type must be delivery or pickup.');
    }
    if (!is_array($items) || count($items) === 0) {
        error('Order must contain at least one item.');
    }
    // Audit fix: cap items array to prevent memory exhaustion.
    if (count($items) > 50) {
        error('An order may contain at most 50 line items.', 422);
    }

    // Audit fix: idempotency — return original response if same key used within 24h.
    if ($idempotencyKey !== null && $idempotencyKey !== '') {
        $iStmt = $db->prepare(
            'SELECT id, total_amount, delivery_fee FROM orders
             WHERE customer_username = ? AND idempotency_key = ?
               AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)'
        );
        $iStmt->execute([$user['sub'], $idempotencyKey]);
        $existing = $iStmt->fetch();
        if ($existing) {
            success([
                'order_id'     => (int)$existing['id'],
                'total'        => (float)$existing['total_amount'],
                'delivery_fee' => (float)$existing['delivery_fee'],
                'idempotent'   => true,
            ], 'Order already placed (idempotent response).');
        }
    }

    // Delivery requires location
    $deliveryAddress = null;
    $deliveryLat     = null;
    $deliveryLng     = null;
    if ($orderType === 'delivery') {
        if (empty($body['delivery_address'])) error('delivery_address is required for delivery orders.');
        $deliveryAddress = trim((string)$body['delivery_address']);
        $deliveryLat     = isset($body['delivery_lat']) ? (float)$body['delivery_lat'] : null;
        $deliveryLng     = isset($body['delivery_lng']) ? (float)$body['delivery_lng'] : null;
    }

    $paymentMethod = isset($body['payment_method'])
        ? (string)$body['payment_method']
        : ($orderType === 'delivery' ? 'cash_on_delivery' : 'cash_on_pickup');

    if (!in_array($paymentMethod, ['cash_on_delivery', 'cash_on_pickup', 'card', 'mobile_money'], true)) {
        error('Invalid payment method.');
    }

    $specialNotes  = isset($body['special_notes']) ? trim((string)$body['special_notes']) : null;
    $tipAmount     = isset($body['tip_amount'])    ? max(0.0, (float)$body['tip_amount']) : 0.0;
    $promotionCode = isset($body['promotion_code']) ? strtoupper(trim((string)$body['promotion_code'])) : '';

    // Verify outlet and ordering mode
    $os = $db->prepare(
        'SELECT id, opening_time, closing_time, accepts_delivery, accepts_pickup, is_temporarily_closed
         FROM food_outlets WHERE id = ? AND is_active = 1'
    );
    $os->execute([$outletId]);
    $outlet = $os->fetch();
    if (!$outlet) error('Outlet not found.', 404);
    if ((int)$outlet['is_temporarily_closed'] === 1) error('This outlet is temporarily closed.');
    if ($orderType === 'delivery' && (int)$outlet['accepts_delivery'] !== 1) error('This outlet is not accepting delivery orders.');
    if ($orderType === 'pickup' && (int)$outlet['accepts_pickup'] !== 1) error('This outlet is not accepting pickup orders.');
    // Audit fix: use explicit Africa/Gaborone timezone for hours check.
    if ($outlet['opening_time'] && $outlet['closing_time']) {
        $gab  = new DateTimeZone(TIMEZONE);
        $now  = (new DateTime('now', $gab))->format('H:i:s');
        $open = $outlet['opening_time'];
        $close = $outlet['closing_time'];
        // Handle wrap-around schedules (closing < opening means crosses midnight)
        $isOpen = ($close >= $open)
            ? ($now >= $open && $now <= $close)
            : ($now >= $open || $now <= $close);
        if (!$isOpen) {
            error('This outlet is currently closed.');
        }
    }

    if (in_array($paymentMethod, ['card', 'mobile_money'], true)
        && configValue('PAYMENT_PROVIDER_ENABLED', 'false') !== 'true'
        && configValue('PAYMENT_PROVIDER', 'sandbox') !== 'sandbox') {
        error('Online payments are not enabled yet. Please use a cash option.');
    }

    // Validate items & compute total
    $total = 0.0;
    $maxPrep = 0;
    $validatedItems = [];
    foreach ($items as $item) {
        if (empty($item['food_item_id']) || empty($item['quantity'])) {
            error('Each item must have food_item_id and quantity.');
        }
        $fid = (int)$item['food_item_id'];
        $qty = (int)$item['quantity'];
        if ($qty < 1 || $qty > 20) {
            error('Item quantity must be between 1 and 20.');
        }

        // Audit fix: fetch name for snapshot; check availability without stock check here.
        $fi = $db->prepare(
            'SELECT id, name, price, prep_minutes, stock_qty
             FROM food_items
             WHERE id = ? AND outlet_id = ? AND is_available = 1'
        );
        $fi->execute([$fid, $outletId]);
        $foodItem = $fi->fetch();
        if (!$foodItem) error("Food item #{$fid} is not available at this outlet.", 404);
        if ($foodItem['stock_qty'] !== null && (int)$foodItem['stock_qty'] < $qty) {
            error("Insufficient stock for '{$foodItem['name']}' (available: {$foodItem['stock_qty']}).", 409);
        }

        $total += $foodItem['price'] * $qty;
        $maxPrep = max($maxPrep, (int)$foodItem['prep_minutes']);
        // Audit fix: snapshot item_name at placement time so history is immutable.
        $validatedItems[] = [
            'id'        => $fid,
            'qty'       => $qty,
            'price'     => $foodItem['price'],
            'item_name' => $foodItem['name'],
        ];
    }

    $discountAmount = 0.0;
    $promotionId = null;
    if ($promotionCode !== '') {
        if (!preg_match('/^[A-Z0-9_-]{2,32}$/', $promotionCode)) {
            error('Invalid promo code format.', 422);
        }

        $promoStmt = $db->prepare(
            'SELECT id, discount_type, discount_value, min_order_amount,
                    max_redemptions, max_uses_per_customer, valid_from, valid_until, is_active
             FROM promotions
             WHERE code = ?'
        );
        $promoStmt->execute([$promotionCode]);
        $promo = $promoStmt->fetch();
        if (!$promo || !(int)$promo['is_active']) {
            error('Promo code not found or no longer active.', 422);
        }

        $now = new DateTime('now', new DateTimeZone(TIMEZONE));
        if ($promo['valid_from'] && new DateTime($promo['valid_from']) > $now) {
            error('This promo code is not yet active.', 422);
        }
        if ($promo['valid_until'] && new DateTime($promo['valid_until']) < $now) {
            error('This promo code has expired.', 422);
        }
        if ($promo['min_order_amount'] !== null && $total < (float)$promo['min_order_amount']) {
            error('Minimum order amount not met for this promo code.', 422);
        }

        if ($promo['max_redemptions'] !== null) {
            $usedStmt = $db->prepare('SELECT COUNT(*) AS used FROM order_promotions WHERE promotion_id = ?');
            $usedStmt->execute([(int)$promo['id']]);
            if ((int)$usedStmt->fetch()['used'] >= (int)$promo['max_redemptions']) {
                error('This promo code has reached its usage limit.', 422);
            }
        }
        if ($promo['max_uses_per_customer'] !== null && (int)$promo['max_uses_per_customer'] > 0) {
            $perUserStmt = $db->prepare(
                'SELECT COUNT(*) AS used FROM order_promotions op
                 JOIN orders o ON o.id = op.order_id
                 WHERE op.promotion_id = ? AND o.customer_username = ?'
            );
            $perUserStmt->execute([(int)$promo['id'], $user['sub']]);
            if ((int)$perUserStmt->fetch()['used'] >= (int)$promo['max_uses_per_customer']) {
                error('You have already used this promo code the maximum number of times.', 422);
            }
        }

        $promotionId = (int)$promo['id'];
        $discountAmount = $promo['discount_type'] === 'flat'
            ? min((float)$promo['discount_value'], $total)
            : round($total * ((float)$promo['discount_value'] / 100), 2);
    }

    $deliveryFee   = $orderType === 'delivery' ? (float)DEFAULT_DELIVERY_FEE : 0.0;
    $grandTotal    = max(0.0, $total + $deliveryFee + $tipAmount - $discountAmount);
    $paymentStatus = in_array($paymentMethod, ['card', 'mobile_money'], true) ? 'pending' : 'not_required';

    $db->beginTransaction();
    try {
        $ins = $db->prepare(
            'INSERT INTO orders
             (customer_username, outlet_id, order_type, delivery_address, delivery_lat, delivery_lng,
              estimated_ready_at, delivery_fee, tip_amount, total_amount,
              payment_method, payment_status, special_notes, idempotency_key)
             VALUES (?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE), ?, ?, ?, ?, ?, ?, ?)'
        );
        $ins->execute([
            $user['sub'], $outletId, $orderType, $deliveryAddress,
            $deliveryLat, $deliveryLng, max(10, $maxPrep),
            $deliveryFee, $tipAmount, $grandTotal,
            $paymentMethod, $paymentStatus, $specialNotes,
            ($idempotencyKey !== '' ? $idempotencyKey : null),
        ]);
        $newOrderId = (int)$db->lastInsertId();

        // Audit fix: snapshot item_name; use atomic stock decrement (SELECT … FOR UPDATE).
        $insi = $db->prepare(
            'INSERT INTO order_items (order_id, food_item_id, item_name, quantity, unit_price)
             VALUES (?, ?, ?, ?, ?)'
        );
        $decrStmt = $db->prepare(
            'UPDATE food_items
             SET stock_qty = CASE WHEN stock_qty IS NULL THEN NULL ELSE stock_qty - ? END
             WHERE id = ? AND (stock_qty IS NULL OR stock_qty >= ?)'
        );
        foreach ($validatedItems as $vi) {
            $insi->execute([$newOrderId, $vi['id'], $vi['item_name'], $vi['qty'], $vi['price']]);
            $decrStmt->execute([$vi['qty'], $vi['id'], $vi['qty']]);
            $stockCheck = $db->prepare('SELECT stock_qty FROM food_items WHERE id = ?');
            $stockCheck->execute([$vi['id']]);
            $remainingStock = $stockCheck->fetch()['stock_qty'] ?? null;
            if ($remainingStock !== null && $decrStmt->rowCount() === 0) {
                $db->rollBack();
                error("'{$vi['item_name']}' just sold out. Please remove it and try again.", 409);
            }
        }
        addOrderHistory($db, $newOrderId, null, 'pending_vendor', $user['sub'], 'customer', 'Order placed');
        if ($promotionId !== null && $discountAmount > 0) {
            $db->prepare(
                'INSERT INTO order_promotions (order_id, promotion_id, discount_amount)
                 VALUES (?, ?, ?)'
            )->execute([$newOrderId, $promotionId, $discountAmount]);
        }
        if ($orderType === 'delivery') {
            $db->prepare(
                'INSERT INTO deliveries (order_id, dropoff_address, status, eta_minutes, delivery_pin)
                 VALUES (?, ?, "unassigned", 30, ?)'
            )->execute([$newOrderId, $deliveryAddress, (string)random_int(1000, 9999)]);
        }
        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        error('Failed to place order. Please try again.', 500);
    }

    // Notify customer
    pushNotification(
        $user['sub'],
        "Your order #{$newOrderId} has been sent to the vendor for confirmation.",
        $newOrderId
    );

    auditLog($user['sub'], 'customer', 'place_order', 'order', (string)$newOrderId, ['total' => $grandTotal]);
    success([
        'order_id' => $newOrderId,
        'subtotal' => $total,
        'delivery_fee' => $deliveryFee,
        'discount_amount' => $discountAmount,
        'total' => $grandTotal,
    ], 'Order placed successfully.');
}

// ---- GET: list or single order ----------------------------------------
if ($method === 'GET') {
    $user = requireAuth(['customer', 'staff', 'admin']);

    if ($orderId) {
        // Single order
        $stmt = $db->prepare(
            'SELECT o.*, fo.name AS outlet_name FROM orders o
             JOIN food_outlets fo ON fo.id = o.outlet_id
             WHERE o.id = ?'
        );
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order) error('Order not found.', 404);

        // Customers can only see their own orders
        if ($user['role'] === 'customer' && $order['customer_username'] !== $user['sub']) {
            error('Forbidden.', 403);
        }
        // Staff can only see their outlet's orders
        if ($user['role'] === 'staff' && (int)$order['outlet_id'] !== (int)$user['outlet_id']) {
            error('Forbidden.', 403);
        }

        // Fetch items
        $istmt = $db->prepare(
            'SELECT oi.quantity, oi.unit_price, COALESCE(NULLIF(oi.item_name, ""), fi.name) AS name, fi.image_url
             FROM order_items oi JOIN food_items fi ON fi.id = oi.food_item_id
             WHERE oi.order_id = ?'
        );
        $istmt->execute([$orderId]);
        $order['items'] = $istmt->fetchAll();

        // Fetch rating if exists
        $rstmt = $db->prepare('SELECT rating, review FROM ratings WHERE order_id = ?');
        $rstmt->execute([$orderId]);
        $order['rating'] = $rstmt->fetch() ?: null;

        $pstmt = $db->prepare(
            'SELECT id, payment_intent_id, provider, method, amount, currency, status, provider_reference, paid_at, created_at
             FROM payments WHERE order_id = ? ORDER BY created_at DESC'
        );
        $pstmt->execute([$orderId]);
        $order['payments'] = $pstmt->fetchAll();

        $dstmt = $db->prepare(
            'SELECT d.id, d.driver_id, d.status, d.eta_minutes, d.delivery_pin,
                    d.assigned_at, d.picked_up_at, d.delivered_at,
                    dr.full_name AS driver_name, dr.vehicle_type
             FROM deliveries d
             LEFT JOIN drivers dr ON dr.id = d.driver_id
             WHERE d.order_id = ?'
        );
        $dstmt->execute([$orderId]);
        $order['delivery'] = $dstmt->fetch() ?: null;

        success($order);
    } else {
        // List orders
        if ($user['role'] === 'customer') {
            $stmt = $db->prepare(
                'SELECT o.id, o.outlet_id, fo.name AS outlet_name, o.order_type,
                        o.status, o.payment_status, o.total_amount, o.created_at, o.updated_at
                 FROM orders o JOIN food_outlets fo ON fo.id = o.outlet_id
                 WHERE o.customer_username = ?
                 ORDER BY o.created_at DESC'
            );
            $stmt->execute([$user['sub']]);
        } elseif ($user['role'] === 'staff') {
            $validStatuses = ['pending_vendor','accepted','preparing','ready_for_pickup','driver_assigned','picked_up','delivered_pending_confirmation','completed','declined_by_vendor','cancelled'];
            $statusFilter  = (isset($_GET['status']) && in_array($_GET['status'], $validStatuses, true))
                ? $_GET['status'] : null;
            $sql = 'SELECT o.id, o.customer_username, o.order_type, o.status,
                           o.payment_status, o.total_amount, o.delivery_address, o.special_notes,
                           o.created_at, o.updated_at
                    FROM orders o WHERE o.outlet_id = ?';
            $params = [(int)$user['outlet_id']];
            if ($statusFilter) {
                $sql .= ' AND o.status = ?';
                $params[] = $statusFilter;
            }
            $sql .= ' ORDER BY o.created_at DESC';
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
        } else {
            // Admin: all orders
            $stmt = $db->query(
                'SELECT o.id, o.customer_username, o.outlet_id, fo.name AS outlet_name,
                        o.order_type, o.status, o.payment_status, o.total_amount, o.created_at
                 FROM orders o JOIN food_outlets fo ON fo.id = o.outlet_id
                 ORDER BY o.created_at DESC LIMIT 500'
            );
        }
        success($stmt->fetchAll());
    }
}

// ---- PATCH: update order status (staff/admin) -------------------------
if ($method === 'PATCH') {
    $user = requireAuth(['staff', 'admin']);
    if (!$orderId) error('Order ID required in URL.');

    $body = getBody();
    requireFields($body, ['status']);
    $newStatus = sanitise($body['status']);

    $validStatuses = ['pending_vendor','accepted','preparing','ready_for_pickup','driver_assigned','picked_up','delivered_pending_confirmation','completed','declined_by_vendor','cancelled'];
    if (!in_array($newStatus, $validStatuses, true)) {
        error('Invalid status value.');
    }

    // Load order
    $stmt = $db->prepare('SELECT * FROM orders WHERE id = ?');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    if (!$order) error('Order not found.', 404);

    if ($user['role'] === 'staff' && (int)$order['outlet_id'] !== (int)$user['outlet_id']) {
        error('Forbidden: this order belongs to a different outlet.', 403);
    }

    $legalTransitions = [
        'pending_vendor' => ['accepted', 'declined_by_vendor', 'cancelled'],
        'accepted'       => ['preparing', 'cancelled'],
        'preparing'      => ['ready_for_pickup', 'cancelled'],
        'ready_for_pickup' => ['driver_assigned', 'picked_up', 'completed', 'cancelled'],
        'driver_assigned' => ['picked_up', 'cancelled'],
        'picked_up'      => ['delivered_pending_confirmation', 'cancelled'],
        'delivered_pending_confirmation' => ['completed'],
        'completed'      => [],
        'declined_by_vendor' => [],
        'cancelled'      => [],
    ];
    $oldStatus = $order['status'];
    if (in_array($newStatus, ['accepted','preparing','ready_for_pickup','driver_assigned','picked_up','delivered_pending_confirmation','completed'], true)
        && in_array($order['payment_method'], ['card','mobile_money'], true)
        && $order['payment_status'] !== 'paid') {
        error('Online payment must be completed before this order can progress.', 409);
    }
    if ($newStatus !== $oldStatus && !in_array($newStatus, $legalTransitions[$oldStatus] ?? [], true)) {
        error("Cannot move order from {$oldStatus} to {$newStatus}.", 409);
    }

    $cancelReason = isset($body['cancellation_reason']) ? sanitise($body['cancellation_reason']) : null;
    if ($newStatus === 'cancelled' && !$cancelReason) {
        $cancelReason = 'Cancelled by ' . $user['role'];
    }

    $upd = $db->prepare('UPDATE orders SET status = ?, cancellation_reason = COALESCE(?, cancellation_reason) WHERE id = ?');
    $upd->execute([$newStatus, $cancelReason, $orderId]);
    $db->prepare(
        'INSERT INTO order_status_history (order_id, from_status, to_status, actor_username, actor_role, note)
         VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([$orderId, $oldStatus, $newStatus, $user['sub'], $user['role'], $cancelReason]);

    // Notify customer
    $messages = [
        'accepted' => "Your order #{$orderId} was accepted by the vendor.",
        'preparing' => "Your order #{$orderId} is now being prepared.",
        'ready_for_pickup' => "Your order #{$orderId} is ready for " .
                        ($order['order_type'] === 'pickup' ? 'pickup.' : 'driver pickup.'),
        'driver_assigned' => "A driver has been assigned to order #{$orderId}.",
        'picked_up' => "Your order #{$orderId} has been picked up.",
        'delivered_pending_confirmation' => "Your order #{$orderId} was marked delivered. Please confirm receipt.",
        'completed' => "Your order #{$orderId} is complete. Enjoy your meal!",
        'declined_by_vendor' => "Your order #{$orderId} was declined by the vendor.",
        'cancelled' => "Your order #{$orderId} has been cancelled.",
    ];
    if (isset($messages[$newStatus])) {
        pushNotification($order['customer_username'], $messages[$newStatus], $orderId);
    }

    auditLog($user['sub'], $user['role'], 'update_order_status', 'order', (string)$orderId, [
        'from' => $oldStatus,
        'to' => $newStatus,
    ]);
    success(['order_id' => $orderId, 'status' => $newStatus], 'Order status updated.');
}

// ---- DELETE: cancel order (customer, pending only) --------------------
if ($method === 'DELETE') {
    $user = requireAuth(['customer']);
    if (!$orderId) error('Order ID required in URL.');

    $stmt = $db->prepare('SELECT * FROM orders WHERE id = ? AND customer_username = ?');
    $stmt->execute([$orderId, $user['sub']]);
    $order = $stmt->fetch();
    if (!$order) error('Order not found.', 404);

    if ($order['status'] !== 'pending_vendor') {
        error('Only orders waiting for vendor confirmation can be cancelled.');
    }

    $db->prepare('UPDATE orders SET status = ?, cancellation_reason = ? WHERE id = ?')
        ->execute(['cancelled', 'Customer cancelled before vendor accepted', $orderId]);
    addOrderHistory($db, $orderId, 'pending_vendor', 'cancelled', $user['sub'], 'customer', 'Customer cancelled before vendor accepted');
    pushNotification($user['sub'], "Your order #{$orderId} has been cancelled.", $orderId);
    auditLog($user['sub'], 'customer', 'cancel_order', 'order', (string)$orderId);
    success([], 'Order cancelled.');
}
