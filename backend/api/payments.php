<?php
// Payment Service
// POST /api/payments/intents
// POST /api/payments/{intent_id}/confirm
// POST /api/payments/webhooks/{provider}
// GET  /api/payments/admin

require_once __DIR__ . '/../includes/helpers.php';

setCorsHeaders();
handlePreflight();

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

function paymentProviderEnabled(string $provider): bool {
    if ($provider === 'sandbox') return true;
    return configValue('PAYMENT_PROVIDER_ENABLED', 'false') === 'true';
}

function markPaymentSucceeded(PDO $db, int $intentId, ?string $providerReference = null): array {
    $stmt = $db->prepare(
        'SELECT pi.*, o.payment_status
         FROM payment_intents pi
         JOIN orders o ON o.id = pi.order_id
         WHERE pi.id = ?'
    );
    $stmt->execute([$intentId]);
    $intent = $stmt->fetch();
    if (!$intent) error('Payment intent not found.', 404);

    $db->beginTransaction();
    try {
        $ref = $providerReference ?: $intent['provider_reference'];
        $db->prepare(
            'UPDATE payment_intents
             SET status = "succeeded", provider_reference = COALESCE(?, provider_reference)
             WHERE id = ?'
        )->execute([$ref, $intentId]);

        $db->prepare(
            'UPDATE payments
             SET status = "paid", provider_reference = COALESCE(?, provider_reference), paid_at = COALESCE(paid_at, NOW())
             WHERE payment_intent_id = ?'
        )->execute([$ref, $intentId]);

        $db->prepare('UPDATE orders SET payment_status = "paid" WHERE id = ?')
           ->execute([(int)$intent['order_id']]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        error('Failed to settle payment.', 500);
    }

    enqueueNotification($intent['customer_username'], 'email', 'payment_receipt', [
        'order_id' => (int)$intent['order_id'],
        'amount' => (float)$intent['amount'],
        'currency' => $intent['currency'],
    ], 'customer');

    return $intent;
}

if (preg_match('#/payments/webhooks/([a-zA-Z0-9_-]+)#', $uri, $m)) {
    if ($method !== 'POST') error('Method not allowed.', 405);
    $provider = strtolower($m[1]);
    $raw = file_get_contents('php://input', false, null, 0, 65537);
    if ($raw === false || strlen($raw) > 65536) error('Webhook payload too large.', 413);
    $payload = json_decode($raw ?: '{}', true);
    if (!is_array($payload)) error('Webhook payload must be valid JSON.', 400);

    $secret = configValue('PAYMENT_WEBHOOK_SECRET_' . strtoupper($provider), configValue('PAYMENT_WEBHOOK_SECRET', ''));
    $signature = $_SERVER['HTTP_X_PROVIDER_SIGNATURE'] ?? $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
    $signatureValid = false;
    if ($secret !== '' && $signature !== '') {
        $expected = hash_hmac('sha256', $raw, $secret);
        $signatureValid = hash_equals($expected, $signature);
    }

    $eventId = (string)($payload['id'] ?? $payload['event_id'] ?? providerReference('evt'));
    $eventType = (string)($payload['type'] ?? $payload['event'] ?? 'payment.updated');
    $providerRef = (string)($payload['provider_reference'] ?? $payload['reference'] ?? $payload['tx_ref'] ?? '');

    try {
        $stmt = $db->prepare(
            'INSERT IGNORE INTO payment_events
             (provider, provider_event_id, provider_reference, event_type, signature_valid, payload_json)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$provider, $eventId, $providerRef ?: null, $eventType, $signatureValid ? 1 : 0, json_encode($payload)]);
    } catch (Throwable $e) {
        error('Webhook event could not be recorded.', 500);
    }

    if (!$signatureValid && $secret !== '') {
        error('Invalid webhook signature.', 401);
    }

    $status = strtolower((string)($payload['status'] ?? $payload['data']['status'] ?? ''));
    if ($providerRef !== '' && in_array($status, ['succeeded','successful','success','paid'], true)) {
        $stmt = $db->prepare('SELECT id FROM payment_intents WHERE provider = ? AND provider_reference = ? LIMIT 1');
        $stmt->execute([$provider, $providerRef]);
        $intent = $stmt->fetch();
        if ($intent) markPaymentSucceeded($db, (int)$intent['id'], $providerRef);
    }

    $db->prepare('UPDATE payment_events SET processed_at = NOW() WHERE provider = ? AND provider_event_id = ?')
       ->execute([$provider, $eventId]);
    success([], 'Webhook received.');
}

if (preg_match('#/payments/admin#', $uri)) {
    $user = requireAuth(['admin']);
    if ($method !== 'GET') error('Method not allowed.', 405);
    $stmt = $db->query(
        'SELECT p.id, p.order_id, p.provider, p.method, p.amount, p.currency, p.status,
                p.provider_reference, p.paid_at, p.created_at, o.customer_username
         FROM payments p
         JOIN orders o ON o.id = p.order_id
         ORDER BY p.created_at DESC LIMIT 300'
    );
    success(['payments' => $stmt->fetchAll()]);
}

if (preg_match('#/payments/(\d+)/confirm#', $uri, $m)) {
    if ($method !== 'POST') error('Method not allowed.', 405);
    $user = requireAuth(['customer', 'admin']);
    $intentId = (int)$m[1];
    $stmt = $db->prepare('SELECT * FROM payment_intents WHERE id = ?');
    $stmt->execute([$intentId]);
    $intent = $stmt->fetch();
    if (!$intent) error('Payment intent not found.', 404);
    if ($user['role'] === 'customer' && $intent['customer_username'] !== $user['sub']) error('Forbidden.', 403);
    if ($intent['provider'] !== 'sandbox') {
        error('Live provider payments must be confirmed by signed provider webhook.', 409);
    }
    markPaymentSucceeded($db, $intentId, $intent['provider_reference'] ?: providerReference('sandbox'));
    auditLog($user['sub'], $user['role'], 'confirm_payment', 'payment_intent', (string)$intentId);
    success(['payment_intent_id' => $intentId, 'status' => 'succeeded'], 'Payment confirmed.');
}

if (preg_match('#/payments/intents#', $uri)) {
    if ($method !== 'POST') error('Method not allowed.', 405);
    $user = requireAuth(['customer']);
    $body = getBody();
    requireFields($body, ['order_id', 'provider']);

    $orderId = (int)$body['order_id'];
    $provider = strtolower(trim((string)$body['provider']));
    $mobile = isset($body['mobile_number']) ? trim((string)$body['mobile_number']) : null;
    $idempotencyKey = $_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? ($body['idempotency_key'] ?? providerReference('pi'));

    $stmt = $db->prepare(
        'SELECT id, customer_username, payment_method, payment_status, total_amount
         FROM orders
         WHERE id = ? AND customer_username = ?'
    );
    $stmt->execute([$orderId, $user['sub']]);
    $order = $stmt->fetch();
    if (!$order) error('Order not found.', 404);
    if (!in_array($order['payment_method'], ['card','mobile_money'], true)) {
        error('This order does not require online payment.', 422);
    }
    if ($order['payment_status'] === 'paid') error('This order is already paid.', 409);
    if (!in_array($provider, ['sandbox','stripe','flutterwave','paystack','dpo','orange_money'], true)) {
        error('Unsupported payment provider.', 422);
    }
    if (!paymentProviderEnabled($provider)) {
        error('Payment provider is not enabled. Configure provider credentials before taking live payments.', 503);
    }

    $existing = $db->prepare(
        'SELECT * FROM payment_intents
         WHERE customer_username = ? AND idempotency_key = ?
         ORDER BY id DESC LIMIT 1'
    );
    $existing->execute([$user['sub'], $idempotencyKey]);
    $found = $existing->fetch();
    if ($found) success(['intent' => $found], 'Payment intent already exists.');

    $reference = providerReference($provider);
    $status = $provider === 'sandbox' ? 'requires_action' : 'pending';
    $checkoutUrl = $provider === 'sandbox' ? null : configValue('PAYMENT_HOSTED_CHECKOUT_BASE', '') . '?reference=' . urlencode($reference);

    $db->beginTransaction();
    try {
        $db->prepare(
            'INSERT INTO payment_intents
             (order_id, customer_username, provider, method, provider_reference, amount, currency,
              status, checkout_url, mobile_number, idempotency_key, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, "BWP", ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))'
        )->execute([
            $orderId, $user['sub'], $provider, $order['payment_method'], $reference,
            (float)$order['total_amount'], $status, $checkoutUrl, $mobile, $idempotencyKey,
        ]);
        $intentId = (int)$db->lastInsertId();
        $db->prepare(
            'INSERT INTO payments
             (order_id, payment_intent_id, provider, method, amount, currency, status, provider_reference)
             VALUES (?, ?, ?, ?, ?, "BWP", "pending", ?)'
        )->execute([$orderId, $intentId, $provider, $order['payment_method'], (float)$order['total_amount'], $reference]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        error('Could not create payment intent.', 500);
    }

    auditLog($user['sub'], 'customer', 'create_payment_intent', 'order', (string)$orderId, ['provider' => $provider]);
    success([
        'id' => $intentId,
        'provider' => $provider,
        'provider_reference' => $reference,
        'amount' => (float)$order['total_amount'],
        'currency' => 'BWP',
        'status' => $status,
        'checkout_url' => $checkoutUrl,
        'next_action' => $provider === 'sandbox'
            ? 'Confirm this sandbox payment to simulate provider settlement.'
            : 'Redirect to hosted checkout or wait for mobile money authorization.',
    ], 'Payment intent created.');
}

error('Unknown payment endpoint.', 404);
