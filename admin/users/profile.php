<?php
$pageTitle = 'My Profile';
$breadcrumbs = [['label' => 'My Profile']];
require_once __DIR__ . '/../includes/header.php';

$user = Auth::user();
$genders = get_gender_options();

// Update profile
if (isPost() && post('action') === 'update_profile') {
    if (!verify_csrf(post('csrf_token'))) {
        alert_error('Invalid security token.');
    } else {
        $data = [
            'name' => trim(post('name', '')),
            'phone' => trim(post('phone', '')),
            'gender' => post('gender', ''),
            'address' => trim(post('address', '')),
            'designation' => trim(post('designation', '')),
            'department' => trim(post('department', '')),
        ];

        $v = validator($data, [
            'name' => 'required|min:2|max:100',
            'phone' => 'phone',
            'gender' => 'in:male,female,other',
        ]);

        if ($v->fails()) {
            alert_error(implode('<br>', $v->allErrors()));
        } else {
            $data['updated_at'] = date('Y-m-d H:i:s');
            db()->update('users', $data, 'id = :id', ['id' => Auth::id()]);
            log_activity(Auth::id(), 'update_profile', 'users', 'Updated profile');
            alert_success('Profile updated successfully.');
            $user = Auth::user();
        }
    }
}

// Change password
if (isPost() && post('action') === 'change_password') {
    if (!verify_csrf(post('csrf_token'))) {
        alert_error('Invalid security token.');
    } else {
        $currentPassword = post('current_password');
        $newPassword = post('new_password');
        $confirmPassword = post('confirm_password');

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            alert_error('All password fields are required.');
        } elseif ($newPassword !== $confirmPassword) {
            alert_error('New password and confirmation do not match.');
        } else {
            $strength = Security::validatePasswordStrength($newPassword);
            if ($strength !== true) {
                alert_error(implode('<br>', $strength));
            } else {
                $result = Auth::changePassword(Auth::id(), $currentPassword, $newPassword);
                if ($result) {
                    log_activity(Auth::id(), 'change_password', 'users', 'Changed password');
                    Security::logSecurityEvent('password_changed', ['user_id' => Auth::id()]);
                    alert_success('Password changed successfully.');
                } else {
                    alert_error('Current password is incorrect.');
                }
            }
        }
    }
}
?>
<div class="page-header">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h4>My Profile</h4>
            <p>Manage your account information and security</p>
        </div>
    </div>
</div>

<?= display_alert() ?>

<div class="row">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body text-center">
                <div style="width:80px;height:80px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:32px;margin:0 auto 16px;">
                    <?= strtoupper(substr($user['name'], 0, 1)) ?>
                </div>
                <h5 style="margin:0;"><?= escape($user['name']) ?></h5>
                <small class="text-muted"><?= escape($user['role_name']) ?></small>
                <hr>
                <div style="text-align:left;font-size:13px;">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Email</span>
                        <span><?= escape($user['email']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Username</span>
                        <span><?= escape($user['username']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Status</span>
                        <span><?= get_status_badge($user['status']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Member Since</span>
                        <span><?= format_date($user['created_at'], 'M Y') ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Last Login</span>
                        <span><?= $user['last_login'] ? time_ago($user['last_login']) : 'Never' ?></span>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($user['designation'] || $user['department']): ?>
        <div class="card">
            <div class="card-header">
                <h5>Work Info</h5>
            </div>
            <div class="card-body" style="font-size:13px;">
                <?php if ($user['designation']): ?>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Designation</span>
                    <span><?= escape($user['designation']) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($user['department']): ?>
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Department</span>
                    <span><?= escape($user['department']) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5>Edit Profile</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_profile">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Full Name <span class="required">*</span></label>
                                <input type="text" name="name" class="form-control" value="<?= escape($user['name']) ?>" required maxlength="100">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" value="<?= escape($user['email']) ?>" disabled>
                                <div class="form-text">Email cannot be changed. Contact administrator.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Phone</label>
                                <input type="text" name="phone" class="form-control" value="<?= escape($user['phone']) ?>" maxlength="20">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Gender</label>
                                <select name="gender" class="form-select">
                                    <option value="">Select Gender</option>
                                    <?php foreach ($genders as $val => $label): ?>
                                    <option value="<?= $val ?>" <?= $user['gender'] === $val ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Designation</label>
                                <input type="text" name="designation" class="form-control" value="<?= escape($user['designation']) ?>" maxlength="100">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Department</label>
                                <input type="text" name="department" class="form-control" value="<?= escape($user['department']) ?>" maxlength="100">
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                <label class="form-label">Address</label>
                                <textarea name="address" class="form-control" rows="2" maxlength="255"><?= escape($user['address']) ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5>Change Password</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="change_password">

                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Current Password <span class="required">*</span></label>
                                <input type="password" name="current_password" class="form-control" required autocomplete="current-password">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">New Password <span class="required">*</span></label>
                                <input type="password" name="new_password" class="form-control" required minlength="8" autocomplete="new-password">
                                <div class="form-text">Min 8 chars, mixed case, number & special char</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="form-label">Confirm Password <span class="required">*</span></label>
                                <input type="password" name="confirm_password" class="form-control" required autocomplete="new-password">
                            </div>
                        </div>
                    </div>

                    <div class="form-group mt-3">
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-key"></i> Change Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
