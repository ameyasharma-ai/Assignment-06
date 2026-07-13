<?php
// api/products.php - REST API for products with token authorization
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
$productId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($productId > 0) {
    // GET SINGLE PRODUCT DETAILS
    try {
        $stmt = $db->prepare("
            SELECT p.*, c.name AS category_name, v.name AS vendor_name
            FROM products p
            JOIN categories c ON p.category_id = c.id
            JOIN vendors v ON p.vendor_id = v.id
            WHERE p.id = ?
        ");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();

        if ($product) {
            // Include variations
            $stmtVar = $db->prepare("SELECT attribute, value, stock FROM product_variations WHERE product_id = ?");
            $stmtVar->execute([$productId]);
            $product['variations'] = $stmtVar->fetchAll();

            http_response_code(200);
            echo json_encode(['status' => 'success', 'data' => $product]);
        } else {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Product not found.']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Query error: ' . $e->getMessage()]);
    }
} else {
    // GET ALL PRODUCTS LISTING
    try {
        $stmt = $db->query("
            SELECT p.id, p.name, p.price, p.stock, p.image, c.name AS category_name, v.name AS vendor_name
            FROM products p
            JOIN categories c ON p.category_id = c.id
            JOIN vendors v ON p.vendor_id = v.id
            ORDER BY p.id DESC
        ");
        $products = $stmt->fetchAll();

        http_response_code(200);
        echo json_encode(['status' => 'success', 'count' => count($products), 'data' => $products]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Query error: ' . $e->getMessage()]);
    }
}
