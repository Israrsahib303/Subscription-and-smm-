<?php
include '_header.php';

$action = $_GET['action'] ?? 'list';
$prize_id = $_GET['id'] ?? 0;
$error = '';
$success = '';

// --- Handle Form Submissions (Create/Update) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $label = sanitize($_POST['label']);
    $amount = (float)$_POST['amount'];
    $probability = (int)$_POST['probability'];
    $color = sanitize($_POST['color']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if ($probability <= 0) $probability = 1;
    if ($amount < 0) $amount = 0;

    try {
        if ($action == 'edit' && $prize_id) {
            // Update
            $stmt = $db->prepare("UPDATE wheel_prizes SET label = ?, amount = ?, probability = ?, color = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$label, $amount, $probability, $color, $is_active, $prize_id]);
            $success = 'Prize updated successfully!';
        } else {
            // Create
            $stmt = $db->prepare("INSERT INTO wheel_prizes (label, amount, probability, color, is_active) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$label, $amount, $probability, $color, $is_active]);
            $success = 'Prize created successfully!';
        }
        $action = 'list'; // Go back to list view
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}

// --- Handle Deletion ---
if ($action == 'delete' && $prize_id) {
    try {
        $stmt = $db->prepare("DELETE FROM wheel_prizes WHERE id = ?");
        $stmt->execute([$prize_id]);
        $success = 'Prize deleted successfully!';
        $action = 'list';
    } catch (PDOException $e) {
        $error = 'Failed to delete prize.';
    }
}

// --- Load Data for Views ---
$prize = null;
if (($action == 'edit' || $action == 'add') && $prize_id) {
    $stmt = $db->prepare("SELECT * FROM wheel_prizes WHERE id = ?");
    $stmt->execute([$prize_id]);
    $prize = $stmt->fetch();
}
if ($action == 'list') {
    $stmt = $db->query("SELECT * FROM wheel_prizes ORDER BY amount ASC");
    $prizes = $stmt->fetchAll();
    
    // Calculate total probability
    $total_probability = 0;
    foreach($prizes as $p) {
        if ($p['is_active']) {
            $total_probability += $p['probability'];
        }
    }
}

?>

<h1>Manage Spin Wheel Prizes</h1>

<?php if ($error): ?><div class="message error"><?php echo $error; ?></div><?php endif; ?>
<?php if ($success): ?><div class="message success"><?php echo $success; ?></div><?php endif; ?>

<?php if ($action == 'list'): ?>
    <div class="message info">
        <strong>How Chances Work:</strong> System in tamam "chances" (probabilities) ko jama karta hai (Total: <?php echo $total_probability; ?>) aur us hisab se jeetne ka % nikalta hai.<br>
        <strong>Example:</strong> Agar "PKR 1" ka chance 80 hai aur "PKR 50" ka chance 20 hai, to 80% chance hai ke user PKR 1 jeete ga aur 20% chance hai ke PKR 50 jeete ga.
    </div>

    <a href="wheel_prizes.php?action=add" class="btn-new">Add New Prize</a>
    <div class="admin-table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Label (Text on wheel)</th>
                    <th>Amount (PKR)</th>
                    <th>Color</th>
                    <th>Probability (Chance)</th>
                    <th>Win % (Approx)</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($prizes as $p): ?>
                <tr>
                    <td><?php echo sanitize($p['label']); ?></td>
                    <td><?php echo formatCurrency($p['amount']); ?></td>
                    <td>
                        <div style="width: 50px; height: 20px; background-color: <?php echo sanitize($p['color']); ?>; border: 1px solid #fff;"></div>
                    </td>
                    <td><?php echo $p['probability']; ?></td>
                    <td>
                        <?php if ($p['is_active'] && $total_probability > 0): ?>
                            <?php echo number_format(($p['probability'] / $total_probability) * 100, 2); ?>%
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($p['is_active']): ?>
                            <span class="status-badge status-active">Active</span>
                        <?php else: ?>
                            <span class="status-badge status-expired">Disabled</span>
                        <?php endif; ?>
                    </td>
                    <td class="action-buttons">
                        <a href="wheel_prizes.php?action=edit&id=<?php echo $p['id']; ?>" class="btn-edit">Edit</a>
                        <a href="wheel_prizes.php?action=delete&id=<?php echo $p['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure?');">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php elseif ($action == 'add' || $action == 'edit'): ?>
    <h2><?php echo ($action == 'edit') ? 'Edit Prize' : 'Add New Prize'; ?></h2>
    
    <form action="wheel_prizes.php?action=<?php echo $action; ?><?php echo $prize_id ? '&id='.$prize_id : ''; ?>" method="POST" class="admin-form">
        
        <div class="form-group">
            <label for="label">Label (Text on wheel, e.g., "PKR 50")</label>
            <input type="text" id="label" name="label" class="form-control" value="<?php echo sanitize($prize['label'] ?? 'PKR 10'); ?>" required>
        </div>
        
        <div class="form-group">
            <label for="amount">Amount (Kitne paise milen ge? "Try Again" ke liye 0)</label>
            <input type="number" id="amount" name="amount" class="form-control" value="<?php echo sanitize($prize['amount'] ?? '10.00'); ?>" step="0.01" required>
        </div>
        
        <div class="form-group">
            <label for="probability">Probability (Chance)</label>
            <input type="number" id="probability" name="probability" class="form-control" value="<?php echo sanitize($prize['probability'] ?? '10'); ?>" required>
        </div>
        
        <div class="form-group">
            <label for="color">Wheel Slice Color (Color Code)</label>
            <input type="text" id="color" name="color" class="form-control color-input" value="<?php echo sanitize($prize['color'] ?? '#0D6EFD'); ?>">
        </div>
        
        <div class="form-group">
            <label>
                <input type="checkbox" name="is_active" value="1" <?php echo (isset($prize['is_active']) && $prize['is_active']) ? 'checked' : 'checked'; ?>>
                Active (Show on wheel)
            </label>
        </div>
        
        <button type="submit" class="btn btn-primary"><?php echo ($action == 'edit') ? 'Save Changes' : 'Create Prize'; ?></button>
        <a href="wheel_prizes.php" class="btn" style="background: #555; color: #fff; text-decoration: none;">Cancel</a>
    </form>
    
    <script>
    // Color input preview (Pehle se maujood)
    document.addEventListener('DOMContentLoaded', function() {
        const colorInputs = document.querySelectorAll('.color-input');
        function updateColorPreview(input) { input.style.backgroundColor = input.value; }
        colorInputs.forEach(function(input) {
            updateColorPreview(input);
            input.addEventListener('input', function() { updateColorPreview(this); });
        });
    });
    </script>
    
<?php endif; ?>

<?php include '_footer.php'; ?>