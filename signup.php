<?php
// signup.php - Customer/Vendor Registration
require_once __DIR__ . '/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'customer'; // 'customer' or 'vendor'
    $store_name = trim($_POST['store_name'] ?? '');

    if (empty($name) || empty($email) || empty($password)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($role === 'vendor' && empty($store_name)) {
        $error = 'Please specify your vendor/store name.';
    } else {
        $db = getDBConnection();
        // Check if email already exists
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Email is already registered. Try logging in instead.';
        } else {
            try {
                $db->beginTransaction();
                // Hash password
                $hashed_pass = password_hash($password, PASSWORD_BCRYPT);
                
                // Insert User
                $stmtInsertUser = $db->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
                $stmtInsertUser->execute([$name, $email, $hashed_pass, $role]);
                $user_id = $db->lastInsertId();

                // If Vendor, create vendor profile as Pending
                if ($role === 'vendor') {
                    $stmtInsertVendor = $db->prepare("INSERT INTO vendors (user_id, name, status) VALUES (?, ?, ?)");
                    $stmtInsertVendor->execute([$user_id, $store_name, 'Pending']);
                }

                $db->commit();
                $success = 'Account created successfully! You can now log in.';
            } catch (Exception $e) {
                $db->rollBack();
                $error = 'Registration failed: ' . $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="glass-panel form-card">
    <h2 style="text-align: center; margin-bottom: 24px; font-size: 2rem; background: linear-gradient(135deg, #a78bfa, #0ea5e9); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
        Join OmniMart
    </h2>

    <?php if (!empty($error)): ?>
        <div class="badge badge-danger" style="display: block; padding: 12px; margin-bottom: 20px; border-radius: 8px;">
            <i class="fa-solid fa-triangle-exclamation"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="badge badge-success" style="display: block; padding: 12px; margin-bottom: 20px; border-radius: 8px;">
            <i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <form action="signup.php" method="POST" id="signup-form">
        <div class="form-group">
            <label for="name">Full Name <span style="color: var(--danger);">*</span></label>
            <input type="text" name="name" id="name" class="form-control" required placeholder="e.g. John Doe">
        </div>

        <div class="form-group">
            <label for="email">Email Address <span style="color: var(--danger);">*</span></label>
            <input type="email" name="email" id="email" class="form-control" required placeholder="e.g. john@example.com">
        </div>

        <div class="form-group">
            <label for="password">Password <span style="color: var(--danger);">*</span></label>
            <input type="password" name="password" id="password" class="form-control" required placeholder="••••••••">
        </div>

        <div class="form-group">
            <label for="role">Register As</label>
            <select name="role" id="role" class="form-control" onchange="toggleVendorField(this.value)">
                <option value="customer" <?php echo (isset($_GET['role']) && $_GET['role'] === 'vendor') ? '' : 'selected'; ?>>Customer</option>
                <option value="vendor" <?php echo (isset($_GET['role']) && $_GET['role'] === 'vendor') ? 'selected' : ''; ?>>Merchant / Seller</option>
            </select>
        </div>

        <!-- Hidden field for store name (visible only if registering as vendor) -->
        <div class="form-group" id="vendor-store-group" style="<?php echo (isset($_GET['role']) && $_GET['role'] === 'vendor') ? '' : 'display: none;'; ?>">
            <label for="store_name">Store / Brand Name <span style="color: var(--danger);">*</span></label>
            <input type="text" name="store_name" id="store_name" class="form-control" placeholder="e.g. Gizmo Labs">
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 16px;">
            Create Account
        </button>

        <p style="text-align: center; margin-top: 20px; color: var(--text-muted); font-size: 0.9rem;">
            Already have an account? <a href="login.php" style="color: var(--primary-light); font-weight: 600;">Sign In</a>
        </p>
    </form>
</div>

<script>
function toggleVendorField(role) {
    const storeGroup = document.getElementById('vendor-store-group');
    const storeInput = document.getElementById('store_name');
    if (role === 'vendor') {
        storeGroup.style.display = 'block';
        storeInput.required = true;
    } else {
        storeGroup.style.display = 'none';
        storeInput.required = false;
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
