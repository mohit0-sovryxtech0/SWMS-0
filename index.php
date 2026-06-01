<?php
require_once __DIR__ . '/includes/config.php';

if (Auth::check()) {
    redirect(ADMIN_URL . 'dashboard/index.php');
} else {
    redirect(BASE_URL . 'pages/auth/login.php');
}
