<?php
// Refund Service
// POST /api/refunds       – admin creates/approves a refund
// GET  /api/refunds       – admin refund ledger

require_once __DIR__ . '/../includes/helpers.php';

setCorsHeaders();
handlePreflight();

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$user = requireAuth(['admin']);

if ($method === 'GET') {
    $stmt = $db->query(
        'SELECT r.id, r.order_id, r.payment_id, r.amount, r.reason, r.status,
                r.provider_reference, r.requested_by, r.approved_by, r.created_at,
                o.customer_username
         FROM refunds r
         JOIN orders o ON o.id = r.order_id
         ORDER BY r.created_at DESC LIMIT 300'
    );
    success(['refunds' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    $body = getBody();
    requireFields($body, ['payment_id', 'amount', 'reason']);
    $paymentId = (int)$body['payment_id'];
    $amount = round((float)$body['amount'], 2);
    $reason = trim((string)$body['reason']);
    if ($amount <= 0) error('Refund amount must be greater than zero.', 422);

    $stmt = $db->prepare(
        'SELECT p.*, o.customer_username
         FROM payments p
         JOIN orders o ON o.id = p.order_id
         WHERE p.id = ? AND p.status IN ("paid","partially_refunded")'
    );
    $stmt->execute([$paymentId]);
    $payment = $stmt->fetch();
    if (!$payment) error('Paid payment not found.', 404);

    $rs = $db->prepare(
        'SELECT COALESCE(SUM(amount),0) AS refunded
         FROM refunds
         WHERE payment_id = ? AND status IN ("approved","processing","succeeded")'
    );
    $rs->execute([$paymentId]);
    $alreadyRefunded = (float)$rs->fetch()['refunded'];
    if ($alreadyRefunded + $amount > (float)$payment['amount']) {
        error('Refund exceeds captured payment amount.', 422);
    }

    $providerRef = providerReference('refund');
    $db->beginTransaction();
    try {
        $db->prepare(
            'INSERT INTO refunds
             (order_id, payment_id, amount, reason, status, provider_reference, requested_by, approved_by)
             VALUES (?, ?, ?, ?, "succeeded", ?, ?, ?)'
        )->execute([
            (int)$payment['order_id'], $paymentId, $amount, $reason, $providerRef,
            $user['sub'], $user['sub'],
        ]);

        $newRefundTotal = $alreadyRefunded + $amount;
        $paymentStatus = $newRefundTotal >= (float)$payment['amount'] ? 'refunded' : 'partially_refunded';
        $orderPaymentStatus = $paymentStatus === 'refunded' ? 'refunded' : 'paid';
        $db->prepare('UPDATE payments SET status = ? WHERE id = ?')->execute([$paymentStatus, $paymentId]);
        $db->prepare('UPDATE orders SET payment_status = ? WHERE id = ?')
           ->execute([$orderPaymentStatus, (int)$payment['order_id']]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        error('Refund could not be recorded.', 500);
    }

    pushNotification(
        $payment['customer_username'],
        'A refund of BWP ' . number_format($amount, 2) . " was processed for order #{$payment['order_id']}.",
        (int)$payment['order_id']
    );
    auditLog($user['sub'], 'admin', 'create_refund', 'payment', (string)$paymentId, ['amount' => $amount]);
    success(['provider_reference' => $providerRef], 'Refund recorded.');
}

error('Method not allowed.', 405);
