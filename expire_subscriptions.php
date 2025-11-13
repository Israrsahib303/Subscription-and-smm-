<?php
// This script should be run by a cPanel cron job every 15 minutes
// Command: /usr/local/bin/php /home/YOUR_CPANEL_USER/public_html/subhub_v6.3/includes/cron/expire_subscriptions.php

// Set directory context
chdir(dirname(__DIR__));
require_once 'db.php';

echo "Cron Started: " . date('Y-m-d H:i:s') . "\n";

try {
    $stmt = $db->query("
        UPDATE orders 
        SET status = 'expired' 
        WHERE end_at <= NOW() 
        AND status = 'completed'
    ");
    
    $affected_rows = $stmt->rowCount();
    echo "Success: {$affected_rows} subscriptions marked as expired.\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    // Log to a file in /assets/logs/
    file_put_contents('../../assets/logs/cron.log', date('Y-m-d H:i:s') . " - " . $e->getMessage() . "\n", FILE_APPEND);
}

echo "Cron Finished: " . date('Y-m-d H:i:s') . "\n";
?>