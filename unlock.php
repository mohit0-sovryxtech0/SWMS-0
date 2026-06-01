<?php
require_once __DIR__ . '/includes/config.php';
db()->update('users', ['is_locked' => 0, 'locked_until' => null, 'login_attempts' => 0], "username = 'admin' OR username = 'admin@swms.gov.np'", []);
db()->delete('login_attempts', 'username = ? OR username = ?', ['admin', 'admin@swms.gov.np']);
echo "Account unlocked. <a href='pages/auth/login.php'>Login now</a>";
