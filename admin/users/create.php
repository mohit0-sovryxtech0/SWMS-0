<?php
require_once __DIR__ . '/../../includes/config.php';
$pageTitle = 'Create User';
$breadcrumbs = [
    ['label' => 'User Management', 'url' => ADMIN_URL . 'users/index.php'],
    ['label' => 'Create User']
];
require_once __DIR__ . '/../includes/header.php';

RBAC::requirePermission('users.create');

$roles = db()->fetchAll("SELECT id, name FROM roles WHERE deleted_at IS NULL ORDER BY name");
$genders = get_gender_options();

if (isPost()) {
    $csrf = post('csrf_token');
    if (!verify_csrf($csrf)) {
        alert_error('Invalid security token. Please try again.');
    } else {
        $data = [
            'name' => trim(post('name', '')),
            'email' => trim(post('email', '')),
            'username' => trim(post('username', '')),
            'password' => post('password', ''),
            'role_id' => (int) post('role_id', 0),
            'phone' => trim(post('phone', '')),
            'gender' => post('gender', ''),
            'address' => trim(post('address', '')),
            'designation' => trim(post('designation', '')),
            'department' => trim(post('department', '')),
            'status' => post('status', 'active'),
        ];

        $v = validator($data, [
            'name' => 'required|min:2|max:100',
            'email' => 'required|email|unique:users,email',
            'username' => 'required|min:3|max:50|unique:users,username',
            'password' => 'required|min:8',
            'role_id' => 'required|numeric',
            'phone' => 'phone',
            'gender' => 'in:male,female,other',
            'status' => 'in:active,inactive,suspended',
        ]);

        if ($v->fails()) {
            $_SESSION['old'] = $data;
            alert_error(implode('<br>', $v->allErrors()));
        } else {
            $passwordStrength = Security::validatePasswordStrength($data['password']);
            if ($passwordStrength !== true) {
                $_SESSION['old'] = $data;
                alert_error(implode('<br>', $passwordStrength));
            } else {
                $hash = Security::hashPassword($data['password']);
                $userId = db()->insert('users', [
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'username' => $data['username'],
                    'password' => $hash,
                    'role_id' => $data['role_id'],
                    'phone' => $data['phone'] ?: null,
                    'gender' => $data['gender'] ?: null,
                    'address' => $data['address'] ?: null,
                    'designation' => $data['designation'] ?: null,
                    'department' => $data['department'] ?: null,
                    'status' => $data['status'],
                    'created_at' => date('Y-m-d H:i:s'),
                    'created_by' => Auth::id(),
                ]);

                if ($userId) {
                    log_activity(Auth::id(), 'create', 'users', "Created user: {$data['name']} ({$data['email']})", ['user_id' => $userId]);
                    alert_success('User created successfully.');
                    if (!isAjax()) {
                        redirect(ADMIN_URL . 'users/index.php');
                    }
                } else {
                    alert_error('Failed to create user. Please try again.');
                }
            }
        }
    }

    if (isAjax()) {
        $flash = flash();
        json_response(['success' => isset($flash['success']), 'message' => $flash['success'] ?? $flash['error'] ?? '']);
    }
}
?>
<div class="page-header">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h4>Create User</h4>
            <p>Add a new system user</p>
        </div>
        <a href="<?= ADMIN_URL ?>users/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Back to Users
        </a>
    </div>
</div>

<?= display_alert() ?>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5>User Information</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <?= csrf_field() ?>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Full Name <span class="required">*</span></label>
                                <input type="text" name="name" class="form-control" value="<?= escape(old('name')) ?>" required maxlength="100">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Email <span class="required">*</span></label>
                                <input type="email" name="email" class="form-control" value="<?= escape(old('email')) ?>" required maxlength="100">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Username <span class="required">*</span></label>
                                <input type="text" name="username" class="form-control" value="<?= escape(old('username')) ?>" required maxlength="50" autocomplete="off">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Password <span class="required">*</span></label>
                                <input type="password" name="password" class="form-control" required minlength="8" autocomplete="new-password">
                                <div class="form-text">Minimum 8 characters with uppercase, lowercase, number & special character</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Role <span class="required">*</span></label>
                                <select name="role_id" class="form-select" required>
                                    <option value="">Select Role</option>
                                    <?php foreach ($roles as $role): ?>
                                    <option value="<?= $role['id'] ?>" <?= old('role_id') == $role['id'] ? 'selected' : '' ?>><?= escape($role['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="active" <?= old('status') === 'active' ? 'selected' : '' ?>>Active</option>
                                    <option value="inactive" <?= old('status') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                    <option value="suspended" <?= old('status') === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Phone</label>
                                <input type="text" name="phone" class="form-control" value="<?= escape(old('phone')) ?>" maxlength="20">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Gender</label>
                                <select name="gender" class="form-select">
                                    <option value="">Select Gender</option>
                                    <?php foreach ($genders as $val => $label): ?>
                                    <option value="<?= $val ?>" <?= old('gender') === $val ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Designation</label>
                                <input type="text" name="designation" class="form-control" value="<?= escape(old('designation')) ?>" maxlength="100">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Department</label>
                                <input type="text" name="department" class="form-control" value="<?= escape(old('department')) ?>" maxlength="100">
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-group">
                                <label class="form-label">Address</label>
                                <textarea name="address" class="form-control" rows="2" maxlength="255"><?= escape(old('address')) ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Create User
                        </button>
                        <a href="<?= ADMIN_URL ?>users/index.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5>Guidelines</h5>
            </div>
            <div class="card-body">
                <ul style="font-size:13px;color:var(--text-muted);padding-left:16px;margin:0;">
                    <li class="mb-2">All fields marked with <span class="required">*</span> are required.</li>
                    <li class="mb-2">Username must be unique and at least 3 characters.</li>
                    <li class="mb-2">Email must be unique and valid.</li>
                    <li class="mb-2">Password must be at least 8 characters with mixed case, numbers, and special characters.</li>
                    <li class="mb-2">Users receive an email notification upon account creation.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php
unset($_SESSION['old']);
require_once __DIR__ . '/../includes/footer.php'; ?>
