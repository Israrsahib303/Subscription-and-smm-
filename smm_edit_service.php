<?php
include '_header.php';

$is_category_edit = isset($_GET['category']);
$id = (int)($_GET['id'] ?? 0);
$category_name = sanitize($_GET['category'] ?? '');
$error = '';
$message = '';
$service = null;

if ($is_category_edit) {
    // Category Edit Mode
    $page_title = "Edit Category Name: " . $category_name;
    $current_name = $category_name;
} elseif ($id) {
    // Service Edit Mode
    $page_title = "Edit SMM Service";
    try {
        $stmt = $db->prepare("
            SELECT s.*, p.name as provider_name
            FROM smm_services s
            JOIN smm_providers p ON s.provider_id = p.id
            WHERE s.id = ?
        ");
        $stmt->execute([$id]);
        $service = $stmt->fetch();
        if (!$service) {
            $error = "Service not found.";
        }
    } catch (PDOException $e) {
        $error = "Database Error.";
    }
} else {
    $error = "Invalid request.";
}

// --- Form Submission Handle Karein ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($is_category_edit) {
        // Category Name Update
        $new_category_name = sanitize($_POST['new_category_name'] ?? '');
        if (empty($new_category_name)) {
            $error = "New category name cannot be empty.";
        } else {
            try {
                // Existing category mein jitni bhi services hain, un sab ka naam badal do
                $stmt = $db->prepare("UPDATE smm_services SET category = ? WHERE category = ?");
                $stmt->execute([$new_category_name, $category_name]);
                $message = "Category name updated successfully from '{$category_name}' to '{$new_category_name}'.";
                
                // Nayi category naam ke saath redirect karein
                header("Location: smm_services.php?success=" . urlencode($message));
                exit;
            } catch (PDOException $e) {
                $error = "Failed to update category name: " . $e->getMessage();
            }
        }
    } elseif ($service) {
        // Single Service Update
        $new_name = sanitize($_POST['name'] ?? '');
        $new_category = sanitize($_POST['category'] ?? '');
        $new_service_rate = (float)($_POST['service_rate'] ?? 0);
        $new_description = sanitize($_POST['description'] ?? '');
        $new_is_active = (int)($_POST['is_active'] ?? 0);

        if (empty($new_name) || empty($new_category) || $new_service_rate <= 0) {
            $error = "Service name, Category, and User Rate are required.";
        } else {
            try {
                $stmt = $db->prepare("
                    UPDATE smm_services SET 
                    name = ?, category = ?, service_rate = ?, description = ?, is_active = ?
                    WHERE id = ?
                ");
                $stmt->execute([$new_name, $new_category, $new_service_rate, $new_description, $new_is_active, $id]);
                $message = "Service #{$id} updated successfully.";
                
                // Updated data ko refresh karein
                $stmt_refresh = $db->prepare("SELECT s.*, p.name as provider_name FROM smm_services s JOIN smm_providers p ON s.provider_id = p.id WHERE s.id = ?");
                $stmt_refresh->execute([$id]);
                $service = $stmt_refresh->fetch(); 
            } catch (PDOException $e) {
                $error = "Failed to update service: " . $e->getMessage();
            }
        }
    }
}
?>

<h1><?php echo $page_title; ?></h1>

<?php if ($error): ?><div class="message error"><?php echo $error; ?></div><?php endif; ?>
<?php if ($message): ?><div class="message success"><?php echo $message; ?></div><?php endif; ?>

<?php if ($is_category_edit && !$error): ?>
    <div class="card">
        <div class="card-header">Rename Category: <?php echo sanitize($category_name); ?></div>
        <div class="card-body">
            <form action="smm_edit_service.php?category=<?php echo urlencode($category_name); ?>" method="POST">
                <div class="form-group">
                    <label for="new_category_name">New Category Name</label>
                    <input type="text" id="new_category_name" name="new_category_name" class="form-control" value="<?php echo sanitize($category_name); ?>" required>
                    <small>All services currently under "<?php echo sanitize($category_name); ?>" will be moved to this new name.</small>
                </div>
                <button type="submit" class="btn btn-primary">Rename Category</button>
            </form>
        </div>
    </div>
<?php elseif ($service): ?>
    <div class="card">
        <div class="card-header">Editing Service #<?php echo $service['id']; ?> (Provider: <?php echo sanitize($service['provider_name']); ?>)</div>
        <div class="card-body">
            <form action="smm_edit_service.php?id=<?php echo $service['id']; ?>" method="POST">
                
                <div class="form-group">
                    <label for="name">Service Name (User-Friendly)</label>
                    <input type="text" id="name" name="name" class="form-control" value="<?php echo sanitize($service['name']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="category">Category Name</label>
                    <input type="text" id="category" name="category" class="form-control" value="<?php echo sanitize($service['category']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="service_rate">User Rate (PKR per 1000)</label>
                    <input type="number" id="service_rate" name="service_rate" class="form-control" step="0.0001" value="<?php echo (float)$service['service_rate']; ?>" required>
                    <small>Provider Rate: <?php echo formatCurrency($service['provider_rate']); ?>. Aap ka Profit Margin: <?php echo formatCurrency($service['service_rate'] - $service['provider_rate']); ?></small>
                </div>

                <div class="form-group">
                    <label for="description">Description / Notes</label>
                    <textarea id="description" name="description" class="form-control" rows="3"><?php echo sanitize($service['description'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="is_active">Status</label>
                    <select id="is_active" name="is_active" class="form-control">
                        <option value="1" <?php echo $service['is_active'] ? 'selected' : ''; ?>>Active (Show to Users)</option>
                        <option value="0" <?php echo !$service['is_active'] ? 'selected' : ''; ?>>Inactive (Hide from Users)</option>
                    </select>
                </div>

                <h3 style="margin-top: 20px; border-top: 1px solid #333; padding-top: 10px; font-size: 1.1rem;">Provider Details (Read-Only)</h3>
                <div class="admin-table-responsive">
                    <table class="admin-table" style="width: 100%;">
                        <tr><th>Provider Service ID</th><td><?php echo sanitize($service['provider_service_id']); ?></td></tr>
                        <tr><th>Min Quantity</th><td><?php echo number_format($service['min']); ?></td></tr>
                        <tr><th>Max Quantity</th><td><?php echo number_format($service['max']); ?></td></tr>
                        <tr><th>Provider ID</th><td><?php echo $service['provider_id']; ?></td></tr>
                    </table>
                </div>

                <button type="submit" class="btn btn-primary" style="margin-top: 20px;">Save Changes</button>
                <a href="smm_services.php" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php include '_footer.php'; ?>
