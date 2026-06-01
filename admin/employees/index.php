<?php
require_once __DIR__ . '/../../includes/config.php';
$pageTitle = 'Employee Management';
$breadcrumbs = [['label' => 'Employees']];
RBAC::requirePermission('employees.view');

require_once __DIR__ . '/../includes/header.php';

$departments = db()->fetchAll("SELECT id, name FROM departments WHERE deleted_at IS NULL AND status = 'active' ORDER BY name");
$designations = db()->fetchAll("SELECT id, name, department_id FROM designations WHERE deleted_at IS NULL AND status = 'active' ORDER BY name");
$statuses = ['active', 'inactive', 'resigned', 'terminated'];

$page = max(1, (int) get('page', 1));
$perPage = RECORDS_PER_PAGE;
$search = trim(get('search', ''));
$deptFilter = get('department', '');
$desgFilter = get('designation', '');
$statusFilter = get('status', '');
$offset = ($page - 1) * $perPage;

$where = "WHERE e.deleted_at IS NULL";
$params = [];

if ($search !== '') {
    $where .= " AND (e.full_name LIKE :search OR e.employee_code LIKE :search2 OR e.mobile LIKE :search3 OR e.email LIKE :search4)";
    $params['search'] = "%{$search}%";
    $params['search2'] = "%{$search}%";
    $params['search3'] = "%{$search}%";
    $params['search4'] = "%{$search}%";
}
if ($deptFilter !== '') {
    $where .= " AND e.department_id = :dept";
    $params['dept'] = (int)$deptFilter;
}
if ($desgFilter !== '') {
    $where .= " AND e.designation_id = :desg";
    $params['desg'] = (int)$desgFilter;
}
if ($statusFilter !== '') {
    $where .= " AND e.status = :status";
    $params['status'] = $statusFilter;
}

$total = db()->fetchColumn("SELECT COUNT(*) FROM employees e {$where}", $params);

$employees = db()->fetchAll(
    "SELECT e.*, d.name as department_name, dg.name as designation_name
     FROM employees e
     LEFT JOIN departments d ON e.department_id = d.id
     LEFT JOIN designations dg ON e.designation_id = dg.id
     {$where}
     ORDER BY e.created_at DESC
     LIMIT {$perPage} OFFSET {$offset}",
    $params
);

$totalPages = ceil($total / $perPage);
$paginationUrl = ADMIN_URL . "employees/index.php?page={page}" .
    ($search ? "&search=" . urlencode($search) : "") .
    ($deptFilter ? "&department={$deptFilter}" : "") .
    ($desgFilter ? "&designation={$desgFilter}" : "") .
    ($statusFilter ? "&status={$statusFilter}" : "");
?>
<div class="page-header">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h4>Employee Management</h4>
            <p>Manage employees, departments, designations and attendance</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <?php if (RBAC::can('employees.create')): ?>
            <a href="<?= ADMIN_URL ?>employees/create.php" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Add Employee
            </a>
            <?php endif; ?>
            <a href="<?= ADMIN_URL ?>employees/attendance.php" class="btn btn-info btn-sm">
                <i class="fas fa-clipboard-check"></i> Attendance
            </a>
            <a href="<?= ADMIN_URL ?>employees/departments.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-building"></i> Departments
            </a>
            <a href="<?= ADMIN_URL ?>employees/designations.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-tag"></i> Designations
            </a>
        </div>
    </div>
</div>

<?= display_alert() ?>

<div class="card">
    <div class="card-body">
        <form method="GET" action="" class="filter-bar">
            <div class="search-box">
                <input type="text" name="search" class="form-control" placeholder="Search name, code, mobile or email..." value="<?= escape($search) ?>">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                <?php if ($search || $deptFilter || $desgFilter || $statusFilter): ?>
                <a href="<?= ADMIN_URL ?>employees/index.php" class="btn btn-outline-secondary"><i class="fas fa-times"></i></a>
                <?php endif; ?>
            </div>
            <select name="department" class="form-select" onchange="this.form.submit()">
                <option value="">All Departments</option>
                <?php foreach ($departments as $d): ?>
                <option value="<?= $d['id'] ?>" <?= $deptFilter == $d['id'] ? 'selected' : '' ?>><?= escape($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="designation" class="form-select" onchange="this.form.submit()">
                <option value="">All Designations</option>
                <?php foreach ($designations as $d): ?>
                <option value="<?= $d['id'] ?>" <?= $desgFilter == $d['id'] ? 'selected' : '' ?>><?= escape($d['name']) ?></option>
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
            <table class="table" id="employeesTable">
                <thead>
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Employee Code</th>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Designation</th>
                        <th>Mobile</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th style="width:130px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($employees)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-4 text-muted">No employees found</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($employees as $i => $e): ?>
                    <tr>
                        <td><?= $offset + $i + 1 ?></td>
                        <td><strong><?= escape($e['employee_code']) ?></strong></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div style="width:32px;height:32px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:12px;flex-shrink:0;overflow:hidden;">
                                    <?php if ($e['photo']): ?>
                                    <img src="<?= UPLOAD_URL ?>employees/<?= escape($e['photo']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                                    <?php else: ?>
                                    <?= strtoupper(substr($e['full_name'], 0, 1)) ?>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div style="font-weight:500;font-size:13px;"><?= escape($e['full_name']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td><?= escape($e['department_name'] ?? '-') ?></td>
                        <td><?= escape($e['designation_name'] ?? '-') ?></td>
                        <td><?= escape($e['mobile'] ?: '-') ?></td>
                        <td><?= escape($e['email'] ?: '-') ?></td>
                        <td><?= get_status_badge($e['status']) ?></td>
                        <td>
                            <div class="table-actions">
                                <a href="<?= ADMIN_URL ?>employees/view.php?id=<?= $e['id'] ?>" class="btn-action view" title="View"><i class="fas fa-eye"></i></a>
                                <?php if (RBAC::can('employees.edit')): ?>
                                <a href="<?= ADMIN_URL ?>employees/edit.php?id=<?= $e['id'] ?>" class="btn-action edit" title="Edit"><i class="fas fa-edit"></i></a>
                                <?php endif; ?>
                                <?php if (RBAC::can('employees.delete')): ?>
                                <button type="button" class="btn-action delete" title="Delete" onclick="confirmDelete(<?= $e['id'] ?>, '<?= escape(addslashes($e['full_name'])) ?>')"><i class="fas fa-trash"></i></button>
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
            <form method="POST" action="<?= ADMIN_URL ?>employees/delete.php">
                <?= csrf_field() ?>
                <input type="hidden" name="employee_id" id="deleteEmployeeId">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete employee <strong id="deleteEmployeeName"></strong>?</p>
                    <p class="text-muted small mb-0">This action will soft-delete the employee record.</p>
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
    document.getElementById('deleteEmployeeId').value = id;
    document.getElementById('deleteEmployeeName').textContent = name;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
