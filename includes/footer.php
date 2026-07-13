<?php
// includes/footer.php - Shared Frontend Footer
?>
    </main>

    <!-- Toast messages container -->
    <div id="toast-container" class="toast-container"></div>

    <footer>
        <div class="container">
            <div class="footer-grid">
                <div class="footer-col">
                    <h3 style="font-size: 1.5rem; margin-bottom: 12px; background: linear-gradient(135deg, #a78bfa, #0ea5e9); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                        OmniMart
                    </h3>
                    <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 20px;">
                        The next generation multi-vendor marketplace connecting top-tier verified merchants with smart buyers worldwide.
                    </p>
                    <div class="newsletter-section">
                        <h4 style="font-size: 0.95rem; text-transform: uppercase; color: var(--text-main); letter-spacing: 0.05em;">Newsletter Signup</h4>
                        <form action="index.php" method="POST" class="newsletter-form" onsubmit="event.preventDefault(); window.showToast('Thank you for subscribing to our newsletter!', 'success'); this.reset();">
                            <input type="email" placeholder="Your email address" required>
                            <button type="submit" class="btn btn-primary btn-sm">Subscribe</button>
                        </form>
                    </div>
                </div>
                <div class="footer-col">
                    <h4>Categories</h4>
                    <ul>
                        <li><a href="search.php?category=1">Electronics</a></li>
                        <li><a href="search.php?category=2">Fashion</a></li>
                        <li><a href="search.php?category=3">Home & Living</a></li>
                        <li><a href="sitemap.php" style="color: var(--secondary);"><i class="fa-solid fa-sitemap"></i> Sitemap (XML)</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>Customer Care</h4>
                    <ul>
                        <li><a href="contact.php">Contact Support</a></li>
                        <li><a href="profile.php">My Orders</a></li>
                        <li><a href="cart.php">Shopping Cart</a></li>
                        <li><a href="profile.php?tab=wishlist">My Wishlist</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>Merchant Center</h4>
                    <ul>
                        <li><a href="signup.php?role=vendor">Apply as Vendor</a></li>
                        <li><a href="vendor/dashboard.php">Vendor Login</a></li>
                        <li><a href="admin/dashboard.php">Administrator Login</a></li>
                    </ul>
                </div>
            </div>
            
            <div class="copyright">
                <p>&copy; <?php echo date('Y'); ?> OmniMart Inc. Engineered with precision. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Core Scripts -->
    <script src="assets/js/main.js"></script>
    <script src="assets/js/cart.js"></script>
</body>
</html>
