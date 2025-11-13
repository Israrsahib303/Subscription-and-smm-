<nav class="main-nav">
    <div class="nav-logo">
        <a href="index.php">
            <?php if (!empty($GLOBALS['settings']['site_logo'])): ?>
                <img src="../assets/img/<?php echo sanitize($GLOBALS['settings']['site_logo']); ?>?v=<?php echo time(); ?>" 
                     alt="<?php echo sanitize($GLOBALS['settings']['site_name']); ?> Logo" 
                     class="site-logo">
            <?php else: ?>
                <?php echo sanitize($GLOBALS['settings']['site_name']); // Fallback text ?>
            <?php endif; ?>
        </a>
    </div>

    <ul class="nav-links">
        <li class="<?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">
            <a href="index.php">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                <span>Dashboard</span>
            </a>
        </li>
        <li class="<?php echo ($current_page == 'add-funds.php') ? 'active' : ''; ?>">
            <a href="add-funds.php">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"></path><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"></path><path d="M18 12a2 2 0 0 1 2 2v6m-4-3h4"></path></svg>
                <span>Add Funds</span>
            </a>
        </li>
        
        <li class="<?php echo (in_array($current_page, ['smm_order.php', 'smm_history.php'])) ? 'active' : ''; ?>">
            <a href="smm_order.php" style="color: #0D6EFD;">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect></svg>
                <span>SMM Panel</span>
            </a>
        </li>

        <li class="<?php echo ($current_page == 'sub_orders.php') ? 'active' : ''; ?>">
            <a href="sub_orders.php">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
                <span>My Subscriptions</span>
            </a>
        </li>
        
        <li class="<?php echo ($current_page == 'spin_wheel.php') ? 'active' : ''; ?>">
            <a href="spin_wheel.php" style="color: #FFC107;"> <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"></path><path d="m9 12 2 2 4-4"></path></svg>
                <span>Spin & Win</span>
            </a>
        </li>
        <li class="<?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>">
            <a href="profile.php">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                <span>Profile</span>
            </a>
        </li>
    </ul>

    <div class="nav-right-side">
        <div class="nav-logout">
            <a href="../logout.php" title="Logout">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
            </a>
        </div>
    </div>
    
    <button class="mobile-nav-toggle" aria-label="Toggle navigation">
        <span></span>
        <span></span>
        <span></span>
    </button>
</nav>