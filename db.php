<?php
// db.php - Database connection wrapper using PDO
// Supports both SQLite (default for easy setup) and MySQL.

if (!defined('DB_TYPE')) {
    define('DB_TYPE', getenv('DB_TYPE') ?: 'mysql'); 
}

// Default values (can be overridden by Environment variables or local config)
$db_host = getenv('DB_HOST') ?: '127.0.0.1';
$db_port = getenv('DB_PORT') ?: '3306';
$db_name = getenv('DB_NAME') ?: 'omnimart';
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASS') ?: '';

// Load local database config file if present (ignored by Git)
if (file_exists(__DIR__ . '/db_config.php')) {
    $local_cfg = include __DIR__ . '/db_config.php';
    if (is_array($local_cfg)) {
        $db_host = $local_cfg['host'] ?? $db_host;
        $db_port = $local_cfg['port'] ?? $db_port;
        $db_name = $local_cfg['name'] ?? $db_name;
        $db_user = $local_cfg['user'] ?? $db_user;
        $db_pass = $local_cfg['pass'] ?? $db_pass;
    }
}

define('DB_HOST', $db_host);
define('DB_PORT', $db_port);
define('DB_NAME', $db_name);
define('DB_USER', $db_user);
define('DB_PASS', $db_pass);

function getDBConnection() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    try {
        if (DB_TYPE === 'sqlite') {
            // Check if we are running in a container with a persistent volume
            $dbPath = is_dir('/data') ? '/data/omnimart.db' : __DIR__ . '/omnimart.db';
            $pdo = new PDO("sqlite:" . $dbPath);
            // Enable foreign keys in SQLite
            $pdo->exec("PRAGMA foreign_keys = ON;");
        } else {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ]);
        }
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        die("Database Connection Error: " . $e->getMessage());
    }
}

// Generates dynamic, beautiful SVG gradients for product card placeholders
function getProductImage($path, $seed = '') {
    if ($path && file_exists(__DIR__ . '/' . $path)) {
        return $path;
    }
    
    $gradients = [
        ['#6366f1', '#a855f7'],
        ['#06b6d4', '#3b82f6'],
        ['#10b981', '#059669'],
        ['#f43f5e', '#ec4899'],
        ['#f59e0b', '#eab308']
    ];
    $idx = abs(crc32($seed)) % count($gradients);
    $g = $gradients[$idx];
    
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300" width="100%" height="100%">';
    $svg .= '<defs>';
    $svg .= '<linearGradient id="grad' . $idx . '" x1="0%" y1="0%" x2="100%" y2="100%">';
    $svg .= '<stop offset="0%" style="stop-color:' . $g[0] . ';stop-opacity:1" />';
    $svg .= '<stop offset="100%" style="stop-color:' . $g[1] . ';stop-opacity:1" />';
    $svg .= '</linearGradient>';
    $svg .= '</defs>';
    $svg .= '<rect width="100%" height="100%" fill="url(#grad' . $idx . ')" />';
    $svg .= '<rect x="180" y="130" width="40" height="30" rx="3" fill="none" stroke="#ffffff" stroke-width="3" opacity="0.8"/>';
    $svg .= '<path d="M190,130 C190,120 210,120 210,130" fill="none" stroke="#ffffff" stroke-width="3" opacity="0.8"/>';
    $svg .= '<text x="50%" y="80%" dominant-baseline="middle" text-anchor="middle" fill="#ffffff" font-family="Outfit, sans-serif" font-size="20" font-weight="700" opacity="0.95">OmniMart Select</text>';
    $svg .= '</svg>';
    
    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

