<?php
// checkout.php - Checkout Form & Order Placement
require_once __DIR__ . '/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Enforce login for checkout
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?msg=auth_required_checkout");
    exit;
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

// Check if cart is empty
if (empty($_SESSION['cart'])) {
    header("Location: cart.php");
    exit;
}

// Calculate total costs
$subtotal = 0;
foreach ($_SESSION['cart'] as $item) {
    $subtotal += ($item['price'] * $item['quantity']);
}

$discount = 0;
if (isset($_SESSION['coupon'])) {
    $cp = $_SESSION['coupon'];
    if ($cp['discount_type'] === 'percentage') {
        $discount = $subtotal * ($cp['value'] / 100);
    } else {
        $discount = $cp['value'];
    }
    if ($discount > $subtotal) $discount = $subtotal;
}
$grandTotal = $subtotal - $discount;

$error = '';
$order_id = 0;
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $shipping_address = trim($_POST['shipping_address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $gateway = $_POST['gateway'] ?? 'COD'; // 'COD', 'Stripe', 'Razorpay'

    if (empty($shipping_address) || empty($phone)) {
        $error = 'Please fill in all shipping details.';
    } else {
        try {
            $db->beginTransaction();

            // 1. Insert Order
            // Status defaults to 'Pending'
            $stmtOrder = $db->prepare("INSERT INTO orders (user_id, total_amount, status) VALUES (?, ?, ?)");
            $stmtOrder->execute([$_SESSION['user_id'], $grandTotal, 'Pending']);
            $order_id = $db->lastInsertId();

            // 2. Insert Order Items & Decrement Stock
            $stmtItem = $db->prepare("INSERT INTO order_items (order_id, product_id, quantity, price, variation_details) VALUES (?, ?, ?, ?, ?)");
            $stmtDecrStock = $db->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
            $stmtDecrVarStock = $db->prepare("UPDATE product_variations SET stock = stock - ? WHERE product_id = ? AND value = ?");

            foreach ($_SESSION['cart'] as $key => $item) {
                // Insert details
                $stmtItem->execute([
                    $order_id,
                    $item['id'],
                    $item['quantity'],
                    $item['price'],
                    $item['variation']
                ]);

                // Decrement main product stock
                $stmtDecrStock->execute([$item['quantity'], $item['id']]);

                // Decrement variation stock if specified
                if (!empty($item['variation'])) {
                    // Extract value e.g. "Color: Black" -> "Black"
                    $parts = explode(':', $item['variation']);
                    if (count($parts) === 2) {
                        $val = trim($parts[1]);
                        $stmtDecrVarStock->execute([$item['quantity'], $item['id'], $val]);
                    }
                }
            }

            // Commit transaction
            $db->commit();

            // Clear session cart/coupon
            $_SESSION['cart'] = [];
            unset($_SESSION['coupon']);
            $success = true;

        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Order processing failed: ' . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($success): ?>
    <!-- Order Success Confirmation -->
    <div class="glass-panel" style="max-width: 600px; margin: 40px auto; padding: 40px; text-align: center; border-radius: 20px;">
        <i class="fa-solid fa-circle-check" style="font-size: 5rem; color: var(--success); margin-bottom: 24px;"></i>
        <h2 style="font-size: 2.25rem; margin-bottom: 12px;">Order Placed!</h2>
        <p style="color: var(--text-muted); margin-bottom: 20px;">Thank you for your purchase. Your order receipt reference ID is <strong style="color: var(--primary-light);">#<?php echo $order_id; ?></strong>.</p>
        
        <div style="background: var(--bg-surface-elevated); padding: 20px; border-radius: 12px; margin-bottom: 30px; text-align: left;">
            <h4 style="margin-bottom: 12px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">Order Details</h4>
            <div class="flex-between" style="margin-bottom: 8px; font-size: 0.95rem;">
                <span style="color: var(--text-muted);">Amount Paid:</span>
                <span style="font-weight: 700; color: var(--primary-light); font-family: 'Outfit';"><?php echo $currency_symbol . number_format($grandTotal, 2); ?></span>
            </div>
            <div class="flex-between" style="font-size: 0.95rem;">
                <span style="color: var(--text-muted);">Status:</span>
                <span class="badge badge-warning">Pending Approval</span>
            </div>
        </div>

        <div style="display: flex; gap: 16px; justify-content: center;">
            <a href="profile.php" class="btn btn-primary">Track Order</a>
            <a href="index.php" class="btn btn-secondary">Go to Home</a>
        </div>
    </div>
<?php else: ?>
    <!-- Checkout Form Layout -->
    <div class="cart-wrapper">
        <!-- Billing / Shipping Form -->
        <div class="glass-panel" style="padding: 32px; border-radius: 16px;">
            <h3 style="margin-bottom: 24px;">Shipping & Billing Information</h3>
            
            <?php if (!empty($error)): ?>
                <div class="badge badge-danger" style="display: block; padding: 12px; margin-bottom: 20px; border-radius: 8px;">
                    <i class="fa-solid fa-triangle-exclamation"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form action="checkout.php" method="POST">
                <input type="hidden" name="place_order" value="1">
                
                <div class="form-group">
                    <label for="full_name">Recipient Name</label>
                    <input type="text" id="full_name" class="form-control" value="<?php echo htmlspecialchars($_SESSION['name']); ?>" readonly style="opacity: 0.7;">
                </div>

                <div class="form-group">
                    <label for="shipping_address">Delivery Address <span style="color: var(--danger);">*</span></label>
                    <textarea name="shipping_address" id="shipping_address" rows="3" class="form-control" required placeholder="Enter street address, building, city, and zip code..."></textarea>
                </div>

                <div class="form-group">
                    <label for="phone">Phone Contact <span style="color: var(--danger);">*</span></label>
                    <input type="text" name="phone" id="phone" class="form-control" required placeholder="e.g. +1 555-0199">
                </div>

                <!-- Payment Sandbox Method -->
                <div class="form-group" style="margin-top: 24px;">
                    <label>Select Payment Method</label>
                    <div style="display: flex; flex-direction: column; gap: 12px; margin-top: 10px;">
                        <label style="display: flex; align-items: center; gap: 12px; background: var(--bg-surface-elevated); padding: 14px; border-radius: 8px; cursor: pointer; border: 1px solid var(--border-color);">
                            <input type="radio" name="gateway" value="COD" checked>
                            <div>
                                <strong>Cash on Delivery (COD)</strong>
                                <div style="font-size: 0.8rem; color: var(--text-muted);">Pay with cash upon package receipt.</div>
                            </div>
                        </label>
                        <label style="display: flex; align-items: center; gap: 12px; background: var(--bg-surface-elevated); padding: 14px; border-radius: 8px; cursor: pointer; border: 1px solid var(--border-color);">
                            <input type="radio" name="gateway" value="Stripe">
                            <div>
                                <strong>Stripe Sandbox Mock</strong>
                                <div style="font-size: 0.8rem; color: var(--text-muted);">Simulate secure online card transactions.</div>
                            </div>
                        </label>
                        <label style="display: flex; align-items: center; gap: 12px; background: var(--bg-surface-elevated); padding: 14px; border-radius: 8px; cursor: pointer; border: 1px solid var(--border-color);">
                            <input type="radio" name="gateway" value="Razorpay">
                            <div>
                                <strong>Razorpay Sandbox Mock</strong>
                                <div style="font-size: 0.8rem; color: var(--text-muted);">Simulate local mobile banking/UPI transfer.</div>
                            </div>
                        </label>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 24px; padding: 14px;">
                    Confirm & Place Order (<?php echo $currency_symbol . number_format($grandTotal, 2); ?>)
                </button>
            </form>
        </div>

        <!-- Order Summary Detail -->
        <div class="glass-panel" style="padding: 24px; border-radius: 16px; height: fit-content; display: flex; flex-direction: column; gap: 16px;">
            <h3>Review Items</h3>
            
            <div style="max-height: 300px; overflow-y: auto; display: flex; flex-direction: column; gap: 12px;">
                <?php foreach ($_SESSION['cart'] as $item): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 10px;">
                        <div>
                            <div style="font-weight: 600; font-size: 0.95rem;"><?php echo htmlspecialchars($item['name']); ?></div>
                            <div style="font-size: 0.75rem; color: var(--text-muted);">
                                Qty: <?php echo $item['quantity']; ?> <?php echo !empty($item['variation']) ? ' | ' . htmlspecialchars($item['variation']) : ''; ?>
                            </div>
                        </div>
                        <div style="font-family: 'Outfit'; font-weight: 600; color: var(--primary-light);">
                            <?php echo $currency_symbol . number_format($item['price'] * $item['quantity'], 2); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 10px;">
                <div class="flex-between" style="font-size: 0.9rem; color: var(--text-muted);">
                    <span>Subtotal:</span>
                    <span><?php echo $currency_symbol . number_format($subtotal, 2); ?></span>
                </div>
                <?php if ($discount > 0): ?>
                    <div class="flex-between" style="font-size: 0.9rem; color: var(--success);">
                        <span>Coupon Discount:</span>
                        <span>-<?php echo $currency_symbol . number_format($discount, 2); ?></span>
                    </div>
                <?php endif; ?>
                <div class="flex-between" style="font-size: 1.15rem; font-weight: 700; border-top: 1px solid var(--border-color); padding-top: 12px;">
                    <span>Total Cost:</span>
                    <span style="color: var(--primary-light);"><?php echo $currency_symbol . number_format($grandTotal, 2); ?></span>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
