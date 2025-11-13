<?php
include '_header.php';

$action = $_GET['action'] ?? 'list';
$category_id = $_GET['id'] ?? 0;
$error = '';
$success = '';

// --- Handle Form Submissions (Create/Update) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = sanitize($_POST['name']);
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
    $icon = sanitize($_POST['icon']);
    $sort_order = (int)$_POST['sort_order'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    try {
        if ($action == 'edit' && $category_id) {
            // Update
            $stmt = $db->prepare("UPDATE categories SET name = ?, slug = ?, icon = ?, sort_order = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$name, $slug, $icon, $sort_order, $is_active, $category_id]);
            $success = 'Category updated successfully!';
        } else {
            // Create
            $stmt = $db->prepare("INSERT INTO categories (name, slug, icon, sort_order, is_active) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $slug, $icon, $sort_order, $is_active]);
            $success = 'Category created successfully!';
        }
        $action = 'list'; // Go back to list view
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}

// --- Handle Deletion ---
if ($action == 'delete' && $category_id) {
    try {
        $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$category_id]);
        $success = 'Category deleted successfully!';
        $action = 'list';
    } catch (PDOException $e) {
        $error = 'Failed to delete category. It might be linked to products.';
    }
}

// --- Load Data for Views ---
$category = null;
if (($action == 'edit' || $action == 'add') && $category_id) {
    $stmt = $db->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$category_id]);
    $category = $stmt->fetch();
}
if ($action == 'list') {
    $stmt = $db->query("SELECT * FROM categories ORDER BY sort_order ASC, name ASC");
    $categories = $stmt->fetchAll();
}

?>

<h1>Manage Categories</h1>

<?php if ($error): ?><div class="message error"><?php echo $error; ?></div><?php endif; ?>
<?php if ($success): ?><div class="message success"><?php echo $success; ?></div><?php endif; ?>


<?php if ($action == 'list'): ?>
    <a href="categories.php?action=add" class="btn-new">Add New Category</a>
    <div class="admin-table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Icon</th>
                    <th>Name</th>
                    <th>Slug</th>
                    <th>Sort Order</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $cat): ?>
                <tr>
                    <td><img src="../assets/img/icons/<?php echo sanitize($cat['icon']); ?>" alt="" style="width: 30px;"></td>
                    <td><?php echo sanitize($cat['name']); ?></td>
                    <td><?php echo sanitize($cat['slug']); ?></td>
                    <td><?php echo $cat['sort_order']; ?></td>
                    <td>
                        <?php if ($cat['is_active']): ?>
                            <span class="status-badge status-active">Active</span>
                        <?php else: ?>
                            <span class="status-badge status-expired">Disabled</span>
                        <?php endif; ?>
                    </td>
                    <td class="action-buttons">
                        <a href="categories.php?action=edit&id=<?php echo $cat['id']; ?>" class="btn-edit">Edit</a>
                        <a href="categories.php?action=delete&id=<?php echo $cat['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure?');">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php elseif ($action == 'add' || $action == 'edit'): ?>
    <h2><?php echo ($action == 'edit') ? 'Edit Category' : 'Add New Category'; ?></h2>
    
    <form action="categories.php?action=<?php echo $action; ?><?php echo $category_id ? '&id='.$category_id : ''; ?>" method="POST" class="admin-form">
        <div class="form-group">
            <label for="name">Category Name (e.g., Streaming)</label>
            <input type="text" id="name" name="name" class="form-control" value="<?php echo sanitize($category['name'] ?? ''); ?>" required>
        </div>
        
        <div class="form-group">
            <label for="icon">Icon Filename (e.g., netflix.svg)</label>
            <input type="text" id="icon" name="icon" class="form-control" value="<?php echo sanitize($category['icon'] ?? ''); ?>">
        </div>
        
        <div class="form-group">
            <label for="sort_order">Sort Order (0 = top)</label>
            <input type="number" id="sort_order" name="sort_order" class="form-control" value="<?php echo sanitize($category['sort_order'] ?? '0'); ?>" required>
        </div>
        
        <div class="form-group">
            <label>
                <input type="checkbox" name="is_active" value="1" <?php echo (isset($category['is_active']) && $category['is_active']) ? 'checked' : 'checked'; ?>>
                Active (Visible to users)
            </label>
        </div>
        
        <button type="submit" class="btn btn-primary"><?php echo ($action == 'edit') ? 'Save Changes' : 'Create Category'; ?></button>
        <a href="categories.php" class="btn" style="background: #555; color: #fff; text-decoration: none;">Cancel</a>
    </form>
<?php endif; ?>

<?php include '_footer.php'; ?>