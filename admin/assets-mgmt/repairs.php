<?php
require_once __DIR__ . '/../../includes/config.php';
$pageTitle = 'Asset Repairs';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => ADMIN_URL . 'dashboard/index.php'],
    ['label' => 'Assets', 'url' => ADMIN_URL . 'assets-mgmt/index.php'],
    ['label' => 'Repairs']
];
RBAC::requirePermission('assets.view');

require_once __DIR__ . '/../includes/header.php';

$assetFilter = get('asset_id', '');
$assets = db()->fetchAll("SELECT id, asset_code, name, asset_type FROM assets WHERE deleted_at IS NULL ORDER BY name");

$extraJs = <<<'JS'
<script>
$(document).ready(function() {
    var table = $('#repairsTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '${ADMIN_URL}assets-mgmt/repairs.php',
            type: 'POST',
            data: function(d) {
                d.action = 'getData';
                d.asset_id = '${assetFilter}';
                d.csrf_token = '${csrf_token()}';
            }
        },
        columns: [
            { data: 'asset' },
            { data: 'repair_date' },
            { data: 'description' },
            { data: 'cost' },
            { data: 'vendor' },
            { data: 'parts_replaced' },
            { data: 'downtime' },
            { data: 'action', orderable: false, searchable: false }
        ],
        order: [[1, 'desc']],
        pageLength: 25,
        language: { searchPlaceholder: 'Search repairs...' }
    });

    $('#repairForm').on('submit', function(e) {
        e.preventDefault();
        var form = $(this);
        var id = $('#repairId').val();
        var url = id ? '${ADMIN_URL}assets-mgmt/repairs.php?action=update&id=' + id : '${ADMIN_URL}assets-mgmt/repairs.php?action=create';
        $.post(url, form.serialize(), function(res) {
            if (res.success) {
                $('#repairModal').modal('hide');
                table.ajax.reload();
                showToast('success', res.message);
            } else { showToast('error', res.message); }
        });
    });

    $('#repairsTable').on('click', '.btn-edit', function() {
        var id = $(this).data('id');
        $.get('${ADMIN_URL}assets-mgmt/repairs.php?action=edit&id=' + id, function(res) {
            if (res.success) {
                var r = res.data;
                $('#repairId').val(r.id);
                $('#rAssetId').val(r.asset_id);
                $('#rDate').val(r.repair_date);
                $('#rDesc').val(r.description);
                $('#rCost').val(r.cost);
                $('#rVendor').val(r.vendor);
                $('#rParts').val(r.parts_replaced);
                $('#rDowntime').val(r.downtime_hours);
                $('#rNotes').val(r.notes);
                $('#repairModalTitle').text('Edit Repair');
                $('#repairModal').modal('show');
            }
        });
    });

    $('.btn-new-repair').click(function() {
        $('#repairForm')[0].reset();
        $('#repairId').val('');
        $('#rDate').val('<?= date('Y-m-d') ?>');
        $('#repairModalTitle').text('Log Repair');
        <?php if ($assetFilter): ?>
        $('#rAssetId').val('{$assetFilter}');
        <?php endif; ?>
        $('#repairModal').modal('show');
    });

    $('#repairsTable').on('click', '.btn-delete', function() {
        var id = $(this).data('id');
        if (confirm('Delete this repair record?')) {
            $.post('${ADMIN_URL}assets-mgmt/repairs.php?action=delete&id=' + id, {csrf_token: '${csrf_token()}'}, function(res) {
                if (res.success) { table.ajax.reload(); showToast('success', res.message); }
                else { showToast('error', res.message); }
            });
        }
    });
});
</script>
JS;

if (isPost()) {
    $action = request('action');
    try {
        if (!verify_csrf(post('csrf_token'))) {
            throw new Exception('Security validation failed');
        }

        if ($action === 'getData') {
            $draw = intval(post('draw'));
            $start = intval(post('start'));
            $length = intval(post('length')) ?: 25;
            $search = post('search')['value'] ?? '';
            $orderColumn = post('order')[0]['column'] ?? 1;
            $orderDir = post('order')[0]['dir'] ?? 'desc';
            $columns = ['a.name', 'ar.repair_date', 'ar.description', 'ar.cost', 'ar.vendor', 'ar.parts_replaced', 'ar.downtime_hours'];
            $orderBy = $columns[$orderColumn] ?? 'ar.repair_date';

            $where = "WHERE 1=1";
            $params = [];
            $af = post('asset_id');
            if (!empty($af)) { $where .= " AND ar.asset_id = :aid"; $params['aid'] = (int)$af; }
            if (!empty($search)) {
                $where .= " AND (ar.description LIKE :s OR a.name LIKE :s2 OR ar.vendor LIKE :s3)";
                $params['s'] = "%{$search}%";
                $params['s2'] = "%{$search}%";
                $params['s3'] = "%{$search}%";
            }

            $total = db()->fetchColumn("SELECT COUNT(*) FROM asset_repairs ar JOIN assets a ON ar.asset_id = a.id {$where}", $params);
            $rows = db()->fetchAll(
                "SELECT ar.*, a.name AS asset_name, a.asset_code
                 FROM asset_repairs ar JOIN assets a ON ar.asset_id = a.id
                 {$where} ORDER BY {$orderBy} {$orderDir} LIMIT {$start}, {$length}", $params
            );

            $data = [];
            foreach ($rows as $r) {
                $data[] = [
                    'asset' => '<strong>' . escape($r['asset_name']) . '</strong><br><small class="text-muted">' . escape($r['asset_code']) . '</small>',
                    'repair_date' => format_date($r['repair_date']),
                    'description' => escape(truncate($r['description'], 50)),
                    'cost' => $r['cost'] > 0 ? format_currency($r['cost']) : '-',
                    'vendor' => escape($r['vendor'] ?? '-'),
                    'parts_replaced' => escape(truncate($r['parts_replaced'] ?? '-', 30)),
                    'downtime' => $r['downtime_hours'] ? $r['downtime_hours'] . ' hrs' : '-',
                    'action' => '<div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-info btn-edit" data-id="' . $r['id'] . '"><i class="fas fa-edit"></i></button>
                        <button type="button" class="btn btn-danger btn-delete" data-id="' . $r['id'] . '"><i class="fas fa-trash"></i></button>
                    </div>'
                ];
            }

            json_response(['draw' => $draw, 'recordsTotal' => intval($total), 'recordsFiltered' => intval($total), 'data' => $data]);
        }

        if ($action === 'create') {
            $v = validator(post(), ['asset_id' => 'required|numeric', 'repair_date' => 'required|date', 'description' => 'required']);
            if ($v->fails()) json_error('Validation failed', $v->errors());

            $id = db()->insert('asset_repairs', [
                'asset_id' => (int)post('asset_id'),
                'repair_date' => post('repair_date'),
                'description' => post('description'),
                'cost' => (float)post('cost', 0),
                'vendor' => post('vendor'),
                'parts_replaced' => post('parts_replaced'),
                'downtime_hours' => (int)post('downtime_hours') ?: null,
                'reported_by' => Auth::id(),
                'notes' => post('notes'),
                'created_at' => date('Y-m-d H:i:s')
            ]);

            // If repair is logged and asset is operational, mark as maintenance
            $asset = db()->fetchOne("SELECT status FROM assets WHERE id = ?", [(int)post('asset_id')]);
            if ($asset && $asset['status'] === 'operational') {
                db()->update('assets', ['status' => 'maintenance'], 'id = :id', ['id' => (int)post('asset_id')]);
            }

            log_activity(Auth::id(), 'create', 'Assets', 'Logged repair for asset ID: ' . post('asset_id'));
            json_success(['id' => $id], 'Repair record created');
        }

        if ($action === 'update') {
            $id = (int)get('id');
            if (!$id) json_error('Invalid ID');
            $v = validator(post(), ['asset_id' => 'required|numeric', 'repair_date' => 'required|date', 'description' => 'required']);
            if ($v->fails()) json_error('Validation failed', $v->errors());

            db()->update('asset_repairs', [
                'asset_id' => (int)post('asset_id'),
                'repair_date' => post('repair_date'),
                'description' => post('description'),
                'cost' => (float)post('cost', 0),
                'vendor' => post('vendor'),
                'parts_replaced' => post('parts_replaced'),
                'downtime_hours' => (int)post('downtime_hours') ?: null,
                'notes' => post('notes'),
            ], 'id = :id', ['id' => $id]);

            log_activity(Auth::id(), 'update', 'Assets', 'Updated repair ID: ' . $id);
            json_success([], 'Repair record updated');
        }

        if ($action === 'delete') {
            $id = (int)get('id');
            if (!$id) json_error('Invalid ID');
            db()->delete('asset_repairs', 'id = :id', ['id' => $id]);
            log_activity(Auth::id(), 'delete', 'Assets', 'Deleted repair ID: ' . $id);
            json_success([], 'Repair record deleted');
        }

        if ($action === 'edit') {
            $id = (int)get('id');
            $r = db()->fetchOne("SELECT * FROM asset_repairs WHERE id = ?", [$id]);
            if (!$r) json_error('Not found');
            json_success($r);
        }
    } catch (Exception $e) {
        json_error($e->getMessage());
    }
}

$totalRepairCost = db()->fetchColumn("SELECT COALESCE(SUM(cost), 0) FROM asset_repairs");
$totalRepairs = db()->fetchColumn("SELECT COUNT(*) FROM asset_repairs");
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <h4 class="mb-0"><i class="fas fa-wrench me-2 text-danger"></i>Asset Repair History</h4>
        <button type="button" class="btn btn-danger btn-sm btn-new-repair">
            <i class="fas fa-plus me-1"></i>Log Repair
        </button>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card card-stats">
            <div class="card-body">
                <h6 class="text-muted mb-1">Total Repairs</h6>
                <h3 class="mb-0"><?= number_format($totalRepairs) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card card-stats">
            <div class="card-body">
                <h6 class="text-muted mb-1">Total Repair Cost</h6>
                <h3 class="mb-0"><?= format_currency($totalRepairCost) ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="get" class="row g-2 mb-3">
            <div class="col-md-4">
                <label class="form-label small">Asset</label>
                <select name="asset_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Assets</option>
                    <?php foreach ($assets as $a): ?>
                    <option value="<?= $a['id'] ?>" <?= $assetFilter == $a['id'] ? 'selected' : '' ?>><?= escape($a['asset_code'] . ' - ' . $a['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <a href="?" class="btn btn-sm btn-secondary"><i class="fas fa-undo"></i> Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table id="repairsTable" class="table table-hover table-bordered w-100">
                <thead class="table-light">
                    <tr>
                        <th>Asset</th>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Cost</th>
                        <th>Vendor</th>
                        <th>Parts Replaced</th>
                        <th>Downtime</th>
                        <th class="no-sort" style="width:80px">Actions</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="repairModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="repairForm" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="id" id="repairId">
                <div class="modal-header">
                    <h5 class="modal-title" id="repairModalTitle"><i class="fas fa-wrench me-2 text-danger"></i>Log Repair</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Asset <span class="text-danger">*</span></label>
                            <select name="asset_id" id="rAssetId" class="form-select" required>
                                <option value="">Select Asset</option>
                                <?php foreach ($assets as $a): ?>
                                <option value="<?= $a['id'] ?>"><?= escape($a['asset_code'] . ' - ' . $a['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Repair Date <span class="text-danger">*</span></label>
                            <input type="date" name="repair_date" id="rDate" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Cost (NRs.)</label>
                            <input type="number" step="0.01" min="0" name="cost" id="rCost" class="form-control" value="0">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea name="description" id="rDesc" class="form-control" rows="3" required></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Vendor/Service Provider</label>
                            <input type="text" name="vendor" id="rVendor" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Parts Replaced</label>
                            <input type="text" name="parts_replaced" id="rParts" class="form-control" placeholder="e.g. Valve, pipe section">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Downtime (Hours)</label>
                            <input type="number" name="downtime_hours" id="rDowntime" class="form-control" min="0">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" id="rNotes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-save me-1"></i>Save Repair</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once ADMIN_PATH . 'includes/footer.php'; ?>
