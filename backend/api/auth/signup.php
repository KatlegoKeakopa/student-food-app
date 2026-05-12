<?php
// POST /api/auth/signup
// Body: { username, password, first_name, last_name, email, phone?, account_type, student_id?, work_id? }

require_once __DIR__ . '/../../includes/helpers.php';

setCorsHeaders();
handlePreflight();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') error('Method not allowed.', 405);

$body = getBody();
requireFields($body, ['username', 'password', 'first_name', 'last_name', 'email']);

// Audit fix: use trim() not sanitise() on input — no HTML-escaping at storage layer.
$username   = trim((string)$body['username']);
$password   = (string)$body['password'];
$first_name = trim((string)$body['first_name']);
$last_name  = trim((string)$body['last_name']);
// Audit fix: normalise email to lowercase before storing.
$email      = filter_var(strtolower(trim((string)$body['email'])), FILTER_VALIDATE_EMAIL);
$phone      = isset($body['phone'])      ? trim((string)$body['phone'])      : null;
$accountType = isset($body['account_type']) ? strtolower(trim((string)$body['account_type'])) : 'student';
$student_id = isset($body['student_id']) ? trim((string)$body['student_id']) : null;
$work_id    = isset($body['work_id'])    ? trim((string)$body['work_id'])    : null;

// Validation
if (strlen($username) < 3 || strlen($username) > 50) {
    error('Username must be between 3 and 50 characters.', 422);
}
if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
    error('Username may only contain letters, numbers, and underscores.', 422);
}
if (!$email) {
    error('A valid email address is required.', 422);
}
if (!in_array($accountType, ['student', 'staff'], true)) {
    error('Account type must be student or staff.', 422);
}
if ($phone !== null && $phone !== '' && !preg_match('/^\+?[0-9\s-]{7,20}$/', $phone)) {
    error('A valid phone number is required.', 422);
}
if ($accountType === 'student') {
    if (!$student_id) error('Student ID is required for student accounts.', 422);
    $work_id = null;
}
if ($accountType === 'staff') {
    if (!$work_id) error('Work ID is required for staff accounts.', 422);
    $student_id = null;
}
// Audit fix: enforce clear password strength rules with a descriptive message.
if (strlen($password) < 8) {
    error('Password must be at least 8 characters long.', 422);
}
if (!preg_match('/[A-Z]/', $password)) {
    error('Password must contain at least one uppercase letter.', 422);
}
if (!preg_match('/[0-9]/', $password)) {
    error('Password must contain at least one number.', 422);
}

$db = getDB();

// Check uniqueness
$stmt = $db->prepare('SELECT username FROM customers WHERE username = ? OR email = ?');
$stmt->execute([$username, $email]);
if ($stmt->fetch()) {
    error('Username or email is already registered.');
}

$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

$stmt = $db->prepare(
    'INSERT INTO customers (username, password_hash, first_name, last_name, email, phone, account_type, student_id, work_id)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
$stmt->execute([$username, $hash, $first_name, $last_name, $email, $phone, $accountType, $student_id, $work_id]);

auditLog($username, 'customer', 'signup', 'customer', $username);

$token = jwtEncode([
    'sub'        => $username,
    'role'       => 'customer',
    'first_name' => $first_name,
]);
setAuthCookie($token);

success([
    'token'      => $token,
    'username'   => $username,
    'first_name' => $first_name,
    'last_name'  => $last_name,
    'email'      => $email,
    'account_type' => $accountType,
    'role'       => 'customer',
], 'Account created successfully.');
