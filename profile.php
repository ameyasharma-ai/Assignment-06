<?php
// profile.php - User Profile, Orders List, Wishlist & Recently Viewed
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

// ----------------------------------------------------
// WISHLIST API TOGGLE HANDLER (AJAX CALLS)
// ----------------------------------------------------
if (isset($_GET['wishlist_api'])) {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Please log in to add items to your wishlist.']);
        exit;
    }

    $productId = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    if ($productId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid product.']);
        exit;
    }

    if (!isset($_SESSION['wishlist'])) {
        $_SESSION['wishlist'] = [];
    }

    $added = false;
    if (($key = array_search($productId, $_SESSION['wishlist'])) !== false) {
        unset($_SESSION['wishlist'][$key]);
        $_SESSION['wishlist'] = array_values($_SESSION['wishlist']); // re-index
        $msg = 'Product removed from your wishlist.';
    } else {
        $_SESSION['wishlist'][] = $productId;
        $added = true;
        $msg = 'Product added to your wishlist!';
    }

    echo json_encode(['success' => true, 'added' => $added, 'message' => $msg]);
    exit;
}

// Enforce login for profile page view
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];

// Fetch user profile info
$stmtUser = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmtUser->execute([$userId]);
$userProfile = $stmtUser->fetch();

// Fetch order history
$stmtOrders = $db->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC");
$stmtOrders->execute([$userId]);
$orders = $stmtOrders->fetchAll();

// Fetch details for each order
$orderItems = [];
foreach ($orders as $order) {
    $stmtItems = $db->prepare("
        SELECT oi.*, p.name AS product_name, p.image AS product_image
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
    ");
    $stmtItems->execute([$order['id']]);
    $orderItems[$order['id']] = $stmtItems->fetchAll();
}

// Fetch wishlist items from DB using session array
$wishlistProducts = [];
if (!empty($_SESSION['wishlist'])) {
    $placeholders = implode(',', array_fill(0, count($_SESSION['wishlist']), '?'));
    $stmtWish = $db->prepare("
        SELECT p.*, c.name AS category_name, v.name AS vendor_name
        FROM products p
        JOIN categories c ON p.category_id = c.id
        JOIN vendors v ON p.vendor_id = v.id
        WHERE p.id IN ($placeholders)
    ");
    $stmtWish->execute($_SESSION['wishlist']);
    $wishlistProducts = $stmtWish->fetchAll();
}

// Fetch recently viewed items from Cookie
$recentProducts = [];
$recentCookie = isset($_COOKIE['recently_viewed']) ? json_decode($_COOKIE['recently_viewed'], true) : [];
if (!empty($recentCookie) && is_array($recentCookie)) {
    // Filter out active product if viewed, keep others
    $placeholders = implode(',', array_fill(0, count($recentCookie), '?'));
    try {
        $stmtRecent = $db->prepare("
            SELECT p.*, c.name AS category_name, v.name AS vendor_name
            FROM products p
            JOIN categories c ON p.category_id = c.id
            JOIN vendors v ON p.vendor_id = v.id
            WHERE p.id IN ($placeholders)
            ORDER BY CASE p.id " . implode(' ', array_map(function($id, $index) { return "WHEN $id THEN $index"; }, $recentCookie, array_keys($recentCookie))) . " END
        ");
        $stmtRecent->execute($recentCookie);
        $recentProducts = $stmtRecent->fetchAll();
    } catch (Exception $e) {
        // Fallback in case CASE sorting fails due to driver differences
        try {
            $stmtRecent = $db->prepare("
                SELECT p.*, c.name AS category_name, v.name AS vendor_name
                FROM products p
                JOIN categories c ON p.category_id = c.id
                JOIN vendors v ON p.vendor_id = v.id
                WHERE p.id IN ($placeholders)
            ");
            $stmtRecent->execute($recentCookie);
            $recentProducts = $stmtRecent->fetchAll();
        } catch (Exception $ex) {}
    }
}

$activeTab = $_GET['tab'] ?? 'orders'; // 'orders', 'wishlist', 'details', 'recent'

require_once __DIR__ . '/includes/header.php';
?>

<div style="display: grid; grid-template-columns: 1fr 3fr; gap: 32px; margin-top: 20px;">
    <!-- Profile Left Navigation -->
    <aside class="glass-panel" style="padding: 24px; border-radius: 16px; height: fit-content;">
        <div style="text-align: center; margin-bottom: 24px; border-bottom: 1px solid var(--border-color); padding-bottom: 20px;">
            <div style="width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, var(--primary), var(--secondary)); display: inline-flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 700; color: #fff; margin-bottom: 12px; font-family: 'Outfit';">
                <?php echo strtoupper(substr($userProfile['name'], 0, 1)); ?>
            </div>
            <h3 style="font-size: 1.15rem;"><?php echo htmlspecialchars($userProfile['name']); ?></h3>
            <span style="color: var(--text-muted); font-size: 0.8rem; text-transform: uppercase;"><?php echo htmlspecialchars($userProfile['role']); ?> Profile</span>
        </div>

        <ul style="list-style: none; display: flex; flex-direction: column; gap: 8px;">
            <li>
                <a href="profile.php?tab=orders" class="btn btn-secondary" style="width: 100%; text-align: left; justify-content: flex-start; <?php echo $activeTab === 'orders' ? 'background: var(--border-color); border-color: var(--primary-light);' : ''; ?>">
                    <i class="fa-solid fa-clock-history" style="width: 20px;"></i> Order History
                </a>
            </li>
            <li>
                <a href="profile.php?tab=wishlist" class="btn btn-secondary" style="width: 100%; text-align: left; justify-content: flex-start; <?php echo $activeTab === 'wishlist' ? 'background: var(--border-color); border-color: var(--primary-light);' : ''; ?>">
                    <i class="fa-regular fa-heart" style="width: 20px;"></i> My Wishlist (<?php echo count($wishlistProducts); ?>)
                </a>
            </li>
            <li>
                <a href="profile.php?tab=recent" class="btn btn-secondary" style="width: 100%; text-align: left; justify-content: flex-start; <?php echo $activeTab === 'recent' ? 'background: var(--border-color); border-color: var(--primary-light);' : ''; ?>">
                    <i class="fa-solid fa-eye" style="width: 20px;"></i> Recently Viewed
                </a>
            </li>
            <li>
                <a href="profile.php?tab=details" class="btn btn-secondary" style="width: 100%; text-align: left; justify-content: flex-start; <?php echo $activeTab === 'details' ? 'background: var(--border-color); border-color: var(--primary-light);' : ''; ?>">
                    <i class="fa-regular fa-id-card" style="width: 20px;"></i> Account Details
                </a>
            </li>
        </ul>
    </aside>

    <!-- Profile Detail Tabs Content -->
    <section class="glass-panel" style="padding: 32px; border-radius: 16px;">
        
        <!-- ORDER HISTORY TAB -->
        <?php if ($activeTab === 'orders'): ?>
            <h3 style="margin-bottom: 24px; border-bottom: 1px solid var(--border-color); padding-bottom: 12px;"><i class="fa-solid fa-receipt"></i> Order History</h3>
            <?php if (empty($orders)): ?>
                <div style="text-align: center; color: var(--text-muted); padding: 40px;">
                    <i class="fa-solid fa-basket-shopping" style="font-size: 3rem; margin-bottom: 16px;"></i>
                    <p>You have not placed any orders yet.</p>
                    <a href="search.php" class="btn btn-primary btn-sm" style="margin-top: 16px;">Browse Products</a>
                </div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 24px;">
                    <?php foreach ($orders as $order): ?>
                        <div class="glass-panel" style="padding: 20px; border-radius: 12px; background: var(--bg-surface-elevated);">
                            <div class="flex-between" style="border-bottom: 1px solid var(--border-color); padding-bottom: 12px; margin-bottom: 12px; font-size: 0.9rem;">
                                <div>
                                    <strong>Order Reference: #<?php echo $order['id']; ?></strong>
                                    <span style="color: var(--text-muted); margin-left: 12px;">Placed on: <?php echo date('M d, Y', strtotime($order['created_at'])); ?></span>
                                </div>
                                <?php 
                                    $statusClass = 'badge-warning';
                                    if ($order['status'] === 'Processing') $statusClass = 'badge-primary';
                                    elseif ($order['status'] === 'Shipped') $statusClass = 'badge-primary';
                                    elseif ($order['status'] === 'Delivered') $statusClass = 'badge-success';
                                ?>
                                <span class="badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($order['status']); ?></span>
                            </div>
                            
                            <!-- Items List -->
                            <div style="display: flex; flex-direction: column; gap: 12px;">
                                <?php foreach ($orderItems[$order['id']] as $item): ?>
                                    <div class="flex-between" style="font-size: 0.95rem;">
                                        <div style="display: flex; gap: 12px; align-items: center;">
                                            <img src="<?php echo getProductImage($item['product_image'], $item['product_name']); ?>" alt="" style="width: 40px; height: 40px; object-fit: cover; border-radius: 6px;">
                                            <div>
                                                <a href="product.php?id=<?php echo $item['product_id']; ?>" style="font-weight: 500;"><?php echo htmlspecialchars($item['product_name']); ?></a>
                                                <?php if (!empty($item['variation_details'])): ?>
                                                    <span style="color: var(--primary-light); font-size: 0.75rem; display: block;">
                                                        <?php echo htmlspecialchars($item['variation_details']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div style="color: var(--text-muted);">
                                            <?php echo $item['quantity']; ?> x <?php echo $currency_symbol . number_format($item['price'], 2); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="flex-between" style="border-top: 1px solid var(--border-color); padding-top: 12px; margin-top: 12px; font-weight: 700;">
                                <span>Grand Total:</span>
                                <span style="color: var(--primary-light); font-family: 'Outfit';"><?php echo $currency_symbol . number_format($order['total_amount'], 2); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <!-- WISHLIST TAB -->
        <?php elseif ($activeTab === 'wishlist'): ?>
            <h3 style="margin-bottom: 24px; border-bottom: 1px solid var(--border-color); padding-bottom: 12px;"><i class="fa-regular fa-heart"></i> My Wishlist</h3>
            <?php if (empty($wishlistProducts)): ?>
                <p style="color: var(--text-muted); text-align: center; padding: 40px;">Your wishlist is currently empty.</p>
            <?php else: ?>
                <div class="grid-cols-3">
                    <?php foreach ($wishlistProducts as $prod): ?>
                        <?php $prodImg = getProductImage($prod['image'], $prod['name']); ?>
                        <div class="product-card">
                            <div class="product-image">
                                <img src="<?php echo $prodImg; ?>" alt="">
                                <span class="product-badge badge badge-primary"><?php echo htmlspecialchars($prod['category_name']); ?></span>
                                <div class="wishlist-btn active" onclick="toggleWishlist(<?php echo $prod['id']; ?>, this); setTimeout(() => location.reload(), 300);" style="color: var(--danger);">
                                    <i class="fa-solid fa-heart"></i>
                                </div>
                            </div>
                            <div class="product-details">
                                <span class="product-vendor"><?php echo htmlspecialchars($prod['vendor_name']); ?></span>
                                <h3 class="product-title"><a href="product.php?id=<?php echo $prod['id']; ?>"><?php echo htmlspecialchars($prod['name']); ?></a></h3>
                                <div class="product-price-row">
                                    <div class="product-price"><?php echo $currency_symbol . number_format($prod['price'], 2); ?></div>
                                    <button onclick="addToCart(<?php echo $prod['id']; ?>, 1)" class="btn btn-primary btn-sm" style="border-radius: 8px;">
                                        Add
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <!-- RECENTLY VIEWED TAB -->
        <?php elseif ($activeTab === 'recent'): ?>
            <h3 style="margin-bottom: 24px; border-bottom: 1px solid var(--border-color); padding-bottom: 12px;"><i class="fa-solid fa-eye"></i> Recently Viewed</h3>
            <?php if (empty($recentProducts)): ?>
                <p style="color: var(--text-muted); text-align: center; padding: 40px;">No recently viewed products recorded in this session.</p>
            <?php else: ?>
                <div class="grid-cols-3">
                    <?php foreach ($recentProducts as $prod): ?>
                        <?php $prodImg = getProductImage($prod['image'], $prod['name']); ?>
                        <div class="product-card">
                            <div class="product-image">
                                <img src="<?php echo $prodImg; ?>" alt="">
                                <span class="product-badge badge badge-primary"><?php echo htmlspecialchars($prod['category_name']); ?></span>
                            </div>
                            <div class="product-details">
                                <span class="product-vendor"><?php echo htmlspecialchars($prod['vendor_name']); ?></span>
                                <h3 class="product-title"><a href="product.php?id=<?php echo $prod['id']; ?>"><?php echo htmlspecialchars($prod['name']); ?></a></h3>
                                <div class="product-price-row">
                                    <div class="product-price"><?php echo $currency_symbol . number_format($prod['price'], 2); ?></div>
                                    <a href="product.php?id=<?php echo $prod['id']; ?>" class="btn btn-secondary btn-sm" style="border-radius: 8px;">View</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <!-- DETAILS TAB -->
        <?php elseif ($activeTab === 'details'): ?>
            <h3 style="margin-bottom: 24px; border-bottom: 1px solid var(--border-color); padding-bottom: 12px;"><i class="fa-regular fa-user"></i> Account Details</h3>
            <div style="display: flex; flex-direction: column; gap: 20px; font-size: 1.05rem;">
                <div>
                    <span style="color: var(--text-muted); font-size: 0.85rem; display: block; text-transform: uppercase;">Full Name</span>
                    <strong><?php echo htmlspecialchars($userProfile['name']); ?></strong>
                </div>
                <div>
                    <span style="color: var(--text-muted); font-size: 0.85rem; display: block; text-transform: uppercase;">Email Address</span>
                    <strong><?php echo htmlspecialchars($userProfile['email']); ?></strong>
                </div>
                <div>
                    <span style="color: var(--text-muted); font-size: 0.85rem; display: block; text-transform: uppercase;">Account Role</span>
                    <strong><?php echo ucfirst(htmlspecialchars($userProfile['role'])); ?></strong>
                </div>
                <div>
                    <span style="color: var(--text-muted); font-size: 0.85rem; display: block; text-transform: uppercase;">Member Since</span>
                    <strong><?php echo date('M d, Y', strtotime($userProfile['created_at'])); ?></strong>
                </div>
            </div>
        <?php endif; ?>

    </section>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
