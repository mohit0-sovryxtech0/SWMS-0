<?php
require_once __DIR__ . '/../../includes/config.php';
$pageTitle = 'Permission Management';
$breadcrumbs = [
    ['label' => 'User Management', 'url' => ADMIN_URL . 'users/index.php'],
    ['label' => 'Permissions']
];
require_once __DIR__ . '/../includes/header.php';

RBAC::requirePermission('roles.manage');

$roles = RBAC::getAllRoles();
$selectedRoleId = (int) get('role_id', $roles[0]['id'] ?? 0);
$groupedPermissions = RBAC::getPermissionsGrouped();

if ($selectedRoleId > 0) {
    $currentPerms = db()->fetchAll(
        "SELECT permission_id FROM role_permissions WHERE role_id = ?", [$selectedRoleId]
    );
    $currentPermIds = array_column($currentPerms, 'permission_id');
} else {
    $currentPermIds = [];
}

// Assign/revoke permission via AJAX
if (isPost()) {
    if (!verify_csrf(post('csrf_token'))) {
        if (isAjax()) {
            json_error('Invalid security token.');
        }
        alert_error('Invalid security token.');
    } else {
        $roleId = (int) post('role_id', 0);
        $permissionId = (int) post('permission_id', 0);
        $action = post('perm_action', '');

        if ($roleId <= 0 || $permissionId <= 0 || !in_array($action, ['assign', 'revoke'])) {
            if (isAjax()) {
                json_error('Invalid parameters.');
            }
            alert_error('Invalid parameters.');
        } else {
            $role = db()->fetchOne("SELECT * FROM roles WHERE id = ? AND deleted_at IS NULL", [$roleId]);
            $perm = db()->fetchOne("SELECT * FROM permissions WHERE id = ?", [$permissionId]);

            if (!$role || !$perm) {
                if (isAjax()) {
                    json_error('Role or permission not found.');
                }
                alert_error('Role or permission not found.');
            } elseif ($role['slug'] === 'super_admin') {
                if (isAjax()) {
                    json_error('Super Admin has all permissions by default.');
                }
                alert_error('Super Admin has all permissions by default.');
            } else {
                if ($action === 'assign') {
                    RBAC::assignPermission($roleId, $permissionId);
                    log_activity(Auth::id(), 'assign_permission', 'users', "Assigned permission '{$perm['name']}' to role '{$role['name']}'");
                } else {
                    RBAC::revokePermission($roleId, $permissionId);
                    log_activity(Auth::id(), 'revoke_permission', 'users', "Revoked permission '{$perm['name']}' from role '{$role['name']}'");
                }
                if (isAjax()) {
                    json_success([], "Permission {$action}ed successfully.");
                }
                alert_success("Permission {$action}ed successfully.");
            }
        }
    }
    if (!isAjax()) {
        redirect(ADMIN_URL . 'users/permissions.php?role_id=' . $selectedRoleId);
    }
}
?>
<div class="page-header">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h4>Permission Management</h4>
            <p>Assign or revoke permissions to roles</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= ADMIN_URL ?>users/roles.php" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-shield-alt"></i> Roles
            </a>
            <a href="<?= ADMIN_URL ?>users/index.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>
</div>

<?= display_alert() ?>

<div class="card">
    <div class="card-body">
        <div class="filter-bar mb-3">
            <label class="form-label mb-0 me-2" style="font-weight:600;">Select Role:</label>
            <form method="GET" action="" class="d-flex gap-2">
                <select name="role_id" class="form-select" onchange="this.form.submit()" style="min-width:250px;">
                    <?php foreach ($roles as $role): ?>
                    <option value="<?= $role['id'] ?>" <?= $selectedRoleId == $role['id'] ? 'selected' : '' ?>><?= escape($role['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <?php if ($selectedRoleId > 0): ?>
        <?php
        $selRole = db()->fetchOne("SELECT * FROM roles WHERE id = ?", [$selectedRoleId]);
        $isSuperAdmin = $selRole && $selRole['slug'] === 'super_admin';
        ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            <?php if ($isSuperAdmin): ?>
            <strong>Super Admin</strong> role has all permissions by default. Permission management is not required.
            <?php else: ?>
            Toggle permissions for <strong><?= escape($selRole['name']) ?></strong>. Checked = assigned, unchecked = revoked.
            <?php endif; ?>
        </div>

        <?php if (!$isSuperAdmin && !empty($groupedPermissions)): ?>
        <div class="accordion" id="permAccordion">
            <?php $moduleIndex = 0; ?>
            <?php foreach ($groupedPermissions as $module => $perms): ?>
            <div class="accordion-item" style="border:1px solid var(--border-color);border-radius:8px;margin-bottom:8px;">
                <h2 class="accordion-header">
                    <button class="accordion-button <?= $moduleIndex > 0 ? 'collapsed' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#module<?= $moduleIndex ?>" style="font-size:14px;font-weight:600;">
                        <?= escape(ucfirst($module)) ?>
                        <span class="badge bg-primary ms-2"><?= count($perms) ?></span>
                    </button>
                </h2>
                <div id="module<?= $moduleIndex ?>" class="accordion-collapse collapse <?= $moduleIndex === 0 ? 'show' : '' ?>" data-bs-parent="#permAccordion">
                    <div class="accordion-body">
                        <div class="row g-2">
                            <?php foreach ($perms as $perm): ?>
                            <?php
                            $isAssigned = in_array($perm['id'], $currentPermIds);
                            ?>
                            <div class="col-md-4 col-sm-6">
                                <div class="form-check" style="padding:8px 12px;border-radius:6px;background:var(--bg-color);">
                                    <input type="checkbox" class="form-check-input perm-toggle"
                                           id="perm_<?= $perm['id'] ?>"
                                           data-role-id="<?= $selectedRoleId ?>"
                                           data-perm-id="<?= $perm['id'] ?>"
                                           data-csrf="<?= csrf_token() ?>"
                                           <?= $isAssigned ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="perm_<?= $perm['id'] ?>" style="font-size:13px;cursor:pointer;">
                                        <?= escape($perm['name']) ?>
                                        <small class="d-block text-muted" style="font-size:11px;"><?= escape($perm['slug']) ?></small>
                                    </label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php $moduleIndex++; ?>
            <?php endforeach; ?>
        </div>
        <?php elseif (!$isSuperAdmin): ?>
        <div class="empty-state">
            <i class="fas fa-key"></i>
            <h5>No Permissions Found</h5>
            <p>No permissions have been defined in the system yet.</p>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
$(document).ready(function() {
    $('.perm-toggle').on('change', function() {
        var cb = $(this);
        var roleId = cb.data('role-id');
        var permId = cb.data('perm-id');
        var action = cb.is(':checked') ? 'assign' : 'revoke';
        var csrf = cb.data('csrf');

        $.ajax({
            url: '<?= ADMIN_URL ?>users/permissions.php?role_id=' + roleId,
            type: 'POST',
            data: {
                csrf_token: csrf,
                role_id: roleId,
                permission_id: permId,
                perm_action: action
            },
            dataType: 'json',
            success: function(resp) {
                if (!resp.success) {
                    cb.prop('checked', !cb.is(':checked'));
                    alert(resp.message);
                }
            },
            error: function() {
                cb.prop('checked', !cb.is(':checked'));
                alert('An error occurred. Please try again.');
            }
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
