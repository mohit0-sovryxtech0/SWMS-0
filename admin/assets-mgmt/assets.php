<?php
require_once __DIR__ . '/../../includes/config.php';
$pageTitle = 'Assets';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => ADMIN_URL . 'dashboard/index.php'],
    ['label' => 'Assets', 'url' => ADMIN_URL . 'assets-mgmt/index.php'],
    ['label' => 'All Assets']
];
RBAC::requirePermission('assets.view');

require_once __DIR__ . '/../includes/header.php';

$categories = db()->fetchAll("SELECT id, name FROM asset_categories WHERE deleted_at IS NULL ORDER BY name");
$users = db()->fetchAll("SELECT id, name FROM users WHERE deleted_at IS NULL AND status = 'active' ORDER BY name");
$assetTypes = ['water_tank', 'pipeline', 'pump', 'valve', 'meter', 'vehicle', 'building', 'equipment', 'other'];
$statuses = ['operational', 'maintenance', 'damaged', 'decommissioned', 'under_construction'];

$extraCss = '<style>
.asset-image-preview { max-width: 80px; max-height: 60px; border-radius: 4px; object-fit: cover; }
</style>';

$extraJs = <<<'JS'
<script>
$(document).ready(function() {
    var table = $('#assetsTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '${ADMIN_URL}assets-mgmt/assets.php',
            type: 'POST',
            data: function(d) {
                d.action = 'getData';
                d.csrf_token = '${csrf_token()}';
            }
        },
        columns: [
            { data: 'asset_code' },
            { data: 'name' },
            { data: 'category' },
            { data: 'asset_type' },
            { data: 'location' },
            { data: 'purchase_cost' },
            { data: 'status' },
            { data: 'action', orderable: false, searchable: false }
        ],
        order: [[1, 'asc']],
        pageLength: 25,
        language: { searchPlaceholder: 'Search assets...' }
    });

    $('#assetForm').on('submit', function(e) {
        e.preventDefault();
        var form = $(this);
        var id = $('#assetId').val();
        var url = id ? '${ADMIN_URL}assets-mgmt/assets.php?action=update&id=' + id : '${ADMIN_URL}assets-mgmt/assets.php?action=create';
        var formData = new FormData(this);
        $.ajax({
            url: url, type: 'POST', data: formData,
            processData: false, contentType: false,
            success: function(res) {
                if (res.success) {
                    $('#assetModal').modal('hide');
                    table.ajax.reload();
                    showToast('success', res.message);
                } else { showToast('error', res.message); }
            }
        });
    });

    $('#assetsTable').on('click', '.btn-edit', function() {
        var id = $(this).data('id');
        $.get('${ADMIN_URL}assets-mgmt/assets.php?action=edit&id=' + id, function(res) {
            if (res.success) {
                var a = res.data;
                $('#assetId').val(a.id);
                $('#assetCode').val(a.asset_code);
                $('#assetCategory').val(a.category_id);
                $('#assetType').val(a.asset_type);
                $('#assetName').val(a.name);
                $('#assetDesc').val(a.description);
                $('#assetLocation').val(a.location);
                $('#assetWard').val(a.ward_no);
                $('#assetLat').val(a.latitude);
                $('#assetLng').val(a.longitude);
                $('#purchaseDate').val(a.purchase_date);
                $('#purchaseCost').val(a.purchase_cost);
                $('#currentValue').val(a.current_value);
                $('#warrantyExpiry').val(a.warranty_expiry);
                $('#lifeSpan').val(a.life_span_years);
                $('#manufacturer').val(a.manufacturer);
                $('#modelNo').val(a.model_no);
                $('#serialNo').val(a.serial_no);
                $('#capacity').val(a.capacity);
                $('#assetStatus').val(a.status);
                $('#assignedTo').val(a.assigned_to);
                $('#assetNotes').val(a.notes);
                $('#assetCurrentImage').val(a.image);
                if (a.image) {
                    $('#imagePreview').attr('src', '${UPLOAD_URL}assets/' + a.image).show();
                } else {
                    $('#imagePreview').hide();
                }
                $('#assetModalTitle').text('Edit Asset');
                $('#assetModal').modal('show');
            }
        });
    });

    $('.btn-new-asset').click(function() {
        $('#assetForm')[0].reset();
        $('#assetId').val('');
        $('#assetCurrentImage').val('');
        $('#imagePreview').hide();
        $('#assetStatus').val('operational');
        $('#assetModalTitle').text('Add New Asset');
        $('#assetModal').modal('show');
    });

    $('#assetsTable').on('click', '.btn-delete', function() {
        var id = $(this).data('id');
        if (confirm('Delete this asset?')) {
            $.post('${ADMIN_URL}assets-mgmt/assets.php?action=delete&id=' + id, {csrf_token: '${csrf_token()}'}, function(res) {
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
            $orderDir = post('order')[0]['dir'] ?? 'asc';
            $columns = ['asset_code', 'name', 'cat.name', 'asset_type', 'location', 'purchase_cost', 'status'];
            $orderBy = $columns[$orderColumn] ?? 'a.name';

            $where = "WHERE a.deleted_at IS NULL";
            $params = [];
            if (!empty($search)) {
                $where .= " AND (a.asset_code LIKE :s OR a.name LIKE :s2 OR a.serial_no LIKE :s3)";
                $params['s'] = "%{$search}%";
                $params['s2'] = "%{$search}%";
                $params['s3'] = "%{$search}%";
            }

            $total = db()->fetchColumn("SELECT COUNT(*) FROM assets a {$where}", $params);
            $rows = db()->fetchAll(
                "SELECT a.*, c.name AS category_name FROM assets a
                 LEFT JOIN asset_categories c ON a.category_id = c.id
                 {$where} ORDER BY {$orderBy} {$orderDir} LIMIT {$start}, {$length}", $params
            );

            $typeLabels = ['water_tank'=>'Water Tank','pipeline'=>'Pipeline','pump'=>'Pump','valve'=>'Valve','meter'=>'Meter','vehicle'=>'Vehicle','building'=>'Building','equipment'=>'Equipment','other'=>'Other'];
            $data = [];
            foreach ($rows as $row) {
                $typeLabel = $typeLabels[$row['asset_type']] ?? ucfirst($row['asset_type']);
                $data[] = [
                    'asset_code' => '<span class="fw-semibold">' . escape($row['asset_code']) . '</span>',
                    'name' => escape($row['name']),
                    'category' => escape($row['category_name'] ?? '-'),
                    'asset_type' => '<span class="badge bg-info">' . $typeLabel . '</span>',
                    'location' => escape(truncate($row['location'] ?? '-', 30)),
                    'purchase_cost' => $row['purchase_cost'] > 0 ? format_currency($row['purchase_cost']) : '-',
                    'status' => get_status_badge($row['status']),
                    'action' => '<div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-info btn-edit" data-id="' . $row['id'] . '" title="Edit"><i class="fas fa-edit"></i></button>
                        <a href="' . ADMIN_URL . 'assets-mgmt/maintenance.php?asset_id=' . $row['id'] . '" class="btn btn-warning" title="Maintenance"><i class="fas fa-tools"></i></a>
                        <button type="button" class="btn btn-danger btn-delete" data-id="' . $row['id'] . '" title="Delete"><i class="fas fa-trash"></i></button>
                    </div>'
                ];
            }

            json_response([
                'draw' => $draw, 'recordsTotal' => intval($total),
                'recordsFiltered' => intval($total), 'data' => $data
            ]);
        }

        if ($action === 'create') {
            RBAC::requirePermission('assets.create');
            $v = validator($_POST + $_FILES, [
                'asset_code' => 'required|unique:assets,asset_code',
                'name' => 'required|min:2|max:200',
                'category_id' => 'required|numeric',
                'asset_type' => 'required',
                'ward_no' => 'numeric',
                'purchase_cost' => 'numeric',
                'current_value' => 'numeric',
                'life_span_years' => 'numeric',
                'image' => 'file:jpg,jpeg,png,gif',
            ]);
            if ($v->fails()) json_error('Validation failed', $v->errors());

            $image = '';
            if (!empty($_FILES['image']['name'])) {
                $uploadDir = UPLOADS_PATH . 'assets/';
                $image = upload_file($_FILES['image'], $uploadDir);
                if ($image === false) json_error('Image upload failed');
            }

            $id = db()->insert('assets', [
                'asset_code' => post('asset_code'),
                'category_id' => (int)post('category_id'),
                'asset_type' => post('asset_type'),
                'name' => post('name'),
                'description' => post('description'),
                'location' => post('location'),
                'ward_no' => (int)post('ward_no') ?: null,
                'latitude' => post('latitude') ?: null,
                'longitude' => post('longitude') ?: null,
                'purchase_date' => post('purchase_date') ?: null,
                'purchase_cost' => (float)post('purchase_cost', 0),
                'current_value' => (float)post('current_value', 0),
                'warranty_expiry' => post('warranty_expiry') ?: null,
                'life_span_years' => (int)post('life_span_years') ?: null,
                'manufacturer' => post('manufacturer'),
                'model_no' => post('model_no'),
                'serial_no' => post('serial_no'),
                'capacity' => post('capacity'),
                'status' => post('status', 'operational'),
                'image' => $image,
                'assigned_to' => (int)post('assigned_to') ?: null,
                'notes' => post('notes'),
                'created_at' => date('Y-m-d H:i:s')
            ]);

            log_activity(Auth::id(), 'create', 'Assets', 'Created asset: ' . post('asset_code'));
            json_success(['id' => $id], 'Asset created successfully');
        }

        if ($action === 'update') {
            RBAC::requirePermission('assets.edit');
            $id = (int)get('id');
            if (!$id) json_error('Invalid asset ID');

            $v = validator($_POST + $_FILES, [
                'asset_code' => 'required|unique:assets,asset_code,' . $id,
                'name' => 'required|min:2|max:200',
                'category_id' => 'required|numeric',
                'asset_type' => 'required',
                'image' => 'file:jpg,jpeg,png,gif',
            ]);
            if ($v->fails()) json_error('Validation failed', $v->errors());

            $existing = db()->fetchOne("SELECT image FROM assets WHERE id = ?", [$id]);
            $image = $existing['image'] ?? '';

            if (!empty($_FILES['image']['name'])) {
                $uploadDir = UPLOADS_PATH . 'assets/';
                $newImage = upload_file($_FILES['image'], $uploadDir);
                if ($newImage === false) json_error('Image upload failed');
                if ($image) delete_file($uploadDir . $image);
                $image = $newImage;
            }

            db()->update('assets', [
                'asset_code' => post('asset_code'),
                'category_id' => (int)post('category_id'),
                'asset_type' => post('asset_type'),
                'name' => post('name'),
                'description' => post('description'),
                'location' => post('location'),
                'ward_no' => (int)post('ward_no') ?: null,
                'latitude' => post('latitude') ?: null,
                'longitude' => post('longitude') ?: null,
                'purchase_date' => post('purchase_date') ?: null,
                'purchase_cost' => (float)post('purchase_cost', 0),
                'current_value' => (float)post('current_value', 0),
                'warranty_expiry' => post('warranty_expiry') ?: null,
                'life_span_years' => (int)post('life_span_years') ?: null,
                'manufacturer' => post('manufacturer'),
                'model_no' => post('model_no'),
                'serial_no' => post('serial_no'),
                'capacity' => post('capacity'),
                'status' => post('status', 'operational'),
                'image' => $image,
                'assigned_to' => (int)post('assigned_to') ?: null,
                'notes' => post('notes'),
            ], 'id = :id', ['id' => $id]);

            log_activity(Auth::id(), 'update', 'Assets', 'Updated asset: ' . post('asset_code'));
            json_success([], 'Asset updated successfully');
        }

        if ($action === 'delete') {
            $id = (int)get('id');
            if (!$id) json_error('Invalid ID');
            db()->softDelete('assets', $id);
            log_activity(Auth::id(), 'delete', 'Assets', 'Deleted asset ID: ' . $id);
            json_success([], 'Asset deleted');
        }

        if ($action === 'edit') {
            $id = (int)get('id');
            $a = db()->fetchOne("SELECT * FROM assets WHERE id = ? AND deleted_at IS NULL", [$id]);
            if (!$a) json_error('Asset not found');
            json_success($a);
        }
    } catch (Exception $e) {
        json_error($e->getMessage());
    }
}
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <h4 class="mb-0"><i class="fas fa-building me-2 text-primary"></i>All Assets</h4>
        <div>
            <?php if (RBAC::can('assets.create')): ?>
            <button type="button" class="btn btn-primary btn-sm btn-new-asset">
                <i class="fas fa-plus me-1"></i>New Asset
            </button>
            <?php endif; ?>
            <a href="<?= ADMIN_URL ?>assets-mgmt/index.php" class="btn btn-info btn-sm">
                <i class="fas fa-chart-pie me-1"></i>Dashboard
            </a>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table id="assetsTable" class="table table-hover table-bordered w-100">
                <thead class="table-light">
                    <tr>
                        <th>Asset Code</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Type</th>
                        <th>Location</th>
                        <th>Cost</th>
                        <th>Status</th>
                        <th class="no-sort" style="width:110px">Actions</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="assetModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form id="assetForm" method="post" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="id" id="assetId">
                <input type="hidden" name="current_image" id="assetCurrentImage">
                <div class="modal-header">
                    <h5 class="modal-title" id="assetModalTitle"><i class="fas fa-building me-2 text-primary"></i>Add Asset</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <ul class="nav nav-tabs mb-3" id="assetTab" role="tablist">
                        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#basicInfo">Basic Info</a></li>
                        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#details">Details</a></li>
                        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#financial">Financial & Location</a></li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="basicInfo">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Asset Code <span class="text-danger">*</span></label>
                                    <input type="text" name="asset_code" id="assetCode" class="form-control" required>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label">Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name" id="assetName" class="form-control" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Category <span class="text-danger">*</span></label>
                                    <select name="category_id" id="assetCategory" class="form-select" required>
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $c): ?>
                                        <option value="<?= $c['id'] ?>"><?= escape($c['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Asset Type <span class="text-danger">*</span></label>
                                    <select name="asset_type" id="assetType" class="form-select" required>
                                        <option value="">Select Type</option>
                                        <?php foreach ($assetTypes as $t): ?>
                                        <option value="<?= $t ?>"><?= ucfirst(str_replace('_', ' ', $t)) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Status</label>
                                    <select name="status" id="assetStatus" class="form-select">
                                        <?php foreach ($statuses as $s): ?>
                                        <option value="<?= $s ?>"><?= ucfirst(str_replace('_', ' ', $s)) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Assigned To</label>
                                    <select name="assigned_to" id="assignedTo" class="form-select">
                                        <option value="">Unassigned</option>
                                        <?php foreach ($users as $u): ?>
                                        <option value="<?= $u['id'] ?>"><?= escape($u['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Description</label>
                                    <textarea name="description" id="assetDesc" class="form-control" rows="2"></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="details">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Manufacturer</label>
                                    <input type="text" name="manufacturer" id="manufacturer" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Model No</label>
                                    <input type="text" name="model_no" id="modelNo" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Serial No</label>
                                    <input type="text" name="serial_no" id="serialNo" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Capacity</label>
                                    <input type="text" name="capacity" id="capacity" class="form-control" placeholder="e.g. 50000 Ltrs">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Life Span (Years)</label>
                                    <input type="number" name="life_span_years" id="lifeSpan" class="form-control" min="0">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Image</label>
                                    <input type="file" name="image" class="form-control" accept="image/*">
                                    <img id="imagePreview" class="mt-2 asset-image-preview" style="display:none">
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="financial">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Purchase Date</label>
                                    <input type="date" name="purchase_date" id="purchaseDate" class="form-control">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Purchase Cost</label>
                                    <input type="number" step="0.01" min="0" name="purchase_cost" id="purchaseCost" class="form-control" value="0">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Current Value</label>
                                    <input type="number" step="0.01" min="0" name="current_value" id="currentValue" class="form-control" value="0">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Warranty Expiry</label>
                                    <input type="date" name="warranty_expiry" id="warrantyExpiry" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Location</label>
                                    <input type="text" name="location" id="assetLocation" class="form-control" placeholder="Physical address">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Ward No</label>
                                    <input type="number" name="ward_no" id="assetWard" class="form-control" min="1" max="20">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Latitude</label>
                                    <input type="text" name="latitude" id="assetLat" class="form-control" placeholder="27.7172">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Longitude</label>
                                    <input type="text" name="longitude" id="assetLng" class="form-control" placeholder="85.3240">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Notes</label>
                                    <textarea name="notes" id="assetNotes" class="form-control" rows="2"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Asset</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once ADMIN_PATH . 'includes/footer.php'; ?>
