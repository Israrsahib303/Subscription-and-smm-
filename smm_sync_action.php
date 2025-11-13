<?php
// Yeh file Admin action aur Cron job dono ke liye istemal ho sakti hai
include '../includes/config.php'; 
include '../includes/database.php';
include '../includes/functions.php';

// Check karein ke Admin logged in hai (agar browser se access ho raha hai)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'];
$redirect_url = '../panel/smm_services.php';


// --- 1. Delete Actions Handle Karein ---
if ($is_admin) {
    // A. Delete Single Service
    if (isset($_GET['delete_service'])) {
        $service_id = (int)$_GET['delete_service'];
        try {
            $stmt = $db->prepare("DELETE FROM smm_services WHERE id = ?");
            $stmt->execute([$service_id]);
            header("Location: $redirect_url?success=" . urlencode("Service #$service_id deleted successfully."));
            exit;
        } catch (PDOException $e) {
            header("Location: $redirect_url?error=" . urlencode("Failed to delete service: " . $e->getMessage()));
            exit;
        }
    }
    
    // B. Delete All Services in a Category
    if (isset($_GET['delete_category'])) {
        $category_name = sanitize($_GET['delete_category']);
        try {
            $stmt = $db->prepare("DELETE FROM smm_services WHERE category = ?");
            $stmt->execute([$category_name]);
            header("Location: $redirect_url?success=" . urlencode("All services in category '{$category_name}' deleted successfully."));
            exit;
        } catch (PDOException $e) {
            header("Location: $redirect_url?error=" . urlencode("Failed to delete category services: " . $e->getMessage()));
            exit;
        }
    }
}


// --- 2. Full Service Sync Logic Start Karein ---

// Agar Admin ne sync button click kiya hai
if ($is_admin && !isset($_GET['delete_service']) && !isset($_GET['delete_category'])) {
    header("Location: $redirect_url?sync_status=sync_start");
    // Execution continue karein
} 
// Agar cron job chal raha hai, to sync shuru karein (no output/redirect)


// --- Main Sync Function ---
function syncServices($db) {
    $log = function($msg) {
        $timestamp = date('Y-m-d H:i:s');
        $log_file = __DIR__ . '/../../assets/logs/smm_service_sync.log';
        file_put_contents($log_file, "[$timestamp] " . $msg . "\n", FILE_APPEND);
    };
    
    $log("--- Service Sync Started ---");
    
    try {
        $stmt_providers = $db->query("SELECT * FROM smm_providers WHERE is_active = 1");
        $providers = $stmt_providers->fetchAll(PDO::FETCH_ASSOC);
        $total_new_services = 0;
        $total_updated_services = 0;
        $total_removed_services = 0;
        $all_provider_service_ids = []; // Sync ke baad check karne ke liye

        foreach ($providers as $provider) {
            $log("-> Syncing Provider: {$provider['name']} (ID: {$provider['id']})");
            
            // Step 1: Provider API se services fetch karein
            $url = $provider['api_url'];
            $key = $provider['api_key'];
            $api_data = [
                'key' => $key,
                'action' => 'services'
            ];
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $api_data);
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $services = json_decode($response, true);
            
            if ($http_code !== 200 || !is_array($services)) {
                $log("   ERROR: Failed to fetch services from {$provider['name']}. HTTP Code: $http_code. Response: " . substr($response, 0, 100));
                continue; 
            }
            
            $provider_new_count = 0;
            $provider_updated_count = 0;
            $current_provider_ids = [];

            // Step 2: Har service ko database mein check/add/update karein
            foreach ($services as $service) {
                $provider_service_id = (int)$service['service'];
                $provider_rate = (float)$service['rate'];
                $min = (int)$service['min'];
                $max = (int)$service['max'];
                $category = sanitize($service['category']);
                $type = sanitize($service['type']);
                
                // Humari database mein check karein
                $stmt_check = $db->prepare("SELECT id, provider_rate FROM smm_services WHERE provider_id = ? AND provider_service_id = ?");
                $stmt_check->execute([$provider['id'], $provider_service_id]);
                $existing_service = $stmt_check->fetch();
                
                $current_provider_ids[] = $provider_service_id;
                
                if ($existing_service) {
                    // Update: Rate ya min/max change hua hai
                    if ($existing_service['provider_rate'] != $provider_rate) {
                        // User rate ko bhi adjust karein (Markup maintain rakhein)
                        $markup = (float)($existing_service['service_rate'] - $existing_service['provider_rate']);
                        $new_user_rate = $provider_rate + $markup;

                        $stmt_update = $db->prepare("
                            UPDATE smm_services SET
                            provider_rate = ?, service_rate = ?, min = ?, max = ?, type = ?, updated_at = NOW()
                            WHERE id = ?
                        ");
                        $stmt_update->execute([$provider_rate, $new_user_rate, $min, $max, $type, $existing_service['id']]);
                        $provider_updated_count++;
                        $log("   - UPDATED #{$existing_service['id']}: Rate change (User Rate: $new_user_rate).");
                    }
                    
                    // Provider Service ID list mein daalein
                    $all_provider_service_ids[$provider['id']][] = $provider_service_id;
                    
                } else {
                    // Add New Service: Default user rate = provider rate + $0.5 (Aap ka default profit)
                    $default_markup = 0.5; // Default profit margin
                    $user_rate = $provider_rate + $default_markup; 
                    
                    $stmt_insert = $db->prepare("
                        INSERT INTO smm_services (provider_id, provider_service_id, name, category, type, provider_rate, service_rate, min, max, is_active, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
                    ");
                    $stmt_insert->execute([
                        $provider['id'],
                        $provider_service_id,
                        sanitize($service['name']), // Service name
                        $category,
                        $type,
                        $provider_rate,
                        $user_rate,
                        $min,
                        $max
                    ]);
                    $provider_new_count++;
                    $log("   - NEW SERVICE: ID {$provider_service_id}, Name: {$service['name']}");

                    // Provider Service ID list mein daalein
                    $all_provider_service_ids[$provider['id']][] = $provider_service_id;
                }
            }
            
            $log("   -> {$provider_new_count} New, {$provider_updated_count} Updated.");
            $total_new_services += $provider_new_count;
            $total_updated_services += $provider_updated_count;

            // Step 3: Check for Removed Services (API se gayab services ko deactivate karein)
            if (!empty($current_provider_ids)) {
                $placeholders = implode(',', array_fill(0, count($current_provider_ids), '?'));
                $sql_removed = "
                    SELECT id, name, provider_service_id FROM smm_services 
                    WHERE provider_id = ? AND provider_service_id NOT IN ($placeholders) AND is_active = 1
                ";
                
                $params_removed = array_merge([$provider['id']], $current_provider_ids);
                $stmt_removed = $db->prepare($sql_removed);
                $stmt_removed->execute($params_removed);
                $removed_services = $stmt_removed->fetchAll();

                if (!empty($removed_services)) {
                    foreach ($removed_services as $removed_service) {
                        // Service ko Deactivate karein (Soft Delete)
                        $stmt_deactivate = $db->prepare("UPDATE smm_services SET is_active = 0 WHERE id = ?");
                        $stmt_deactivate->execute([$removed_service['id']]);
                        $total_removed_services++;
                        $log("   - REMOVED/DEACTIVATED #{$removed_service['id']}: Provider {$removed_service['provider_service_id']} not found in API.");
                    }
                }
            }

        } // End Providers loop
        
        $final_message = "--- Service Sync Complete (Summary: New: $total_new_services, Updated: $total_updated_services, Removed/Deactivated: $total_removed_services) ---";
        $log($final_message);
        return $final_message;

    } catch (PDOException $e) {
        $log("FATAL DB ERROR: " . $e->getMessage());
        return "FATAL DB ERROR: " . $e->getMessage();
    } catch (Exception $e) {
        $log("FATAL ERROR: " . $e->getMessage());
        return "FATAL ERROR: " . $e->getMessage();
    }
}

// --- Sync Ko Run Karein ---
// Agar cron job ya admin ne sync shuru kiya hai
if (isset($_GET['sync_status']) || !$is_admin) {
    // Sync services run karein
    $sync_result = syncServices($db);
    
    if ($is_admin) {
        // Admin ko redirect karein
        header("Location: $redirect_url?sync_status=sync_complete");
        exit;
    } else {
        // Cron job ko output dein
        echo "SMM Service Sync Completed. " . $sync_result;
    }
}
