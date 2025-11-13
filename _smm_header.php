<?php
require_once __DIR__ . '/../includes/helpers.php';
requireLogin(); // Protect all user pages
$user_balance = getUserBalance($_SESSION['user_id']);
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>SMM Panel - <?php echo $GLOBALS['settings']['site_name'] ?? 'SubHub'; ?></title>
    
    <link rel="stylesheet" href="../assets/css/smm_style.css?v=1.0">
    
</head>
<body class="smm-app-theme"> 
    
    <div class="smm-app-container">