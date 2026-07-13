<?php
// admin/orders.php - Platform Purchase Orders Processing
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/admin_header.php';

$db = getDBConnection();

// Check administrative access
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: index.php");
    exit;
}

$error = '';
$success = '';

// Handle Status Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order_status'])) {
    $orderId = intval($_POST['order_id'] ?? 0);
    $newStatus = $_POST['status'] ?? '';
    
    $allowedStatuses = ['Pending', 'Processing', 'Shipped', 'Delivered'];
    if ($orderId > 0 && in_array($newStatus, $allowedStatuses)) {
        try {
            $stmt = $db->prepare("UPDATE orders SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $orderId]);
            $success = "Order #$orderId status updated to '$newStatus' successfully.";
        } catch (Exception $e) {
            $error = 'Failed to update order status: ' . $e->getMessage();
        }
    } else {
        $error = 'Invalid order update request parameters.';
    }
}

// Fetch all orders
try {
    $stmt = $db->query("
        SELECT o.*, u.name AS customer_name, u.email AS customer_email
        FROM orders o
        JOIN users u ON o.user_id = u.id
        ORDER BY o.id DESC
    ");
    $orders = $stmt->fetchAll();
} catch (Exception $e) {
    $orders = [];
}

// Fetch item details for all orders
$orderItems = [];
foreach ($orders as $order) {
    try {
        $stmtItems = $db->prepare("
            SELECT oi.*, p.name AS product_name, v.name AS vendor_name
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            JOIN vendors v ON p.vendor_id = v.id
            WHERE oi.order_id = ?
        ");
        $stmtItems->execute([$order['id']]);
        $orderItems[$order['id']] = $stmtItems->fetchAll();
    } catch (Exception $e) {
        $orderItems[$order['id']] = [];
    }
}
?>

<?php if (!empty($error)): ?>
    <div style="background: rgba(239, 68, 68, 0.15); color: var(--danger); padding: 12px; border-radius: 6px; margin-bottom: 20px;">
        <i class="fa-solid fa-triangle-exclamation"></i> <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div style="background: rgba(16, 185, 129, 0.15); color: var(--success); padding: 12px; border-radius: 6px; margin-bottom: 20px;">
        <i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($success); ?>
    </div>
<?php endif; ?>

<div class="data-table-container">
    <div class="data-table-header">
        <h3>Platform Customer Orders Processing</h3>
        <p style="color: var(--text-muted); font-size: 0.85rem;">Review order detail receipts, verify fulfillment, and dispatch shipping statuses.</p>
    </div>
    
    <table class="data-table">
        <thead>
            <tr>
                <th>Order ID</th>
                <th>Customer Contact</th>
                <th>Ordered Items & Merchant Source</th>
                <th>Total Value</th>
                <th>Dispatch Status</th>
                <th>Action Updates</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($orders)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 30px;">No customer orders placed yet.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($orders as $order): 
                    $items = $orderItems[$order['id']] ?? [];
                    
                    $statusBadge = 'status-pending';
                    if ($order['status'] === 'Processing') $statusBadge = 'status-processing';
                    elseif ($order['status'] === 'Shipped') $statusBadge = 'status-shipped';
                    elseif ($order['status'] === 'Delivered') $statusBadge = 'status-delivered';
                ?>
                    <tr>
                        <td><strong>#<?php echo $order['id']; ?></strong><br><span style="font-size:0.75rem; color:var(--text-muted);"><?php echo date('M d, Y', strtotime($order['created_at'])); ?></span></td>
                        <td>
                            <strong><?php echo htmlspecialchars($order['customer_name']); ?></strong><br>
                            <span style="font-size:0.8rem; color:var(--text-muted);"><?php echo htmlspecialchars($order['customer_email']); ?></span>
                        </td>
                        <td>
                            <div style="display: flex; flex-direction: column; gap: 8px;">
                                <?php foreach ($items as $item): ?>
                                    <div style="font-size: 0.85rem; line-height: 1.3;">
                                        • <?php echo htmlspecialchars($item['product_name']); ?> 
                                        <strong>x<?php echo $item['quantity']; ?></strong> 
                                        <span style="color: var(--secondary); font-size: 0.75rem;">(Vendor: <?php echo htmlspecialchars($item['vendor_name']); ?>)</span>
                                        <?php if (!empty($item['variation_details'])): ?>
                                            <span style="color: var(--primary-light); font-size: 0.75rem; display: block; margin-left: 8px;"><?php echo htmlspecialchars($item['variation_details']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </td>
                        <td style="font-family: 'Outfit'; font-weight: 700; color: var(--primary-light);"><?php echo $currency_symbol . number_format($order['total_amount'], 2); ?></td>
                        <td><span class="status-badge <?php echo $statusBadge; ?>"><?php echo htmlspecialchars($order['status']); ?></span></td>
                        <td>
                            <form action="orders.php" method="POST" style="display: flex; gap: 6px; align-items: center;">
                                <input type="hidden" name="update_order_status" value="1">
                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                
                                <select name="status" class="admin-form-control" style="padding: 6px; font-size: 0.85rem; width: 110px;">
                                    <option value="Pending" <?php echo $order['status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="Processing" <?php echo $order['status'] === 'Processing' ? 'selected' : ''; ?>>Processing</option>
                                    <option value="Shipped" <?php echo $order['status'] === 'Shipped' ? 'selected' : ''; ?>>Shipped</option>
                                    <option value="Delivered" <?php echo $order['status'] === 'Delivered' ? 'selected' : ''; ?>>Delivered</option>
                                </select>
                                
                                <button type="submit" class="btn-admin btn-admin-primary" style="padding: 6px 10px; font-size: 0.8rem; height: 32px;"><i class="fa-solid fa-save"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
