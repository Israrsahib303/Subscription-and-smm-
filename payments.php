<?php
// --- ERROR DEBUGGING START ---
ini_set('display_errors', 1);
error_reporting(E_ALL);
// --- ERROR DEBUGGING END ---

include '_header.php'; 
require_once __DIR__ . '/../includes/wallet.class.php';

$wallet = new Wallet($db);
$error = '';
$success = '';

// --- YEH NAYA CODE HAI (Tabs ke liye) ---
$filter = $_GET['filter'] ?? 'pending'; // Default 'pending' tab
$where_clause = '';
if ($filter == 'pending') {
    $where_clause = "WHERE p.status = 'pending'";
} elseif ($filter == 'approved') {
    $where_clause = "WHERE p.status = 'approved'";
} elseif ($filter == 'rejected') {
    $where_clause = "WHERE p.status = 'rejected'";
}
// Agar filter 'all' ho to where_clause khali rahe ga (sab dikhaye ga)


// Handle Actions (Approve/Reject)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['payment_id'])) {
    $payment_id = (int)$_POST['payment_id'];
    $admin_id = $_SESSION['user_id'];

    try {
        $stmt = $db->prepare("SELECT * FROM payments WHERE id = ? AND status = 'pending'");
        $stmt->execute([$payment_id]);
        $payment = $stmt->fetch();

        if ($payment) {
            if (isset($_POST['action']) && $_POST['action'] == 'approve') {
                $db->beginTransaction();
                $stmt_update = $db->prepare("UPDATE payments SET status = 'approved', approved_at = NOW(), admin_id = ? WHERE id = ?");
                $stmt_update->execute([$admin_id, $payment_id]);
                
                $credit_note = "Manual deposit approved: #" . $payment['txn_id'];
                $wallet->addCredit($payment['user_id'], $payment['amount'], 'payment', $payment_id, $credit_note);
                
                $db->commit();
                $success = "Payment #{$payment_id} approved and funds credited.";
                
            } elseif (isset($_POST['action']) && $_POST['action'] == 'reject') {
                $stmt_reject = $db->prepare("UPDATE payments SET status = 'rejected', admin_id = ? WHERE id = ?");
                $stmt_reject->execute([$admin_id, $payment_id]);
                $success = "Payment #{$payment_id} has been rejected.";
            }
        } else {
            $error = 'Payment not found or already processed.';
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $error = 'An error occurred: ' . $e->getMessage();
    }
}

// --- YEH SQL QUERY CHANGE HO GAYI HAI ---
// Ab yeh $where_clause ke mutabiq filter karti hai
$stmt_payments = $db->prepare("
    SELECT p.*, u.email as user_email 
    FROM payments p
    JOIN users u ON p.user_id = u.id
    $where_clause
    ORDER BY p.created_at DESC
");
$stmt_payments->execute();
$payments = $stmt_payments->fetchAll();
?>

<style>
.payment-tabs {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
    border-bottom: 2px solid var(--admin-border);
}
.payment-tabs a {
    padding: 0.8rem 1.2rem;
    text-decoration: none;
    font-weight: 600;
    color: var(--admin-text-muted);
    border-bottom: 3px solid transparent;
}
.payment-tabs a.active {
    color: var(--brand-red);
    border-bottom-color: var(--brand-red);
}
.payment-tabs a:hover {
    color: var(--admin-text);
}
</style>

<h1>Manage Payments</h1>

<?php if ($error): ?><div class="message error"><?php echo $error; ?></div><?php endif; ?>
<?php if ($success): ?><div class="message success"><?php echo $success; ?></div><?php endif; ?>

<div class="payment-tabs">
    <a href="payments.php?filter=pending" class="<?php echo ($filter == 'pending') ? 'active' : ''; ?>">Pending</a>
    <a href="payments.php?filter=approved" class="<?php echo ($filter == 'approved') ? 'active' : ''; ?>">Approved</a>
    <a href="payments.php?filter=rejected" class="<?php echo ($filter == 'rejected') ? 'active' : ''; ?>">Rejected</a>
    <a href="payments.php?filter=all" class="<?php echo ($filter == 'all') ? 'active' : ''; ?>">All</a>
</div>

<div class="admin-table-responsive">
    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>User</th>
                <th>Amount</th>
                <th>Method</th>
                <th>TXN ID</th>
                <th>Screenshot</th>
                <th>Date</th>
                <th>Actions/Status</th> </tr>
        </thead>
        <tbody>
            <?php if (empty($payments)): ?>
                <tr><td colspan="8" style="text-align: center;">No <?php echo $filter; ?> payments found.</td></tr>
            <?php else: ?>
                <?php foreach ($payments as $p): ?>
                <tr>
                    <td><?php echo $p['id']; ?></td>
                    <td><?php echo sanitize($p['user_email']); ?></td>
                    <td><?php echo formatCurrency($p['amount']); ?></td>
                    <td><?php echo sanitize($p['method']); ?></td>
                    <td><?php echo sanitize($p['txn_id']); ?></td>
                    <td>
                        <a href="../assets/uploads/<?php echo sanitize($p['screenshot_path']); ?>" target="_blank">
                            <img src="../assets/uploads/<?php echo sanitize($p['screenshot_path']); ?>" alt="Screenshot" style="width: 100px;">
                        </a>
                    </td>
                    <td><?php echo formatDate($p['created_at']); ?></td>
                    <td>
                        <?php if ($p['status'] == 'pending'): ?>
                            <form action="payments.php?filter=pending" method="POST" style="display: inline;">
                                <input type="hidden" name="payment_id" value="<?php echo $p['id']; ?>">
                                <button type="submit" name="action" value="approve" class="btn-edit" style="background: #4CAF50; color: #fff;">Approve</button>
                            </form>
                            <form action="payments.php?filter=pending" method="POST" style="display: inline; margin-top: 5px;">
                                <input type="hidden" name="payment_id" value="<?php echo $p['id']; ?>">
                                <button type="submit" name="action" value="reject" class="btn-delete" onclick="return confirm('Are you sure you want to reject this payment?');">Reject</button>
                            </form>
                        <?php elseif ($p['status'] == 'approved'): ?>
                            <span class="status-badge status-active">Approved</span>
                        <?php else: ?>
                            <span class="status-badge status-rejected">Rejected</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include '_footer.php'; ?>