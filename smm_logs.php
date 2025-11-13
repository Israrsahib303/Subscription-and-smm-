<?php
include '_header.php';

// Log files ke paths (jo hum ne cron jobs mein set kiye the)
$log_files = [
    'Order Placer Log' => '../../assets/logs/smm_order_placer.log',
    'Status Sync Log' => '../../assets/logs/smm_status_sync.log',
    'Email Parser Log (NayaPay)' => '../../assets/logs/email_cron.log',
    'Service Sync Log (Nightly)' => '../../assets/logs/smm_service_sync.log' // NAYA LOG
];

$selected_log_name = key($log_files);

// Agar user ne URL mein koi specific log select kiya hai
if (isset($_GET['log']) && array_key_exists($_GET['log'], $log_files)) {
    $selected_log_name = $_GET['log'];
}

$selected_log_path = $log_files[$selected_log_name];
$log_content = '';
$error = '';
$success = ''; 

// Log clear karne ki logic
if (isset($_GET['clear']) && $_GET['clear'] === 'true') {
    if (file_exists($selected_log_path) && is_writable($selected_log_path)) {
        file_put_contents($selected_log_path, "Log cleared by Admin at " . date('Y-m-d H:i:s') . "\n--------------------------------\n");
        $success = "Log file '$selected_log_name' has been cleared.";
    } else {
        $error = "Error: Log file is not writable or does not exist. Check file permissions.";
    }
}


if (file_exists($selected_log_path)) {
    $file_lines = file($selected_log_path);
    if ($file_lines !== false) {
        $last_lines = array_slice($file_lines, -500); 
        $log_content = implode('', array_reverse($last_lines));
    } else {
        $error = "Error: Could not read file: " . basename($selected_log_path);
    }
} else {
    $error = "Log file not found at: " . $selected_log_path . ". Cron job chalne ke baad yeh automatically ban jaye gi.";
}
?>

<style>
.log-switcher {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 20px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--admin-border-color);
}
.log-switcher a {
    text-decoration: none;
    padding: 10px 15px;
    border-radius: 5px;
    color: #fff;
    background: #555;
    font-weight: 600;
    font-size: 0.9rem;
}
.log-switcher a.active {
    background: var(--brand-red);
}
.log-switcher a.btn-delete {
    background: #c0392b;
    margin-left: auto;
}
.log-content-container {
    background-color: #2b2b2b;
    color: #f0f0f0;
    padding: 15px;
    border-radius: 5px;
    max-height: 70vh;
    overflow: auto;
    font-family: monospace;
    white-space: pre-wrap;
    line-height: 1.4;
    border: 1px solid #444;
}
</style>

<h1>SMM System Diagnostics & Logs</h1>
<p class="description">Yahan aap tamam automated (cron job) scripts ke logs dekh sakte hain.</p>

<?php if ($error): ?><div class="message error"><?php echo $error; ?></div><?php endif; ?>
<?php if ($success): ?><div class="message success"><?php echo $success; ?></div><?php endif; ?>

<div class="log-switcher">
    <?php foreach ($log_files as $name => $path): ?>
        <a href="?log=<?php echo urlencode($name); ?>" 
           class="<?php echo ($name == $selected_log_name) ? 'active' : ''; ?>">
            <?php echo $name; ?>
        </a>
    <?php endforeach; ?>
    
    <a href="?log=<?php echo urlencode($selected_log_name); ?>&clear=true" 
       onclick="return confirm('Are you sure you want to clear this log file?');"
       class="btn-delete">
        Clear Log
    </a>
</div>

<h2><?php echo sanitize($selected_log_name); ?> (Latest entries on top)</h2>

<div class="log-content-container">
    <?php if (!empty($log_content)): ?>
        <?php echo nl2br(sanitize($log_content)); ?>
    <?php else: ?>
        <p style="color: #ccc;">Log file is empty or could not be loaded. Please ensure your cron jobs are set up and running.</p>
    <?php endif; ?>
</div>

<?php include '_footer.php'; ?>