<?php
// GET   /api/notifications             – customer's notifications
// PATCH /api/notifications             – mark all as read
// GET   /api/notifications/preferences – current user's preferences
// PATCH /api/notifications/preferences – update preferences

require_once __DIR__ . '/../includes/helpers.php';

setCorsHeaders();
handlePreflight();

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$user   = requireAuth(['customer']);
$isPreferences = preg_match('#/notifications/preferences#', $_SERVER['REQUEST_URI']) === 1;

if ($isPreferences) {
    if ($method === 'GET') {
        $stmt = $db->prepare(
            'SELECT email_enabled, sms_enabled, push_enabled, marketing_enabled, updated_at
             FROM notification_preferences
             WHERE user_role = ? AND user_identifier = ?'
        );
        $stmt->execute([$user['role'], $user['sub']]);
        $prefs = $stmt->fetch() ?: [
            'email_enabled' => 1,
            'sms_enabled' => 1,
            'push_enabled' => 1,
            'marketing_enabled' => 0,
            'updated_at' => null,
        ];
        success(['preferences' => $prefs]);
    }

    if ($method === 'PATCH') {
        $body = getBody();
        $email = !empty($body['email_enabled']) ? 1 : 0;
        $sms = !empty($body['sms_enabled']) ? 1 : 0;
        $push = !empty($body['push_enabled']) ? 1 : 0;
        $marketing = !empty($body['marketing_enabled']) ? 1 : 0;
        $stmt = $db->prepare(
            'INSERT INTO notification_preferences
             (user_role, user_identifier, email_enabled, sms_enabled, push_enabled, marketing_enabled)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                email_enabled = VALUES(email_enabled),
                sms_enabled = VALUES(sms_enabled),
                push_enabled = VALUES(push_enabled),
                marketing_enabled = VALUES(marketing_enabled)'
        );
        $stmt->execute([$user['role'], $user['sub'], $email, $sms, $push, $marketing]);
        auditLog($user['sub'], $user['role'], 'update_notification_preferences', 'customer', $user['sub']);
        success([], 'Notification preferences updated.');
    }

    error('Method not allowed.', 405);
}

if ($method === 'GET') {
    $stmt = $db->prepare(
        'SELECT id, order_id, message, is_read, created_at
         FROM notifications WHERE customer_username = ?
         ORDER BY created_at DESC LIMIT 50'
    );
    $stmt->execute([$user['sub']]);
    $notifs = $stmt->fetchAll();

    $countStmt = $db->prepare(
        'SELECT COUNT(*) AS unread_count
         FROM notifications
         WHERE customer_username = ? AND is_read = 0'
    );
    $countStmt->execute([$user['sub']]);
    $unread = (int)($countStmt->fetch()['unread_count'] ?? 0);

    success(['notifications' => $notifs, 'unread_count' => $unread]);
}

if ($method === 'PATCH') {
    $db->prepare(
        'UPDATE notifications SET is_read = 1 WHERE customer_username = ?'
    )->execute([$user['sub']]);
    success([], 'Notifications marked as read.');
}
