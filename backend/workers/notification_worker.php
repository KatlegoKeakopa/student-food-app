<?php
// DB-backed notification worker.
// Production adapters should replace the sandbox sender with SendGrid/Mailgun/SES,
// Orange/Twilio/Infobip SMS, and FCM/APNs.

require_once __DIR__ . '/../includes/helpers.php';

$db = getDB();
$limit = (int)($argv[1] ?? 25);
$limit = max(1, min($limit, 100));

$stmt = $db->prepare(
    'SELECT *
     FROM notification_jobs
     WHERE status = "queued"
       AND (next_attempt_at IS NULL OR next_attempt_at <= NOW())
     ORDER BY created_at ASC
     LIMIT ?'
);
$stmt->bindValue(1, $limit, PDO::PARAM_INT);
$stmt->execute();
$jobs = $stmt->fetchAll();

foreach ($jobs as $job) {
    try {
        $db->beginTransaction();
        $db->prepare('UPDATE notification_jobs SET status = "sending" WHERE id = ? AND status = "queued"')
           ->execute([(int)$job['id']]);

        if ($job['channel'] === 'email') {
            $provider = configValue('EMAIL_PROVIDER', 'sandbox');
        } elseif ($job['channel'] === 'sms') {
            $provider = configValue('SMS_PROVIDER', 'sandbox');
        } elseif ($job['channel'] === 'push') {
            $provider = configValue('PUSH_PROVIDER', 'sandbox');
        } else {
            $provider = 'in_app';
        }
        $providerMessageId = providerReference($provider);

        $db->prepare(
            'INSERT INTO notification_deliveries
             (job_id, provider, provider_message_id, delivery_status, event_json)
             VALUES (?, ?, ?, "sent", ?)'
        )->execute([
            (int)$job['id'],
            $provider,
            $providerMessageId,
            json_encode(['sandbox' => true, 'channel' => $job['channel']], JSON_UNESCAPED_SLASHES),
        ]);

        $db->prepare('UPDATE notification_jobs SET status = "sent", last_error = NULL WHERE id = ?')
           ->execute([(int)$job['id']]);
        $db->commit();
        echo "sent job {$job['id']} via {$provider}\n";
    } catch (Throwable $e) {
        $db->rollBack();
        $retry = min(5, ((int)$job['retry_count']) + 1);
        $status = $retry >= 5 ? 'failed' : 'queued';
        $db->prepare(
            'UPDATE notification_jobs
             SET status = ?, retry_count = ?, next_attempt_at = DATE_ADD(NOW(), INTERVAL ? MINUTE), last_error = ?
             WHERE id = ?'
        )->execute([$status, $retry, $retry * 5, substr($e->getMessage(), 0, 255), (int)$job['id']]);
        echo "failed job {$job['id']}: {$e->getMessage()}\n";
    }
}

if (!$jobs) {
    echo "no queued notification jobs\n";
}
