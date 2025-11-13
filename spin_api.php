<?php
// Yeh file "Spin" button ka backend logic hai (AJAX)
require_once __DIR__ . '/../includes/helpers.php';
requireLogin();
require_once __DIR__ . '/../includes/wallet.class.php';

header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => 'An unknown error occurred.',
    'prize_index' => 0, // Wheel ko kahan rokna hai
    'prize_label' => 'Try Again'
];

try {
    // 1. Check karein ke feature enabled hai
    if (empty($GLOBALS['settings']['daily_spin_enabled']) || $GLOBALS['settings']['daily_spin_enabled'] != '1') {
        throw new Exception('Spin & Win is currently disabled.');
    }

    $user_id = $_SESSION['user_id'];
    $cooldown_hours = (int)($GLOBALS['settings']['daily_spin_cooldown_hours'] ?? 24);

    // 2. User ka last spin time check karein
    $stmt_user = $db->prepare("SELECT last_spin_time FROM users WHERE id = ?");
    $stmt_user->execute([$user_id]);
    $user = $stmt_user->fetch();
    
    if ($user['last_spin_time']) {
        $last_spin = strtotime($user['last_spin_time']);
        $next_spin_time = $last_spin + ($cooldown_hours * 3600);
        
        if (time() < $next_spin_time) {
            $remaining = $next_spin_time - time();
            throw new Exception('You can spin again in ' . gmdate("H:i:s", $remaining));
        }
    }

    // 3. Tamam active prizes database se fetch karein
    $stmt_prizes = $db->query("SELECT * FROM wheel_prizes WHERE is_active = 1");
    $prizes = $stmt_prizes->fetchAll();
    
    if (empty($prizes)) {
        throw new Exception('No prizes are configured. Please contact admin.');
    }

    // 4. "100x Better" Prize Selection (Probability ke hisab se)
    $total_probability = 0;
    $weighted_list = [];
    foreach ($prizes as $prize) {
        $total_probability += (int)$prize['probability'];
        $weighted_list[$prize['id']] = (int)$prize['probability'];
    }
    
    $random_num = rand(1, $total_probability);
    $won_prize_id = 0;
    $current_weight = 0;

    foreach ($weighted_list as $id => $weight) {
        $current_weight += $weight;
        if ($random_num <= $current_weight) {
            $won_prize_id = $id;
            break;
        }
    }

    // 5. Jeeta hua prize find karein
    $won_prize = null;
    $prize_index = 0; // Wheel ko kahan rokna hai
    foreach ($prizes as $index => $prize) {
        if ($prize['id'] == $won_prize_id) {
            $won_prize = $prize;
            $prize_index = $index;
            break;
        }
    }

    if (!$won_prize) {
        throw new Exception('Prize calculation error.');
    }

    // 6. Transaction shuru karein
    $db->beginTransaction();
    
    $amount_won = (float)$won_prize['amount'];
    
    // 7. User ka spin time update karein (taake woh dobara na kar sake)
    $stmt_update_user = $db->prepare("UPDATE users SET last_spin_time = NOW() WHERE id = ?");
    $stmt_update_user->execute([$user_id]);

    // 8. User ka wallet update karein (agar prize 0 se zyada hai)
    if ($amount_won > 0) {
        $wallet = new Wallet($db);
        $note = "Daily Spin & Win Bonus (" . $won_prize['label'] . ")";
        $wallet->addCredit($user_id, $amount_won, 'admin_adjust', 1, $note);
    }
    
    // 9. Log mein entry karein
    $stmt_log = $db->prepare("INSERT INTO wheel_spins_log (user_id, prize_id, amount_won) VALUES (?, ?, ?)");
    $stmt_log->execute([$user_id, $won_prize['id'], $amount_won]);
    
    // 10. Kamyab!
    $db->commit();

    $response['success'] = true;
    $response['message'] = ($amount_won > 0) ? 'Congratulations! You won ' . $won_prize['label'] . '!' : 'Better luck next time!';
    $response['prize_index'] = $prize_index;
    $response['prize_label'] = $won_prize['label'];

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    $response['message'] = $e->getMessage();
}

// JSON response wapis bhejein
echo json_encode($response);
exit;