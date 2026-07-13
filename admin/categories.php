<?php
// admin/categories.php - Product Categories Management
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/admin_header.php';

$db = getDBConnection();

// Check administrative access
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: index.php");
    exit;
}

$action = $_GET['action'] ?? 'list'; // 'list', 'add', 'edit', 'delete'
$error = '';
$success = '';

if (isset($_GET['success'])) {
    $success = $_GET['success'];
}

// ----------------------------------------------------
// PROCESS ACTIONS (DELETE, ADD, EDIT)
// ----------------------------------------------------

if ($action === 'delete') {
    $catId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    try {
        $stmtDel = $db->prepare("DELETE FROM categories WHERE id = ?");
        $stmtDel->execute([$catId]);
        header("Location: categories.php?success=Category+deleted+successfully");
        exit;
    } catch (Exception $e) {
        $error = 'Failed to delete category: ' . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');

    if (empty($name)) {
        $error = 'Category name cannot be empty.';
    } else {
        try {
            if ($action === 'add') {
                $stmt = $db->prepare("INSERT INTO categories (name) VALUES (?)");
                $stmt->execute([$name]);
                $success = 'Category created successfully!';
            } else {
                $catId = intval($_GET['id'] ?? 0);
                $stmt = $db->prepare("UPDATE categories SET name = ? WHERE id = ?");
                $stmt->execute([$name, $catId]);
                $success = 'Category updated successfully!';
            }
            header("Location: categories.php?success=" . urlencode($success));
            exit;
        } catch (Exception $e) {
            $error = 'Save failed: ' . $e->getMessage();
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

<!-- LIST AND ADD INTERFACE IN ONE ROW -->
<?php if ($action === 'list'): 
    $stmt = $db->query("SELECT * FROM categories ORDER BY name ASC");
    $allCats = $stmt->fetchAll();
?>
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
        
        <!-- Categories Table -->
        <div class="data-table-container">
            <div class="data-table-header">
                <h3>Product Categories</h3>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Category Name</th>
                        <th>Registered Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($allCats)): ?>
                        <tr>
                            <td colspan="4" style="text-align: center; color: var(--text-muted);">No categories created.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($allCats as $cat): ?>
                            <tr>
                                <td>#<?php echo $cat['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($cat['name']); ?></strong></td>
                                <td style="color: var(--text-muted); font-size: 0.85rem;"><?php echo date('M d, Y', strtotime($cat['created_at'])); ?></td>
                                <td>
                                    <div style="display: flex; gap: 8px;">
                                        <a href="categories.php?action=edit&id=<?php echo $cat['id']; ?>" class="btn-admin btn-admin-secondary" style="padding: 6px 12px; font-size:0.75rem;"><i class="fa-solid fa-pen"></i> Edit</a>
                                        <a href="categories.php?action=delete&id=<?php echo $cat['id']; ?>" class="btn-admin btn-admin-secondary" style="padding: 6px 12px; font-size:0.75rem; color: var(--danger); border-color: var(--danger);" onclick="return confirm('Deleting this category will cascade delete products associated with it. Continue?');"><i class="fa-solid fa-trash"></i> Delete</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Add Category Inline Form -->
        <div class="admin-card">
            <h3>Add Category</h3>
            <form action="categories.php?action=add" method="POST" style="margin-top: 16px;">
                <div class="admin-form-group">
                    <label for="name">Category Name</label>
                    <input type="text" name="name" id="name" class="admin-form-control" required placeholder="e.g. Health & Beauty" autofocus>
                </div>
                <button type="submit" class="btn-admin btn-admin-primary" style="width: 100%; justify-content: center; height: 38px;">
                    <i class="fa-solid fa-plus"></i> Save Category
                </button>
            </form>
        </div>
    </div>

<!-- EDIT INTERFACE -->
<?php elseif ($action === 'edit'): 
    $catId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    $stmtEdit = $db->prepare("SELECT * FROM categories WHERE id = ?");
    $stmtEdit->execute([$catId]);
    $catEdit = $stmtEdit->fetch();
    
    if (!$catEdit) {
        echo "<script>location.href='categories.php';</script>";
        exit;
    }
?>
    <div class="admin-card" style="max-width: 500px; margin: 0 auto;">
        <h3>Modify Category Name</h3>
        <form action="categories.php?action=edit&id=<?php echo $catEdit['id']; ?>" method="POST" style="margin-top: 20px;">
            <div class="admin-form-group">
                <label for="name">Category Name</label>
                <input type="text" name="name" id="name" class="admin-form-control" required value="<?php echo htmlspecialchars($catEdit['name']); ?>">
            </div>
            <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 20px;">
                <a href="categories.php" class="btn-admin btn-admin-secondary">Cancel</a>
                <button type="submit" class="btn-admin btn-admin-primary">Save Changes</button>
            </div>
        </form>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
