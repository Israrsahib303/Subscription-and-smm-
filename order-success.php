<?php
include '_header.php';

// Get order details from session to prevent URL tampering
if (!isset($_SESSION['last_order_id'])) {
    redirect('orders.php');
}

$order_id = $_SESSION['last_order_id'];
$product_name = $_SESSION['last_product_name'];
unset($_SESSION['last_order_id'], $_SESSION['last_product_name']); // Clear session

$stmt = $db->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->execute([$order_id, $_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    redirect('orders.php'); // Order not found or doesn't belong to user
}

$whatsapp_link = generateWhatsAppLink($order, $product_name);
?>

<div class="success-box">
    <h1>Order Successful!</h1>
    <p>Your subscription for <strong><?php echo sanitize($product_name); ?></strong> is now active.</p>

    <div class="order-summary">
        <p>Order ID: <span>#<?php echo $order['code']; ?></span></p>
        <p>Total Paid: <span><?php echo formatCurrency($order['total_price']); ?></span></p>
        <p>Start Date: <span><?php echo formatDate($order['start_at']); ?></span></p>
        <p>End Date: <span><?php echo formatDate($order['end_at']); ?></span></p>
    </div>

    <a href="<?php echo $whatsapp_link; ?>" target="_blank" class="btn btn-whatsapp" style="width: auto; padding: 0.8rem 1.5rem;">
        Send Receipt on WhatsApp
    </a>
    <a href="orders.php" class="btn" style="background: #333; color: #fff; width: auto; padding: 0.8rem 1.5rem; margin-top: 10px;">
        View All Orders
    </a>
</div>

<?php include '_footer.php'; ?>