<?php
// --- CRON JOB (Run every 5 minutes) ---
// Command: /usr/local/bin/php /home/israrlia/test.israrliaqat.shop/includes/cron/check_email.php

chdir(dirname(__DIR__)); 
require_once 'config.php';
require_once 'db.php';

$log_file = '../../assets/logs/email_cron.log';
$log_message = "Cron started at " . date('Y-m-d H:i:s') . "\n";

// IMAP extension check
if (!function_exists('imap_open')) {
    $log_message .= "IMAP extension is not installed on this server.\n";
    file_put_contents($log_file, $log_message, FILE_APPEND);
    die('IMAP not installed');
}

try {
    $stmt = $db->query("SELECT * FROM payment_methods WHERE is_auto = 1 AND is_active = 1");
    $auto_methods = $stmt->fetchAll();
    
    if (empty($auto_methods)) {
        $log_message .= "No active auto-payment methods found in database.\n";
        file_put_contents($log_file, $log_message, FILE_APPEND);
        die('No auto-methods enabled.');
    }

    $log_message .= "Found " . count($auto_methods) . " auto-method(s) to check.\n";

    foreach ($auto_methods as $method) {
        $log_message .= "--- Checking Method: " . $method['name'] . " ---\n";
        
        $mail_server = $method['auto_mail_server'];
        $email_user = $method['auto_email_user'];
        $email_pass = $method['auto_email_pass'];
        
        if (empty($mail_server) || empty($email_user) || empty($email_pass)) {
            $log_message .= "  SKIPPED: Email settings (Server, User, Pass) are incomplete.\n";
            continue; 
        }

        $email_host = "{" . $mail_server . ":993/imap/ssl/novalidate-cert}INBOX";
        $mbox = imap_open($email_host, $email_user, $email_pass);

        if (!$mbox) {
            $log_message .= "  FAILED to connect to IMAP for " . $email_user . ": " . imap_last_error() . "\n";
            continue; 
        }

        $emails = imap_search($mbox, 'UNSEEN');

        if ($emails) {
            $log_message .= "  Found " . count($emails) . " unread emails.\n";
            
            foreach ($emails as $email_id) {
                // Email ki poori body haasil karein
                $body = imap_fetchbody($mbox, $email_id, 1);
                $body = quoted_printable_decode($body); // Encoding fix karein
                $body = strip_tags($body); // Faltu HTML tags (jese <img>) hata dein

                $txn_id = null;
                $amount = null;
                
                // --- YEH HAIN NAYE, FIXED PATTERNS (Aap ke log ke mutabiq) ---

                // Pattern 1: (Transaction ID 6912099d...)
                if (preg_match('/Transaction ID\s*([\w\d]+)/i', $body, $matches)) {
                    $txn_id = $matches[1];
                }
                
                // Pattern 2: (Amount Received Rs. 1)
                if (preg_match('/Amount Received\s*Rs\.\s*([\d,\.]+)/i', $body, $matches)) {
                    $amount = str_replace(',', '', $matches[1]);
                    $amount = (float)$amount;
                }
                // --- FIX KHATAM ---

                if ($txn_id && $amount) {
                    try {
                        $stmt_insert = $db->prepare("INSERT INTO email_payments (txn_id, amount, status, raw_email_data) VALUES (?, ?, 'pending', ?)");
                        $stmt_insert->execute([$txn_id, $amount, $body]);
                        $log_message .= "    SUCCESS: Added TXN ID: $txn_id, Amount: $amount\n";
                        imap_setflag_full($mbox, $email_id, "\\Seen");
                        
                    } catch (PDOException $e) {
                        if ($e->getCode() == 23000) { 
                            $log_message .= "    SKIPPED: TXN ID: $txn_id already exists.\n";
                        } else {
                            $log_message .= "    DB ERROR: " . $e->getMessage() . "\n";
                        }
                        imap_setflag_full($mbox, $email_id, "\\Seen");
                    }
                } else {
                    $log_message .= "    FAILED: Could not parse TXN ID or Amount from email ID $email_id.\n";
                    $log_message .= "    --- START RAW EMAIL BODY (FOR DEBUGGING) ---\n";
                    $log_message .= $body; 
                    $log_message .= "\n    --- END RAW EMAIL BODY ---\n";
                    imap_setflag_full($mbox, $email_id, "\\Seen");
                }
            }
        } else {
            $log_message .= "  No new emails found for " . $email_user . ".\n";
        }
        imap_close($mbox);
    }

} catch (PDOException $e) {
    $log_message .= "Database connection failed: " . $e->getMessage() . "\n";
}

$log_message .= "Cron finished at " . date('Y-m-d H:i:s') . "\n\n";
file_put_contents($log_file, $log_message, FILE_APPEND);
echo $log_message;
?>