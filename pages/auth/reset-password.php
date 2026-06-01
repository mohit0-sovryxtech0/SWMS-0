<?php
require_once __DIR__ . '/../../includes/config.php';

if (Auth::check()) {
    redirect(ADMIN_URL . 'dashboard/index.php');
}

$token = get('token', '');
$email = get('email', '');
$error = '';
$message = '';
$valid = false;

if (!empty($token) && !empty($email)) {
    $valid = Auth::validateResetToken($token, $email);
    if (!$valid) {
        $error = 'Invalid or expired reset link. Please request a new password reset.';
        Security::logSecurityEvent('invalid_reset_token', ['email' => $email]);
    }
} else {
    $error = 'Missing reset token or email.';
}

if (isPost() && $valid) {
    $csrf = post('csrf_token');
    if (!verify_csrf($csrf)) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $password = post('password', '');
        $confirm = post('confirm_password', '');

        if (empty($password) || empty($confirm)) {
            $error = 'Please enter and confirm your new password.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            $strength = Security::validatePasswordStrength($password);
            if ($strength !== true) {
                $error = implode('<br>', $strength);
            } else {
                $rateKey = 'reset_' . $_SERVER['REMOTE_ADDR'];
                if (!Security::checkRateLimit($rateKey, 3, 300)) {
                    $error = 'Too many attempts. Please try again in 5 minutes.';
                } else {
                    Auth::updatePassword($email, $password);
                    Security::logSecurityEvent('password_reset_completed', ['email' => $email]);
                    $message = 'Password has been reset successfully. You can now login with your new password.';
                    $valid = false;
                }
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
    <title>Reset Password - <?= APP_NAME ?></title>
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

        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i> <?= escape($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <div class="text-center mt-3">
                <a href="login.php" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i> Proceed to Login
                </a>
            </div>
        <?php elseif ($valid): ?>
            <h5 style="text-align:center;margin-bottom:8px;font-weight:600;">Reset Password</h5>
            <p style="text-align:center;font-size:13px;color:var(--text-muted);margin-bottom:24px;">
                Enter your new password for <strong><?= escape($email) ?></strong>
            </p>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i> <?= $error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= escape($token) ?>">
                <input type="hidden" name="email" value="<?= escape($email) ?>">

                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" name="password" class="form-control" placeholder="Enter new password" required minlength="8" autocomplete="new-password">
                    </div>
                    <div class="form-text">Minimum 8 characters with uppercase, lowercase, number & special character.</div>
                </div>

                <div class="form-group">
                    <label class="form-label">Confirm Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-check-circle"></i></span>
                        <input type="password" name="confirm_password" class="form-control" placeholder="Confirm new password" required autocomplete="new-password">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 btn-lg">
                    <i class="fas fa-key"></i> Reset Password
                </button>
            </form>

            <div class="text-center mt-4">
                <a href="login.php" style="font-size:13px;color:var(--primary);text-decoration:none;">
                    <i class="fas fa-arrow-left me-1"></i> Back to Login
                </a>
            </div>
        <?php else: ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i> <?= escape($error ?: 'Invalid reset link.') ?>
            </div>
            <div class="text-center mt-3">
                <a href="forgot-password.php" class="btn btn-primary">
                    <i class="fas fa-redo"></i> Request New Reset Link
                </a>
                <a href="login.php" class="btn btn-outline-primary mt-2">
                    <i class="fas fa-arrow-left"></i> Back to Login
                </a>
            </div>
        <?php endif; ?>
    </div>

    <div class="login-footer">
        &copy; <?= date('Y') ?> <?= APP_NAME ?>. All rights reserved. | Government of Nepal
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
