<?php
require_once __DIR__ . '/../../includes/config.php';

// Redirect if already logged in
if (Auth::check()) {
    redirect(ADMIN_URL . 'dashboard/index.php');
}

$error = '';

// Clear any rate limit session data
unset($_SESSION['rate_limit_login_' . $_SERVER['REMOTE_ADDR']]);

if (isPost()) {
    $username = post('username');
    $password = post('password');
    $remember = post('remember') === '1';

    // Verify CSRF
    if (!verify_csrf(post('csrf_token'))) {
        $error = 'Invalid security token. Please try again.';
    } elseif (empty($username) || empty($password)) {
        $error = 'Please enter username and password.';
    } else {
        $user = Auth::login($username, $password, $remember);
        if ($user) {
            log_activity($user['id'], 'login', 'auth', 'Login successful');
            redirect(ADMIN_URL . 'dashboard/index.php');
        } else {
            $error = 'Invalid credentials or account is locked/suspended.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="<?= ADMIN_URL ?>assets/css/admin.css" rel="stylesheet">
    <style>
        .login-page { background: linear-gradient(135deg, #181CB8 0%, #0f1180 50%, #0a0c5e 100%); }
        .login-card { position: relative; overflow: hidden; }
        .login-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(24,28,184,0.03) 0%, transparent 70%);
            pointer-events: none;
        }
        .login-footer { position: fixed; bottom: 20px; text-align: center; width: 100%; color: rgba(255,255,255,0.6); font-size: 12px; }
    </style>
</head>
<body class="login-page">
    <div class="login-card">
        <div class="login-logo">
            <div class="logo-icon">
                <i class="fas fa-water"></i>
            </div>
            <h3><?= APP_NAME ?></h3>
            <p>Drinking Water & Sanitation Consumer Committee</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i> <?= escape($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="POST" action="" data-validate>
            <?= csrf_field() ?>
            
            <div class="form-group">
                <label class="form-label">Username or Email</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                    <input type="text" name="username" class="form-control" placeholder="Enter username or email" required autofocus autocomplete="username">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    <input type="password" name="password" class="form-control" placeholder="Enter password" required autocomplete="current-password">
                    <button class="input-group-text toggle-password" type="button">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>

            <div class="form-group d-flex justify-content-between align-items-center">
                <div class="form-check">
                    <input type="checkbox" name="remember" id="remember" value="1" class="form-check-input">
                    <label class="form-check-label" for="remember" style="font-size:13px;">Remember me</label>
                </div>
                <a href="forgot-password.php" style="font-size:13px; color:var(--primary); text-decoration:none;">Forgot Password?</a>
            </div>

            <button type="submit" class="btn btn-primary w-100 btn-lg">
                <i class="fas fa-sign-in-alt"></i> Sign In
            </button>
        </form>

        <div class="text-center mt-4">
            <p style="font-size:12px; color:var(--text-muted); margin:0;">
                <i class="fas fa-shield-alt me-1"></i> Secured with 256-bit encryption
            </p>
        </div>
    </div>

    <div class="login-footer">
        &copy; <?= date('Y') ?> <?= APP_NAME ?>. All rights reserved. | Government of Nepal
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.querySelector('.toggle-password')?.addEventListener('click', function() {
        var input = this.previousElementSibling;
        var icon = this.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    });
    </script>
</body>
</html>
