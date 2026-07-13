<?php
// includes/admin_header.php - Shared Panel Header for Admin & Vendor Dashboards
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../db.php';
$db = getDBConnection();

// Check authentication status and role authorization
$requestUri = $_SERVER['REQUEST_URI'];
$isAdminPanel = (strpos($requestUri, '/admin/') !== false);
$isVendorPanel = (strpos($requestUri, '/vendor/') !== false);

if (!isset($_SESSION['user_id'])) {
    if ($isAdminPanel) {
        header("Location: index.php"); // redirect admin attempts to home
        exit;
    } else {
        header("Location: ../login.php");
        exit;
    }
}

$userRole = $_SESSION['role'] ?? 'customer';
$userEmail = $_SESSION['email'] ?? '';
$userName = $_SESSION['name'] ?? 'User';

if ($isAdminPanel && $userRole !== 'admin') {
    die("Access Denied: Administrative privileges required.");
}

if ($isVendorPanel && $userRole !== 'vendor') {
    die("Access Denied: Vendor credentials required.");
}

// Fetch site configuration dynamically
$settings = [];
try {
    $stmt = $db->query("SELECT key_name, val_value FROM settings");
    while ($row = $stmt->fetch()) {
        $settings[$row['key_name']] = $row['val_value'];
    }
} catch (Exception $e) {
    // Fail-safe fallbacks
    $settings = ['site_name' => 'OmniMart', 'currency' => '$'];
}

$site_name = $settings['site_name'] ?? 'OmniMart';
$currency_symbol = $settings['currency'] ?? '$';

// Identify page names for active highlighting
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $userRole === 'admin' ? 'Admin Control Center' : 'Vendor Merchant Desk'; ?> | <?php echo htmlspecialchars($site_name); ?></title>
    <!-- Use relative styling paths based on folder depth -->
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-wrapper">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-brand">
                <i class="fa-solid fa-gauge-high"></i> <?php echo htmlspecialchars($site_name); ?>
            </div>
            
            <ul class="sidebar-menu">
                <?php if ($userRole === 'admin'): ?>
                    <!-- Admin Menu -->
                    <li class="sidebar-item <?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>">
                        <a href="dashboard.php"><i class="fa-solid fa-chart-line"></i> Dashboard</a>
                    </li>
                    <li class="sidebar-item <?php echo $currentPage === 'products.php' ? 'active' : ''; ?>">
                        <a href="products.php"><i class="fa-solid fa-box-open"></i> Products</a>
                    </li>
                    <li class="sidebar-item <?php echo $currentPage === 'categories.php' ? 'active' : ''; ?>">
                        <a href="categories.php"><i class="fa-solid fa-folder-tree"></i> Categories</a>
                    </li>
                    <li class="sidebar-item <?php echo $currentPage === 'vendors.php' ? 'active' : ''; ?>">
                        <a href="vendors.php"><i class="fa-solid fa-users-viewfinder"></i> Vendors</a>
                    </li>
                    <li class="sidebar-item <?php echo $currentPage === 'orders.php' ? 'active' : ''; ?>">
                        <a href="orders.php"><i class="fa-solid fa-cart-flatbed"></i> Orders</a>
                    </li>
                    <li class="sidebar-item <?php echo $currentPage === 'coupons.php' ? 'active' : ''; ?>">
                        <a href="coupons.php"><i class="fa-solid fa-ticket"></i> Coupons</a>
                    </li>
                    <li class="sidebar-item <?php echo $currentPage === 'reports.php' ? 'active' : ''; ?>">
                        <a href="reports.php"><i class="fa-solid fa-file-invoice-dollar"></i> Reports</a>
                    </li>
                    <li class="sidebar-item <?php echo $currentPage === 'settings.php' ? 'active' : ''; ?>">
                        <a href="settings.php"><i class="fa-solid fa-gears"></i> Settings</a>
                    </li>
                <?php elseif ($userRole === 'vendor'): ?>
                    <!-- Vendor Menu -->
                    <li class="sidebar-item <?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>">
                        <a href="dashboard.php"><i class="fa-solid fa-chart-pie"></i> Sales Dashboard</a>
                    </li>
                    <li class="sidebar-item <?php echo $currentPage === 'products.php' ? 'active' : ''; ?>">
                        <a href="products.php"><i class="fa-solid fa-boxes-packing"></i> My Products</a>
                    </li>
                    <li class="sidebar-item <?php echo $currentPage === 'orders.php' ? 'active' : ''; ?>">
                        <a href="orders.php"><i class="fa-solid fa-truck-ramp-box"></i> My Orders</a>
                    </li>
                <?php endif; ?>
                
                <li class="sidebar-item" style="margin-top: 30px;">
                    <a href="../index.php"><i class="fa-solid fa-globe"></i> View Storefront</a>
                </li>
                <li class="sidebar-item">
                    <a href="../logout.php" style="color: var(--danger);"><i class="fa-solid fa-power-off"></i> Sign Out</a>
                </li>
            </ul>
            
            <div class="sidebar-footer">
                <div style="font-size: 0.8rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                    <strong><?php echo htmlspecialchars($userName); ?></strong><br>
                    <span style="color: var(--text-muted);"><?php echo htmlspecialchars($userRole); ?></span>
                </div>
            </div>
        </aside>
        
        <!-- Main Panel Body -->
        <div class="main-content">
            <!-- Header bar inside Dashboard -->
            <header class="content-header">
                <h2>
                    <?php 
                        if ($userRole === 'admin') echo "Administrator Control Center";
                        else echo "Vendor Portal - " . htmlspecialchars($_SESSION['vendor_name'] ?? 'Store');
                    ?>
                </h2>
                <div style="display: flex; align-items: center; gap: 16px;">
                    <span class="status-badge <?php echo $userRole === 'admin' ? 'status-active' : 'status-processing'; ?>">
                        ONLINE: <?php echo htmlspecialchars($userName); ?>
                    </span>
                </div>
            </header>
            
            <!-- Body area for specific dashboard modules -->
            <main class="content-body">
