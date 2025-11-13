<?php
// Yeh file SMM order ko handle kare gi
require_once __DIR__ . '/../includes/helpers.php';
requireLogin();
require_once __DIR__ . '/../includes/wallet.class.php';

$wallet = new Wallet($db);
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Form se data lein
    $service_id = (int)$_POST['service_id'];
    $link = sanitize($_POST['link']);
    $quantity = (int)$_POST['quantity'];

    try {
        // 1. Service ki details (price, min, max) DB se dobara check karein
        $stmt_service = $db->prepare("SELECT * FROM smm_services WHERE id = ? AND is_active = 1");
        $stmt_service->execute([$service_id]);
        $service = $stmt_service->fetch();

        if (!$service) {
            redirect('smm_order.php?error=Service not found or is disabled.');
        }

        // 2. Quantity check karein
        if ($quantity < $service['min']) {
            redirect('smm_order.php?error=Quantity is less than minimum (' . $service['min'] . ').');
        }
        if ($quantity > $service['max']) {
            redirect('smm_order.php?error=Quantity is more than maximum (' . $service['max'] . ').');
        }

        // 3. Price calculate karein
        $charge = ($quantity / 1000) * (float)$service['service_rate'];

        // 4. Wallet balance check karein
        $current_balance = $wallet->getBalance($user_id);
        if ($current_balance < $charge) {
            redirect('add-funds.php?error=insufficient_funds');
        }

        // 5. Sab kuch theek hai, transaction shuru karein
        $db->beginTransaction();

        // 6. SMM Order ko 'pending' save karein
        $stmt_order = $db->prepare("
            INSERT INTO smm_orders (user_id, service_id, link, quantity, charge, status, provider_order_id)
            VALUES (?, ?, ?, ?, ?, 'pending', NULL)
        ");
        $stmt_order->execute([$user_id, $service_id, $link, $quantity, $charge]);
        $order_id = $db->lastInsertId();
        
        // 7. Wallet se paise kaatein
        $debit_note = "SMM Order #" . $order_id . " (" . $service['name'] . ")";
        $debit_success = $wallet->addDebit($user_id, $charge, 'order', $order_id, $debit_note);

        if (!$debit_success) {
            $db->rollBack();
            redirect('smm_order.php?error=Wallet debit failed.');
        }

        // 8. Kamyab!
        $db->commit();
        redirect('smm_history.php?success=Order placed successfully!');

    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        redirect('smm_order.php?error=' . $e->getMessage());
    }

} else {
    // Agar koi direct access kare
    redirect('smm_order.php');
}
?>