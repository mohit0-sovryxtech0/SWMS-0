<?php
require_once __DIR__ . '/../../includes/config.php';
$pageTitle = 'Complaint Categories';
$breadcrumbs = [
    ['label' => 'Complaint Management', 'url' => ADMIN_URL . 'complaints/index.php'],
    ['label' => 'Categories']
];
RBAC::requirePermission('complaints.view');

require_once __DIR__ . '/../includes/header.php';

$categories = db()->fetchAll("SELECT * FROM complaint_categories WHERE deleted_at IS NULL ORDER BY name");

// Handle create/edit
if (isPost() && post('action') === 'save') {
    RBAC::requirePermission('complaints.create');
    $csrf = post('csrf_token');
    if (!verify_csrf($csrf)) {
        alert_error('Invalid security token.');
    } else {
        $id = (int) post('id', 0);
        $name = trim(post('name', ''));
        $slug = trim(post('slug', ''));
        $description = trim(post('description', ''));
        $slaHours = (int) post('sla_hours', 24);

        if (empty($name)) {
            alert_error('Category name is required.');
        } else {
            if (empty($slug)) {
                $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
            }

            if ($id > 0) {
                db()->update('complaint_categories', [
                    'name' => $name,
                    'slug' => $slug,
                    'description' => $description ?: null,
                    'sla_hours' => $slaHours,
                ], 'id = :id', ['id' => $id]);
                log_activity(Auth::id(), 'edit', 'complaints', "Updated complaint category: {$name}");
                alert_success('Category updated successfully.');
            } else {
                $exists = db()->fetchColumn("SELECT COUNT(*) FROM complaint_categories WHERE slug = ? AND deleted_at IS NULL", [$slug]);
                if ($exists) {
                    alert_error('A category with this slug already exists.');
                } else {
                    db()->insert('complaint_categories', [
                        'name' => $name,
                        'slug' => $slug,
                        'description' => $description ?: null,
                        'sla_hours' => $slaHours,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                    log_activity(Auth::id(), 'create', 'complaints', "Created complaint category: {$name}");
                    alert_success('Category created successfully.');
                }
            }
            redirect(ADMIN_URL . 'complaints/categories.php');
        }
    }
}

// Handle delete
if (isPost() && post('action') === 'delete') {
    RBAC::requirePermission('complaints.create');
    $csrf = post('csrf_token');
    if (!verify_csrf($csrf)) {
        alert_error('Invalid security token.');
    } else {
        $id = (int) post('id', 0);
        $cat = db()->fetchOne("SELECT id, name FROM complaint_categories WHERE id = ? AND deleted_at IS NULL", [$id]);
        if ($cat) {
            $inUse = db()->fetchColumn("SELECT COUNT(*) FROM complaints WHERE category_id = ? AND deleted_at IS NULL", [$id]);
            if ($inUse > 0) {
                alert_error("Cannot delete category '{$cat['name']}' - it is used by {$inUse} complaint(s).");
            } else {
                db()->softDelete('complaint_categories', $id);
                log_activity(Auth::id(), 'delete', 'complaints', "Deleted complaint category: {$cat['name']}");
                alert_success('Category deleted successfully.');
            }
        }
        redirect(ADMIN_URL . 'complaints/categories.php');
    }
}

$editCat = null;
$editId = (int) get('edit', 0);
if ($editId) {
    $editCat = db()->fetchOne("SELECT * FROM complaint_categories WHERE id = ? AND deleted_at IS NULL", [$editId]);
}
?>
<div class="page-header">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h4>Complaint Categories</h4>
            <p>Manage complaint categories and SLA settings</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= ADMIN_URL ?>complaints/index.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Complaints
            </a>
        </div>
    </div>
</div>

<?= display_alert() ?>

<div class="row">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5><?= $editCat ? 'Edit Category' : 'Add Category' ?></h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save">
                    <?php if ($editCat): ?>
                    <input type="hidden" name="id" value="<?= $editCat['id'] ?>">
                    <?php endif; ?>
                    <div class="form-group mb-3">
                        <label class="form-label">Name <span class="required">*</span></label>
                        <input type="text" name="name" class="form-control" value="<?= escape($editCat['name'] ?? '') ?>" required maxlength="200" id="catName">
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Slug</label>
                        <input type="text" name="slug" class="form-control" value="<?= escape($editCat['slug'] ?? '') ?>" maxlength="200" id="catSlug" placeholder="Auto-generated from name">
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2"><?= escape($editCat['description'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">SLA Hours</label>
                        <input type="number" name="sla_hours" class="form-control" value="<?= (int)($editCat['sla_hours'] ?? 24) ?>" min="1" max="720">
                        <div class="form-text">Target resolution time in hours.</div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-save"></i> <?= $editCat ? 'Update' : 'Save' ?> Category
                    </button>
                    <?php if ($editCat): ?>
                    <a href="<?= ADMIN_URL ?>complaints/categories.php" class="btn btn-outline-secondary w-100 mt-2">Cancel Edit</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><h5>All Categories</h5></div>
            <div class="card-body p-0">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Slug</th>
                            <th>SLA (hrs)</th>
                            <th>Complaints</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($categories)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">No categories found</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($categories as $cat):
                            $count = db()->fetchColumn("SELECT COUNT(*) FROM complaints WHERE category_id = ? AND deleted_at IS NULL", [$cat['id']]);
                        ?>
                        <tr>
                            <td><strong><?= escape($cat['name']) ?></strong></td>
                            <td><code><?= escape($cat['slug']) ?></code></td>
                            <td><?= (int)$cat['sla_hours'] ?></td>
                            <td><span class="badge bg-light text-dark"><?= $count ?></span></td>
                            <td>
                                <div class="table-actions">
                                    <a href="?edit=<?= $cat['id'] ?>" class="btn-action edit" title="Edit"><i class="fas fa-edit"></i></a>
                                    <?php if ($count === 0): ?>
                                    <form method="POST" action="" style="display:inline" onsubmit="return confirm('Delete category \'<?= escape($cat['name']) ?>\'?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                                        <button type="submit" class="btn-action delete border-0 bg-transparent" title="Delete"><i class="fas fa-trash"></i></button>
                                    </form>
                                    <?php endif; ?>
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
</div>

<script>
document.getElementById('catName')?.addEventListener('input', function() {
    var slugField = document.getElementById('catSlug');
    if (!slugField.value || slugField.dataset.auto === 'true') {
        slugField.value = this.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
        slugField.dataset.auto = 'true';
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
