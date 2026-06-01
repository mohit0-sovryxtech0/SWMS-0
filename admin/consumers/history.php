<?php
require_once __DIR__ . '/../../includes/config.php';
$pageTitle = 'Consumer Audit History';
$breadcrumbs = [
    ['label' => 'Consumer Management', 'url' => ADMIN_URL . 'consumers/index.php'],
    ['label' => 'Audit History']
];
RBAC::requirePermission('consumers.view');

require_once __DIR__ . '/../includes/header.php';

$consumerId = (int) get('id', 0);
$consumer = db()->fetchOne("SELECT id, consumer_no, full_name FROM consumers WHERE id = ? AND deleted_at IS NULL", [$consumerId]);

if (!$consumer) {
    alert_error('Consumer not found.');
    redirect(ADMIN_URL . 'consumers/index.php');
}

$page = max(1, (int) get('page', 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

$total = db()->fetchColumn(
    "SELECT COUNT(*) FROM consumer_history WHERE consumer_id = ?",
    [$consumerId]
);

$history = db()->fetchAll(
    "SELECT h.*, u.name as changed_by_name
     FROM consumer_history h
     LEFT JOIN users u ON h.changed_by = u.id
     WHERE h.consumer_id = ?
     ORDER BY h.changed_at DESC
     LIMIT {$perPage} OFFSET {$offset}",
    [$consumerId]
);

$totalPages = ceil($total / $perPage);
$paginationUrl = ADMIN_URL . "consumers/history.php?id={$consumerId}&page={page}";
?>
<div class="page-header">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h4>Audit History</h4>
            <p><?= escape($consumer['consumer_no']) ?> &mdash; <?= escape($consumer['full_name']) ?></p>
        </div>
        <a href="<?= ADMIN_URL ?>consumers/view.php?id=<?= $consumerId ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Back to Profile
        </a>
    </div>
</div>

<?= display_alert() ?>

<div class="card">
    <div class="card-body">
        <?php if (empty($history)): ?>
        <div class="text-center py-5 text-muted">
            <i class="fas fa-history fa-3x mb-3" style="opacity:0.3;"></i>
            <p>No history records found for this consumer.</p>
        </div>
        <?php else: ?>
        <div class="timeline" style="position:relative;padding-left:30px;">
            <div style="position:absolute;left:12px;top:0;bottom:0;width:2px;background:var(--border-color);"></div>
            <?php foreach ($history as $h): ?>
            <?php
            $actionColors = [
                'created' => 'success',
                'updated' => 'primary',
                'deleted' => 'danger',
                'transfer_requested' => 'warning',
                'transfer_approved' => 'success',
                'transfer_rejected' => 'danger',
                'document_uploaded' => 'info',
                'status_changed' => 'secondary',
            ];
            $actionIcons = [
                'created' => 'fa-plus-circle',
                'updated' => 'fa-edit',
                'deleted' => 'fa-trash',
                'transfer_requested' => 'fa-exchange-alt',
                'transfer_approved' => 'fa-check-circle',
                'transfer_rejected' => 'fa-times-circle',
                'document_uploaded' => 'fa-file-upload',
                'status_changed' => 'fa-toggle-on',
            ];
            $color = $actionColors[$h['action']] ?? 'secondary';
            $icon = $actionIcons[$h['action']] ?? 'fa-history';
            ?>
            <div class="timeline-item" style="position:relative;padding-bottom:20px;">
                <div style="position:absolute;left:-22px;top:4px;width:20px;height:20px;border-radius:50%;background:var(--<?= $color ?>);color:#fff;display:flex;align-items:center;justify-content:center;font-size:10px;z-index:1;">
                    <i class="fas <?= $icon ?>"></i>
                </div>
                <div style="background:#f8f9fa;border-radius:8px;padding:12px 16px;border-left:3px solid var(--<?= $color ?>);">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <strong style="text-transform:capitalize;font-size:13px;"><?= escape(ucfirst(str_replace('_', ' ', $h['action']))) ?></strong>
                        <span style="font-size:11px;color:var(--text-muted);"><?= format_datetime($h['changed_at']) ?></span>
                    </div>
                    <div style="font-size:12px;color:var(--text-muted);">
                        By <?= escape($h['changed_by_name'] ?: 'System') ?>
                    </div>
                    <?php if ($h['old_value'] || $h['new_value']): ?>
                    <div class="mt-2" style="font-size:11px;">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleDiff(this, <?= $h['id'] ?>)">
                            <i class="fas fa-code"></i> View Data
                        </button>
                        <pre id="diff-<?= $h['id'] ?>" style="display:none;background:#1a1c2e;color:#e8e8e8;padding:12px;border-radius:6px;margin-top:8px;font-size:11px;max-height:200px;overflow:auto;" class="mt-2"><code><?php
                            $output = '';
                            if ($h['old_value']) {
                                $output .= "--- OLD ---\n" . json_encode(json_decode($h['old_value'], true), JSON_PRETTY_PRINT) . "\n\n";
                            }
                            if ($h['new_value']) {
                                $output .= "+++ NEW +++\n" . json_encode(json_decode($h['new_value'], true), JSON_PRETTY_PRINT);
                            }
                            echo escape($output);
                        ?></code></pre>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="d-flex justify-content-between align-items-center mt-3">
            <small class="text-muted">Page <?= $page ?> of <?= $totalPages ?> (<?= $total ?> records)</small>
            <nav>
                <ul class="pagination mb-0">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= str_replace('{page}', $page - 1, $paginationUrl) ?>"><i class="fas fa-chevron-left"></i></a>
                    </li>
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="<?= str_replace('{page}', $i, $paginationUrl) ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= str_replace('{page}', $page + 1, $paginationUrl) ?>"><i class="fas fa-chevron-right"></i></a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleDiff(btn, id) {
    var pre = document.getElementById('diff-' + id);
    if (pre.style.display === 'none') {
        pre.style.display = 'block';
        btn.innerHTML = '<i class="fas fa-code"></i> Hide Data';
    } else {
        pre.style.display = 'none';
        btn.innerHTML = '<i class="fas fa-code"></i> View Data';
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
