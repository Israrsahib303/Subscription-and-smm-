<?php
include '_header.php';

try {
    $stmt = $db->prepare("
        SELECT o.*, p.name as product_name, p.icon as product_icon
        FROM orders o
        JOIN products p ON o.product_id = p.id
        WHERE o.user_id = ?
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $orders = $stmt->fetchAll();
} catch (PDOException $e) {
    $orders = [];
    // Log error
}
?>

<style>
.credentials-box {
    background: #111;
    border: 1px solid var(--card-border);
    padding: 1rem;
    margin-top: 0.5rem;
    border-radius: var(--radius);
}
.credentials-box p {
    font-size: 0.9rem;
    color: var(--text-muted);
    margin-bottom: 0.5rem;
}
.credentials-box p strong {
    color: var(--text-color);
    display: inline-block;
    min-width: 100px;
}
.btn-show-details {
    background: #333;
    color: #fff;
    border: 0;
    padding: 0.3rem 0.8rem;
    font-size: 0.8rem;
    border-radius: 4px;
    cursor: pointer;
}
</style>

<h1 class="section-title">My Orders</h1>

<div class="table-responsive">
    <table class="orders-table">
        <thead>
            <tr>
                <th>Order ID</th>
                <th>Product</th>
                <th>Total</th>
                <th>Expires In</th>
                <th>Status</th>
                <th>Login Details</th> </tr>
        </thead>
        <tbody>
            <?php if (empty($orders)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding: 2rem;">You have not placed any orders yet.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><strong>#<?php echo $order['code']; ?></strong></td>
                        <td>
                            <div class="product-name">
                                <img src="../assets/img/icons/<?php echo sanitize($order['product_icon']); ?>" alt="">
                                <span><?php echo sanitize($order['product_name']); ?></span>
                            </div>
                        </td>
                        <td><?php echo formatCurrency($order['total_price']); ?></td>
                        <td>
                            <?php if ($order['status'] == 'completed'): ?>
                                <div class="countdown" data-end-at="<?php echo $order['end_at']; ?>">
                                    Loading...
                                </div>
                            <?php else: ?>
                                ---
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo $order['status'] == 'completed' ? 'active' : $order['status']; ?>">
                                <?php echo $order['status'] == 'completed' ? 'Active' : ucfirst($order['status']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($order['service_username'])): ?>
                                <button class="btn-show-details" onclick="toggleDetails(<?php echo $order['id']; ?>)">
                                    Show Details
                                </button>
                            <?php else: ?>
                                <span style="color: var(--text-muted); font-style: italic; font-size: 0.8rem;">Pending...</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    
                    <?php if (!empty($order['service_username'])): ?>
                    <tr class="details-row" id="details-<?php echo $order['id']; ?>" style="display: none;">
                        <td colspan="6">
                            <div class="credentials-box">
                                <p><strong>Username:</strong> <?php echo sanitize($order['service_username']); ?></p>
                                <p><strong>Password:</strong> <?php echo sanitize($order['service_password']); ?></p>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>

                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
function toggleDetails(orderId) {
    const detailsRow = document.getElementById('details-' + orderId);
    if (detailsRow) {
        if (detailsRow.style.display === 'none') {
            detailsRow.style.display = 'table-row';
        } else {
            detailsRow.style.display = 'none';
        }
    }
}
</script>

<?php include '_footer.php'; ?>