<?php
require_once __DIR__ . '/../../includes/config.php';
$pageTitle = 'Asset Maintenance';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => ADMIN_URL . 'dashboard/index.php'],
    ['label' => 'Assets', 'url' => ADMIN_URL . 'assets-mgmt/index.php'],
    ['label' => 'Maintenance']
];
RBAC::requirePermission('maintenance.schedule');

require_once __DIR__ . '/../includes/header.php';

$assetFilter = get('asset_id', '');
$statusFilter = get('status', '');

$assets = db()->fetchAll("SELECT id, asset_code, name, asset_type FROM assets WHERE deleted_at IS NULL ORDER BY name");
$maintenanceTypes = ['routine', 'repair', 'emergency', 'overhaul'];
$maintenanceStatuses = ['scheduled', 'in_progress', 'completed', 'cancelled'];

$extraJs = <<<'JS'
<script>
$(document).ready(function() {
    var table = $('#maintenanceTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '${ADMIN_URL}assets-mgmt/maintenance.php',
            type: 'POST',
            data: function(d) {
                d.action = 'getData';
                d.asset_id = '${assetFilter}';
                d.status = '${statusFilter}';
                d.csrf_token = '${csrf_token()}';
            }
        },
        columns: [
            { data: 'asset' },
            { data: 'title' },
            { data: 'type' },
            { data: 'scheduled_date' },
            { data: 'completion_date' },
            { data: 'cost' },
            { data: 'vendor' },
            { data: 'status' },
            { data: 'action', orderable: false, searchable: false }
        ],
        order: [[3, 'desc']],
        pageLength: 25,
        language: { searchPlaceholder: 'Search maintenance...' }
    });

    $('#maintenanceForm').on('submit', function(e) {
        e.preventDefault();
        var form = $(this);
        var id = $('#maintenanceId').val();
        var url = id ? '${ADMIN_URL}assets-mgmt/maintenance.php?action=update&id=' + id : '${ADMIN_URL}assets-mgmt/maintenance.php?action=create';
        $.post(url, form.serialize(), function(res) {
            if (res.success) {
                $('#maintenanceModal').modal('hide');
                table.ajax.reload();
                showToast('success', res.message);
            } else { showToast('error', res.message); }
        });
    });

    $('#maintenanceTable').on('click', '.btn-edit', function() {
        var id = $(this).data('id');
        $.get('${ADMIN_URL}assets-mgmt/maintenance.php?action=edit&id=' + id, function(res) {
            if (res.success) {
                var m = res.data;
                $('#maintenanceId').val(m.id);
                $('#mAssetId').val(m.asset_id);
                $('#mType').val(m.maintenance_type);
                $('#mTitle').val(m.title);
                $('#mDesc').val(m.description);
                $('#mScheduledDate').val(m.scheduled_date);
                $('#mCompletionDate').val(m.completion_date);
                $('#mCost').val(m.cost);
                $('#mPerformedBy').val(m.performed_by);
                $('#mVendor').val(m.vendor);
                $('#mNotes').val(m.notes);
                $('#mStatus').val(m.status);
                $('#maintenanceModalTitle').text('Edit Maintenance');
                $('#maintenanceModal').modal('show');
            }
        });
    });

    $('.btn-new-maintenance').click(function() {
        $('#maintenanceForm')[0].reset();
        $('#maintenanceId').val('');
        $('#mType').val('routine');
        $('#mStatus').val('scheduled');
        $('#maintenanceModalTitle').text('Schedule Maintenance');
        <?php if ($assetFilter): ?>
        $('#mAssetId').val('{$assetFilter}');
        <?php endif; ?>
        $('#maintenanceModal').modal('show');
    });

    $('#maintenanceTable').on('click', '.btn-delete', function() {
        var id = $(this).data('id');
        if (confirm('Delete this maintenance record?')) {
            $.post('${ADMIN_URL}assets-mgmt/maintenance.php?action=delete&id=' + id, {csrf_token: '${csrf_token()}'}, function(res) {
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
            $orderColumn = post('order')[0]['column'] ?? 3;
            $orderDir = post('order')[0]['dir'] ?? 'desc';
            $columns = ['a.name', 'am.title', 'am.maintenance_type', 'am.scheduled_date', 'am.completion_date', 'am.cost', 'am.vendor', 'am.status'];
            $orderBy = $columns[$orderColumn] ?? 'am.scheduled_date';

            $where = "WHERE 1=1";
            $params = [];

            $af = post('asset_id');
            if (!empty($af)) { $where .= " AND am.asset_id = :aid"; $params['aid'] = (int)$af; }
            $sf = post('status');
            if (!empty($sf)) { $where .= " AND am.status = :st"; $params['st'] = $sf; }
            if (!empty($search)) {
                $where .= " AND (am.title LIKE :s OR a.name LIKE :s2)";
                $params['s'] = "%{$search}%";
                $params['s2'] = "%{$search}%";
            }

            $total = db()->fetchColumn("SELECT COUNT(*) FROM asset_maintenance am JOIN assets a ON am.asset_id = a.id {$where}", $params);
            $rows = db()->fetchAll(
                "SELECT am.*, a.name AS asset_name, a.asset_code
                 FROM asset_maintenance am JOIN assets a ON am.asset_id = a.id
                 {$where} ORDER BY {$orderBy} {$orderDir} LIMIT {$start}, {$length}", $params
            );

            $typeLabels = ['routine' => 'Routine', 'repair' => 'Repair', 'emergency' => 'Emergency', 'overhaul' => 'Overhaul'];
            $data = [];
            foreach ($rows as $r) {
                $typeLabel = $typeLabels[$r['maintenance_type']] ?? ucfirst($r['maintenance_type']);
                $data[] = [
                    'asset' => '<strong>' . escape($r['asset_name']) . '</strong><br><small class="text-muted">' . escape($r['asset_code']) . '</small>',
                    'title' => escape(truncate($r['title'], 40)),
                    'type' => '<span class="badge bg-secondary">' . $typeLabel . '</span>',
                    'scheduled_date' => $r['scheduled_date'] ? format_date($r['scheduled_date']) : '-',
                    'completion_date' => $r['completion_date'] ? format_date($r['completion_date']) : '-',
                    'cost' => $r['cost'] > 0 ? format_currency($r['cost']) : '-',
                    'vendor' => escape($r['vendor'] ?? '-'),
                    'status' => get_status_badge($r['status']),
                    'action' => '<div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-info btn-edit" data-id="' . $r['id'] . '"><i class="fas fa-edit"></i></button>
                        <button type="button" class="btn btn-danger btn-delete" data-id="' . $r['id'] . '"><i class="fas fa-trash"></i></button>
                    </div>'
                ];
            }

            json_response(['draw' => $draw, 'recordsTotal' => intval($total), 'recordsFiltered' => intval($total), 'data' => $data]);
        }

        if ($action === 'create') {
            $v = validator(post(), ['asset_id' => 'required|numeric', 'title' => 'required', 'scheduled_date' => 'date']);
            if ($v->fails()) json_error('Validation failed', $v->errors());

            $id = db()->insert('asset_maintenance', [
                'asset_id' => (int)post('asset_id'),
                'maintenance_type' => post('maintenance_type', 'routine'),
                'title' => post('title'),
                'description' => post('description'),
                'scheduled_date' => post('scheduled_date') ?: null,
                'completion_date' => post('completion_date') ?: null,
                'cost' => (float)post('cost', 0),
                'performed_by' => post('performed_by'),
                'vendor' => post('vendor'),
                'notes' => post('notes'),
                'status' => post('status', 'scheduled'),
                'created_at' => date('Y-m-d H:i:s')
            ]);

            // Update asset status if maintenance is being performed
            if (post('status') === 'in_progress') {
                db()->update('assets', ['status' => 'maintenance'], 'id = :id', ['id' => (int)post('asset_id')]);
            }
            if (post('status') === 'completed') {
                db()->update('assets', ['status' => 'operational'], 'id = :id', ['id' => (int)post('asset_id')]);
            }

            log_activity(Auth::id(), 'create', 'Assets', 'Maintenance scheduled: ' . post('title'));
            json_success(['id' => $id], 'Maintenance record created');
        }

        if ($action === 'update') {
            $id = (int)get('id');
            if (!$id) json_error('Invalid ID');
            $v = validator(post(), ['asset_id' => 'required|numeric', 'title' => 'required']);
            if ($v->fails()) json_error('Validation failed', $v->errors());

            $old = db()->fetchOne("SELECT status, asset_id FROM asset_maintenance WHERE id = ?", [$id]);

            db()->update('asset_maintenance', [
                'asset_id' => (int)post('asset_id'),
                'maintenance_type' => post('maintenance_type', 'routine'),
                'title' => post('title'),
                'description' => post('description'),
                'scheduled_date' => post('scheduled_date') ?: null,
                'completion_date' => post('completion_date') ?: null,
                'cost' => (float)post('cost', 0),
                'performed_by' => post('performed_by'),
                'vendor' => post('vendor'),
                'notes' => post('notes'),
                'status' => post('status', 'scheduled'),
            ], 'id = :id', ['id' => $id]);

            // Update asset status based on maintenance status change
            $newStatus = post('status');
            if ($newStatus !== ($old['status'] ?? '')) {
                $assetId = (int)post('asset_id');
                if ($newStatus === 'in_progress') {
                    db()->update('assets', ['status' => 'maintenance'], 'id = :id', ['id' => $assetId]);
                } elseif ($newStatus === 'completed') {
                    db()->update('assets', ['status' => 'operational'], 'id = :id', ['id' => $assetId]);
                } elseif ($newStatus === 'cancelled' && $old['status'] === 'in_progress') {
                    db()->update('assets', ['status' => 'operational'], 'id = :id', ['id' => $assetId]);
                }
            }

            log_activity(Auth::id(), 'update', 'Assets', 'Updated maintenance: ' . post('title'));
            json_success([], 'Maintenance updated');
        }

        if ($action === 'delete') {
            $id = (int)get('id');
            if (!$id) json_error('Invalid ID');
            $m = db()->fetchOne("SELECT asset_id, status FROM asset_maintenance WHERE id = ?", [$id]);
            if ($m && $m['status'] === 'in_progress') {
                db()->update('assets', ['status' => 'operational'], 'id = :id', ['id' => $m['asset_id']]);
            }
            db()->delete('asset_maintenance', 'id = :id', ['id' => $id]);
            log_activity(Auth::id(), 'delete', 'Assets', 'Deleted maintenance ID: ' . $id);
            json_success([], 'Maintenance record deleted');
        }

        if ($action === 'edit') {
            $id = (int)get('id');
            $m = db()->fetchOne("SELECT * FROM asset_maintenance WHERE id = ?", [$id]);
            if (!$m) json_error('Not found');
            json_success($m);
        }
    } catch (Exception $e) {
        json_error($e->getMessage());
    }
}

$upcomingCount = db()->fetchColumn("SELECT COUNT(*) FROM asset_maintenance WHERE status IN ('scheduled', 'in_progress')");
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <h4 class="mb-0"><i class="fas fa-tools me-2 text-warning"></i>Asset Maintenance</h4>
        <div>
            <span class="badge bg-warning text-dark me-2"><?= $upcomingCount ?> Active/Scheduled</span>
            <button type="button" class="btn btn-warning btn-sm btn-new-maintenance">
                <i class="fas fa-plus me-1"></i>Schedule Maintenance
            </button>
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
            <div class="col-md-3">
                <label class="form-label small">Status</label>
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Status</option>
                    <?php foreach ($maintenanceStatuses as $s): ?>
                    <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $s)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <a href="?" class="btn btn-sm btn-secondary"><i class="fas fa-undo"></i> Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table id="maintenanceTable" class="table table-hover table-bordered w-100">
                <thead class="table-light">
                    <tr>
                        <th>Asset</th>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Scheduled</th>
                        <th>Completed</th>
                        <th>Cost</th>
                        <th>Vendor</th>
                        <th>Status</th>
                        <th class="no-sort" style="width:80px">Actions</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="maintenanceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="maintenanceForm" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="id" id="maintenanceId">
                <div class="modal-header">
                    <h5 class="modal-title" id="maintenanceModalTitle"><i class="fas fa-tools me-2 text-warning"></i>Schedule Maintenance</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Asset <span class="text-danger">*</span></label>
                            <select name="asset_id" id="mAssetId" class="form-select" required>
                                <option value="">Select Asset</option>
                                <?php foreach ($assets as $a): ?>
                                <option value="<?= $a['id'] ?>"><?= escape($a['asset_code'] . ' - ' . $a['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Type</label>
                            <select name="maintenance_type" id="mType" class="form-select">
                                <?php foreach ($maintenanceTypes as $t): ?>
                                <option value="<?= $t ?>"><?= ucfirst($t) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="status" id="mStatus" class="form-select">
                                <?php foreach ($maintenanceStatuses as $s): ?>
                                <option value="<?= $s ?>"><?= ucfirst(str_replace('_', ' ', $s)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" id="mTitle" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" id="mDesc" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Scheduled Date</label>
                            <input type="date" name="scheduled_date" id="mScheduledDate" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Completion Date</label>
                            <input type="date" name="completion_date" id="mCompletionDate" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Cost (NRs.)</label>
                            <input type="number" step="0.01" min="0" name="cost" id="mCost" class="form-control" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Performed By</label>
                            <input type="text" name="performed_by" id="mPerformedBy" class="form-control" placeholder="Person name">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Vendor/Contractor</label>
                            <input type="text" name="vendor" id="mVendor" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Notes</label>
                            <input type="text" name="notes" id="mNotes" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-warning"><i class="fas fa-save me-1"></i>Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once ADMIN_PATH . 'includes/footer.php'; ?>
