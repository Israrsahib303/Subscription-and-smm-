<?php
include '_header.php';

$action = $_GET['action'] ?? 'list';
$product_id = $_GET['id'] ?? 0;
$error = '';
$success = '';

// Categories fetch karein (dropdown ke liye)
$stmt_cats = $db->query("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name ASC");
$categories = $stmt_cats->fetchAll();

$upload_dir = __DIR__ . '/../assets/img/icons/';

// --- Handle Form Submissions (Create/Update) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = sanitize($_POST['name']);
    $description = sanitize($_POST['description']);
    $proof_link = sanitize($_POST['proof_link']); // NAYA FIELD
    $category_id = (int)$_POST['category_id'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
    $icon_name = $_POST['existing_icon'] ?? '';

    // Handle icon upload
    if (isset($_FILES['icon']) && $_FILES['icon']['error'] == 0) {
        $file = $_FILES['icon'];
        if ($file['size'] > 1 * 1024 * 1024) { $error = 'Icon file is too large (Max 1MB).'; }
        else {
            $allowed_types = ['image/svg+xml', 'image/png', 'image/jpeg', 'image/webp'];
            $file_type = mime_content_type($file['tmp_name']);
            if (in_array($file_type, $allowed_types)) {
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $icon_name = $slug . '-' . uniqid() . '.' . $ext;
                if (!move_uploaded_file($file['tmp_name'], $upload_dir . $icon_name)) {
                    $error = 'Failed to upload icon. Check folder permissions.';
                }
            } else { $error = 'Invalid icon file type (Allowed: svg, png, jpg, webp).'; }
        }
    }

    if (empty($error)) {
        try {
            if ($action == 'edit' && $product_id) {
                // Update
                $stmt = $db->prepare("UPDATE products SET name = ?, slug = ?, icon = ?, description = ?, proof_link = ?, category_id = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$name, $slug, $icon_name, $description, $proof_link, $category_id, $is_active, $product_id]);
                $success = 'Product updated successfully!';
            } else {
                // Create
                $stmt = $db->prepare("INSERT INTO products (name, slug, icon, description, proof_link, category_id, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $slug, $icon_name, $description, $proof_link, $category_id, $is_active]);
                $success = 'Product created successfully!';
            }
            $action = 'list'; // Go back to list view
        } catch (PDOException $e) { $error = 'Database error: ' . $e->getMessage(); }
    }
}

// ... (Baaki ka code waisa hi rahe ga) ...
if ($action == 'delete' && $product_id) {
    try {
        $stmt = $db->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $success = 'Product deleted successfully!';
        $action = 'list';
    } catch (PDOException $e) { $error = 'Failed to delete product. It might be linked to existing orders.'; }
}
$product = null;
if (($action == 'edit' || $action == 'add') && $product_id) {
    $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
}
if ($action == 'list') {
    $stmt = $db->query("
        SELECT p.*, c.name as category_name 
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        ORDER BY c.name ASC, p.name ASC
    ");
    $products = $stmt->fetchAll();
}
?>

<h1>Manage Products</h1>
<?php if ($error): ?><div class="message error"><?php echo $error; ?></div><?php endif; ?>
<?php if ($success): ?><div class="message success"><?php echo $success; ?></div><?php endif; ?>

<?php if ($action == 'list'): ?>
    <a href="products.php?action=add" class="btn-new">Add New Product</a>
    <div class="admin-table-responsive">
        <table class="admin-table">
            <thead> <tr> <th>Icon</th> <th>Name</th> <th>Category</th> <th>Status</th> <th>Prices (Variations)</th> <th>Actions</th> </tr> </thead>
            <tbody>
                <?php foreach ($products as $p): ?>
                <tr>
                    <td><img src="../assets/img/icons/<?php echo sanitize($p['icon']); ?>" alt="" style="width: 30px;"></td>
                    <td><?php echo sanitize($p['name']); ?></td>
                    <td><?php echo sanitize($p['category_name'] ?? 'N/A'); ?></td>
                    <td>
                        <?php if ($p['is_active']): ?><span class="status-badge status-active">Active</span>
                        <?php else: ?><span class="status-badge status-expired">Disabled</span><?php endif; ?>
                    </td>
                    <td>
                        <a href="variations.php?product_id=<?php echo $p['id']; ?>" class="btn-edit" style="background: #007bff; color: white;"> Manage Prices </a>
                    </td>
                    <td class="action-buttons">
                        <a href="products.php?action=edit&id=<?php echo $p['id']; ?>" class="btn-edit">Edit</a>
                        <a href="products.php?action=delete&id=<?php echo $p['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure?');">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php elseif ($action == 'add' || $action == 'edit'): ?>
    <h2><?php echo ($action == 'edit') ? 'Edit Product' : 'Add New Product'; ?></h2>
    
    <form action="products.php?action=<?php echo $action; ?><?php echo $product_id ? '&id='.$product_id : ''; ?>" method="POST" enctype="multipart/form-data" class="admin-form">
        <div class="form-group">
            <label for="name">Product Name (e.g., Netflix)</label>
            <input type="text" id="name" name="name" class="form-control" value="<?php echo sanitize($product['name'] ?? ''); ?>" required>
        </div>
        <div class="form-group">
            <label for="category_id">Category</label>
            <select id="category_id" name="category_id" class="form-control" required>
                <option value="">-- Select Category --</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat['id']; ?>" <?php echo (isset($product['category_id']) && $product['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                        <?php echo sanitize($cat['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="description">Description (What's included?)</label>
            <textarea id="description" name="description" class="form-control" rows="4"><?php echo sanitize($product['description'] ?? ''); ?></textarea>
        </div>
        
        <div class="form-group">
            <label for="proof_link">Proof Link (Optional, e.g., https://netflix.com/price)</label>
            <input type="text" id="proof_link" name="proof_link" class="form-control" value="<?php echo sanitize($product['proof_link'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label for="icon">Product Icon (svg, png, jpg)</label>
            <input type="file" id="icon" name="icon" class="form-control" accept="image/svg+xml,image/png,image/jpeg,image/webp">
            <?php if (isset($product['icon']) && $product['icon']): ?>
                <p style="margin-top: 10px;">Current: <img src="../assets/img/icons/<?php echo sanitize($product['icon']); ?>" alt="" style="width: 30px; height: 30px; vertical-align: middle;"></p>
                <input type="hidden" name="existing_icon" value="<?php echo sanitize($product['icon']); ?>">
            <?php endif; ?>
        </div>
        <div class="form-group">
            <label>
                <input type="checkbox" name="is_active" value="1" <?php echo (isset($product['is_active']) && $product['is_active']) ? 'checked' : 'checked'; ?>>
                Active (Visible to users)
            </label>
        </div>
        <button type="submit" class="btn btn-primary"><?php echo ($action == 'edit') ? 'Save Changes' : 'Create Product'; ?></button>
        <a href="products.php" class="btn" style="background: #555; color: #fff; text-decoration: none;">Cancel</a>
    </form>
<?php endif; ?>

<?php include '_footer.php'; ?>