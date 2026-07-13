<?php
// includes/header.php - Shared Frontend Header
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../db.php';
$db = getDBConnection();

// Fetch site configuration dynamically
$settings = [];
try {
    $stmt = $db->query("SELECT key_name, val_value FROM settings");
    while ($row = $stmt->fetch()) {
        $settings[$row['key_name']] = $row['val_value'];
    }
} catch (PDOException $e) {
    // Fail-safe fallbacks if table not fully loaded yet
    $settings = [
        'site_name' => 'OmniMart',
        'currency' => '$',
        'seo_title' => 'OmniMart',
        'seo_description' => 'Multi-vendor marketplace.'
    ];
}

$site_name = $settings['site_name'] ?? 'OmniMart';
$currency_symbol = $settings['currency'] ?? '$';
$seo_title = $settings['seo_title'] ?? $site_name;
$seo_description = $settings['seo_description'] ?? '';

// Get categories for navigation
$categories = [];
try {
    $categories = $db->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();
} catch (Exception $e) {
    // Silent fail
}

// Calculate cart count
$cart_count = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cart_count += ($item['quantity'] ?? 1);
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($seo_title); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($seo_description); ?>">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="container">
            <a href="index.php" class="logo">
                <i class="fa-solid fa-bag-shopping"></i> <?php echo htmlspecialchars($site_name); ?>
            </a>

            <ul class="nav-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="search.php">Shop</a></li>
                <li><a href="contact.php">Contact</a></li>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <li><a href="admin/dashboard.php" style="color: var(--primary-light);">Admin Panel</a></li>
                <?php elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'vendor'): ?>
                    <li><a href="vendor/dashboard.php" style="color: var(--secondary);">Vendor Panel</a></li>
                <?php endif; ?>
            </ul>

            <div class="nav-actions">
                <!-- Search bar -->
                <form action="search.php" method="GET" class="search-form">
                    <input type="text" name="q" placeholder="Search products..." value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>">
                    <button type="submit"><i class="fa-solid fa-magnifying-glass" style="color: var(--text-muted);"></i></button>
                </form>

                <!-- Theme switch toggle -->
                <button id="theme-toggle" class="nav-btn" title="Toggle Theme">🌙</button>

                <!-- Wishlist -->
                <a href="profile.php?tab=wishlist" class="nav-btn" title="Wishlist">
                    <i class="fa-regular fa-heart"></i>
                </a>

                <!-- Cart drawer trigger -->
                <a href="cart.php" class="nav-btn" title="Cart">
                    <i class="fa-solid fa-cart-shopping"></i>
                    <span id="cart-counter" class="nav-badge" style="<?php echo $cart_count > 0 ? '' : 'display: none;'; ?>">
                        <?php echo $cart_count; ?>
                    </span>
                </a>

                <!-- User profile / Auth -->
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="profile.php" class="nav-btn" title="My Profile">
                        <i class="fa-regular fa-user"></i>
                    </a>
                    <a href="logout.php" class="nav-btn" title="Logout" style="font-size: 1rem; color: var(--danger);">
                        <i class="fa-solid fa-right-from-bracket"></i>
                    </a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-secondary btn-sm">Login</a>
                    <a href="signup.php" class="btn btn-primary btn-sm">Sign Up</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    <main class="container" style="min-height: 70vh; padding-top: 30px; padding-bottom: 50px;">
