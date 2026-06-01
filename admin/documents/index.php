<?php
require_once __DIR__ . '/../../includes/config.php';
$pageTitle = 'Document Management';
$breadcrumbs = [['label' => 'Document Management']];
RBAC::requirePermission('consumers.view');
require_once __DIR__ . '/../includes/header.php';

$documentTypes = ['citizenship', 'land_ownership', 'agreement', 'photo', 'application', 'bill_copy', 'other'];

// Handle AJAX requests
if (isAjax() && isPost()) {
    $csrf = post('csrf_token');
    if (!verify_csrf($csrf)) json_error('Invalid security token.');

    $action = post('action', '');

    if ($action === 'upload') {
        RBAC::requirePermission('consumers.edit');

        $consumerId = (int) post('consumer_id', 0);
        $docType = post('document_type', 'other');
        $remarks = trim(post('remarks', ''));

        if (!$consumerId) json_error('Please select a consumer.');
        if (empty($_FILES['document_file']['name'])) json_error('Please select a file.');

        $consumer = db()->fetchOne("SELECT id, consumer_no, full_name FROM consumers WHERE id = ? AND deleted_at IS NULL", [$consumerId]);
        if (!$consumer) json_error('Consumer not found.');

        $uploadDir = UPLOADS_PATH . 'documents/';
        $fileInfo = upload_document($_FILES['document_file'], $uploadDir);

        if ($fileInfo === false) {
            json_error('File upload failed. Allowed: jpg, jpeg, png, gif, pdf, doc, docx (max 10MB).');
        }

        db()->insert('consumer_documents', [
            'consumer_id' => $consumerId,
            'document_type' => $docType,
            'title' => $fileInfo['original_name'],
            'file_path' => $fileInfo['file_name'],
            'file_size' => $fileInfo['file_size'],
            'mime_type' => $fileInfo['mime_type'],
            'original_name' => $fileInfo['original_name'],
            'remarks' => $remarks,
            'uploaded_by' => Auth::id(),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        log_activity(Auth::id(), 'upload', 'documents',
            "Uploaded document for consumer {$consumer['consumer_no']}: {$fileInfo['original_name']}",
            ['consumer_id' => $consumerId]
        );
        json_success([], 'Document uploaded successfully.');
    }

    if ($action === 'delete') {
        RBAC::requirePermission('consumers.delete');

        $docId = (int) post('document_id', 0);
        $doc = db()->fetchOne("SELECT cd.*, c.consumer_no FROM consumer_documents cd JOIN consumers c ON cd.consumer_id = c.id WHERE cd.id = ? AND cd.deleted_at IS NULL", [$docId]);

        if (!$doc) json_error('Document not found.');

        $filePath = UPLOADS_PATH . 'documents/' . $doc['file_path'];
        delete_file($filePath);

        db()->softDelete('consumer_documents', $docId);

        log_activity(Auth::id(), 'delete', 'documents',
            "Deleted document ID {$docId} for consumer {$doc['consumer_no']}",
            ['document_id' => $docId]
        );
        json_success([], 'Document deleted successfully.');
    }

    json_error('Invalid action.');
}

$typeFilter = get('document_type', '');
$consumerSearch = trim(get('consumer_search', ''));
$dateFrom = get('date_from', '');
$dateTo = get('date_to', '');
$search = trim(get('search', ''));

$where = "WHERE cd.deleted_at IS NULL";
$params = [];

if ($typeFilter !== '') {
    $where .= " AND cd.document_type = :doc_type";
    $params['doc_type'] = $typeFilter;
}
if ($consumerSearch !== '') {
    $where .= " AND (c.full_name LIKE :cname OR c.consumer_no LIKE :cno)";
    $params['cname'] = "%{$consumerSearch}%";
    $params['cno'] = "%{$consumerSearch}%";
}
if ($dateFrom !== '') {
    $where .= " AND DATE(cd.created_at) >= :date_from";
    $params['date_from'] = $dateFrom;
}
if ($dateTo !== '') {
    $where .= " AND DATE(cd.created_at) <= :date_to";
    $params['date_to'] = $dateTo;
}
if ($search !== '') {
    $where .= " AND (cd.title LIKE :search OR cd.original_name LIKE :search3 OR c.full_name LIKE :search2)";
    $params['search3'] = "%{$search}%";
    $params['search'] = "%{$search}%";
    $params['search2'] = "%{$search}%";
}

$page = max(1, (int) get('page', 1));
$perPage = RECORDS_PER_PAGE;
$offset = ($page - 1) * $perPage;

$total = db()->fetchColumn(
    "SELECT COUNT(*) FROM consumer_documents cd
     JOIN consumers c ON cd.consumer_id = c.id
     {$where}", $params
);

$documents = db()->fetchAll(
    "SELECT cd.*, c.consumer_no, c.full_name as consumer_name, u.name as uploaded_by_name
     FROM consumer_documents cd
     JOIN consumers c ON cd.consumer_id = c.id
     LEFT JOIN users u ON cd.uploaded_by = u.id
     {$where}
     ORDER BY cd.created_at DESC
     LIMIT {$perPage} OFFSET {$offset}",
    $params
);

$totalPages = ceil($total / $perPage);
$paginationUrl = ADMIN_URL . "documents/index.php?page={page}" .
    ($typeFilter ? "&document_type={$typeFilter}" : "") .
    ($consumerSearch ? "&consumer_search=" . urlencode($consumerSearch) : "") .
    ($dateFrom ? "&date_from={$dateFrom}" : "") .
    ($dateTo ? "&date_to={$dateTo}" : "") .
    ($search ? "&search=" . urlencode($search) : "");

$allConsumers = db()->fetchAll("SELECT id, consumer_no, full_name FROM consumers WHERE deleted_at IS NULL AND status = 'active' ORDER BY consumer_no");
?>
<div class="page-header">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h4>Document Management</h4>
            <p>Centralized document repository for all consumers</p>
        </div>
        <div class="d-flex gap-2">
            <?php if (RBAC::can('consumers.edit')): ?>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#uploadModal">
                <i class="fas fa-upload"></i> Upload Document
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<?= display_alert() ?>

<div class="card mb-4">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-auto">
                <select name="document_type" class="form-select form-select-sm" style="min-width:150px;">
                    <option value="">All Types</option>
                    <?php foreach ($documentTypes as $dt): ?>
                        <option value="<?= $dt ?>" <?= $typeFilter === $dt ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $dt)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <input type="text" name="consumer_search" class="form-control form-control-sm" placeholder="Consumer name/no" value="<?= escape($consumerSearch) ?>" style="min-width:180px;">
            </div>
            <div class="col-auto">
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= escape($dateFrom) ?>" placeholder="From">
            </div>
            <div class="col-auto">
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= escape($dateTo) ?>" placeholder="To">
            </div>
            <div class="col-auto">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search documents..." value="<?= escape($search) ?>" style="min-width:160px;">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-filter"></i> Filter</button>
                <a href="<?= ADMIN_URL ?>documents/index.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-times"></i> Clear</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h5>All Documents (<?= number_format($total) ?>)</h5></div>
    <div class="card-body">
        <?php if (empty($documents)): ?>
        <div class="text-center py-5 text-muted">
            <i class="fas fa-file-alt fa-3x mb-3" style="opacity:0.3;"></i>
            <p>No documents found.</p>
        </div>
        <?php else: ?>
        <div class="table-container">
            <table class="table table-hover" id="docTable">
                <thead>
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Document Name</th>
                        <th>Type</th>
                        <th>Consumer</th>
                        <th>Size</th>
                        <th>Uploaded By</th>
                        <th>Date</th>
                        <th style="width:140px">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($documents as $i => $doc): ?>
                    <?php
                        $ext = strtolower(pathinfo($doc['file_path'], PATHINFO_EXTENSION));
                        $isPreviewable = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'pdf']);
                        $icon = $ext === 'pdf' ? 'fa-file-pdf' : (in_array($ext, ['jpg', 'jpeg', 'png', 'gif']) ? 'fa-file-image' : 'fa-file-alt');
                    ?>
                    <tr>
                        <td><?= $offset + $i + 1 ?></td>
                        <td>
                            <i class="fas <?= $icon ?> text-muted me-1"></i>
                            <strong><?= escape($doc['title'] ?: basename($doc['file_path'])) ?></strong>
                            <?php if ($doc['remarks']): ?>
                            <div class="text-muted small"><?= escape($doc['remarks']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge bg-light text-dark"><?= ucfirst(str_replace('_', ' ', $doc['document_type'])) ?></span></td>
                        <td>
                            <a href="<?= ADMIN_URL ?>consumers/view.php?id=<?= $doc['consumer_id'] ?>" class="text-decoration-none">
                                <?= escape($doc['consumer_name']) ?>
                            </a>
                            <div class="small text-muted"><?= escape($doc['consumer_no']) ?></div>
                        </td>
                        <td class="small text-muted"><?= $doc['file_size'] ? format_size($doc['file_size']) : '-' ?></td>
                        <td class="small text-muted"><?= escape($doc['uploaded_by_name'] ?? 'System') ?></td>
                        <td class="small text-muted"><?= format_date($doc['created_at']) ?></td>
                        <td>
                            <?php if ($isPreviewable): ?>
                            <a href="<?= ADMIN_URL ?>documents/preview.php?id=<?= $doc['id'] ?>" class="btn-action view" title="Preview" target="_blank"><i class="fas fa-eye"></i></a>
                            <?php endif; ?>
                            <a href="<?= ADMIN_URL ?>documents/download.php?id=<?= $doc['id'] ?>" class="btn-action" title="Download"><i class="fas fa-download"></i></a>
                            <?php if (RBAC::can('consumers.delete')): ?>
                            <button type="button" class="btn-action delete" title="Delete" onclick="deleteDoc(<?= $doc['id'] ?>)"><i class="fas fa-trash"></i></button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= pagination($total, $page, $perPage, $paginationUrl) ?>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="docUploadForm" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="upload">
                <div class="modal-header">
                    <h5 class="modal-title">Upload Document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group mb-3">
                        <label class="form-label">Consumer <span class="required">*</span></label>
                        <select name="consumer_id" class="form-select" required>
                            <option value="">Select Consumer</option>
                            <?php foreach ($allConsumers as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= escape($c['consumer_no']) ?> - <?= escape($c['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Document Type <span class="required">*</span></label>
                        <select name="document_type" class="form-select" required>
                            <?php foreach ($documentTypes as $dt): ?>
                                <option value="<?= $dt ?>"><?= ucfirst(str_replace('_', ' ', $dt)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">File <span class="required">*</span></label>
                        <input type="file" name="document_file" class="form-control" required
                               accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx"
                               onchange="updateFileName(this)">
                        <div class="form-text">Allowed: JPG, PNG, GIF, PDF, DOC, DOCX. Max 10MB.</div>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Remarks</label>
                        <textarea name="remarks" class="form-control" rows="2" maxlength="500"></textarea>
                    </div>
                    <div id="uploadProgress" style="display:none;">
                        <div class="progress">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" style="width:100%">Uploading...</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="uploadBtn"><i class="fas fa-upload"></i> Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php $extraJs = <<<'JS'
<script>
function updateFileName(input) {
    if (input.files && input.files[0]) {
        // validation
        var ext = input.files[0].name.split('.').pop().toLowerCase();
        var allowed = ['jpg','jpeg','png','gif','pdf','doc','docx'];
        if (allowed.indexOf(ext) === -1) {
            alert('File type not allowed.');
            input.value = '';
        }
        if (input.files[0].size > 10485760) {
            alert('File too large. Max 10MB.');
            input.value = '';
        }
    }
}

function deleteDoc(id) {
    if (!confirm('Delete this document permanently?')) return;
    var fd = new FormData();
    fd.append('action', 'delete');
    fd.append('document_id', id);
    fd.append('csrf_token', '<?= csrf_token() ?>');
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: fd
    }).then(function(r) { return r.json(); }).then(function(d) {
        if (d.success) { location.reload(); }
        else { alert(d.message || 'Delete failed'); }
    }).catch(function() { alert('Network error'); });
}

document.addEventListener('DOMContentLoaded', function() {
    var form = document.getElementById('docUploadForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            var btn = document.getElementById('uploadBtn');
            var progress = document.getElementById('uploadProgress');
            btn.disabled = true;
            progress.style.display = 'block';
            var fd = new FormData(this);
            fetch(window.location.href, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: fd
            }).then(function(r) { return r.json(); }).then(function(d) {
                progress.style.display = 'none';
                btn.disabled = false;
                if (d.success) { location.reload(); }
                else { alert(d.message || 'Upload failed'); }
            }).catch(function() {
                progress.style.display = 'none';
                btn.disabled = false;
                alert('Network error');
            });
        });
    }
});
</script>
JS;
?>

<?php
// Helper: format file size
function format_size($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}

// Helper: secure document upload
function upload_document($file, $targetDir) {
    $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'];
    $allowedMimes = [
        'image/jpeg', 'image/png', 'image/gif',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    if ($file['error'] !== UPLOAD_ERR_OK) return false;

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExts)) return false;

    if ($file['size'] > 10485760) return false; // 10MB

    // Verify mime type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mimeType, $allowedMimes)) {
        // Allow if extension-based check passes but mime check might be unreliable for some files
        error_log("Document upload: unexpected MIME type {$mimeType} for extension {$ext}");
    }

    // Generate secure filename
    $newName = bin2hex(random_bytes(16)) . '.' . $ext;
    $targetPath = rtrim($targetDir, '/') . '/' . $newName;

    if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return [
            'file_name' => $newName,
            'original_name' => $file['name'],
            'file_size' => $file['size'],
            'mime_type' => $mimeType,
        ];
    }
    return false;
}

require_once __DIR__ . '/../includes/footer.php';
?>
