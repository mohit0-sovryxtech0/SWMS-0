<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/WorkflowEngine.php';
$pageTitle = 'Meter Reading Routes';
$breadcrumbs = [
    ['label' => 'Home', 'url' => ADMIN_URL . 'dashboard/index.php'],
    ['label' => 'Meter Reading', 'url' => ADMIN_URL . 'meter-reading/index.php'],
    ['label' => 'Routes'],
];
RBAC::requirePermission('readings.view');
$apiUrl = API_URL;

$action = post('action') ?: get('action');
$error = '';
$success = '';

try {
    if (isPost() && $action === 'save') {
        if (!verify_csrf(post('csrf_token'))) throw new Exception('Security validation failed');
        $id = intval(post('id'));
        $data = [
            'route_code' => post('route_code'),
            'route_name' => post('route_name'),
            'ward_no' => post('ward_no'),
            'area_description' => post('area_description'),
            'estimated_consumers' => post('estimated_consumers'),
            'assigned_reader_id' => post('assigned_reader_id'),
            'status' => post('status', 'active'),
        ];
        if ($id) {
            WorkflowEngine::updateRoute($id, $data);
            $success = 'Route updated successfully';
        } else {
            $id = WorkflowEngine::createRoute($data);
            $success = 'Route created successfully';
        }
        if (!isAjax()) redirect(ADMIN_URL . 'meter-reading/routes.php');
    }

    if (isPost() && $action === 'assign_consumers') {
        if (!verify_csrf(post('csrf_token'))) throw new Exception('Security validation failed');
        $routeId = intval(post('route_id'));
        $consumerIds = post('consumer_ids', []);
        WorkflowEngine::assignConsumersToRoute($routeId, $consumerIds);
        $success = 'Consumers assigned to route successfully';
        if (!isAjax()) redirect(ADMIN_URL . 'meter-reading/routes.php?id=' . $routeId);
    }

    if ($action === 'delete' && get('id')) {
        if (!verify_csrf(get('csrf_token'))) throw new Exception('Security validation failed');
        WorkflowEngine::deleteRoute(intval(get('id')));
        $success = 'Route deleted';
        redirect(ADMIN_URL . 'meter-reading/routes.php');
    }
} catch (Exception $e) {
    if (isAjax()) { json_error($e->getMessage()); }
    $error = $e->getMessage();
}

$editId = intval(get('id'));
$editRoute = $editId ? WorkflowEngine::getRoute($editId) : null;
$routes = WorkflowEngine::getRoutes();
$readers = db()->fetchAll("SELECT id, name FROM users WHERE status = 'active' AND deleted_at IS NULL ORDER BY name");
$consumers = db()->fetchAll("SELECT id, consumer_no, full_name, ward_no, tole FROM consumers WHERE status = 'active' AND deleted_at IS NULL ORDER BY full_name");

$routeConsumers = $editId ? WorkflowEngine::getRouteConsumers($editId) : [];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h4 class="mb-1"><i class="fas fa-route me-2 text-primary"></i>Meter Reading Routes</h4>
        <p class="text-muted mb-0">Manage routes for meter reading assignments</p>
    </div>
    <div>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#routeModal">
            <i class="fas fa-plus me-1"></i>New Route
        </button>
        <a href="index.php" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-tachometer-alt me-1"></i>POS Reading
        </a>
    </div>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= escape($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover datatable">
                        <thead>
                            <tr>
                                <th>Route Code</th>
                                <th>Route Name</th>
                                <th>Ward</th>
                                <th>Assigned Reader</th>
                                <th>Consumers</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($routes as $r): ?>
                            <tr>
                                <td><strong><?= escape($r['route_code']) ?></strong></td>
                                <td><?= escape($r['route_name']) ?></td>
                                <td><?= $r['ward_no'] ? 'Ward ' . $r['ward_no'] : 'All' ?></td>
                                <td><?= escape($r['reader_name'] ?? 'Unassigned') ?></td>
                                <td><?= intval($r['consumer_count']) ?></td>
                                <td><span class="badge bg-<?= $r['status'] === 'active' ? 'success' : 'secondary' ?>"><?= $r['status'] ?></span></td>
                                <td class="text-end">
                                    <a href="?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-primary" title="Manage"><i class="fas fa-cog"></i></a>
                                    <a href="routes.php?action=delete&id=<?= $r['id'] ?>&csrf_token=<?= csrf_token() ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this route?')"><i class="fas fa-trash"></i></a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($routes)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">No routes defined. Create one to get started.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <?php if ($editId): ?>
    <div class="col-12 mt-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-users me-2 text-primary"></i>Route Consumers: <?= escape($editRoute['route_name'] ?? '') ?></h5>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#assignModal">
                    <i class="fas fa-plus me-1"></i>Assign Consumers
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead><tr><th>#</th><th>Consumer No</th><th>Name</th><th>Mobile</th><th>Ward</th><th>Tole</th><th>Meter No</th></tr></thead>
                        <tbody>
                            <?php foreach ($routeConsumers as $rc): ?>
                            <tr>
                                <td><?= $rc['sequence_no'] ?></td>
                                <td><?= escape($rc['consumer_no']) ?></td>
                                <td><?= escape($rc['full_name']) ?></td>
                                <td><?= escape($rc['mobile']) ?></td>
                                <td><?= $rc['ward_no'] ?></td>
                                <td><?= escape($rc['tole']) ?></td>
                                <td><?= escape($rc['meter_no'] ?? 'No meter') ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($routeConsumers)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-3">No consumers assigned to this route.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Route Modal -->
<div class="modal fade" id="routeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?= $editId ?>">
                <div class="modal-header">
                    <h5><i class="fas fa-route me-2 text-primary"></i><?= $editId ? 'Edit' : 'New' ?> Route</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Route Code <span class="text-danger">*</span></label>
                            <input type="text" name="route_code" class="form-control" required value="<?= escape($editRoute['route_code'] ?? 'RTE-' . strtoupper(bin2hex(random_bytes(2)))) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Route Name <span class="text-danger">*</span></label>
                            <input type="text" name="route_name" class="form-control" required value="<?= escape($editRoute['route_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Ward No</label>
                            <input type="number" name="ward_no" class="form-control" min="0" max="99" value="<?= intval($editRoute['ward_no'] ?? 0) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Est. Consumers</label>
                            <input type="number" name="estimated_consumers" class="form-control" min="0" value="<?= intval($editRoute['estimated_consumers'] ?? 0) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="active" <?= ($editRoute['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= ($editRoute['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Assigned Reader</label>
                            <select name="assigned_reader_id" class="form-select">
                                <option value="">-- Unassigned --</option>
                                <?php foreach ($readers as $u): ?>
                                <option value="<?= $u['id'] ?>" <?= ($editRoute['assigned_reader_id'] ?? '') == $u['id'] ? 'selected' : '' ?>><?= escape($u['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Area Description</label>
                            <textarea name="area_description" class="form-control" rows="2"><?= escape($editRoute['area_description'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Route</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Assign Consumers Modal -->
<div class="modal fade" id="assignModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="assign_consumers">
                <input type="hidden" name="route_id" value="<?= $editId ?>">
                <div class="modal-header">
                    <h5><i class="fas fa-user-plus me-2 text-primary"></i>Assign Consumers to Route</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <input type="text" class="form-control" id="consumerFilter" placeholder="Filter consumers by name or number...">
                    </div>
                    <div class="table-responsive" style="max-height:400px;overflow-y:auto;">
                        <table class="table table-sm table-hover">
                            <thead><tr><th style="width:40px"><input type="checkbox" id="selectAll"></th><th>Consumer No</th><th>Name</th><th>Mobile</th><th>Ward</th><th>Tole</th></tr></thead>
                            <tbody id="consumerList">
                                <?php foreach ($consumers as $c):
                                    $assigned = in_array($c['id'], array_column($routeConsumers, 'consumer_id'));
                                ?>
                                <tr class="<?= $assigned ? 'table-success' : '' ?>">
                                    <td><input type="checkbox" name="consumer_ids[]" value="<?= $c['id'] ?>" class="consumer-check" <?= $assigned ? 'checked' : '' ?>></td>
                                    <td><?= escape($c['consumer_no']) ?></td>
                                    <td><?= escape($c['full_name']) ?></td>
                                    <td><?= escape($c['mobile']) ?></td>
                                    <td><?= $c['ward_no'] ?></td>
                                    <td><?= escape($c['tole']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check me-1"></i>Assign Selected</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
ob_start(); ?>
<script>
$(function() {
    $('#consumerFilter').on('keyup', function() {
        var q = $(this).val().toLowerCase();
        $('#consumerList tr').each(function() {
            var text = $(this).text().toLowerCase();
            $(this).toggle(text.indexOf(q) > -1);
        });
    });
    $('#selectAll').change(function() {
        $('#consumerList .consumer-check').prop('checked', $(this).is(':checked'));
    });
    <?php if ($editId && !$error): ?>
    var routeModal = new bootstrap.Modal(document.getElementById('routeModal'));
    routeModal.show();
    <?php endif; ?>
});
</script>
<?php
$extraJs = ob_get_clean();
require_once __DIR__ . '/../includes/footer.php';
?>
