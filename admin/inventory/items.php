<?php
require_once __DIR__ . '/../../includes/config.php';
$pageTitle = 'Inventory Items';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => ADMIN_URL . 'dashboard/index.php'],
    ['label' => 'Inventory', 'url' => ADMIN_URL . 'inventory/index.php'],
    ['label' => 'Items']
];
RBAC::requirePermission('inventory.items');

$categories = ['pipe', 'valve', 'fitting', 'meter', 'pump', 'chemical', 'tool', 'safety_equipment', 'office_supply', 'other'];
$units = ['pcs', 'mtr', 'kg', 'ltr', 'bag', 'roll', 'box', 'set', 'pair', 'dozen', 'packet'];

$extraCss = '<style>
table.dataTable tbody tr.low-stock td { background-color: #fff5f5 !important; }
table.dataTable tbody tr.low-stock td:first-child { border-left: 3px solid #dc3545; }
table.dataTable tbody tr.out-of-stock td { background-color: #f8d7da !important; text-decoration: line-through; opacity: 0.7; }
</style>';

$ajaxUrl = ADMIN_URL . 'inventory/items.php';
$csrfToken = csrf_token();

// Handle AJAX before HTML output
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
            $columns = ['item_code', 'name', 'category', 'unit', 'current_stock', 'reorder_level', 'status'];
            $orderBy = $columns[$orderColumn] ?? 'name';

            $where = "WHERE deleted_at IS NULL";
            $params = [];

            if (!empty($search)) {
                $where .= " AND (item_code LIKE :search OR name LIKE :search2 OR category LIKE :search3)";
                $params['search'] = "%{$search}%";
                $params['search2'] = "%{$search}%";
                $params['search3'] = "%{$search}%";
            }

            $total = db()->fetchColumn("SELECT COUNT(*) FROM inventory_items {$where}", $params);

            $sql = "SELECT * FROM inventory_items {$where} ORDER BY {$orderBy} {$orderDir} LIMIT {$start}, {$length}";
            $rows = db()->fetchAll($sql, $params);

            $data = [];
            foreach ($rows as $row) {
                $catLabel = ucfirst(str_replace('_', ' ', $row['category']));
                $stock = (float)$row['current_stock'];
                $reorder = (float)$row['reorder_level'];
                $stockBadge = $stock <= 0
                    ? '<span class="badge bg-danger">Out of Stock</span>'
                    : ($stock <= $reorder
                        ? '<span class="badge bg-warning text-dark">Low</span>'
                        : '<span class="badge bg-success">In Stock</span>');

                $data[] = [
                    'item_code' => '<span class="fw-semibold">' . escape($row['item_code']) . '</span>',
                    'name' => escape($row['name']),
                    'category' => '<span class="badge bg-secondary">' . $catLabel . '</span>',
                    'unit' => escape($row['unit']),
                    'current_stock' => '<strong>' . number_format($stock) . '</strong>',
                    'reorder_level' => number_format($reorder),
                    'status' => get_status_badge($row['status']),
                    'action' => '<div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-info btn-edit" data-id="' . $row['id'] . '" title="Edit"><i class="fas fa-edit"></i></button>
                        <button type="button" class="btn btn-danger btn-delete" data-id="' . $row['id'] . '" title="Delete"><i class="fas fa-trash"></i></button>
                    </div>'
                ];
            }

            json_response([
                'draw' => $draw,
                'recordsTotal' => intval($total),
                'recordsFiltered' => intval($total),
                'data' => $data
            ]);
        }

        if ($action === 'create') {
            RBAC::requirePermission('inventory.items');
            $v = validator(post(), [
                'item_code' => 'required|unique:inventory_items,item_code',
                'name' => 'required|min:2|max:200',
                'category' => 'required',
                'unit' => 'required',
                'reorder_level' => 'numeric',
                'current_stock' => 'numeric',
                'min_stock' => 'numeric',
                'max_stock' => 'numeric',
                'unit_price' => 'numeric',
            ]);

            if ($v->fails()) {
                json_error('Validation failed', $v->errors());
            }

            $itemId = db()->insert('inventory_items', [
                'item_code' => post('item_code'),
                'name' => post('name'),
                'description' => post('description'),
                'category' => post('category'),
                'unit' => post('unit'),
                'unit_price' => (float)post('unit_price', 0),
                'reorder_level' => (int)post('reorder_level', 10),
                'current_stock' => (float)post('current_stock', 0),
                'min_stock' => (float)post('min_stock', 0),
                'max_stock' => (float)post('max_stock', 0),
                'location' => post('location'),
                'status' => post('status', 'active'),
                'created_at' => date('Y-m-d H:i:s')
            ]);

            log_activity(Auth::id(), 'create', 'Inventory', 'Created item: ' . post('item_code'));
            json_success(['id' => $itemId], 'Item created successfully');
        }

        if ($action === 'update') {
            RBAC::requirePermission('inventory.items');
            $id = (int)get('id');
            if (!$id) json_error('Invalid item ID');

            $v = validator(post(), [
                'item_code' => 'required|unique:inventory_items,item_code,' . $id,
                'name' => 'required|min:2|max:200',
                'category' => 'required',
                'unit' => 'required',
            ]);

            if ($v->fails()) {
                json_error('Validation failed', $v->errors());
            }

            db()->update('inventory_items', [
                'item_code' => post('item_code'),
                'name' => post('name'),
                'description' => post('description'),
                'category' => post('category'),
                'unit' => post('unit'),
                'unit_price' => (float)post('unit_price', 0),
                'reorder_level' => (int)post('reorder_level', 10),
                'current_stock' => (float)post('current_stock', 0),
                'min_stock' => (float)post('min_stock', 0),
                'max_stock' => (float)post('max_stock', 0),
                'location' => post('location'),
                'status' => post('status', 'active'),
            ], 'id = :id', ['id' => $id]);

            log_activity(Auth::id(), 'update', 'Inventory', 'Updated item: ' . post('item_code'));
            json_success([], 'Item updated successfully');
        }

        if ($action === 'delete') {
            RBAC::requirePermission('inventory.items');
            $id = (int)get('id');
            if (!$id) json_error('Invalid item ID');

            db()->softDelete('inventory_items', $id);
            log_activity(Auth::id(), 'delete', 'Inventory', 'Deleted item ID: ' . $id);
            json_success([], 'Item deleted successfully');
        }

        if ($action === 'edit') {
            $id = (int)get('id');
            $item = db()->fetchOne("SELECT * FROM inventory_items WHERE id = ? AND deleted_at IS NULL", [$id]);
            if (!$item) json_error('Item not found');
            json_success($item);
        }
    } catch (Exception $e) {
        json_error($e->getMessage());
    }
}

$extraJs = <<<JS
<script>
\$(document).ready(function() {
    var table = \$('#itemsTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{$ajaxUrl}',
            type: 'POST',
            data: function(d) {
                d.action = 'getData';
                d.csrf_token = '{$csrfToken}';
            }
        },
        columns: [
            { data: 'item_code' },
            { data: 'name' },
            { data: 'category' },
            { data: 'unit' },
            { data: 'current_stock' },
            { data: 'reorder_level' },
            { data: 'status' },
            { data: 'action', orderable: false, searchable: false }
        ],
        order: [[1, 'asc']],
        pageLength: 25,
        language: { searchPlaceholder: 'Search items...' },
        createdRow: function(row, data, dataIndex) {
            if (parseFloat(data.current_stock) <= 0) {
                \$(row).addClass('out-of-stock');
            } else if (parseFloat(data.current_stock) <= parseFloat(data.reorder_level)) {
                \$(row).addClass('low-stock');
            }
        }
    });

    \$('#itemForm').on('submit', function(e) {
        e.preventDefault();
        var form = \$(this);
        var id = \$('#itemId').val();
        var url = id ? '{$ajaxUrl}?action=update&id=' + id : '{$ajaxUrl}?action=create';
        \$.ajax({
            url: url,
            type: 'POST',
            data: form.serialize(),
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    \$('#itemModal').modal('hide');
                    table.ajax.reload();
                    showToast('success', res.message);
                } else {
                    showToast('error', res.message);
                }
            },
            error: function(xhr) {
                var msg = 'Request failed';
                try {
                    var res = JSON.parse(xhr.responseText);
                    if (res.message) msg = res.message;
                } catch(e) {}
                showToast('error', msg);
            }
        });
    });

    \$('#itemsTable').on('click', '.btn-edit', function() {
        var id = \$(this).data('id');
        \$.ajax({
            url: '{$ajaxUrl}?action=edit&id=' + id,
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                if (data.success) {
                    var item = data.data;
                    \$('#itemId').val(item.id);
                    \$('#itemCode').val(item.item_code);
                    \$('#itemName').val(item.name);
                    \$('#itemDesc').val(item.description);
                    \$('#itemCategory').val(item.category);
                    \$('#itemUnit').val(item.unit);
                    \$('#itemUnitPrice').val(item.unit_price);
                    \$('#itemReorderLevel').val(item.reorder_level);
                    \$('#itemCurrentStock').val(item.current_stock);
                    \$('#itemMinStock').val(item.min_stock);
                    \$('#itemMaxStock').val(item.max_stock);
                    \$('#itemLocation').val(item.location);
                    \$('#itemStatus').val(item.status);
                    \$('#itemModalTitle').text('Edit Item');
                    \$('#itemModal').modal('show');
                }
            },
            error: function(xhr) {
                var msg = 'Failed to load item';
                try { var res = JSON.parse(xhr.responseText); if (res.message) msg = res.message; } catch(e) {}
                showToast('error', msg);
            }
        });
    });

    \$('.btn-new-item').click(function() {
        \$('#itemForm')[0].reset();
        \$('#itemId').val('');
        \$('#itemCurrentStock').val(0);
        \$('#itemReorderLevel').val(10);
        \$('#itemStatus').val('active');
        \$('#itemModalTitle').text('Add New Item');
        \$('#itemModal').modal('show');
    });

    \$('#itemsTable').on('click', '.btn-delete', function() {
        var id = \$(this).data('id');
        if (confirm('Are you sure you want to delete this item?')) {
            \$.ajax({
                url: '{$ajaxUrl}?action=delete&id=' + id,
                type: 'POST',
                data: {csrf_token: '{$csrfToken}'},
                dataType: 'json',
                success: function(res) {
                    if (res.success) {
                        table.ajax.reload();
                        showToast('success', res.message);
                    } else {
                        showToast('error', res.message);
                    }
                },
                error: function(xhr) {
                    var msg = 'Delete failed';
                    try { var res = JSON.parse(xhr.responseText); if (res.message) msg = res.message; } catch(e) {}
                    showToast('error', msg);
                }
            });
        }
    });
});
</script>
JS;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <h4 class="mb-0"><i class="fas fa-box me-2 text-primary"></i>Inventory Items</h4>
        <div>
            <button type="button" class="btn btn-primary btn-sm btn-new-item">
                <i class="fas fa-plus me-1"></i>New Item
            </button>
            <a href="<?= ADMIN_URL ?>inventory/stock-report.php" class="btn btn-info btn-sm">
                <i class="fas fa-file-alt me-1"></i>Stock Report
            </a>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table id="itemsTable" class="table table-hover table-bordered w-100">
                <thead class="table-light">
                    <tr>
                        <th>Item Code</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Unit</th>
                        <th>Current Stock</th>
                        <th>Reorder Level</th>
                        <th>Status</th>
                        <th class="no-sort" style="width:80px">Actions</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>

<!-- Item Modal -->
<div class="modal fade" id="itemModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="itemForm" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="id" id="itemId">
                <div class="modal-header">
                    <h5 class="modal-title" id="itemModalTitle"><i class="fas fa-box me-2 text-primary"></i>Add New Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Item Code <span class="text-danger">*</span></label>
                            <input type="text" name="item_code" id="itemCode" class="form-control" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="itemName" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Category <span class="text-danger">*</span></label>
                            <select name="category" id="itemCategory" class="form-select" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat ?>"><?= ucfirst(str_replace('_', ' ', $cat)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Unit <span class="text-danger">*</span></label>
                            <select name="unit" id="itemUnit" class="form-select" required>
                                <option value="">Select Unit</option>
                                <?php foreach ($units as $u): ?>
                                <option value="<?= $u ?>"><?= strtoupper($u) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Unit Price</label>
                            <input type="number" step="0.01" min="0" name="unit_price" id="itemUnitPrice" class="form-control" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Current Stock</label>
                            <input type="number" step="0.01" min="0" name="current_stock" id="itemCurrentStock" class="form-control" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Reorder Level</label>
                            <input type="number" step="0.01" min="0" name="reorder_level" id="itemReorderLevel" class="form-control" value="10">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Max Stock</label>
                            <input type="number" step="0.01" min="0" name="max_stock" id="itemMaxStock" class="form-control" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Min Stock</label>
                            <input type="number" step="0.01" min="0" name="min_stock" id="itemMinStock" class="form-control" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <select name="status" id="itemStatus" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Location / Storage</label>
                            <input type="text" name="location" id="itemLocation" class="form-control" placeholder="e.g. Store Room A, Shelf 3">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" id="itemDesc" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once ADMIN_PATH . 'includes/footer.php'; ?>
