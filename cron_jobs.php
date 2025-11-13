<?php
include '_header.php';

// --- NAYI "BEAST" LOGIC: Auto-Detect Paths ---

// 1. PHP Executable ka path (cPanel par 99% yehi hota hai)
$php_path = '/usr/local/bin/php';

// 2. Aap ki website ka server par mukammal path (e.g., /home/israrlia/test.israrliaqat.shop)
$base_path = dirname(__DIR__); // "panel" folder se ek folder upar

// 3. Tamam cron commands ko tayyar karein
$cron_jobs = [
    [
        'title' => 'NayaPay Email Parser (Auto-Payment)',
        'description' => 'Har 1 minute mein NayaPay ki payment emails ko check karta hai aur database mein add karta hai.',
        'frequency' => 'Once per minute (* * * * *)',
        'command' => $php_path . ' ' . $base_path . '/includes/cron/check_email.php'
    ],
    [
        'title' => 'SMM Order Placer (Automation)',
        'description' => 'Har 1 minute mein "Pending" SMM orders ko provider ki API par bhejta hai.',
        'frequency' => 'Once per minute (* * * * *)',
        'command' => $php_path . ' ' . $base_path . '/includes/cron/smm_order_placer.php'
    ],
    [
        'title' => 'SMM Status Sync (Auto-Refund)',
        'description' => 'Har 5 minute mein "In Progress" orders ka status check karta hai aur (Partial/Cancelled) ko auto-refund karta hai.',
        'frequency' => 'Once per 5 minutes (*/5 * * * *)',
        'command' => $php_path . ' ' . $base_path . '/includes/cron/smm_status_sync.php'
    ],
    [
        'title' => 'Subscription Expiry Check',
        'description' => 'Har ghantay chalta hai aur purani (expired) Subscriptions ko "Expired" mark karta hai.',
        'frequency' => 'Once per hour (0 * * * *)',
        'command' => $php_path . ' ' . $base_path . '/includes/cron/expire_subscriptions.php'
    ],
    [ // --- YEH RAHA NAYA FEATURE JO AAP NE ADD KARWAYA ---
        'title' => 'SMM Service Sync (Auto-Price Update)',
        'description' => 'Har raat 3 baje chalta hai. Provider ki tamam services/prices ko aap ke profit % ke saath sync karta hai.',
        'frequency' => 'Once per day (0 3 * * *)',
        'command' => $php_path . ' ' . $base_path . '/includes/cron/smm_service_sync.php'
    ]
];
?>

<style>
.cron-card {
    background: var(--card-color);
    border: 1px solid var(--card-border);
    border-radius: var(--radius);
    margin-bottom: 1.5rem;
    box-shadow: var(--shadow);
}
.cron-card-header {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--card-border);
}
.cron-card-header h3 {
    margin: 0;
    color: var(--text-color);
}
.cron-card-body {
    padding: 1.5rem;
}
.cron-card-body p {
    font-size: 0.9rem;
    color: var(--text-muted);
    margin-top: 0;
    margin-bottom: 0.5rem;
}
.cron-card-body strong {
    color: var(--text-color);
}
.cron-command-box {
    display: flex;
    background: #111;
    border-radius: 4px;
    margin-top: 1rem;
}
.cron-command-box input {
    flex-grow: 1;
    background: transparent;
    border: none;
    color: #4CAF50; /* Green command */
    padding: 0.8rem;
    font-family: monospace;
    font-size: 0.9rem;
}
.copy-btn {
    background: var(--brand-red);
    color: #fff;
    border: none;
    padding: 0 1rem;
    cursor: pointer;
    font-weight: 600;
    transition: background 0.2s ease;
}
.copy-btn:hover {
    background: var(--brand-red-hover);
}
</style>

<h1>Cron Job Manager</h1>
<p class="description">
    Yeh aap ki website ke tamam automatic tasks hain. Inhein chalaane ke liye, neeche di gayi commands ko copy kar ke apne cPanel "Cron Jobs" section mein (Frequency ke mutabiq) paste karein.
</p>

<?php if (isset($_GET['copied'])): ?>
    <div class="message success">Command copied to clipboard!</div>
<?php endif; ?>

<?php foreach ($cron_jobs as $job): ?>
<div class="cron-card">
    <div class="cron-card-header">
        <h3><?php echo $job['title']; ?></h3>
    </div>
    <div class="cron-card-body">
        <p><?php echo $job['description']; ?></p>
        <p><strong>Recommended Frequency:</strong> <?php echo $job['frequency']; ?></p>
        
        <label style="font-weight: 600; font-size: 0.9rem;">cPanel Command:</label>
        <div class="cron-command-box">
            <input type="text" value="<?php echo $job['command']; ?>" readonly onclick="copyToClipboard(this)">
            <button class="copy-btn" onclick="copyToClipboard(this.previousElementSibling)">Copy</button>
        </div>
    </div>
</div>
<?php endforeach; ?>

<script>
function copyToClipboard(inputElement) {
    inputElement.select();
    inputElement.setSelectionRange(0, 99999); // For mobile devices
    
    try {
        document.execCommand('copy');
        // Page reload karein taake "copied" message dikhe
        window.location.href = 'cron_jobs.php?copied=true';
    } catch (err) {
        alert('Failed to copy command. Please copy it manually.');
    }
}
</script>

<?php include '_footer.php'; ?>