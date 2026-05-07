<?php
// POST /api/auth/login
// Body: { username, password, role }   role = customer | staff | admin

require_once __DIR__ . '/../../includes/helpers.php';

setCorsHeaders();
handlePreflight();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') error('Method not allowed.', 405);

$body = getBody();
requireFields($body, ['username', 'password', 'role']);

// Audit fix: use trim() not sanitise() on username/role — no HTML escaping at input.
$username = trim((string)$body['username']);
$password = (string)$body['password'];
$role     = trim((string)$body['role']);

if (!in_array($role, ['customer', 'staff', 'admin'], true)) {
    error('Invalid role. Must be customer, staff, or admin.');
}

$db = getDB();

function recordLoginAttempt(PDO $db, string $username, string $role, bool $success): void {
    try {
        $stmt = $db->prepare(
            'INSERT INTO auth_attempts (username, role, ip_address, success) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$username, $role, clientIp(), $success ? 1 : 0]);
    } catch (Throwable $e) {
    }
}

// Audit fix: enforce limits independently by username AND by IP (two separate counters).
function enforceLoginRateLimit(PDO $db, string $username, string $role): void {
    try {
        // Per IP+username (existing)
        $s1 = $db->prepare(
            'SELECT COUNT(*) AS failed FROM auth_attempts
             WHERE username = ? AND role = ? AND ip_address = ? AND success = 0
               AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)'
        );
        $s1->execute([$username, $role, clientIp()]);
        if ((int)($s1->fetch()['failed'] ?? 0) >= 8) {
            error('Too many failed login attempts from your network. Try again in 15 minutes.', 429);
        }
        // Per username across all IPs (detect credential-stuffing / botnet)
        $s2 = $db->prepare(
            'SELECT COUNT(*) AS failed FROM auth_attempts
             WHERE username = ? AND role = ? AND success = 0
               AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)'
        );
        $s2->execute([$username, $role]);
        if ((int)($s2->fetch()['failed'] ?? 0) >= 15) {
            error('This account is temporarily locked due to multiple failed attempts. Try again in 15 minutes.', 429);
        }
    } catch (Throwable $e) {
    }
}

enforceLoginRateLimit($db, $username, $role);

switch ($role) {
    case 'customer':
        $stmt = $db->prepare(
            'SELECT username, password_hash, first_name, last_name, email
             FROM customers WHERE username = ? AND status = "active"'
        );
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) {
            recordLoginAttempt($db, $username, $role, false);
            error('Invalid username or password.', 401);
        }
        $payload = [
            'sub'        => $user['username'],
            'role'       => 'customer',
            'first_name' => $user['first_name'],
        ];
        // Audit fix: include password_reset_required flag for future forced-reset flows.
        $responseData = [
            'username'               => $user['username'],
            'first_name'             => $user['first_name'],
            'last_name'              => $user['last_name'],
            'email'                  => $user['email'],
            'role'                   => 'customer',
            'password_reset_required'=> false,
        ];
        break;

    case 'staff':
        $stmt = $db->prepare(
            'SELECT s.id, s.username, s.password_hash, s.first_name, s.last_name, s.email,
                    s.outlet_id, s.role AS staff_role, o.name AS outlet_name
             FROM outlet_staff s
             JOIN food_outlets o ON o.id = s.outlet_id
             WHERE s.username = ?'
        );
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) {
            recordLoginAttempt($db, $username, $role, false);
            error('Invalid username or password.', 401);
        }
        $payload = [
            'sub'        => $user['username'],
            'role'       => 'staff',
            'staff_role' => $user['staff_role'],
            'outlet_id'  => (int)$user['outlet_id'],
        ];
        $responseData = [
            'username'    => $user['username'],
            'first_name'  => $user['first_name'],
            'last_name'   => $user['last_name'],
            'email'       => $user['email'],
            'role'        => 'staff',
            'staff_role'  => $user['staff_role'],
            'outlet_id'   => (int)$user['outlet_id'],
            'outlet_name' => $user['outlet_name'],
        ];
        break;

    case 'admin':
        $stmt = $db->prepare('SELECT id, username, password_hash, email FROM admins WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) {
            recordLoginAttempt($db, $username, $role, false);
            error('Invalid username or password.', 401);
        }
        $payload = [
            'sub'  => $user['username'],
            'role' => 'admin',
        ];
        $responseData = [
            'username' => $user['username'],
            'email'    => $user['email'],
            'role'     => 'admin',
        ];
        break;
}

recordLoginAttempt($db, $username, $role, true);
auditLog($username, $role, 'login', 'session', null);
$token = jwtEncode($payload);
setAuthCookie($token);

success(array_merge($responseData, ['token' => $token]), 'Login successful.');
