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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $GLOBALS['settings']['site_name'] ?? 'SubHub'; ?> - Dashboard</title>
    
    <link rel="stylesheet" href="../assets/css/style.css?v=2.2">
    
    <?php
        // Database se colors fetch karein
        $theme_primary = $GLOBALS['settings']['theme_primary'] ?? '#E50914';
        $theme_hover = $GLOBALS['settings']['theme_hover'] ?? '#f40612';
        $bg_color = $GLOBALS['settings']['bg_color'] ?? '#141414';
        $card_color = $GLOBALS['settings']['card_color'] ?? '#1d1d1d';
        $text_color = $GLOBALS['settings']['text_color'] ?? '#e5e5e5';
        $text_muted_color = $GLOBALS['settings']['text_muted_color'] ?? '#8c8c8c';
    ?>
    <style>
        :root {
            /* Button Colors */
            --brand-red: <?php echo $theme_primary; ?>;
            --brand-red-hover: <?php echo $theme_hover; ?>;
            --gradient-1: linear-gradient(135deg, <?php echo $theme_primary; ?> 0%, <?php echo $theme_hover; ?> 100%);
            
            /* Background Colors */
            --bg-color: <?php echo $bg_color; ?>;
            --card-color: <?php echo $card_color; ?>;

            /* Text Colors */
            --text-color: <?php echo $text_color; ?>;
            --text-muted: <?php echo $text_muted_color; ?>;
        }
        
        /* Yeh product card ka layout fix hai */
        .product-card-body {
            display: flex; flex-direction: column; height: 100%;
        }
        .card-heading-icon {
            display: flex; align-items: center; gap: 6px; font-size: 0.9rem;
            font-weight: 700; color: var(--text-color); margin-top: 5px; margin-bottom: 8px;
        }
        .card-heading-icon svg {
            width: 16px; height: 16px; stroke-width: 2.5px; color: var(--text-muted);
        }
        .card-heading-icon.original-price-heading svg { color: var(--brand-red); }
        .product-card-description {
            font-size: 0.9rem; color: var(--text-muted); margin-bottom: 1rem;
            line-height: 1.5; flex-grow: 1;
        }
        .price-block {
            display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 0.5rem;
        }
        .price-item { display: flex; flex-direction: column; }
        .product-card-price {
            font-size: 1.5rem; color: #4CAF50; font-weight: 700;
            margin-bottom: 0; margin-top: 0; line-height: 1.2;
        }
        .product-card-price span {
            font-size: 0.9rem; color: var(--text-muted); font-weight: 400;
        }
        .product-card-original-price {
            font-size: 1.1rem; color: var(--text-muted); margin-top: 0;
            margin-bottom: 3px; line-height: 1.2;
        }
        .product-card-original-price s { color: var(--brand-red); }
        .product-card-proof-link {
            font-size: 0.8rem; font-weight: 600; color: #6c9cff;
            margin-bottom: 1rem; display: inline-block;
        }
        .product-card-proof-link:hover { text-decoration: underline; }
        .btn-buy { margin-top: auto; }
    </style>
    </head>
<body>
    
    <?php include '_nav.php'; ?>
    
    <div class="container">