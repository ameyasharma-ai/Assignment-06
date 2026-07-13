<?php
// admin/settings.php - Dynamic Site & SEO Configuration Settings
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/admin_header.php';

$db = getDBConnection();

// Check administrative access
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: index.php");
    exit;
}

$error = '';
$success = '';

// Handle POST Save Configurations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $siteName = trim($_POST['site_name'] ?? '');
    $currency = trim($_POST['currency'] ?? '$');
    $theme = $_POST['theme'] ?? 'dark';
    $seoTitle = trim($_POST['seo_title'] ?? '');
    $seoDescription = trim($_POST['seo_description'] ?? '');
    $bannerTitle = trim($_POST['banner_title'] ?? '');
    $bannerSubtitle = trim($_POST['banner_subtitle'] ?? '');

    if (empty($siteName) || empty($currency)) {
        $error = 'Site Name and Currency Symbol cannot be blank.';
    } else {
        try {
            $db->beginTransaction();
            
            $stmtUpdate = $db->prepare("UPDATE settings SET val_value = ? WHERE key_name = ?");
            
            $stmtUpdate->execute([$siteName, 'site_name']);
            $stmtUpdate->execute([$currency, 'currency']);
            $stmtUpdate->execute([$theme, 'theme']);
            $stmtUpdate->execute([$seoTitle, 'seo_title']);
            $stmtUpdate->execute([$seoDescription, 'seo_description']);
            $stmtUpdate->execute([$bannerTitle, 'banner_title']);
            $stmtUpdate->execute([$bannerSubtitle, 'banner_subtitle']);

            $db->commit();
            $success = 'Configuration settings saved successfully!';
            
            // Reload settings for current script execution
            $settings['site_name'] = $siteName;
            $settings['currency'] = $currency;
            $settings['theme'] = $theme;
            $settings['seo_title'] = $seoTitle;
            $settings['seo_description'] = $seoDescription;
            $settings['banner_title'] = $bannerTitle;
            $settings['banner_subtitle'] = $bannerSubtitle;

        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Configuration update failed: ' . $e->getMessage();
        }
    }
}

// Read current settings
$currentSettings = [];
try {
    $stmt = $db->query("SELECT key_name, val_value FROM settings");
    while ($row = $stmt->fetch()) {
        $currentSettings[$row['key_name']] = $row['val_value'];
    }
} catch (Exception $e) {}
?>

<?php if (!empty($error)): ?>
    <div style="background: rgba(239, 68, 68, 0.15); color: var(--danger); padding: 12px; border-radius: 6px; margin-bottom: 20px; font-weight: 500;">
        <i class="fa-solid fa-triangle-exclamation"></i> <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div style="background: rgba(16, 185, 129, 0.15); color: var(--success); padding: 12px; border-radius: 6px; margin-bottom: 20px; font-weight: 500;">
        <i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($success); ?>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
    <!-- Site Settings Form Card -->
    <div class="admin-card">
        <h3>Platform Configuration settings</h3>
        
        <form action="settings.php" method="POST" style="margin-top: 20px;">
            <input type="hidden" name="save_settings" value="1">
            
            <!-- Site Identity -->
            <h4 style="margin-bottom: 12px; border-bottom: 1px solid var(--border-color); padding-bottom: 6px; color: var(--primary-light);"><i class="fa-solid fa-signature"></i> Identity & Core System</h4>
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div class="admin-form-group" style="margin-bottom:0;">
                    <label for="site_name">Marketplace Brand Name</label>
                    <input type="text" name="site_name" id="site_name" class="admin-form-control" required value="<?php echo htmlspecialchars($currentSettings['site_name'] ?? 'OmniMart'); ?>">
                </div>
                <div class="admin-form-group" style="margin-bottom:0;">
                    <label for="currency">Currency Symbol</label>
                    <input type="text" name="currency" id="currency" class="admin-form-control" required value="<?php echo htmlspecialchars($currentSettings['currency'] ?? '$'); ?>">
                </div>
            </div>

            <div class="admin-form-group" style="margin-bottom: 24px;">
                <label for="theme">Default Interface Theme Color</label>
                <select name="theme" id="theme" class="admin-form-control">
                    <option value="dark" <?php echo ($currentSettings['theme'] ?? 'dark') === 'dark' ? 'selected' : ''; ?>>Dark Theme (Curated Premium Mode)</option>
                    <option value="light" <?php echo ($currentSettings['theme'] ?? 'dark') === 'light' ? 'selected' : ''; ?>>Light Theme (High Contrast Mode)</option>
                </select>
            </div>

            <!-- Homepage Banners -->
            <h4 style="margin-bottom: 12px; border-bottom: 1px solid var(--border-color); padding-bottom: 6px; color: var(--primary-light);"><i class="fa-solid fa-rectangle-ad"></i> Storefront Landing Page Banner</h4>
            <div class="admin-form-group">
                <label for="banner_title">Main Banner Headline Text</label>
                <input type="text" name="banner_title" id="banner_title" class="admin-form-control" value="<?php echo htmlspecialchars($currentSettings['banner_title'] ?? ''); ?>">
            </div>
            
            <div class="admin-form-group" style="margin-bottom: 24px;">
                <label for="banner_subtitle">Banner Subtitle / Promotional Text</label>
                <textarea name="banner_subtitle" id="banner_subtitle" rows="2" class="admin-form-control"><?php echo htmlspecialchars($currentSettings['banner_subtitle'] ?? ''); ?></textarea>
            </div>

            <!-- SEO Configuration -->
            <h4 style="margin-bottom: 12px; border-bottom: 1px solid var(--border-color); padding-bottom: 6px; color: var(--primary-light);"><i class="fa-solid fa-magnifying-glass-chart"></i> Search Engine Optimization (SEO)</h4>
            <div class="admin-form-group">
                <label for="seo_title">Site SEO Title</label>
                <input type="text" name="seo_title" id="seo_title" class="admin-form-control" value="<?php echo htmlspecialchars($currentSettings['seo_title'] ?? ''); ?>">
            </div>
            
            <div class="admin-form-group" style="margin-bottom: 24px;">
                <label for="seo_description">Meta Description Tag</label>
                <textarea name="seo_description" id="seo_description" rows="3" class="admin-form-control"><?php echo htmlspecialchars($currentSettings['seo_description'] ?? ''); ?></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end;">
                <button type="submit" class="btn-admin btn-admin-primary"><i class="fa-solid fa-save"></i> Save Site Configuration</button>
            </div>
        </form>
    </div>

    <!-- Sitemap Trigger Card -->
    <div class="admin-card" style="height: fit-content;">
        <h3>SEO Site XML Sitemap</h3>
        <p style="color: var(--text-muted); font-size: 0.8rem; margin-top: 10px; line-height: 1.5;">
            Dynamic XML sitemaps index catalog listings, category routes, and static pages for crawl search indexers.
        </p>
        
        <div style="background: rgba(139, 92, 246, 0.08); border: 1px solid var(--border-color); padding: 16px; border-radius: 8px; margin-top: 20px; font-size: 0.85rem; line-height: 1.4;">
            <i class="fa-solid fa-circle-info" style="color: var(--primary-light); margin-right: 6px;"></i> Active: <strong>Live Generator</strong>
            <div style="margin-top: 8px;">
                XML sitemap runs query hooks on-the-fly when read by engines.
            </div>
        </div>

        <a href="../sitemap.php" target="_blank" class="btn-admin btn-admin-secondary" style="width: 100%; justify-content: center; margin-top: 20px; height: 38px;">
            <i class="fa-solid fa-arrow-up-right-from-square"></i> Open XML Sitemap
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
