<?php
class Order {
    private $db;
    private $wallet;

    public function __construct($db, $wallet) {
        $this->db = $db;
        $this->wallet = $wallet;
    }

    // --- YEH FUNCTION MUKAMMAL CHANGE HO GAYA HAI ---
    public function createOrderFromVariation($user_id, $variation_id) {
        try {
            // 1. Variation ki details (jismein price hai) fetch karein
            $stmt_var = $this->db->prepare("
                SELECT v.*, p.name as product_name 
                FROM product_variations v
                JOIN products p ON v.product_id = p.id
                WHERE v.id = ? AND v.is_active = 1 AND p.is_active = 1
            ");
            $stmt_var->execute([$variation_id]);
            $variation = $stmt_var->fetch();

            if (!$variation) {
                return ['success' => false, 'error' => 'Selected plan is not available.'];
            }

            // 2. Price aur baaqi details variation se lein
            $product_id = $variation['product_id'];
            $product_name = $variation['product_name'];
            $duration_months = $variation['duration_months'];
            $total_price = (float)$variation['price'];
            $unit_price = $total_price / $duration_months; // Sirf record ke liye

            // 3. User ka balance check karein
            $current_balance = $this->wallet->getBalance($user_id);
            if ($current_balance < $total_price) {
                return ['success' => false, 'error' => 'insufficient_funds'];
            }
            
            // 4. Transaction shuru karein
            $this->db->beginTransaction();

            // 5. Order create karein
            $code = generateCode('SH-');
            
            // NAYI LOGIC: Status ab 'pending' ho ga, start/end date NULL ho gi
            $stmt = $this->db->prepare("
                INSERT INTO orders (code, user_id, product_id, duration_months, unit_price, total_price, status, start_at, end_at)
                VALUES (?, ?, ?, ?, ?, ?, 'pending', NULL, NULL)
            ");
            $stmt->execute([
                $code, $user_id, $product_id, $duration_months,
                $unit_price, $total_price
            ]);
            
            $order_id = $this->db->lastInsertId();

            // 6. Wallet se paise kaatein
            $debit_note = "Order #{$code} - {$product_name} ({$variation['type']} - {$duration_months}M)";
            $debit_success = $this->wallet->addDebit($user_id, $total_price, 'order', $order_id, $debit_note);

            if (!$debit_success) {
                $this->db->rollBack();
                return ['success' => false, 'error' => 'Wallet debit failed.'];
            }

            $this->db->commit();
            
            // Naya order data wapis bhejein
            $stmt_success = $this->db->prepare("SELECT * FROM orders WHERE id = ?");
            $stmt_success->execute([$order_id]);
            
            // Yahan product_name bhi wapis bhejein taake success page par show ho
            return ['success' => true, 'order' => $stmt_success->fetch(), 'product_name' => $product_name];

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return ['success' => false, 'error' => 'An internal error occurred: ' . $e->getMessage()];
        }
    }
}
?>