<?php
require_once __DIR__ . '/../../includes/config.php';
Auth::logout();
redirect(BASE_URL . 'pages/auth/login.php');
