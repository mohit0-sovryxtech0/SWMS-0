<?php
require_once __DIR__ . '/../../includes/config.php';

Auth::requireAuth();
RBAC::requirePermission('tariffs.manage');

$pageTitle = 'Tariff Management';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => ADMIN_URL . 'dashboard/index.php'],
    ['label' => 'Billing', 'url' => ADMIN_URL . 'billing/index.php'],
    ['label' => 'Tariffs']
];

$error = '';
$success = '';

$categories = db()->fetchAll("SELECT id, name FROM consumer_categories WHERE deleted_at IS NULL ORDER BY name");

// Handle POST actions
if (isPost()) {
    try {
        if (!verify_csrf(post('csrf_token'))) {
            throw new Exception('Security validation failed');
        }

        $action = post('tariff_action');

        if ($action === 'save') {
            $id = intval(post('id'));
            $data = [
                'name' => post('name'),
                'category_id' => post('category_id') ?: null,
                'connection_type' => post('connection_type'),
                'min_consumption' => floatval(post('min_consumption') ?: 0),
                'max_consumption' => floatval(post('max_consumption') ?: 999999),
                'base_fee' => floatval(post('base_fee') ?: 0),
                'rate_per_unit' => floatval(post('rate_per_unit') ?: 0),
                'min_charge' => floatval(post('min_charge') ?: 0),
                'meter_rent' => floatval(post('meter_rent') ?: 0),
                'sewerage_fee' => floatval(post('sewerage_fee') ?: 0),
                'vat_percent' => floatval(post('vat_percent') ?: 0),
                'penalty_percent' => floatval(post('penalty_percent') ?: 5.00),
                'penalty_days' => intval(post('penalty_days') ?: 15),
                'effective_from' => post('effective_from'),
                'effective_to' => post('effective_to'),
                'is_current' => post('is_current') ? 1 : 0,
                'status' => post('status')
            ];

            $validator = validator($_POST, [
                'name' => 'required|min:3',
                'connection_type' => 'required|in:household,commercial,institutional,all',
                'base_fee' => 'required|numeric',
                'rate_per_unit' => 'required|numeric',
                'effective_from' => 'required|date'
            ]);

            if ($validator->fails()) {
                throw new Exception($validator->firstError());
            }

            if ($id > 0) {
                db()->update('tariffs', $data, 'id = :id', ['id' => $id]);
                log_activity(Auth::id(), 'edit_tariff', 'billing', "Updated tariff: {$data['name']}", ['tariff_id' => $id]);
                set_flash('success', 'Tariff updated successfully');
            } else {
                $data['created_at'] = date('Y-m-d H:i:s');
                db()->insert('tariffs', $data);
                log_activity(Auth::id(), 'create_tariff', 'billing', "Created tariff: {$data['name']}");
                set_flash('success', 'Tariff created successfully');
            }

            if ($data['is_current']) {
                db()->update('tariffs', ['is_current' => 0], 'id != :id AND connection_type = :ctype', [
                    'id' => $id ?: 9999999999,
                    'ctype' => $data['connection_type']
                ]);
            }

            redirect(ADMIN_URL . 'billing/tariffs.php');
        }

        if ($action === 'set_current') {
            $id = intval(post('id'));
            $tariff = db()->fetchOne("SELECT * FROM tariffs WHERE id = ?", [$id]);
            if (!$tariff) throw new Exception('Tariff not found');

            db()->update('tariffs', ['is_current' => 0], 'connection_type = :ctype', ['ctype' => $tariff['connection_type']]);
            db()->update('tariffs', ['is_current' => 1], 'id = :id', ['id' => $id]);

            log_activity(Auth::id(), 'set_current_tariff', 'billing', "Set tariff '{$tariff['name']}' as current");
            set_flash('success', 'Tariff set as current');
            redirect(ADMIN_URL . 'billing/tariffs.php');
        }

        if ($action === 'delete') {
            $id = intval(post('id'));
            db()->softDelete('tariffs', $id);
            log_activity(Auth::id(), 'delete_tariff', 'billing', "Deleted tariff #{$id}");
            set_flash('success', 'Tariff deleted successfully');
            redirect(ADMIN_URL . 'billing/tariffs.php');
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$tariffs = db()->fetchAll(
    "SELECT t.*, cc.name as category_name
     FROM tariffs t
     LEFT JOIN consumer_categories cc ON t.category_id = cc.id
     WHERE t.deleted_at IS NULL
     ORDER BY t.is_current DESC, t.effective_from DESC"
);

$editTariff = null;
$editId = intval(get('edit'));
if ($editId) {
    $editTariff = db()->fetchOne("SELECT * FROM tariffs WHERE id = ? AND deleted_at IS NULL", [$editId]);
}

$connectionTypes = ['household' => 'Household', 'commercial' => 'Commercial', 'institutional' => 'Institutional', 'all' => 'All Types'];

include_once ADMIN_PATH . 'includes/header.php';
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <h4 class="page-title mb-0">
            <i class="fas fa-tags me-2 text-primary"></i>Tariff Management
        </h4>
        <button type="button" class="btn btn-primary" onclick="showTariffModal(0)">
            <i class="fas fa-plus-circle me-1"></i>Add Tariff
        </button>
    </div>
</div>

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <i class="fas fa-exclamation-circle me-2"></i><?= escape($error) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-bordered" id="tariffsTable">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Connection Type</th>
                        <th>Base Fee</th>
                        <th>Rate/Unit</th>
                        <th>Min Charge</th>
                        <th>Meter Rent</th>
                        <th>VAT %</th>
                        <th>Effective</th>
                        <th>Status</th>
                        <th style="width:120px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tariffs as $t): ?>
                    <tr class="<?= $t['is_current'] ? 'table-success' : '' ?>">
                        <td class="fw-semibold">
                            <?= escape($t['name']) ?>
                            <?php if ($t['is_current']): ?>
                                <span class="badge bg-success ms-1">Current</span>
                            <?php endif; ?>
                        </td>
                        <td><?= escape($t['category_name'] ?? 'All') ?></td>
                        <td><?= escape(ucfirst($t['connection_type'])) ?></td>
                        <td><?= format_currency($t['base_fee']) ?></td>
                        <td><?= format_currency($t['rate_per_unit']) ?></td>
                        <td><?= format_currency($t['min_charge']) ?></td>
                        <td><?= format_currency($t['meter_rent']) ?></td>
                        <td><?= number_format($t['vat_percent'], 2) ?>%</td>
                        <td>
                            <small>
                                <?= format_date($t['effective_from']) ?>
                                <?= $t['effective_to'] ? ' - ' . format_date($t['effective_to']) : '' ?>
                            </small>
                        </td>
                        <td><?= get_status_badge($t['status']) ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-warning" onclick="showTariffModal(<?= $t['id'] ?>)" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if (!$t['is_current']): ?>
                                <form method="post" class="d-inline" onsubmit="return confirm('Set this tariff as current? Old tariffs will be deactivated.');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="tariff_action" value="set_current">
                                    <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                    <button type="submit" class="btn btn-success" title="Set as Current">
                                        <i class="fas fa-check-circle"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                                <form method="post" class="d-inline" onsubmit="return confirm('Delete this tariff?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="tariff_action" value="delete">
                                    <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                    <button type="submit" class="btn btn-danger" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($tariffs)): ?>
                    <tr><td colspan="11" class="text-center text-muted py-4">No tariffs found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Tariff Form Modal -->
<div class="modal fade" id="tariffModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post" class="needs-validation" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="tariff_action" value="save">
                <input type="hidden" name="id" id="tariffId" value="0">
                <div class="modal-header">
                    <h5 class="modal-title" id="tariffModalTitle"><i class="fas fa-tag me-2"></i>Add Tariff</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Tariff Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="t_name" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Category</label>
                            <select name="category_id" id="t_category_id" class="form-select">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= escape($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Connection Type <span class="text-danger">*</span></label>
                            <select name="connection_type" id="t_connection_type" class="form-select" required>
                                <?php foreach ($connectionTypes as $val => $label): ?>
                                <option value="<?= $val ?>"><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Min Consumption</label>
                            <input type="number" step="0.01" name="min_consumption" id="t_min_consumption" class="form-control" value="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Max Consumption</label>
                            <input type="number" step="0.01" name="max_consumption" id="t_max_consumption" class="form-control" value="999999">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Base Fee <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="base_fee" id="t_base_fee" class="form-control" required value="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Rate Per Unit <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="rate_per_unit" id="t_rate_per_unit" class="form-control" required value="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Min Charge</label>
                            <input type="number" step="0.01" name="min_charge" id="t_min_charge" class="form-control" value="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Meter Rent</label>
                            <input type="number" step="0.01" name="meter_rent" id="t_meter_rent" class="form-control" value="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Sewerage Fee</label>
                            <input type="number" step="0.01" name="sewerage_fee" id="t_sewerage_fee" class="form-control" value="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">VAT %</label>
                            <input type="number" step="0.01" name="vat_percent" id="t_vat_percent" class="form-control" value="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Penalty %</label>
                            <input type="number" step="0.01" name="penalty_percent" id="t_penalty_percent" class="form-control" value="5.00">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Penalty Days</label>
                            <input type="number" name="penalty_days" id="t_penalty_days" class="form-control" value="15">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Effective From <span class="text-danger">*</span></label>
                            <input type="date" name="effective_from" id="t_effective_from" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Effective To</label>
                            <input type="date" name="effective_to" id="t_effective_to" class="form-control">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Current</label>
                            <div class="form-check form-switch mt-2">
                                <input type="checkbox" name="is_current" id="t_is_current" class="form-check-input" value="1">
                                <label class="form-check-label" for="t_is_current">Active</label>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Status</label>
                            <select name="status" id="t_status" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Tariff</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
var tariffData = <?= json_encode($editTariff ?: new stdClass()) ?>;

function showTariffModal(id) {
    if (id > 0) {
        document.getElementById('tariffModalTitle').innerHTML = '<i class="fas fa-edit me-2"></i>Edit Tariff';
        var t = tariffData;
        if (t && t.id == id) {
            document.getElementById('tariffId').value = t.id;
            document.getElementById('t_name').value = t.name;
            document.getElementById('t_category_id').value = t.category_id || '';
            document.getElementById('t_connection_type').value = t.connection_type;
            document.getElementById('t_min_consumption').value = t.min_consumption;
            document.getElementById('t_max_consumption').value = t.max_consumption;
            document.getElementById('t_base_fee').value = t.base_fee;
            document.getElementById('t_rate_per_unit').value = t.rate_per_unit;
            document.getElementById('t_min_charge').value = t.min_charge;
            document.getElementById('t_meter_rent').value = t.meter_rent;
            document.getElementById('t_sewerage_fee').value = t.sewerage_fee;
            document.getElementById('t_vat_percent').value = t.vat_percent;
            document.getElementById('t_penalty_percent').value = t.penalty_percent;
            document.getElementById('t_penalty_days').value = t.penalty_days;
            document.getElementById('t_effective_from').value = t.effective_from;
            document.getElementById('t_effective_to').value = t.effective_to;
            document.getElementById('t_is_current').checked = t.is_current == 1;
            document.getElementById('t_status').value = t.status;
        }
    } else {
        document.getElementById('tariffModalTitle').innerHTML = '<i class="fas fa-plus-circle me-2"></i>Add Tariff';
        document.getElementById('tariffId').value = 0;
        document.getElementById('t_name').value = '';
        document.getElementById('t_category_id').value = '';
        document.getElementById('t_connection_type').value = 'all';
        document.getElementById('t_min_consumption').value = 0;
        document.getElementById('t_max_consumption').value = 999999;
        document.getElementById('t_base_fee').value = 0;
        document.getElementById('t_rate_per_unit').value = 0;
        document.getElementById('t_min_charge').value = 0;
        document.getElementById('t_meter_rent').value = 0;
        document.getElementById('t_sewerage_fee').value = 0;
        document.getElementById('t_vat_percent').value = 0;
        document.getElementById('t_penalty_percent').value = 5;
        document.getElementById('t_penalty_days').value = 15;
        document.getElementById('t_effective_from').value = '<?= date('Y-m-d') ?>';
        document.getElementById('t_effective_to').value = '';
        document.getElementById('t_is_current').checked = true;
        document.getElementById('t_status').value = 'active';
    }
    new bootstrap.Modal(document.getElementById('tariffModal')).show();
}

$(document).ready(function() {
    $('#tariffsTable').DataTable({
        pageLength: 25,
        order: [[0, 'asc']]
    });
});
</script>

<?php include_once ADMIN_PATH . 'includes/footer.php'; ?>
