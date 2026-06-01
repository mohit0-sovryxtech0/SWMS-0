<?php
$currentRole = Auth::role();
$can = function($perm) { return RBAC::can($perm); };
$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir = basename(dirname($_SERVER['PHP_SELF']));

function isActive($dir, $pages = []) {
    global $currentDir, $currentPage;
    if ($currentDir === $dir) return true;
    if (in_array($currentPage, $pages)) return true;
    return false;
}
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon">SW</div>
        <div class="brand-text">Smart Water MS</div>
    </div>
    <nav class="sidebar-nav">
        <!-- Dashboard -->
        <div class="nav-section">Main</div>
        <a href="<?= ADMIN_URL ?>dashboard/index.php" class="nav-item <?= isActive('dashboard') && $currentPage === 'index.php' ? 'active' : '' ?>">
            <i class="fas fa-chart-pie nav-icon"></i>
            <span class="nav-label">Dashboard</span>
        </a>
        <a href="<?= ADMIN_URL ?>workflow/index.php" class="nav-item <?= isActive('workflow') ? 'active' : '' ?>">
            <i class="fas fa-project-diagram nav-icon"></i>
            <span class="nav-label">Workflow</span>
        </a>

        <?php if ($can('users.view')): ?>
        <div class="nav-section">Administration</div>
        <a href="<?= ADMIN_URL ?>users/index.php" class="nav-item <?= isActive('users') ? 'active' : '' ?>">
            <i class="fas fa-users-cog nav-icon"></i>
            <span class="nav-label">User Management</span>
        </a>
        <?php endif; ?>

        <?php if ($can('consumers.view')): ?>
        <div class="nav-section">Operations</div>
        <a href="<?= ADMIN_URL ?>consumers/index.php" class="nav-item <?= isActive('consumers') ? 'active' : '' ?>">
            <i class="fas fa-users nav-icon"></i>
            <span class="nav-label">Consumers</span>
        </a>
        <?php endif; ?>

        <?php if ($can('employees.view')): ?>
        <a href="<?= ADMIN_URL ?>employees/index.php" class="nav-item <?= isActive('employees') ? 'active' : '' ?>">
            <i class="fas fa-user-tie nav-icon"></i>
            <span class="nav-label">Employees</span>
        </a>
        <?php endif; ?>

        <?php if ($can('bills.view') || $can('payments.view')): ?>
        <div class="nav-section">Billing & Revenue</div>
        <a href="<?= ADMIN_URL ?>billing/index.php" class="nav-item <?= isActive('billing') ? 'active' : '' ?>">
            <i class="fas fa-file-invoice-dollar nav-icon"></i>
            <span class="nav-label">Billing</span>
        </a>
        <a href="<?= ADMIN_URL ?>billing/payments.php" class="nav-item <?= isActive('billing') && $currentPage === 'payments.php' ? 'active' : '' ?>">
            <i class="fas fa-hand-holding-usd nav-icon"></i>
            <span class="nav-label">Payments</span>
        </a>
        <a href="<?= ADMIN_URL ?>billing/defaulters.php" class="nav-item <?= isActive('billing') && strpos($currentPage, 'defaulters') !== false ? 'active' : '' ?>">
            <i class="fas fa-exclamation-triangle nav-icon"></i>
            <span class="nav-label">Defaulters</span>
            <span class="nav-badge bg-danger" id="defaulterBadge">0</span>
        </a>
        <?php if ($can('bills.generate')): ?>
        <a href="<?= ADMIN_URL ?>billing/cycles.php" class="nav-item <?= isActive('billing') && strpos($currentPage, 'cycles') !== false ? 'active' : '' ?>">
            <i class="fas fa-sync-alt nav-icon"></i>
            <span class="nav-label">Billing Cycles</span>
        </a>
        <?php endif; ?>
        <?php if ($can('tariffs.manage')): ?>
        <a href="<?= ADMIN_URL ?>billing/tariffs.php" class="nav-item <?= isActive('billing') && strpos($currentPage, 'tariffs') !== false ? 'active' : '' ?>">
            <i class="fas fa-tags nav-icon"></i>
            <span class="nav-label">Tariffs</span>
        </a>
        <a href="<?= ADMIN_URL ?>billing/settings.php" class="nav-item <?= isActive('billing') && strpos($currentPage, 'settings') !== false ? 'active' : '' ?>">
            <i class="fas fa-cog nav-icon"></i>
            <span class="nav-label">Billing Settings</span>
        </a>
        <?php endif; ?>
        <?php if ($can('settings.edit')): ?>
        <a href="<?= ADMIN_URL ?>billing/gateways.php" class="nav-item <?= isActive('billing') && strpos($currentPage, 'gateways') !== false ? 'active' : '' ?>">
            <i class="fas fa-credit-card nav-icon"></i>
            <span class="nav-label">Payment Gateways</span>
        </a>
        <?php endif; ?>
        <?php endif; ?>

        <?php if ($can('readings.view') || $can('readings.enter')): ?>
        <div class="nav-section">Meter Reading</div>
        <a href="<?= ADMIN_URL ?>meter-reading/index.php" class="nav-item <?= isActive('meter-reading') && $currentPage === 'index.php' ? 'active' : '' ?>">
            <i class="fas fa-tachometer-alt nav-icon"></i>
            <span class="nav-label">POS Reading</span>
        </a>
        <a href="<?= ADMIN_URL ?>meter-reading/routes.php" class="nav-item <?= isActive('meter-reading') && strpos($currentPage, 'routes') !== false ? 'active' : '' ?>">
            <i class="fas fa-route nav-icon"></i>
            <span class="nav-label">Routes</span>
        </a>
        <a href="<?= ADMIN_URL ?>meter-reading/verify.php" class="nav-item <?= isActive('meter-reading') && strpos($currentPage, 'verify') !== false ? 'active' : '' ?>">
            <i class="fas fa-check-double nav-icon"></i>
            <span class="nav-label">Verify Readings</span>
        </a>
        <a href="<?= ADMIN_URL ?>meter-reading/history.php" class="nav-item <?= isActive('meter-reading') && strpos($currentPage, 'history') !== false ? 'active' : '' ?>">
            <i class="fas fa-history nav-icon"></i>
            <span class="nav-label">Reading History</span>
        </a>
        <?php endif; ?>

        <?php if ($can('complaints.view')): ?>
        <div class="nav-section">Services</div>
        <a href="<?= ADMIN_URL ?>complaints/index.php" class="nav-item <?= isActive('complaints') ? 'active' : '' ?>">
            <i class="fas fa-headset nav-icon"></i>
            <span class="nav-label">Complaints</span>
            <span class="nav-badge bg-warning" id="complaintBadge">0</span>
        </a>
        <?php endif; ?>

        <?php if ($can('inventory.view')): ?>
        <a href="<?= ADMIN_URL ?>inventory/index.php" class="nav-item <?= isActive('inventory') ? 'active' : '' ?>">
            <i class="fas fa-boxes nav-icon"></i>
            <span class="nav-label">Inventory</span>
        </a>
        <?php endif; ?>

        <?php if ($can('assets.view')): ?>
        <div class="nav-section">Infrastructure</div>
        <a href="<?= ADMIN_URL ?>assets-mgmt/index.php" class="nav-item <?= isActive('assets-mgmt') ? 'active' : '' ?>">
            <i class="fas fa-building nav-icon"></i>
            <span class="nav-label">Assets</span>
        </a>
        <?php endif; ?>

        <?php if ($can('gis.view')): ?>
        <a href="<?= ADMIN_URL ?>gis/index.php" class="nav-item <?= isActive('gis') ? 'active' : '' ?>">
            <i class="fas fa-map-marked-alt nav-icon"></i>
            <span class="nav-label">GIS Mapping</span>
        </a>
        <?php endif; ?>

        <?php if ($can('reports.view')): ?>
        <div class="nav-section">Analytics</div>
        <a href="<?= ADMIN_URL ?>reports/index.php" class="nav-item <?= isActive('reports') ? 'active' : '' ?>">
            <i class="fas fa-chart-bar nav-icon"></i>
            <span class="nav-label">Reports</span>
        </a>
        <?php endif; ?>

        <?php if ($can('settings.view')): ?>
        <div class="nav-section">System</div>
        <a href="<?= ADMIN_URL ?>notifications/index.php" class="nav-item <?= isActive('notifications') ? 'active' : '' ?>">
            <i class="fas fa-bell nav-icon"></i>
            <span class="nav-label">Notifications</span>
        </a>
        <a href="<?= ADMIN_URL ?>documents/index.php" class="nav-item <?= isActive('documents') ? 'active' : '' ?>">
            <i class="fas fa-file-alt nav-icon"></i>
            <span class="nav-label">Documents</span>
        </a>
        <a href="<?= ADMIN_URL ?>settings/index.php" class="nav-item <?= isActive('settings') ? 'active' : '' ?>">
            <i class="fas fa-cog nav-icon"></i>
            <span class="nav-label">Settings</span>
        </a>
        <?php endif; ?>
    </nav>
</aside>
