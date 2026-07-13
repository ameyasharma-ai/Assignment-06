<?php
// admin/dashboard.php - Store-wide Analytics Dashboard
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/admin_header.php';

$db = getDBConnection();

// 1. Total Store Revenue
$stmtSales = $db->query("SELECT SUM(total_amount) AS total_sales FROM orders WHERE status = 'Delivered' OR status = 'Shipped' OR status = 'Processing' OR status = 'Pending'");
$salesVal = $stmtSales->fetch()['total_sales'] ?? 0.00;

// 2. Total Orders
$stmtOrders = $db->query("SELECT COUNT(id) AS total_orders FROM orders");
$ordersVal = $stmtOrders->fetch()['total_orders'] ?? 0;

// 3. Total Products Cataloged
$stmtProd = $db->query("SELECT COUNT(id) AS total_prod FROM products");
$prodVal = $stmtProd->fetch()['total_prod'] ?? 0;

// 4. Total Active Vendors
$stmtVendors = $db->query("SELECT COUNT(id) AS total_vendors FROM vendors WHERE status = 'Active'");
$vendorsVal = $stmtVendors->fetch()['total_vendors'] ?? 0;

// Fetch recent orders
$recentOrders = [];
try {
    $stmtRecent = $db->query("
        SELECT o.*, u.name AS customer_name 
        FROM orders o
        JOIN users u ON o.user_id = u.id
        ORDER BY o.id DESC LIMIT 5
    ");
    $recentOrders = $stmtRecent->fetchAll();
} catch (Exception $e) {}
?>

<!-- Metrics Summary Panel -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-info">
            <h3>Gross Store Sales</h3>
            <p><?php echo $currency_symbol . number_format($salesVal, 2); ?></p>
        </div>
        <div class="stat-icon" style="color: var(--success); background: rgba(16, 185, 129, 0.1);"><i class="fa-solid fa-file-invoice-dollar"></i></div>
    </div>
    <div class="stat-card">
        <div class="stat-info">
            <h3>Total Orders</h3>
            <p><?php echo $ordersVal; ?></p>
        </div>
        <div class="stat-icon"><i class="fa-solid fa-cart-shopping"></i></div>
    </div>
    <div class="stat-card">
        <div class="stat-info">
            <h3>Products Listed</h3>
            <p><?php echo $prodVal; ?></p>
        </div>
        <div class="stat-icon" style="color: var(--secondary); background: rgba(6, 182, 212, 0.1);"><i class="fa-solid fa-box-open"></i></div>
    </div>
    <div class="stat-card">
        <div class="stat-info">
            <h3>Active Sellers</h3>
            <p><?php echo $vendorsVal; ?></p>
        </div>
        <div class="stat-icon" style="color: var(--warning); background: rgba(245, 158, 11, 0.1);"><i class="fa-solid fa-users"></i></div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-top: 30px;">
    <!-- Recent Orders table -->
    <div class="data-table-container">
        <div class="data-table-header">
            <h3>Recent Customer Orders</h3>
            <a href="orders.php" class="btn-admin btn-admin-secondary" style="font-size: 0.8rem; padding: 6px 12px;">Manage Orders</a>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Ref #</th>
                    <th>Customer</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Placed Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentOrders)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 20px;">No customer orders placed yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentOrders as $order): 
                        $statusBadge = 'status-pending';
                        if ($order['status'] === 'Processing') $statusBadge = 'status-processing';
                        elseif ($order['status'] === 'Shipped') $statusBadge = 'status-shipped';
                        elseif ($order['status'] === 'Delivered') $statusBadge = 'status-delivered';
                    ?>
                        <tr>
                            <td><strong>#<?php echo $order['id']; ?></strong></td>
                            <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                            <td style="font-family: 'Outfit'; font-weight: 600;"><?php echo $currency_symbol . number_format($order['total_amount'], 2); ?></td>
                            <td><span class="status-badge <?php echo $statusBadge; ?>"><?php echo htmlspecialchars($order['status']); ?></span></td>
                            <td style="color: var(--text-muted); font-size: 0.85rem;"><?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Store sales visualizer chart -->
    <div class="admin-card">
        <h3>Platform Performance</h3>
        <p style="color: var(--text-muted); font-size: 0.8rem; margin-bottom: 20px;">Gross sales index by month</p>
        
        <div class="bar-chart-container">
            <div class="bar-wrapper">
                <div class="bar" style="height: 25%;"></div>
                <div class="bar-label">Feb</div>
            </div>
            <div class="bar-wrapper">
                <div class="bar" style="height: 40%;"></div>
                <div class="bar-label">Mar</div>
            </div>
            <div class="bar-wrapper">
                <div class="bar" style="height: 55%;"></div>
                <div class="bar-label">Apr</div>
            </div>
            <div class="bar-wrapper">
                <div class="bar" style="height: 70%;"></div>
                <div class="bar-label">May</div>
            </div>
            <div class="bar-wrapper">
                <div class="bar" style="height: 90%;"></div>
                <div class="bar-label">Jun</div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
