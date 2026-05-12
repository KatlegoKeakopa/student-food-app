<?php
// Onboarding Service: driver applications
// POST /api/driver-applications
// GET  /api/driver-applications/me?phone=x
// POST /api/driver-applications/{id}/documents
// PATCH/POST /api/admin/driver-applications/{id}/review|approve|reject

require_once __DIR__ . '/../includes/helpers.php';

setCorsHeaders();
handlePreflight();

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

if (preg_match('#/admin/driver-applications/(\d+)/(review|approve|reject)#', $uri, $m)) {
    $admin = requireAuth(['admin']);
    $id = (int)$m[1];
    $action = $m[2];
    $body = getBody();
    $notes = isset($body['notes']) ? trim((string)$body['notes']) : null;

    $stmt = $db->prepare('SELECT * FROM driver_applications WHERE id = ?');
    $stmt->execute([$id]);
    $app = $stmt->fetch();
    if (!$app) error('Driver application not found.', 404);

    if ($action === 'review') {
        $status = isset($body['status']) ? trim((string)$body['status']) : 'needs_changes';
        if (!in_array($status, ['submitted','under_review','needs_changes','approved','rejected','activated'], true)) error('Invalid review status.', 422);
        $db->prepare(
            'UPDATE driver_applications
             SET status = ?, reviewer_notes = ?, reviewed_by = ?, reviewed_at = NOW()
             WHERE id = ?'
        )->execute([$status, $notes, $admin['sub'], $id]);
        auditLog($admin['sub'], 'admin', 'review_driver_application', 'driver_application', (string)$id, ['status' => $status]);
        success([], 'Driver application reviewed.');
    }

    if ($action === 'reject') {
        $db->prepare(
            'UPDATE driver_applications
             SET status = "rejected", reviewer_notes = ?, reviewed_by = ?, reviewed_at = NOW()
             WHERE id = ?'
        )->execute([$notes ?: 'Application rejected.', $admin['sub'], $id]);
        auditLog($admin['sub'], 'admin', 'reject_driver_application', 'driver_application', (string)$id);
        success([], 'Driver application rejected.');
    }

    if ($action === 'approve') {
        $apiToken = bin2hex(random_bytes(24));
        $db->beginTransaction();
        try {
            $db->prepare(
                'INSERT INTO drivers
                 (application_id, full_name, phone, email, vehicle_type, verification_status, api_token_hash)
                 VALUES (?, ?, ?, ?, ?, "approved", ?)'
            )->execute([
                $id,
                $app['legal_name'],
                $app['phone'],
                $app['email'],
                $app['vehicle_type'],
                hashDriverToken($apiToken),
            ]);
            $driverId = (int)$db->lastInsertId();
            $db->prepare('INSERT INTO driver_availability (driver_id, is_online) VALUES (?, 0)')
               ->execute([$driverId]);
            $db->prepare(
                'UPDATE driver_applications
                 SET status = "approved", reviewer_notes = ?, reviewed_by = ?, reviewed_at = NOW(), created_driver_id = ?
                 WHERE id = ?'
            )->execute([$notes, $admin['sub'], $driverId, $id]);
            foreach (['identity','licence','vehicle','payout','agreement'] as $check) {
                $db->prepare(
                    'INSERT INTO driver_compliance_checks
                     (application_id, check_key, status, checked_by, checked_at)
                     VALUES (?, ?, "pending", ?, NOW())'
                )->execute([$id, $check, $admin['sub']]);
            }
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            error('Driver application could not be approved.', 500);
        }
        auditLog($admin['sub'], 'admin', 'approve_driver_application', 'driver_application', (string)$id, ['driver_id' => $driverId]);
        success([
            'driver_id' => $driverId,
            'api_token' => $apiToken,
            'token_notice' => 'Show once. Store in a secure credential vault or driver app onboarding channel.',
        ], 'Driver approved.');
    }
}

if (preg_match('#/driver-applications/(\d+)/documents#', $uri, $m)) {
    if ($method !== 'POST') error('Method not allowed.', 405);
    $id = (int)$m[1];
    $body = getBody();
    requireFields($body, ['document_type', 'file_url']);
    $type = trim((string)$body['document_type']);
    $fileUrl = trim((string)$body['file_url']);
    if (!preg_match('#^https?://|^/uploads/#', $fileUrl)) {
        error('Document must reference object storage or an uploaded file URL.', 422);
    }
    $expires = isset($body['expires_at']) ? trim((string)$body['expires_at']) : null;
    $stmt = $db->prepare(
        'INSERT INTO driver_application_documents
         (application_id, document_type, file_url, expires_at)
         VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$id, $type, $fileUrl, $expires ?: null]);
    success(['document_id' => (int)$db->lastInsertId()], 'Document added.');
}

if ($method === 'POST') {
    $body = getBody();
    requireFields($body, ['legal_name','phone','vehicle_type']);
    $email = isset($body['email']) && $body['email'] !== ''
        ? filter_var(strtolower(trim((string)$body['email'])), FILTER_VALIDATE_EMAIL)
        : null;
    if (isset($body['email']) && $body['email'] !== '' && !$email) error('A valid email is required.', 422);
    $stmt = $db->prepare(
        'INSERT INTO driver_applications
         (legal_name, email, phone, id_number, licence_number, vehicle_type, vehicle_registration,
          emergency_contact, service_areas, payout_method, campus_id, identity_reference, document_url, notes)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        trim((string)$body['legal_name']),
        $email,
        trim((string)$body['phone']),
        isset($body['id_number']) ? trim((string)$body['id_number']) : null,
        isset($body['licence_number']) ? trim((string)$body['licence_number']) : null,
        trim((string)$body['vehicle_type']),
        isset($body['vehicle_registration']) ? trim((string)$body['vehicle_registration']) : null,
        isset($body['emergency_contact']) ? trim((string)$body['emergency_contact']) : null,
        isset($body['service_areas']) ? trim((string)$body['service_areas']) : null,
        isset($body['payout_method']) ? trim((string)$body['payout_method']) : null,
        isset($body['campus_id']) ? trim((string)$body['campus_id']) : null,
        isset($body['identity_reference']) ? trim((string)$body['identity_reference']) : null,
        isset($body['document_url']) ? trim((string)$body['document_url']) : null,
        isset($body['notes']) ? trim((string)$body['notes']) : null,
    ]);
    $id = (int)$db->lastInsertId();
    enqueueNotification(trim((string)$body['phone']), 'sms', 'driver_application_received', ['application_id' => $id], 'driver');
    auditLog(null, null, 'submit_driver_application', 'driver_application', (string)$id);
    success(['application_id' => $id], 'Driver application submitted.');
}

if ($method === 'GET') {
    if (preg_match('#/admin/driver-applications#', $uri)) {
        requireAuth(['admin']);
        $stmt = $db->query(
            'SELECT id, legal_name, email, phone, id_number, licence_number, vehicle_type, vehicle_registration,
                    emergency_contact, service_areas, payout_method, campus_id, identity_reference,
                    document_url, notes, status, reviewer_notes, created_driver_id, created_at, updated_at
             FROM driver_applications ORDER BY created_at DESC LIMIT 300'
        );
        success(['applications' => $stmt->fetchAll()]);
    }
    $phone = isset($_GET['phone']) ? trim((string)$_GET['phone']) : '';
    if ($phone === '') error('phone query parameter is required.', 422);
    $stmt = $db->prepare(
        'SELECT id, legal_name, status, reviewer_notes, created_driver_id, created_at, updated_at
         FROM driver_applications WHERE phone = ? ORDER BY created_at DESC'
    );
    $stmt->execute([$phone]);
    success(['applications' => $stmt->fetchAll()]);
}

error('Method not allowed.', 405);
