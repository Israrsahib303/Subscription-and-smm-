<?php
require_once __DIR__ . '/includes/helpers.php';
session_unset();
session_destroy();
redirect(SITE_URL . '/login.php');
?>