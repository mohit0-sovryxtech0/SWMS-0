<?php
require_once __DIR__ . '/../../includes/config.php';
$pageTitle = 'Audit Logs';
$breadcrumbs = [
    ['label' => 'User Management', 'url' => ADMIN_URL . 'users/index.php'],
    ['label' => 'Audit Logs']
];
require_once __DIR__ . '/../includes/header.php';

RBAC::requirePermission('audit.view');

$page = max(1, (int) get('page', 1));
$perPage = RECORDS_PER_PAGE;
$offset = ($page - 1) * $perPage;

$search = trim(get('search', ''));
$actionFilter = get('action', '');
$moduleFilter = get('module', '');
$userId = (int) get('user_id', 0);
$dateFrom = get('date_from', '');
$dateTo = get('date_to', '');

$where = "WHERE 1=1";
$params = [];

if ($search !== '') {
    $where .= " AND (al.description LIKE :search OR u.name LIKE :search2 OR u.email LIKE :search3)";
    $params['search'] = "%{$search}%";
    $params['search2'] = "%{$search}%";
    $params['search3'] = "%{$search}%";
}
if ($actionFilter !== '') {
    $where .= " AND al.action = :action";
    $params['action'] = $actionFilter;
}
if ($moduleFilter !== '') {
    $where .= " AND al.module = :module";
    $params['module'] = $moduleFilter;
}
if ($userId > 0) {
    $where .= " AND al.user_id = :user_id";
    $params['user_id'] = $userId;
}
if ($dateFrom !== '') {
    $where .= " AND al.created_at >= :date_from";
    $params['date_from'] = $dateFrom . ' 00:00:00';
}
if ($dateTo !== '') {
    $where .= " AND al.created_at <= :date_to";
    $params['date_to'] = $dateTo . ' 23:59:59';
}

// For audit we use security_logs which has more sensitive data tracking
$total = db()->fetchColumn(
    "SELECT COUNT(*) FROM security_logs al {$where}", $params
);

$logs = db()->fetchAll(
    "SELECT al.*, u.name as user_name, u.email as user_email
     FROM security_logs al
     LEFT JOIN users u ON al.user_id = u.id
     {$where}
     ORDER BY al.created_at DESC
     LIMIT {$perPage} OFFSET {$offset}",
    $params
);

$actions = db()->fetchAll("SELECT DISTINCT event FROM security_logs ORDER BY event");
$modules = []; // security_logs doesn't have module, we use the event field

$totalPages = ceil($total / $perPage);
$paginationUrl = ADMIN_URL . "users/audit-logs.php?page={page}" .
    ($search ? "&search=" . urlencode($search) : "") .
    ($actionFilter ? "&action={$actionFilter}" : "") .
    ($dateFrom ? "&date_from={$dateFrom}" : "") .
    ($dateTo ? "&date_to={$dateTo}" : "");

// Get event types for dropdown
$eventTypes = db()->fetchAll("SELECT DISTINCT event FROM security_logs ORDER BY event");
?>
<div class="page-header">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h4>Audit Logs</h4>
            <p>Security and audit trail of system activities</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= ADMIN_URL ?>users/activity-logs.php" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-list"></i> Activity Logs
            </a>
            <button class="btn btn-outline-secondary btn-sm" onclick="window.print()">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>
</div>

<?= display_alert() ?>

<div class="card">
    <div class="card-body">
        <form method="GET" action="" class="filter-bar">
            <div class="search-box">
                <input type="text" name="search" class="form-control" placeholder="Search logs..." value="<?= escape($search) ?>">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                <?php if ($search || $actionFilter || $dateFrom || $dateTo): ?>
                <a href="<?= ADMIN_URL ?>users/audit-logs.php" class="btn btn-outline-secondary"><i class="fas fa-times"></i></a>
                <?php endif; ?>
            </div>
            <select name="action" class="form-select" onchange="this.form.submit()">
                <option value="">All Events</option>
                <?php foreach ($eventTypes as $ev): ?>
                <option value="<?= escape($ev['event']) ?>" <?= $actionFilter === $ev['event'] ? 'selected' : '' ?>><?= escape(ucfirst(str_replace('_', ' ', $ev['event']))) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="date_from" class="form-control" value="<?= escape($dateFrom) ?>" placeholder="From" onchange="this.form.submit()" style="min-width:140px;">
            <input type="date" name="date_to" class="form-control" value="<?= escape($dateTo) ?>" placeholder="To" onchange="this.form.submit()" style="min-width:140px;">
        </form>

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Timestamp</th>
                        <th>Event</th>
                        <th>User</th>
                        <th>IP Address</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">No audit logs found</td>
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
                            <span class="badge bg-<?= in_array($log['event'], ['failed_login', 'user_deleted', 'password_changed']) ? 'warning' : 'info' ?>">
                                <?= escape(ucfirst(str_replace('_', ' ', $log['event']))) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($log['user_name']): ?>
                            <div><?= escape($log['user_name']) ?></div>
                            <small class="text-muted"><?= escape($log['user_email']) ?></small>
                            <?php else: ?>
                            <span class="text-muted">System / Guest</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:12px;font-family:monospace;"><?= escape($log['ip_address'] ?: '-') ?></td>
                        <td style="max-width:300px;">
                            <?php if ($log['details']): ?>
                            <button type="button" class="btn btn-sm btn-outline-info" onclick="viewDetails('<?= escape(addslashes($log['details'])) ?>')">
                                <i class="fas fa-eye"></i> View
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

        <?php if ($totalPages > 1): ?>
        <div class="d-flex justify-content-between align-items-center mt-3">
            <small class="text-muted">Showing <?= min($total, $offset + 1) ?> to <?= min($total, $offset + $perPage) ?> of <?= $total ?> entries</small>
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
    </div>
</div>

<!-- Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Event Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <pre id="detailsContent" style="background:var(--bg-color);padding:16px;border-radius:8px;font-size:12px;max-height:400px;overflow-y:auto;margin:0;white-space:pre-wrap;word-break:break-all;"></pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function viewDetails(json) {
    try {
        var parsed = JSON.parse(json);
        document.getElementById('detailsContent').textContent = JSON.stringify(parsed, null, 2);
    } catch(e) {
        document.getElementById('detailsContent').textContent = json;
    }
    new bootstrap.Modal(document.getElementById('detailsModal')).show();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
