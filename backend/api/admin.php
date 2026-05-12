<?php
// Admin API endpoints (admin role only)
// GET /api/admin?resource=users|reports|outlets_summary

require_once __DIR__ . '/../includes/helpers.php';

setCorsHeaders();
handlePreflight();

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$user   = requireAuth(['admin']);

if ($method === 'GET') {
    $resource = isset($_GET['resource']) ? sanitise($_GET['resource']) : '';

    switch ($resource) {

        case 'users':
            $stmt = $db->query(
                'SELECT username, first_name, last_name, email, phone, account_type, student_id, work_id, created_at
                 FROM customers WHERE status = "active" ORDER BY created_at DESC'
            );
            success(['users' => $stmt->fetchAll()]);
            break;

        case 'staff':
            $stmt = $db->query(
                'SELECT s.id, s.username, s.first_name, s.last_name, s.email,
                        s.role AS staff_role, o.name AS outlet_name, s.created_at
                 FROM outlet_staff s JOIN food_outlets o ON o.id = s.outlet_id
                 ORDER BY o.name, s.username'
            );
            success(['staff' => $stmt->fetchAll()]);
            break;

        case 'reports':
            // Order summary per outlet
            $stmt = $db->query(
                'SELECT fo.name AS outlet,
                        COALESCE(os.total_orders, 0) AS total_orders,
                        COALESCE(os.delivered, 0) AS delivered,
                        COALESCE(os.cancelled, 0) AS cancelled,
                        COALESCE(os.revenue, 0) AS revenue,
                        rs.avg_rating
                 FROM food_outlets fo
                 LEFT JOIN (
                    SELECT outlet_id,
                           COUNT(*) AS total_orders,
                           SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) AS delivered,
                           SUM(CASE WHEN status = "cancelled" THEN 1 ELSE 0 END) AS cancelled,
                           SUM(CASE WHEN status = "completed" THEN total_amount ELSE 0 END) AS revenue
                    FROM orders GROUP BY outlet_id
                 ) os ON os.outlet_id = fo.id
                 LEFT JOIN (
                    SELECT outlet_id, ROUND(AVG(rating), 1) AS avg_rating
                    FROM ratings GROUP BY outlet_id
                 ) rs ON rs.outlet_id = fo.id
                 GROUP BY fo.id
                 ORDER BY fo.name'
            );
            $outletReport = $stmt->fetchAll();

            // Daily orders for the last 30 days
            $stmt = $db->query(
                'SELECT DATE(created_at) AS day, COUNT(*) AS orders, SUM(total_amount) AS revenue
                 FROM orders
                 WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                 GROUP BY DATE(created_at)
                 ORDER BY day'
            );
            $dailyReport = $stmt->fetchAll();

            // Status breakdown
            $stmt = $db->query(
                'SELECT status, COUNT(*) AS count FROM orders GROUP BY status'
            );
            $statusBreakdown = $stmt->fetchAll();

            success([
                'outlet_report'    => $outletReport,
                'daily_report'     => $dailyReport,
                'status_breakdown' => $statusBreakdown,
            ]);
            break;

        case 'outlets_summary':
            $stmt = $db->query(
                'SELECT fo.id, fo.name, fo.is_active,
                        COUNT(DISTINCT fi.id) AS menu_item_count,
                        COUNT(DISTINCT s.id)  AS staff_count
                 FROM food_outlets fo
                 LEFT JOIN food_items fi ON fi.outlet_id = fo.id
                 LEFT JOIN outlet_staff s ON s.outlet_id = fo.id
                 GROUP BY fo.id'
            );
            success(['outlets' => $stmt->fetchAll()]);
            break;

        case 'support':
            $stmt = $db->query(
                'SELECT st.id, st.customer_username, st.order_id, st.subject, st.status,
                        st.created_at, st.updated_at
                 FROM support_tickets st
                 ORDER BY st.created_at DESC LIMIT 200'
            );
            success(['tickets' => $stmt->fetchAll()]);
            break;

        case 'audit':
            $stmt = $db->query(
                'SELECT actor_username, actor_role, action, entity_type, entity_id, ip_address, created_at
                 FROM audit_logs
                 ORDER BY created_at DESC LIMIT 200'
            );
            success(['audit_logs' => $stmt->fetchAll()]);
            break;

        case 'promotions':
            $stmt = $db->query(
                'SELECT id, code, description, discount_type, discount_value,
                        min_order_amount, max_redemptions, max_uses_per_customer,
                        valid_from, valid_until, is_active
                 FROM promotions
                 ORDER BY created_at DESC'
            );
            success(['promotions' => $stmt->fetchAll()]);
            break;

        case 'payments':
            $stmt = $db->query(
                'SELECT p.id, p.order_id, o.customer_username, p.provider, p.method,
                        p.amount, p.currency, p.status, p.provider_reference, p.paid_at, p.created_at
                 FROM payments p
                 JOIN orders o ON o.id = p.order_id
                 ORDER BY p.created_at DESC LIMIT 300'
            );
            success(['payments' => $stmt->fetchAll()]);
            break;

        case 'refunds':
            $stmt = $db->query(
                'SELECT r.id, r.order_id, r.payment_id, r.amount, r.reason, r.status,
                        r.provider_reference, r.created_at, o.customer_username
                 FROM refunds r
                 JOIN orders o ON o.id = r.order_id
                 ORDER BY r.created_at DESC LIMIT 300'
            );
            success(['refunds' => $stmt->fetchAll()]);
            break;

        case 'reconciliation':
            $stmt = $db->query(
                'SELECT id, provider, provider_reference, order_id, expected_amount,
                        settled_amount, currency, status, notes, created_at
                 FROM reconciliation_entries
                 ORDER BY created_at DESC LIMIT 300'
            );
            success(['reconciliation' => $stmt->fetchAll()]);
            break;

        case 'vendor_applications':
            $stmt = $db->query(
                'SELECT id, business_name, trading_name, contact_name, email, phone,
                        location, cuisine_type, status, reviewer_notes, created_outlet_id, created_at
                 FROM vendor_applications
                 ORDER BY created_at DESC LIMIT 300'
            );
            success(['applications' => $stmt->fetchAll()]);
            break;

        case 'driver_applications':
            $stmt = $db->query(
                'SELECT id, legal_name, email, phone, vehicle_type, vehicle_registration,
                        service_areas, status, reviewer_notes, created_driver_id, created_at
                 FROM driver_applications
                 ORDER BY created_at DESC LIMIT 300'
            );
            success(['applications' => $stmt->fetchAll()]);
            break;

        case 'dispatch':
            $stmt = $db->query(
                'SELECT d.id, d.order_id, d.status, d.eta_minutes, d.updated_at,
                        dr.full_name AS driver_name, da.is_online, da.current_lat, da.current_lng,
                        o.customer_username, o.delivery_address
                 FROM deliveries d
                 LEFT JOIN drivers dr ON dr.id = d.driver_id
                 LEFT JOIN driver_availability da ON da.driver_id = dr.id
                 JOIN orders o ON o.id = d.order_id
                 ORDER BY d.updated_at DESC LIMIT 300'
            );
            success(['deliveries' => $stmt->fetchAll()]);
            break;

        case 'legal':
            $docs = $db->query(
                'SELECT id, document_type, version, title, content_url, effective_date, is_active
                 FROM legal_documents ORDER BY document_type, effective_date DESC'
            )->fetchAll();
            $requests = $db->query(
                'SELECT id, user_role, user_identifier, request_type, status, created_at
                 FROM data_requests ORDER BY created_at DESC LIMIT 100'
            )->fetchAll();
            success(['documents' => $docs, 'data_requests' => $requests]);
            break;

        default:
            error('Unknown resource. Use: users | staff | reports | outlets_summary | support | audit | promotions | payments | refunds | reconciliation | vendor_applications | driver_applications | dispatch | legal');
    }
}

// DELETE /api/admin?resource=user&username={u}
if ($method === 'DELETE') {
    $resource = isset($_GET['resource']) ? sanitise($_GET['resource']) : '';
    if ($resource === 'user' && !empty($_GET['username'])) {
        $uname = sanitise($_GET['username']);
        if ($uname === $user['sub']) error('Cannot delete your own admin account.');
        $stmt = $db->prepare(
            'UPDATE customers
             SET status = "deleted",
                 deleted_at = NOW(),
                 first_name = "Deleted",
                 last_name = "User",
                 email = CONCAT("deleted+", username, "@anonymous.local"),
                 phone = NULL,
                 student_id = NULL
             WHERE username = ?'
        );
        $stmt->execute([$uname]);
        auditLog($user['sub'], 'admin', 'delete_customer', 'customer', $uname);
        success([], 'User account deleted.');
    } else {
        error('Invalid delete request.');
    }
}
