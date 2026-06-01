<?php
require_once __DIR__ . '/../../includes/config.php';
$pageTitle = 'GIS Layer Management';
$breadcrumbs = [
    ['label' => 'GIS Mapping', 'url' => ADMIN_URL . 'gis/index.php'],
    ['label' => 'Layer Management']
];
RBAC::requirePermission('gis.view');

require_once __DIR__ . '/../includes/header.php';

$layerTypes = [
    'consumer' => 'Consumer Locations',
    'pipeline' => 'Pipeline Network',
    'tank' => 'Water Tanks',
    'pump' => 'Pump Stations',
    'valve' => 'Valves',
    'service_area' => 'Service Area',
    'ward_boundary' => 'Ward Boundaries'
];

$extraJs = <<<'JS'
<script>
$(document).ready(function() {
    var table = $('#layersTable').DataTable({
        processing: true,
        serverSide: false,
        pageLength: 25,
        order: [[0, 'asc']],
        language: { searchPlaceholder: 'Search layers...' }
    });

    $('#layerForm').on('submit', function(e) {
        e.preventDefault();
        var form = $(this);
        var id = $('#layerId').val();
        var url = id ? '<?= ADMIN_URL ?>gis/layers.php?action=update&id=' + id : '<?= ADMIN_URL ?>gis/layers.php?action=create';
        $.ajax({
            url: url, type: 'POST', data: form.serialize(),
            success: function(res) {
                if (res.success) {
                    $('#layerModal').modal('hide');
                    location.reload();
                } else {
                    showToast('error', res.message);
                }
            },
            error: function() { showToast('error', 'Request failed'); }
        });
    });

    $('#layersTable').on('click', '.btn-edit', function() {
        var id = $(this).data('id');
        $.get('<?= ADMIN_URL ?>gis/layers.php?action=edit&id=' + id, function(res) {
            if (res.success) {
                var d = res.data;
                $('#layerId').val(d.id);
                $('#layerName').val(d.name);
                $('#layerType').val(d.layer_type);
                $('#layerColor').val(d.color);
                $('#layerDesc').val(d.description);
                $('#layerVisible').prop('checked', d.is_visible == 1);
                $('#layerModalTitle').text('Edit Layer');
                $('#layerModal').modal('show');
            } else { showToast('error', res.message); }
        });
    });

    $('.btn-new-layer').click(function() {
        $('#layerForm')[0].reset();
        $('#layerId').val('');
        $('#layerVisible').prop('checked', true);
        $('#layerColor').val('#181CB8');
        $('#layerModalTitle').text('Add New Layer');
        $('#layerModal').modal('show');
    });

    $('#layersTable').on('click', '.btn-delete', function() {
        var id = $(this).data('id');
        if (confirm('Delete this layer and all its markers/shapes?')) {
            $.post('<?= ADMIN_URL ?>gis/layers.php?action=delete&id=' + id,
                { csrf_token: '<?= csrf_token() ?>' },
                function(res) {
                    if (res.success) { location.reload(); }
                    else { showToast('error', res.message); }
                }
            );
        }
    });

    $('#layersTable').on('click', '.btn-vis-toggle', function() {
        var id = $(this).data('id');
        var visible = $(this).data('visible');
        $.post('<?= ADMIN_URL ?>gis/layers.php?action=toggle&id=' + id,
            { visible: visible ? 0 : 1, csrf_token: '<?= csrf_token() ?>' },
            function(res) {
                if (res.success) location.reload();
                else showToast('error', res.message);
            }
        );
    });

    // Marker/Shape management
    $('#layersTable').on('click', '.btn-markers', function() {
        var layerId = $(this).data('id');
        var layerName = $(this).data('name');
        loadMarkers(layerId, layerName);
    });

    $('#markerForm').on('submit', function(e) {
        e.preventDefault();
        var form = $(this);
        var id = $('#markerId').val();
        var layerId = $('#markerLayerId').val();
        var url = id ? '<?= ADMIN_URL ?>gis/layers.php?action=updateMarker&id=' + id : '<?= ADMIN_URL ?>gis/layers.php?action=createMarker';
        $.ajax({
            url: url, type: 'POST', data: form.serialize(),
            success: function(res) {
                if (res.success) {
                    $('#markerModal').modal('hide');
                    loadMarkers(layerId, $('#markerLayerName').val());
                } else { showToast('error', res.message); }
            }
        });
    });

    $('.btn-new-marker').click(function() {
        $('#markerForm')[0].reset();
        $('#markerId').val('');
        $('#markerModalTitle').text('Add Marker');
        $('#markerModal').modal('show');
    });
});

function loadMarkers(layerId, layerName) {
    $('#markerLayerId').val(layerId);
    $('#markerLayerName').val(layerName);
    $('#markerLayerTitle').text('Markers - ' + layerName);
    $('#markersTableBody').html('<tr><td colspan="5" class="text-center py-3"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>');
    $('#markersModal').modal('show');

    $.get('<?= ADMIN_URL ?>gis/layers.php?action=getMarkers&layer_id=' + layerId, function(res) {
        if (res.success) {
            var html = '';
            if (res.data.length === 0) {
                html = '<tr><td colspan="5" class="text-center py-3 text-muted">No markers found</td></tr>';
            } else {
                res.data.forEach(function(m) {
                    html += '<tr>' +
                        '<td>' + escapeHtml(m.label || '-') + '</td>' +
                        '<td>' + m.latitude + '</td>' +
                        '<td>' + m.longitude + '</td>' +
                        '<td><span style="display:inline-block;width:20px;height:20px;border-radius:4px;background:' + (m.color || '#181CB8') + '"></span></td>' +
                        '<td>' +
                            '<button class="btn btn-sm btn-info btn-edit-marker" data-id="' + m.id + '" title="Edit"><i class="fas fa-edit"></i></button> ' +
                            '<button class="btn btn-sm btn-danger btn-delete-marker" data-id="' + m.id + '" title="Delete"><i class="fas fa-trash"></i></button>' +
                        '</td></tr>';
                });
            }
            $('#markersTableBody').html(html);
        } else {
            $('#markersTableBody').html('<tr><td colspan="5" class="text-center py-3 text-danger">Failed to load markers</td></tr>');
        }
    });
}

$(document).on('click', '.btn-edit-marker', function() {
    var id = $(this).data('id');
    $.get('<?= ADMIN_URL ?>gis/layers.php?action=getMarker&id=' + id, function(res) {
        if (res.success) {
            var d = res.data;
            $('#markerId').val(d.id);
            $('#markerLabel').val(d.label);
            $('#markerLat').val(d.latitude);
            $('#markerLng').val(d.longitude);
            $('#markerDesc').val(d.description);
            $('#markerColor').val(d.color || '#181CB8');
            $('#markerPopup').val(d.popup_content);
            $('#markerModalTitle').text('Edit Marker');
            $('#markerModal').modal('show');
        }
    });
});

$(document).on('click', '.btn-delete-marker', function() {
    if (!confirm('Delete this marker?')) return;
    var id = $(this).data('id');
    var layerId = $('#markerLayerId').val();
    var layerName = $('#markerLayerName').val();
    $.post('<?= ADMIN_URL ?>gis/layers.php?action=deleteMarker&id=' + id,
        { csrf_token: '<?= csrf_token() ?>' },
        function(res) {
            if (res.success) loadMarkers(layerId, layerName);
            else showToast('error', res.message);
        }
    );
});

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
</script>
JS;

// Handle AJAX actions
if (isPost() || isGet() && get('action')) {
    $action = get('action');
    try {
        // --- Layer CRUD ---
        if ($action === 'create') {
            RBAC::requirePermission('gis.edit');
            if (!verify_csrf(post('csrf_token'))) json_error('Security validation failed');

            $id = db()->insert('gis_layers', [
                'name' => post('name'),
                'layer_type' => post('layer_type'),
                'description' => post('description'),
                'color' => post('color', '#181CB8'),
                'is_visible' => post('is_visible') ? 1 : 0,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            log_activity(Auth::id(), 'create', 'GIS', 'Created layer: ' . post('name'));
            json_success(['id' => $id], 'Layer created successfully');
        }

        if ($action === 'update') {
            RBAC::requirePermission('gis.edit');
            if (!verify_csrf(post('csrf_token'))) json_error('Security validation failed');
            $id = (int)get('id');
            if (!$id) json_error('Invalid layer ID');

            db()->update('gis_layers', [
                'name' => post('name'),
                'layer_type' => post('layer_type'),
                'description' => post('description'),
                'color' => post('color', '#181CB8'),
                'is_visible' => post('is_visible') ? 1 : 0,
            ], 'id = :id', ['id' => $id]);
            log_activity(Auth::id(), 'update', 'GIS', 'Updated layer ID: ' . $id);
            json_success([], 'Layer updated successfully');
        }

        if ($action === 'delete') {
            RBAC::requirePermission('gis.edit');
            if (!verify_csrf(post('csrf_token'))) json_error('Security validation failed');
            $id = (int)get('id');
            if (!$id) json_error('Invalid ID');
            db()->delete('gis_markers', 'layer_id = ?', [$id]);
            db()->delete('gis_shapes', 'layer_id = ?', [$id]);
            db()->delete('gis_layers', 'id = ?', [$id]);
            log_activity(Auth::id(), 'delete', 'GIS', 'Deleted layer ID: ' . $id);
            json_success([], 'Layer deleted');
        }

        if ($action === 'toggle') {
            RBAC::requirePermission('gis.edit');
            $id = (int)get('id');
            if (!$id) json_error('Invalid ID');
            db()->update('gis_layers', ['is_visible' => (int)post('visible')], 'id = :id', ['id' => $id]);
            json_success([], 'Visibility toggled');
        }

        if ($action === 'edit') {
            $id = (int)get('id');
            $layer = db()->fetchOne("SELECT * FROM gis_layers WHERE id = ?", [$id]);
            if (!$layer) json_error('Layer not found');
            json_success($layer);
        }

        // --- Marker CRUD ---
        if ($action === 'getMarkers') {
            $layerId = (int)get('layer_id');
            $markers = db()->fetchAll("SELECT * FROM gis_markers WHERE layer_id = ? ORDER BY id ASC", [$layerId]);
            json_success($markers);
        }

        if ($action === 'getMarker') {
            $id = (int)get('id');
            $marker = db()->fetchOne("SELECT * FROM gis_markers WHERE id = ?", [$id]);
            if (!$marker) json_error('Marker not found');
            json_success($marker);
        }

        if ($action === 'createMarker') {
            RBAC::requirePermission('gis.edit');
            if (!verify_csrf(post('csrf_token'))) json_error('Security validation failed');

            $id = db()->insert('gis_markers', [
                'layer_id' => (int)post('layer_id'),
                'label' => post('label'),
                'description' => post('description'),
                'latitude' => post('latitude'),
                'longitude' => post('longitude'),
                'color' => post('color', '#181CB8'),
                'popup_content' => post('popup_content'),
                'created_at' => date('Y-m-d H:i:s')
            ]);
            json_success(['id' => $id], 'Marker added');
        }

        if ($action === 'updateMarker') {
            RBAC::requirePermission('gis.edit');
            if (!verify_csrf(post('csrf_token'))) json_error('Security validation failed');
            $id = (int)get('id');
            if (!$id) json_error('Invalid ID');

            db()->update('gis_markers', [
                'label' => post('label'),
                'description' => post('description'),
                'latitude' => post('latitude'),
                'longitude' => post('longitude'),
                'color' => post('color', '#181CB8'),
                'popup_content' => post('popup_content'),
            ], 'id = :id', ['id' => $id]);
            json_success([], 'Marker updated');
        }

        if ($action === 'deleteMarker') {
            RBAC::requirePermission('gis.edit');
            if (!verify_csrf(post('csrf_token'))) json_error('Security validation failed');
            $id = (int)get('id');
            if (!$id) json_error('Invalid ID');
            db()->delete('gis_markers', 'id = ?', [$id]);
            json_success([], 'Marker deleted');
        }
    } catch (Exception $e) {
        json_error($e->getMessage());
    }
}

$layers = db()->fetchAll("SELECT l.*, (SELECT COUNT(*) FROM gis_markers WHERE layer_id = l.id) AS marker_count FROM gis_layers l ORDER BY l.id ASC");
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h4><i class="fas fa-layer-group me-2 text-primary"></i>GIS Layer Management</h4>
            <p>Manage map layers, markers, and visibility</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= ADMIN_URL ?>gis/index.php" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-map-marked-alt"></i> Open Map
            </a>
            <?php if (RBAC::can('gis.edit')): ?>
            <button type="button" class="btn btn-primary btn-sm btn-new-layer">
                <i class="fas fa-plus"></i> New Layer
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<?= display_alert() ?>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table id="layersTable" class="table table-hover table-bordered w-100">
                <thead class="table-light">
                    <tr>
                        <th style="width:60px">Color</th>
                        <th>Name</th>
                        <th>Layer Type</th>
                        <th>Markers</th>
                        <th style="width:80px">Visible</th>
                        <th style="width:190px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($layers)): ?>
                    <tr><td colspan="6" class="text-center py-4 text-muted">No layers configured</td></tr>
                    <?php else: ?>
                    <?php foreach ($layers as $l): ?>
                    <tr>
                        <td>
                            <span style="display:inline-block;width:28px;height:28px;border-radius:6px;background:<?= escape($l['color'] ?: '#181CB8') ?>"></span>
                        </td>
                        <td><strong><?= escape($l['name']) ?></strong></td>
                        <td><span class="badge bg-light text-dark"><?= escape($layerTypes[$l['layer_type']] ?? $l['layer_type']) ?></span></td>
                        <td><span class="badge bg-secondary"><?= (int)$l['marker_count'] ?></span></td>
                        <td>
                            <button type="button" class="btn btn-sm <?= $l['is_visible'] ? 'btn-success' : 'btn-outline-secondary' ?> btn-vis-toggle" data-id="<?= $l['id'] ?>" data-visible="<?= $l['is_visible'] ?>">
                                <i class="fas <?= $l['is_visible'] ? 'fa-eye' : 'fa-eye-slash' ?>"></i>
                            </button>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-info btn-edit" data-id="<?= $l['id'] ?>" title="Edit"><i class="fas fa-edit"></i></button>
                                <button type="button" class="btn btn-secondary btn-markers" data-id="<?= $l['id'] ?>" data-name="<?= escape($l['name']) ?>" title="Markers"><i class="fas fa-map-pin"></i></button>
                                <button type="button" class="btn btn-danger btn-delete" data-id="<?= $l['id'] ?>" title="Delete"><i class="fas fa-trash"></i></button>
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

<!-- Layer Modal -->
<div class="modal fade" id="layerModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="layerForm" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="id" id="layerId">
                <div class="modal-header">
                    <h5 class="modal-title" id="layerModalTitle"><i class="fas fa-layer-group me-2 text-primary"></i>Add Layer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="layerName" class="form-control" required maxlength="200">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Layer Type <span class="text-danger">*</span></label>
                            <select name="layer_type" id="layerType" class="form-select" required>
                                <option value="">Select Type</option>
                                <?php foreach ($layerTypes as $val => $label): ?>
                                <option value="<?= $val ?>"><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Color</label>
                            <div class="input-group">
                                <input type="color" name="color" id="layerColor" class="form-control form-control-color" value="#181CB8">
                                <input type="text" class="form-control" id="layerColorHex" value="#181CB8" maxlength="7" oninput="document.getElementById('layerColor').value=this.value">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check mt-4">
                                <input type="checkbox" name="is_visible" id="layerVisible" class="form-check-input" value="1" checked>
                                <label class="form-check-label" for="layerVisible">Visible by default</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" id="layerDesc" class="form-control" rows="2" maxlength="500"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Save Layer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Markers Modal -->
<div class="modal fade" id="markersModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="markerLayerTitle"><i class="fas fa-map-pin me-2 text-primary"></i>Markers</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="markerLayerId">
                <input type="hidden" id="markerLayerName">
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Label</th>
                                <th>Latitude</th>
                                <th>Longitude</th>
                                <th>Color</th>
                                <th style="width:90px">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="markersTableBody">
                            <tr><td colspan="5" class="text-center py-3 text-muted">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
                <button type="button" class="btn btn-primary btn-sm btn-new-marker"><i class="fas fa-plus"></i> Add Marker</button>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Marker Edit Modal -->
<div class="modal fade" id="markerModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="markerForm" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="id" id="markerId">
                <input type="hidden" name="layer_id" id="markerLayerIdField">
                <div class="modal-header">
                    <h5 class="modal-title" id="markerModalTitle"><i class="fas fa-map-pin me-2 text-primary"></i>Marker</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Label</label>
                            <input type="text" name="label" id="markerLabel" class="form-control" maxlength="200">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Color</label>
                            <input type="color" name="color" id="markerColor" class="form-control form-control-color" value="#181CB8">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Latitude <span class="text-danger">*</span></label>
                            <input type="text" name="latitude" id="markerLat" class="form-control" required placeholder="27.7172">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Longitude <span class="text-danger">*</span></label>
                            <input type="text" name="longitude" id="markerLng" class="form-control" required placeholder="85.3240">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" id="markerDesc" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Popup Content (HTML)</label>
                            <textarea name="popup_content" id="markerPopup" class="form-control" rows="2" placeholder="Optional HTML for popup"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Save Marker</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('layerColor').addEventListener('input', function() {
    document.getElementById('layerColorHex').value = this.value;
});
document.getElementById('layerColorHex').addEventListener('input', function() {
    if (/^#[0-9a-f]{6}$/i.test(this.value)) {
        document.getElementById('layerColor').value = this.value;
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
