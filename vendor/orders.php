<?php
// vendor/orders.php - Merchant Sales Orders Tracking
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/admin_header.php';

$vendorId = $_SESSION['vendor_id'] ?? 0;
$db = getDBConnection();

// Fetch orders containing products from this vendor
// Join orders, users, order_items, products
try {
    $stmt = $db->prepare("
        SELECT DISTINCT o.*, u.name AS customer_name, u.email AS customer_email
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        JOIN users u ON o.user_id = u.id
        WHERE p.vendor_id = ?
        ORDER BY o.id DESC
    ");
    $stmt->execute([$vendorId]);
    $vendorOrders = $stmt->fetchAll();
} catch (Exception $e) {
    $vendorOrders = [];
}

// Fetch item details for each order specifically matching this vendor
$vendorOrderItems = [];
foreach ($vendorOrders as $order) {
    try {
        $stmtItems = $db->prepare("
            SELECT oi.*, p.name AS product_name, p.image AS product_image
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ? AND p.vendor_id = ?
        ");
        $stmtItems->execute([$order['id'], $vendorId]);
        $vendorOrderItems[$order['id']] = $stmtItems->fetchAll();
    } catch (Exception $e) {
        $vendorOrderItems[$order['id']] = [];
    }
}
?>

<div class="data-table-container">
    <div class="data-table-header">
        <h3>Sales Orders Dispatch Queue</h3>
        <p style="color: var(--text-muted); font-size: 0.85rem;">Displaying customer requests containing your products</p>
    </div>
    
    <table class="data-table">
        <thead>
            <tr>
                <th>Order Ref</th>
                <th>Customer Contact</th>
                <th>Order Items to Ship</th>
                <th>My Revenue</th>
                <th>Global Status</th>
                <th>Placed Date</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($vendorOrders)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 30px;">
                        No customer orders have been received for your products yet.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($vendorOrders as $order): 
                    $orderItems = $vendorOrderItems[$order['id']] ?? [];
                    
                    // Sum subtotal for this vendor
                    $mySubtotal = 0;
                    foreach ($orderItems as $item) {
                        $mySubtotal += ($item['quantity'] * $item['price']);
                    }

                    $statusBadge = 'status-pending';
                    if ($order['status'] === 'Processing') $statusBadge = 'status-processing';
                    elseif ($order['status'] === 'Shipped') $statusBadge = 'status-shipped';
                    elseif ($order['status'] === 'Delivered') $statusBadge = 'status-delivered';
                ?>
                    <tr>
                        <td><strong>#<?php echo $order['id']; ?></strong></td>
                        <td>
                            <strong><?php echo htmlspecialchars($order['customer_name']); ?></strong><br>
                            <span style="font-size: 0.75rem; color: var(--text-muted);"><?php echo htmlspecialchars($order['customer_email']); ?></span>
                        </td>
                        <td>
                            <div style="display: flex; flex-direction: column; gap: 8px;">
                                <?php foreach ($orderItems as $item): ?>
                                    <div style="font-size: 0.85rem; line-height: 1.3;">
                                        • <?php echo htmlspecialchars($item['product_name']); ?> 
                                        <strong>x<?php echo $item['quantity']; ?></strong> 
                                        <?php if (!empty($item['variation_details'])): ?>
                                            <span style="color: var(--primary-light); font-size: 0.75rem;">(<?php echo htmlspecialchars($item['variation_details']); ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </td>
                        <td style="font-family: 'Outfit'; font-weight: 600;"><?php echo $currency_symbol . number_format($mySubtotal, 2); ?></td>
                        <td><span class="status-badge <?php echo $statusBadge; ?>"><?php echo htmlspecialchars($order['status']); ?></span></td>
                        <td style="color: var(--text-muted); font-size: 0.85rem;"><?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
