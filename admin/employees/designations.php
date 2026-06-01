<?php
require_once __DIR__ . '/../../includes/config.php';
$pageTitle = 'Designation Management';
$breadcrumbs = [
    ['label' => 'Employees', 'url' => ADMIN_URL . 'employees/index.php'],
    ['label' => 'Designations']
];
RBAC::requirePermission('employees.view');

require_once __DIR__ . '/../includes/header.php';

$departments = db()->fetchAll("SELECT id, name FROM departments WHERE deleted_at IS NULL AND status = 'active' ORDER BY name");

if (isPost()) {
    $csrf = post('csrf_token');
    if (!verify_csrf($csrf)) {
        alert_error('Invalid security token.');
    } else {
        $action = post('action', '');

        if ($action === 'create' || $action === 'update') {
            $name = trim(post('name', ''));
            $departmentId = (int) post('department_id', 0);
            $description = trim(post('description', ''));
            $status = post('status', 'active');

            if (empty($name)) {
                alert_error('Designation name is required.');
            } elseif ($departmentId <= 0) {
                alert_error('Please select a department.');
            } else {
                $data = [
                    'name' => $name,
                    'department_id' => $departmentId,
                    'description' => $description ?: null,
                    'status' => $status,
                ];

                if ($action === 'create') {
                    $existing = db()->fetchColumn("SELECT COUNT(*) FROM designations WHERE name = ? AND department_id = ? AND deleted_at IS NULL", [$name, $departmentId]);
                    if ($existing > 0) {
                        alert_error('Designation with this name already exists in the selected department.');
                    } else {
                        $data['created_at'] = date('Y-m-d H:i:s');
                        db()->insert('designations', $data);
                        log_activity(Auth::id(), 'create', 'employees', "Created designation: {$name}");
                        alert_success('Designation created successfully.');
                    }
                } else {
                    $desgId = (int) post('id', 0);
                    if ($desgId > 0) {
                        $existing = db()->fetchColumn("SELECT COUNT(*) FROM designations WHERE name = ? AND department_id = ? AND id != ? AND deleted_at IS NULL", [$name, $departmentId, $desgId]);
                        if ($existing > 0) {
                            alert_error('Designation with this name already exists in the selected department.');
                        } else {
                            $data['updated_at'] = date('Y-m-d H:i:s');
                            db()->update('designations', $data, 'id = :id', ['id' => $desgId]);
                            log_activity(Auth::id(), 'update', 'employees', "Updated designation: {$name}");
                            alert_success('Designation updated successfully.');
                        }
                    }
                }
            }
        } elseif ($action === 'delete') {
            $desgId = (int) post('id', 0);
            if ($desgId > 0) {
                $empCount = db()->fetchColumn("SELECT COUNT(*) FROM employees WHERE designation_id = ? AND deleted_at IS NULL", [$desgId]);
                if ($empCount > 0) {
                    alert_error("Cannot delete designation: {$empCount} employee(s) are linked to it.");
                } else {
                    $desg = db()->fetchOne("SELECT name FROM designations WHERE id = ?", [$desgId]);
                    db()->softDelete('designations', $desgId);
                    log_activity(Auth::id(), 'delete', 'employees', "Deleted designation: {$desg['name']}");
                    alert_success('Designation deleted successfully.');
                }
            }
        }

        redirect(ADMIN_URL . 'employees/designations.php');
    }
}

$designations = db()->fetchAll(
    "SELECT dg.*, d.name as department_name
     FROM designations dg
     LEFT JOIN departments d ON dg.department_id = d.id
     WHERE dg.deleted_at IS NULL
     ORDER BY dg.name"
);
$statuses = ['active', 'inactive'];
?>
<div class="page-header">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h4>Designation Management</h4>
            <p>Manage employee designations linked to departments</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#desgModal">
                <i class="fas fa-plus"></i> Add Designation
            </button>
            <a href="<?= ADMIN_URL ?>employees/departments.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-building"></i> Departments
            </a>
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
            <table class="table" id="designationsTable">
                <thead>
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th style="width:120px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($designations)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">No designations found</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($designations as $i => $d): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><strong><?= escape($d['name']) ?></strong></td>
                        <td><?= escape($d['department_name'] ?? '-') ?></td>
                        <td><?= escape(truncate($d['description'] ?? '', 60)) ?: '-' ?></td>
                        <td><?= get_status_badge($d['status']) ?></td>
                        <td>
                            <div class="table-actions">
                                <button type="button" class="btn-action edit" title="Edit"
                                    onclick="editDesg(<?= $d['id'] ?>, '<?= escape(addslashes($d['name'])) ?>', <?= $d['department_id'] ?>, '<?= escape(addslashes($d['description'] ?? '')) ?>', '<?= $d['status'] ?>')">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" class="btn-action delete" title="Delete" onclick="deleteDesg(<?= $d['id'] ?>, '<?= escape(addslashes($d['name'])) ?>')">
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
<div class="modal fade" id="desgModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="action" id="desgAction" value="create">
                <input type="hidden" name="id" id="desgId" value="0">
                <div class="modal-header">
                    <h5 class="modal-title" id="desgModalTitle">Add Designation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group mb-3">
                        <label class="form-label">Department <span class="required">*</span></label>
                        <select name="department_id" id="desgDepartment" class="form-select" required>
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= escape($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Designation Name <span class="required">*</span></label>
                        <input type="text" name="name" id="desgName" class="form-control" required maxlength="200">
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="desgDesc" class="form-control" rows="3" maxlength="500"></textarea>
                    </div>
                    <div class="form-group mb-0">
                        <label class="form-label">Status</label>
                        <select name="status" id="desgStatus" class="form-select">
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
<div class="modal fade" id="deleteDesgModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="deleteDesgId">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete designation <strong id="deleteDesgName"></strong>?</p>
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
function editDesg(id, name, deptId, desc, status) {
    document.getElementById('desgAction').value = 'update';
    document.getElementById('desgId').value = id;
    document.getElementById('desgModalTitle').textContent = 'Edit Designation';
    document.getElementById('desgName').value = name;
    document.getElementById('desgDepartment').value = deptId;
    document.getElementById('desgDesc').value = desc;
    document.getElementById('desgStatus').value = status;
    new bootstrap.Modal(document.getElementById('desgModal')).show();
}

function deleteDesg(id, name) {
    document.getElementById('deleteDesgId').value = id;
    document.getElementById('deleteDesgName').textContent = name;
    new bootstrap.Modal(document.getElementById('deleteDesgModal')).show();
}

document.addEventListener('DOMContentLoaded', function() {
    var desgModal = document.getElementById('desgModal');
    desgModal.addEventListener('hidden.bs.modal', function() {
        document.getElementById('desgAction').value = 'create';
        document.getElementById('desgId').value = 0;
        document.getElementById('desgModalTitle').textContent = 'Add Designation';
        document.getElementById('desgName').value = '';
        document.getElementById('desgDepartment').value = '';
        document.getElementById('desgDesc').value = '';
        document.getElementById('desgStatus').value = 'active';
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
