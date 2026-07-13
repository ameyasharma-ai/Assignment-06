<?php
// admin/vendors.php - Merchant Vendors Status Review
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

// Handle Status Switches (Active, Suspended, Pending)
if (isset($_GET['action']) && $_GET['action'] === 'status') {
    $vendorId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $newStatus = $_GET['status'] ?? '';
    
    $allowedStatuses = ['Pending', 'Active', 'Suspended'];
    if (in_array($newStatus, $allowedStatuses) && $vendorId > 0) {
        try {
            $stmt = $db->prepare("UPDATE vendors SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $vendorId]);
            $success = "Vendor status updated to '$newStatus' successfully.";
        } catch (Exception $e) {
            $error = 'Failed to update vendor status: ' . $e->getMessage();
        }
    } else {
        $error = 'Invalid status switch requested.';
    }
}

// Fetch all vendors from database
try {
    $stmt = $db->query("
        SELECT v.*, u.name AS merchant_owner_name, u.email AS merchant_owner_email
        FROM vendors v
        JOIN users u ON v.user_id = u.id
        ORDER BY v.id DESC
    ");
    $allVendors = $stmt->fetchAll();
} catch (Exception $e) {
    $allVendors = [];
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
        <h3>Platform Registered Merchant Vendors</h3>
        <p style="color: var(--text-muted); font-size: 0.85rem;">Approve, review, or suspend vendor store accesses.</p>
    </div>
    
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Store / Brand Name</th>
                <th>Owner / Main Contact</th>
                <th>Verification Status</th>
                <th>Management Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($allVendors)): ?>
                <tr>
                    <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 24px;">No merchant vendors registered on this platform yet.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($allVendors as $vendor): 
                    $statusBadge = 'status-pending';
                    if ($vendor['status'] === 'Active') $statusBadge = 'status-active';
                    elseif ($vendor['status'] === 'Suspended') $statusBadge = 'status-suspended';
                ?>
                    <tr>
                        <td>#<?php echo $vendor['id']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($vendor['name']); ?></strong>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($vendor['merchant_owner_name']); ?></strong><br>
                            <span style="font-size: 0.8rem; color: var(--text-muted);"><?php echo htmlspecialchars($vendor['merchant_owner_email']); ?></span>
                        </td>
                        <td>
                            <span class="status-badge <?php echo $statusBadge; ?>"><?php echo htmlspecialchars($vendor['status']); ?></span>
                        </td>
                        <td>
                            <div style="display: flex; gap: 8px;">
                                <?php if ($vendor['status'] !== 'Active'): ?>
                                    <a href="vendors.php?action=status&id=<?php echo $vendor['id']; ?>&status=Active" class="btn-admin btn-admin-primary" style="padding: 6px 12px; font-size: 0.75rem; background: var(--success);"><i class="fa-solid fa-circle-check"></i> Activate</a>
                                <?php endif; ?>
                                
                                <?php if ($vendor['status'] !== 'Suspended'): ?>
                                    <a href="vendors.php?action=status&id=<?php echo $vendor['id']; ?>&status=Suspended" class="btn-admin btn-admin-secondary" style="padding: 6px 12px; font-size: 0.75rem; color: var(--danger); border-color: var(--danger);"><i class="fa-solid fa-ban"></i> Suspend</a>
                                <?php endif; ?>
                                
                                <?php if ($vendor['status'] === 'Active'): ?>
                                    <a href="vendors.php?action=status&id=<?php echo $vendor['id']; ?>&status=Pending" class="btn-admin btn-admin-secondary" style="padding: 6px 12px; font-size: 0.75rem; color: var(--warning); border-color: var(--warning);"><i class="fa-solid fa-clock"></i> Set Pending</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
