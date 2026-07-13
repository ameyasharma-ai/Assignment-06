<?php
// sitemap.php - Dynamic XML sitemap generator
require_once __DIR__ . '/db.php';

header("Content-Type: application/xml; charset=utf-8");

$db = getDBConnection();
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443 ? "https://" : "http://";
$domainName = $_SERVER['HTTP_HOST'];
$base_url = $protocol . $domainName . dirname($_SERVER['PHP_SELF']) . '/';
// Clean double slashes in paths
$base_url = str_replace('//', '/', $base_url);
$base_url = str_replace(':/', '://', $base_url);

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

// 1. Static Pages
$staticPages = ['index.php', 'search.php', 'contact.php', 'signup.php', 'login.php'];
foreach ($staticPages as $page) {
    echo '  <url>' . "\n";
    echo '    <loc>' . $base_url . $page . '</loc>' . "\n";
    echo '    <changefreq>daily</changefreq>' . "\n";
    echo '    <priority>0.80</priority>' . "\n";
    echo '  </url>' . "\n";
}

// 2. Dynamic Product Pages
try {
    $stmt = $db->query("SELECT id FROM products ORDER BY id DESC");
    while ($product = $stmt->fetch()) {
        echo '  <url>' . "\n";
        echo '    <loc>' . $base_url . 'product.php?id=' . $product['id'] . '</loc>' . "\n";
        echo '    <changefreq>weekly</changefreq>' . "\n";
        echo '    <priority>0.64</priority>' . "\n";
        echo '  </url>' . "\n";
    }
} catch (Exception $e) {
    // Silent fail if db error
}

echo '</urlset>' . "\n";
