<?php
include '_header.php';
// Nayi API class ko include karein
require_once __DIR__ . '/../includes/smm_api.class.php';

$action = $_GET['action'] ?? 'list';
$error = '';
$success = '';

// --- NAYI LOGIC: API SYNC (Aap ki request ke mutabiq) ---
if ($action == 'sync') {
    try {
        // 1. Active provider ko fetch karein
        $stmt_provider = $db->prepare("SELECT * FROM smm_providers WHERE is_active = 1 LIMIT 1");
        $stmt_provider->execute();
        $provider = $stmt_provider->fetch();

        if (!$provider) {
            $error = 'No active API provider found. Please add one in "API Providers" first.';
        } else {
            // 2. Settings se Currency Rate uthayein
            $usd_to_pkr_rate = (float)($GLOBALS['settings']['currency_conversion_rate'] ?? 280.00);
            
            // 3. API class ko call karein
            $api = new SmmApi($provider['api_url'], $provider['api_key']);
            $result = $api->getServices();
            
            if (!$result['success'] || !is_array($result['services'])) {
                $error = 'Failed to fetch services from API. Provider said: ' . ($result['error'] ?? 'Invalid response');
            } else {
                $provider_id = $provider['id'];
                $profit_margin = (float)$provider['profit_margin'];
                $services = $result['services'];
                
                $db->beginTransaction();
                
                // 3. Purani services ko 'disable' mark karein (taake jo remove ho gayi hain, woh hat jayein)
                $db->prepare("UPDATE smm_services SET is_active = 0 WHERE provider_id = ?")
                   ->execute([$provider_id]);

                $imported_count = 0;
                $updated_count = 0;

                // 4. Har service ko check/update/insert karein
                foreach ($services as $service) {
                    if (empty($service['service']) || empty($service['name']) || !isset($service['rate'])) {
                        continue; // Ghalat data ko ignore karein
                    }

                    $service_id = (int)$service['service'];
                    $name = sanitize($service['name']);
                    $category = sanitize($service['category']);
                    
                    // --- NAYI CURRENCY/PROFIT LOGIC ---
                    $base_price_usd = (float)$service['rate']; // Provider ki price (USD)
                    $base_price_pkr = $base_price_usd * $usd_to_pkr_rate; // PKR mein convert
                    $service_rate_pkr = $base_price_pkr + ($base_price_pkr * ($profit_margin / 100)); // 50% profit
                    // --- LOGIC KHATAM ---
                    
                    $min = (int)$service['min'];
                    $max = (int)$service['max'];
                    $has_refill = (isset($service['refill']) && $service['refill'] == 1) ? 1 : 0;
                    $has_cancel = (isset($service['cancel']) && $service['cancel'] == 1) ? 1 : 0;
                    $avg_time = sanitize($service['average_time'] ?? null); // Average Time

                    // 5. Check karein ke service pehle se hai ya nahi
                    $stmt_check = $db->prepare("SELECT id FROM smm_services WHERE provider_id = ? AND service_id = ?");
                    $stmt_check->execute([$provider_id, $service_id]);
                    $existing_id = $stmt_check->fetchColumn();

                    if ($existing_id) {
                        // 6. Agar hai to UPDATE karein
                        $stmt_update = $db->prepare("
                            UPDATE smm_services 
                            SET name = ?, category = ?, base_price = ?, service_rate = ?, min = ?, max = ?, 
                                avg_time = ?, has_refill = ?, has_cancel = ?, is_active = 1
                            WHERE id = ?
                        ");
                        $stmt_update->execute([
                            $name, $category, $base_price_pkr, $service_rate_pkr, $min, $max,
                            $avg_time, $has_refill, $has_cancel,
                            $existing_id
                        ]);
                        $updated_count++;
                    } else {
                        // 7. Agar nahi hai to INSERT karein
                        $stmt_insert = $db->prepare("
                            INSERT INTO smm_services 
                            (provider_id, service_id, name, category, base_price, service_rate, min, max, avg_time, has_refill, has_cancel, is_active)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                        ");
                        $stmt_insert->execute([
                            $provider_id, $service_id, $name, $category, $base_price_pkr, $service_rate_pkr, $min, $max,
                            $avg_time, $has_refill, $has_cancel
                        ]);
                        $imported_count++;
                    }
                }
                
                $db->commit();
                $success = "Sync complete! $imported_count new services imported, $updated_count services updated.";
            }
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $error = 'An error occurred: ' . $e->getMessage();
    }
    
    $action = 'list'; // List page par wapis bhej dein
}

// --- Load Data for Views (Searchable) ---
$search = $_GET['search'] ?? '';
$where = '';
$params = [];
if (!empty($search)) {
    $where = 'WHERE s.name LIKE ? OR s.category LIKE ?';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$stmt_list = $db->prepare("
    SELECT s.*, p.name as provider_name 
    FROM smm_services s
    JOIN smm_providers p ON s.provider_id = p.id
    $where
    ORDER BY s.category ASC, s.name ASC
");
$stmt_list->execute($params);
$services = $stmt_list->fetchAll();

?>

<style>
.sync-box {
    background: var(--card-color);
    border: 1px solid var(--card-border);
    padding: 1.5rem;
    border-radius: var(--radius);
    margin-bottom: 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.search-box {
    margin-bottom: 1.5rem;
}
.search-box input {
    width: 100%;
}
</style>

<h1>Manage SMM Services</h1>

<?php if ($error): ?><div class="message error"><?php echo $error; ?></div><?php endif; ?>
<?php if ($success): ?><div class="message success"><?php echo $success; ?></div><?php endif; ?>

<div class="sync-box">
    <div>
        <strong>Sync Services with Provider</strong><br>
        <small>This will import new services, update prices (with your profit %), and disable removed services.</small>
    </div>
    <a href="services.php?action=sync" class="btn-new" onclick="return confirm('Are you sure you want to sync all services? This may take a few minutes.')">
        Sync Now
    </a>
</div>

<div class="search-box">
    <form action="services.php" method="GET">
        <input type="text" name="search" class="form-control" placeholder="Search by service name or category (aap ki request)..." value="<?php echo sanitize($search); ?>">
    </form>
</div>

<div class="admin-table-responsive">
    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Category</th>
                <th>Our Rate (/1k)</th>
                <th>Provider Rate (PKR)</th>
                <th>Min/Max</th>
                <th>Refill</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($services)): ?>
                <tr><td colspan="8" style="text-align: center;">No services found. Try syncing with your provider.</td></tr>
            <?php endif; ?>
            
            <?php foreach ($services as $s): ?>
            <tr>
                <td><?php echo $s['service_id']; ?></td>
                <td><?php echo sanitize($s['name']); ?></td>
                <td><?php echo sanitize($s['category']); ?></td>
                <td><?php echo formatCurrency($s['service_rate']); ?></td>
                <td><?php echo formatCurrency($s['base_price']); ?></td>
                <td><?php echo $s['min']; ?> / <?php echo $s['max']; ?></td>
                <td>
                    <?php if ($s['has_refill']): ?>
                        <span class="status-badge status-active">Yes</span>
                    <?php else: ?>
                        <span class="status-badge status-expired">No</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($s['is_active']): ?>
                        <span class="status-badge status-active">Active</span>
                    <?php else: ?>
                        <span class="status-badge status-expired">Disabled</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include '_footer.php'; ?>