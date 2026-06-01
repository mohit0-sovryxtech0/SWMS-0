<?php
require_once __DIR__ . '/../includes/config.php';

// Redirect if already logged in
if (isset($_SESSION['citizen_id'])) {
    redirect(CITIZEN_URL . 'dashboard.php');
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    redirect(CITIZEN_URL . 'login.php');
}

$pageTitle = 'Citizen Login';
$error = '';
$otpMode = isset($_GET['otp']);

if (isPost()) {
    $loginType = post('login_type', 'password');
    $mobile = post('mobile');
    $consumerNo = post('consumer_no');
    $password = post('password');
    $csrf = post('csrf_token');

    if (!verify_csrf($csrf)) {
        $error = 'Invalid security token. Please try again.';
    } elseif (empty($mobile) && empty($consumerNo)) {
        $error = 'Please enter your mobile number or consumer number.';
    } elseif ($loginType === 'password' && empty($password)) {
        $error = 'Please enter your password.';
    } else {
        $db = db();
        $params = [];
        $where = '';

        if (!empty($consumerNo)) {
            $where = 'consumer_no = :identifier';
            $params['identifier'] = $consumerNo;
        } else {
            $where = 'mobile = :identifier';
            $params['identifier'] = $mobile;
        }

        $where .= ' AND status = \'active\' AND deleted_at IS NULL';

        $consumer = $db->fetchOne("SELECT id, consumer_no, full_name, mobile, email, password, status FROM consumers WHERE {$where} LIMIT 1", $params);

        if (!$consumer) {
            $error = 'No account found with the provided information.';
        } elseif ($loginType === 'password') {
            if (!password_verify($password, $consumer['password'] ?? '')) {
                $error = 'Invalid password. Please try again.';
            } else {
                $_SESSION['citizen_id'] = $consumer['id'];
                $_SESSION['citizen_name'] = $consumer['full_name'];
                $_SESSION['citizen_no'] = $consumer['consumer_no'];

                $redirect = $_SESSION['redirect_after_login'] ?? CITIZEN_URL . 'dashboard.php';
                unset($_SESSION['redirect_after_login']);
                redirect($redirect);
            }
        } else {
            // OTP mode placeholder — generate OTP logic here
            // For now, fallback to password
            if (!password_verify($password, $consumer['password'] ?? '')) {
                $error = 'Invalid credentials. Please try again.';
            } else {
                $_SESSION['citizen_id'] = $consumer['id'];
                $_SESSION['citizen_name'] = $consumer['full_name'];
                $_SESSION['citizen_no'] = $consumer['consumer_no'];

                $redirect = $_SESSION['redirect_after_login'] ?? CITIZEN_URL . 'dashboard.php';
                unset($_SESSION['redirect_after_login']);
                redirect($redirect);
            }
        }
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
</head>
<body class="auth-page">
    <div class="auth-card">
        <div class="auth-header">
            <div class="auth-icon"><i class="fas fa-water"></i></div>
            <h3>Citizen Login</h3>
            <p><?= APP_ORG ?>, Government of Nepal</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i> <?= escape($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($_GET['registered'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i> Registration successful! Please login with your credentials.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="login_type" id="loginType" value="password">

            <div class="form-group mb-3">
                <label class="form-label">Consumer Number</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                    <input type="text" name="consumer_no" class="form-control" placeholder="e.g. C0001" value="<?= escape(post('consumer_no')) ?>">
                </div>
                <div class="text-center my-2"><small class="text-muted">— or —</small></div>
                <label class="form-label">Mobile Number</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-mobile-alt"></i></span>
                    <input type="text" name="mobile" class="form-control" placeholder="98XXXXXXXX" value="<?= escape(post('mobile')) ?>">
                </div>
            </div>

            <div class="form-group mb-3">
                <label class="form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    <input type="password" name="password" class="form-control" placeholder="Enter your password" autocomplete="current-password">
                    <button class="input-group-text toggle-password" type="button"><i class="fas fa-eye"></i></button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 btn-lg mb-3">
                <i class="fas fa-sign-in-alt me-2"></i> Sign In
            </button>
        </form>

        <div class="auth-divider"><span>New Citizen?</span></div>

        <div class="auth-footer">
            <p class="mb-2">Don't have an account? <a href="<?= CITIZEN_URL ?>register.php" class="fw-bold text-primary text-decoration-none">Register Here</a></p>
            <p class="mb-0"><a href="<?= CITIZEN_URL ?>complaint-track.php" class="text-muted text-decoration-none small"><i class="fas fa-search me-1"></i> Track Complaint without Login</a></p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= CITIZEN_URL ?>assets/js/citizen.js"></script>
</body>
</html>
