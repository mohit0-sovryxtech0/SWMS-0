<?php
require_once __DIR__ . '/../../includes/config.php';
$pageTitle = 'User Management';
$breadcrumbs = [['label' => 'User Management']];
RBAC::requirePermission('users.view');
require_once __DIR__ . '/../includes/header.php';

$roles = db()->fetchAll("SELECT id, name FROM roles WHERE deleted_at IS NULL ORDER BY name");
$statuses = ['active', 'inactive', 'suspended'];
$page = max(1, (int) get('page', 1));
$perPage = RECORDS_PER_PAGE;
$search = trim(get('search', ''));
$roleFilter = get('role', '');
$statusFilter = get('status', '');
$offset = ($page - 1) * $perPage;

$where = "WHERE u.deleted_at IS NULL";
$params = [];

if ($search !== '') {
    $where .= " AND (u.name LIKE :search OR u.email LIKE :search2 OR u.username LIKE :search3 OR u.phone LIKE :search4)";
    $params['search'] = "%{$search}%";
    $params['search2'] = "%{$search}%";
    $params['search3'] = "%{$search}%";
    $params['search4'] = "%{$search}%";
}
if ($roleFilter !== '') {
    $where .= " AND u.role_id = :role";
    $params['role'] = (int)$roleFilter;
}
if ($statusFilter !== '') {
    $where .= " AND u.status = :status";
    $params['status'] = $statusFilter;
}

$total = db()->fetchColumn(
    "SELECT COUNT(*) FROM users u {$where}", $params
);

$users = db()->fetchAll(
    "SELECT u.*, r.name as role_name, r.slug as role_slug
     FROM users u
     JOIN roles r ON u.role_id = r.id
     {$where}
     ORDER BY u.created_at DESC
     LIMIT {$perPage} OFFSET {$offset}",
    $params
);

$totalPages = ceil($total / $perPage);
$paginationUrl = ADMIN_URL . "users/index.php?page={page}" .
    ($search ? "&search=" . urlencode($search) : "") .
    ($roleFilter ? "&role={$roleFilter}" : "") .
    ($statusFilter ? "&status={$statusFilter}" : "");
?>
<div class="page-header">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h4>User Management</h4>
            <p>Manage system users, roles, and permissions</p>
        </div>
        <div class="d-flex gap-2">
            <?php if (RBAC::can('users.create')): ?>
            <a href="<?= ADMIN_URL ?>users/create.php" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Add User
            </a>
            <?php endif; ?>
            <?php if (RBAC::can('roles.manage')): ?>
            <a href="<?= ADMIN_URL ?>users/roles.php" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-shield-alt"></i> Roles
            </a>
            <a href="<?= ADMIN_URL ?>users/permissions.php" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-key"></i> Permissions
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?= display_alert() ?>

<div class="card">
    <div class="card-body">
        <form method="GET" action="" class="filter-bar">
            <div class="search-box">
                <input type="text" name="search" class="form-control" placeholder="Search users..." value="<?= escape($search) ?>">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                <?php if ($search || $roleFilter || $statusFilter): ?>
                <a href="<?= ADMIN_URL ?>users/index.php" class="btn btn-outline-secondary"><i class="fas fa-times"></i></a>
                <?php endif; ?>
            </div>
            <select name="role" class="form-select" onchange="this.form.submit()">
                <option value="">All Roles</option>
                <?php foreach ($roles as $role): ?>
                <option value="<?= $role['id'] ?>" <?= $roleFilter == $role['id'] ? 'selected' : '' ?>><?= escape($role['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="status" class="form-select" onchange="this.form.submit()">
                <option value="">All Status</option>
                <?php foreach ($statuses as $st): ?>
                <option value="<?= $st ?>" <?= $statusFilter === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
                <?php endforeach; ?>
            </select>
        </form>

        <div class="table-container">
            <table class="table" id="usersTable">
                <thead>
                    <tr>
                        <th style="width:40px">#</th>
                        <th>User</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th style="width:120px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">No users found</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($users as $i => $user): ?>
                    <tr>
                        <td><?= $offset + $i + 1 ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="user-avatar" style="width:32px;height:32px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:12px;flex-shrink:0;">
                                    <?= strtoupper(substr($user['name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <div style="font-weight:500;font-size:13px;"><?= escape($user['name']) ?></div>
                                    <div style="font-size:11px;color:var(--text-muted);"><?= escape($user['email']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td><?= escape($user['username']) ?></td>
                        <td><span class="badge bg-primary"><?= escape($user['role_name']) ?></span></td>
                        <td><?= escape($user['phone'] ?: '-') ?></td>
                        <td><?= get_status_badge($user['status']) ?></td>
                        <td style="font-size:12px;color:var(--text-muted);"><?= $user['last_login'] ? format_datetime($user['last_login']) : 'Never' ?></td>
                        <td>
                            <div class="table-actions">
                                <?php if (RBAC::can('users.edit')): ?>
                                <a href="<?= ADMIN_URL ?>users/edit.php?id=<?= $user['id'] ?>" class="btn-action edit" title="Edit"><i class="fas fa-edit"></i></a>
                                <?php endif; ?>
                                <?php if (RBAC::can('users.delete') && Auth::id() != $user['id']): ?>
                                <button type="button" class="btn-action delete" title="Delete" onclick="confirmDelete(<?= $user['id'] ?>, '<?= escape(addslashes($user['name'])) ?>')"><i class="fas fa-trash"></i></button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="d-flex justify-content-between align-items-center mt-3">
            <small class="text-muted">Showing <?= min($total, $offset + 1) ?> to <?= min($total, $offset + $perPage) ?> of <?= $total ?> entries</small>
            <nav>
                <ul class="pagination mb-0">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= str_replace('{page}', $page - 1, $paginationUrl) ?>"><i class="fas fa-chevron-left"></i></a>
                    </li>
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="<?= str_replace('{page}', $i, $paginationUrl) ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= str_replace('{page}', $page + 1, $paginationUrl) ?>"><i class="fas fa-chevron-right"></i></a>
                    </li>
                </ul>
            </nav>
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
                    <p class="text-muted small mb-0">This action will soft-delete the user. They can be restored by an administrator.</p>
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
