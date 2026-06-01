<?php
// Document Preview - Inline viewer for PDF and images
require_once __DIR__ . '/../../includes/config.php';

Auth::requireAuth();
RBAC::requirePermission('consumers.view');

$docId = (int) get('id', 0);
if (!$docId) {
    die('Document ID required.');
}

$doc = db()->fetchOne(
    "SELECT cd.*, c.full_name as consumer_name, c.consumer_no
     FROM consumer_documents cd
     JOIN consumers c ON cd.consumer_id = c.id
     WHERE cd.id = ? AND cd.deleted_at IS NULL",
    [$docId]
);

if (!$doc) {
    die('Document not found.');
}

$filePath = UPLOADS_PATH . 'documents/' . $doc['file_path'];
if (!file_exists($filePath)) {
    die('File not found on server.');
}

$ext = strtolower(pathinfo($doc['file_path'], PATHINFO_EXTENSION));
$isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif']);
$isPdf = $ext === 'pdf';

if (!$isImage && !$isPdf) {
    // Not previewable - redirect to download
    redirect(ADMIN_URL . 'documents/download.php?id=' . $docId);
}

$pageTitle = 'Document Preview - ' . ($doc['title'] ?: basename($doc['file_path']));
$mimeType = $doc['mime_type'] ?: mime_content_type($filePath);
$fileUrl = ADMIN_URL . 'documents/download.php?id=' . $docId . '&inline=1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape($pageTitle) ?> - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #1a1a2e; color: #fff; font-family: 'Inter', sans-serif; }
        .preview-header {
            background: #16213e;
            padding: 12px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .preview-header .title { font-size: 15px; font-weight: 600; }
        .preview-header .subtitle { font-size: 12px; color: rgba(255,255,255,0.5); }
        .preview-header .actions { display: flex; gap: 8px; }
        .preview-body {
            display: flex;
            align-items: flex-start;
            justify-content: center;
            min-height: calc(100vh - 56px);
            padding: 20px;
        }
        .preview-body img {
            max-width: 100%;
            max-height: calc(100vh - 100px);
            object-fit: contain;
            border-radius: 4px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        .preview-body iframe {
            width: 100%;
            height: calc(100vh - 56px);
            border: none;
        }
        .btn-icon {
            background: rgba(255,255,255,0.1);
            border: none;
            color: #fff;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.2s;
            text-decoration: none;
        }
        .btn-icon:hover { background: rgba(255,255,255,0.2); color: #fff; }
    </style>
</head>
<body>
    <div class="preview-header">
        <div>
            <div class="title"><i class="fas fa-file me-2"></i><?= escape($doc['title'] ?: basename($doc['file_path'])) ?></div>
            <div class="subtitle">
                <?= escape($doc['consumer_no']) ?> &mdash; <?= escape($doc['consumer_name']) ?>
                &nbsp;&middot;&nbsp; <?= strtoupper($ext) ?> &nbsp;&middot;&nbsp; <?= format_size($doc['file_size']) ?>
            </div>
        </div>
        <div class="actions">
            <a href="<?= ADMIN_URL ?>documents/download.php?id=<?= $docId ?>" class="btn-icon" title="Download">
                <i class="fas fa-download"></i>
            </a>
            <a href="<?= ADMIN_URL ?>documents/index.php" class="btn-icon" title="Back to Documents">
                <i class="fas fa-th-large"></i>
            </a>
            <button class="btn-icon" onclick="window.close()" title="Close">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    <div class="preview-body">
        <?php if ($isImage): ?>
            <img src="<?= escape($fileUrl) ?>" alt="<?= escape($doc['title'] ?: 'Document') ?>">
        <?php elseif ($isPdf): ?>
            <iframe src="<?= escape($fileUrl) ?>" title="PDF Viewer"></iframe>
        <?php endif; ?>
    </div>
</body>
</html>

<?php
// Helper: format file size
function format_size($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}
?>
