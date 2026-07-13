<?php
// admin/reports.php - Sales Report Builder & CSV Downloader
require_once __DIR__ . '/../db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db = getDBConnection();

// Check administrative access
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: index.php");
    exit;
}

// ----------------------------------------------------
// READ FILTER VALUES
// ----------------------------------------------------
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$vendorFilter = isset($_GET['vendor_id']) ? intval($_GET['vendor_id']) : 0;
$statusFilter = $_GET['status'] ?? '';

// Build dynamic WHERE query
$whereSql = "WHERE DATE(o.created_at) >= ? AND DATE(o.created_at) <= ?";
$params = [$startDate, $endDate];

if ($vendorFilter > 0) {
    $whereSql .= " AND p.vendor_id = ?";
    $params[] = $vendorFilter;
}

if ($statusFilter !== '') {
    $whereSql .= " AND o.status = ?";
    $params[] = $statusFilter;
}

// ----------------------------------------------------
// CSV REPORT EXPORTER
// ----------------------------------------------------
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="omnimart_sales_report_' . $startDate . '_to_' . $endDate . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['OrderID', 'CustomerName', 'ProductName', 'MerchantVendor', 'Quantity', 'ItemPrice', 'TotalAmount', 'Status', 'Date']);
    
    try {
        $stmtCsv = $db->prepare("
            SELECT oi.order_id, u.name AS customer_name, p.name AS product_name, v.name AS vendor_name, 
                   oi.quantity, oi.price, (oi.quantity * oi.price) AS total_item_val, o.status, o.created_at
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.id
            JOIN products p ON oi.product_id = p.id
            JOIN vendors v ON p.vendor_id = v.id
            JOIN users u ON o.user_id = u.id
            $whereSql
            ORDER BY o.id DESC
        ");
        $stmtCsv->execute($params);
        while ($row = $stmtCsv->fetch()) {
            fputcsv($output, [
                $row['order_id'],
                $row['customer_name'],
                $row['product_name'],
                $row['vendor_name'],
                $row['quantity'],
                $row['price'],
                $row['total_item_val'],
                $row['status'],
                $row['created_at']
            ]);
        }
    } catch (Exception $e) {}
    
    fclose($output);
    exit;
}

// ----------------------------------------------------
// QUERY ANALYTICS METRICS FOR VIEW
// ----------------------------------------------------
try {
    // 1. Fetch sales items lines
    $stmtLines = $db->prepare("
        SELECT oi.order_id, u.name AS customer_name, p.name AS product_name, v.name AS vendor_name, 
               oi.quantity, oi.price, (oi.quantity * oi.price) AS total_item_val, o.status, o.created_at
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        JOIN products p ON oi.product_id = p.id
        JOIN vendors v ON p.vendor_id = v.id
        JOIN users u ON o.user_id = u.id
        $whereSql
        ORDER BY o.id DESC
    ");
    $stmtLines->execute($params);
    $reportLines = $stmtLines->fetchAll();
    
    // Calculate totals
    $totalGrossRevenue = 0.00;
    $totalQtySold = 0;
    foreach ($reportLines as $line) {
        $totalGrossRevenue += $line['total_item_val'];
        $totalQtySold += $line['quantity'];
    }
} catch (Exception $e) {
    $reportLines = [];
    $totalGrossRevenue = 0;
    $totalQtySold = 0;
}

// Fetch vendors list for dropdown filter
$vendorsList = [];
try {
    $vendorsList = $db->query("SELECT id, name FROM vendors WHERE status = 'Active'")->fetchAll();
} catch (Exception $e) {}

require_once __DIR__ . '/../includes/admin_header.php';
?>

<!-- Report Configuration Sidebar/Inputs -->
<div class="admin-card">
    <h3>Sales Analytics Report Builder</h3>
    
    <form action="reports.php" method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-top: 16px; align-items: flex-end;">
        <div class="admin-form-group" style="margin-bottom:0;">
            <label for="start_date">Start Date</label>
            <input type="date" name="start_date" id="start_date" class="admin-form-control" value="<?php echo htmlspecialchars($startDate); ?>">
        </div>
        
        <div class="admin-form-group" style="margin-bottom:0;">
            <label for="end_date">End Date</label>
            <input type="date" name="end_date" id="end_date" class="admin-form-control" value="<?php echo htmlspecialchars($endDate); ?>">
        </div>
        
        <div class="admin-form-group" style="margin-bottom:0;">
            <label for="vendor_id">Filter Merchant</label>
            <select name="vendor_id" id="vendor_id" class="admin-form-control">
                <option value="0">All Vendors</option>
                <?php foreach ($vendorsList as $v): ?>
                    <option value="<?php echo $v['id']; ?>" <?php echo $vendorFilter === intval($v['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($v['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="admin-form-group" style="margin-bottom:0;">
            <label for="status">Order Status</label>
            <select name="status" id="status" class="admin-form-control">
                <option value="">All Statuses</option>
                <option value="Pending" <?php echo $statusFilter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="Processing" <?php echo $statusFilter === 'Processing' ? 'selected' : ''; ?>>Processing</option>
                <option value="Shipped" <?php echo $statusFilter === 'Shipped' ? 'selected' : ''; ?>>Shipped</option>
                <option value="Delivered" <?php echo $statusFilter === 'Delivered' ? 'selected' : ''; ?>>Delivered</option>
            </select>
        </div>

        <div style="display: flex; gap: 8px;">
            <button type="submit" class="btn-admin btn-admin-primary" style="height: 38px; flex-grow: 1; justify-content: center;"><i class="fa-solid fa-calculator"></i> Run Report</button>
            <a href="reports.php?start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>&vendor_id=<?php echo $vendorFilter; ?>&status=<?php echo $statusFilter; ?>&export=csv" 
               class="btn-admin btn-admin-secondary" style="height: 38px; width: 44px; display: inline-flex; align-items: center; justify-content: center; padding:0;" title="Download CSV report">
                <i class="fa-solid fa-file-excel" style="font-size: 1.1rem; color: var(--success);"></i>
            </a>
        </div>
    </form>
</div>

<!-- Performance Metrics Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-info">
            <h3>Report Gross Revenue</h3>
            <p><?php echo $currency_symbol . number_format($totalGrossRevenue, 2); ?></p>
        </div>
        <div class="stat-icon" style="color: var(--success); background: rgba(16, 185, 129, 0.1);"><i class="fa-solid fa-sack-dollar"></i></div>
    </div>
    <div class="stat-card">
        <div class="stat-info">
            <h3>Items Sold Volume</h3>
            <p><?php echo $totalQtySold; ?> units</p>
        </div>
        <div class="stat-icon"><i class="fa-solid fa-boxes-stacked"></i></div>
    </div>
    <div class="stat-card">
        <div class="stat-info">
            <h3>Avg Item Sale Price</h3>
            <p>
                <?php 
                    $avgPrice = $totalQtySold > 0 ? ($totalGrossRevenue / $totalQtySold) : 0;
                    echo $currency_symbol . number_format($avgPrice, 2); 
                ?>
            </p>
        </div>
        <div class="stat-icon" style="color: var(--secondary); background: rgba(6, 182, 212, 0.1);"><i class="fa-solid fa-chart-line"></i></div>
    </div>
</div>

<!-- Report Table Output -->
<div class="data-table-container">
    <div class="data-table-header">
        <h3>Report Statement Line Items</h3>
        <span style="color:var(--text-muted); font-size: 0.8rem;">Showing values from <?php echo date('M d, Y', strtotime($startDate)); ?> to <?php echo date('M d, Y', strtotime($endDate)); ?></span>
    </div>
    
    <table class="data-table">
        <thead>
            <tr>
                <th>Order</th>
                <th>Product</th>
                <th>Merchant</th>
                <th>Qty</th>
                <th>Unit Price</th>
                <th>Line Total</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($reportLines)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 24px;">No items match selected query configurations.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($reportLines as $line): ?>
                    <tr>
                        <td><strong>#<?php echo $line['order_id']; ?></strong></td>
                        <td><?php echo htmlspecialchars($line['product_name']); ?></td>
                        <td><span style="color: var(--secondary); font-weight:500;"><?php echo htmlspecialchars($line['vendor_name']); ?></span></td>
                        <td><?php echo $line['quantity']; ?></td>
                        <td style="font-family: 'Outfit';"><?php echo $currency_symbol . number_format($line['price'], 2); ?></td>
                        <td style="font-family: 'Outfit'; font-weight: 600; color: var(--primary-light);"><?php echo $currency_symbol . number_format($line['total_item_val'], 2); ?></td>
                        <td style="color: var(--text-muted); font-size: 0.85rem;"><?php echo date('M d, Y H:i', strtotime($line['created_at'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
