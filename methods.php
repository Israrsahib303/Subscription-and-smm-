<?php
include '_header.php';

$action = $_GET['action'] ?? 'list';
$method_id = $_GET['id'] ?? 0;
$error = '';
$success = '';

// --- Handle Form Submissions (Create/Update) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Basic Details
    $name = sanitize($_POST['name']);
    $account_name = sanitize($_POST['account_name']);
    $account_number = sanitize($_POST['account_number']);
    $note = sanitize($_POST['note']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $icon_path = sanitize($_POST['icon_path'] ?? 'default.png'); 
    
    // --- NAYE MIN/MAX FIELDS ---
    $min_amount = (float)($_POST['min_amount'] ?? 0);
    $max_amount = (float)($_POST['max_amount'] ?? 0);

    // Auto Settings
    $is_auto = isset($_POST['is_auto']) ? 1 : 0;
    $auto_mail_server = sanitize($_POST['auto_mail_server']);
    $auto_email_user = sanitize($_POST['auto_email_user']);
    $auto_email_pass = sanitize($_POST['auto_email_pass']);

    try {
        if ($action == 'edit' && $method_id) {
            // Update
            $stmt = $db->prepare("
                UPDATE payment_methods 
                SET name = ?, icon_path = ?, account_name = ?, account_number = ?, note = ?, 
                    min_amount = ?, max_amount = ?, is_active = ?, 
                    is_auto = ?, auto_mail_server = ?, auto_email_user = ?, auto_email_pass = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $name, $icon_path, $account_name, $account_number, $note, 
                $min_amount, $max_amount, $is_active, 
                $is_auto, $auto_mail_server, $auto_email_user, $auto_email_pass, 
                $method_id
            ]);
            $success = 'Payment method updated!';
        } else {
            // Create
            $stmt = $db->prepare("
                INSERT INTO payment_methods (name, icon_path, account_name, account_number, note, 
                                           min_amount, max_amount, is_active, 
                                           is_auto, auto_mail_server, auto_email_user, auto_email_pass) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $name, $icon_path, $account_name, $account_number, $note,
                $min_amount, $max_amount, $is_active,
                $is_auto, $auto_mail_server, $auto_email_user, $auto_email_pass
            ]);
            $success = 'Payment method created!';
        }
        $action = 'list';
    } catch (PDOException $e) {
        // --- YEH THA FIX (Extra quote hata diya) ---
        $error = 'Database error: ' . $e->getMessage();
    }
}

// --- Handle Deletion ---
if ($action == 'delete' && $method_id) {
    try {
        $stmt = $db->prepare("DELETE FROM payment_methods WHERE id = ?");
        $stmt->execute([$method_id]);
        $success = 'Payment method deleted!';
        $action = 'list';
    } catch (PDOException $e) {
        $error = 'Failed to delete method.';
    }
}

// --- Load Data for Views ---
$method = null;
if (($action == 'edit' || $action == 'add') && $method_id) {
    $stmt = $db->prepare("SELECT * FROM payment_methods WHERE id = ?");
    $stmt->execute([$method_id]);
    $method = $stmt->fetch();
}
if ($action == 'list') {
    $stmt = $db->query("SELECT * FROM payment_methods ORDER BY name ASC");
    $methods = $stmt->fetchAll();
}
?>

<h1>Manage Payment Methods</h1>

<?php if ($error): ?><div class="message error"><?php echo $error; ?></div><?php endif; ?>
<?php if ($success): ?><div class="message success"><?php echo $success; ?></div><?php endif; ?>

<?php if ($action == 'list'): ?>
    <a href="methods.php?action=add" class="btn-new">Add New Method</a>
    <div class="admin-table-responsive"> <table class="admin-table">
            <thead>
                <tr>
                    <th>Icon</th>
                    <th>Name</th>
                    <th>Account Name</th>
                    <th>Status</th>
                    <th>Automation</th> <th>Limits</th> <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($methods as $m): ?>
                <tr>
                    <td><img src="../assets/img/methods/<?php echo sanitize($m['icon_path']); ?>" alt=""></td>
                    <td><?php echo sanitize($m['name']); ?></td>
                    <td><?php echo sanitize($m['account_name']); ?></td>
                    <td>
                        <?php if ($m['is_active']): ?>
                            <span class="status-badge status-active">Active</span> <?php else: ?>
                            <span class="status-badge status-expired">Disabled</span> <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($m['is_auto']): ?>
                            <span class="status-badge" style="background: #007bff; color: white;">Auto</span> <?php else: ?>
                            <span class="status-badge" style="background: #555; color: white;">Manual</span> <?php endif; ?>
                    </td>
                    <td>
                        Min: <?php echo formatCurrency($m['min_amount']); ?><br>
                        Max: <?php echo ($m['max_amount'] > 0) ? formatCurrency($m['max_amount']) : 'None'; ?>
                    </td>
                    <td class="action-buttons"> <a href="methods.php?action=edit&id=<?php echo $m['id']; ?>" class="btn-edit">Edit</a> <a href="methods.php?action=delete&id=<?php echo $m['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure?');">Delete</a> </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php elseif ($action == 'add' || $action == 'edit'): ?>
    <h2><?php echo ($action == 'edit') ? 'Edit Method' : 'Add New Method'; ?></h2>
    
    <form action="methods.php?action=<?php echo $action; ?><?php echo $method_id ? '&id='.$method_id : ''; ?>" method="POST" class="admin-form"> <fieldset>
            <legend>Basic Details</legend>
            <div class="form-grid-2"> <div class="form-group"> <label for="name">Method Name (e.g., NayaPay)</label>
                    <input type="text" id="name" name="name" class="form-control" value="<?php echo sanitize($method['name'] ?? ''); ?>" required> </div>
                <div class="form-group"> <label for="icon_path">Icon Filename (e.g., nayapay.png)</label>
                    <input type="text" id="icon_path" name="icon_path" class="form-control" value="<?php echo sanitize($method['icon_path'] ?? ''); ?>" required> </div>
            </div>
            <div class="form-grid-2"> <div class="form-group"> <label for="account_name">Account Holder Name</label>
                    <input type="text" id="account_name" name="account_name" class="form-control" value="<?php echo sanitize($method['account_name'] ?? ''); ?>" required> </div>
                <div class="form-group"> <label for="account_number">Account Number / IBAN</label>
                    <input type="text" id="account_number" name="account_number" class="form-control" value="<?php echo sanitize($method['account_number'] ?? ''); ?>" required> </div>
            </div>
            
            <div class="form-grid-2"> <div class="form-group"> <label for="min_amount">Min Amount (0 = no limit)</label>
                    <input type="number" id="min_amount" name="min_amount" class="form-control" value="<?php echo sanitize($method['min_amount'] ?? '0.00'); ?>" step="0.01"> </div>
                <div class="form-group"> <label for="max_amount">Max Amount (0 = no limit)</label>
                    <input type="number" id="max_amount" name="max_amount" class="form-control" value="<?php echo sanitize($method['max_amount'] ?? '0.00'); ?>" step="0.01"> </div>
            </div>
            
            <div class="form-group"> <label for="note">Note (Optional, e.g., Min deposit)</label>
                <textarea id="note" name="note" class="form-control"><?php echo sanitize($method['note'] ?? ''); ?></textarea> </div>
            <div class="form-group"> <label><input type="checkbox" name="is_active" value="1" <?php echo (isset($method['is_active']) && $method['is_active']) ? 'checked' : ''; ?>> Active (Show to user)</label>
            </div>
        </fieldset>

        <fieldset style="margin-top: 2rem; border-color: #4CAF50;">
            <legend>Automation Settings (Email Parsing)</legend>
            <div class="form-group"> <label>
                    <input type="checkbox" name="is_auto" value="1" <?php echo (isset($method['is_auto']) && $method['is_auto']) ? 'checked' : ''; ?>>
                    Enable Auto-Verification (Email)
                </label>
            </div>
            <div class="form-group"> <label for="auto_mail_server">Mail Server (e.g., test.israrliaqat.shop)</label>
                <input type="text" id="auto_mail_server" name="auto_mail_server" class="form-control" value="<?php echo sanitize($method['auto_mail_server'] ?? ''); ?>"> </div>
            <div class="form-group"> <label for="auto_email_user">Email Address (e.g., payments@test.israrliaqat.shop)</label>
                <input type="text" id="auto_email_user" name="auto_email_user" class="form-control" value="<?php echo sanitize($method['auto_email_user'] ?? ''); ?>"> </div>
            <div class="form-group"> <label for="auto_email_pass">Email Password</label>
                <input type="password" id="auto_email_pass" name="auto_email_pass" class="form-control" value="<?php echo sanitize($method['auto_email_pass'] ?? ''); ?>"> </div>
        </fieldset>

        <button type="submit" class="btn btn-primary" style="margin-top: 1.5rem;">Save Method</button> </form>
<?php endif; ?>

<?php include '_footer.php'; ?>