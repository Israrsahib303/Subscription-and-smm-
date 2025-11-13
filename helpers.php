<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

// --- Global Settings ---
$GLOBALS['settings'] = [];
try {
    $stmt = $db->query("SELECT setting_key, setting_value FROM settings");
    while ($row = $stmt->fetch()) {
        $GLOBALS['settings'][$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    // Settings table might not exist during install
}

// --- Security & Input ---
function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    if (!empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token)) {
        unset($_SESSION['csrf_token']); // One-time use
        return true;
    }
    return false;
}

function redirect($url) {
    header('Location: ' . $url);
    exit;
}

// --- Authentication ---
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return (isLoggedIn() && isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1);
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirect(SITE_URL . '/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    }
}

function requireAdmin() {
    if (!isAdmin()) {
        // *** YEH HAI ASAL FIX ***
        // Yeh /login.php par redirect karta hai (jo sahi hai)
        // /admin/login.php par nahi (jo ghalat hai)
        redirect(SITE_URL . '/login.php?error=auth');
    }
}

// --- Formatting ---
function formatCurrency($amount, $symbol = null) {
    global $settings;
    $symbol = $symbol ?? $settings['currency_symbol'] ?? 'PKR';
    return $symbol . ' ' . number_format($amount, 2);
}

function formatDate($timestamp) {
    return date('d M, Y h:i A', strtotime($timestamp));
}

function generateCode($prefix = 'SH-') {
    return $prefix . strtoupper(bin2hex(random_bytes(4)));
}

// --- User & Wallet ---
function getUserBalance($user_id) {
    global $db;
    $stmt = $db->prepare("SELECT balance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    return $user ? (float)$user['balance'] : 0.0;
}

// --- WhatsApp ---
function generateWhatsAppLink($order_data, $product_name) {
    global $settings;
    $admin_phone = $settings['whatsapp_number'] ?? '';
    
    $message = "🎉 *Order Receipt - SubHub* 🎉\n\n";
    $message .= "Order ID: *#" . $order_data['code'] . "*\n";
    $message .= "Service: *" . $product_name . "*\n";
    $message .= "Duration: *" . $order_data['duration_months'] . " Month(s)*\n";
    $message .= "Total Paid: *" . formatCurrency($order_data['total_price']) . "*\n";
    $message .= "Starts: *" . formatDate($order_data['start_at']) . "*\n";
    $message .= "Ends: *" . formatDate($order_data['end_at']) . "*\n\n";
    $message .= "Status: *Active*";

    $encoded_message = urlencode($message);
    return "https://wa.me/{$admin_phone}?text={$encoded_message}";
}
?>