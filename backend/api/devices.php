<?php
// POST /api/devices/register – register web/mobile push token

require_once __DIR__ . '/../includes/helpers.php';

setCorsHeaders();
handlePreflight();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') error('Method not allowed.', 405);

$user = requireAuth(['customer', 'staff', 'admin']);
$body = getBody();
requireFields($body, ['platform', 'token']);

$platform = strtolower(trim((string)$body['platform']));
$token = trim((string)$body['token']);
if (!in_array($platform, ['web','android','ios'], true)) error('Unsupported device platform.', 422);
if (strlen($token) < 20 || strlen($token) > 255) error('Device token has an invalid length.', 422);

$db = getDB();
$stmt = $db->prepare(
    'INSERT INTO device_tokens (user_role, user_identifier, platform, token, last_seen)
     VALUES (?, ?, ?, ?, NOW())
     ON DUPLICATE KEY UPDATE
        user_role = VALUES(user_role),
        user_identifier = VALUES(user_identifier),
        platform = VALUES(platform),
        last_seen = NOW()'
);
$stmt->execute([$user['role'], $user['sub'], $platform, $token]);

auditLog($user['sub'], $user['role'], 'register_device', 'device_token', null, ['platform' => $platform]);
success([], 'Device registered.');
