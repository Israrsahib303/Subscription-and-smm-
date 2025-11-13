<?php
include '_header.php';

// Pagination
$page = $_GET['page'] ?? 1;
$limit = 50;
$offset = ($page - 1) * $limit;

// Search/Filter Logic
$where = '';
$params = [];
$search_query = '';

if (!empty($_GET['search'])) {
    $search_query = sanitize($_GET['search']);
    // Search by Order ID, Link, or User Email
    $where = 'WHERE (o.id = ? OR o.link LIKE ? OR u.email LIKE ?)';
    $params[] = $search_query;
    $params[] = '%' . $search_query . '%';
    $params[] = '%' . $search_query . '%';
}

try {
    // Total records for pagination
    $total_records_stmt = $db->prepare("SELECT COUNT(o.id) FROM smm_orders o LEFT JOIN users u ON o.user_id = u.id $where");
    // Bind parameters for search (for count)
    foreach ($params as $key => $value) {
        $total_records_stmt->bindValue($key + 1, $value);
    }
    $total_records_stmt->execute();
    $total_records = $total_records_stmt->fetchColumn();
    $total_pages = ceil($total_records / $limit);

    // Fetch orders
    $stmt = $db->prepare("
        SELECT o.*, u.email as user_email, s.name as service_name, p.name as provider_name
        FROM smm_orders o
        JOIN users u ON o.user_id = u.id
        JOIN smm_services s ON o.service_id = s.id
        JOIN smm_providers p ON s.provider_id = p.id
        $where
        ORDER BY o.created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    
    // Bind parameters for search (for main query)
    foreach ($params as $key => $value) {
        $stmt->bindValue($key + 1, $value);
    }
    
    // Bind limit and offset
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $smm_orders = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = 'Failed to fetch SMM orders: ' . $e->getMessage();
    $smm_orders = [];
    $total_pages = 0;
}
?>

<h1>SMM Orders</h1>

<?php if (isset($error)): ?><div class="message error"><?php echo $error; ?></div><?php endif; ?>

<div class="search-box">
    <form action="smm_orders.php" method="GET">
        <input type="text" name="search" class="form-control" placeholder="Search by Order ID, Link, or User Email..." value="<?php echo sanitize($search_query); ?>">
    </form>
</div>

<div class="admin-table-responsive">
    <table class="admin-table">
        <thead>
            <tr>
                <th>Order ID</th>
                <th>Provider ID</th>
                <th>Provider</th>
                <th>User</th>
                <th>Service</th>
                <th>Quantity</th>
                <th>Charge</th>
                <th>Status</th>
                <th>Link</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($smm_orders)): ?>
                <tr><td colspan="9" style="text-align: center;">No SMM orders found.</td></tr>
            <?php else: ?>
                <?php foreach ($smm_orders as $order): ?>
                <tr>
                    <td><strong>#<?php echo $order['id']; ?></strong></td>
                    <td><?php echo $order['provider_order_id'] ?? 'N/A'; ?></td>
                    <td><?php echo sanitize($order['provider_name']); ?></td>
                    <td><?php echo sanitize($order['user_email']); ?></td>
                    <td><?php echo sanitize($order['service_name']); ?></td>
                    <td><?php echo number_format($order['quantity']); ?></td>
                    <td><?php echo formatCurrency($order['charge']); ?></td>
                    <td>
                        <span class="status-badge status-<?php echo str_replace(' ', '_', strtolower($order['status'])); ?>">
                            <?php echo ucfirst($order['status']); ?>
                        </span>
                    </td>
                    <td style="word-break: break-all; max-width: 200px;"><a href="<?php echo sanitize($order['link']); ?>" target="_blank"><?php echo substr(sanitize($order['link']), 0, 40); ?>...</a></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="pagination" style="margin-top: 1rem; text-align: center;">
    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <a href="?page=<?php echo $i; ?>&search=<?php echo sanitize($search_query); ?>" 
           style="display: inline-block; padding: 5px 10px; background: #333; color: #fff; text-decoration: none; margin: 2px; <?php echo ($i == $page) ? 'background: var(--brand-red);' : ''; ?>">
            <?php echo $i; ?>
        </a>
    <?php endfor; ?>
</div>

<?php include '_footer.php'; ?>