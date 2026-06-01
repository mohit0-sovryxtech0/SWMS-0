<?php
require_once __DIR__ . '/../../includes/config.php';
$pageTitle = 'Notification Templates';
$breadcrumbs = [
    ['label' => 'Notification Management', 'url' => ADMIN_URL . 'notifications/index.php'],
    ['label' => 'Templates']
];
RBAC::requirePermission('settings.view');

require_once __DIR__ . '/../includes/header.php';

$isEditing = false;
$editTemplate = null;

if (isPost() && isAjax()) {
    $csrf = post('csrf_token');
    if (!verify_csrf($csrf)) json_error('Invalid security token.');

    $action = post('action', '');

    if ($action === 'save_template') {
        $id = (int) post('id', 0);
        $name = trim(post('name', ''));
        $slug = trim(post('slug', ''));
        $type = post('type', 'email');
        $subject = trim(post('subject', ''));
        $body = trim(post('body', ''));
        $isActive = (int) post('is_active', 1);

        if (empty($name)) json_error('Template name is required.');
        if (empty($body)) json_error('Template body is required.');

        $slug = $slug ?: preg_replace('/[^a-z0-9_]+/', '_', strtolower($name));

        if ($id > 0) {
            db()->update('notification_templates', [
                'name' => $name,
                'slug' => $slug,
                'type' => $type,
                'subject' => $subject,
                'body' => $body,
                'is_active' => $isActive,
            ], 'id = :id', ['id' => $id]);
            log_activity(Auth::id(), 'update', 'notifications', "Updated template: {$name}");
            json_success([], 'Template updated successfully.');
        } else {
            $existing = db()->fetchColumn("SELECT COUNT(*) FROM notification_templates WHERE slug = :slug AND deleted_at IS NULL", ['slug' => $slug]);
            if ($existing) json_error('A template with this slug already exists.');
            db()->insert('notification_templates', [
                'name' => $name,
                'slug' => $slug,
                'type' => $type,
                'subject' => $subject,
                'body' => $body,
                'is_active' => $isActive,
                'created_by' => Auth::id(),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            log_activity(Auth::id(), 'create', 'notifications', "Created template: {$name}");
            json_success([], 'Template created successfully.');
        }
    }

    if ($action === 'delete_template') {
        $id = (int) post('id', 0);
        $tpl = db()->fetchOne("SELECT * FROM notification_templates WHERE id = ? AND deleted_at IS NULL", [$id]);
        if (!$tpl) json_error('Template not found.');
        db()->softDelete('notification_templates', $id);
        log_activity(Auth::id(), 'delete', 'notifications', "Deleted template: {$tpl['name']}");
        json_success([], 'Template deleted successfully.');
    }

    json_error('Invalid action.');
}

$editId = (int) get('edit', 0);
if ($editId) {
    $editTemplate = db()->fetchOne("SELECT * FROM notification_templates WHERE id = ? AND deleted_at IS NULL", [$editId]);
    if ($editTemplate) $isEditing = true;
}

$templates = db()->fetchAll("SELECT * FROM notification_templates WHERE deleted_at IS NULL ORDER BY name");
$variableHelp = [
    'consumer_name' => 'Consumer full name',
    'consumer_no' => 'Consumer number',
    'bill_no' => 'Bill number',
    'amount' => 'Amount due/paid',
    'due_date' => 'Due date',
    'payment_date' => 'Payment date',
    'receipt_no' => 'Receipt number',
    'ticket_no' => 'Complaint ticket number',
    'subject' => 'Complaint subject',
    'resolution' => 'Resolution notes',
    'disconnection_date' => 'Disconnection date',
    'overdue_days' => 'Days overdue',
    'app_name' => 'Application name',
];
?>
<div class="page-header">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h4>Notification Templates</h4>
            <p>Pre-defined templates with placeholder variables</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= ADMIN_URL ?>notifications/index.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Notifications
            </a>
            <button type="button" class="btn btn-primary btn-sm" onclick="openCreate()">
                <i class="fas fa-plus"></i> New Template
            </button>
        </div>
    </div>
</div>

<?= display_alert() ?>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><h5>All Templates (<?= count($templates) ?>)</h5></div>
            <div class="card-body">
                <?php if (empty($templates)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-file-alt fa-3x mb-3" style="opacity:0.3;"></i>
                    <p>No templates defined yet.</p>
                </div>
                <?php else: ?>
                <div class="table-container">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Slug</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Updated</th>
                                <th style="width:100px">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($templates as $t): ?>
                            <tr>
                                <td><strong><?= escape($t['name']) ?></strong></td>
                                <td><code><?= escape($t['slug']) ?></code></td>
                                <td><span class="badge bg-secondary"><?= ucfirst($t['type']) ?></span></td>
                                <td><?= $t['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></td>
                                <td class="small text-muted"><?= format_datetime($t['updated_at']) ?></td>
                                <td>
                                    <button type="button" class="btn-action edit" title="Edit" onclick="openEdit(<?= $t['id'] ?>)"><i class="fas fa-edit"></i></button>
                                    <button type="button" class="btn-action delete" title="Delete" onclick="deleteTpl(<?= $t['id'] ?>)"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h5>Available Variables</h5></div>
            <div class="card-body">
                <p class="small text-muted mb-2">Use these placeholders in templates:</p>
                <div class="table-responsive">
                    <table class="table table-sm small">
                        <thead>
                            <tr><th>Variable</th><th>Description</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($variableHelp as $var => $desc): ?>
                            <tr>
                                <td><code>{<?= $var ?>}</code></td>
                                <td class="text-muted"><?= escape($desc) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="small text-muted mt-2 mb-0">
                                    <i class="fas fa-info-circle"></i> Variables are replaced dynamically when sending notifications.
                </p>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="templateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="templateForm">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="save_template">
                <input type="hidden" name="id" id="tplId" value="0">
                <div class="modal-header">
                    <h5 class="modal-title" id="tplModalTitle">Create Template</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label class="form-label">Name <span class="required">*</span></label>
                                <input type="text" name="name" id="tplName" class="form-control" required maxlength="200" oninput="autoSlug(this)">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label class="form-label">Slug</label>
                                <input type="text" name="slug" id="tplSlug" class="form-control" maxlength="200">
                                <div class="form-text">Auto-generated from name. Used for programmatic reference.</div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label class="form-label">Type</label>
                                <select name="type" id="tplType" class="form-select">
                                    <option value="both">Both (Email & SMS)</option>
                                    <option value="email">Email</option>
                                    <option value="sms">SMS</option>
                                    <option value="system">System</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label class="form-label">Status</label>
                                <select name="is_active" id="tplActive" class="form-select">
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Subject (for email)</label>
                        <input type="text" name="subject" id="tplSubject" class="form-control" maxlength="300">
                        <div class="form-text">Use variables like <code>{consumer_name}</code></div>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Body <span class="required">*</span></label>
                        <textarea name="body" id="tplBody" class="form-control" rows="8" required></textarea>
                        <div class="form-text">Use variables like <code>{consumer_name}</code>, <code>{amount}</code>, <code>{due_date}</code></div>
                    </div>
                    <div class="bg-light p-2 rounded small">
                        <strong>Quick insert:</strong>
                        <?php foreach (array_keys($variableHelp) as $var): ?>
                            <a href="javascript:void(0)" class="badge bg-primary me-1" onclick="insertVar('{<?= $var ?>}')" style="cursor:pointer;text-decoration:none;">{<?= $var ?>}</a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="tplSaveBtn"><i class="fas fa-save"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php $extraJs = <<<'JS'
<script>
var templateData = {};
function autoSlug(el) {
    var slug = el.value.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
    document.getElementById('tplSlug').value = slug;
}
function insertVar(v) {
    var ta = document.getElementById('tplBody');
    var start = ta.selectionStart, end = ta.selectionEnd;
    ta.value = ta.value.substring(0, start) + v + ta.value.substring(end);
    ta.selectionStart = ta.selectionEnd = start + v.length;
    ta.focus();
}

function openCreate() {
    document.getElementById('tplModalTitle').textContent = 'Create Template';
    document.getElementById('tplId').value = 0;
    document.getElementById('tplName').value = '';
    document.getElementById('tplSlug').value = '';
    document.getElementById('tplType').value = 'email';
    document.getElementById('tplActive').value = '1';
    document.getElementById('tplSubject').value = '';
    document.getElementById('tplBody').value = '';
    var modal = new bootstrap.Modal(document.getElementById('templateModal'));
    modal.show();
}

function openEdit(id) {
    $.get('?edit=' + id + '&ajax=1', function(r) {
        if (r.success && r.data) {
            var d = r.data;
            document.getElementById('tplModalTitle').textContent = 'Edit Template';
            document.getElementById('tplId').value = d.id;
            document.getElementById('tplName').value = d.name;
            document.getElementById('tplSlug').value = d.slug;
            document.getElementById('tplType').value = d.type;
            document.getElementById('tplActive').value = d.is_active;
            document.getElementById('tplSubject').value = d.subject || '';
            document.getElementById('tplBody').value = d.body;
            var modal = new bootstrap.Modal(document.getElementById('templateModal'));
            modal.show();
        } else {
            alert('Could not load template');
        }
    }, 'json').fail(function() { alert('Network error'); });
}

function deleteTpl(id) {
    if (!confirm('Delete this template?')) return;
    var fd = new FormData();
    fd.append('action', 'delete_template');
    fd.append('id', id);
    fd.append('csrf_token', '<?= csrf_token() ?>');
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: fd
    }).then(r => r.json()).then(d => {
        if (d.success) { location.reload(); }
        else { alert(d.message || 'Delete failed'); }
    }).catch(function() { alert('Network error'); });
}

document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('templateForm').addEventListener('submit', function(e) {
        e.preventDefault();
        var btn = document.getElementById('tplSaveBtn');
        btn.disabled = true;
        var fd = new FormData(this);
        fetch(window.location.href, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        }).then(r => r.json()).then(d => {
            btn.disabled = false;
            if (d.success) { location.reload(); }
            else { alert(d.message || 'Save failed'); }
        }).catch(function() {
            btn.disabled = false;
            alert('Network error');
        });
    });
});
</script>
JS;
?>

<?php
// AJAX: fetch template data for editing
if (isAjax() && isGet() && isset($_GET['edit'])) {
    $eid = (int) $_GET['edit'];
    if (isset($_GET['ajax'])) {
        $tpl = db()->fetchOne("SELECT * FROM notification_templates WHERE id = ? AND deleted_at IS NULL", [$eid]);
        if ($tpl) {
            json_success($tpl);
        }
        json_error('Not found');
    }
}

require_once __DIR__ . '/../includes/footer.php';
?>
