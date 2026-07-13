<?php
// admin/index.php - Administrative Login Portal
require_once __DIR__ . '/../db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if already logged in as admin
if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'admin') {
    header("Location: dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'All fields are required.';
    } else {
        $db = getDBConnection();
        // Query admin table
        $stmt = $db->prepare("SELECT * FROM admin WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['user_id'] = $admin['id'];
            $_SESSION['name'] = 'System Administrator';
            $_SESSION['email'] = 'admin@omnimart.com';
            $_SESSION['role'] = 'admin';
            header("Location: dashboard.php");
            exit;
        } else {
            // Also check users table for role admin
            $stmtUser = $db->prepare("SELECT * FROM users WHERE email = ? AND role = 'admin'");
            $stmtUser->execute([$username]);
            $user = $stmtUser->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = 'admin';
                header("Location: dashboard.php");
                exit;
            } else {
                $error = 'Invalid administrative credentials.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Control Login | OmniMart</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: radial-gradient(circle at 10% 20%, rgba(139, 92, 246, 0.1) 0%, transparent 40%),
                        radial-gradient(circle at 90% 80%, rgba(6, 182, 212, 0.1) 0%, transparent 40%),
                        var(--bg-base);
        }
        .login-card {
            width: 100%;
            max-width: 400px;
            background: var(--bg-surface);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4);
        }
    </style>
</head>
<body>
    <div class="login-card">
        <h2 style="text-align: center; margin-bottom: 8px; font-family: 'Outfit', sans-serif;">OmniMart Admin</h2>
        <p style="text-align: center; color: var(--text-muted); font-size: 0.9rem; margin-bottom: 24px;">Secure Control Panel Authentication</p>

        <?php if (!empty($error)): ?>
            <div style="background: rgba(239, 68, 68, 0.15); color: var(--danger); padding: 12px; border-radius: 6px; font-size: 0.85rem; margin-bottom: 20px; font-weight: 500; text-align: center;">
                <i class="fa-solid fa-circle-exclamation"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form action="index.php" method="POST">
            <div class="admin-form-group">
                <label for="username">Admin Username / Email</label>
                <input type="text" name="username" id="username" class="admin-form-control" required placeholder="e.g. admin" autofocus>
            </div>

            <div class="admin-form-group">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" class="admin-form-control" required placeholder="••••••••">
            </div>

            <button type="submit" class="btn-admin btn-admin-primary" style="width: 100%; justify-content: center; margin-top: 12px; height: 44px;">
                <i class="fa-solid fa-lock"></i> Authorize Access
            </button>
        </form>
        
        <div style="text-align: center; margin-top: 24px; font-size: 0.85rem;">
            <a href="../index.php" style="color: var(--text-muted); transition: var(--transition);"><i class="fa-solid fa-arrow-left-long"></i> Back to Storefront</a>
        </div>
    </div>
</body>
</html>
