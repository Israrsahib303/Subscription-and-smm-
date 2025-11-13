<?php
include '_header.php';

$error = '';
$success = '';
$user_id = $_SESSION['user_id'];

// User ka data fetch karein (API key ke liye)
$stmt_user = $db->prepare("SELECT email, api_key FROM users WHERE id = ?");
$stmt_user->execute([$user_id]);
$user = $stmt_user->fetch();

// --- Handle Password Change ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_pass = $_POST['current_password'];
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];
    
    $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_pass = $stmt->fetch();

    if ($new_pass !== $confirm_pass) {
        $error = 'New passwords do not match.';
    } elseif (strlen($new_pass) < 6) {
        $error = 'New password must be at least 6 characters long.';
    } elseif (password_verify($current_pass, $user_pass['password_hash'])) {
        $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
        $stmt_update = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt_update->execute([$new_hash, $user_id]);
        $success = 'Password changed successfully!';
    } else {
        $error = 'Incorrect current password.';
    }
}

// --- NAYI LOGIC: Handle API Key Generation ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate_api_key'])) {
    try {
        // Ek unique, random, 32-character key banayein
        $new_api_key = bin2hex(random_bytes(16)); 
        
        $stmt_api = $db->prepare("UPDATE users SET api_key = ? WHERE id = ?");
        $stmt_api->execute([$new_api_key, $user_id]);
        
        $user['api_key'] = $new_api_key; // Page par foran show karne ke liye
        $success = 'New API Key generated successfully!';
        
    } catch (PDOException $e) {
        // Agar key pehle se exist karti hai (bohat rare chance)
        if ($e->getCode() == 23000) {
            $error = 'Failed to generate key (collision). Please try again.';
        } else {
            $error = 'An error occurred: ' . $e->getMessage();
        }
    }
}
?>

<h1 class="section-title">My Profile</h1>

<?php if ($error): ?><div class="message error"><?php echo $error; ?></div><?php endif; ?>
<?php if ($success): ?><div class="message success"><?php echo $success; ?></div><?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">

    <div class="checkout-box" style="max-width: 100%; margin: 0;">
        <h2>Change Password</h2>
        <form action="profile.php" method="POST" class="checkout-form">
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
            
            <button type="submit" class="btn btn-primary">Update Password</button>
        </form>
    </div>

    <div class="checkout-box" style="max-width: 100%; margin: 0; background: var(--card-color); border-color: var(--brand-red);">
        <h2 style="color: var(--brand-red);">Reseller API Access</h2>
        <p style="color: var(--text-muted); font-size: 0.9rem;">
            Use this API key to resell our Subscriptions and SMM services on your own panel.
        </p>
        
        <div class="form-group">
            <label for="api_key">Your API Key</label>
            <input type="text" id="api_key" name="api_key" class="form-control" 
                   value="<?php echo sanitize($user['api_key'] ?? 'No key generated yet.'); ?>" readonly 
                   style="background: #111; color: #4CAF50; font-weight: 600;">
        </div>
        
        <div class="form-group">
            <label for="api_url">Your API URL</label>
            <input type="text" id="api_url" name="api_url" class="form-control" 
                   value="<?php echo SITE_URL; ?>/api_v2.php" readonly 
                   style="background: #111;">
        </div>

        <form action="profile.php" method="POST">
            <input type="hidden" name="generate_api_key" value="1">
            <button type="submit" class="btn btn-primary" onclick="return confirm('Are you sure? This will disable your old key.');">
                Generate New Key
            </button>
        </form>
    </div>

</div>

<?php include '_footer.php'; ?>