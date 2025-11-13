<?php
include '_header.php';

$error = '';
$success = '';
$notice = $_GET['notice'] ?? '';

// --- NAYI LOGIC: Ab yeh Email settings ko bhi save kare ga ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_settings'])) {
    
    global $settings; 
    $upload_dir = __DIR__ . '/../assets/img/';
    $current_logo = $settings['site_logo'] ?? 'logo.png'; 

    // Logo Upload Logic
    if (isset($_FILES['site_logo']) && $_FILES['site_logo']['error'] == 0) {
        $file = $_FILES['site_logo'];
        if ($file['size'] > 1 * 1024 * 1024) { $error = 'Logo file is too large (Max 1MB).'; }
        else {
            $allowed_types = ['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml', 'image/gif'];
            $file_type = mime_content_type($file['tmp_name']);
            if (in_array($file_type, $allowed_types)) {
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $new_logo_name = 'logo.' . $ext; 
                if (move_uploaded_file($file['tmp_name'], $upload_dir . $new_logo_name)) {
                    $current_logo = $new_logo_name; 
                    $success = 'Logo updated successfully!';
                } else { $error = 'Failed to upload logo. Check permissions for /assets/img/ folder (should be 755).'; }
            } else { $error = 'Invalid file type (Allowed: png, jpg, webp, svg, gif).'; }
        }
    }
    
    if (empty($error)) { 
        $settings_to_update = [
            'site_name' => sanitize($_POST['site_name'] ?? $settings['site_name']),
            'site_logo' => $current_logo,
            'currency_symbol' => sanitize($_POST['currency_symbol'] ?? $settings['currency_symbol']),
            'whatsapp_number' => sanitize($_POST['whatsapp_number'] ?? $settings['whatsapp_number']),
            'currency_conversion_rate' => sanitize($_POST['currency_conversion_rate'] ?? '280.00'),
            
            // Theme Colors
            'theme_primary' => sanitize($_POST['theme_primary'] ?? $settings['theme_primary']), 
            'theme_hover' => sanitize($_POST['theme_hover'] ?? $settings['theme_hover']),
            'bg_color' => sanitize($_POST['bg_color'] ?? $settings['bg_color']),
            'card_color' => sanitize($_POST['card_color'] ?? $settings['card_color']),
            'text_color' => sanitize($_POST['text_color'] ?? $settings['text_color']),
            'text_muted_color' => sanitize($_POST['text_muted_color'] ?? $settings['text_muted_color']),
            
            // Spin Wheel Settings
            'daily_spin_enabled' => isset($_POST['daily_spin_enabled']) ? '1' : '0',
            'daily_spin_cooldown_hours' => (int)($_POST['daily_spin_cooldown_hours'] ?? 24),

            // NAYI EMAIL (SMTP) SETTINGS
            'smtp_host' => sanitize($_POST['smtp_host'] ?? ''),
            'smtp_port' => sanitize($_POST['smtp_port'] ?? '465'),
            'smtp_user' => sanitize($_POST['smtp_user'] ?? ''),
            'smtp_pass' => sanitize($_POST['smtp_pass'] ?? ''),
            'smtp_secure' => sanitize($_POST['smtp_secure'] ?? 'ssl'),
            'smtp_from_email' => sanitize($_POST['smtp_from_email'] ?? ''),
            'smtp_from_name' => sanitize($_POST['smtp_from_name'] ?? $settings['site_name'])
        ];
        
        try {
            $stmt = $db->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES (?, ?)");
            foreach ($settings_to_update as $key => $value) {
                $stmt->execute([$key, $value]);
                $GLOBALS['settings'][$key] = $value;
            }
            if (empty($success)) { $success = 'Settings updated successfully!'; }
        } catch (PDOException $e) { $error = 'Failed to update settings: ' . $e->getMessage(); }
    }
}

// ... (Password change logic waisa hi hai) ...
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    // ... (code waisa hi) ...
}
?>

<style>
    .color-input {
        transition: background-color 0.3s ease; color: #fff !important; 
        text-shadow: 0 1px 2px rgba(0,0,0,0.5);
    }
</style>

<h1>Site Settings</h1>

<?php if ($error): ?><div class="message error"><?php echo $error; ?></div><?php endif; ?>
<?php if ($success): ?><div class="message success"><?php echo $success; ?></div><?php endif; ?>
<?php if ($notice == 'change_password'): ?>
    <div class="message error" style="background: #a67c00; color: #fff; border-color: #ffc107;">
        <strong>SECURITY:</strong> You are using the default admin password. Please change it immediately.
    </div>
<?php endif; ?>

<div class="form-grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">

    <form action="settings.php" method="POST" class="admin-form" style="max-width: 100%;" enctype="multipart/form-data">
        <h2>General Settings</h2>
        <input type="hidden" name="update_settings" value="1">
        
        <div class="form-group">
            <label for="site_name">Site Name</label>
            <input type="text" id="site_name" name="site_name" class="form-control" value="<?php echo sanitize($GLOBALS['settings']['site_name']); ?>">
        </div>

        <div class="form-group">
            <label for="site_logo">Site Logo (Recommended: PNG, Max 1MB)</label>
            <input type="file" id="site_logo" name="site_logo" class="form-control" accept="image/*">
            <?php if (!empty($GLOBALS['settings']['site_logo'])): ?>
                <p style="margin-top: 10px;">Current Logo: 
                    <img src="../assets/img/<?php echo sanitize($GLOBALS['settings']['site_logo']); ?>?v=<?php echo time(); ?>" 
                         alt="Logo" style="width: 100px; height: auto; background: #555; border-radius: 4px; padding: 5px;">
                </p>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="whatsapp_number">WhatsApp Number (with country code)</label>
            <input type="text" id="whatsapp_number" name="whatsapp_number" class="form-control" value="<?php echo sanitize($GLOBALS['settings']['whatsapp_number']); ?>" placeholder="+923001234567">
        </div>
        
        <h3 style="margin-top: 2rem; margin-bottom: 1rem; border-top: 1px solid var(--admin-border); padding-top: 1rem;">Currency Settings</h3>
        
        <div class="form-grid-2">
            <div class="form-group">
                <label for="currency_symbol">Your Site Currency (e.g., PKR)</label>
                <input type="text" id="currency_symbol" name="currency_symbol" class="form-control" value="<?php echo sanitize($GLOBALS['settings']['currency_symbol']); ?>">
            </div>
            <div class="form-group">
                <label for="currency_conversion_rate">SMM Provider Rate (1 USD = ? PKR)</label>
                <input type="text" id="currency_conversion_rate" name="currency_conversion_rate" class="form-control" 
                       value="<?php echo sanitize($GLOBALS['settings']['currency_conversion_rate'] ?? '280.00'); ?>">
            </div>
        </div>
        
        <h3 style="margin-top: 2rem; margin-bottom: 1rem; border-top: 1px solid var(--admin-border); padding-top: 1rem;">Daily Bonus Spin Wheel</h3>
        <div class="form-group">
            <label>
                <input type="checkbox" name="daily_spin_enabled" value="1" <?php echo ($GLOBALS['settings']['daily_spin_enabled'] ?? '1') == '1' ? 'checked' : ''; ?>>
                Enable Spin & Win
            </label>
        </div>
        <div class="form-group">
            <label for="daily_spin_cooldown_hours">Cooldown (Hours between spins)</label>
            <input type="number" id="daily_spin_cooldown_hours" name="daily_spin_cooldown_hours" class="form-control" 
                   value="<?php echo sanitize($GLOBALS['settings']['daily_spin_cooldown_hours'] ?? '24'); ?>">
        </div>
        <p><a href="wheel_prizes.php" class="btn-edit" style="text-decoration: none;">Manage Wheel Prizes & Chances</a></p>


        <h3 style="margin-top: 2rem; margin-bottom: 1rem; border-top: 1px solid var(--admin-border); padding-top: 1rem;">Theme Colors</h3>
        <h3 style="margin-top: 2rem; margin-bottom: 1rem; border-top: 1px solid var(--admin-border); padding-top: 1rem;">Email (SMTP) Settings</h3>
        <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: -1rem; margin-bottom: 1rem;">
            Yeh "Forgot Password" aur "Order Notifications" ke liye istemal hon gi. cPanel (Webmail) ya Gmail ki settings daalein.
        </p>
        
        <div class="form-group">
            <label for="smtp_host">SMTP Host (e.g., mail.test.israrliaqat.shop)</label>
            <input type="text" id="smtp_host" name="smtp_host" class="form-control" value="<?php echo sanitize($GLOBALS['settings']['smtp_host'] ?? ''); ?>">
        </div>
        
        <div class="form-grid-2">
            <div class="form-group">
                <label for="smtp_port">SMTP Port (e.g., 465)</label>
                <input type="text" id="smtp_port" name="smtp_port" class="form-control" value="<?php echo sanitize($GLOBALS['settings']['smtp_port'] ?? '465'); ?>">
            </div>
            <div class="form-group">
                <label for="smtp_secure">Security (ssl / tls)</label>
                <input type="text" id="smtp_secure" name="smtp_secure" class="form-control" value="<?php echo sanitize($GLOBALS['settings']['smtp_secure'] ?? 'ssl'); ?>">
            </div>
        </div>

        <div class="form-group">
            <label for="smtp_user">SMTP Username (e.g., support@test.israrliaqat.shop)</label>
            <input type="text" id="smtp_user" name="smtp_user" class="form-control" value="<?php echo sanitize($GLOBALS['settings']['smtp_user'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label for="smtp_pass">SMTP Password</label>
            <input type="password" id="smtp_pass" name="smtp_pass" class="form-control" value="<?php echo sanitize($GLOBALS['settings']['smtp_pass'] ?? ''); ?>">
        </div>
        
        <div class="form-grid-2">
            <div class="form-group">
                <label for="smtp_from_email">From Email (Aap ka email)</label>
                <input type="text" id="smtp_from_email" name="smtp_from_email" class="form-control" value="<?php echo sanitize($GLOBALS['settings']['smtp_from_email'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="smtp_from_name">From Name (Aap ki site ka naam)</label>
                <input type="text" id="smtp_from_name" name="smtp_from_name" class="form-control" value="<?php echo sanitize($GLOBALS['settings']['smtp_from_name'] ?? ''); ?>">
            </div>
        </div>
        <button type="submit" class="btn btn-primary" style="margin-top: 2rem;">Save Settings</button>
    </form>
    
    <form action="settings.php" method="POST" class="admin-form" style="max-width: 100%;">
        <h2>Change Admin Password</h2>
        <input type="hidden" name="change_password" value="1">
        <div class="form-group">
            <label for="current_password">Current Password</label>
            <input type="password" id="current_password" name="current_password" class="form-control" required>
        </div>
        <div class="form-group">
            <label for="new_password">New Password</label>
            <input type="password" id="new_password" name="new_password" class="form-control" required>
        </div>
        <div class="form-group">
            <label for="confirm_password">Confirm New Password</label>
            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
        </div>
        
        <button type="submit" class="btn btn-primary">Change Password</button>
    </form>

</div>

<script>
// ... (Color input wala script waisa hi rahe ga) ...
document.addEventListener('DOMContentLoaded', function() {
    const colorInputs = document.querySelectorAll('.color-input');
    function updateColorPreview(input) {
        if(input.value) input.style.backgroundColor = input.value;
    }
    colorInputs.forEach(function(input) {
        updateColorPreview(input);
        input.addEventListener('input', function() { updateColorPreview(this); });
    });
});
</script>

<?php include '_footer.php'; ?>