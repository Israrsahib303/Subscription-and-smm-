<?php
// Naye SMM Header aur Footer files istemal karein
include '_smm_header.php'; 

$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';

// --- SMM Services ko DB se fetch karein (FIXED QUERY) ---
try {
    $user_id = (int)$_SESSION['user_id']; 
    
    $stmt_services = $db->query("
        SELECT s.*, p.api_url, p.api_key, f.id as is_favorite
        FROM smm_services s
        JOIN smm_providers p ON s.provider_id = p.id
        LEFT JOIN user_favorite_services f ON s.id = f.service_id AND f.user_id = $user_id
        WHERE s.is_active = 1 AND p.is_active = 1
        ORDER BY is_favorite DESC, s.category ASC, s.name ASC
    ");
    $all_services = $stmt_services->fetchAll();
    
    $services_by_category = [];
    $services_data_json = []; 
    $categories_list = [];

    foreach ($all_services as $service) {
        if (preg_match('/^([\w\s]+?)(?:\s\»\s|\s\-\s|\s\d|$)/i', $service['category'], $matches)) {
            $clean_category = trim($matches[1]);
        } else {
            $clean_category = trim($service['category']);
        }
        $service['clean_category'] = $clean_category;
        
        $services_by_category[$clean_category][] = $service;
        $categories_list[$clean_category] = 1;
        
        // --- YEH RAHA FIX: Description ko JS mein add karein ---
        $services_data_json[$service['id']] = [
            'rate' => (float)$service['service_rate'],
            'min' => (int)$service['min'],
            'max' => (int)$service['max'],
            'avg_time' => sanitize($service['avg_time'] ?? 'N/A'),
            'has_refill' => (bool)$service['has_refill'],
            'has_cancel' => (bool)$service['has_cancel'],
            'name' => sanitize($service['name']),
            'desc' => sanitize($service['description'] ?? 'No description available.') // NAYI LINE
        ];
    }
    
    $categories = array_keys($categories_list);
    sort($categories);

} catch (PDOException $e) {
    $categories = [];
    $services_by_category = [];
    $services_data_json = [];
    $error = "Database Error: Cannot load services. " . $e->getMessage();
}
?>

<div class="app-header">
    <div class="header-left">
        <p>Your Balance</p>
        <h2 style="color: var(--app-primary);"><?php echo formatCurrency($user_balance); ?></h2>
    </div>
    <div class="header-right">
        <a href="add-funds.php" class="btn-add-funds-app" style="background: var(--app-primary); color: white;">+</a>
    </div>
</div>

<?php if ($error): ?><div class="app-message app-error"><?php echo urldecode($error); ?></div><?php endif; ?>
<?php if ($success): ?><div class="app-message app-success"><?php echo sanitize($success); ?></div><?php endif; ?>


<div class="search-bar">
    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
    <input type="text" id="service-search" class="form-control-app" placeholder="Search for a service...">
</div>

<div class="category-accordion">
    <?php if (empty($categories)): ?>
        <div class="app-message">No SMM services are available right now. Please sync services in Admin Panel.</div>
    <?php else: ?>
        <?php foreach ($categories as $category_name): ?>
            <div class="category-group">
                <div class="category-header" data-category="<?php echo sanitize(strtolower(str_replace(' ', '-', $category_name))); ?>">
                    <div class="category-header-icon">
                        <span><?php echo strtoupper(substr($category_name, 0, 1)); ?></span>
                    </div>
                    <h3><?php echo sanitize($category_name); ?></h3>
                    <svg class="chevron" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                </div>
                
                <div class="service-list" id="category-<?php echo sanitize(strtolower(str_replace(' ', '-', $category_name))); ?>">
                    <?php if (isset($services_by_category[$category_name])): ?>
                        <?php foreach ($services_by_category[$category_name] as $service): ?>
                            <div class="service-item" 
                                 data-service-id="<?php echo $service['id']; ?>"
                                 data-service-name="<?php echo sanitize($service['name']); ?>">
                                
                                <div class="service-item-header">
                                    <span class="service-item-name">
                                        <?php if ($service['is_favorite']): ?> ⭐ <?php endif; ?>
                                        <?php echo sanitize($service['name']); ?>
                                    </span>
                                    <span class="service-item-rate"><?php echo formatCurrency($service['service_rate']); ?> / 1k</span>
                                </div>
                                <div class="service-item-meta">
                                    <span>
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20v-6M6 20v-2M18 20v-4"></path></svg>
                                        Avg. Time: <?php echo sanitize($service['avg_time'] ?? 'N/A'); ?>
                                    </span>
                                    <?php if ($service['has_refill']): ?>
                                    <span class="refill-yes">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                                        Refill Available
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div class="modal-overlay" id="order-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modal-service-name">Place Order</h2>
            <button class="modal-close" id="modal-close-btn">&times;</button>
        </div>
        
        <div class="service-description" id="modal-service-desc">
            Service description will load here...
        </div>

        <form id="modal-order-form" action="smm_order_action.php" method="POST">
            <input type="hidden" name="service_id" id="modal-service-id">
            
            <div class="app-form-group">
                <label for="modal-link">Link</label>
                <input type="text" name="link" id="modal-link" class="form-control-app" placeholder="https://..." required>
                <div class="link-detector" id="link-detector-msg"></div>
            </div>
            
            <div class="app-form-group">
                <label for="modal-quantity">Quantity</label>
                <input type="number" name="quantity" id="modal-quantity" class="form-control-app" placeholder="1000" required>
                <small id="modal-min-max-msg" style="color: var(--app-secondary);"></small>
            </div>
            
            <div class="charge-display">
                <p>Total Charge</p>
                <h2 id="modal-total-charge">PKR 0.00</h2>
            </div>
            
            <button type="submit" class="btn-app-primary" disabled>Place Order</button>
        </form>
    </div>
</div>

<audio id="cha-ching-sound" src="../assets/sounds/cha-ching.mp3" preload="auto"></audio>


<script>
    // Tamam services ka data PHP se JS mein lein
    window.allServicesData = <?php echo json_encode($services_data_json); ?>;
</script>

<?php include '_smm_footer.php'; // Naya SMM Footer istemal karein ?>