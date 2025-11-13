<?php
include '_header.php';

$product_id = $_GET['product_id'] ?? 0;
$action = $_GET['action'] ?? 'list';
$variation_id = $_GET['id'] ?? 0;
$error = '';
$success = '';

// Product ki details fetch karein
$stmt_prod = $db->prepare("SELECT * FROM products WHERE id = ?");
$stmt_prod->execute([$product_id]);
$product = $stmt_prod->fetch();

if (!$product) {
    echo "<h1>Error</h1><p>Product not found.</p>";
    include '_footer.php';
    exit;
}

// --- Handle Form Submissions (Create/Update) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $type = sanitize($_POST['type']);
    $duration_months = (int)$_POST['duration_months'];
    $price = (float)$_POST['price'];
    $original_price = !empty($_POST['original_price']) ? (float)$_POST['original_price'] : null;
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    try {
        if ($action == 'edit' && $variation_id) {
            // Update
            $stmt = $db->prepare("UPDATE product_variations SET type = ?, duration_months = ?, price = ?, original_price = ?, is_active = ? WHERE id = ? AND product_id = ?");
            $stmt->execute([$type, $duration_months, $price, $original_price, $is_active, $variation_id, $product_id]);
            $success = 'Variation updated successfully!';
        } else {
            // Create
            $stmt = $db->prepare("INSERT INTO product_variations (product_id, type, duration_months, price, original_price, is_active) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$product_id, $type, $duration_months, $price, $original_price, $is_active]);
            $success = 'Variation created successfully!';
        }
        $action = 'list'; // Go back to list view
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}

// --- Handle Deletion ---
if ($action == 'delete' && $variation_id) {
    try {
        $stmt = $db->prepare("DELETE FROM product_variations WHERE id = ? AND product_id = ?");
        $stmt->execute([$variation_id, $product_id]);
        $success = 'Variation deleted successfully!';
        $action = 'list';
    } catch (PDOException $e) {
        $error = 'Failed to delete variation.';
    }
}

// --- Load Data for Views ---
$variation = null;
if (($action == 'edit' || $action == 'add') && $variation_id) {
    $stmt = $db->prepare("SELECT * FROM product_variations WHERE id = ? AND product_id = ?");
    $stmt->execute([$variation_id, $product_id]);
    $variation = $stmt->fetch();
}
if ($action == 'list') {
    $stmt = $db->prepare("SELECT * FROM product_variations WHERE product_id = ? ORDER BY type ASC, duration_months ASC");
    $stmt->execute([$product_id]);
    $variations = $stmt->fetchAll();
}

?>

<h1>Manage Prices for "<?php echo sanitize($product['name']); ?>"</h1>
<p><a href="products.php">&larr; Back to Products List</a></p>

<?php if ($error): ?><div class="message error"><?php echo $error; ?></div><?php endif; ?>
<?php if ($success): ?><div class="message success"><?php echo $success; ?></div><?php endif; ?>


<?php if ($action == 'list'): ?>
    <a href="variations.php?product_id=<?php echo $product_id; ?>&action=add" class="btn-new">Add New Price/Variation</a>
    <div class="admin-table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Type (e.g., Private)</th>
                    <th>Duration</th>
                    <th>Our Price</th>
                    <th>Original Price</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($variations)): ?>
                    <tr><td colspan="6" style="text-align: center;">No prices added yet for this product.</td></tr>
                <?php endif; ?>
                <?php foreach ($variations as $var): ?>
                <tr>
                    <td><?php echo sanitize($var['type']); ?></td>
                    <td><?php echo $var['duration_months']; ?> Month(s)</td>
                    <td><?php echo formatCurrency($var['price']); ?></td>
                    <td><?php echo formatCurrency($var['original_price'] ?? 0); ?></td>
                    <td>
                        <?php if ($var['is_active']): ?>
                            <span class="status-badge status-active">Active</span>
                        <?php else: ?>
                            <span class="status-badge status-expired">Disabled</span>
                        <?php endif; ?>
                    </td>
                    <td class="action-buttons">
                        <a href="variations.php?product_id=<?php echo $product_id; ?>&action=edit&id=<?php echo $var['id']; ?>" class="btn-edit">Edit</a>
                        <a href="variations.php?product_id=<?php echo $product_id; ?>&action=delete&id=<?php echo $var['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure?');">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php elseif ($action == 'add' || $action == 'edit'): ?>
    <h2><?php echo ($action == 'edit') ? 'Edit Variation' : 'Add New Variation'; ?></h2>
    
    <form action="variations.php?product_id=<?php echo $product_id; ?>&action=<?php echo $action; ?><?php echo $variation_id ? '&id='.$variation_id : ''; ?>" method="POST" class="admin-form">
        <div class="form-group">
            <label for="type">Type (e.g., Shared, Private, Semi-Private)</label>
            <input type="text" id="type" name="type" class="form-control" value="<?php echo sanitize($variation['type'] ?? 'Shared'); ?>" required>
        </div>
        
        <div class="form-group">
            <label for="duration_months">Duration (in Months)</label>
            <select id="duration_months" name="duration_months" class="form-control" required>
                <option value="1" <?php echo (isset($variation['duration_months']) && $variation['duration_months'] == 1) ? 'selected' : ''; ?>>1 Month</option>
                <option value="3" <?php echo (isset($variation['duration_months']) && $variation['duration_months'] == 3) ? 'selected' : ''; ?>>3 Months</option>
                <option value="6" <?php echo (isset($variation['duration_months']) && $variation['duration_months'] == 6) ? 'selected' : ''; ?>>6 Months</option>
                <option value="12" <?php echo (isset($variation['duration_months']) && $variation['duration_months'] == 12) ? 'selected' : ''; ?>>12 Months (1 Year)</option>
            </select>
        </div>
        
        <div class="form-grid-2">
            <div class="form-group">
                <label for="price">Our Price (e.g., 700)</label>
                <input type="number" id="price" name="price" class="form-control" value="<?php echo sanitize($variation['price'] ?? '0.00'); ?>" step="0.01" required>
            </div>
            <div class="form-group">
                <label for="original_price">Original Price (Optional)</label>
                <input type="number" id="original_price" name="original_price" class="form-control" value="<?php echo sanitize($variation['original_price'] ?? '0.00'); ?>" step="0.01">
            </div>
        </div>
        
        <div class="form-group">
            <label>
                <input type="checkbox" name="is_active" value="1" <?php echo (isset($variation['is_active']) && $variation['is_active']) ? 'checked' : 'checked'; ?>>
                Active (Visible to users)
            </label>
        </div>
        
        <button type="submit" class="btn btn-primary"><?php echo ($action == 'edit') ? 'Save Changes' : 'Add Price'; ?></button>
        <a href="variations.php?product_id=<?php echo $product_id; ?>" class="btn" style="background: #555; color: #fff; text-decoration: none;">Cancel</a>
    </form>
<?php endif; ?>

<?php include '_footer.php'; ?>