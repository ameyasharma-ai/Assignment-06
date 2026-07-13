<?php
// index.php - E-Commerce Homepage
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/header.php';

// Fetch products from database
$products = [];
try {
    $stmt = $db->query("
        SELECT p.*, c.name AS category_name, v.name AS vendor_name, 
               (SELECT IFNULL(AVG(rating), 0) FROM reviews WHERE product_id = p.id) as avg_rating,
               (SELECT COUNT(id) FROM reviews WHERE product_id = p.id) as review_count
        FROM products p
        JOIN categories c ON p.category_id = c.id
        JOIN vendors v ON p.vendor_id = v.id
        ORDER BY p.id DESC LIMIT 8
    ");
    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    // If running SQLite and IFNULL is different, fallback to standard SQLite AVG handling
    try {
        $stmt = $db->query("
            SELECT p.*, c.name AS category_name, v.name AS vendor_name, 
                   (SELECT COALESCE(AVG(rating), 0) FROM reviews WHERE product_id = p.id) as avg_rating,
                   (SELECT COUNT(id) FROM reviews WHERE product_id = p.id) as review_count
            FROM products p
            JOIN categories c ON p.category_id = c.id
            JOIN vendors v ON p.vendor_id = v.id
            ORDER BY p.id DESC LIMIT 8
        ");
        $products = $stmt->fetchAll();
    } catch (Exception $ex) {
        $products = [];
    }
}

// Fetch categories
$homeCategories = [];
try {
    $homeCategories = $db->query("SELECT * FROM categories LIMIT 6")->fetchAll();
} catch (Exception $e) {
    $homeCategories = [];
}

// Map icons to categories
$categoryIcons = [
    'Electronics' => 'fa-laptop-code',
    'Fashion' => 'fa-shirt',
    'Home & Living' => 'fa-couch',
    'Books' => 'fa-book-open',
    'Sports' => 'fa-volleyball'
];

// Fetch banner settings from database settings table
$bannerTitle = $settings['banner_title'] ?? 'Elevate Your Digital Lifestyle';
$bannerSubtitle = $settings['banner_subtitle'] ?? 'Discover premium, curated gear from verified merchants.';
$bannerImage = $settings['banner_image'] ?? 'assets/images/banner.jpg';
$bannerBg = getProductImage($bannerImage, 'banner-key');
?>

<!-- Hero Banner Section -->
<section class="hero glass-panel" style="background: linear-gradient(135deg, rgba(11, 15, 25, 0.9), rgba(21, 28, 44, 0.8)), url('<?php echo $bannerBg; ?>') no-repeat center/cover;">
    <div class="container hero-content">
        <span class="badge badge-primary" style="margin-bottom: 16px; padding: 6px 12px;">★ Elite Marketplace</span>
        <h1 style="margin-bottom: 16px; font-weight: 800; font-size: 3rem;"><?php echo htmlspecialchars($bannerTitle); ?></h1>
        <p style="margin-bottom: 30px; font-size: 1.1rem; line-height: 1.5; opacity: 0.9;"><?php echo htmlspecialchars($bannerSubtitle); ?></p>
        <div style="display: flex; gap: 16px;">
            <a href="search.php" class="btn btn-primary">Browse Shop</a>
            <a href="signup.php?role=vendor" class="btn btn-secondary">Become a Seller</a>
        </div>
    </div>
</section>

<!-- Categories Section -->
<section style="margin-top: 60px;">
    <div class="flex-between" style="margin-bottom: 24px;">
        <h2>Explore Categories</h2>
        <a href="search.php" style="color: var(--primary-light); font-size: 0.95rem; font-weight: 600;">View All <i class="fa-solid fa-arrow-right"></i></a>
    </div>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 20px;">
        <?php foreach ($homeCategories as $cat): ?>
            <?php 
                $icon = $categoryIcons[$cat['name']] ?? 'fa-tags';
            ?>
            <a href="search.php?category=<?php echo $cat['id']; ?>" class="category-card">
                <div class="category-icon"><i class="fa-solid <?php echo $icon; ?>"></i></div>
                <h3 style="font-size: 1.05rem; font-weight: 600;"><?php echo htmlspecialchars($cat['name']); ?></h3>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- Featured Products Section -->
<section class="product-grid" style="margin-top: 60px;">
    <div class="flex-between" style="margin-bottom: 24px;">
        <h2>Featured Products</h2>
        <span style="color: var(--text-muted); font-size: 0.9rem;">Fresh arrivals from top sellers</span>
    </div>
    
    <?php if (empty($products)): ?>
        <div class="glass-panel" style="padding: 40px; text-align: center; color: var(--text-muted);">
            <i class="fa-solid fa-box-open" style="font-size: 3rem; margin-bottom: 16px;"></i>
            <p>No products found. Start by seeding the database or logging in as vendor to publish!</p>
        </div>
    <?php else: ?>
        <div class="grid-cols-4">
            <?php foreach ($products as $prod): ?>
                <?php 
                    $prodImg = getProductImage($prod['image'], $prod['name']);
                    $avgRating = round($prod['avg_rating'], 1);
                    $reviewCount = $prod['review_count'];
                    
                    // Wishlist check
                    $inWishlist = false;
                    if (isset($_SESSION['wishlist']) && in_array($prod['id'], $_SESSION['wishlist'])) {
                        $inWishlist = true;
                    }
                ?>
                <div class="product-card">
                    <div class="product-image">
                        <img src="<?php echo $prodImg; ?>" alt="<?php echo htmlspecialchars($prod['name']); ?>">
                        <span class="product-badge badge badge-primary"><?php echo htmlspecialchars($prod['category_name']); ?></span>
                        
                        <!-- Wishlist AJAX button -->
                        <div class="wishlist-btn" onclick="toggleWishlist(<?php echo $prod['id']; ?>, this)" style="<?php echo $inWishlist ? 'color: var(--danger);' : ''; ?>">
                            <i class="fa-solid fa-heart"></i>
                        </div>
                    </div>
                    
                    <div class="product-details">
                        <span class="product-vendor"><i class="fa-solid fa-store" style="font-size: 0.75rem;"></i> <?php echo htmlspecialchars($prod['vendor_name']); ?></span>
                        <h3 class="product-title">
                            <a href="product.php?id=<?php echo $prod['id']; ?>"><?php echo htmlspecialchars($prod['name']); ?></a>
                        </h3>
                        
                        <!-- Ratings -->
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
                            <button onclick="addToCart(<?php echo $prod['id']; ?>, 1)" class="btn btn-primary btn-sm" style="border-radius: 8px; padding: 8px 12px;" title="Add to Cart">
                                <i class="fa-solid fa-cart-plus"></i> Add
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<!-- Promotional Grid Banners -->
<section style="margin-top: 70px;">
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
        <div class="glass-panel" style="padding: 30px; border-radius: 20px; background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(168, 85, 247, 0.05)); border: 1px solid rgba(255,255,255,0.05); display: flex; flex-direction: column; justify-content: space-between;">
            <div>
                <span class="badge badge-warning" style="margin-bottom: 12px;">Limited Offer</span>
                <h3 style="font-size: 1.75rem; margin-bottom: 8px; font-weight: 700;">Subscribe & Get 10% Off</h3>
                <p style="color: var(--text-muted); font-size: 0.95rem; margin-bottom: 24px;">Use coupon code <strong style="color: var(--primary-light);">WELCOME10</strong> at checkout to redeem a discount on electronics or apparel items.</p>
            </div>
            <a href="search.php" class="btn btn-secondary btn-sm" style="align-self: flex-start;">Shop Now</a>
        </div>
        <div class="glass-panel" style="padding: 30px; border-radius: 20px; background: linear-gradient(135deg, rgba(6, 182, 212, 0.1), rgba(59, 130, 246, 0.05)); border: 1px solid rgba(255,255,255,0.05); display: flex; flex-direction: column; justify-content: space-between;">
            <div>
                <span class="badge badge-success" style="margin-bottom: 12px;">Merchant Hub</span>
                <h3 style="font-size: 1.75rem; margin-bottom: 8px; font-weight: 700;">Start Selling on OmniMart</h3>
                <p style="color: var(--text-muted); font-size: 0.95rem; margin-bottom: 24px;">Reach millions of customers. Low commissions, instant dashboard insights, and easy listing uploads.</p>
            </div>
            <a href="signup.php?role=vendor" class="btn btn-secondary btn-sm" style="align-self: flex-start;">Apply Now</a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
