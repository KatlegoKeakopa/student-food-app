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
                'SELECT username, first_name, last_name, email, phone, student_id, created_at
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
                           SUM(CASE WHEN status = "delivered" THEN 1 ELSE 0 END) AS delivered,
                           SUM(CASE WHEN status = "cancelled" THEN 1 ELSE 0 END) AS cancelled,
                           SUM(CASE WHEN status = "delivered" THEN total_amount ELSE 0 END) AS revenue
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

        default:
            error('Unknown resource. Use: users | staff | reports | outlets_summary');
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
