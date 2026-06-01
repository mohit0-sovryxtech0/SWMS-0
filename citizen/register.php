<?php
require_once __DIR__ . '/../includes/config.php';

// Redirect if already logged in
if (isset($_SESSION['citizen_id'])) {
    redirect(CITIZEN_URL . 'dashboard.php');
}

$pageTitle = 'Citizen Registration';
$error = '';
$success = '';

// Fetch consumer categories
$categories = db()->fetchAll("SELECT id, name FROM consumer_categories WHERE deleted_at IS NULL ORDER BY name");

if (isPost()) {
    $fullName = post('full_name');
    $mobile = post('mobile');
    $email = post('email');
    $consumerNo = post('consumer_no');
    $password = post('password');
    $confirmPassword = post('password_confirm');
    $wardNo = post('ward_no');
    $tole = post('tole');
    $connectionType = post('connection_type', 'household');
    $categoryId = post('category_id');
    $agree = post('agree');

    if (!verify_csrf(post('csrf_token'))) {
        $error = 'Invalid security token. Please try again.';
    } elseif (empty($fullName)) {
        $error = 'Full name is required.';
    } elseif (empty($mobile) || !preg_match('/^(98|97|96)\d{8}$/', $mobile)) {
        $error = 'Please enter a valid Nepali mobile number (98XXXXXXXX).';
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (empty($consumerNo)) {
        $error = 'Consumer number is required.';
    } elseif (empty($password) || strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } elseif (!$agree) {
        $error = 'You must agree to the terms and conditions.';
    } else {
        $db = db();

        // Check if consumer number exists
        $consumer = $db->fetchOne("SELECT id, full_name, mobile, email, password, category_id FROM consumers WHERE consumer_no = ? AND deleted_at IS NULL LIMIT 1", [$consumerNo]);

        if (!$consumer) {
            $error = 'Consumer number not found in our records. Please contact the office.';
        } elseif (!empty($consumer['password'])) {
            $error = 'This consumer number is already registered. Please login.';
        } else {
            if ($db->exists('consumers', 'mobile = ? AND deleted_at IS NULL AND id != ?', [$mobile, $consumer['id']])) {
                $error = 'Mobile number is already registered with another account.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $db->update('consumers', [
                    'full_name' => $fullName,
                    'mobile' => $mobile,
                    'email' => $email ?: null,
                    'password' => $hash,
                    'ward_no' => (int)$wardNo,
                    'tole' => $tole,
                    'connection_type' => $connectionType,
                    'category_id' => $categoryId ? (int)$categoryId : ($consumer['category_id'] ?? null),
                ], 'id = :id', ['id' => $consumer['id']]);

                $success = 'Registration successful! You can now login.';
                alert_success('Registration successful! Please login.');
                redirect(CITIZEN_URL . 'login.php?registered=1');
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
    <div class="auth-card" style="max-width:560px;">
        <div class="auth-header">
            <div class="auth-icon"><i class="fas fa-user-plus"></i></div>
            <h3>Citizen Registration</h3>
            <p>Register for online water services</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i> <?= escape($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success" role="alert"><i class="fas fa-check-circle me-2"></i> <?= escape($success) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <?= csrf_field() ?>

            <div class="row g-3">
                <div class="col-12">
                    <h6 class="fw-bold border-bottom pb-2"><i class="fas fa-user text-primary me-2"></i>Personal Information</h6>
                </div>
                <div class="col-12">
                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="full_name" class="form-control" value="<?= escape(post('full_name')) ?>" required placeholder="Your full name as per citizenship">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Mobile Number <span class="text-danger">*</span></label>
                    <input type="text" name="mobile" class="form-control" value="<?= escape(post('mobile')) ?>" required placeholder="98XXXXXXXX" maxlength="10">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" value="<?= escape(post('email')) ?>" placeholder="email@example.com">
                </div>

                <div class="col-12 mt-3">
                    <h6 class="fw-bold border-bottom pb-2"><i class="fas fa-id-card text-primary me-2"></i>Account Details</h6>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Consumer Number <span class="text-danger">*</span></label>
                    <input type="text" name="consumer_no" class="form-control" value="<?= escape(post('consumer_no')) ?>" required placeholder="e.g. C0001">
                    <small class="text-muted">Provided by the water committee</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Connection Type</label>
                    <select name="connection_type" class="form-select">
                        <option value="household" <?= post('connection_type') == 'household' ? 'selected' : '' ?>>Household</option>
                        <option value="commercial" <?= post('connection_type') == 'commercial' ? 'selected' : '' ?>>Commercial</option>
                        <option value="institutional" <?= post('connection_type') == 'institutional' ? 'selected' : '' ?>>Institutional</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Ward No.</label>
                    <select name="ward_no" class="form-select">
                        <option value="">Select Ward</option>
                        <?php foreach (get_ward_options() as $num => $label): ?>
                            <option value="<?= $num ?>" <?= post('ward_no') == $num ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Tole / Area</label>
                    <input type="text" name="tole" class="form-control" value="<?= escape(post('tole')) ?>" placeholder="e.g. New Baneshwor">
                </div>

                <div class="col-12 mt-3">
                    <h6 class="fw-bold border-bottom pb-2"><i class="fas fa-lock text-danger me-2"></i>Security</h6>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Password <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" name="password" class="form-control" required placeholder="Min 6 characters" minlength="6">
                        <button class="input-group-text toggle-password" type="button"><i class="fas fa-eye"></i></button>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-check-circle"></i></span>
                        <input type="password" name="password_confirm" class="form-control" required placeholder="Re-enter password">
                    </div>
                </div>

                <div class="col-12 mt-3">
                    <div class="form-check">
                        <input type="checkbox" name="agree" id="agree" value="1" class="form-check-input" <?= post('agree') ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="agree">
                            I agree to the <a href="#" class="text-primary">Terms & Conditions</a> and confirm that the information provided is correct. <span class="text-danger">*</span>
                        </label>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 btn-lg mt-4">
                <i class="fas fa-user-plus me-2"></i> Register
            </button>
        </form>

        <div class="auth-footer">
            <p class="mb-0">Already registered? <a href="<?= CITIZEN_URL ?>login.php" class="fw-bold text-primary text-decoration-none">Login Here</a></p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= CITIZEN_URL ?>assets/js/citizen.js"></script>
</body>
</html>
