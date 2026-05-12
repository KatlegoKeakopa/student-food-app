<?php
// Onboarding Service: vendor applications
// POST /api/vendor-applications
// GET  /api/vendor-applications/me?email=x
// POST /api/vendor-applications/{id}/documents
// PATCH/POST /api/admin/vendor-applications/{id}/review|approve|reject

require_once __DIR__ . '/../includes/helpers.php';

setCorsHeaders();
handlePreflight();

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

if (preg_match('#/admin/vendor-applications/(\d+)/(review|approve|reject)#', $uri, $m)) {
    $admin = requireAuth(['admin']);
    $id = (int)$m[1];
    $action = $m[2];
    $body = getBody();
    $notes = isset($body['notes']) ? trim((string)$body['notes']) : null;

    $stmt = $db->prepare('SELECT * FROM vendor_applications WHERE id = ?');
    $stmt->execute([$id]);
    $app = $stmt->fetch();
    if (!$app) error('Vendor application not found.', 404);

    if ($action === 'review') {
        $status = isset($body['status']) ? trim((string)$body['status']) : 'needs_changes';
        if (!in_array($status, ['submitted','under_review','needs_changes','approved','rejected','activated'], true)) error('Invalid review status.', 422);
        $db->prepare(
            'UPDATE vendor_applications
             SET status = ?, reviewer_notes = ?, reviewed_by = ?, reviewed_at = NOW()
             WHERE id = ?'
        )->execute([$status, $notes, $admin['sub'], $id]);
        auditLog($admin['sub'], 'admin', 'review_vendor_application', 'vendor_application', (string)$id, ['status' => $status]);
        success([], 'Vendor application reviewed.');
    }

    if ($action === 'reject') {
        $db->prepare(
            'UPDATE vendor_applications
             SET status = "rejected", reviewer_notes = ?, reviewed_by = ?, reviewed_at = NOW()
             WHERE id = ?'
        )->execute([$notes ?: 'Application rejected.', $admin['sub'], $id]);
        auditLog($admin['sub'], 'admin', 'reject_vendor_application', 'vendor_application', (string)$id);
        success([], 'Vendor application rejected.');
    }

    if ($action === 'approve') {
        $db->beginTransaction();
        try {
            $db->prepare(
                'INSERT INTO food_outlets
                 (name, description, cuisine, accepts_delivery, accepts_pickup, is_temporarily_closed, is_active, created_by_username)
                 VALUES (?, ?, ?, ?, ?, 1, 0, ?)'
            )->execute([
                $app['trading_name'],
                'Pending activation for ' . $app['business_name'],
                $app['cuisine_type'],
                (int)$app['delivery_available'],
                (int)$app['pickup_available'],
                $admin['sub'],
            ]);
            $outletId = (int)$db->lastInsertId();
            $db->prepare(
                'UPDATE vendor_applications
                 SET status = "approved", reviewer_notes = ?, reviewed_by = ?, reviewed_at = NOW(), created_outlet_id = ?
                 WHERE id = ?'
            )->execute([$notes, $admin['sub'], $outletId, $id]);
            foreach (['trade_licence','food_safety','payout_info','identity'] as $check) {
                $db->prepare(
                    'INSERT INTO vendor_compliance_checks
                     (application_id, check_key, status, checked_by, checked_at)
                     VALUES (?, ?, "pending", ?, NOW())'
                )->execute([$id, $check, $admin['sub']]);
            }
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            error('Vendor application could not be approved.', 500);
        }
        auditLog($admin['sub'], 'admin', 'approve_vendor_application', 'vendor_application', (string)$id, ['outlet_id' => $outletId]);
        success(['outlet_id' => $outletId], 'Vendor approved. Outlet created inactive pending activation checklist.');
    }
}

if (preg_match('#/vendor-applications/(\d+)/documents#', $uri, $m)) {
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
        'INSERT INTO vendor_application_documents
         (application_id, document_type, file_url, expires_at)
         VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$id, $type, $fileUrl, $expires ?: null]);
    success(['document_id' => (int)$db->lastInsertId()], 'Document added.');
}

if ($method === 'POST') {
    $body = getBody();
    requireFields($body, ['business_name','trading_name','contact_name','email','phone','location']);
    $email = filter_var(strtolower(trim((string)$body['email'])), FILTER_VALIDATE_EMAIL);
    if (!$email) error('A valid email is required.', 422);
    $stmt = $db->prepare(
        'INSERT INTO vendor_applications
         (business_name, trading_name, contact_name, email, phone, location, cuisine_type,
          operating_hours, service_modes, pickup_available, delivery_available, payout_method,
          licence_number, food_safety_reference, document_url, notes)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        trim((string)$body['business_name']),
        trim((string)$body['trading_name']),
        trim((string)$body['contact_name']),
        $email,
        trim((string)$body['phone']),
        trim((string)$body['location']),
        isset($body['cuisine_type']) ? trim((string)$body['cuisine_type']) : null,
        isset($body['operating_hours']) ? trim((string)$body['operating_hours']) : null,
        isset($body['service_modes']) ? trim((string)$body['service_modes']) : null,
        !empty($body['pickup_available']) ? 1 : 0,
        !empty($body['delivery_available']) ? 1 : 0,
        isset($body['payout_method']) ? trim((string)$body['payout_method']) : null,
        isset($body['licence_number']) ? trim((string)$body['licence_number']) : null,
        isset($body['food_safety_reference']) ? trim((string)$body['food_safety_reference']) : null,
        isset($body['document_url']) ? trim((string)$body['document_url']) : null,
        isset($body['notes']) ? trim((string)$body['notes']) : null,
    ]);
    $id = (int)$db->lastInsertId();
    enqueueNotification($email, 'email', 'vendor_application_received', ['application_id' => $id], 'vendor');
    auditLog(null, null, 'submit_vendor_application', 'vendor_application', (string)$id);
    success(['application_id' => $id], 'Vendor application submitted.');
}

if ($method === 'GET') {
    if (preg_match('#/admin/vendor-applications#', $uri)) {
        requireAuth(['admin']);
        $stmt = $db->query(
                'SELECT id, business_name, trading_name, contact_name, email, phone, location,
                    cuisine_type, operating_hours, service_modes, pickup_available, delivery_available,
                    payout_method, licence_number, food_safety_reference, document_url, notes,
                    status, reviewer_notes, created_outlet_id, created_at, updated_at
             FROM vendor_applications ORDER BY created_at DESC LIMIT 300'
        );
        success(['applications' => $stmt->fetchAll()]);
    }
    $email = isset($_GET['email']) ? filter_var(strtolower(trim((string)$_GET['email'])), FILTER_VALIDATE_EMAIL) : null;
    if (!$email) error('email query parameter is required.', 422);
    $stmt = $db->prepare(
        'SELECT id, business_name, trading_name, status, reviewer_notes, created_outlet_id, created_at, updated_at
         FROM vendor_applications WHERE email = ? ORDER BY created_at DESC'
    );
    $stmt->execute([$email]);
    success(['applications' => $stmt->fetchAll()]);
}

error('Method not allowed.', 405);
