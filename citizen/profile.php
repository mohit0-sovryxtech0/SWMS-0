<?php
require_once __DIR__ . '/../includes/config.php';
$pageTitle = 'My Profile';
if (!isset($_SESSION['citizen_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    redirect(CITIZEN_URL . 'login.php');
}
require_once __DIR__ . '/includes/header.php';

$consumerId = citizenId();
$db = db();

$consumer = $db->fetchOne("SELECT * FROM consumers WHERE id = ? AND deleted_at IS NULL", [$consumerId]);

if (!$consumer) {
    alert_error('Profile not found.');
    redirect(CITIZEN_URL . 'dashboard.php');
}

// Handle profile update
if (isPost() && isset($_POST['update_profile'])) {
    $fullName = post('full_name');
    $email = post('email');
    $phone = post('phone');
    $tole = post('tole');
    $wardNo = post('ward_no');
    $temporaryAddress = post('temporary_address');

    if (!verify_csrf(post('csrf_token'))) {
        alert_error('Invalid security token.');
    } elseif (empty($fullName)) {
        alert_error('Full name is required.');
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        alert_error('Invalid email address.');
    } else {
        $db->update('consumers', [
            'full_name' => $fullName,
            'email' => $email ?: null,
            'phone' => $phone ?: null,
            'tole' => $tole,
            'ward_no' => (int)$wardNo,
            'temporary_address' => $temporaryAddress
        ], 'id = :id', ['id' => $consumerId]);

        $_SESSION['citizen_name'] = $fullName;
        alert_success('Profile updated successfully.');
        redirect(CITIZEN_URL . 'profile.php');
    }
}

// Handle password change
if (isPost() && isset($_POST['change_password'])) {
    $currentPassword = post('current_password');
    $newPassword = post('new_password');
    $confirmPassword = post('confirm_password');

    if (!verify_csrf(post('csrf_token'))) {
        alert_error('Invalid security token.');
    } elseif (empty($currentPassword) || empty($newPassword)) {
        alert_error('Please fill in all password fields.');
    } elseif (strlen($newPassword) < 6) {
        alert_error('New password must be at least 6 characters.');
    } elseif ($newPassword !== $confirmPassword) {
        alert_error('New passwords do not match.');
    } elseif (!password_verify($currentPassword, $consumer['password'])) {
        alert_error('Current password is incorrect.');
    } else {
        $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        $db->update('consumers', ['password' => $hash], 'id = :id', ['id' => $consumerId]);
        alert_success('Password changed successfully.');
        redirect(CITIZEN_URL . 'profile.php');
    }
}

// Re-fetch consumer data after potential updates
$consumer = $db->fetchOne("SELECT * FROM consumers WHERE id = ? AND deleted_at IS NULL", [$consumerId]);
?>
<div class="page-header">
    <div class="container">
        <h2><i class="fas fa-user-circle me-2"></i> My Profile</h2>
        <p>Manage your account information</p>
    </div>
</div>
<div class="container pb-5">
    <div class="row g-4">
        <!-- Profile Header -->
        <div class="col-12">
            <div class="profile-header-card position-relative">
                <div class="profile-avatar"><?= strtoupper(substr($consumer['full_name'], 0, 1)) ?></div>
                <h4><?= escape($consumer['full_name']) ?></h4>
                <span class="consumer-badge"><i class="fas fa-id-card me-1"></i> <?= escape($consumer['consumer_no']) ?></span>
                <span class="consumer-badge ms-2"><?= get_connection_type_badge($consumer['connection_type']) ?></span>
                <span class="consumer-badge ms-2"><?= get_status_badge($consumer['status']) ?></span>
            </div>
        </div>

        <!-- Edit Profile -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><i class="fas fa-edit me-2 text-primary"></i> Edit Profile</div>
                <div class="card-body">
                    <form method="POST" action="">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" class="form-control" value="<?= escape($consumer['full_name']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-control" value="<?= escape($consumer['email'] ?? '') ?>" placeholder="email@example.com">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control" value="<?= escape($consumer['phone'] ?? '') ?>" placeholder="Landline or alternative">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Mobile <span class="text-muted small">(Not editable)</span></label>
                            <input type="text" class="form-control" value="<?= escape($consumer['mobile']) ?>" disabled>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Consumer No <span class="text-muted small">(Not editable)</span></label>
                            <input type="text" class="form-control" value="<?= escape($consumer['consumer_no']) ?>" disabled>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Ward No.</label>
                                <select name="ward_no" class="form-select">
                                    <option value="">Select Ward</option>
                                    <?php foreach (get_ward_options() as $num => $label): ?>
                                        <option value="<?= $num ?>" <?= ($consumer['ward_no'] ?? '') == $num ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Tole / Area</label>
                                <input type="text" name="tole" class="form-control" value="<?= escape($consumer['tole'] ?? '') ?>" placeholder="e.g. New Baneshwor">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Temporary Address</label>
                            <textarea name="temporary_address" class="form-control" rows="2" placeholder="If different from permanent"><?= escape($consumer['temporary_address'] ?? '') ?></textarea>
                        </div>
                        <button type="submit" name="update_profile" class="btn btn-primary"><i class="fas fa-save me-1"></i> Update Profile</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Change Password + Account Info -->
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header"><i class="fas fa-lock me-2 text-danger"></i> Change Password</div>
                <div class="card-body">
                    <form method="POST" action="">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label">Current Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" name="current_password" class="form-control" placeholder="Enter current password">
                                <button class="input-group-text toggle-password" type="button"><i class="fas fa-eye"></i></button>
                            </div>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">New Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-key"></i></span>
                                    <input type="password" name="new_password" class="form-control" placeholder="Min 6 characters" minlength="6">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Confirm New Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-check-circle"></i></span>
                                    <input type="password" name="confirm_password" class="form-control" placeholder="Re-enter new password">
                                </div>
                            </div>
                        </div>
                        <button type="submit" name="change_password" class="btn btn-danger"><i class="fas fa-key me-1"></i> Change Password</button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><i class="fas fa-info-circle me-2 text-info"></i> Account Details</div>
                <div class="card-body">
                    <table class="table table-citizen mb-0">
                        <tbody>
                            <tr><td class="text-muted" style="width:150px;">Consumer No</td><td class="fw-semibold"><?= escape($consumer['consumer_no']) ?></td></tr>
                            <tr><td class="text-muted">Connection Type</td><td><?= get_connection_type_badge($consumer['connection_type']) ?></td></tr>
                            <tr><td class="text-muted">Registration Date</td><td><?= format_date($consumer['registration_date'] ?? $consumer['created_at']) ?></td></tr>
                            <tr><td class="text-muted">Account Status</td><td><?= get_status_badge($consumer['status']) ?></td></tr>
                            <?php if ($consumer['registered_at']): ?>
                            <tr><td class="text-muted">Portal Registered</td><td><?= format_datetime($consumer['registered_at']) ?></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include_once __DIR__ . '/includes/footer.php'; ?>
