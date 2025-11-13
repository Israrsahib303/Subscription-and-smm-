<?php
include '_header.php';

// Pagination
$page = $_GET['page'] ?? 1;
$limit = 50;
$offset = ($page - 1) * $limit;

// Search
$search = $_GET['search'] ?? '';
$where = '';
$params = [];
if (!empty($search)) {
    $where = 'WHERE email LIKE ?';
    $params[] = '%' . $search . '%';
}

try {
    $total_records_stmt = $db->prepare("SELECT COUNT(id) FROM users $where");
    $total_records_stmt->execute($params);
    $total_records = $total_records_stmt->fetchColumn();
    $total_pages = ceil($total_records / $limit);

    $stmt = $db->prepare("SELECT * FROM users $where ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key + 1, $value);
    }
    $stmt->execute();
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Failed to fetch users: ' . $e->getMessage();
    $users = [];
    $total_pages = 0;
}
?>

<style>
.api-key-cell {
    max-width: 150px;
    word-break: break-all;
    font-family: monospace;
    font-size: 0.8rem;
    color: #4CAF50;
}
</style>

<h1>Manage Users</h1>

<?php if (isset($error)): ?><div class="message error"><?php echo $error; ?></div><?php endif; ?>
<?php if (isset($_GET['success'])): ?><div class="message success"><?php echo sanitize($_GET['success']); ?></div><?php endif; ?>

<div class="search-box">
    <form action="users.php" method="GET">
        <input type="text" name="search" class="form-control" placeholder="Search by user email..." value="<?php echo sanitize($search); ?>">
    </form>
</div>

<div class="admin-table-responsive">
    <table class="admin-table">
        <thead>
            <tr>
                <th>User ID</th>
                <th>Email</th>
                <th>Balance</th>
                <th>API Key (Reseller)</th>
                <th>Registered</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($users)): ?>
                <tr><td colspan="6" style="text-align: center;">No users found.</td></tr>
            <?php else: ?>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td><?php echo $user['id']; ?></td>
                    <td><?php echo sanitize($user['email']); ?></td>
                    <td><?php echo formatCurrency($user['balance']); ?></td>
                    <td class="api-key-cell"><?php echo sanitize($user['api_key'] ?? 'N/A'); ?></td>
                    <td><?php echo formatDate($user['created_at']); ?></td>
                    <td>
                        </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="pagination" style="margin-top: 1rem; text-align: center;">
    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <a href="?page=<?php echo $i; ?>&search=<?php echo sanitize($search); ?>" 
           style="display: inline-block; padding: 5px 10px; background: #333; color: #fff; text-decoration: none; margin: 2px; <?php echo ($i == $page) ? 'background: var(--brand-red);' : ''; ?>">
            <?php echo $i; ?>
        </a>
    <?php endfor; ?>
</div>

<?php include '_footer.php'; ?>