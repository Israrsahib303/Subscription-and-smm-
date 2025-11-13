<?php
// --- CRON JOB (Run every 24 hours @ 3 AM) ---
// Yeh script provider ki services/prices/status ko sync karti hai

chdir(dirname(__DIR__)); 
require_once 'config.php';
require_once 'db.php';
require_once 'smm_api.class.php';

$log_file = '../../assets/logs/smm_service_sync.log';
$log_message = "Service Sync Cron started at " . date('Y-m-d H:i:s') . "\n";

try {
    // 1. Settings se Currency Rate uthayein
    $usd_to_pkr_rate = (float)($GLOBALS['settings']['currency_conversion_rate'] ?? 280.00);
    
    // 2. Tamam Active Providers ko fetch karein
    $stmt_provider = $db->query("SELECT * FROM smm_providers WHERE is_active = 1");
    $providers = $stmt_provider->fetchAll();

    if (empty($providers)) {
        throw new Exception('No active API providers found to sync.');
    }

    $total_new = 0;
    $total_updated = 0;
    $total_disabled = 0;

    foreach ($providers as $provider) {
        $provider_id = $provider['id'];
        $profit_margin = (float)$provider['profit_margin'];
        $log_message .= "--- Syncing Provider: " . $provider['name'] . " ---\n";
        
        // 3. API class ko call karein
        $api = new SmmApi($provider['api_url'], $provider['api_key']);
        $result = $api->getServices();
        
        if (!$result['success'] || !is_array($result['services'])) {
            $log_message .= "  ERROR: Failed to fetch services from API. Provider said: " . ($result['error'] ?? 'Invalid response') . "\n";
            continue; // Agle provider ko check karein
        }
        
        $services_from_api = $result['services'];
        $provider_service_ids = []; // API se milne wali IDs

        $db->beginTransaction();

        // 4. Har service ko check/update/insert karein
        foreach ($services_from_api as $service) {
            if (empty($service['service']) || empty($service['name']) || !isset($service['rate'])) {
                continue; // Ghalat data ko ignore karein
            }

            $service_id = (int)$service['service'];
            $provider_service_ids[] = $service_id; // ID ko list mein add karein
            
            $name = sanitize($service['name']);
            $category = sanitize($service['category']);
            $base_price_usd = (float)$service['rate'];
            $base_price_pkr = $base_price_usd * $usd_to_pkr_rate;
            $service_rate_pkr = $base_price_pkr + ($base_price_pkr * ($profit_margin / 100));
            $min = (int)$service['min'];
            $max = (int)$service['max'];
            $has_refill = (isset($service['refill']) && $service['refill'] == 1) ? 1 : 0;
            $has_cancel = (isset($service['cancel']) && $service['cancel'] == 1) ? 1 : 0;
            $avg_time = sanitize($service['average_time'] ?? null);

            // 5. Check karein ke service pehle se hai ya nahi
            $stmt_check = $db->prepare("SELECT id, base_price FROM smm_services WHERE provider_id = ? AND service_id = ?");
            $stmt_check->execute([$provider_id, $service_id]);
            $existing_service = $stmt_check->fetch();

            if ($existing_service) {
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
                    $existing_service['id']
                ]);
                $total_updated++;
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
                $total_new++;
            }
        }
        
        // 8. Purani (Remove shuda) services ko 'disable' karein
        if (!empty($provider_service_ids)) {
            $placeholders = implode(',', array_fill(0, count($provider_service_ids), '?'));
            $params = array_merge([$provider_id], $provider_service_ids);
            
            $stmt_disable = $db->prepare("
                UPDATE smm_services 
                SET is_active = 0 
                WHERE provider_id = ? AND service_id NOT IN ($placeholders)
            ");
            $stmt_disable->execute($params);
            $total_disabled += $stmt_disable->rowCount();
        }

        $db->commit();
        $log_message .= "  Provider " . $provider['name'] . " Sync Complete: $total_new New, $total_updated Updated, $total_disabled Disabled.\n";
    }

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    $log_message .= "CRITICAL ERROR: " . $e->getMessage() . "\n";
}

$log_message .= "Cron finished at " . date('Y-m-d H:i:s') . "\n\n";
file_put_contents($log_file, $log_message, FILE_APPEND);
echo $log_message;
?>