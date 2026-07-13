<?php
// product.php - Product Detail Page
require_once __DIR__ . '/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$productId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$db = getDBConnection();

// Auto-create Q&A table if it doesn't exist (Advanced Feature)
try {
    $db->exec("CREATE TABLE IF NOT EXISTS product_qna (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        product_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        question TEXT NOT NULL,
        answer TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {
    // If MySQL, auto-translate syntax
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS product_qna (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            user_id INT NOT NULL,
            question TEXT NOT NULL,
            answer TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $ex) {}
}

// Fetch product details
$stmt = $db->prepare("
    SELECT p.*, c.name AS category_name, v.name AS vendor_name, v.id AS vendor_real_id
    FROM products p
    JOIN categories c ON p.category_id = c.id
    JOIN vendors v ON p.vendor_id = v.id
    WHERE p.id = ?
");
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    require_once __DIR__ . '/includes/header.php';
    echo '<div class="glass-panel" style="padding: 40px; text-align: center; color: var(--text-muted);">';
    echo '<h2>Product Not Found</h2><p>The requested product details are unavailable.</p>';
    echo '<a href="index.php" class="btn btn-primary" style="margin-top: 16px;">Back to Home</a></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Handle review submit
$reviewMsg = '';
$reviewErr = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    if (!isset($_SESSION['user_id'])) {
        $reviewErr = 'You must be logged in to write a review.';
    } else {
        $rating = isset($_POST['rating']) ? intval($_POST['rating']) : 5;
        $comment = trim($_POST['comment'] ?? '');

        if (empty($comment)) {
            $reviewErr = 'Please write a comment.';
        } else {
            try {
                $stmtRev = $db->prepare("INSERT INTO reviews (product_id, user_id, rating, comment) VALUES (?, ?, ?, ?)");
                $stmtRev->execute([$productId, $_SESSION['user_id'], $rating, $comment]);
                $reviewMsg = 'Your review has been submitted successfully!';
            } catch (Exception $e) {
                $reviewErr = 'Failed to submit review: ' . $e->getMessage();
            }
        }
    }
}

// Handle Q&A submit
$qnaMsg = '';
$qnaErr = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_question'])) {
    if (!isset($_SESSION['user_id'])) {
        $qnaErr = 'You must be logged in to submit a question.';
    } else {
        $questionText = trim($_POST['question'] ?? '');
        if (empty($questionText)) {
            $qnaErr = 'Please enter a valid question.';
        } else {
            try {
                $stmtQ = $db->prepare("INSERT INTO product_qna (product_id, user_id, question) VALUES (?, ?, ?)");
                $stmtQ->execute([$productId, $_SESSION['user_id'], $questionText]);
                $qnaMsg = 'Your question has been posted! The merchant will answer soon.';
            } catch (Exception $e) {
                $qnaErr = 'Failed to post question: ' . $e->getMessage();
            }
        }
    }
}

// Fetch variations
$variations = [];
$stmtVar = $db->prepare("SELECT * FROM product_variations WHERE product_id = ?");
$stmtVar->execute([$productId]);
while ($row = $stmtVar->fetch()) {
    $variations[$row['attribute']][] = $row;
}

// Fetch reviews
$reviews = $db->prepare("
    SELECT r.*, u.name AS user_name 
    FROM reviews r 
    JOIN users u ON r.user_id = u.id 
    WHERE r.product_id = ? 
    ORDER BY r.created_at DESC
");
$reviews->execute([$productId]);
$allReviews = $reviews->fetchAll();

// Fetch Q&A
$qnaList = [];
try {
    $stmtQna = $db->prepare("
        SELECT q.*, u.name AS user_name 
        FROM product_qna q 
        JOIN users u ON q.user_id = u.id 
        WHERE q.product_id = ? 
        ORDER BY q.created_at DESC
    ");
    $stmtQna->execute([$productId]);
    $qnaList = $stmtQna->fetchAll();
} catch (Exception $e) {}

// Calculate average ratings
$avgRating = 0;
if (count($allReviews) > 0) {
    $sum = 0;
    foreach ($allReviews as $rev) {
        $sum += $rev['rating'];
    }
    $avgRating = round($sum / count($allReviews), 1);
}

// Register as Recently Viewed in Cookies
$recentlyViewed = isset($_COOKIE['recently_viewed']) ? json_decode($_COOKIE['recently_viewed'], true) : [];
if (!is_array($recentlyViewed)) {
    $recentlyViewed = [];
}
if (($key = array_search($productId, $recentlyViewed)) !== false) {
    unset($recentlyViewed[$key]);
}
array_unshift($recentlyViewed, $productId);
$recentlyViewed = array_slice($recentlyViewed, 0, 5); // keep last 5
setcookie('recently_viewed', json_encode($recentlyViewed), time() + (86400 * 30), "/"); // 30 days

require_once __DIR__ . '/includes/header.php';
?>

<div class="product-single">
    <!-- Gallery/Image Panel -->
    <div class="product-gallery">
        <img id="main-product-image" src="<?php echo getProductImage($product['image'], $product['name']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" style="width: 100%; border-radius: 20px; object-fit: cover;">
    </div>

    <!-- Info Detail Panel -->
    <div class="product-info-panel glass-panel" style="padding: 40px; border-radius: 24px;">
        <span class="badge badge-success"><i class="fa-solid fa-store"></i> <?php echo htmlspecialchars($product['vendor_name']); ?></span>
        <h1 style="font-size: 2.25rem; margin-top: 10px;"><?php echo htmlspecialchars($product['name']); ?></h1>
        
        <!-- Star Rating -->
        <div class="rating-stars" style="font-size: 1.1rem; margin-top: 5px;">
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
            <span style="color: var(--text-muted); font-size: 0.9rem; margin-left: 8px;">
                <?php echo $avgRating; ?>/5 Rating (<?php echo count($allReviews); ?> Reviews)
            </span>
        </div>

        <div style="font-size: 1.85rem; font-weight: 700; color: var(--primary-light); margin-top: 10px;">
            <?php echo $currency_symbol . number_format($product['price'], 2); ?>
        </div>

        <p style="color: var(--text-muted); font-size: 0.95rem; margin-top: 10px;">
            Category: <strong style="color: var(--text-main);"><?php echo htmlspecialchars($product['category_name']); ?></strong>
        </p>

        <p style="margin-top: 16px; line-height: 1.7; color: var(--text-muted);">
            <?php echo nl2br(htmlspecialchars($product['description'])); ?>
        </p>

        <!-- Variations Selectors -->
        <?php if (!empty($variations)): ?>
            <div style="margin-top: 24px; border-top: 1px solid var(--border-color); padding-top: 20px;">
                <?php foreach ($variations as $attr => $options): ?>
                    <div class="variation-section">
                        <div class="variation-label"><?php echo htmlspecialchars($attr); ?>:</div>
                        <div class="variation-options" id="variation-<?php echo htmlspecialchars($attr); ?>">
                            <?php foreach ($options as $idx => $opt): ?>
                                <button type="button" class="variation-pill <?php echo $idx === 0 ? 'active' : ''; ?>" 
                                        data-attribute="<?php echo htmlspecialchars($attr); ?>"
                                        data-value="<?php echo htmlspecialchars($opt['value']); ?>"
                                        data-stock="<?php echo $opt['stock']; ?>"
                                        onclick="selectVariation(this, '<?php echo htmlspecialchars($attr); ?>', <?php echo $opt['stock']; ?>)">
                                    <?php echo htmlspecialchars($opt['value']); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Quantity and Action Buttons -->
        <div style="margin-top: 30px; display: flex; align-items: center; gap: 20px; border-top: 1px solid var(--border-color); padding-top: 24px;">
            <div class="qty-control">
                <button type="button" class="qty-btn" onclick="adjustQty(-1)">-</button>
                <input type="text" id="qty-selector" class="qty-input" value="1" readonly>
                <button type="button" class="qty-btn" onclick="adjustQty(1)">+</button>
            </div>
            
            <button onclick="triggerAddToCart()" class="btn btn-primary" style="flex-grow: 1;">
                <i class="fa-solid fa-cart-shopping"></i> Add to Cart
            </button>
            
            <button onclick="toggleWishlist(<?php echo $productId; ?>, this)" class="btn btn-secondary" style="border-radius: 50%; width: 48px; height: 48px; padding: 0; display: inline-flex; align-items: center; justify-content: center; font-size: 1.2rem;">
                <i class="fa-solid fa-heart"></i>
            </button>
        </div>

        <div style="margin-top: 16px; font-size: 0.9rem; color: var(--text-muted);" id="stock-display">
            In Stock: <strong style="color: var(--success);"><?php echo $product['stock']; ?> units</strong>
        </div>
    </div>
</div>

<!-- Tabs for Reviews & Q&A -->
<div style="margin-top: 60px;">
    <div style="display: flex; gap: 24px; border-bottom: 1px solid var(--border-color); margin-bottom: 24px;">
        <button onclick="switchTab('reviews')" id="tab-btn-reviews" class="btn" style="border-radius: 0; border-bottom: 3px solid var(--primary); padding: 12px 24px; font-weight: 700; color: #fff;">
            Customer Reviews (<?php echo count($allReviews); ?>)
        </button>
        <button onclick="switchTab('qna')" id="tab-btn-qna" class="btn" style="border-radius: 0; border-bottom: 3px solid transparent; padding: 12px 24px; font-weight: 700; color: var(--text-muted);">
            Product Q&A (<?php echo count($qnaList); ?>)
        </button>
    </div>

    <!-- REVIEWS TAB PANEL -->
    <div id="tab-content-reviews">
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 40px;">
            <div>
                <?php if (empty($allReviews)): ?>
                    <p style="color: var(--text-muted); font-style: italic;">No reviews yet. Be the first to share your experience!</p>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 20px;">
                        <?php foreach ($allReviews as $rev): ?>
                            <div class="glass-panel" style="padding: 24px; border-radius: 12px;">
                                <div class="flex-between" style="margin-bottom: 8px;">
                                    <strong style="font-size: 1rem;"><?php echo htmlspecialchars($rev['user_name']); ?></strong>
                                    <span style="color: var(--text-muted); font-size: 0.8rem;"><?php echo date('M d, Y', strtotime($rev['created_at'])); ?></span>
                                </div>
                                <div class="rating-stars" style="margin-bottom: 8px;">
                                    <?php 
                                        for ($i = 1; $i <= 5; $i++) {
                                            echo $i <= $rev['rating'] ? '<i class="fa-solid fa-star"></i>' : '<i class="fa-regular fa-star"></i>';
                                        }
                                    ?>
                                </div>
                                <p style="color: var(--text-muted);"><?php echo nl2br(htmlspecialchars($rev['comment'])); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Review submission form -->
            <div>
                <div class="glass-panel" style="padding: 24px; border-radius: 12px;">
                    <h3 style="margin-bottom: 16px;">Write a Review</h3>
                    <?php if (!empty($reviewMsg)): ?>
                        <div class="badge badge-success" style="display:block; padding:8px; margin-bottom:12px;"><?php echo $reviewMsg; ?></div>
                    <?php endif; ?>
                    <?php if (!empty($reviewErr)): ?>
                        <div class="badge badge-danger" style="display:block; padding:8px; margin-bottom:12px;"><?php echo $reviewErr; ?></div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['user_id'])): ?>
                        <form action="product.php?id=<?php echo $productId; ?>" method="POST">
                            <input type="hidden" name="submit_review" value="1">
                            <div class="form-group">
                                <label for="rating">Rating</label>
                                <select name="rating" id="rating" class="form-control">
                                    <option value="5">⭐⭐⭐⭐⭐ (5 - Excellent)</option>
                                    <option value="4">⭐⭐⭐⭐ (4 - Good)</option>
                                    <option value="3">⭐⭐⭐ (3 - Average)</option>
                                    <option value="2">⭐⭐ (2 - Poor)</option>
                                    <option value="1">⭐ (1 - Terrible)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="comment">Your Comment</label>
                                <textarea name="comment" id="comment" rows="4" class="form-control" placeholder="Write feedback details..." required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary" style="width: 100%;">Submit Review</button>
                        </form>
                    <?php else: ?>
                        <p style="color: var(--text-muted); font-size: 0.9rem;">
                            Please <a href="login.php" style="color: var(--primary-light); font-weight: 600;">log in</a> to write a product review.
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- QNA TAB PANEL -->
    <div id="tab-content-qna" style="display: none;">
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 40px;">
            <div>
                <?php if (empty($qnaList)): ?>
                    <p style="color: var(--text-muted); font-style: italic;">No questions have been asked about this product yet.</p>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 20px;">
                        <?php foreach ($qnaList as $qna): ?>
                            <div class="glass-panel" style="padding: 24px; border-radius: 12px; border-left: 4px solid var(--secondary);">
                                <div style="margin-bottom: 8px;">
                                    <strong style="color: var(--secondary);"><span style="font-size:1.1rem;">Q:</span> <?php echo htmlspecialchars($qna['question']); ?></strong>
                                    <br><span style="color: var(--text-muted); font-size: 0.75rem;">Asked by <?php echo htmlspecialchars($qna['user_name']); ?> on <?php echo date('M d, Y', strtotime($qna['created_at'])); ?></span>
                                </div>
                                <div style="margin-top: 12px; padding-top: 12px; border-top: 1px dashed var(--border-color);">
                                    <?php if (!empty($qna['answer'])): ?>
                                        <p style="color: var(--text-main);"><strong>A:</strong> <?php echo nl2br(htmlspecialchars($qna['answer'])); ?></p>
                                        <span style="color: var(--text-muted); font-size: 0.75rem; font-style: italic;">Merchant Response</span>
                                    <?php else: ?>
                                        <p style="color: var(--text-muted); font-style: italic;">Awaiting response from merchant.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Submit a question form -->
            <div>
                <div class="glass-panel" style="padding: 24px; border-radius: 12px;">
                    <h3 style="margin-bottom: 16px;">Ask a Question</h3>
                    <?php if (!empty($qnaMsg)): ?>
                        <div class="badge badge-success" style="display:block; padding:8px; margin-bottom:12px;"><?php echo $qnaMsg; ?></div>
                    <?php endif; ?>
                    <?php if (!empty($qnaErr)): ?>
                        <div class="badge badge-danger" style="display:block; padding:8px; margin-bottom:12px;"><?php echo $qnaErr; ?></div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['user_id'])): ?>
                        <form action="product.php?id=<?php echo $productId; ?>" method="POST">
                            <input type="hidden" name="submit_question" value="1">
                            <div class="form-group">
                                <label for="question">Your Question</label>
                                <textarea name="question" id="question" rows="4" class="form-control" placeholder="What would you like to know about this item?" required></textarea>
                            </div>
                            <button type="submit" class="btn btn-secondary" style="width: 100%; border-color: var(--secondary); color: var(--secondary);">Post Question</button>
                        </form>
                    <?php else: ?>
                        <p style="color: var(--text-muted); font-size: 0.9rem;">
                            Please <a href="login.php" style="color: var(--primary-light); font-weight: 600;">log in</a> to ask questions.
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let selectedVariationDetails = {};

function selectVariation(button, attribute, stock) {
    // Set active style
    const container = button.parentNode;
    container.querySelectorAll('.variation-pill').forEach(btn => btn.classList.remove('active'));
    button.classList.add('active');

    // Save details
    selectedVariationDetails[attribute] = button.getAttribute('data-value');
    
    // Optional: update stock count display dynamically if attribute updates stock
    const customStock = button.getAttribute('data-stock');
    if (customStock !== null) {
         document.getElementById('stock-display').innerHTML = `In Stock (${attribute} ${button.innerText}): <strong style="color: var(--success);">${customStock} units</strong>`;
    }
}

function adjustQty(amount) {
    const qtyInput = document.getElementById('qty-selector');
    let currentQty = parseInt(qtyInput.value);
    currentQty += amount;
    if (currentQty < 1) currentQty = 1;
    qtyInput.value = currentQty;
}

function triggerAddToCart() {
    // Build variation string details
    let selectedDetails = [];
    document.querySelectorAll('.variation-section').forEach(section => {
        const activeBtn = section.querySelector('.variation-pill.active');
        if (activeBtn) {
            const attr = activeBtn.getAttribute('data-attribute');
            const val = activeBtn.getAttribute('data-value');
            selectedDetails.push(`${attr}: ${val}`);
        }
    });

    const quantity = parseInt(document.getElementById('qty-selector').value);
    const varString = selectedDetails.join(', ');
    
    // Trigger standard AJAX cart call (passes details inside variation_id or via variation query)
    const data = new FormData();
    data.append('action', 'add');
    data.append('product_id', <?php echo $productId; ?>);
    data.append('quantity', quantity);
    data.append('variation', varString);

    fetch('cart.php', {
        method: 'POST',
        body: data
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            window.showToast('Product successfully added to cart!', 'success');
            const counter = document.getElementById('cart-counter');
            if (counter && result.cart_count !== undefined) {
                counter.innerText = result.cart_count;
                counter.style.display = 'flex';
            }
        } else {
            window.showToast(result.message || 'Error adding to cart.', 'error');
        }
    });
}

function switchTab(tab) {
    const reviewContent = document.getElementById('tab-content-reviews');
    const qnaContent = document.getElementById('tab-content-qna');
    const reviewBtn = document.getElementById('tab-btn-reviews');
    const qnaBtn = document.getElementById('tab-btn-qna');

    if (tab === 'reviews') {
        reviewContent.style.display = 'block';
        qnaContent.style.display = 'none';
        reviewBtn.style.borderBottomColor = 'var(--primary)';
        reviewBtn.style.color = '#fff';
        qnaBtn.style.borderBottomColor = 'transparent';
        qnaBtn.style.color = 'var(--text-muted)';
    } else {
        reviewContent.style.display = 'none';
        qnaContent.style.display = 'block';
        reviewBtn.style.borderBottomColor = 'transparent';
        reviewBtn.style.color = 'var(--text-muted)';
        qnaBtn.style.borderBottomColor = 'var(--primary)';
        qnaBtn.style.color = '#fff';
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
