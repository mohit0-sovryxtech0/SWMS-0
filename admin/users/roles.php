<?php
require_once __DIR__ . '/../../includes/config.php';
$pageTitle = 'Role Management';
$breadcrumbs = [
    ['label' => 'User Management', 'url' => ADMIN_URL . 'users/index.php'],
    ['label' => 'Roles']
];
require_once __DIR__ . '/../includes/header.php';

RBAC::requirePermission('roles.manage');

// Create role
if (isPost() && post('action') === 'create_role') {
    if (!verify_csrf(post('csrf_token'))) {
        alert_error('Invalid security token.');
    } else {
        $name = trim(post('name', ''));
        $slug = trim(post('slug', ''));
        $description = trim(post('description', ''));

        if (empty($name) || empty($slug)) {
            alert_error('Role name and slug are required.');
        } elseif (db()->exists('roles', 'slug = :slug AND deleted_at IS NULL', ['slug' => $slug])) {
            alert_error('Role slug already exists.');
        } else {
            RBAC::createRole([
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
            ]);
            log_activity(Auth::id(), 'create_role', 'users', "Created role: {$name}");
            alert_success('Role created successfully.');
            redirect(ADMIN_URL . 'users/roles.php');
        }
    }
}

// Update role
if (isPost() && post('action') === 'update_role') {
    if (!verify_csrf(post('csrf_token'))) {
        alert_error('Invalid security token.');
    } else {
        $roleId = (int) post('role_id', 0);
        $name = trim(post('name', ''));
        $description = trim(post('description', ''));

        if ($roleId <= 0 || empty($name)) {
            alert_error('Invalid role data.');
        } else {
            RBAC::updateRole($roleId, [
                'name' => $name,
                'description' => $description,
            ]);
            log_activity(Auth::id(), 'update_role', 'users', "Updated role ID: {$roleId}");
            alert_success('Role updated successfully.');
            redirect(ADMIN_URL . 'users/roles.php');
        }
    }
}

// Delete role
if (isPost() && post('action') === 'delete_role') {
    if (!verify_csrf(post('csrf_token'))) {
        alert_error('Invalid security token.');
    } else {
        $roleId = (int) post('role_id', 0);
        $role = db()->fetchOne("SELECT * FROM roles WHERE id = ? AND deleted_at IS NULL", [$roleId]);

        if (!$role) {
            alert_error('Role not found.');
        } elseif ($role['slug'] === 'super_admin') {
            alert_error('Cannot delete the Super Admin role.');
        } elseif (db()->exists('users', 'role_id = :r AND deleted_at IS NULL', ['r' => $roleId])) {
            alert_error('Cannot delete role with assigned users. Reassign users first.');
        } else {
            db()->delete('role_permissions', 'role_id = :r', ['r' => $roleId]);
            RBAC::deleteRole($roleId);
            log_activity(Auth::id(), 'delete_role', 'users', "Deleted role: {$role['name']}");
            alert_success('Role deleted successfully.');
            redirect(ADMIN_URL . 'users/roles.php');
        }
    }
}

$roles = RBAC::getAllRoles();
$editRole = null;
$editId = get('edit');
if ($editId) {
    $editRole = db()->fetchOne("SELECT * FROM roles WHERE id = ? AND deleted_at IS NULL", [(int)$editId]);
}
?>
<div class="page-header">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h4>Role Management</h4>
            <p>Create, edit, and manage user roles</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createRoleModal">
                <i class="fas fa-plus"></i> Add Role
            </button>
            <a href="<?= ADMIN_URL ?>users/permissions.php" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-key"></i> Permissions
            </a>
            <a href="<?= ADMIN_URL ?>users/index.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>
</div>

<?= display_alert() ?>

<div class="row">
    <?php foreach ($roles as $role): ?>
    <div class="col-xl-4 col-md-6">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <h5 style="margin:0;font-size:15px;"><?= escape($role['name']) ?></h5>
                        <small class="text-muted">Slug: <?= escape($role['slug']) ?></small>
                    </div>
                    <?php if ($role['slug'] === 'super_admin'): ?>
                    <span class="badge bg-warning">System</span>
                    <?php endif; ?>
                </div>
                <?php if ($role['description']): ?>
                <p style="font-size:13px;color:var(--text-muted);margin:8px 0;"><?= escape($role['description']) ?></p>
                <?php endif; ?>
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <small class="text-muted">
                        <?php
                        $userCount = db()->fetchColumn("SELECT COUNT(*) FROM users WHERE role_id = ? AND deleted_at IS NULL", [$role['id']]);
                        $permCount = db()->fetchColumn("SELECT COUNT(*) FROM role_permissions WHERE role_id = ?", [$role['id']]);
                        ?>
                        <i class="fas fa-users"></i> <?= $userCount ?> users &middot;
                        <i class="fas fa-key"></i> <?= $permCount ?> permissions
                    </small>
                    <div class="table-actions">
                        <button type="button" class="btn-action edit" title="Edit" onclick="editRole(<?= $role['id'] ?>, '<?= escape(addslashes($role['name'])) ?>', '<?= escape(addslashes($role['description'])) ?>')">
                            <i class="fas fa-edit"></i>
                        </button>
                        <?php if ($role['slug'] !== 'super_admin'): ?>
                        <button type="button" class="btn-action delete" title="Delete" onclick="deleteRole(<?= $role['id'] ?>, '<?= escape(addslashes($role['name'])) ?>')">
                            <i class="fas fa-trash"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Create Role Modal -->
<div class="modal fade" id="createRoleModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create_role">
                <div class="modal-header">
                    <h5 class="modal-title">Create Role</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Role Name <span class="required">*</span></label>
                        <input type="text" name="name" class="form-control" required maxlength="50" placeholder="e.g., Manager">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Slug <span class="required">*</span></label>
                        <input type="text" name="slug" class="form-control" required maxlength="50" placeholder="e.g., manager" pattern="^[a-z0-9_]+$">
                        <div class="form-text">Lowercase letters, numbers, and underscores only.</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2" maxlength="255" placeholder="Optional description"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Create</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Role Modal -->
<div class="modal fade" id="editRoleModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_role">
                <input type="hidden" name="role_id" id="editRoleId">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Role</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Role Name <span class="required">*</span></label>
                        <input type="text" name="name" id="editRoleName" class="form-control" required maxlength="50">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="editRoleDesc" class="form-control" rows="2" maxlength="255"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Role Modal -->
<div class="modal fade" id="deleteRoleModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_role">
                <input type="hidden" name="role_id" id="deleteRoleId">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete role <strong id="deleteRoleName"></strong>?</p>
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
function editRole(id, name, desc) {
    document.getElementById('editRoleId').value = id;
    document.getElementById('editRoleName').value = name;
    document.getElementById('editRoleDesc').value = desc;
    new bootstrap.Modal(document.getElementById('editRoleModal')).show();
}
function deleteRole(id, name) {
    document.getElementById('deleteRoleId').value = id;
    document.getElementById('deleteRoleName').textContent = name;
    new bootstrap.Modal(document.getElementById('deleteRoleModal')).show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
