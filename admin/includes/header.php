<?php
require_once __DIR__ . '/../../includes/config.php';

// Require authentication
Auth::requireAuth();

$currentUser = Auth::user();
$pageTitle = $pageTitle ?? 'Dashboard';

// Get unread notification count
$notifCount = db()->fetchColumn(
    "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0",
    [Auth::id()]
);
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape($pageTitle) ?> - <?= APP_NAME ?></title>
    <link rel="icon" type="image/png" href="<?= ADMIN_URL ?>assets/images/favicon.png">
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    
    <!-- Inter Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    
    <!-- DataTables -->
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet">
    
    <!-- Leaflet.js -->
    <link href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" rel="stylesheet">
    
    <!-- Admin CSS -->
    <link href="<?= ADMIN_URL ?>assets/css/admin.css" rel="stylesheet">
    
    <!-- Page Specific CSS -->
    <?= $extraCss ?? '' ?>
</head>
<body>
    <!-- Offline Indicator -->
    <div class="offline-indicator" id="offlineIndicator">
        <i class="fas fa-wifi-slash me-2"></i> You are offline. Some features may be unavailable.
    </div>

    <!-- Loading Spinner -->
    <div class="spinner-overlay" id="spinnerOverlay">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <!-- Sidebar Overlay (mobile) -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <?php include_once __DIR__ . '/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">

    <!-- Top Header -->
    <header class="top-header">
        <div class="header-left">
            <button class="sidebar-toggle" id="sidebarToggle" title="Toggle Sidebar">
                <i class="fas fa-bars"></i>
            </button>
            <?php if (isset($breadcrumbs)): ?>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <?php foreach ($breadcrumbs as $crumb): ?>
                        <?php if (isset($crumb['url'])): ?>
                            <li class="breadcrumb-item"><a href="<?= $crumb['url'] ?>"><?= escape($crumb['label']) ?></a></li>
                        <?php else: ?>
                            <li class="breadcrumb-item active"><?= escape($crumb['label']) ?></li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ol>
            </nav>
            <?php endif; ?>
        </div>
        <div class="header-right">
            <!-- Notifications -->
            <div class="dropdown">
                <button class="btn-icon" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
                    <i class="fas fa-bell"></i>
                    <?php if ($notifCount > 0): ?>
                        <span class="badge-dot"></span>
                    <?php endif; ?>
                </button>
                <div class="dropdown-menu dropdown-menu-end notif-dropdown">
                    <div class="notif-header">
                        Notifications
                        <?php if ($notifCount > 0): ?>
                            <span class="badge bg-primary ms-2"><?= $notifCount ?> new</span>
                        <?php endif; ?>
                    </div>
                    <div class="notif-body" id="notificationList">
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-bell-slash fa-2x mb-2"></i>
                            <p class="mb-0 small">No notifications</p>
                        </div>
                    </div>
                    <div class="notif-footer">
                        <a href="<?= ADMIN_URL ?>notifications/index.php">View All Notifications</a>
                    </div>
                </div>
            </div>

            <!-- User Dropdown -->
            <div class="dropdown">
                <div class="user-dropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="user-avatar">
                        <?= strtoupper(substr($currentUser['name'] ?? 'U', 0, 1)) ?>
                    </div>
                    <div class="user-info d-none d-md-block">
                        <div class="name"><?= escape($currentUser['name'] ?? 'User') ?></div>
                        <div class="role"><?= escape($currentUser['role_name'] ?? '') ?></div>
                    </div>
                </div>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <a class="dropdown-item" href="<?= ADMIN_URL ?>users/profile.php">
                            <i class="fas fa-user"></i> My Profile
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="<?= ADMIN_URL ?>settings/index.php">
                            <i class="fas fa-cog"></i> Settings
                        </a>
                    </li>
                    <?php if (Auth::isSuperAdmin()): ?>
                    <li>
                        <a class="dropdown-item" href="<?= ADMIN_URL ?>users/audit-logs.php">
                            <i class="fas fa-history"></i> Audit Logs
                        </a>
                    </li>
                    <?php endif; ?>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item text-danger" href="<?= BASE_URL ?>pages/auth/logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </header>

    <!-- Page Content -->
    <div class="page-content">
        <?= display_alert() ?>
