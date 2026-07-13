<?php
// search.php - Product Search, Categories & Filters
require_once __DIR__ . '/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db = getDBConnection();

// Fetch site configuration dynamically
$settings = [];
try {
    $stmt = $db->query("SELECT key_name, val_value FROM settings");
    while ($row = $stmt->fetch()) {
        $settings[$row['key_name']] = $row['val_value'];
    }
} catch (Exception $e) {}
$currency_symbol = $settings['currency'] ?? '$';

// Read filter variables
$searchQuery = trim($_GET['q'] ?? '');
$catFilter = isset($_GET['category']) ? intval($_GET['category']) : 0;
$priceMin = isset($_GET['price_min']) && $_GET['price_min'] !== '' ? floatval($_GET['price_min']) : '';
$priceMax = isset($_GET['price_max']) && $_GET['price_max'] !== '' ? floatval($_GET['price_max']) : '';
$ratingFilter = isset($_GET['rating']) ? intval($_GET['rating']) : 0;

// Fetch categories for sidebar filter
$allCats = [];
try {
    $allCats = $db->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
} catch (Exception $e) {}

// Build SQL dynamic search query
$sql = "
    SELECT p.*, c.name AS category_name, v.name AS vendor_name, 
           (SELECT COALESCE(AVG(rating), 0) FROM reviews WHERE product_id = p.id) as avg_rating,
           (SELECT COUNT(id) FROM reviews WHERE product_id = p.id) as review_count
    FROM products p
    JOIN categories c ON p.category_id = c.id
    JOIN vendors v ON p.vendor_id = v.id
    WHERE 1=1
";
$params = [];

if ($searchQuery !== '') {
    $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
}

if ($catFilter > 0) {
    $sql .= " AND p.category_id = ?";
    $params[] = $catFilter;
}

if ($priceMin !== '') {
    $sql .= " AND p.price >= ?";
    $params[] = $priceMin;
}

if ($priceMax !== '') {
    $sql .= " AND p.price <= ?";
    $params[] = $priceMax;
}

// Subquery filter for average ratings
if ($ratingFilter > 0) {
    $sql .= " AND (SELECT COALESCE(AVG(rating), 0) FROM reviews WHERE product_id = p.id) >= ?";
    $params[] = $ratingFilter;
}

$sql .= " ORDER BY p.id DESC";

// Execute search
try {
    $stmtSearch = $db->prepare($sql);
    $stmtSearch->execute($params);
    $results = $stmtSearch->fetchAll();
} catch (Exception $e) {
    $results = [];
}

require_once __DIR__ . '/includes/header.php';
?>

<div style="display: grid; grid-template-columns: 1fr 3fr; gap: 32px; margin-top: 20px;">
    <!-- Filters Sidebar -->
    <aside class="glass-panel" style="padding: 24px; border-radius: 16px; height: fit-content;">
        <h3 style="margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px;">Filters</h3>
        
        <form action="search.php" method="GET" style="display: flex; flex-direction: column; gap: 20px;">
            <!-- Carry over keyword -->
            <input type="hidden" name="q" value="<?php echo htmlspecialchars($searchQuery); ?>">

            <!-- Category selection -->
            <div>
                <label style="font-weight: 600; font-size: 0.9rem; display: block; margin-bottom: 8px;">Category</label>
                <select name="category" class="form-control" style="padding: 8px;">
                    <option value="0">All Categories</option>
                    <?php foreach ($allCats as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo $catFilter === intval($cat['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Price Range -->
            <div>
                <label style="font-weight: 600; font-size: 0.9rem; display: block; margin-bottom: 8px;">Price Range (<?php echo $currency_symbol; ?>)</label>
                <div style="display: flex; gap: 8px; align-items: center;">
                    <input type="number" name="price_min" class="form-control" placeholder="Min" value="<?php echo htmlspecialchars($priceMin); ?>" style="padding: 8px; font-size: 0.85rem;">
                    <span style="color: var(--text-muted);">to</span>
                    <input type="number" name="price_max" class="form-control" placeholder="Max" value="<?php echo htmlspecialchars($priceMax); ?>" style="padding: 8px; font-size: 0.85rem;">
                </div>
            </div>

            <!-- Rating Stars -->
            <div>
                <label style="font-weight: 600; font-size: 0.9rem; display: block; margin-bottom: 8px;">Average Rating</label>
                <div style="display: flex; flex-direction: column; gap: 8px;">
                    <label style="display: flex; align-items: center; gap: 8px; font-size: 0.9rem; cursor: pointer;">
                        <input type="radio" name="rating" value="0" <?php echo $ratingFilter === 0 ? 'checked' : ''; ?>> All Ratings
                    </label>
                    <label style="display: flex; align-items: center; gap: 8px; font-size: 0.9rem; cursor: pointer;">
                        <input type="radio" name="rating" value="4" <?php echo $ratingFilter === 4 ? 'checked' : ''; ?>> ⭐⭐⭐⭐+ & up
                    </label>
                    <label style="display: flex; align-items: center; gap: 8px; font-size: 0.9rem; cursor: pointer;">
                        <input type="radio" name="rating" value="3" <?php echo $ratingFilter === 3 ? 'checked' : ''; ?>> ⭐⭐⭐+ & up
                    </label>
                    <label style="display: flex; align-items: center; gap: 8px; font-size: 0.9rem; cursor: pointer;">
                        <input type="radio" name="rating" value="2" <?php echo $ratingFilter === 2 ? 'checked' : ''; ?>> ⭐⭐+ & up
                    </label>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; border-radius: 8px; padding: 10px;">
                Apply Filters
            </button>
            
            <a href="search.php?q=<?php echo urlencode($searchQuery); ?>" class="btn btn-secondary" style="width: 100%; border-radius: 8px; padding: 10px; font-size:0.9rem; text-align: center;">
                Reset Filters
            </a>
        </form>
    </aside>

    <!-- Search Results -->
    <div>
        <div style="margin-bottom: 24px;">
            <h2 style="font-size: 2rem;">
                <?php if ($searchQuery !== ''): ?>
                    Search Results for "<?php echo htmlspecialchars($searchQuery); ?>"
                <?php else: ?>
                    All Products
                <?php endif; ?>
            </h2>
            <p style="color: var(--text-muted); font-size: 0.95rem; margin-top: 4px;">
                Found <?php echo count($results); ?> items matching your specifications.
            </p>
        </div>

        <?php if (empty($results)): ?>
            <div class="glass-panel" style="padding: 60px; text-align: center; color: var(--text-muted); border-radius: 16px;">
                <i class="fa-solid fa-folder-open" style="font-size: 4rem; margin-bottom: 20px; color: var(--text-muted);"></i>
                <h3>No products match your criteria</h3>
                <p style="margin-top: 10px;">Try adjusting your keyword search or filter constraints.</p>
            </div>
        <?php else: ?>
            <div class="grid-cols-3">
                <?php foreach ($results as $prod): ?>
                    <?php 
                        $prodImg = getProductImage($prod['image'], $prod['name']);
                        $avgRating = round($prod['avg_rating'], 1);
                        $reviewCount = $prod['review_count'];
                        
                        $inWishlist = false;
                        if (isset($_SESSION['wishlist']) && in_array($prod['id'], $_SESSION['wishlist'])) {
                            $inWishlist = true;
                        }
                    ?>
                    <div class="product-card">
                        <div class="product-image">
                            <img src="<?php echo $prodImg; ?>" alt="<?php echo htmlspecialchars($prod['name']); ?>">
                            <span class="product-badge badge badge-primary"><?php echo htmlspecialchars($prod['category_name']); ?></span>
                            
                            <div class="wishlist-btn" onclick="toggleWishlist(<?php echo $prod['id']; ?>, this)" style="<?php echo $inWishlist ? 'color: var(--danger);' : ''; ?>">
                                <i class="fa-solid fa-heart"></i>
                            </div>
                        </div>
                        
                        <div class="product-details">
                            <span class="product-vendor"><i class="fa-solid fa-store" style="font-size: 0.75rem;"></i> <?php echo htmlspecialchars($prod['vendor_name']); ?></span>
                            <h3 class="product-title">
                                <a href="product.php?id=<?php echo $prod['id']; ?>"><?php echo htmlspecialchars($prod['name']); ?></a>
                            </h3>
                            
                            <div class="rating-stars">
                                <?php 
                                    $fullStars = floor($avgRating);
                                    $hasHalf = ($avgRating - $fullStars) >= 0.5;
                                    for ($i = 1; $i <= 5; $i++) {
                                        if ($i <= $fullStars) {
                                            echo '<i class="fa-solid fa-star"></i>';
                                        } elseif ($i == $fullStars + 1 && $hasHalf) {
                                            echo '<i class="fa-solid fa-star-half-stroke"></i>';
                                        } else {
                                            echo '<i class="fa-regular fa-star"></i>';
                                        }
                                    }
                                ?>
                                <span style="color: var(--text-muted); font-size: 0.8rem; margin-left: 4px;">(<?php echo $reviewCount; ?>)</span>
                            </div>
                            
                            <div class="product-price-row">
                                <div class="product-price"><?php echo $currency_symbol . number_format($prod['price'], 2); ?></div>
                                <button onclick="addToCart(<?php echo $prod['id']; ?>, 1)" class="btn btn-primary btn-sm" style="border-radius: 8px;">
                                    <i class="fa-solid fa-cart-plus"></i> Add
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
