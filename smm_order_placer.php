<?php
// --- CRON JOB (Run every 1 minute) ---
// Yeh script 'pending' orders ko provider ko bhejti hai

chdir(dirname(__DIR__)); 
require_once 'config.php';
require_once 'db.php';
require_once 'smm_api.class.php';

$log_file = '../../assets/logs/smm_order_placer.log';
$log_message = "Order Placer Cron started at " . date('Y-m-d H:i:s') . "\n";

try {
    // 1. Sirf 1 active provider fetch karein
    $stmt_provider = $db->prepare("SELECT * FROM smm_providers WHERE is_active = 1 LIMIT 1");
    $stmt_provider->execute();
    $provider = $stmt_provider->fetch();

    if (!$provider) {
        throw new Exception('No active SMM provider found.');
    }
    
    // 2. API class ko tayyar karein
    $api = new SmmApi($provider['api_url'], $provider['api_key']);

    // 3. Tamam 'pending' orders (limit 10) fetch karein
    $stmt_orders = $db->prepare("
        SELECT o.*, s.service_id as provider_service_id 
        FROM smm_orders o
        JOIN smm_services s ON o.service_id = s.id
        WHERE o.status = 'pending'
        LIMIT 10
    ");
    $stmt_orders->execute();
    $pending_orders = $stmt_orders->fetchAll();

    if (empty($pending_orders)) {
        $log_message .= "No pending orders found.\n";
    } else {
        $log_message .= count($pending_orders) . " pending orders found. Processing...\n";
    }

    // 4. Har order ko provider ko bhejein
    foreach ($pending_orders as $order) {
        $order_id = $order['id'];
        $provider_service_id = $order['provider_service_id'];
        $link = $order['link'];
        $quantity = $order['quantity'];
        
        // Drip-feed data (agar future mein add karein)
        $drip_feed_data = null; 

        // API call karein
        $result = $api->placeOrder($provider_service_id, $link, $quantity, $drip_feed_data);

        if ($result['success']) {
            // Kamyab!
            $provider_order_id = $result['provider_order_id'];
            // Status ko 'in_progress' aur provider_order_id ko save karein
            $stmt_update = $db->prepare("UPDATE smm_orders SET status = 'in_progress', provider_order_id = ? WHERE id = ?");
            $stmt_update->execute([$provider_order_id, $order_id]);
            $log_message .= "  SUCCESS: Order #$order_id placed. Provider ID: $provider_order_id\n";
        } else {
            // Fail!
            $error_msg = $result['error'] ?? 'Unknown API error';
            // Status ko 'failed' (ya 'cancelled') mark karein aur refund karein
            $db->prepare("UPDATE smm_orders SET status = 'cancelled' WHERE id = ?")->execute([$order_id]);
            
            // Wallet class ko call kar ke foran refund karein
            require_once 'wallet.class.php';
            $wallet = new Wallet($db);
            $wallet->addCredit($order['user_id'], $order['charge'], 'admin_adjust', $order_id, "SMM Order Failed: " . $error_msg);
            
            $log_message .= "  FAILED: Order #$order_id failed ($error_msg). Refunded.\n";
        }
    }

} catch (Exception $e) {
    $log_message .= "CRITICAL ERROR: " . $e->getMessage() . "\n";
}

$log_message .= "Cron finished at " . date('Y-m-d H:i:s') . "\n\n";
file_put_contents($log_file, $log_message, FILE_APPEND);
echo $log_message;
?>