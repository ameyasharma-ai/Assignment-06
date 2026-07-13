<?php
// login.php - Unified Login Portal
require_once __DIR__ . '/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin/dashboard.php");
    } elseif ($_SESSION['role'] === 'vendor') {
        header("Location: vendor/dashboard.php");
    } else {
        header("Location: index.php");
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        $db = getDBConnection();
        // Check users table
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Set basic user session keys
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];

            // Role-specific routing and state setup
            if ($user['role'] === 'admin') {
                header("Location: admin/dashboard.php");
                exit;
            } elseif ($user['role'] === 'vendor') {
                // Fetch vendor details
                $stmtVendor = $db->prepare("SELECT * FROM vendors WHERE user_id = ?");
                $stmtVendor->execute([$user['id']]);
                $vendor = $stmtVendor->fetch();

                if (!$vendor) {
                    $error = 'Associated vendor profile not found.';
                    session_destroy();
                } elseif ($vendor['status'] === 'Pending') {
                    $error = 'Your merchant account is pending approval by the administrator.';
                    session_destroy();
                } elseif ($vendor['status'] === 'Suspended') {
                    $error = 'Your merchant account has been suspended. Contact support.';
                    session_destroy();
                } else {
                    $_SESSION['vendor_id'] = $vendor['id'];
                    $_SESSION['vendor_name'] = $vendor['name'];
                    header("Location: vendor/dashboard.php");
                    exit;
                }
            } else {
                // Normal Customer Login
                header("Location: index.php");
                exit;
            }
        } else {
            // Check admin table specifically if email matches username (optional fallback check requested by schema)
            $stmtAdmin = $db->prepare("SELECT * FROM admin WHERE username = ?");
            $stmtAdmin->execute([$email]);
            $admin = $stmtAdmin->fetch();

            if ($admin && password_verify($password, $admin['password'])) {
                $_SESSION['user_id'] = $admin['id'];
                $_SESSION['name'] = 'System Administrator';
                $_SESSION['email'] = 'admin@omnimart.com';
                $_SESSION['role'] = 'admin';
                header("Location: admin/dashboard.php");
                exit;
            } else {
                $error = 'Invalid email or password.';
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="glass-panel form-card">
    <h2 style="text-align: center; margin-bottom: 24px; font-size: 2rem; background: linear-gradient(135deg, #a78bfa, #0ea5e9); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
        Sign In
    </h2>

    <?php if (!empty($error)): ?>
        <div class="badge badge-danger" style="display: block; padding: 12px; margin-bottom: 20px; border-radius: 8px;">
            <i class="fa-solid fa-circle-exclamation"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <form action="login.php" method="POST">
        <div class="form-group">
            <label for="email">Email Address / Username</label>
            <input type="text" name="email" id="email" class="form-control" required placeholder="e.g. customer@omnimart.com">
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" name="password" id="password" class="form-control" required placeholder="••••••••">
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 16px;">
            Sign In
        </button>

        <p style="text-align: center; margin-top: 20px; color: var(--text-muted); font-size: 0.9rem;">
            New to OmniMart? <a href="signup.php" style="color: var(--primary-light); font-weight: 600;">Create account</a>
        </p>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
