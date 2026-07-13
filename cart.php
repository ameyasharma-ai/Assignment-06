<?php
// cart.php - Interactive Shopping Cart Page & AJAX Handler
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

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// ----------------------------------------------------
// POST REQUEST HANDLER (AJAX & Standard Form Posts)
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // AJAX Add to Cart
    if ($action === 'add') {
        $productId = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
        $variation = trim($_POST['variation'] ?? ''); // e.g. "Color: Black" or "Size: M"

        // Validate product
        $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();

        if (!$product) {
            echo json_encode(['success' => false, 'message' => 'Product not found.']);
            exit;
        }

        if ($product['stock'] < $quantity) {
            echo json_encode(['success' => false, 'message' => 'Not enough stock available.']);
            exit;
        }

        // Create a unique key for cart items based on product + variation details
        $cartKey = $productId . '_' . md5($variation);

        if (isset($_SESSION['cart'][$cartKey])) {
            $_SESSION['cart'][$cartKey]['quantity'] += $quantity;
        } else {
            $_SESSION['cart'][$cartKey] = [
                'id' => $productId,
                'name' => $product['name'],
                'price' => $product['price'],
                'image' => $product['image'],
                'quantity' => $quantity,
                'variation' => $variation
            ];
        }

        // Count total items
        $totalItems = 0;
        foreach ($_SESSION['cart'] as $item) {
            $totalItems += $item['quantity'];
        }

        echo json_encode([
            'success' => true, 
            'cart_count' => $totalItems, 
            'message' => 'Added to cart successfully!'
        ]);
        exit;
    }

    // AJAX / Post Update Quantity
    if ($action === 'update') {
        $productId = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
        $variation = trim($_POST['variation'] ?? '');
        
        $cartKey = $productId . '_' . md5($variation);

        if (isset($_SESSION['cart'][$cartKey]) && $quantity >= 1) {
            // Verify stock
            $stmt = $db->prepare("SELECT stock FROM products WHERE id = ?");
            $stmt->execute([$productId]);
            $product = $stmt->fetch();

            if ($product && $product['stock'] >= $quantity) {
                $_SESSION['cart'][$cartKey]['quantity'] = $quantity;
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Insufficent stock for this quantity.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Cart line item not found.']);
        }
        exit;
    }

    // Standard Form Coupon application
    if ($action === 'apply_coupon') {
        $couponCode = trim($_POST['coupon_code'] ?? '');
        $stmt = $db->prepare("SELECT * FROM coupons WHERE code = ? AND expiry >= ?");
        $stmt->execute([$couponCode, date('Y-m-d')]);
        $coupon = $stmt->fetch();

        if ($coupon) {
            $_SESSION['coupon'] = [
                'code' => $coupon['code'],
                'discount_type' => $coupon['discount_type'],
                'value' => $coupon['value']
            ];
            header("Location: cart.php?coupon_success=1");
        } else {
            header("Location: cart.php?coupon_error=1");
        }
        exit;
    }

    // Standard Form Remove coupon
    if ($action === 'remove_coupon') {
        unset($_SESSION['coupon']);
        header("Location: cart.php");
        exit;
    }
}

// Handle Line Item Deletion via GET
if (isset($_GET['remove'])) {
    $removeKey = $_GET['remove'];
    if (isset($_SESSION['cart'][$removeKey])) {
        unset($_SESSION['cart'][$removeKey]);
    }
    header("Location: cart.php");
    exit;
}

// Handle Cart Clearing via GET
if (isset($_GET['clear'])) {
    $_SESSION['cart'] = [];
    unset($_SESSION['coupon']);
    header("Location: cart.php");
    exit;
}

require_once __DIR__ . '/includes/header.php';
?>

<h2 style="font-size: 2.25rem; margin-bottom: 8px;">Shopping Cart</h2>
<p style="color: var(--text-muted); margin-bottom: 30px;">Manage items in your checkout bag</p>

<?php if (isset($_GET['coupon_error'])): ?>
    <div class="badge badge-danger" style="display: block; padding: 12px; margin-bottom: 20px; border-radius: 8px;">
        <i class="fa-solid fa-triangle-exclamation"></i> Invalid or expired coupon code.
    </div>
<?php endif; ?>

<?php if (isset($_GET['coupon_success'])): ?>
    <div class="badge badge-success" style="display: block; padding: 12px; margin-bottom: 20px; border-radius: 8px;">
        <i class="fa-solid fa-circle-check"></i> Coupon applied successfully!
    </div>
<?php endif; ?>

<?php if (empty($_SESSION['cart'])): ?>
    <div class="glass-panel" style="padding: 60px; text-align: center; color: var(--text-muted); border-radius: 20px;">
        <i class="fa-solid fa-cart-arrow-down" style="font-size: 4rem; margin-bottom: 20px; color: var(--primary-light);"></i>
        <h3>Your cart is empty</h3>
        <p style="margin-top: 10px;">Looks like you haven't added any items yet.</p>
        <a href="search.php" class="btn btn-primary" style="margin-top: 24px;">Start Shopping</a>
    </div>
<?php else: ?>
    <div class="cart-wrapper">
        <!-- Cart Table Items -->
        <div class="glass-panel" style="padding: 24px; border-radius: 16px; height: fit-content;">
            <table class="cart-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Price</th>
                        <th>Quantity</th>
                        <th>Total</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $subtotal = 0;
                    foreach ($_SESSION['cart'] as $key => $item): 
                        $itemTotal = $item['price'] * $item['quantity'];
                        $subtotal += $itemTotal;
                        $itemImg = getProductImage($item['image'], $item['name']);
                    ?>
                        <tr>
                            <td>
                                <div style="display: flex; gap: 16px; align-items: center;">
                                    <img src="<?php echo $itemImg; ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;">
                                    <div>
                                        <a href="product.php?id=<?php echo $item['id']; ?>" style="font-weight: 600; font-size: 1rem;"><?php echo htmlspecialchars($item['name']); ?></a>
                                        <?php if (!empty($item['variation'])): ?>
                                            <div style="font-size: 0.8rem; color: var(--primary-light); margin-top: 4px;">
                                                <?php echo htmlspecialchars($item['variation']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td style="font-family: 'Outfit'; font-weight: 600;"><?php echo $currency_symbol . number_format($item['price'], 2); ?></td>
                            <td>
                                <div class="qty-control">
                                    <button class="qty-btn" onclick="updateCartQty(<?php echo $item['id']; ?>, <?php echo $item['quantity'] - 1; ?>, '<?php echo addslashes($item['variation']); ?>')">-</button>
                                    <input type="text" value="<?php echo $item['quantity']; ?>" class="qty-input" readonly>
                                    <button class="qty-btn" onclick="updateCartQty(<?php echo $item['id']; ?>, <?php echo $item['quantity'] + 1; ?>, '<?php echo addslashes($item['variation']); ?>')">+</button>
                                </div>
                            </td>
                            <td style="font-family: 'Outfit'; font-weight: 700; color: var(--primary-light);"><?php echo $currency_symbol . number_format($itemTotal, 2); ?></td>
                            <td>
                                <a href="cart.php?remove=<?php echo urlencode($key); ?>" class="remove-btn" title="Remove Item"><i class="fa-regular fa-trash-can"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="flex-between">
                <a href="search.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left-long"></i> Continue Shopping</a>
                <a href="cart.php?clear=1" class="btn btn-secondary btn-sm" style="color: var(--danger); border-color: var(--danger);"><i class="fa-regular fa-circle-xmark"></i> Clear Cart</a>
            </div>
        </div>

        <!-- Checkout Summary Card -->
        <div class="glass-panel" style="padding: 24px; border-radius: 16px; height: fit-content; display: flex; flex-direction: column; gap: 20px;">
            <h3>Order Summary</h3>
            
            <div style="display: flex; flex-direction: column; gap: 12px; border-bottom: 1px solid var(--border-color); padding-bottom: 16px;">
                <div class="flex-between">
                    <span style="color: var(--text-muted);">Subtotal</span>
                    <span style="font-weight: 600; font-family: 'Outfit';"><?php echo $currency_symbol . number_format($subtotal, 2); ?></span>
                </div>

                <!-- Coupon Discount Calculation -->
                <?php 
                $discount = 0;
                if (isset($_SESSION['coupon'])): 
                    $cp = $_SESSION['coupon'];
                    if ($cp['discount_type'] === 'percentage') {
                        $discount = $subtotal * ($cp['value'] / 100);
                    } else {
                        $discount = $cp['value'];
                    }
                    if ($discount > $subtotal) $discount = $subtotal;
                ?>
                    <div class="flex-between" style="color: var(--success);">
                        <span>Discount (Code: <?php echo htmlspecialchars($cp['code']); ?>)</span>
                        <span>-<?php echo $currency_symbol . number_format($discount, 2); ?></span>
                    </div>
                <?php endif; ?>
                
                <div class="flex-between">
                    <span style="color: var(--text-muted);">Estimated Tax</span>
                    <span style="font-weight: 600; font-family: 'Outfit';"><?php echo $currency_symbol; ?>0.00</span>
                </div>
            </div>

            <!-- Total Amount -->
            <?php $grandTotal = $subtotal - $discount; ?>
            <div class="flex-between" style="font-size: 1.25rem; font-weight: 700;">
                <span>Total Amount</span>
                <span style="color: var(--primary-light); font-family: 'Outfit';"><?php echo $currency_symbol . number_format($grandTotal, 2); ?></span>
            </div>

            <!-- Coupon Input Form -->
            <div style="border-top: 1px solid var(--border-color); padding-top: 20px;">
                <?php if (isset($_SESSION['coupon'])): ?>
                    <form action="cart.php" method="POST">
                        <input type="hidden" name="action" value="remove_coupon">
                        <div class="flex-between" style="background: rgba(16, 185, 129, 0.1); padding: 8px 12px; border-radius: 8px; border: 1px dashed var(--success);">
                            <span style="font-size:0.85rem; font-weight:600; color:var(--success);"><i class="fa-solid fa-tag"></i> Active: <?php echo htmlspecialchars($_SESSION['coupon']['code']); ?></span>
                            <button type="submit" style="color: var(--danger); font-size: 0.8rem; cursor: pointer; font-weight: 600;">Remove</button>
                        </div>
                    </form>
                <?php else: ?>
                    <form action="cart.php" method="POST" style="display: flex; gap: 8px;">
                        <input type="hidden" name="action" value="apply_coupon">
                        <input type="text" name="coupon_code" class="form-control" placeholder="Promo code" required style="padding: 8px 12px; font-size: 0.875rem;">
                        <button type="submit" class="btn btn-secondary btn-sm" style="border-radius: 8px; padding: 8px 16px;">Apply</button>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Proceed to checkout -->
            <a href="checkout.php" class="btn btn-primary" style="width: 100%; margin-top: 10px;">
                Proceed to Checkout <i class="fa-solid fa-arrow-right-long"></i>
            </a>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
