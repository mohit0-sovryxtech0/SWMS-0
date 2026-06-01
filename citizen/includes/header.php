<?php
require_once __DIR__ . '/../../includes/config.php';

$pageTitle = $pageTitle ?? 'Citizen Portal';
$currentPage = basename($_SERVER['PHP_SELF']);

// Check if citizen is logged in
function citizenLoggedIn() {
    return isset($_SESSION['citizen_id']);
}

function citizenId() {
    return $_SESSION['citizen_id'] ?? null;
}

function citizenName() {
    return $_SESSION['citizen_name'] ?? 'Guest';
}

function requireCitizen() {
    if (!citizenLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        redirect(CITIZEN_URL . 'login.php');
    }
}

$citizen = null;
if (citizenLoggedIn()) {
    $citizen = db()->fetchOne("SELECT id, consumer_no, full_name, mobile, email, ward_no, tole, connection_type, status FROM consumers WHERE id = ? AND deleted_at IS NULL", [citizenId()]);
    if (!$citizen) {
        session_destroy();
        redirect(CITIZEN_URL . 'login.php');
    }
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape($pageTitle) ?> - <?= APP_SHORT ?> | Citizen Portal</title>
    <link rel="icon" type="image/png" href="<?= ADMIN_URL ?>assets/images/favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="<?= CITIZEN_URL ?>assets/css/citizen.css" rel="stylesheet">
    <?= $extraCss ?? '' ?>
</head>
<body>
    <!-- Top Navigation -->
    <nav class="navbar navbar-expand-lg citizen-navbar">
        <div class="container">
            <a class="navbar-brand" href="<?= CITIZEN_URL ?>">
                <span class="brand-icon"><i class="fas fa-water"></i></span>
                <span class="brand-text"><?= APP_SHORT ?></span>
                <small class="d-none d-md-inline">Citizen Portal</small>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#citizenNav" aria-controls="citizenNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="citizenNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'index.php' ? 'active' : '' ?>" href="<?= CITIZEN_URL ?>"><i class="fas fa-home"></i> Home</a>
                    </li>
                    <?php if (citizenLoggedIn()): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'dashboard.php' ? 'active' : '' ?>" href="<?= CITIZEN_URL ?>dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'bills.php' ? 'active' : '' ?>" href="<?= CITIZEN_URL ?>bills.php"><i class="fas fa-file-invoice"></i> Bills</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'payment-history.php' ? 'active' : '' ?>" href="<?= CITIZEN_URL ?>payment-history.php"><i class="fas fa-history"></i> Payments</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'complaints.php' ? 'active' : '' ?>" href="<?= CITIZEN_URL ?>complaints.php"><i class="fas fa-exclamation-triangle"></i> Complaints</a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage == 'complaint-track.php' ? 'active' : '' ?>" href="<?= CITIZEN_URL ?>complaint-track.php"><i class="fas fa-search"></i> Track Complaint</a>
                    </li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <?php if (citizenLoggedIn()): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="user-avatar-sm"><?= strtoupper(substr($citizen['full_name'] ?? 'U', 0, 1)) ?></span>
                            <span class="d-none d-md-inline"><?= escape($citizen['full_name'] ?? 'User') ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="<?= CITIZEN_URL ?>profile.php"><i class="fas fa-user-circle"></i> My Profile</a></li>
                            <li><a class="dropdown-item" href="<?= CITIZEN_URL ?>dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="<?= CITIZEN_URL ?>login.php?logout=1"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                        </ul>
                    </li>
                    <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= CITIZEN_URL ?>login.php"><i class="fas fa-sign-in-alt"></i> Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= CITIZEN_URL ?>register.php"><i class="fas fa-user-plus"></i> Register</a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle lang-switch" href="#" id="langDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-globe"></i> <span class="d-none d-md-inline">EN</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="langDropdown">
                            <li><a class="dropdown-item active" href="#">English</a></li>
                            <li><a class="dropdown-item" href="#">नेपाली</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <?= display_alert() ?>
