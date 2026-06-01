<?php
require_once __DIR__ . '/../../includes/config.php';

if (Auth::check()) {
    redirect(ADMIN_URL . 'dashboard/index.php');
}

$message = '';
$error = '';

if (isPost()) {
    $csrf = post('csrf_token');
    if (!verify_csrf($csrf)) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $email = trim(post('email', ''));
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $rateKey = 'forgot_' . $_SERVER['REMOTE_ADDR'];
            if (!Security::checkRateLimit($rateKey, 3, 300)) {
                $error = 'Too many requests. Please try again in 5 minutes.';
            } else {
                $result = Auth::passwordReset($email);
                if ($result) {
                    Security::logSecurityEvent('password_reset_requested', ['email' => $email]);
                }
                $message = 'If an account exists with that email, a password reset link has been sent. Please check your inbox.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="<?= ADMIN_URL ?>assets/css/admin.css" rel="stylesheet">
    <style>
        .login-page { background: linear-gradient(135deg, #181CB8 0%, #0f1180 50%, #0a0c5e 100%); }
        .login-card { position: relative; overflow: hidden; }
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

        <h5 style="text-align:center;margin-bottom:8px;font-weight:600;">Forgot Password?</h5>
        <p style="text-align:center;font-size:13px;color:var(--text-muted);margin-bottom:24px;">
            Enter your registered email address and we'll send you a password reset link.
        </p>

        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i> <?= escape($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i> <?= escape($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label">Email Address</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                    <input type="email" name="email" class="form-control" placeholder="Enter your registered email" required autofocus autocomplete="email">
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 btn-lg">
                <i class="fas fa-paper-plane"></i> Send Reset Link
            </button>
        </form>

        <div class="text-center mt-4">
            <a href="login.php" style="font-size:13px;color:var(--primary);text-decoration:none;">
                <i class="fas fa-arrow-left me-1"></i> Back to Login
            </a>
        </div>
    </div>

    <div class="login-footer">
        &copy; <?= date('Y') ?> <?= APP_NAME ?>. All rights reserved. | Government of Nepal
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
