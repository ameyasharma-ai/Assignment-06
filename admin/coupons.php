<?php
// admin/coupons.php - Discount Promo Codes Controller
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/admin_header.php';

$db = getDBConnection();

// Check administrative access
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: index.php");
    exit;
}

$action = $_GET['action'] ?? 'list'; // 'list', 'add', 'delete'
$error = '';
$success = '';

if (isset($_GET['success'])) {
    $success = $_GET['success'];
}

// ----------------------------------------------------
// PROCESS ACTIONS (DELETE & ADD)
// ----------------------------------------------------

if ($action === 'delete') {
    $couponId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    try {
        $stmtDel = $db->prepare("DELETE FROM coupons WHERE id = ?");
        $stmtDel->execute([$couponId]);
        header("Location: coupons.php?success=Coupon+removed+successfully");
        exit;
    } catch (Exception $e) {
        $error = 'Failed to delete coupon: ' . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $discountType = $_POST['discount_type'] ?? 'percentage'; // 'percentage' or 'fixed'
    $value = floatval($_POST['value'] ?? 0);
    $expiry = $_POST['expiry'] ?? '';

    if (empty($code) || $value <= 0 || empty($expiry)) {
        $error = 'Promo code, discount value, and expiration date are required.';
    } else {
        try {
            $stmt = $db->prepare("INSERT INTO coupons (code, discount_type, value, expiry) VALUES (?, ?, ?, ?)");
            $stmt->execute([$code, $discountType, $value, $expiry]);
            header("Location: coupons.php?success=New+coupon+code+created+successfully!");
            exit;
        } catch (Exception $e) {
            $error = 'Could not create coupon code: ' . $e->getMessage();
        }
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

<!-- SPLIT GRID INTERFACE: LIST COUPONS + CREATE COUPON INLINE -->
<?php if ($action === 'list'): 
    $stmt = $db->query("SELECT * FROM coupons ORDER BY id DESC");
    $coupons = $stmt->fetchAll();
?>
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
        
        <!-- Coupons Table -->
        <div class="data-table-container">
            <div class="data-table-header">
                <h3>Active Campaign Promo Coupons</h3>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Discount Value</th>
                        <th>Valid Expiration</th>
                        <th>Remove</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($coupons)): ?>
                        <tr>
                            <td colspan="4" style="text-align: center; color: var(--text-muted);">No coupon codes registered.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($coupons as $cp): 
                            $isExpired = strtotime($cp['expiry']) < time();
                        ?>
                            <tr style="<?php echo $isExpired ? 'opacity: 0.5;' : ''; ?>">
                                <td>
                                    <strong style="color: var(--primary-light); font-size: 1.05rem; letter-spacing: 0.05em;"><i class="fa-solid fa-tag"></i> <?php echo htmlspecialchars($cp['code']); ?></strong>
                                </td>
                                <td>
                                    <?php 
                                        if ($cp['discount_type'] === 'percentage') {
                                            echo intval($cp['value']) . '% Off Subtotal';
                                        } else {
                                            echo $currency_symbol . number_format($cp['value'], 2) . ' Flat Discount';
                                        }
                                    ?>
                                </td>
                                <td>
                                    <?php echo date('M d, Y', strtotime($cp['expiry'])); ?>
                                    <?php if ($isExpired): ?>
                                        <span class="badge badge-danger" style="margin-left:8px;">Expired</span>
                                    <?php else: ?>
                                        <span class="badge badge-success" style="margin-left:8px;">Active</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="coupons.php?action=delete&id=<?php echo $cp['id']; ?>" class="btn-admin btn-admin-secondary" style="color: var(--danger); border-color: var(--danger); padding: 4px 8px;" onclick="return confirm('Remove this promo coupon?');"><i class="fa-solid fa-trash"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Create Coupon Form -->
        <div class="admin-card">
            <h3>Generate Promo Coupon</h3>
            
            <form action="coupons.php?action=add" method="POST" style="margin-top: 20px;">
                <div class="admin-form-group">
                    <label for="code">Coupon Promo Code</label>
                    <input type="text" name="code" id="code" class="admin-form-control" required placeholder="e.g. EXTRA50" style="text-transform: uppercase;">
                </div>
                
                <div class="admin-form-group">
                    <label for="discount_type">Discount Type</label>
                    <select name="discount_type" id="discount_type" class="admin-form-control">
                        <option value="percentage">Percentage discount (%)</option>
                        <option value="fixed">Fixed cash discount (<?php echo $currency_symbol; ?>)</option>
                    </select>
                </div>

                <div class="admin-form-group">
                    <label for="value">Discount Value / Amount</label>
                    <input type="number" step="0.01" name="value" id="value" class="admin-form-control" required placeholder="e.g. 15.00">
                </div>

                <div class="admin-form-group">
                    <label for="expiry">Campaign Expiry Date</label>
                    <input type="date" name="expiry" id="expiry" class="admin-form-control" required value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>">
                </div>

                <button type="submit" class="btn-admin btn-admin-primary" style="width: 100%; justify-content: center; height: 38px;">
                    <i class="fa-solid fa-ticket"></i> Save Discount Coupon
                </button>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
