<?php
require_once __DIR__ . '/../../includes/config.php';
$pageTitle = 'Consumer Documents';
$breadcrumbs = [
    ['label' => 'Consumer Management', 'url' => ADMIN_URL . 'consumers/index.php'],
    ['label' => 'Documents']
];
RBAC::requirePermission('consumers.view');

require_once __DIR__ . '/../includes/header.php';

$consumerId = (int) get('consumer_id', 0);
$consumer = db()->fetchOne("SELECT id, consumer_no, full_name FROM consumers WHERE id = ? AND deleted_at IS NULL", [$consumerId]);

if (!$consumer) {
    alert_error('Consumer not found.');
    redirect(ADMIN_URL . 'consumers/index.php');
}

// Handle AJAX requests
if (isAjax() && isPost()) {
    $csrf = post('csrf_token');
    if (!verify_csrf($csrf)) {
        json_error('Invalid security token.');
    }

    $action = post('action', '');

    if ($action === 'upload') {
        RBAC::requirePermission('consumers.edit');

        $title = trim(post('title', ''));
        $documentType = post('document_type', 'other');

        if (empty($title)) {
            json_error('Document title is required.');
        }

        if (empty($_FILES['document_file']['name'])) {
            json_error('Please select a file to upload.');
        }

        $uploadDir = UPLOADS_PATH . 'documents/';
        $filePath = upload_file($_FILES['document_file'], $uploadDir, ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx']);

        if ($filePath === false) {
            json_error('File upload failed. Allowed: jpg, jpeg, png, gif, pdf, doc, docx (max 5MB).');
        }

        db()->insert('consumer_documents', [
            'consumer_id' => $consumerId,
            'title' => $title,
            'document_type' => $documentType,
            'file_path' => $filePath,
            'original_name' => $_FILES['document_file']['name'],
            'file_size' => $_FILES['document_file']['size'],
            'mime_type' => mime_content_type($uploadDir . $filePath) ?: '',
            'uploaded_by' => Auth::id(),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        log_activity(Auth::id(), 'upload', 'documents', "Uploaded document '{$title}' for consumer {$consumer['consumer_no']}", ['consumer_id' => $consumerId]);
        json_success([], 'Document uploaded successfully.');
    }

    if ($action === 'delete') {
        RBAC::requirePermission('consumers.delete');

        $documentId = (int) post('document_id', 0);
        $doc = db()->fetchOne("SELECT * FROM consumer_documents WHERE id = ? AND consumer_id = ? AND deleted_at IS NULL", [$documentId, $consumerId]);

        if (!$doc) {
            json_error('Document not found.');
        }

        $filePath = UPLOADS_PATH . 'documents/' . $doc['file_path'];
        delete_file($filePath);

        db()->softDelete('consumer_documents', $documentId);

        log_activity(Auth::id(), 'delete', 'documents', "Deleted document '{$doc['title']}' for consumer {$consumer['consumer_no']}", ['document_id' => $documentId]);
        json_success([], 'Document deleted successfully.');
    }

    json_error('Invalid action.');
}

$documents = db()->fetchAll("SELECT * FROM consumer_documents WHERE consumer_id = ? AND deleted_at IS NULL ORDER BY created_at DESC", [$consumerId]);
?>
<div class="page-header">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h4>Consumer Documents</h4>
            <p><?= escape($consumer['consumer_no']) ?> &mdash; <?= escape($consumer['full_name']) ?></p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= ADMIN_URL ?>consumers/view.php?id=<?= $consumerId ?>" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Profile
            </a>
        </div>
    </div>
</div>

<?= display_alert() ?>

<div class="row">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h5>Upload Document</h5></div>
            <div class="card-body">
                <form id="documentUploadForm" enctype="multipart/form-data">
                    <input type="hidden" name="consumer_id" value="<?= $consumerId ?>">
                    <input type="hidden" name="action" value="upload">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label class="form-label">Document Title <span class="required">*</span></label>
                        <input type="text" name="title" class="form-control" required maxlength="200">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Document Type</label>
                        <select name="document_type" class="form-select">
                            <option value="citizenship">Citizenship</option>
                            <option value="passport">Passport</option>
                            <option value="tax_receipt">Tax Receipt</option>
                            <option value="property_document">Property Document</option>
                            <option value="agreement">Agreement</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">File <span class="required">*</span></label>
                        <input type="file" name="document_file" class="form-control" required accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx">
                        <div class="form-text">JPG, PNG, PDF, DOC. Max 5MB.</div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100" id="uploadBtn">
                        <i class="fas fa-upload"></i> Upload
                    </button>
                </form>
                <div id="uploadProgress" class="mt-3" style="display:none;">
                    <div class="progress">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" style="width:100%">Uploading...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><h5>All Documents (<?= count($documents) ?>)</h5></div>
            <div class="card-body">
                <?php if (empty($documents)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-file-alt fa-3x mb-3" style="opacity:0.3;"></i>
                    <p>No documents uploaded yet.</p>
                </div>
                <?php else: ?>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th style="width:40px">#</th>
                                <th>Title</th>
                                <th>Type</th>
                                <th>File</th>
                                <th>Size</th>
                                <th>Uploaded</th>
                                <th style="width:80px">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($documents as $i => $doc): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><strong><?= escape($doc['title']) ?></strong></td>
                                <td><span class="badge bg-light text-dark"><?= escape(ucfirst(str_replace('_', ' ', $doc['document_type']))) ?></span></td>
                                <td>
                                    <a href="<?= UPLOAD_URL ?>documents/<?= escape($doc['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                </td>
                                <td style="font-size:12px;color:var(--text-muted);"><?= $doc['file_size'] ? round($doc['file_size'] / 1024, 1) . ' KB' : '-' ?></td>
                                <td style="font-size:12px;color:var(--text-muted);"><?= format_datetime($doc['created_at']) ?></td>
                                <td>
                                    <?php if (RBAC::can('consumers.delete')): ?>
                                    <button type="button" class="btn-action delete" title="Delete" onclick="deleteDoc(<?= $doc['id'] ?>)"><i class="fas fa-trash"></i></button>
                                    <?php endif; ?>
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
</div>

<script>
function deleteDoc(docId) {
    if (!confirm('Are you sure you want to delete this document?')) return;
    var formData = new FormData();
    formData.append('action', 'delete');
    formData.append('document_id', docId);
    formData.append('csrf_token', '<?= csrf_token() ?>');

    fetch(window.location.href, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) { location.reload(); }
        else { alert(d.message || 'Delete failed'); }
    })
    .catch(() => alert('Network error'));
}

document.addEventListener('DOMContentLoaded', function() {
    var uploadForm = document.getElementById('documentUploadForm');
    if (uploadForm) {
        uploadForm.addEventListener('submit', function(e) {
            e.preventDefault();
            var btn = document.getElementById('uploadBtn');
            var progress = document.getElementById('uploadProgress');
            btn.disabled = true;
            progress.style.display = 'block';

            var formData = new FormData(this);
            fetch(window.location.href, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(r => r.json())
            .then(d => {
                progress.style.display = 'none';
                btn.disabled = false;
                if (d.success) { location.reload(); }
                else { alert(d.message || 'Upload failed'); }
            })
            .catch(() => {
                progress.style.display = 'none';
                btn.disabled = false;
                alert('Network error');
            });
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
