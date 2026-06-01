<?php
require_once __DIR__ . '/../../includes/config.php';
$pageTitle = 'Asset Categories';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => ADMIN_URL . 'dashboard/index.php'],
    ['label' => 'Assets', 'url' => ADMIN_URL . 'assets-mgmt/index.php'],
    ['label' => 'Categories']
];
RBAC::requirePermission('assets.view');

require_once __DIR__ . '/../includes/header.php';

$extraJs = <<<'JS'
<script>
$(document).ready(function() {
    var table = $('#categoriesTable').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: '${ADMIN_URL}assets-mgmt/categories.php?action=getData',
            type: 'POST',
            data: function(d) { d.csrf_token = '${csrf_token()}'; }
        },
        columns: [
            { data: 'name' },
            { data: 'slug' },
            { data: 'description' },
            { data: 'assets_count' },
            { data: 'action', orderable: false, searchable: false }
        ],
        order: [[0, 'asc']],
        pageLength: 25
    });

    $('#categoryForm').on('submit', function(e) {
        e.preventDefault();
        var form = $(this);
        var id = $('#catId').val();
        var url = id ? '${ADMIN_URL}assets-mgmt/categories.php?action=update&id=' + id : '${ADMIN_URL}assets-mgmt/categories.php?action=create';
        $.post(url, form.serialize(), function(res) {
            if (res.success) {
                $('#categoryModal').modal('hide');
                table.ajax.reload();
                showToast('success', res.message);
            } else {
                showToast('error', res.message);
            }
        });
    });

    $('#categoriesTable').on('click', '.btn-edit', function() {
        var id = $(this).data('id');
        $.get('${ADMIN_URL}assets-mgmt/categories.php?action=edit&id=' + id, function(res) {
            if (res.success) {
                var c = res.data;
                $('#catId').val(c.id);
                $('#catName').val(c.name);
                $('#catSlug').val(c.slug);
                $('#catDesc').val(c.description);
                $('#categoryModalTitle').text('Edit Category');
                $('#categoryModal').modal('show');
            }
        });
    });

    $('.btn-new-category').click(function() {
        $('#categoryForm')[0].reset();
        $('#catId').val('');
        $('#categoryModalTitle').text('Add New Category');
        $('#categoryModal').modal('show');
    });

    $('#categoriesTable').on('click', '.btn-delete', function() {
        var id = $(this).data('id');
        if (confirm('Delete this category? Assets in this category will become uncategorized.')) {
            $.post('${ADMIN_URL}assets-mgmt/categories.php?action=delete&id=' + id, {csrf_token: '${csrf_token()}'}, function(res) {
                if (res.success) { table.ajax.reload(); showToast('success', res.message); }
                else { showToast('error', res.message); }
            });
        }
    });

    $('#catName').on('input', function() {
        if (!$('#catId').val()) {
            $('#catSlug').val($(this).val().toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-\$/g, ''));
        }
    });
});
</script>
JS;

if (isPost()) {
    $action = get('action');
    try {
        if (!verify_csrf(post('csrf_token'))) {
            throw new Exception('Security validation failed');
        }

        if ($action === 'getData') {
            $rows = db()->fetchAll(
                "SELECT c.*, (SELECT COUNT(*) FROM assets a WHERE a.category_id = c.id AND a.deleted_at IS NULL) AS assets_count
                 FROM asset_categories c WHERE c.deleted_at IS NULL ORDER BY c.name"
            );
            $data = [];
            foreach ($rows as $r) {
                $data[] = [
                    'name' => '<strong>' . escape($r['name']) . '</strong>',
                    'slug' => '<code>' . escape($r['slug']) . '</code>',
                    'description' => escape(truncate($r['description'] ?? '-', 60)),
                    'assets_count' => '<span class="badge bg-primary">' . $r['assets_count'] . '</span>',
                    'action' => '<div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-info btn-edit" data-id="' . $r['id'] . '"><i class="fas fa-edit"></i></button>
                        <button type="button" class="btn btn-danger btn-delete" data-id="' . $r['id'] . '"><i class="fas fa-trash"></i></button>
                    </div>'
                ];
            }
            json_response(['data' => $data]);
        }

        if ($action === 'create') {
            $slug = post('slug');
            if (empty($slug)) {
                $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', trim(post('name'))));
                $slug = trim($slug, '-');
            }
            $v = validator(post(), ['name' => 'required|min:2|max:200']);
            if ($v->fails()) json_error('Validation failed', $v->errors());

            if (db()->exists('asset_categories', 'slug = :s AND deleted_at IS NULL', ['s' => $slug])) {
                json_error('Slug already exists');
            }

            $id = db()->insert('asset_categories', [
                'name' => post('name'), 'slug' => $slug,
                'description' => post('description'),
                'created_at' => date('Y-m-d H:i:s')
            ]);
            log_activity(Auth::id(), 'create', 'Assets', 'Created category: ' . post('name'));
            json_success(['id' => $id], 'Category created');
        }

        if ($action === 'update') {
            $id = (int)get('id');
            if (!$id) json_error('Invalid ID');
            $v = validator(post(), ['name' => 'required|min:2|max:200']);
            if ($v->fails()) json_error('Validation failed', $v->errors());

            db()->update('asset_categories', [
                'name' => post('name'), 'slug' => post('slug'),
                'description' => post('description'),
            ], 'id = :id', ['id' => $id]);
            log_activity(Auth::id(), 'update', 'Assets', 'Updated category: ' . post('name'));
            json_success([], 'Category updated');
        }

        if ($action === 'delete') {
            $id = (int)get('id');
            if (!$id) json_error('Invalid ID');
            db()->softDelete('asset_categories', $id);
            log_activity(Auth::id(), 'delete', 'Assets', 'Deleted category ID: ' . $id);
            json_success([], 'Category deleted');
        }

        if ($action === 'edit') {
            $id = (int)get('id');
            $c = db()->fetchOne("SELECT * FROM asset_categories WHERE id = ? AND deleted_at IS NULL", [$id]);
            if (!$c) json_error('Not found');
            json_success($c);
        }
    } catch (Exception $e) {
        json_error($e->getMessage());
    }
}
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <h4 class="mb-0"><i class="fas fa-tags me-2 text-primary"></i>Asset Categories</h4>
        <button type="button" class="btn btn-primary btn-sm btn-new-category">
            <i class="fas fa-plus me-1"></i>New Category
        </button>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table id="categoriesTable" class="table table-hover table-bordered w-100">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Description</th>
                        <th>Assets</th>
                        <th class="no-sort" style="width:80px">Actions</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="categoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="categoryForm" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="id" id="catId">
                <div class="modal-header">
                    <h5 class="modal-title" id="categoryModalTitle">Add Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="catName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Slug</label>
                        <input type="text" name="slug" id="catSlug" class="form-control">
                        <div class="form-text">Auto-generated from name. Used in URLs.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="catDesc" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once ADMIN_PATH . 'includes/footer.php'; ?>
