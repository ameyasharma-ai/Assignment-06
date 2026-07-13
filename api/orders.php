<?php
// api/orders.php - REST API for orders with token authorization
require_once __DIR__ . '/../db.php';

header("Content-Type: application/json; charset=UTF-8");

// ----------------------------------------------------
// TOKEN-BASED AUTHORIZATION CHECK
// ----------------------------------------------------
$authHeader = '';
if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
} elseif (function_exists('apache_request_headers')) {
    $headers = apache_request_headers();
    if (isset($headers['Authorization'])) {
        $authHeader = $headers['Authorization'];
    }
}

if (empty($authHeader) || strpos($authHeader, 'Bearer ') !== 0) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized: Missing or invalid Authorization header. Expected format: Bearer <token_key>'
    ]);
    exit;
}

$token = substr($authHeader, 7);
$expectedToken = 'omnimart_api_token_secure_123'; // Evaluation test token

if ($token !== $expectedToken) {
    http_response_code(403);
    echo json_encode([
        'status' => 'error',
        'message' => 'Forbidden: The provided authorization token is invalid.'
    ]);
    exit;
}

// ----------------------------------------------------
// ROUTING GET REQUESTS
// ----------------------------------------------------
$db = getDBConnection();
$orderId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($orderId > 0) {
    // GET SINGLE ORDER INVOICE DETAILS
    try {
        $stmt = $db->prepare("
            SELECT o.*, u.name AS customer_name, u.email AS customer_email
            FROM orders o
            JOIN users u ON o.user_id = u.id
            WHERE o.id = ?
        ");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();

        if ($order) {
            // Fetch ordered items
            $stmtItems = $db->prepare("
                SELECT oi.*, p.name AS product_name, v.name AS vendor_name
                FROM order_items oi
                JOIN products p ON oi.product_id = p.id
                JOIN vendors v ON p.vendor_id = v.id
                WHERE oi.order_id = ?
            ");
            $stmtItems->execute([$orderId]);
            $order['items'] = $stmtItems->fetchAll();

            http_response_code(200);
            echo json_encode(['status' => 'success', 'data' => $order]);
        } else {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Order reference not found.']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Query error: ' . $e->getMessage()]);
    }
} else {
    // GET ALL ORDERS REGISTERED ON PLATFORM
    try {
        $stmt = $db->query("
            SELECT o.id, o.total_amount, o.status, o.created_at, u.name AS customer_name
            FROM orders o
            JOIN users u ON o.user_id = u.id
            ORDER BY o.id DESC
        ");
        $orders = $stmt->fetchAll();

        http_response_code(200);
        echo json_encode(['status' => 'success', 'count' => count($orders), 'data' => $orders]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Query error: ' . $e->getMessage()]);
    }
}
