<?php
class Wallet {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function getBalance($user_id) {
        $stmt = $this->db->prepare("SELECT balance FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        return (float) $stmt->fetchColumn();
    }

    /**
     * Naya (FIXED) addCredit function.
     * Yeh function ab transaction khud shuru nahi karta.
     * Yeh farz karta hai ke transaction pehle hi 'panel/orders.php' ya 'panel/payments.php' mein shuru ho chuki hai.
     */
    public function addCredit($user_id, $amount, $ref_type, $ref_id, $note = '') {
        try {
            // Transaction (beginTransaction/commit) yahan se hata di gayi hai.

            // 1. User ka balance update karein
            $stmt = $this->db->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
            $stmt->execute([$amount, $user_id]);

            // 2. Ledger mein entry karein
            $stmt_ledger = $this->db->prepare("INSERT INTO wallet_ledger (user_id, type, amount, ref_type, ref_id, note) VALUES (?, 'credit', ?, ?, ?, ?)");
            $stmt_ledger->execute([$user_id, $amount, $ref_type, $ref_id, $note]);

            return true;
        } catch (Exception $e) {
            // rollBack() yahan se hata diya gaya hai.
            // Error ko wapis throw karein taake asal function (e.g., panel/orders.php) usay pakar sake.
            throw $e;
        }
    }

    /**
     * Naya (FIXED) addDebit function.
     * Yeh function bhi transaction khud shuru nahi karta.
     */
    public function addDebit($user_id, $amount, $ref_type, $ref_id, $note = '') {
        try {
            // Transaction (beginTransaction/commit) yahan se hata di gayi hai.

            // 1. Balance check karein (FOR UPDATE zaroori hai)
            $stmt = $this->db->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
            $stmt->execute([$user_id]);
            $current_balance = (float) $stmt->fetchColumn();

            if ($current_balance < $amount) {
                return false; // Insufficient funds
            }

            // 2. User ka balance update karein
            $stmt_update = $this->db->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
            $stmt_update->execute([$amount, $user_id]);

            // 3. Ledger mein entry karein
            $stmt_ledger = $this->db->prepare("INSERT INTO wallet_ledger (user_id, type, amount, ref_type, ref_id, note) VALUES (?, 'debit', ?, ?, ?, ?)");
            $stmt_ledger->execute([$user_id, $amount, $ref_type, $ref_id, $note]);

            return true;
        } catch (Exception $e) {
            // rollBack() yahan se hata diya gaya hai.
            throw $e;
        }
    }
}
?>