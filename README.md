# OmniMart - Full-Stack Dynamic Multi-Vendor E-Commerce Platform

OmniMart is a premium, full-stack e-commerce web application inspired by professional marketplaces. It features a responsive customer-facing storefront, a restricted vendor management desk, and a comprehensive administration dashboard. 

The platform runs on a dual-driver PDO system supporting both SQLite and MySQL, with full Docker container compatibility for modern cloud deployments.

---

## 🌟 Key Features

### 🛒 Customer Storefront
*   **Dynamic Landing Page:** Features scrolling promotion banners, dynamic categories, and featured grids loaded directly from the database.
*   **Interactive Product Details:** Supports options for color and size variations with real-time stock counters, customer reviews (ratings & text feedback), and public Q&A threads.
*   **Shopping Cart & Checkout:** AJAX-powered bag update and wishlist toggles, tax/shipping logic, and coupon campaign validation (e.g. `WELCOME10` code). Supports mock Stripe, Razorpay, and COD checkout routes.
*   **Customer Profiles:** Contains comprehensive order transaction log listings, custom product wishlist grids, and cookie-based recently viewed items.
*   **Search Engine & Sitemap:** Advanced sidebar filtering (by category, price ranges, and review scores) alongside a dynamically generated XML SEO sitemap.

### 👔 Merchant Vendor Panel
*   **Isolated Statistics Dashboard:** Vendor-specific analytics dashboard showing total earnings, monthly sales counters, and item grids.
*   **Catalog CRUD & Uploads:** Add/edit products, manage specific variation sizes and quantities, and upload images.
*   **Merchant Shipping Logs:** Restricts order item listings to only display transactions tied to the logged-in vendor's products.

### 🛡️ Administrative Command Center
*   **Global KPI Summaries:** Aggregated site revenue charts, vendor counts, and recent order listings.
*   **Product Catalog & CSV Operations:** Manage all marketplace listings, with support for bulk import/export of products using CSV spreadsheets.
*   **Campaign Coupons Engine:** Define flat-rate or percentage-off promo campaigns.
*   **Site Configuration Settings:** Control the brand name, currency symbol, default theme toggles, and metadata tags from a central UI.

### 🔌 REST API Endpoints
*   Token-secured API endpoints (`api/products.php` & `api/orders.php`) for programmatic catalog and sales integration, authenticated via `Authorization: Bearer omnimart_api_token_secure_123` header.

---

## 🛠️ Technology Stack
*   **Backend:** PHP 8.x (with PDO driver extension)
*   **Database:** Dual-driver compatibility:
    *   **SQLite** (`omnimart.db` for local sandboxed zero-install testing)
    *   **MySQL** (Aiven MySQL for production cloud deployments)
*   **Frontend:** Semantic HTML5, Vanilla JavaScript, and Vanilla CSS (built responsive-first with dark/light theme options).
*   **Infrastructure:** Docker, Render Web Services deployment.

---

## 🚀 How to Run Locally

1.  **Start the Web Server:**
    Ensure you have PHP 8.x installed, then launch the built-in development server from the project directory:
    ```bash
    php -S localhost:8082
    ```
2.  **Initialize the Database:**
    Open your browser and navigate to the database setup script to compile the schema and seed default data:
    ```url
    http://localhost:8082/init_db.php
    ```
3.  **Explore the App:**
    *   Storefront homepage: [http://localhost:8082/](http://localhost:8082/)
    *   Admin portal: [http://localhost:8082/admin/](http://localhost:8082/admin/) (Credentials: `admin` / `admin123`)
    *   Customer Account: `customer@omnimart.com` / `customer123`
    *   Vendor Account: `vendor1@omnimart.com` / `vendor123`
