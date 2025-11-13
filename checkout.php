<?php
// --- ERROR DEBUGGING (Blank page fix) ---
ini_set('display_errors', 1);
error_reporting(E_ALL);

// --- YEH POORA LOGIC BLOCK TOP PAR MOVE HO GAYA HAI ---
require_once __DIR__ . '/../includes/helpers.php'; // Helpers pehle load karein
requireLogin(); // Login check pehle karein

$error = '';

// Handle form submission (BEFORE any HTML)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    require_once __DIR__ . '/../includes/wallet.class.php';
    require_once __DIR__ . '/../includes/order.class.php';
    
    $variation_id = isset($_POST['variation_id']) ? (int)$_POST['variation_id'] : 0;
    
    $wallet = new Wallet($db);
    $order = new Order($db, $wallet);
    
    $result = $order->createOrderFromVariation($_SESSION['user_id'], $variation_id);
    
    if ($result['success']) {
        // Pass order details to success page (BLANK PAGE FIX)
        $_SESSION['last_order_id'] = $result['order']['id'];
        $_SESSION['last_product_name'] = $result['product_name'];
        redirect('order-success.php'); // Ab yeh header se pehle call ho ga
    } else {
        if ($result['error'] == 'insufficient_funds') {
            redirect('add-funds.php?error=insufficient_funds');
        } else {
            $error = $result['error'];
        }
    }
}
// --- LOGIC BLOCK KHATAM ---


// Ab baaqi ka page load karein
include '_header.php';

$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;

// Product ki details fetch karein
try {
    $stmt = $db->prepare("SELECT * FROM products WHERE id = ? AND is_active = 1");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
} catch (PDOException $e) {
    $product = false;
}

if (!$product) {
    redirect('index.php'); // Product not found
}

// Naya code: Is product ki saari variations (prices) fetch karein
try {
    $stmt_vars = $db->prepare("SELECT * FROM product_variations WHERE product_id = ? AND is_active = 1 ORDER BY type ASC, duration_months ASC");
    $stmt_vars->execute([$product_id]);
    $variations = $stmt_vars->fetchAll();
} catch (PDOException $e) {
    $variations = [];
}
?>

<style>
.variation-selector { margin-top: 1.5rem; }
.variation-option {
    background: var(--card-color);
    border: 2px solid var(--card-border);
    border-radius: var(--radius);
    padding: 1rem;
    margin-bottom: 1rem;
    cursor: pointer;
    transition: all 0.2s ease;
    display: block; /* Label ko block banayein */
}
.variation-option:hover {
    border-color: var(--brand-red);
}
.variation-option input[type="radio"] {
    display: none; /* Radio button ko chupayein */
}
/* Jab radio button select ho to style change karein */
.variation-option input[type="radio"]:checked + .variation-details {
    border: 2px solid var(--brand-red); /* Naya border style */
    box-shadow: 0 0 10px rgba(229, 9, 20, 0.3);
    padding: 1rem;
    margin: -1rem; /* Padding ko compensate karein */
    border-radius: 6px; /* Inner radius */
}

/* Asal layout */
.variation-details {
    display: flex;
    justify-content: space-between;
    align-items: center;
    pointer-events: none; /* Taake click label par hi register ho */
    border: 2px solid transparent; /* Placeholder border */
    padding: 0;
    border-radius: 0;
    margin: 0;
}
.variation-info h4 {
    font-size: 1.1rem;
    color: var(--text-color);
    margin: 0 0 0.25rem 0;
}
.variation-info p {
    font-size: 0.9rem;
    color: var(--text-muted);
    margin: 0;
}
.variation-price {
    text-align: right;
    flex-shrink: 0; /* Taake price wrap na ho */
    margin-left: 1rem;
}
.variation-price .price-our {
    font-size: 1.5rem;
    font-weight: 700;
    color: #4CAF50;
    line-height: 1.2;
}
.variation-price .price-original {
    font-size: 0.9rem;
    color: var(--text-muted);
    line-height: 1;
}
.variation-price .price-original s {
    color: var(--brand-red);
}
</style>
<div class="checkout-box">
    <div class="checkout-product">
        <img src="../assets/img/icons/<?php echo sanitize($product['icon']); ?>" alt="<?php echo sanitize($product['name']); ?>">
        <h2><?php echo sanitize($product['name']); ?></h2>
    </div>

    <?php if ($error): ?><div class="message error"><?php echo $error; ?></div><?php endif; ?>

    <form class="checkout-form" method="POST" id="checkout-form" action="checkout.php?product_id=<?php echo $product_id; ?>">
        <div class="form-group">
            <label>Select Plan</label>
        </div>

        <div class="variation-selector">
            <?php if (empty($variations)): ?>
                <p>No plans are currently available for this product.</p>
            <?php else: ?>
                <?php foreach ($variations as $var): ?>
                    <label class="variation-option">
                        <input type="radio" name="variation_id" value="<?php echo $var['id']; ?>" required>
                        
                        <div class="variation-details">
                            <div class="variation-info">
                                <h4><?php echo sanitize($var['type']); ?></h4>
                                <p><?php echo $var['duration_months']; ?> Month(s)</p>
                            </div>
                            <div class="variation-price">
                                <span class="price-our"><?php echo formatCurrency($var['price']); ?></span>
                                <?php if (!empty($var['original_price']) && $var['original_price'] > $var['price']): ?>
                                    <br><span class="price-original"><s><?php echo formatCurrency($var['original_price']); ?></s></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </label>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <button type="submit" class="btn btn-primary" <?php if (empty($variations)) echo 'disabled'; ?>>
            Pay from Wallet (<?php echo formatCurrency($user_balance); ?>)
        </button>
    </form>
</div>

<?php include '_footer.php'; ?>