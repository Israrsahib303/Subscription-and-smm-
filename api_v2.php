<?php
// --- SubHub v7.0 - API v2 Engine ---
header('Content-Type: application/json');

// Helpers, DB, aur Classes ko include karein
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/wallet.class.php';
require_once __DIR__ . '/includes/order.class.php';
require_once __DIR__ . '/includes/smm_api.class.php';

$response = [];
$action = $_POST['action'] ?? '';
$api_key = $_POST['key'] ?? '';

try {
    // 1. API Key ko check karein
    if (empty($api_key)) {
        throw new Exception('API key is missing.');
    }

    $stmt_user = $db->prepare("SELECT * FROM users WHERE api_key = ?");
    $stmt_user->execute([$api_key]);
    $user = $stmt_user->fetch();

    if (!$user) {
        throw new Exception('Invalid API key.');
    }
    
    $user_id = $user['id'];
    $wallet = new Wallet($db);

    // 2. Action ke hisab se kaam karein
    switch ($action) {
        
        // --- Action: Balance Check ---
        case 'balance':
            $balance = $wallet->getBalance($user_id);
            $response = [
                'balance' => $balance,
                'currency' => $GLOBALS['settings']['currency_symbol'] ?? 'PKR'
            ];
            break;

        // --- Action: Services List (Yeh 1000x better hai) ---
        case 'services':
            $services_list = [];
            
            // 1. Subscription Services
            $stmt_subs = $db->query("SELECT v.*, p.name FROM product_variations v JOIN products p ON v.product_id = p.id WHERE v.is_active = 1 AND p.is_active = 1");
            while ($sub = $stmt_subs->fetch()) {
                $services_list[] = [
                    'service' => 'SUB-' . $sub['id'], // 'SUB-' prefix taake ID conflict na ho
                    'name' => "[SUB] " . $sub['name'] . " - " . $sub['type'] . " (" . $sub['duration_months'] . "M)",
                    'category' => 'Digital Subscriptions',
                    'rate' => $sub['price'],
                    'min' => 1,
                    'max' => 1
                ];
            }
            
            // 2. SMM Services
            $stmt_smm = $db->query("SELECT * FROM smm_services WHERE is_active = 1");
            while ($smm = $stmt_smm->fetch()) {
                $services_list[] = [
                    'service' => $smm['id'], // Yeh normal ID hai
                    'name' => $smm['name'],
                    'category' => $smm['category'],
                    'rate' => $smm['service_rate'],
                    'min' => $smm['min'],
                    'max' => $smm['max']
                ];
            }
            $response = $services_list;
            break;

        // --- Action: Place Order (Yeh bhi 1000x better hai) ---
        case 'add':
            $service_id_input = $_POST['service'] ?? '';
            $link = sanitize($_POST['link'] ?? '');
            $quantity = (int)($_POST['quantity'] ?? 0);
            
            if (empty($service_id_input)) {
                throw new Exception('Service ID is missing.');
            }

            // Check karein ke yeh Subscription hai ya SMM
            if (strpos($service_id_input, 'SUB-') === 0) {
                // YEH SUBSCRIPTION ORDER HAI
                $variation_id = (int)str_replace('SUB-', '', $service_id_input);
                
                $order_class = new Order($db, $wallet);
                $result = $order_class->createOrderFromVariation($user_id, $variation_id);
                
                if ($result['success']) {
                    $response = ['order' => $result['order']['id']]; // Order ID wapis bhejein
                } else {
                    throw new Exception($result['error']);
                }
                
            } else {
                // YEH SMM ORDER HAI
                $service_id = (int)$service_id_input;
                
                $stmt_service = $db->prepare("SELECT * FROM smm_services WHERE id = ? AND is_active = 1");
                $stmt_service->execute([$service_id]);
                $service = $stmt_service->fetch();

                if (!$service) { throw new Exception('Service not found or is disabled.'); }
                if ($quantity < $service['min']) { throw new Exception('Quantity is less than minimum.'); }
                if ($quantity > $service['max']) { throw new Exception('Quantity is more than maximum.'); }

                $charge = ($quantity / 1000) * (float)$service['service_rate'];
                
                $current_balance = $wallet->getBalance($user_id);
                if ($current_balance < $charge) { throw new Exception('Insufficient funds.'); }

                $db->beginTransaction();
                $stmt_order = $db->prepare("INSERT INTO smm_orders (user_id, service_id, link, quantity, charge, status) VALUES (?, ?, ?, ?, ?, 'pending')");
                $stmt_order->execute([$user_id, $service_id, $link, $quantity, $charge]);
                $order_id = $db->lastInsertId();
                $debit_success = $wallet->addDebit($user_id, $charge, 'order', $order_id, "SMM API Order #$order_id");

                if (!$debit_success) {
                    $db->rollBack();
                    throw new Exception('Wallet debit failed.');
                }
                
                $db->commit();
                $response = ['order' => $order_id]; // Order ID wapis bhejein
            }
            break;

        // --- Action: Order Status ---
        case 'status':
            $order_id = (int)($_POST['order'] ?? 0);
            
            // Pehle SMM orders mein check karein
            $stmt_smm = $db->prepare("SELECT status, start_count, remains FROM smm_orders WHERE id = ? AND user_id = ?");
            $stmt_smm->execute([$order_id, $user_id]);
            $smm_order = $stmt_smm->fetch();
            
            if ($smm_order) {
                $response = [
                    'status' => ucfirst($smm_order['status']),
                    'start_count' => $smm_order['start_count'],
                    'remains' => $smm_order['remains'],
                    'currency' => $GLOBALS['settings']['currency_symbol'] ?? 'PKR'
                ];
            } else {
                // Phir Subscription orders mein check karein
                $stmt_sub = $db->prepare("SELECT status, start_at, end_at FROM orders WHERE id = ? AND user_id = ?");
                $stmt_sub->execute([$order_id, $user_id]);
                $sub_order = $stmt_sub->fetch();
                
                if ($sub_order) {
                    $response = [
                        'status' => ucfirst($sub_order['status']),
                        'start_count' => $sub_order['start_at'], // Start date
                        'remains' => $sub_order['end_at'],     // End date
                        'currency' => $GLOBALS['settings']['currency_symbol'] ?? 'PKR'
                    ];
                } else {
                    throw new Exception('Order not found.');
                }
            }
            break;

        default:
            throw new Exception('Invalid action.');
            break;
    }

} catch (Exception $e) {
    $response = ['error' => $e->getMessage()];
}

echo json_encode($response);
exit;