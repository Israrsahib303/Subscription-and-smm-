<?php
// --- YEH POORA LOGIC BLOCK TOP PAR MOVE HO GAYA HAI (Blank Page Fix) ---
require_once __DIR__ . '/../includes/helpers.php'; // Helpers pehle load karein
requireLogin(); // Login check pehle karein
require_once __DIR__ . '/../includes/wallet.class.php';

$wallet = new Wallet($db);
$error = '';
$success = '';

// Auto-Refresh Fix (Part 1)
if (isset($_GET['success']) && $_GET['success'] == 'claimed') {
    $success = 'Payment verified! ' . formatCurrency($_GET['amount']) . ' has been added to your wallet.';
}
if (isset($_GET['success']) && $_GET['success'] == 'manual') {
    $success = 'Deposit submitted for approval! Please wait for admin.';
}

// LOGIC 1: NayaPay Auto-Claim Logic
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'claim_payment') {
    
    $txn_id = sanitize($_POST['nayapay_txn_id']);
    $amount = (float)($_POST['nayapay_amount']);
    $user_id = $_SESSION['user_id'];

    if (empty($txn_id) || $amount <= 0) {
        $error = 'Please enter a valid TXN ID and Amount.';
    } else {
        try {
            $stmt = $db->prepare("SELECT * FROM email_payments WHERE txn_id = ? AND status = 'pending' FOR UPDATE");
            $stmt->execute([$txn_id]);
            $unclaimed_payment = $stmt->fetch();

            if (!$unclaimed_payment) {
                $error = 'Transaction ID not found or already claimed. Please wait 5 minutes if you just paid.';
            } 
            elseif (abs((float)$unclaimed_payment['amount'] - $amount) > 0.01) {
                $error = 'The amount (PKR ' . $amount . ') does not match the transaction amount (PKR ' . $unclaimed_payment['amount'] . ').';
            } else {
                $stmt_method = $db->prepare("SELECT min_amount, max_amount FROM payment_methods WHERE name LIKE ?");
                $stmt_method->execute(['%NayaPay%']);
                $method_limits = $stmt_method->fetch();
                
                if ($method_limits && $method_limits['min_amount'] > 0 && $amount < $method_limits['min_amount']) {
                     $error = 'Minimum deposit is ' . formatCurrency($method_limits['min_amount']);
                } elseif ($method_limits && $method_limits['max_amount'] > 0 && $amount > $method_limits['max_amount']) {
                     $error = 'Maximum deposit is ' . formatCurrency($method_limits['max_amount']);
                } else {
                    $db->beginTransaction();
                    $wallet->addCredit($user_id, $amount, 'payment', $unclaimed_payment['id'], 'NayaPay Claim: ' . $txn_id);
                    $stmt_claim = $db->prepare("UPDATE email_payments SET status = 'claimed', claimed_by_user_id = ?, claimed_at = NOW() WHERE id = ?");
                    $stmt_claim->execute([$user_id, $unclaimed_payment['id']]);
                    $stmt_log = $db->prepare("INSERT INTO payments (user_id, method, amount, txn_id, status, gateway_ref, created_at, approved_at) VALUES (?, 'NayaPay-Auto', ?, ?, 'approved', ?, NOW(), NOW())");
                    $stmt_log->execute([$user_id, $amount, $txn_id, 'email_payment_id:' . $unclaimed_payment['id']]);
                    $db->commit();
                    
                    // Auto-Refresh Fix (Part 2)
                    redirect("add-funds.php?success=claimed&amount=" . $amount);
                }
            }
        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $error = 'An error occurred: ' . $e->getMessage();
        }
    }
}

// LOGIC 2: Manual Deposit Logic (Screenshot wala)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'manual_deposit') {
    $amount = (float)($_POST['manual_amount'] ?? 0);
    $method = sanitize($_POST['manual_method'] ?? '');
    $txn_id = sanitize($_POST['txn_id'] ?? '');
    $screenshot = $_FILES['screenshot'];

    $stmt_method = $db->prepare("SELECT min_amount, max_amount FROM payment_methods WHERE name = ?");
    $stmt_method->execute([$method]);
    $method_limits = $stmt_method->fetch();

    if ($amount <= 0 || empty($method) || empty($txn_id) || $screenshot['error'] == 4) {
        $error = 'Please fill all manual deposit fields and upload a screenshot.';
    } elseif ($method_limits && $method_limits['min_amount'] > 0 && $amount < $method_limits['min_amount']) {
         $error = 'Minimum deposit for '.$method.' is ' . formatCurrency($method_limits['min_amount']);
    } elseif ($method_limits && $method_limits['max_amount'] > 0 && $amount > $method_limits['max_amount']) {
         $error = 'Maximum deposit for '.$method.' is ' . formatCurrency($method_limits['max_amount']);
    } elseif ($screenshot['size'] > 2 * 1024 * 1024) {
        $error = 'Screenshot file is too large (Max 2MB).';
    } else {
        $upload_dir = __DIR__ . '/../assets/uploads/';
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $file_type = mime_content_type($screenshot['tmp_name']);
        
        if (in_array($file_type, $allowed_types)) {
            $file_ext = pathinfo($screenshot['name'], PATHINFO_EXTENSION);
            $file_name = uniqid('ss_', true) . '.' . $file_ext;
            $upload_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($screenshot['tmp_name'], $upload_path)) {
                try {
                    $stmt = $db->prepare("INSERT INTO payments (user_id, method, amount, txn_id, screenshot_path, status) VALUES (?, ?, ?, ?, ?, 'pending')");
                    $stmt->execute([$_SESSION['user_id'], $method, $amount, $txn_id, $file_name]);
                    
                    // Auto-Refresh Fix (Part 3)
                    redirect("add-funds.php?success=manual");

                } catch (PDOException $e) { $error = 'Database error. ' . $e->getMessage(); }
            } else { $error = 'Failed to upload screenshot. Check folder permissions.'; }
        } else { $error = 'Invalid file type. Only JPG, PNG, GIF, and WEBP are allowed.'; }
    }
}
// --- POORA PHP LOGIC BLOCK YAHAN KHATAM HOTA HAI ---


// Ab HTML shuru ho ga
include '_header.php';

// Manual payment methods (JazzCash, etc.) yahan show honge (instructions ke liye)
$stmt_methods = $db->query("SELECT * FROM payment_methods WHERE is_active = 1");
$methods = $stmt_methods->fetchAll();

// NayaPay ke limits pehle se fetch kar lein (Form mein dikhane ke liye)
$nayapay_limits = null;
foreach ($methods as $m) {
    if ($m['is_auto'] == 1) { // Pehla auto method (NayaPay)
        $nayapay_limits = $m;
        break;
    }
}
?>

<style>
.payment-grid-container {
    display: grid;
    grid-template-columns: 2fr 1fr; /* 2 hissay forms ke, 1 hissa instructions ka */
    gap: 2rem;
}
@media (max-width: 900px) {
    .payment-grid-container { grid-template-columns: 1fr; }
}
.payment-card.claim-box {
    background: linear-gradient(135deg, #1f4037 0%, #0e201b 100%);
    border: 1px solid #4CAF50;
    animation: borderGlow 3s ease-in-out infinite;
    margin-bottom: 2rem; /* Dono forms ke beech gap */
}
@keyframes borderGlow {
    0% { border-color: #4CAF50; } 50% { border-color: #81C784; } 100% { border-color: #4CAF50; }
}
.payment-card.claim-box h3 {
    color: #4CAF50; display: flex; align-items: center; gap: 10px;
}
.payment-card.claim-box h3 svg { width: 24px; height: 24px; }
.instructions-list { font-size: 0.9rem; color: var(--text-muted); margin-left: 20px; }
.method-limits { 
    font-size: 0.9rem; color: #ffc107; font-weight: 600; 
    margin-top: 5px; padding: 0.5rem; background: #333;
    border-radius: 4px; border-left: 3px solid #ffc107;
} 
.payment-card.manual-box {
    background: var(--card-color); border: 1px solid var(--card-border);
}
.payment-card.manual-box h3 {
    color: #FFC107; display: flex; align-items: center; gap: 10px;
}
.payment-card.manual-box h3 svg { width: 24px; height: 24px; }
</style>

<h1 class="section-title">Add Funds to Wallet</h1>

<?php if ($error): ?><div class="message error"><?php echo sanitize($error); ?></div><?php endif; ?>
<?php if ($success): ?><div class="message success"><?php echo sanitize($success); ?></div><?php endif; ?>

<div class="payment-grid-container">
    
    <div class="forms-column">
        
        <div class="payment-card claim-box">
            <h3>
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"></path><path d="m9 12 2 2 4-4"></path></svg>
                NayaPay Auto Verification (Instant)
            </h3>
            
            <ol class="instructions-list">
                <li>Pay to our NayaPay account (details on the right).</li>
                <li>Copy the <strong>Transaction ID</strong> and <strong>Exact Amount</strong>.</li>
                <li>Enter them below to claim your funds instantly.</li>
            </ol>
            
            <?php if ($nayapay_limits && ($nayapay_limits['min_amount'] > 0 || $nayapay_limits['max_amount'] > 0)): ?>
            <p class="method-limits" style="margin-top: 1rem;">
                <?php if ($nayapay_limits['min_amount'] > 0): ?>
                    Min: <?php echo formatCurrency($nayapay_limits['min_amount']); ?>
                <?php endif; ?>
                <?php if ($nayapay_limits['max_amount'] > 0): ?>
                    | Max: <?php echo formatCurrency($nayapay_limits['max_amount']); ?>
                <?php endif; ?>
            </p>
            <?php endif; ?>
            
            <form action="add-funds.php" method="POST" style="margin-top: 1.5rem;">
                <input type="hidden" name="action" value="claim_payment">
                <div class="form-group">
                    <label for="nayapay_txn_id">NayaPay Transaction ID (TID)</label>
                    <input type="text" name="nayapay_txn_id" id="nayapay_txn_id" class="form-control" placeholder="e.g., 123456789" required>
                </div>
                <div class="form-group">
                    <label for="nayapay_amount">Exact Amount Sent (PKR)</label>
                    <input type="number" name="nayapay_amount" id="nayapay_amount" class="form-control" placeholder="e.g., 500" min="1" step="0.01" required>
                </div>
                <button type="submit" class="btn btn-primary" style="background: #4CAF50; border-color: #4CAF50;">Verify & Claim Payment</button>
            </form>
        </div>

        <div class="payment-card manual-box">
            <h3>
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10v11a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V10"></path><path d="M7 10V7a5 5 0 0 1 10 0v3"></path><path d="M22 6H2"></path><path d="M16 6a4 4 0 0 0-8 0"></path></svg>
                Manual Deposit (JazzCash, Easypaisa, etc.)
            </h3>
            
            <form action="add-funds.php" method="POST" enctype="multipart/form-data" style="margin-top: 1.5rem;">
                <input type="hidden" name="action" value="manual_deposit">
                <div class="form-group">
                    <label for="manual_method">Payment Method Used</label>
                    <select name="manual_method" id="manual_method" class="form-control" required>
                        <option value="">-- Select Method --</option>
                        <?php foreach ($methods as $method): ?>
                            <?php if($method['is_auto'] == 0): // Sirf manual methods dikhayein ?>
                            <option value="<?php echo sanitize($method['name']); ?>"><?php echo sanitize($method['name']); ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="manual_amount">Amount Sent (PKR)</label>
                    <input type="number" name="manual_amount" id="manual_amount" class="form-control" placeholder="e.g., 1000" min="1" step="1" required>
                </div>
                <div class="form-group">
                    <label for="txn_id">Transaction ID (TID / TXN ID)</label>
                    <input type="text" name="txn_id" id="txn_id" class="form-control" placeholder="e.g., 81273618273" required>
                </div>
                <div class="form-group">
                    <label for="screenshot">Payment Screenshot</label>
                    <input type="file" name="screenshot" id="screenshot" class="form-control" accept="image/*" required>
                </div>
                <button type="submit" class="btn btn-primary">Submit for Approval</button>
            </form>
        </div>
    </div>
    
    <div class="instructions-column">
        <div class="payment-card">
            <h3>Payment Accounts</h3>
            <ul class="payment-methods-list">
                <?php foreach ($methods as $method): ?>
                <li class="payment-method">
                    <div class="payment-method-header">
                        <img src="../assets/img/methods/<?php echo sanitize($method['icon_path']); ?>" alt="<?php echo sanitize($method['name']); ?>">
                        <span><?php echo sanitize($method['name']); ?></span>
                    </div>
                    <p>Name: <strong><?php echo sanitize($method['account_name']); ?></strong></p>
                    <p>Number: <strong><?php echo sanitize($method['account_number']); ?></strong></p>
                    
                    <p class="method-limits">
                        <?php if ($method['min_amount'] > 0): ?>
                            Min: <?php echo formatCurrency($method['min_amount']); ?>
                        <?php endif; ?>
                        <?php if ($method['max_amount'] > 0): ?>
                            | Max: <?php echo formatCurrency($method['max_amount']); ?>
                        <?php endif; ?>
                    </p>
                    
                    <?php if ($method['note']): ?>
                        <p style="font-style: italic; color: #ccc; margin-top: 5px;"><?php echo sanitize($method['note']); ?></p>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

</div>

<?php include '_footer.php'; ?>