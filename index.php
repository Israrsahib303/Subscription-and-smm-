<?php
require_once __DIR__ . '/includes/helpers.php';

if (isLoggedIn()) {
    if (isAdmin()) {
        redirect(SITE_URL . '/panel/index.php');
    } else {
        redirect(SITE_URL . '/user/index.php');
    }
} else {
    redirect(SITE_URL . '/login.php');
}
?>