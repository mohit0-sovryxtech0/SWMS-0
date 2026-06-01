<?php
require_once __DIR__ . '/../../includes/config.php';
$pageTitle = 'Department Management';
$breadcrumbs = [
    ['label' => 'Employees', 'url' => ADMIN_URL . 'employees/index.php'],
    ['label' => 'Departments']
];
RBAC::requirePermission('employees.view');

require_once __DIR__ . '/../includes/header.php';

if (isPost()) {
    $csrf = post('csrf_token');
    if (!verify_csrf($csrf)) {
        alert_error('Invalid security token.');
    } else {
        $action = post('action', '');

        if ($action === 'create' || $action === 'update') {
            $name = trim(post('name', ''));
            $code = trim(post('code', ''));
            $description = trim(post('description', ''));
            $status = post('status', 'active');

            if (empty($name)) {
                alert_error('Department name is required.');
            } else {
                $data = [
                    'name' => $name,
                    'code' => $code ?: null,
                    'description' => $description ?: null,
                    'status' => $status,
                ];

                if ($action === 'create') {
                    $existing = db()->fetchColumn("SELECT COUNT(*) FROM departments WHERE name = ? AND deleted_at IS NULL", [$name]);
                    if ($existing > 0) {
                        alert_error('Department with this name already exists.');
                    } else {
                        $data['created_at'] = date('Y-m-d H:i:s');
                        db()->insert('departments', $data);
                        log_activity(Auth::id(), 'create', 'employees', "Created department: {$name}");
                        alert_success('Department created successfully.');
                    }
                } else {
                    $deptId = (int) post('id', 0);
                    if ($deptId > 0) {
                        $existing = db()->fetchColumn("SELECT COUNT(*) FROM departments WHERE name = ? AND id != ? AND deleted_at IS NULL", [$name, $deptId]);
                        if ($existing > 0) {
                            alert_error('Department with this name already exists.');
                        } else {
                            $data['updated_at'] = date('Y-m-d H:i:s');
                            db()->update('departments', $data, 'id = :id', ['id' => $deptId]);
                            log_activity(Auth::id(), 'update', 'employees', "Updated department: {$name}");
                            alert_success('Department updated successfully.');
                        }
                    }
                }
            }
        } elseif ($action === 'delete') {
            $deptId = (int) post('id', 0);
            if ($deptId > 0) {
                $empCount = db()->fetchColumn("SELECT COUNT(*) FROM employees WHERE department_id = ? AND deleted_at IS NULL", [$deptId]);
                if ($empCount > 0) {
                    alert_error("Cannot delete department: {$empCount} employee(s) are linked to it.");
                } else {
                    $dept = db()->fetchOne("SELECT name FROM departments WHERE id = ?", [$deptId]);
                    db()->softDelete('departments', $deptId);
                    log_activity(Auth::id(), 'delete', 'employees', "Deleted department: {$dept['name']}");
                    alert_success('Department deleted successfully.');
                }
            }
        }

        redirect(ADMIN_URL . 'employees/departments.php');
    }
}

$departments = db()->fetchAll("SELECT * FROM departments WHERE deleted_at IS NULL ORDER BY name");
$statuses = ['active', 'inactive'];
?>
<div class="page-header">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h4>Department Management</h4>
            <p>Manage employee departments</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#deptModal">
                <i class="fas fa-plus"></i> Add Department
            </button>
            <a href="<?= ADMIN_URL ?>employees/index.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>
</div>

<?= display_alert() ?>

<div class="card">
    <div class="card-body">
        <div class="table-container">
            <table class="table" id="departmentsTable">
                <thead>
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Name</th>
                        <th>Code</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th style="width:120px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($departments)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">No departments found</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($departments as $i => $d): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><strong><?= escape($d['name']) ?></strong></td>
                        <td><?= escape($d['code'] ?: '-') ?></td>
                        <td><?= escape(truncate($d['description'] ?? '', 60)) ?: '-' ?></td>
                        <td><?= get_status_badge($d['status']) ?></td>
                        <td>
                            <div class="table-actions">
                                <button type="button" class="btn-action edit" title="Edit"
                                    onclick="editDept(<?= $d['id'] ?>, '<?= escape(addslashes($d['name'])) ?>', '<?= escape(addslashes($d['code'] ?? '')) ?>', '<?= escape(addslashes($d['description'] ?? '')) ?>', '<?= $d['status'] ?>')">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" class="btn-action delete" title="Delete" onclick="deleteDept(<?= $d['id'] ?>, '<?= escape(addslashes($d['name'])) ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create/Edit Modal -->
<div class="modal fade" id="deptModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="action" id="deptAction" value="create">
                <input type="hidden" name="id" id="deptId" value="0">
                <div class="modal-header">
                    <h5 class="modal-title" id="deptModalTitle">Add Department</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group mb-3">
                        <label class="form-label">Department Name <span class="required">*</span></label>
                        <input type="text" name="name" id="deptName" class="form-control" required maxlength="200">
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Code</label>
                        <input type="text" name="code" id="deptCode" class="form-control" maxlength="50">
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="deptDesc" class="form-control" rows="3" maxlength="500"></textarea>
                    </div>
                    <div class="form-group mb-0">
                        <label class="form-label">Status</label>
                        <select name="status" id="deptStatus" class="form-select">
                            <?php foreach ($statuses as $st): ?>
                            <option value="<?= $st ?>"><?= ucfirst($st) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteDeptModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="deleteDeptId">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete department <strong id="deleteDeptName"></strong>?</p>
                    <p class="text-muted small mb-0">This cannot be undone if employees are linked to it.</p>
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
function editDept(id, name, code, desc, status) {
    document.getElementById('deptAction').value = 'update';
    document.getElementById('deptId').value = id;
    document.getElementById('deptModalTitle').textContent = 'Edit Department';
    document.getElementById('deptName').value = name;
    document.getElementById('deptCode').value = code;
    document.getElementById('deptDesc').value = desc;
    document.getElementById('deptStatus').value = status;
    new bootstrap.Modal(document.getElementById('deptModal')).show();
}

function deleteDept(id, name) {
    document.getElementById('deleteDeptId').value = id;
    document.getElementById('deleteDeptName').textContent = name;
    new bootstrap.Modal(document.getElementById('deleteDeptModal')).show();
}

document.addEventListener('DOMContentLoaded', function() {
    var deptModal = document.getElementById('deptModal');
    deptModal.addEventListener('hidden.bs.modal', function() {
        document.getElementById('deptAction').value = 'create';
        document.getElementById('deptId').value = 0;
        document.getElementById('deptModalTitle').textContent = 'Add Department';
        document.getElementById('deptName').value = '';
        document.getElementById('deptCode').value = '';
        document.getElementById('deptDesc').value = '';
        document.getElementById('deptStatus').value = 'active';
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
