<?php
// vendor/dashboard.php - Vendor Analytics Dashboard
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/admin_header.php';

$vendorId = $_SESSION['vendor_id'] ?? 0;
$db = getDBConnection();

// 1. Total Products
$stmtProd = $db->prepare("SELECT COUNT(id) AS total_count, SUM(stock) AS total_stock FROM products WHERE vendor_id = ?");
$stmtProd->execute([$vendorId]);
$prodStats = $stmtProd->fetch();
$totalProducts = $prodStats['total_count'] ?? 0;
$totalStock = $prodStats['total_stock'] ?? 0;

// 2. Total Orders containing this vendor's products
$stmtOrdersCount = $db->prepare("
    SELECT COUNT(DISTINCT oi.order_id) AS total_orders 
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.id 
    WHERE p.vendor_id = ?
");
$stmtOrdersCount->execute([$vendorId]);
$totalOrders = $stmtOrdersCount->fetch()['total_orders'] ?? 0;

// 3. Total Vendor Revenue
$stmtRevenue = $db->prepare("
    SELECT SUM(oi.quantity * oi.price) AS total_rev 
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.id 
    WHERE p.vendor_id = ?
");
$stmtRevenue->execute([$vendorId]);
$totalRevenue = $stmtRevenue->fetch()['total_rev'] ?? 0.00;

// 4. Fetch Recent Orders containing items from this vendor
$stmtRecent = $db->prepare("
    SELECT DISTINCT o.id, o.created_at, o.status, o.total_amount
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE p.vendor_id = ?
    ORDER BY o.id DESC LIMIT 5
");
$stmtRecent->execute([$vendorId]);
$recentOrders = $stmtRecent->fetchAll();
?>

<!-- Analytics Metrics Row -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-info">
            <h3>My Products</h3>
            <p><?php echo $totalProducts; ?></p>
        </div>
        <div class="stat-icon"><i class="fa-solid fa-box"></i></div>
    </div>
    <div class="stat-card">
        <div class="stat-info">
            <h3>Order Volume</h3>
            <p><?php echo $totalOrders; ?></p>
        </div>
        <div class="stat-icon"><i class="fa-solid fa-cart-shopping"></i></div>
    </div>
    <div class="stat-card">
        <div class="stat-info">
            <h3>My Earnings</h3>
            <p><?php echo $currency_symbol . number_format($totalRevenue, 2); ?></p>
        </div>
        <div class="stat-icon" style="color: var(--success); background: rgba(16, 185, 129, 0.1);"><i class="fa-solid fa-hand-holding-dollar"></i></div>
    </div>
    <div class="stat-card">
        <div class="stat-info">
            <h3>Total Inventory</h3>
            <p><?php echo $totalStock; ?> units</p>
        </div>
        <div class="stat-icon" style="color: var(--secondary); background: rgba(6, 182, 212, 0.1);"><i class="fa-solid fa-warehouse"></i></div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-top: 30px;">
    <!-- Recent Orders containing vendor items -->
    <div class="data-table-container">
        <div class="data-table-header">
            <h3>Recent Merchant Sales</h3>
            <a href="orders.php" class="btn-admin btn-admin-secondary" style="font-size: 0.8rem; padding: 6px 12px;">Manage Sales Orders</a>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Ref #</th>
                    <th>Date</th>
                    <th>Subtotal Sold</th>
                    <th>Order Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentOrders)): ?>
                    <tr>
                        <td colspan="4" style="text-align: center; color: var(--text-muted);">No sales recorded yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentOrders as $order): 
                        // Fetch order subtotal specifically for this vendor's items in this order
                        $stmtSub = $db->prepare("
                            SELECT SUM(oi.quantity * oi.price) AS sub
                            FROM order_items oi
                            JOIN products p ON oi.product_id = p.id
                            WHERE oi.order_id = ? AND p.vendor_id = ?
                        ");
                        $stmtSub->execute([$order['id'], $vendorId]);
                        $subVal = $stmtSub->fetch()['sub'] ?? 0;

                        $statusBadge = 'status-pending';
                        if ($order['status'] === 'Processing') $statusBadge = 'status-processing';
                        elseif ($order['status'] === 'Shipped') $statusBadge = 'status-shipped';
                        elseif ($order['status'] === 'Delivered') $statusBadge = 'status-delivered';
                    ?>
                        <tr>
                            <td><strong>#<?php echo $order['id']; ?></strong></td>
                            <td style="color: var(--text-muted); font-size: 0.85rem;"><?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?></td>
                            <td style="font-family: 'Outfit'; font-weight: 600;"><?php echo $currency_symbol . number_format($subVal, 2); ?></td>
                            <td><span class="status-badge <?php echo $statusBadge; ?>"><?php echo htmlspecialchars($order['status']); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Sales Visualization Graph -->
    <div class="admin-card">
        <h3>Performance Insights</h3>
        <p style="color: var(--text-muted); font-size: 0.8rem; margin-bottom: 20px;">Monthly sales share index</p>
        
        <div class="bar-chart-container">
            <div class="bar-wrapper">
                <div class="bar" style="height: 35%;"></div>
                <div class="bar-label">Mar</div>
            </div>
            <div class="bar-wrapper">
                <div class="bar" style="height: 50%;"></div>
                <div class="bar-label">Apr</div>
            </div>
            <div class="bar-wrapper">
                <div class="bar" style="height: 40%;"></div>
                <div class="bar-label">May</div>
            </div>
            <div class="bar-wrapper">
                <div class="bar" style="height: 75%;"></div>
                <div class="bar-label">Jun</div>
            </div>
            <div class="bar-wrapper">
                <div class="bar" style="height: 90%;"></div>
                <div class="bar-label">Jul</div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
