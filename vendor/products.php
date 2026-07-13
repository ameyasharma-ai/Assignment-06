<?php
// vendor/products.php - Merchant Product CRUD Management
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/admin_header.php';

$vendorId = $_SESSION['vendor_id'] ?? 0;
$db = getDBConnection();

$action = $_GET['action'] ?? 'list'; // 'list', 'add', 'edit', 'delete'
$error = '';
$success = '';

// Make sure target assets/images directory exists
if (!is_dir(__DIR__ . '/../assets/images')) {
    mkdir(__DIR__ . '/../assets/images', 0755, true);
}

// ----------------------------------------------------
// PROCESS ACTIONS (ADD, EDIT, DELETE)
// ----------------------------------------------------

// DELETE
if ($action === 'delete') {
    $prodId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    // Verify product belongs to this vendor
    $stmtCheck = $db->prepare("SELECT id FROM products WHERE id = ? AND vendor_id = ?");
    $stmtCheck->execute([$prodId, $vendorId]);
    if ($stmtCheck->fetch()) {
        try {
            $stmtDel = $db->prepare("DELETE FROM products WHERE id = ?");
            $stmtDel->execute([$prodId]);
            header("Location: products.php?success=Product+deleted+successfully");
            exit;
        } catch (Exception $e) {
            $error = 'Failed to delete product: ' . $e->getMessage();
        }
    } else {
        $error = 'Access Denied: Product not found or unauthorized.';
    }
}

// ADD & EDIT POST HANDLERS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $stock = intval($_POST['stock'] ?? 0);
    $categoryId = intval($_POST['category_id'] ?? 0);
    
    // Variations parser inputs
    $colorVarRaw = trim($_POST['variations_color'] ?? ''); // e.g. "Black:10,Silver:15"
    $sizeVarRaw = trim($_POST['variations_size'] ?? '');   // e.g. "M:12,L:13"

    if (empty($name) || $price <= 0 || $categoryId <= 0) {
        $error = 'Product name, price, and category are required.';
    } else {
        // Image uploading handling
        $imagePath = $_POST['existing_image'] ?? '';
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['product_image']['tmp_name'];
            $fileName = $_FILES['product_image']['name'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            if (in_array($fileExtension, $allowedExtensions)) {
                $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
                $destPath = __DIR__ . '/../assets/images/' . $newFileName;
                if (move_uploaded_file($fileTmpPath, $destPath)) {
                    $imagePath = 'assets/images/' . $newFileName;
                }
            } else {
                $error = 'Invalid image file type. Allowed: png, jpg, jpeg, webp, gif';
            }
        }

        if (empty($error)) {
            try {
                $db->beginTransaction();

                if ($action === 'add') {
                    // INSERT PRODUCT
                    $stmt = $db->prepare("
                        INSERT INTO products (vendor_id, name, description, price, stock, image, category_id) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$vendorId, $name, $description, $price, $stock, $imagePath, $categoryId]);
                    $productId = $db->lastInsertId();
                    $success = 'Product added successfully!';
                } else {
                    // UPDATE PRODUCT
                    $productId = intval($_GET['id'] ?? 0);
                    // Verify ownership
                    $stmtCheck = $db->prepare("SELECT id FROM products WHERE id = ? AND vendor_id = ?");
                    $stmtCheck->execute([$productId, $vendorId]);
                    if (!$stmtCheck->fetch()) {
                        throw new Exception("Unauthorized edit attempt.");
                    }

                    $stmt = $db->prepare("
                        UPDATE products 
                        SET name = ?, description = ?, price = ?, stock = ?, image = ?, category_id = ? 
                        WHERE id = ? AND vendor_id = ?
                    ");
                    $stmt->execute([$name, $description, $price, $stock, $imagePath, $categoryId, $productId, $vendorId]);
                    
                    // Clear existing variations before rebuilding
                    $stmtClearVar = $db->prepare("DELETE FROM product_variations WHERE product_id = ?");
                    $stmtClearVar->execute([$productId]);
                    
                    $success = 'Product updated successfully!';
                }

                // Parse and insert Color Variations
                if (!empty($colorVarRaw)) {
                    $stmtVar = $db->prepare("INSERT INTO product_variations (product_id, attribute, value, stock) VALUES (?, 'Color', ?, ?)");
                    $pairs = explode(',', $colorVarRaw);
                    foreach ($pairs as $p) {
                        $parts = explode(':', $p);
                        if (count($parts) === 2) {
                            $stmtVar->execute([$productId, trim($parts[0]), intval($parts[1])]);
                        }
                    }
                }

                // Parse and insert Size Variations
                if (!empty($sizeVarRaw)) {
                    $stmtVar = $db->prepare("INSERT INTO product_variations (product_id, attribute, value, stock) VALUES (?, 'Size', ?, ?)");
                    $pairs = explode(',', $sizeVarRaw);
                    foreach ($pairs as $p) {
                        $parts = explode(':', $p);
                        if (count($parts) === 2) {
                            $stmtVar->execute([$productId, trim($parts[0]), intval($parts[1])]);
                        }
                    }
                }

                $db->commit();
                
                // Redirect back to list
                header("Location: products.php?success=" . urlencode($success));
                exit;

            } catch (Exception $e) {
                $db->rollBack();
                $error = 'Save failed: ' . $e->getMessage();
            }
        }
    }
}

// Fetch categories for form selects
$categories = [];
try {
    $categories = $db->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
} catch (Exception $e) {}

// Read success msg from redirect
if (isset($_GET['success'])) {
    $success = $_GET['success'];
}
?>

<?php if (!empty($error)): ?>
    <div style="background: rgba(239, 68, 68, 0.15); color: var(--danger); padding: 12px; border-radius: 6px; margin-bottom: 20px; font-weight: 500;">
        <i class="fa-solid fa-triangle-exclamation"></i> <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div style="background: rgba(16, 185, 129, 0.15); color: var(--success); padding: 12px; border-radius: 6px; margin-bottom: 20px; font-weight: 500;">
        <i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($success); ?>
    </div>
<?php endif; ?>

<!-- ---------------------------------------------------- -->
<!-- LIST PRODUCTS MODULE -->
<!-- ---------------------------------------------------- -->
<?php if ($action === 'list'): 
    // Fetch products
    $stmtProd = $db->prepare("
        SELECT p.*, c.name AS category_name 
        FROM products p
        JOIN categories c ON p.category_id = c.id
        WHERE p.vendor_id = ?
        ORDER BY p.id DESC
    ");
    $stmtProd->execute([$vendorId]);
    $vendorProducts = $stmtProd->fetchAll();
?>
    <div class="data-table-container">
        <div class="data-table-header">
            <h3>My Catalog Listings</h3>
            <a href="products.php?action=add" class="btn-admin btn-admin-primary"><i class="fa-solid fa-plus"></i> Add New Product</a>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Image</th>
                    <th>Product Name</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($vendorProducts)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 30px;">
                            You have no products listed. Click "Add New Product" to start selling.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($vendorProducts as $prod): 
                        $prodImg = getProductImage($prod['image'], $prod['name']);
                    ?>
                        <tr>
                            <td>
                                <img src="<?php echo $prodImg; ?>" alt="" style="width: 50px; height: 50px; object-fit: cover; border-radius: 6px;">
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($prod['name']); ?></strong>
                            </td>
                            <td style="color: var(--text-muted); font-size: 0.9rem;"><?php echo htmlspecialchars($prod['category_name']); ?></td>
                            <td style="font-family: 'Outfit'; font-weight: 600;"><?php echo $currency_symbol . number_format($prod['price'], 2); ?></td>
                            <td>
                                <?php if ($prod['stock'] > 10): ?>
                                    <span class="badge badge-success"><?php echo $prod['stock']; ?> units</span>
                                <?php elseif ($prod['stock'] > 0): ?>
                                    <span class="badge badge-warning">Low: <?php echo $prod['stock']; ?></span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Out of Stock</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display: flex; gap: 10px;">
                                    <a href="products.php?action=edit&id=<?php echo $prod['id']; ?>" class="btn-admin btn-admin-secondary" style="font-size: 0.8rem; padding: 6px 12px;" title="Edit details"><i class="fa-solid fa-pen"></i> Edit</a>
                                    <a href="products.php?action=delete&id=<?php echo $prod['id']; ?>" class="btn-admin btn-admin-secondary" style="font-size: 0.8rem; padding: 6px 12px; color: var(--danger); border-color: var(--danger);" onclick="return confirm('Are you sure you want to delete this listing?');" title="Delete listing"><i class="fa-solid fa-trash"></i> Delete</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

<!-- ---------------------------------------------------- -->
<!-- ADD / EDIT PRODUCT FORM -->
<!-- ---------------------------------------------------- -->
<?php elseif ($action === 'add' || $action === 'edit'): 
    $title = 'Add New Listing';
    $nameVal = '';
    $descVal = '';
    $priceVal = '';
    $stockVal = 0;
    $catVal = 0;
    $imageVal = '';
    
    // Prep color/size variation strings
    $colorValStr = '';
    $sizeValStr = '';

    if ($action === 'edit') {
        $productId = isset($_GET['id']) ? intval($_GET['id']) : 0;
        // Fetch details
        $stmtEdit = $db->prepare("SELECT * FROM products WHERE id = ? AND vendor_id = ?");
        $stmtEdit->execute([$productId, $vendorId]);
        $prodEdit = $stmtEdit->fetch();

        if ($prodEdit) {
            $title = 'Edit Product Details - #' . $prodEdit['id'];
            $nameVal = $prodEdit['name'];
            $descVal = $prodEdit['description'];
            $priceVal = $prodEdit['price'];
            $stockVal = $prodEdit['stock'];
            $catVal = $prodEdit['category_id'];
            $imageVal = $prodEdit['image'];

            // Fetch variations and format back to inputs
            $stmtVarSelect = $db->prepare("SELECT * FROM product_variations WHERE product_id = ?");
            $stmtVarSelect->execute([$productId]);
            $cv = [];
            $sv = [];
            while ($row = $stmtVarSelect->fetch()) {
                if ($row['attribute'] === 'Color') {
                    $cv[] = $row['value'] . ':' . $row['stock'];
                } elseif ($row['attribute'] === 'Size') {
                    $sv[] = $row['value'] . ':' . $row['stock'];
                }
            }
            $colorValStr = implode(',', $cv);
            $sizeValStr = implode(',', $sv);
        } else {
            echo "<script>location.href='products.php';</script>";
            exit;
        }
    }
?>
    <div class="admin-card" style="max-width: 800px; margin: 0 auto;">
        <h3 style="margin-bottom: 24px; border-bottom: 1px solid var(--border-color); padding-bottom: 12px;"><?php echo $title; ?></h3>
        
        <form action="products.php?action=<?php echo $action; ?><?php echo $action==='edit' ? '&id='.$productId : ''; ?>" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="existing_image" value="<?php echo htmlspecialchars($imageVal); ?>">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="admin-form-group">
                    <label for="name">Product Name *</label>
                    <input type="text" name="name" id="name" class="admin-form-control" required value="<?php echo htmlspecialchars($nameVal); ?>" placeholder="e.g. Mechanical Keyboard">
                </div>
                
                <div class="admin-form-group">
                    <label for="category_id">Category *</label>
                    <select name="category_id" id="category_id" class="admin-form-control" required>
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $catVal === intval($cat['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="admin-form-group">
                <label for="description">Product Description</label>
                <textarea name="description" id="description" rows="4" class="admin-form-control" placeholder="Describe key attributes, features, warranty, specs..."><?php echo htmlspecialchars($descVal); ?></textarea>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="admin-form-group">
                    <label for="price">Price (<?php echo $currency_symbol; ?>) *</label>
                    <input type="number" step="0.01" name="price" id="price" class="admin-form-control" required value="<?php echo htmlspecialchars($priceVal); ?>" placeholder="99.99">
                </div>
                
                <div class="admin-form-group">
                    <label for="stock">Main Stock Inventory Count *</label>
                    <input type="number" name="stock" id="stock" class="admin-form-control" required value="<?php echo $stockVal; ?>" placeholder="50">
                </div>
            </div>

            <!-- Upload File -->
            <div class="admin-form-group">
                <label for="product_image">Product Image File</label>
                <?php if (!empty($imageVal)): ?>
                    <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 10px;">
                        <img src="../<?php echo $imageVal; ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 6px;">
                        <span style="font-size: 0.8rem; color: var(--text-muted);">Current image file: <?php echo basename($imageVal); ?></span>
                    </div>
                <?php endif; ?>
                <input type="file" name="product_image" id="product_image" class="admin-form-control" style="padding: 6px;">
                <span style="font-size: 0.75rem; color: var(--text-muted);">Supported formats: PNG, JPG, JPEG, WEBP, GIF</span>
            </div>

            <!-- Variations Block Input -->
            <div class="admin-card" style="background: var(--bg-base); margin-top: 30px; border-color: rgba(255,255,255,0.04);">
                <h4 style="margin-bottom: 12px;"><i class="fa-solid fa-code-branch"></i> Product Variations (Optional)</h4>
                <p style="color: var(--text-muted); font-size: 0.75rem; margin-bottom: 16px;">
                    Define attributes using the format: <strong>Value:Stock</strong> separated by commas. E.g. Color variation: <code>Black:10,White:15</code>. Size variation: <code>M:5,L:10</code>.
                </p>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="admin-form-group">
                        <label for="variations_color">Color Options</label>
                        <input type="text" name="variations_color" id="variations_color" class="admin-form-control" value="<?php echo htmlspecialchars($colorValStr); ?>" placeholder="Black:25,Silver:25">
                    </div>
                    <div class="admin-form-group">
                        <label for="variations_size">Size Options</label>
                        <input type="text" name="variations_size" id="variations_size" class="admin-form-control" value="<?php echo htmlspecialchars($sizeValStr); ?>" placeholder="S:10,M:15,L:15">
                    </div>
                </div>
            </div>

            <div style="display: flex; gap: 16px; margin-top: 30px; justify-content: flex-end;">
                <a href="products.php" class="btn-admin btn-admin-secondary">Cancel</a>
                <button type="submit" class="btn-admin btn-admin-primary">Save Product Listing</button>
            </div>
        </form>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
