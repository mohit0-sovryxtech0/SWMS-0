<?php
require_once __DIR__ . '/../../includes/config.php';
$pageTitle = 'Edit User';
$breadcrumbs = [
    ['label' => 'User Management', 'url' => ADMIN_URL . 'users/index.php'],
    ['label' => 'Edit User']
];
require_once __DIR__ . '/../includes/header.php';

RBAC::requirePermission('users.edit');

$userId = (int) get('id', 0);
$user = db()->fetchOne("SELECT * FROM users WHERE id = ? AND deleted_at IS NULL", [$userId]);

if (!$user) {
    alert_error('User not found.');
    redirect(ADMIN_URL . 'users/index.php');
}

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
            'email' => "required|email|unique:users,email,{$userId}",
            'username' => "required|min:3|max:50|unique:users,username,{$userId}",
            'role_id' => 'required|numeric',
            'phone' => 'phone',
            'gender' => 'in:male,female,other',
            'status' => 'in:active,inactive,suspended',
        ]);

        if ($v->fails()) {
            alert_error(implode('<br>', $v->allErrors()));
        } else {
            $updateData = [
                'name' => $data['name'],
                'email' => $data['email'],
                'username' => $data['username'],
                'role_id' => $data['role_id'],
                'phone' => $data['phone'] ?: null,
                'gender' => $data['gender'] ?: null,
                'address' => $data['address'] ?: null,
                'designation' => $data['designation'] ?: null,
                'department' => $data['department'] ?: null,
                'status' => $data['status'],
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => Auth::id(),
            ];

            $password = post('password');
            if (!empty($password)) {
                $passwordStrength = Security::validatePasswordStrength($password);
                if ($passwordStrength !== true) {
                    alert_error(implode('<br>', $passwordStrength));
                    require_once __DIR__ . '/../includes/footer.php';
                    exit;
                }
                $updateData['password'] = Security::hashPassword($password);
            }

            db()->update('users', $updateData, 'id = :id', ['id' => $userId]);
            log_activity(Auth::id(), 'update', 'users', "Updated user: {$data['name']} ({$data['email']})", ['user_id' => $userId]);
            alert_success('User updated successfully.');

            if (!isAjax()) {
                redirect(ADMIN_URL . 'users/edit.php?id=' . $userId);
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
            <h4>Edit User</h4>
            <p>Update user information</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= ADMIN_URL ?>users/create.php" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Add New
            </a>
            <a href="<?= ADMIN_URL ?>users/index.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Users
            </a>
        </div>
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
                                <input type="text" name="name" class="form-control" value="<?= escape($user['name']) ?>" required maxlength="100">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Email <span class="required">*</span></label>
                                <input type="email" name="email" class="form-control" value="<?= escape($user['email']) ?>" required maxlength="100">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Username <span class="required">*</span></label>
                                <input type="text" name="username" class="form-control" value="<?= escape($user['username']) ?>" required maxlength="50" autocomplete="off">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">New Password</label>
                                <input type="password" name="password" class="form-control" minlength="8" autocomplete="new-password">
                                <div class="form-text">Leave blank to keep current password. Minimum 8 characters with mixed case, number & special char.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Role <span class="required">*</span></label>
                                <select name="role_id" class="form-select" required>
                                    <option value="">Select Role</option>
                                    <?php foreach ($roles as $role): ?>
                                    <option value="<?= $role['id'] ?>" <?= $user['role_id'] == $role['id'] ? 'selected' : '' ?>><?= escape($role['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="active" <?= $user['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                    <option value="inactive" <?= $user['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                    <option value="suspended" <?= $user['status'] === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                                </select>
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

                    <div class="form-group mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update User
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
                <h5>Account Info</h5>
            </div>
            <div class="card-body">
                <div style="text-align:center;padding:16px 0;">
                    <div style="width:64px;height:64px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:24px;margin:0 auto 12px;">
                        <?= strtoupper(substr($user['name'], 0, 1)) ?>
                    </div>
                    <h6 style="margin:0;"><?= escape($user['name']) ?></h6>
                    <small class="text-muted"><?= escape($user['email']) ?></small>
                </div>
                <hr>
                <div style="font-size:13px;">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Created</span>
                        <span><?= format_datetime($user['created_at']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Last Login</span>
                        <span><?= $user['last_login'] ? format_datetime($user['last_login']) : 'Never' ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Last IP</span>
                        <span><?= escape($user['last_ip'] ?: '-') ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Status</span>
                        <span><?= get_status_badge($user['status']) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <?php if (RBAC::can('users.delete') && Auth::id() != $user['id']): ?>
        <div class="card border-danger">
            <div class="card-header text-danger">
                <h5>Danger Zone</h5>
            </div>
            <div class="card-body">
                <p class="small text-muted">Deleting this user will revoke all access. This action can be reversed by an administrator.</p>
                <button type="button" class="btn btn-danger btn-sm w-100" onclick="confirmDelete(<?= $user['id'] ?>, '<?= escape(addslashes($user['name'])) ?>')">
                    <i class="fas fa-trash"></i> Delete User
                </button>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="<?= ADMIN_URL ?>users/delete.php">
                <?= csrf_field() ?>
                <input type="hidden" name="user_id" id="deleteUserId">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete user <strong id="deleteUserName"></strong>?</p>
                    <p class="text-muted small mb-0">This action will soft-delete the user.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-trash"></i> Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function confirmDelete(id, name) {
    document.getElementById('deleteUserId').value = id;
    document.getElementById('deleteUserName').textContent = name;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
