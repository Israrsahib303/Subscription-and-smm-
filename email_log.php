<?php
include '_header.php';

// Pagination (simple)
$page = $_GET['page'] ?? 1;
$limit = 50;
$offset = ($page - 1) * $limit;

try {
    $total_records = $db->query("SELECT COUNT(id) FROM email_payments")->fetchColumn();
    $total_pages = ceil($total_records / $limit);

    $stmt = $db->prepare("
        SELECT e.*, u.email as claimed_by_email
        FROM email_payments e
        LEFT JOIN users u ON e.claimed_by_user_id = u.id
        ORDER BY e.created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Failed to fetch logs: ' . $e->getMessage();
    $logs = [];
    $total_pages = 0;
}
?>

<h1>NayaPay Email Log</h1>
<p>This log shows all transactions automatically read from the payment email. 'Pending' means the user has not claimed this payment yet.</p>

<?php if (isset($error)): ?><div class="message error"><?php echo $error; ?></div><?php endif; ?>

<div class="admin-table-responsive"> <table class="admin-table"> <thead>
            <tr>
                <th>TXN ID</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Claimed By</th>
                <th>Date Received</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)): ?>
                <tr><td colspan="5" style="text-align: center;">No email payments found yet.</td></tr>
            <?php else: ?>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td><strong><?php echo sanitize($log['txn_id']); ?></strong></td>
                    <td><?php echo formatCurrency($log['amount']); ?></td>
                    <td>
                        <?php if ($log['status'] == 'pending'): ?>
                            <span class="status-badge status-pending">Pending</span> <?php elseif ($log['status'] == 'claimed'): ?>
                            <span class="status-badge status-active">Claimed</span> <?php else: ?>
                            <span class="status-badge status-rejected">Failed</span> <?php endif; ?>
                    </td>
                    <td>
                        <?php echo sanitize($log['claimed_by_email'] ?? '---'); ?>
                        <?php if ($log['claimed_at']): ?>
                            <br><small><?php echo formatDate($log['claimed_at']); ?></small> <?php endif; ?>
                    </td>
                    <td><?php echo formatDate($log['created_at']); ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="pagination" style="margin-top: 1rem; text-align: center;"> <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <a href="?page=<?php echo $i; ?>" style="display: inline-block; padding: 5px 10px; background: #333; color: #fff; text-decoration: none; margin: 2px; <?php echo ($i == $page) ? 'background: var(--brand-red);' : ''; ?>">
            <?php echo $i; ?>
        </a>
    <?php endfor; ?>
</div>

<?php include '_footer.php'; ?>