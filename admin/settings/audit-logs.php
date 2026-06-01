<?php
require_once __DIR__ . '/../../includes/config.php';
$pageTitle = 'Audit Logs';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => ADMIN_URL . 'dashboard/index.php'],
    ['label' => 'Settings', 'url' => ADMIN_URL . 'settings/index.php'],
    ['label' => 'Audit Logs']
];
RBAC::requirePermission('audit.view');
require_once __DIR__ . '/../includes/header.php';

$page = max(1, (int) get('page', 1));
$perPage = RECORDS_PER_PAGE;
$offset = ($page - 1) * $perPage;

$search = trim(get('search', ''));
$moduleFilter = get('module', '');
$actionFilter = get('action', '');
$userId = (int) get('user_id', 0);
$dateFrom = get('date_from', '');
$dateTo = get('date_to', '');

$where = "WHERE 1=1";
$params = [];

if ($search !== '') {
    $where .= " AND (at.description LIKE :search OR u.name LIKE :search2 OR u.email LIKE :search3 OR at.reference_type LIKE :search4)";
    $params['search'] = "%{$search}%";
    $params['search2'] = "%{$search}%";
    $params['search3'] = "%{$search}%";
    $params['search4'] = "%{$search}%";
}
if ($moduleFilter !== '') {
    $where .= " AND at.module = :module";
    $params['module'] = $moduleFilter;
}
if ($actionFilter !== '') {
    $where .= " AND at.action = :action";
    $params['action'] = $actionFilter;
}
if ($userId > 0) {
    $where .= " AND at.user_id = :user_id";
    $params['user_id'] = $userId;
}
if ($dateFrom !== '') {
    $where .= " AND at.created_at >= :date_from";
    $params['date_from'] = $dateFrom . ' 00:00:00';
}
if ($dateTo !== '') {
    $where .= " AND at.created_at <= :date_to";
    $params['date_to'] = $dateTo . ' 23:59:59';
}

$total = db()->fetchColumn(
    "SELECT COUNT(*) FROM audit_trail at LEFT JOIN users u ON at.user_id = u.id {$where}", $params
);

$logs = db()->fetchAll(
    "SELECT at.*, u.name AS user_name, u.email AS user_email, u.avatar AS user_avatar
     FROM audit_trail at
     LEFT JOIN users u ON at.user_id = u.id
     {$where}
     ORDER BY at.created_at DESC
     LIMIT {$perPage} OFFSET {$offset}",
    $params
);

$totalPages = ceil($total / $perPage);

$modules = db()->fetchAll("SELECT DISTINCT module FROM audit_trail ORDER BY module");
$actions = db()->fetchAll("SELECT DISTINCT action FROM audit_trail ORDER BY action");
$users = db()->fetchAll("SELECT DISTINCT u.id, u.name FROM audit_trail at JOIN users u ON at.user_id = u.id ORDER BY u.name");

$paginationUrl = ADMIN_URL . "settings/audit-logs.php?page={page}" .
    ($search ? "&search=" . urlencode($search) : "") .
    ($moduleFilter ? "&module={$moduleFilter}" : "") .
    ($actionFilter ? "&action={$actionFilter}" : "") .
    ($userId ? "&user_id={$userId}" : "") .
    ($dateFrom ? "&date_from={$dateFrom}" : "") .
    ($dateTo ? "&date_to={$dateTo}" : "");

$extraCss = '<style>
.audit-detail-toggle { cursor: pointer; }
.audit-detail-toggle:hover { background: var(--bg-color); }
pre.audit-json { background: #f8f9fa; padding: 12px; border-radius: 6px; font-size: 11px; max-height: 300px; overflow-y: auto; white-space: pre-wrap; word-break: break-all; margin: 0; }
</style>';

$extraJs = <<<JS
<script>
$(document).ready(function() {
    $('.btn-view-details').on('click', function() {
        var oldVal = $(this).data('old');
        var newVal = $(this).data('new');
        var desc = $(this).data('desc');

        var html = '';
        if (desc) html += '<p>' + escapeHtml(desc) + '</p>';
        if (oldVal) {
            html += '<h6 class="text-danger mb-1">Old Value</h6><pre class="audit-json mb-3">' + formatJson(oldVal) + '</pre>';
        }
        if (newVal) {
            html += '<h6 class="text-success mb-1">New Value</h6><pre class="audit-json">' + formatJson(newVal) + '</pre>';
        }
        if (!html) html = '<p class="text-muted">No detailed data available</p>';

        $('#auditDetailContent').html(html);
        new bootstrap.Modal(document.getElementById('auditDetailModal')).show();
    });
});

function formatJson(str) {
    try {
        var parsed = JSON.parse(str);
        return JSON.stringify(parsed, null, 2);
    } catch(e) {
        return escapeHtml(str);
    }
}

function escapeHtml(str) {
    var div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
</script>
JS;
?>
<div class="page-header">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h4>Audit Trail</h4>
            <p>Comprehensive audit log of all system changes and activities</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-primary btn-sm" onclick="window.print()">
                <i class="fas fa-print me-1"></i> Print
            </button>
            <a href="<?= ADMIN_URL ?>settings/index.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i> Back
            </a>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <!-- Filters -->
        <form method="GET" action="" class="mb-3">
            <div class="row g-2">
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search description, user..." value="<?= escape($search) ?>">
                </div>
                <div class="col-md-2">
                    <select name="module" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Modules</option>
                        <?php foreach ($modules as $m): ?>
                        <option value="<?= escape($m['module']) ?>" <?= $moduleFilter === $m['module'] ? 'selected' : '' ?>><?= escape($m['module']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="action" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Actions</option>
                        <?php foreach ($actions as $a): ?>
                        <option value="<?= escape($a['action']) ?>" <?= $actionFilter === $a['action'] ? 'selected' : '' ?>><?= escape(ucfirst($a['action'])) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="user_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All Users</option>
                        <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= $userId === (int)$u['id'] ? 'selected' : '' ?>><?= escape($u['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-1">
                    <input type="date" name="date_from" class="form-control form-control-sm" value="<?= escape($dateFrom) ?>" onchange="this.form.submit()" title="From date">
                </div>
                <div class="col-md-1">
                    <input type="date" name="date_to" class="form-control form-control-sm" value="<?= escape($dateTo) ?>" onchange="this.form.submit()" title="To date">
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fas fa-search"></i></button>
                </div>
            </div>
            <?php if ($search || $moduleFilter || $actionFilter || $userId || $dateFrom || $dateTo): ?>
            <div class="mt-2">
                <a href="<?= ADMIN_URL ?>settings/audit-logs.php" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-times me-1"></i> Clear Filters
                </a>
            </div>
            <?php endif; ?>
        </form>

        <!-- Table -->
        <div class="table-container">
            <table class="table table-hover table-bordered" id="auditTable">
                <thead class="table-light">
                    <tr>
                        <th style="width:40px">#</th>
                        <th style="width:160px">Timestamp</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Module</th>
                        <th>Reference</th>
                        <th style="width:80px">Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                            No audit logs found
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($logs as $i => $log): ?>
                    <tr>
                        <td><?= $offset + $i + 1 ?></td>
                        <td style="white-space:nowrap;font-size:12px;">
                            <div><?= format_datetime($log['created_at']) ?></div>
                            <small class="text-muted"><?= time_ago($log['created_at']) ?></small>
                        </td>
                        <td>
                            <?php if ($log['user_name']): ?>
                            <div class="d-flex align-items-center gap-2">
                                <div class="user-avatar-sm" style="width:28px;height:28px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:600;">
                                    <?= strtoupper(substr($log['user_name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <div class="fw-semibold" style="font-size:13px;"><?= escape($log['user_name']) ?></div>
                                    <small class="text-muted"><?= escape($log['user_email'] ?? '') ?></small>
                                </div>
                            </div>
                            <?php else: ?>
                            <span class="text-muted">
                                <i class="fas fa-robot me-1"></i> System / Deleted User
                            </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $actionClasses = [
                                'create' => 'success',
                                'update' => 'info',
                                'delete' => 'danger',
                                'login' => 'primary',
                                'logout' => 'secondary',
                                'export' => 'warning',
                                'restore' => 'success',
                                'cancel' => 'danger',
                            ];
                            $badgeClass = $actionClasses[$log['action']] ?? 'primary';
                            ?>
                            <span class="badge bg-<?= $badgeClass ?>">
                                <?= escape(ucfirst($log['action'])) ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-dark"><?= escape($log['module']) ?></span>
                        </td>
                        <td style="font-size:12px;">
                            <?php if ($log['reference_type']): ?>
                            <span class="text-muted"><?= escape($log['reference_type']) ?></span>
                            <?php if ($log['reference_id']): ?>
                            <code>#<?= $log['reference_id'] ?></code>
                            <?php endif; ?>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                            <?php if ($log['description']): ?>
                            <div class="text-muted small mt-1"><?= escape(truncate($log['description'], 80)) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($log['old_value'] || $log['new_value']): ?>
                            <button type="button" class="btn btn-sm btn-outline-info btn-view-details"
                                    data-old="<?= escape($log['old_value'] ?? '') ?>"
                                    data-new="<?= escape($log['new_value'] ?? '') ?>"
                                    data-desc="<?= escape($log['description'] ?? '') ?>"
                                    title="View Changes">
                                <i class="fas fa-code-branch"></i>
                            </button>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="d-flex justify-content-between align-items-center mt-3">
            <small class="text-muted">
                Showing <?= min($total, $offset + 1) ?> to <?= min($total, $offset + $perPage) ?> of <?= number_format($total) ?> entries
            </small>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $page > 1 ? str_replace('{page}', $page - 1, $paginationUrl) : '#' ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    for ($i = $startPage; $i <= $endPage; $i++):
                    ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="<?= str_replace('{page}', $i, $paginationUrl) ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $page < $totalPages ? str_replace('{page}', $page + 1, $paginationUrl) : '#' ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Detail Modal -->
<div class="modal fade" id="auditDetailModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-code-branch me-2 text-primary"></i> Audit Change Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="auditDetailContent">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php include_once ADMIN_PATH . 'includes/footer.php'; ?>
