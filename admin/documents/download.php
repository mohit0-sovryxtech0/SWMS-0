<?php
// Secure Document Download with access control and logging
require_once __DIR__ . '/../../includes/config.php';

Auth::requireAuth();
RBAC::requirePermission('consumers.view');

$docId = (int) get('id', 0);
$isInline = (int) get('inline', 0);

if (!$docId) {
    http_response_code(400);
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
    http_response_code(404);
    die('Document not found.');
}

$filePath = UPLOADS_PATH . 'documents/' . $doc['file_path'];

if (!file_exists($filePath)) {
    http_response_code(404);
    log_activity(Auth::id(), 'download_failed', 'documents',
        "File not found on server for document ID {$docId}",
        ['document_id' => $docId, 'reason' => 'file_missing']
    );
    die('File not found on server.');
}

$ext = strtolower(pathinfo($doc['file_path'], PATHINFO_EXTENSION));
$mimeType = $doc['mime_type'] ?: mime_content_type($filePath) ?: 'application/octet-stream';

$allowedImages = ['jpg', 'jpeg', 'png', 'gif'];
$imageMimes = ['image/jpeg', 'image/png', 'image/gif'];

// Log download
log_activity(Auth::id(), 'download', 'documents',
    "Downloaded document: {$doc['title']} for consumer {$doc['consumer_no']}",
    ['document_id' => $docId, 'consumer_id' => $doc['consumer_id'], 'inline' => $isInline]
);

// Clear output buffering
while (ob_get_level()) ob_end_clean();

// Set headers
if ($isInline && in_array($ext, $allowedImages)) {
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: inline; filename="' . basename($doc['title'] ?: $doc['file_path']) . '"');
    header('Cache-Control: public, max-age=3600');
    header('X-Content-Type-Options: nosniff');
    readfile($filePath);
    exit;
}

if ($isInline && $ext === 'pdf') {
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . basename($doc['title'] ?: $doc['file_path']) . '"');
    header('Cache-Control: public, max-age=3600');
    header('X-Content-Type-Options: nosniff');
    readfile($filePath);
    exit;
}

// Force download for all other cases
$downloadName = $doc['title'] ?: 'document_' . $docId . '.' . $ext;
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

readfile($filePath);
exit;
?>
