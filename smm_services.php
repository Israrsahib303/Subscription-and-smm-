<?php
include '_header.php';

$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';
$sync_status = $_GET['sync_status'] ?? '';

// --- Category Grouping ke liye services fetch karein ---
try {
    $stmt = $db->query("
        SELECT s.*, p.name as provider_name, p.api_key, p.api_url
        FROM smm_services s
        JOIN smm_providers p ON s.provider_id = p.id
        ORDER BY s.category ASC, s.name ASC
    ");
    $all_services = $stmt->fetchAll();
    
    $services_by_category = [];
    $categories = [];

    foreach ($all_services as $service) {
        // Category ko clean karein (jaisa ke frontend mein kiya tha)
        if (preg_match('/^([\w\s]+?)(?:\s\»\s|\s\-\s|\s\d|$)/i', $service['category'], $matches)) {
            $clean_category = trim($matches[1]);
        } else {
            $clean_category = trim($service['category']);
        }
        
        $services_by_category[$clean_category][] = $service;
        $categories[$clean_category] = $clean_category; // Unique list
    }
    
    ksort($categories); // Categories ko Alphabetical order mein sort karein

} catch (PDOException $e) {
    $error = 'Database Error: Failed to load services list.';
    $services_by_category = [];
    $categories = [];
}
?>

<h1>SMM Services & Categories</h1>

<?php if ($error): ?><div class="message error"><?php echo $error; ?></div><?php endif; ?>
<?php if ($success): ?><div class="message success"><?php echo $success; ?></div><?php endif; ?>
<?php if ($sync_status === 'sync_start'): ?><div class="message info">Service sync has started. Please wait a moment and refresh this page.</div><?php endif; ?>
<?php if ($sync_status === 'sync_complete'): ?><div class="message success">Service sync completed successfully.</div><?php endif; ?>


<div class="top-actions" style="margin-bottom: 20px;">
    <a href="smm_sync_action.php" class="btn btn-primary" onclick="return confirm('Do you want to start a full service sync from providers? This may take up to 2 minutes.');">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle;"><path d="M21.5 2v6h-6"></path><path d="M2.5 22v-6h6"></path><path d="M22 11.5A10 10 0 0 0 12 2C6.48 2 2 6.48 2 12a10 10 0 0 0 10 10 10 10 0 0 0 10-10"></path></svg>
        Sync Services Now
    </a>
</div>

<div class="admin-table-responsive" style="margin-top: 20px;">
    <div class="category-manager">
        <?php if (empty($categories)): ?>
            <div class="message info">No services found. Please add a provider and run the Sync Services action.</div>
        <?php else: ?>
            <?php foreach ($categories as $category_name): ?>
                <div class="category-card">
                    <div class="category-header">
                        <h2><?php echo sanitize($category_name); ?> (<?php echo count($services_by_category[$category_name]); ?> Services)</h2>
                        
                        <div class="category-actions">
                            <a href="smm_edit_service.php?category=<?php echo urlencode($category_name); ?>" class="btn-small btn-info" title="Edit Category Name">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.5 3.5l4 4L7.5 19H3v-4L16.5 3.5z"></path></svg>
                                Edit Name
                            </a>
                            <a href="smm_sync_action.php?delete_category=<?php echo urlencode($category_name); ?>" 
                               class="btn-small btn-danger" 
                               onclick="return confirm('Are you sure you want to DELETE ALL SERVICES in the category: <?php echo sanitize($category_name); ?>? This is permanent.');" 
                               title="Delete All Services in this Category">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>
                                Delete All
                            </a>
                        </div>
                    </div>
                    
                    <div class="category-body">
                        <table class="admin-table service-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Service Name</th>
                                    <th>Provider</th>
                                    <th>Rate (User)</th>
                                    <th>Rate (Provider)</th>
                                    <th>Min/Max</th>
                                    <th>Active</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($services_by_category[$category_name] as $service): ?>
                                    <tr>
                                        <td>#<?php echo $service['id']; ?></td>
                                        <td><?php echo sanitize($service['name']); ?></td>
                                        <td><?php echo sanitize($service['provider_name']); ?></td>
                                        <td><?php echo formatCurrency($service['service_rate']); ?></td>
                                        <td><?php echo formatCurrency($service['provider_rate']); ?></td>
                                        <td><?php echo number_format($service['min']); ?>/<?php echo number_format($service['max']); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $service['is_active'] ? 'active' : 'inactive'; ?>">
                                                <?php echo $service['is_active'] ? 'Yes' : 'No'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="smm_edit_service.php?id=<?php echo $service['id']; ?>" class="btn-small btn-info" title="Edit Service">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.5 3.5l4 4L7.5 19H3v-4L16.5 3.5z"></path></svg>
                                            </a>
                                            <a href="smm_sync_action.php?delete_service=<?php echo $service['id']; ?>" 
                                               class="btn-small btn-danger" 
                                               onclick="return confirm('Are you sure you want to DELETE Service #<?php echo $service['id']; ?>: <?php echo sanitize($service['name']); ?>?');" 
                                               title="Delete Service">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<style>
.category-manager {
    border: 1px solid var(--admin-border-color);
    border-radius: 8px;
    overflow: hidden;
}
.category-card {
    border-bottom: 1px solid var(--admin-border-color);
}
.category-card:last-child {
    border-bottom: none;
}
.category-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    background: var(--admin-sidebar-bg);
    cursor: pointer;
}
.category-header h2 {
    margin: 0;
    font-size: 1.1rem;
    color: var(--admin-text-color);
}
.category-actions {
    display: flex;
    gap: 10px;
}
.category-actions a {
    text-decoration: none;
}
.category-body {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease-out;
    background: var(--admin-card-bg);
}
.category-card.active .category-body {
    max-height: 2000px; /* Suficiently large value */
}
.service-table th, .service-table td {
    padding: 10px 15px;
    border: none;
}
.service-table tbody tr:nth-child(even) {
    background: var(--admin-sidebar-bg);
}

/* Edit/Delete Buttons */
.btn-small {
    padding: 5px 10px;
    font-size: 0.8rem;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    border-radius: 4px;
}
.btn-info { background: #007bff; color: white; border: none; }
.btn-danger { background: #dc3545; color: white; border: none; }
.status-active { background: #28a745; color: white; }
.status-inactive { background: #6c757d; color: white; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const categoryCards = document.querySelectorAll('.category-card');
    categoryCards.forEach(card => {
        const header = card.querySelector('.category-header');
        header.addEventListener('click', function() {
            // Toggle active state on the card
            card.classList.toggle('active');
            
            // Toggle visibility of the body
            const body = card.querySelector('.category-body');
            if (card.classList.contains('active')) {
                // Open: Set a large height to allow scrolling
                body.style.maxHeight = body.scrollHeight + "px";
            } else {
                // Close: Collapse the height
                body.style.maxHeight = "0";
            }
        });
    });
});
</script>

<?php include '_footer.php'; ?>
