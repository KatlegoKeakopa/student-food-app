<?php
// ============================================================
// Helpers: JSON responses, JWT, CORS, input sanitisation
// ============================================================

require_once __DIR__ . '/../config/db.php';

// ------ CORS -----------------------------------------------
// Audit fix: remove wildcard fallback — require explicit origin in allow-list.
// For local dev, add http://localhost or http://127.0.0.1 to CORS_ALLOWED_ORIGINS.
function setCorsHeaders(): void {
    $origin  = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowed = array_map('trim', explode(',', CORS_ALLOWED_ORIGINS));
    if ($origin && in_array($origin, $allowed, true)) {
        header("Access-Control-Allow-Origin: {$origin}");
        header('Access-Control-Allow-Credentials: true');
        header('Vary: Origin');
    } elseif ($origin) {
        // Origin is present but not in allow-list — deny explicitly.
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'CORS: origin not allowed.']);
        exit;
    }
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, Idempotency-Key');
    header('Content-Type: application/json; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header('X-Frame-Options: DENY');
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

function handlePreflight(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

// ------ Response helpers -----------------------------------
function respond(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function success(array $data = [], string $message = 'OK'): void {
    respond(['success' => true, 'message' => $message, 'data' => $data]);
}

function error(string $message, int $code = 400): void {
    respond(['success' => false, 'message' => $message], $code);
}

// ------ Input ----------------------------------------------
// Audit fix: cap request body size to 64 KB to prevent memory exhaustion.
function getBody(): array {
    $raw = file_get_contents('php://input', false, null, 0, 65537);
    if ($raw === '' || $raw === false) return [];
    if (strlen($raw) > 65536) {
        error('Request body too large (max 64 KB).', 413);
    }
    $body = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($body)) {
        error('Request body must be valid JSON.', 400);
    }
    return $body;
}

function requireFields(array $body, array $fields): void {
    foreach ($fields as $f) {
        if (!isset($body[$f]) || trim((string)$body[$f]) === '') {
            error("Field '{$f}' is required.", 422);
        }
    }
}

// Audit fix: sanitise() is now ONLY for HTML output-escaping, not for storing
// values. Do NOT use on IDs, enums, or numeric fields — use prepared statements.
function sanitise(string $value): string {
    return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
}

// Audit fix: announce() writes to ssr_log for debug tracing without leaking to response.
function announce(string $message): void {
    try {
        $db = getDB();
        $db->prepare('INSERT INTO ssr_log (message, created_at) VALUES (?, NOW())')
           ->execute([substr($message, 0, 1000)]);
    } catch (Throwable $e) {
        // Never break the request flow for a log write.
    }
}

// ------ Minimal JWT (HMAC-SHA256, no external lib) ---------
function jwtEncode(array $payload): string {
    $header  = base64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload['exp'] = time() + JWT_EXPIRY;
    $body    = base64url_encode(json_encode($payload));
    $sig     = base64url_encode(hash_hmac('sha256', "$header.$body", JWT_SECRET, true));
    return "$header.$body.$sig";
}

function jwtDecode(string $token): ?array {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;
    [$header, $body, $sig] = $parts;
    $expected = base64url_encode(hash_hmac('sha256', "$header.$body", JWT_SECRET, true));
    if (!hash_equals($expected, $sig)) return null;
    $payload = json_decode(base64url_decode($body), true);
    if (!$payload || (isset($payload['exp']) && $payload['exp'] < time())) return null;
    return $payload;
}

function base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode(string $data): string {
    return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
}

// ------ Auth middleware ------------------------------------
function requireAuth(array $allowedRoles = []): array {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $token = null;
    if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m)) {
        $token = $m[1];
    } elseif (!empty($_COOKIE['ub_session'])) {
        $token = $_COOKIE['ub_session'];
    }
    if (!$token) {
        error('Unauthorised: missing or invalid token.', 401);
    }
    $payload = jwtDecode($token);
    if (!$payload) {
        error('Unauthorised: token invalid or expired.', 401);
    }
    if ($allowedRoles && !in_array($payload['role'], $allowedRoles, true)) {
        error('Forbidden: insufficient permissions.', 403);
    }
    return $payload;
}

function setAuthCookie(string $token): void {
    setcookie('ub_session', $token, [
        'expires' => time() + JWT_EXPIRY,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function clientIp(): string {
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function auditLog(?string $actor, ?string $role, string $action, string $entityType, ?string $entityId, array $meta = []): void {
    try {
        $db = getDB();
        $stmt = $db->prepare(
            'INSERT INTO audit_logs (actor_username, actor_role, action, entity_type, entity_id, ip_address, metadata)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $actor,
            $role,
            $action,
            $entityType,
            $entityId,
            clientIp(),
            json_encode($meta, JSON_UNESCAPED_SLASHES),
        ]);
    } catch (Throwable $e) {
        // Audit logging must never break the customer/vendor flow.
    }
}

function providerReference(string $prefix): string {
    return strtoupper($prefix) . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
}

function hashDriverToken(string $token): string {
    return hash('sha256', $token);
}

function requireDriverToken(PDO $db, int $driverId, string $token): array {
    if ($driverId <= 0 || $token === '') {
        error('Driver credentials are required.', 401);
    }
    $stmt = $db->prepare(
        'SELECT id, full_name, phone, verification_status
         FROM drivers
         WHERE id = ? AND api_token_hash = ?'
    );
    $stmt->execute([$driverId, hashDriverToken($token)]);
    $driver = $stmt->fetch();
    if (!$driver || $driver['verification_status'] !== 'approved') {
        error('Driver credentials are invalid or inactive.', 401);
    }
    return $driver;
}

function enqueueNotification(
    string $recipient,
    string $channel,
    string $templateKey,
    array $payload = [],
    ?string $role = null
): void {
    try {
        $db = getDB();
        $stmt = $db->prepare(
            'INSERT INTO notification_jobs
             (recipient, user_role, channel, template_key, payload_json, status, next_attempt_at)
             VALUES (?, ?, ?, ?, ?, "queued", NOW())'
        );
        $stmt->execute([
            substr($recipient, 0, 160),
            $role,
            $channel,
            $templateKey,
            json_encode($payload, JSON_UNESCAPED_SLASHES),
        ]);
    } catch (Throwable $e) {
        // Queue failures should be visible in logs, but must not break checkout.
        announce('Notification queue write failed: ' . $e->getMessage());
    }
}

// ------ Notification helper --------------------------------
function pushNotification(string $username, string $message, ?int $orderId = null): void {
    $db = getDB();
    $stmt = $db->prepare(
        'INSERT INTO notifications (customer_username, order_id, message) VALUES (?, ?, ?)'
    );
    $stmt->execute([$username, $orderId, $message]);

    enqueueNotification($username, 'in_app', 'order_status', [
        'order_id' => $orderId,
        'message' => $message,
    ], 'customer');
}
