<?php
// test_db_render.php - Detailed PDO MySQL SSL diagnostics
header("Content-Type: text/plain");

$host = getenv('DB_HOST') ?: 'mysql-3dbfe4a8-sharmaameya999-b5be.a.aivencloud.com';
$port = getenv('DB_PORT') ?: '22493';
$dbname = getenv('DB_NAME') ?: 'defaultdb';
$user = getenv('DB_USER') ?: 'avnadmin';
$pass = getenv('DB_PASS');

$dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";

echo "DB Host: $host\n";
echo "DB Port: $port\n";
echo "DB User: $user\n\n";

// Test Option 1: SSL_CA => ''
echo "--- Testing Option 1: SSL_CA => '' ---\n";
try {
    $options1 = [
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
        PDO::MYSQL_ATTR_SSL_CA => ''
    ];
    $pdo = new PDO($dsn, $user, $pass, $options1);
    echo "SUCCESS!\n";
} catch (PDOException $e) {
    echo "FAILED: " . $e->getMessage() . " (Code: " . $e->getCode() . ")\n";
}

// Test Option 2: SSL_CA => true
echo "\n--- Testing Option 2: SSL_CA => true ---\n";
try {
    $options2 = [
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
        PDO::MYSQL_ATTR_SSL_CA => true
    ];
    $pdo = new PDO($dsn, $user, $pass, $options2);
    echo "SUCCESS!\n";
} catch (PDOException $e) {
    echo "FAILED: " . $e->getMessage() . " (Code: " . $e->getCode() . ")\n";
}

// Test Option 3: NO SSL OPTIONS
echo "\n--- Testing Option 3: NO SSL OPTIONS ---\n";
try {
    $pdo = new PDO($dsn, $user, $pass);
    echo "SUCCESS!\n";
} catch (PDOException $e) {
    echo "FAILED: " . $e->getMessage() . " (Code: " . $e->getCode() . ")\n";
}
