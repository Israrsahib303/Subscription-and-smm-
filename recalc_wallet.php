<?php
/**
 * Cron Job: Recalculate Wallet Balances
 *
 * This script is a maintenance utility. It recalculates every user's balance
 * based on the sum of their transactions in the 'wallet_ledger' table.
 *
 * This should be run infrequently (e.g., once per day) to fix any potential
 * discrepancies that might arise from failed transactions or bugs.
 *
 * Command: /usr/local/bin/php /home/YOUR_CPANEL_USER/public_html/subhub_v6.3/includes/cron/recalc_wallet.php
 */

// Set directory context
chdir(dirname(__DIR__));
require_once 'db.php';

$log_file = '../../assets/logs/wallet_recalc.log';
$log_message = "Wallet Recalc Cron Started: " . date('Y-m-d H:i:s') . "\n";
echo $log_message;

try {
    $db->beginTransaction();

    // Get all users (excluding admins, or include them if they can have balances)
    $users = $db->query("SELECT id, email, balance FROM users WHERE is_admin = 0")->fetchAll();

    $fix_count = 0;
    $log_message .= "Processing " . count($users) . " users...\n";

    foreach ($users as $user) {
        $user_id = $user['id'];
        $current_balance = (float)$user['balance'];

        // Calculate the "true" balance from the ledger
        $stmt = $db->prepare("
            SELECT
                SUM(CASE WHEN type = 'credit' THEN amount ELSE 0 END) -
                SUM(CASE WHEN type = 'debit' THEN amount ELSE 0 END)
            AS true_balance
            FROM wallet_ledger
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
        $calculated_balance = (float)$stmt->fetchColumn();

        // Compare and update if different
        if (abs($current_balance - $calculated_balance) > 0.001) { // Use a small tolerance for float comparison
            $stmt = $db->prepare("UPDATE users SET balance = ? WHERE id = ?");
            $stmt->execute([$calculated_balance, $user_id]);

            $log_message .= "  FIXED: User ID {$user_id} ({$user['email']}). Was: {$current_balance}, Recalculated: {$calculated_balance}\n";
            $fix_count++;
        }
    }

    $db->commit();

    $log_message .= "Cron Finished. Total users checked: " . count($users) . ". Total balances corrected: {$fix_count}.\n";
    echo $log_message;
    file_put_contents($log_file, $log_message, FILE_APPEND);

} catch (Exception $e) {
    $db->rollBack();
    $error_message = "Error: " . $e->getMessage() . "\n";
    echo $error_message;
    file_put_contents($log_file, $error_message, FILE_APPEND);
}
?>