<?php
include '_smm_header.php'; // Naya SMM Header istemal karein
require_once __DIR__ . '/../includes/smm_api.class.php';

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// --- NAYI LOGIC: REFILL/CANCEL BUTTONS ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $order_id = (int)$_POST['order_id'];

    try {
        // 1. Order ko DB se fetch karein (aur check karein ke yeh isi user ka hai)
        $stmt_order = $db->prepare("SELECT o.*, s.has_refill, s.has_cancel, p.api_url, p.api_key
                                    FROM smm_orders o
                                    JOIN smm_services s ON o.service_id = s.id
                                    JOIN smm_providers p ON s.provider_id = p.id
                                    WHERE o.id = ? AND o.user_id = ?");
        $stmt_order->execute([$order_id, $user_id]);
        $order = $stmt_order->fetch();

        if (!$order || empty($order['provider_order_id'])) {
            $error = 'Order not found or not yet processed by provider.';
        } else {
            // API class ko call karein
            $api = new SmmApi($order['api_url'], $order['api_key']);
            
            // 2. REFILL ACTION
            if ($_POST['action'] == 'refill' && $order['has_refill']) {
                $result = $api->refillOrder($order['provider_order_id']);
                if ($result['success']) {
                    $success = 'Refill request sent for Order #' . $order['id'] . '!';
                } else {
                    $error = 'Refill failed: ' . ($result['error'] ?? 'Provider error');
                }
            } 
            // 3. CANCEL ACTION
            elseif ($_POST['action'] == 'cancel' && $order['has_cancel']) {
                $result = $api->cancelOrder($order['provider_order_id']);
                if ($result['success']) {
                    // Status ko 'cancelled' mark karein (Refund cron job khud kar dega)
                    $db->prepare("UPDATE smm_orders SET status = 'cancelled' WHERE id = ?")->execute([$order_id]);
                    $success = 'Cancel request sent for Order #' . $order['id'] . '!';
                } else {
                    $error = 'Cancel failed: ' . ($result['error'] ?? 'Provider error');
                }
            } else {
                $error = 'This action is not supported for this service.';
            }
        }
    } catch (Exception $e) {
        $error = 'An error occurred: ' . $e->getMessage();
    }
}


// --- SMM Orders ki history fetch karein ---
try {
    $stmt = $db->prepare("
        SELECT o.*, s.name as service_name, s.has_refill, s.has_cancel
        FROM smm_orders o
        JOIN smm_services s ON o.service_id = s.id
        WHERE o.user_id = ?
        ORDER BY o.created_at DESC
        LIMIT 50 
    ");
    $stmt->execute([$user_id]);
    $smm_orders = $stmt->fetchAll();
} catch (PDOException $e) {
    $smm_orders = [];
    $error = "Failed to load orders: " . $e->getMessage();
}
?>

<div class="app-page-header">
    <a href="smm_order.php" class="back-button">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"></path><path d="m12 19-7-7 7-7"></path></svg>
    </a>
    <h2>My SMM Orders</h2>
</div>

<?php if ($error): ?><div class="app-message app-error"><?php echo sanitize($error); ?></div><?php endif; ?>
<?php if ($success): ?><div class="app-message app-success"><?php echo sanitize($success); ?></div><?php endif; ?>
<?php if (isset($_GET['success'])): ?><div class="app-message app-success"><?php echo sanitize($_GET['success']); ?></div><?php endif; ?>

<div class="order-history-list">
    <?php if (empty($smm_orders)): ?>
        <div class="app-card" style="text-align: center; padding-top: 2rem; padding-bottom: 2rem;">
            <p style="color: var(--app-secondary); margin:0;">You have not placed any SMM orders yet.</p>
        </div>
    <?php else: ?>
        <?php foreach ($smm_orders as $order): ?>
            <div class="app-card-order">
                <div class="order-card-header">
                    <div class="order-card-info">
                        <h3><?php echo sanitize($order['service_name']); ?></h3>
                        <p>Order ID: #<?php echo $order['id']; ?> | Qty: <?php echo $order['quantity']; ?></p>
                    </div>
                    <div class="order-card-price">
                        <?php echo formatCurrency($order['charge']); ?>
                    </div>
                </div>
                
                <div class="order-card-status">
                    <span class="app-status status-<?php echo str_replace(' ', '_', strtolower($order['status'])); ?>">
                        <?php echo ucfirst($order['status']); ?>
                    </span>
                </div>

                <div class="order-card-details">
                    <p><strong>Link:</strong> <span style="word-break: break-all;"><?php echo sanitize($order['link']); ?></span></p>
                    <p><strong>Date:</strong> <?php echo formatDate($order['created_at']); ?></p>
                    
                    <div class="order-actions">
                        <form action="smm_history.php" method="POST">
                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                            <button type="submit" name="action" value="refill" class="btn-app-secondary btn-refill" 
                                <?php echo ($order['has_refill'] && $order['status'] == 'completed') ? '' : 'disabled'; ?>>
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L20.49 15a9 9 0 0 1-14.85 3.36L3.51 9z"></path></svg>
                                Refill
                            </button>
                        </form>
                        <form action="smm_history.php" method="POST">
                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                            <button type="submit" name="action" value="cancel" class="btn-app-secondary btn-cancel" 
                                <?php echo ($order['has_cancel'] && ($order['status'] == 'pending' || $order['status'] == 'in_progress')) ? '' : 'disabled'; ?>>
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
                                Cancel
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>


<?php include '_smm_footer.php'; // Naya SMM Footer istemal karein ?>