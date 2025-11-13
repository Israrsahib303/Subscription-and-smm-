<?php
include '_header.php';

$error = '';
$success = '';

// --- NAYI "BEAST" LOGIC: Automatic Category Sync ---
// 1. Pehle 'smm_services' se tamam unique "clean" categories nikalen
try {
    $stmt_services_cats = $db->query("SELECT DISTINCT category FROM smm_services");
    $service_categories = $stmt_services_cats->fetchAll(PDO::FETCH_COLUMN);
    
    $clean_categories = [];
    foreach ($service_categories as $category_name) {
        if (preg_match('/^([\w\s]+?)(?:\s\»\s|\s\-\s|\s\d|$)/i', $category_name, $matches)) {
            $clean_category = trim($matches[1]);
        } else {
            $clean_category = trim($category_name);
        }
        $clean_categories[$clean_category] = 1; // Unique list banayein
    }

    // 2. Ab in categories ko 'smm_categories' table mein add karein (agar pehle se nahi hain)
    if (!empty($clean_categories)) {
        $stmt_insert = $db->prepare("INSERT IGNORE INTO smm_categories (name) VALUES (?)");
        foreach (array_keys($clean_categories) as $name) {
            $stmt_insert->execute([$name]);
        }
    }
} catch (PDOException $e) {
    $error = "Failed to sync categories: " . $e->getMessage();
}
// --- SYNC LOGIC KHATAM ---


// --- Handle Form Submission (Icon Update) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_icons'])) {
    $icons = $_POST['icons'] ?? [];
    $sort_orders = $_POST['sort_orders'] ?? [];
    
    try {
        $db->beginTransaction();
        $stmt_update = $db->prepare("UPDATE smm_categories SET icon_filename = ?, sort_order = ? WHERE id = ?");
        
        foreach ($icons as $id => $icon_name) {
            $sort = (int)($sort_orders[$id] ?? 0);
            $stmt_update->execute([sanitize($icon_name), $sort, $id]);
        }
        
        $db->commit();
        $success = 'Category icons and sorting updated successfully!';
    } catch (PDOException $e) {
        $db->rollBack();
        $error = 'Database error: ' . $e->getMessage();
    }
}

// --- Load Data for View ---
try {
    $stmt_list = $db->query("SELECT * FROM smm_categories ORDER BY sort_order ASC, name ASC");
    $categories = $stmt_list->fetchAll();
} catch (PDOException $e) {
    $error = 'Failed to fetch categories: ' . $e->getMessage();
    $categories = [];
}
?>

<h1>Manage SMM Category Icons</h1>
<p class.="description">
    Aap ki provider ki categories yahan automatically list ho jati hain. <br>
    Aap har category ke liye `assets/img/icons/` folder mein maujood icon ka naam (e.g., `tiktok.svg` or `youtube.png`) yahan likh sakte hain.
</p>

<?php if ($error): ?><div class="message error"><?php echo $error; ?></div><?php endif; ?>
<?php if ($success): ?><div class="message success"><?php echo $success; ?></div><?php endif; ?>

<form action="smm_categories.php" method="POST" class="admin-form">
    <input type="hidden" name="update_icons" value="1">
    
    <div class="admin-table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th style="width: 100px;">Current Icon</th>
                    <th>Category Name</th>
                    <th>Icon Filename (e.g., tiktok.svg)</th>
                    <th style="width: 100px;">Sort Order</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($categories)): ?>
                    <tr><td colspan="4" style="text-align: center;">No categories found. Please sync SMM services first.</td></tr>
                <?php endif; ?>
                
                <?php foreach ($categories as $cat): ?>
                <tr>
                    <td>
                        <?php if (!empty($cat['icon_filename'])): ?>
                            <img src="../assets/img/icons/<?php echo sanitize($cat['icon_filename']); ?>" alt="" style="width: 30px; height: 30px; background: #fff; border-radius: 4px; padding: 2px;">
                        <?php else: ?>
                            (None)
                        <?php endif; ?>
                    </td>
                    <td><strong><?php echo sanitize($cat['name']); ?></strong></td>
                    <td>
                        <input type="text" name="icons[<?php echo $cat['id']; ?>]" class="form-control" 
                               value="<?php echo sanitize($cat['icon_filename']); ?>" 
                               placeholder="e.g., youtube.svg">
                    </td>
                    <td>
                        <input type="number" name="sort_orders[<?php echo $cat['id']; ?>]" class="form-control" 
                               value="<?php echo (int)$cat['sort_order']; ?>">
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <button type="submit" class="btn btn-primary" style="margin-top: 1.5rem; width: auto;">Save All Changes</button>
</form>

<?php include '_footer.php'; ?>