<?php
// admin/products.php - Platform Catalog CRUD & CSV Bulk Operations
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
// DYNAMIC CSV EXPORT (TRIP-TRIGGER)
// ----------------------------------------------------
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // Clear buffer to prevent syntax noise
    ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="omnimart_products_catalog.csv"');
    
    $output = fopen('php://output', 'w');
    
    // CSV Header row
    fputcsv($output, ['ID', 'VendorID', 'ProductName', 'Description', 'Price', 'Stock', 'Image', 'CategoryID', 'CreatedAt']);
    
    try {
        $stmt = $db->query("SELECT * FROM products ORDER BY id ASC");
        while ($row = $stmt->fetch()) {
            fputcsv($output, [
                $row['id'],
                $row['vendor_id'],
                $row['name'],
                $row['description'],
                $row['price'],
                $row['stock'],
                $row['image'],
                $row['category_id'],
                $row['created_at']
            ]);
        }
    } catch (Exception $e) {}
    
    fclose($output);
    exit;
}

// ----------------------------------------------------
// DYNAMIC CSV IMPORT
// ----------------------------------------------------
$importError = '';
$importSuccess = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_csv'])) {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['csv_file']['tmp_name'];
        if (($handle = fopen($fileTmpPath, "r")) !== FALSE) {
            // Read header
            $header = fgetcsv($handle, 1000, ",");
            
            $rowCount = 0;
            try {
                $db->beginTransaction();
                $stmtInsert = $db->prepare("
                    INSERT INTO products (vendor_id, name, description, price, stock, image, category_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    // Map index matching: [ID, VendorID, ProductName, Description, Price, Stock, Image, CategoryID, CreatedAt]
                    // If CSV row has valid content
                    if (count($data) >= 8) {
                        $vendorId = intval($data[1]);
                        $name = trim($data[2]);
                        $description = trim($data[3]);
                        $price = floatval($data[4]);
                        $stock = intval($data[5]);
                        $image = trim($data[6]);
                        $categoryId = intval($data[7]);
                        
                        if (!empty($name) && $price > 0 && $vendorId > 0 && $categoryId > 0) {
                            $stmtInsert->execute([$vendorId, $name, $description, $price, $stock, $image, $categoryId]);
                            $rowCount++;
                        }
                    }
                }
                
                $db->commit();
                $importSuccess = "Bulk Import Complete: Successfully uploaded $rowCount new product listings.";
            } catch (Exception $e) {
                $db->rollBack();
                $importError = "Failed to import CSV row data: " . $e->getMessage();
            }
            fclose($handle);
        } else {
            $importError = "Could not parse CSV file. Ensure it is a valid comma-separated layout.";
        }
    } else {
        $importError = "Please select a valid CSV catalog file to import.";
    }
}

// ----------------------------------------------------
// CRUD DELETE
// ----------------------------------------------------
$action = $_GET['action'] ?? 'list';
$error = '';
$success = '';

if (isset($_GET['success'])) {
    $success = $_GET['success'];
}

if ($action === 'delete') {
    $prodId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    try {
        $stmtDel = $db->prepare("DELETE FROM products WHERE id = ?");
        $stmtDel->execute([$prodId]);
        header("Location: products.php?success=Product+deleted+successfully");
        exit;
    } catch (Exception $e) {
        $error = 'Failed to delete product: ' . $e->getMessage();
    }
}

// ----------------------------------------------------
// CRUD ADD / EDIT POST HANDLER
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['import_csv'])) {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $stock = intval($_POST['stock'] ?? 0);
    $categoryId = intval($_POST['category_id'] ?? 0);
    $vendorId = intval($_POST['vendor_id'] ?? 0);
    
    $colorVarRaw = trim($_POST['variations_color'] ?? '');
    $sizeVarRaw = trim($_POST['variations_size'] ?? '');

    if (empty($name) || $price <= 0 || $categoryId <= 0 || $vendorId <= 0) {
        $error = 'Product name, price, category, and vendor assignment are required.';
    } else {
        $imagePath = $_POST['existing_image'] ?? '';
        if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['product_image']['tmp_name'];
            $fileName = $_FILES['product_image']['name'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $destPath = __DIR__ . '/../assets/images/' . md5(time() . $fileName) . '.' . $fileExtension;
            
            if (move_uploaded_file($fileTmpPath, $destPath)) {
                $imagePath = 'assets/images/' . basename($destPath);
            }
        }

        if (empty($error)) {
            try {
                $db->beginTransaction();

                if ($action === 'add') {
                    $stmt = $db->prepare("
                        INSERT INTO products (vendor_id, name, description, price, stock, image, category_id) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$vendorId, $name, $description, $price, $stock, $imagePath, $categoryId]);
                    $productId = $db->lastInsertId();
                    $success = 'Product added successfully!';
                } else {
                    $productId = intval($_GET['id'] ?? 0);
                    $stmt = $db->prepare("
                        UPDATE products 
                        SET name = ?, description = ?, price = ?, stock = ?, image = ?, category_id = ?, vendor_id = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$name, $description, $price, $stock, $imagePath, $categoryId, $vendorId, $productId]);
                    
                    // Reset variations
                    $stmtClearVar = $db->prepare("DELETE FROM product_variations WHERE product_id = ?");
                    $stmtClearVar->execute([$productId]);
                    $success = 'Product updated successfully!';
                }

                // Add Color Variations
                if (!empty($colorVarRaw)) {
                    $stmtVar = $db->prepare("INSERT INTO product_variations (product_id, attribute, value, stock) VALUES (?, 'Color', ?, ?)");
                    foreach (explode(',', $colorVarRaw) as $p) {
                        $parts = explode(':', $p);
                        if (count($parts) === 2) $stmtVar->execute([$productId, trim($parts[0]), intval($parts[1])]);
                    }
                }

                // Add Size Variations
                if (!empty($sizeVarRaw)) {
                    $stmtVar = $db->prepare("INSERT INTO product_variations (product_id, attribute, value, stock) VALUES (?, 'Size', ?, ?)");
                    foreach (explode(',', $sizeVarRaw) as $p) {
                        $parts = explode(':', $p);
                        if (count($parts) === 2) $stmtVar->execute([$productId, trim($parts[0]), intval($parts[1])]);
                    }
                }

                $db->commit();
                header("Location: products.php?success=" . urlencode($success));
                exit;

            } catch (Exception $e) {
                $db->rollBack();
                $error = 'Failed to save product details: ' . $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/../includes/admin_header.php';
?>

<!-- CSV IMPORT / EXPORT METRICS CARD -->
<?php if ($action === 'list'): ?>
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-bottom: 24px;">
        <!-- CSV Import Card -->
        <div class="admin-card" style="margin-bottom:0;">
            <h3>Bulk Product Import via CSV</h3>
            <p style="color: var(--text-muted); font-size: 0.8rem; margin-bottom: 12px;">Select and upload a CSV spreadsheet containing catalog values.</p>
            
            <?php if (!empty($importError)): ?>
                <div style="background: rgba(239, 68, 68, 0.15); color: var(--danger); padding: 8px 12px; border-radius: 6px; font-size: 0.8rem; margin-bottom: 10px;">
                    <?php echo htmlspecialchars($importError); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($importSuccess)): ?>
                <div style="background: rgba(16, 185, 129, 0.15); color: var(--success); padding: 8px 12px; border-radius: 6px; font-size: 0.8rem; margin-bottom: 10px;">
                    <?php echo htmlspecialchars($importSuccess); ?>
                </div>
            <?php endif; ?>

            <form action="products.php" method="POST" enctype="multipart/form-data" style="display: flex; gap: 12px; align-items: center;">
                <input type="hidden" name="import_csv" value="1">
                <input type="file" name="csv_file" class="admin-form-control" required style="padding: 6px; font-size: 0.85rem;" accept=".csv">
                <button type="submit" class="btn-admin btn-admin-primary" style="white-space: nowrap; height: 38px;"><i class="fa-solid fa-file-import"></i> Upload CSV</button>
            </form>
        </div>

        <!-- CSV Export Card -->
        <div class="admin-card" style="margin-bottom:0; display: flex; flex-direction: column; justify-content: space-between;">
            <div>
                <h3>Catalog CSV Export</h3>
                <p style="color: var(--text-muted); font-size: 0.8rem;">Download the entire e-commerce products spreadsheet instantly.</p>
            </div>
            <a href="products.php?export=csv" class="btn-admin btn-admin-secondary" style="text-align: center; justify-content: center; height: 38px; margin-top: 10px;">
                <i class="fa-solid fa-file-export"></i> Download CSV Database
            </a>
        </div>
    </div>
<?php endif; ?>

<!-- CRUD MESSAGES -->
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

<!-- CRUD LIST MODULE -->
<?php if ($action === 'list'): 
    $stmt = $db->query("
        SELECT p.*, c.name AS category_name, v.name AS vendor_name 
        FROM products p
        JOIN categories c ON p.category_id = c.id
        JOIN vendors v ON p.vendor_id = v.id
        ORDER BY p.id DESC
    ");
    $allProducts = $stmt->fetchAll();
?>
    <div class="data-table-container">
        <div class="data-table-header">
            <h3>Global Product Inventory</h3>
            <a href="products.php?action=add" class="btn-admin btn-admin-primary"><i class="fa-solid fa-plus"></i> Add Product Listing</a>
        </div>
        
        <table class="data-table">
            <thead>
                <tr>
                    <th>Img</th>
                    <th>Name</th>
                    <th>Merchant</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($allProducts)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: var(--text-muted);">No products registered yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($allProducts as $prod): 
                        $prodImg = getProductImage($prod['image'], $prod['name']);
                    ?>
                        <tr>
                            <td><img src="../<?php echo htmlspecialchars($prodImg); ?>" alt="" style="width:40px; height:40px; object-fit:cover; border-radius:4px;"></td>
                            <td><strong><?php echo htmlspecialchars($prod['name']); ?></strong></td>
                            <td><span style="color: var(--secondary); font-weight:500;"><?php echo htmlspecialchars($prod['vendor_name']); ?></span></td>
                            <td style="color: var(--text-muted);"><?php echo htmlspecialchars($prod['category_name']); ?></td>
                            <td style="font-family:'Outfit'; font-weight:600;"><?php echo $currency_symbol . number_format($prod['price'], 2); ?></td>
                            <td><?php echo $prod['stock']; ?> u</td>
                            <td>
                                <div style="display: flex; gap: 8px;">
                                    <a href="products.php?action=edit&id=<?php echo $prod['id']; ?>" class="btn-admin btn-admin-secondary" style="font-size:0.75rem; padding: 4px 8px;"><i class="fa-solid fa-pen"></i></a>
                                    <a href="products.php?action=delete&id=<?php echo $prod['id']; ?>" class="btn-admin btn-admin-secondary" style="font-size:0.75rem; padding: 4px 8px; color: var(--danger); border-color: var(--danger);" onclick="return confirm('Delete this product permanently?');"><i class="fa-solid fa-trash"></i></a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

<!-- CRUD ADD / EDIT FORM MODULE -->
<?php elseif ($action === 'add' || $action === 'edit'): 
    $title = 'Add New Global Product';
    $nameVal = '';
    $descVal = '';
    $priceVal = '';
    $stockVal = 0;
    $catVal = 0;
    $vendorVal = 0;
    $imageVal = '';
    $colorValStr = '';
    $sizeValStr = '';

    if ($action === 'edit') {
        $productId = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $stmtEdit = $db->prepare("SELECT * FROM products WHERE id = ?");
        $stmtEdit->execute([$productId]);
        $prodEdit = $stmtEdit->fetch();

        if ($prodEdit) {
            $title = 'Modify Global Product #' . $prodEdit['id'];
            $nameVal = $prodEdit['name'];
            $descVal = $prodEdit['description'];
            $priceVal = $prodEdit['price'];
            $stockVal = $prodEdit['stock'];
            $catVal = $prodEdit['category_id'];
            $vendorVal = $prodEdit['vendor_id'];
            $imageVal = $prodEdit['image'];

            // Variations
            $stmtVar = $db->prepare("SELECT * FROM product_variations WHERE product_id = ?");
            $stmtVar->execute([$productId]);
            $cv = []; $sv = [];
            while ($row = $stmtVar->fetch()) {
                if ($row['attribute'] === 'Color') $cv[] = $row['value'] . ':' . $row['stock'];
                elseif ($row['attribute'] === 'Size') $sv[] = $row['value'] . ':' . $row['stock'];
            }
            $colorValStr = implode(',', $cv);
            $sizeValStr = implode(',', $sv);
        }
    }

    // Fetch active vendors
    $allVendors = [];
    try {
        $allVendors = $db->query("SELECT id, name FROM vendors WHERE status = 'Active'")->fetchAll();
    } catch (Exception $e) {}
    
    // Fetch categories
    $allCats = [];
    try {
        $allCats = $db->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();
    } catch (Exception $e) {}
?>
    <div class="admin-card" style="max-width: 800px; margin: 0 auto;">
        <h3><?php echo $title; ?></h3>
        <form action="products.php?action=<?php echo $action; ?><?php echo $action==='edit' ? '&id='.$productId : ''; ?>" method="POST" enctype="multipart/form-data" style="margin-top:20px;">
            <input type="hidden" name="existing_image" value="<?php echo htmlspecialchars($imageVal); ?>">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="admin-form-group">
                    <label for="name">Product Name</label>
                    <input type="text" name="name" id="name" class="admin-form-control" required value="<?php echo htmlspecialchars($nameVal); ?>">
                </div>
                <div class="admin-form-group">
                    <label for="vendor_id">Assign Merchant / Vendor</label>
                    <select name="vendor_id" id="vendor_id" class="admin-form-control" required>
                        <option value="">Select Vendor</option>
                        <?php foreach ($allVendors as $v): ?>
                            <option value="<?php echo $v['id']; ?>" <?php echo $vendorVal === intval($v['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($v['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="admin-form-group">
                    <label for="category_id">Category</label>
                    <select name="category_id" id="category_id" class="admin-form-control" required>
                        <option value="">Select Category</option>
                        <?php foreach ($allCats as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $catVal === intval($cat['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="admin-form-group">
                    <label for="price">Price (<?php echo $currency_symbol; ?>)</label>
                    <input type="number" step="0.01" name="price" id="price" class="admin-form-control" required value="<?php echo htmlspecialchars($priceVal); ?>">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="admin-form-group">
                    <label for="stock">Cumulative Stock</label>
                    <input type="number" name="stock" id="stock" class="admin-form-control" required value="<?php echo $stockVal; ?>">
                </div>
                <div class="admin-form-group">
                    <label for="product_image">Product Image File</label>
                    <input type="file" name="product_image" id="product_image" class="admin-form-control" style="padding:6px;">
                </div>
            </div>

            <div class="admin-form-group">
                <label for="description">Product Description</label>
                <textarea name="description" id="description" rows="3" class="admin-form-control"><?php echo htmlspecialchars($descVal); ?></textarea>
            </div>

            <!-- Variations -->
            <div class="admin-card" style="background: var(--bg-base); border-color: rgba(255,255,255,0.04);">
                <h4>Attributes & Variation Stock Mapping</h4>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top:12px;">
                    <div class="admin-form-group">
                        <label>Color Options (Format: Red:10,Blue:20)</label>
                        <input type="text" name="variations_color" class="admin-form-control" value="<?php echo htmlspecialchars($colorValStr); ?>">
                    </div>
                    <div class="admin-form-group">
                        <label>Size Options (Format: M:5,L:15)</label>
                        <input type="text" name="variations_size" class="admin-form-control" value="<?php echo htmlspecialchars($sizeValStr); ?>">
                    </div>
                </div>
            </div>

            <div style="display:flex; justify-content: flex-end; gap: 12px; margin-top:20px;">
                <a href="products.php" class="btn-admin btn-admin-secondary">Cancel</a>
                <button type="submit" class="btn-admin btn-admin-primary">Save Product</button>
            </div>
        </form>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
