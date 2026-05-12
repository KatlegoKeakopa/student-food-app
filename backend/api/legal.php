<?php
// Legal/Compliance Service
// GET  /api/legal/documents
// POST /api/legal/consents
// POST /api/legal/data-requests

require_once __DIR__ . '/../includes/helpers.php';

setCorsHeaders();
handlePreflight();

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

if (preg_match('#/legal/documents#', $uri)) {
    if ($method !== 'GET') error('Method not allowed.', 405);
    $stmt = $db->query(
        'SELECT id, document_type, version, title, content_url, effective_date
         FROM legal_documents
         WHERE is_active = 1
         ORDER BY document_type, effective_date DESC'
    );
    success(['documents' => $stmt->fetchAll()]);
}

if (preg_match('#/legal/consents#', $uri)) {
    if ($method !== 'POST') error('Method not allowed.', 405);
    $user = requireAuth(['customer', 'staff', 'admin']);
    $body = getBody();
    requireFields($body, ['document_ids']);
    if (!is_array($body['document_ids']) || count($body['document_ids']) === 0) {
        error('At least one legal document must be accepted.', 422);
    }

    $db->beginTransaction();
    try {
        $stmt = $db->prepare(
            'INSERT INTO user_consents
             (user_role, user_identifier, legal_document_id, ip_address)
             VALUES (?, ?, ?, ?)'
        );
        foreach ($body['document_ids'] as $docId) {
            $stmt->execute([$user['role'], $user['sub'], (int)$docId, clientIp()]);
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        error('Consent could not be recorded.', 500);
    }
    auditLog($user['sub'], $user['role'], 'accept_legal_documents', 'legal_documents', null, ['documents' => $body['document_ids']]);
    success([], 'Consent recorded.');
}

if (preg_match('#/legal/data-requests#', $uri)) {
    if ($method !== 'POST') error('Method not allowed.', 405);
    $user = requireAuth(['customer', 'staff', 'admin']);
    $body = getBody();
    requireFields($body, ['request_type']);
    $type = trim((string)$body['request_type']);
    if (!in_array($type, ['access','delete','export','correction'], true)) {
        error('Invalid data request type.', 422);
    }
    $notes = isset($body['notes']) ? trim((string)$body['notes']) : null;
    $stmt = $db->prepare(
        'INSERT INTO data_requests (user_role, user_identifier, request_type, notes)
         VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$user['role'], $user['sub'], $type, $notes]);
    auditLog($user['sub'], $user['role'], 'create_data_request', 'data_request', (string)$db->lastInsertId(), ['type' => $type]);
    success(['request_id' => (int)$db->lastInsertId()], 'Data request submitted.');
}

error('Unknown legal endpoint.', 404);
