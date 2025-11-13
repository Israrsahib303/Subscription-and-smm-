<?php
include '_header.php';
// Wallet class ko include karein taake refund kar sakein
require_once __DIR__ . '/../includes/wallet.class.php';

$wallet = new Wallet($db);
$error = '';
$success = '';

// --- LOGIC: ORDER APPROVE KARNA ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'approve') {
    $order_id = (int)$_POST['order_id'];
    
    try {
        $stmt_ord = $db->prepare("SELECT duration_months FROM orders WHERE id = ? AND status = 'pending'");
        $stmt_ord->execute([$order_id]);
        $order = $stmt_ord->fetch();

        if ($order) {
            $duration_months = $order['duration_months'];
            $stmt_approve = $db->prepare("
                UPDATE orders 
                SET status = 'completed', start_at = NOW(), end_at = DATE_ADD(NOW(), INTERVAL ? MONTH)
                WHERE id = ?
            ");
            $stmt_approve->execute([$duration_months, $order_id]);
            $success = 'Order #' . $order_id . ' has been approved and activated!';
        } else { $error = 'Order not found or already processed.'; }
    } catch (PDOException $e) { $error = 'Database error: ' . $e->getMessage(); }
}
// --- APPROVE LOGIC KHATAM ---


// --- NAYI LOGIC: ORDER CANCEL/REFUND KARNA (24-Hour Grace Period) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'cancel') {
    $order_id = (int)$_POST['order_id'];

    try {
        $stmt_ord = $db->prepare("SELECT * FROM orders WHERE id = ? AND status = 'completed'");
        $stmt_ord->execute([$order_id]);
        $order = $stmt_ord->fetch();

        if ($order) {
            // --- NAYA 24-HOUR Pro-Rata Refund Calculation ---
            $total_paid = (float)$order['total_price'];
            $duration_months = (int)$order['duration_months'];
            $total_days = $duration_months * 30; // 1 mahina = 30 din
            
            if ($total_days <= 0) $total_days = 1; 
            
            $per_day_cost = $total_paid / $total_days;
            
            $start_time = strtotime($order['start_at']);
            $current_time = time();
            $seconds_elapsed = $current_time - $start_time; // Kitne seconds istemal hue

            $refund_amount = 0; // Default

            // YEH HAI NAYA FIX (Aap ki request)
            // 24 hours = 86400 seconds
            // Agar 24 ghante (86400 seconds) ke andar cancel kiya, to 100% refund do
            if ($seconds_elapsed < 86400) {
                $refund_amount = $total_paid; // 100% Full Refund
            } else {
                // Agar 24 ghante se zyada ho gaye, to pro-rata hisab lagao
                // (ceil matlab 24 ghante 1 minute bhi 2 din count ho ga)
                $days_used = ceil($seconds_elapsed / (60 * 60 * 24));
                
                $cost_to_cut = $per_day_cost * $days_used;
                $refund_amount = $total_paid - $cost_to_cut;
            }

            // Agar refund negative ho jaye
            if ($refund_amount < 0) {
                $refund_amount = 0;
            }
            
            $db->beginTransaction();
            
            // 1. Order ko 'cancelled' mark karein
            $stmt_cancel = $db->prepare("UPDATE orders SET status = 'cancelled', end_at = NOW() WHERE id = ?");
            $stmt_cancel->execute([$order_id]);
            
            // 2. Agar refund banta hai to wallet credit karein
            if ($refund_amount > 0) {
                $user_id = $order['user_id'];
                $note = "Refund for cancelled Order #" . $order['code'] . " (Pro-rata)";
                $wallet->addCredit($user_id, $refund_amount, 'admin_adjust', $_SESSION['user_id'], $note);
            }
            
            $db->commit();
            $success = 'Order #' . $order_id . ' has been cancelled. ' . formatCurrency($refund_amount) . ' refunded to user wallet.';

        } else {
            $error = 'Order not found or is not active.';
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $error = 'An error occurred during cancellation: ' . $e->getMessage();
    }
}
// --- CANCEL LOGIC KHATAM ---


// --- NAYI LOGIC: Order Details Update Karna ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_details') {
    $order_id = (int)$_POST['order_id'];
    $username = sanitize($_POST['service_username']);
    $password = sanitize($_POST['service_password']);
    try {
        $stmt = $db->prepare("UPDATE orders SET service_username = ?, service_password = ? WHERE id = ?");
        $stmt->execute([$username, $password, $order_id]);
        $success = 'Login details updated successfully!';
    } catch (PDOException $e) { $error = 'Failed to update details: ' + $e->getMessage(); }
}
// --- DETAILS LOGIC KHATAM ---


// Pagination (simple)
$page = $_GET['page'] ?? 1;
$limit = 25;
$offset = ($page - 1) * $limit;

try {
    $total_orders = $db->query("SELECT COUNT(id) FROM orders")->fetchColumn();
    $total_pages = ceil($total_orders / $limit);

    $stmt = $db->prepare("
        SELECT o.*, u.email as user_email, p.name as product_name
        FROM orders o
        JOIN users u ON o.user_id = u.id
        JOIN products p ON o.product_id = p.id
        ORDER BY o.created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $orders = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Failed to fetch orders: ' . $e->getMessage();
    $orders = [];
    $total_pages = 0;
}
?>

<style>
.details-form-row { display: none; }
.btn-add-details {
    background: #FFC107; color: #111; border:0; padding: 5px 10px; 
    border-radius: 4px; cursor: pointer; font-size: 0.8rem; font-weight: 600;
}
.details-form { background: #2a2a2a; padding: 1rem; }
.btn-save-details { background: #4CAF50; color: white; border:0; padding: 5px 10px; border-radius: 4px; cursor: pointer; font-weight: 600;}
</style>

<h1>All Orders</h1>

<?php if ($error): ?><div class="message error"><?php echo $error; ?></div><?php endif; ?>
<?php if ($success): ?><div class="message success"><?php echo $success; ?></div><?php endif; ?>

<div class="admin-table-responsive">
    <table class="admin-table">
        <thead>
            <tr>
                <th>Order ID</th>
                <th>User</th>
                <th>Product</th>
                <th>Total</th>
                <th>Status</th>
                <th>Date Placed</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($orders)): ?>
                <tr><td colspan="7" style="text-align: center;">No orders found.</td></tr>
            <?php else: ?>
                <?php foreach ($orders as $order): ?>
                <tr>
                    <td><strong>#<?php echo $order['code']; ?></strong></td>
                    <td><?php echo sanitize($order['user_email']); ?></td>
                    <td><?php echo sanitize($order['product_name']); ?></td>
                    <td><?php echo formatCurrency($order['total_price']); ?></td>
                    <td>
                        <span class="status-badge status-<?php echo $order['status'] == 'completed' ? 'active' : $order['status']; ?>">
                            <?php 
                            if ($order['status'] == 'completed') echo 'Active';
                            else echo ucfirst($order['status']);
                            ?>
                        </span>
                    </td>
                    <td><?php echo formatDate($order['created_at']); ?></td>
                    <td>
                        <?php if ($order['status'] == 'pending'): ?>
                            <form action="orders.php?page=<?php echo $page; ?>" method="POST" style="display: inline;">
                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                <button type="submit" name="action" value="approve" class="btn-edit" style="background: #4CAF50; color: #fff;">
                                    Approve
                                </button>
                            </form>
                        <?php elseif ($order['status'] == 'completed'): ?>
                             <form action="orders.php?page=<?php echo $page; ?>" method="POST" style="display: inline;">
                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                <button type="submit" name="action" value="cancel" class="btn-delete" onclick="return confirm('Are you sure you want to cancel this order? This will refund the remaining amount to the user.');">
                                    Cancel
                                </button>
                            </form>
                        <?php else: ?>
                            ---
                        <?php endif; ?>
                        
                        <button class="btn-add-details" onclick="toggleDetailsForm(<?php echo $order['id']; ?>)">
                            Details
                        </button>
                    </td>
                </tr>
                <tr class="details-form-row" id="form-<?php echo $order['id']; ?>">
                    <td colspan="7">
                        <form action="orders.php" method="POST" class="details-form">
                            <input type="hidden" name="action" value="update_details">
                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                            <div class="form-grid-2">
                                <div class="form-group">
                                    <label>Username/Email</label>
                                    <input type="text" name="service_username" class="form-control" value="<?php echo sanitize($order['service_username'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Password/Details</label>
                                    <input type="text" name="service_password" class="form-control" value="<?php echo sanitize($order['service_password'] ?? ''); ?>">
                                </div>
                            </div>
                            <button type="submit" class="btn-save-details">Save Details</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="pagination" style="margin-top: 1rem; text-align: center;">
    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <a href="?page=<?php echo $i; ?>" style="display: inline-block; padding: 5px 10px; background: #333; color: #fff; text-decoration: none; margin: 2px; <?php echo ($i == $page) ? 'background: var(--brand-red);' : ''; ?>">
            <?php echo $i; ?>
        </a>
    <?php endfor; ?>
</div>

<script>
function toggleDetailsForm(orderId) {
    const formRow = document.getElementById('form-' + orderId);
    if (formRow) {
        if (formRow.style.display === 'none' || formRow.style.display === '') {
            formRow.style.display = 'table-row';
        } else {
            formRow.style.display = 'none';
        }
    }
}
</script>

<?php include '_footer.php'; ?>