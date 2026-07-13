<?php
// init_db.php - Database Initialization Script
// Sets up all tables and inserts seed data. Works for SQLite and MySQL.

require_once __DIR__ . '/db.php';

$pdo = getDBConnection();
$dbType = DB_TYPE;

echo "Initializing database using driver: " . $dbType . "...\n";

// Helper to define table creation strings depending on driver
$queries = [];

if ($dbType === 'sqlite') {
    $queries['users'] = "CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role VARCHAR(50) NOT NULL DEFAULT 'customer',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";

    $queries['vendors'] = "CREATE TABLE IF NOT EXISTS vendors (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        name VARCHAR(255) NOT NULL,
        status VARCHAR(50) NOT NULL DEFAULT 'Pending',
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";

    $queries['categories'] = "CREATE TABLE IF NOT EXISTS categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(255) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";

    $queries['products'] = "CREATE TABLE IF NOT EXISTS products (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        vendor_id INTEGER NOT NULL,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        price DECIMAL(10, 2) NOT NULL,
        stock INTEGER NOT NULL DEFAULT 0,
        image VARCHAR(255),
        category_id INTEGER NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
    )";

    $queries['product_variations'] = "CREATE TABLE IF NOT EXISTS product_variations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        product_id INTEGER NOT NULL,
        attribute VARCHAR(100) NOT NULL,
        value VARCHAR(100) NOT NULL,
        stock INTEGER NOT NULL DEFAULT 0,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    )";

    $queries['orders'] = "CREATE TABLE IF NOT EXISTS orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        total_amount DECIMAL(10, 2) NOT NULL,
        status VARCHAR(50) NOT NULL DEFAULT 'Pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";

    $queries['order_items'] = "CREATE TABLE IF NOT EXISTS order_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        order_id INTEGER NOT NULL,
        product_id INTEGER NOT NULL,
        quantity INTEGER NOT NULL,
        price DECIMAL(10, 2) NOT NULL,
        variation_details VARCHAR(255),
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    )";

    $queries['coupons'] = "CREATE TABLE IF NOT EXISTS coupons (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code VARCHAR(50) UNIQUE NOT NULL,
        discount_type VARCHAR(50) NOT NULL, -- 'percentage' or 'fixed'
        value DECIMAL(10, 2) NOT NULL,
        expiry DATE NOT NULL
    )";

    $queries['reviews'] = "CREATE TABLE IF NOT EXISTS reviews (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        product_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        rating INTEGER NOT NULL,
        comment TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";

    $queries['feedback'] = "CREATE TABLE IF NOT EXISTS feedback (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";

    $queries['admin'] = "CREATE TABLE IF NOT EXISTS admin (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL
    )";

    $queries['settings'] = "CREATE TABLE IF NOT EXISTS settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        key_name VARCHAR(100) UNIQUE NOT NULL,
        val_value TEXT
    )";
} else {
    // MySQL Queries
    $queries['users'] = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role VARCHAR(50) NOT NULL DEFAULT 'customer',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $queries['vendors'] = "CREATE TABLE IF NOT EXISTS vendors (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        status VARCHAR(50) NOT NULL DEFAULT 'Pending',
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $queries['categories'] = "CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $queries['products'] = "CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        vendor_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        price DECIMAL(10, 2) NOT NULL,
        stock INT NOT NULL DEFAULT 0,
        image VARCHAR(255),
        category_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $queries['product_variations'] = "CREATE TABLE IF NOT EXISTS product_variations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        attribute VARCHAR(100) NOT NULL,
        value VARCHAR(100) NOT NULL,
        stock INT NOT NULL DEFAULT 0,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $queries['orders'] = "CREATE TABLE IF NOT EXISTS orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        total_amount DECIMAL(10, 2) NOT NULL,
        status VARCHAR(50) NOT NULL DEFAULT 'Pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $queries['order_items'] = "CREATE TABLE IF NOT EXISTS order_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        product_id INT NOT NULL,
        quantity INT NOT NULL,
        price DECIMAL(10, 2) NOT NULL,
        variation_details VARCHAR(255),
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $queries['coupons'] = "CREATE TABLE IF NOT EXISTS coupons (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(50) UNIQUE NOT NULL,
        discount_type VARCHAR(50) NOT NULL,
        value DECIMAL(10, 2) NOT NULL,
        expiry DATE NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $queries['reviews'] = "CREATE TABLE IF NOT EXISTS reviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        user_id INT NOT NULL,
        rating INT NOT NULL,
        comment TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $queries['feedback'] = "CREATE TABLE IF NOT EXISTS feedback (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $queries['admin'] = "CREATE TABLE IF NOT EXISTS admin (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $queries['settings'] = "CREATE TABLE IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        key_name VARCHAR(100) UNIQUE NOT NULL,
        val_value TEXT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
}

// Execute queries to build schema
foreach ($queries as $table => $sql) {
    try {
        $pdo->exec($sql);
        echo "Table '$table' verified/created successfully.\n";
    } catch (PDOException $e) {
        die("Error creating table '$table': " . $e->getMessage() . "\n");
    }
}

// Populate Seeding Data
echo "Seeding default data...\n";

// Clear tables to prevent duplicate seed issues on rerun
$tablesToClear = ['settings', 'coupons', 'categories', 'admin'];
foreach ($tablesToClear as $tbl) {
    $pdo->exec("DELETE FROM $tbl");
}

// Let's clear users, vendors, products, variations, reviews, feedback as well but safely
// For foreign keys, temporarily toggle off or ignore (in SQLite/MySQL delete order)
$pdo->exec("DELETE FROM product_variations");
$pdo->exec("DELETE FROM reviews");
$pdo->exec("DELETE FROM order_items");
$pdo->exec("DELETE FROM orders");
$pdo->exec("DELETE FROM products");
$pdo->exec("DELETE FROM vendors");
$pdo->exec("DELETE FROM users");
$pdo->exec("DELETE FROM feedback");

// 1. Seed admin
$adminPass = password_hash('admin123', PASSWORD_BCRYPT);
$stmt = $pdo->prepare("INSERT INTO admin (id, username, password) VALUES (1, 'admin', ?)");
$stmt->execute([$adminPass]);

// 2. Seed default users
$userPass = password_hash('customer123', PASSWORD_BCRYPT);
$vendor1Pass = password_hash('vendor123', PASSWORD_BCRYPT);
$vendor2Pass = password_hash('vendor123', PASSWORD_BCRYPT);

$stmtUser = $pdo->prepare("INSERT INTO users (id, name, email, password, role) VALUES (?, ?, ?, ?, ?)");
$stmtUser->execute([1, 'System Admin', 'admin@omnimart.com', $adminPass, 'admin']);
$stmtUser->execute([2, 'John Doe', 'customer@omnimart.com', $userPass, 'customer']);
$stmtUser->execute([3, 'Tech Plaza Seller', 'vendor1@omnimart.com', $vendor1Pass, 'vendor']);
$stmtUser->execute([4, 'Fashion Hub Seller', 'vendor2@omnimart.com', $vendor2Pass, 'vendor']);

// 3. Seed vendors
$stmtVendor = $pdo->prepare("INSERT INTO vendors (id, user_id, name, status) VALUES (?, ?, ?, ?)");
$stmtVendor->execute([1, 3, 'Tech Plaza', 'Active']);
$stmtVendor->execute([2, 4, 'Fashion Hub', 'Active']);

// 4. Seed categories
$stmtCat = $pdo->prepare("INSERT INTO categories (id, name) VALUES (?, ?)");
$stmtCat->execute([1, 'Electronics']);
$stmtCat->execute([2, 'Fashion']);
$stmtCat->execute([3, 'Home & Living']);

// 5. Seed products
$stmtProd = $pdo->prepare("INSERT INTO products (id, vendor_id, name, description, price, stock, image, category_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$stmtProd->execute([
    1, 1, 'Premium Wireless Headphones', 
    'Experience crystal clear sound with active noise-canceling headphones. Features 40 hours of playtime and comfortable memory foam earcups.', 
    199.99, 50, 'assets/images/headphones.jpg', 1
]);
$stmtProd->execute([
    2, 2, 'Slim Fit Denim Jacket', 
    'A timeless fashion staple. This blue denim jacket is crafted from 100% premium soft cotton and is perfect for casual outings.', 
    79.99, 30, 'assets/images/jacket.jpg', 2
]);
$stmtProd->execute([
    3, 1, 'Smart Fitness Watch v2', 
    'Stay healthy and connected. Tracks your heart rate, dynamic workouts, sleep cycle, and delivers real-time smartphone notifications.', 
    129.99, 40, 'assets/images/watch.jpg', 1
]);

// 6. Seed variations
$stmtVar = $pdo->prepare("INSERT INTO product_variations (product_id, attribute, value, stock) VALUES (?, ?, ?, ?)");
// Headphones variations
$stmtVar->execute([1, 'Color', 'Black', 25]);
$stmtVar->execute([1, 'Color', 'Silver', 25]);
// Jacket variations
$stmtVar->execute([2, 'Size', 'M', 15]);
$stmtVar->execute([2, 'Size', 'L', 15]);
// Watch variations
$stmtVar->execute([3, 'Color', 'Obsidian', 20]);
$stmtVar->execute([3, 'Color', 'Charcoal', 20]);

// 7. Seed coupons
$stmtCoupon = $pdo->prepare("INSERT INTO coupons (code, discount_type, value, expiry) VALUES (?, ?, ?, ?)");
$stmtCoupon->execute(['WELCOME10', 'percentage', 10.00, '2027-12-31']);
$stmtCoupon->execute(['FLAT20', 'fixed', 20.00, '2027-12-31']);

// 8. Seed settings
$stmtSettings = $pdo->prepare("INSERT INTO settings (key_name, val_value) VALUES (?, ?)");
$stmtSettings->execute(['site_name', 'OmniMart']);
$stmtSettings->execute(['currency', '$']);
$stmtSettings->execute(['theme', 'dark']);
$stmtSettings->execute(['seo_title', 'OmniMart | Full-Stack Premium Multi-Vendor Marketplace']);
$stmtSettings->execute(['seo_description', 'Discover a dynamic shopping portal with products from elite verified vendors. Free shipping on selected items.']);
$stmtSettings->execute(['banner_title', 'Summer Electronics Carnival']);
$stmtSettings->execute(['banner_subtitle', 'Upgrade your setup with up to 40% discount on headphones, smart watches, and premium gear.']);
$stmtSettings->execute(['banner_image', 'assets/images/banner.jpg']);

// 9. Seed mock feedback
$stmtFeedback = $pdo->prepare("INSERT INTO feedback (name, email, message) VALUES (?, ?, ?)");
$stmtFeedback->execute(['Alice Smith', 'alice@example.com', 'Great website design! Simple shopping checkout process.']);

// 10. Seed mock review
$stmtReview = $pdo->prepare("INSERT INTO reviews (product_id, user_id, rating, comment) VALUES (?, ?, ?, ?)");
$stmtReview->execute([1, 2, 5, 'Absolutely incredible sound quality. Best purchase I have made this year!']);

echo "Database initialization and seeding completed successfully!\n";
