<?php
require_once __DIR__ . '/../../includes/config.php';
$pageTitle = 'Activity Logs';
$breadcrumbs = [
    ['label' => 'User Management', 'url' => ADMIN_URL . 'users/index.php'],
    ['label' => 'Activity Logs']
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
    $where .= " AND (al.description LIKE :search OR u.name LIKE :search2)";
    $params['search'] = "%{$search}%";
    $params['search2'] = "%{$search}%";
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

$total = db()->fetchColumn(
    "SELECT COUNT(*) FROM activity_logs al {$where}", $params
);

$logs = db()->fetchAll(
    "SELECT al.*, u.name as user_name, u.email as user_email
     FROM activity_logs al
     LEFT JOIN users u ON al.user_id = u.id
     {$where}
     ORDER BY al.created_at DESC
     LIMIT {$perPage} OFFSET {$offset}",
    $params
);

$actions = db()->fetchAll("SELECT DISTINCT action FROM activity_logs ORDER BY action");
$modules = db()->fetchAll("SELECT DISTINCT module FROM activity_logs ORDER BY module");
$users = db()->fetchAll("SELECT DISTINCT u.id, u.name FROM activity_logs al JOIN users u ON al.user_id = u.id WHERE u.deleted_at IS NULL ORDER BY u.name");

$totalPages = ceil($total / $perPage);
$paginationUrl = ADMIN_URL . "users/activity-logs.php?page={page}" .
    ($search ? "&search=" . urlencode($search) : "") .
    ($actionFilter ? "&action={$actionFilter}" : "") .
    ($moduleFilter ? "&module={$moduleFilter}" : "") .
    ($userId ? "&user_id={$userId}" : "") .
    ($dateFrom ? "&date_from={$dateFrom}" : "") .
    ($dateTo ? "&date_to={$dateTo}" : "");
?>
<div class="page-header">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h4>Activity Logs</h4>
            <p>Track all user activities across the system</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= ADMIN_URL ?>users/audit-logs.php" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-shield-alt"></i> Audit Logs
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
                <input type="text" name="search" class="form-control" placeholder="Search activities..." value="<?= escape($search) ?>">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                <?php if ($search || $actionFilter || $moduleFilter || $userId || $dateFrom || $dateTo): ?>
                <a href="<?= ADMIN_URL ?>users/activity-logs.php" class="btn btn-outline-secondary"><i class="fas fa-times"></i></a>
                <?php endif; ?>
            </div>
            <select name="action" class="form-select" onchange="this.form.submit()">
                <option value="">All Actions</option>
                <?php foreach ($actions as $a): ?>
                <option value="<?= escape($a['action']) ?>" <?= $actionFilter === $a['action'] ? 'selected' : '' ?>><?= escape(ucfirst($a['action'])) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="module" class="form-select" onchange="this.form.submit()">
                <option value="">All Modules</option>
                <?php foreach ($modules as $m): ?>
                <option value="<?= escape($m['module']) ?>" <?= $moduleFilter === $m['module'] ? 'selected' : '' ?>><?= escape(ucfirst($m['module'])) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="user_id" class="form-select" onchange="this.form.submit()">
                <option value="">All Users</option>
                <?php foreach ($users as $u): ?>
                <option value="<?= $u['id'] ?>" <?= $userId == $u['id'] ? 'selected' : '' ?>><?= escape($u['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="date_from" class="form-control" value="<?= escape($dateFrom) ?>" onchange="this.form.submit()" style="min-width:140px;">
            <input type="date" name="date_to" class="form-control" value="<?= escape($dateTo) ?>" onchange="this.form.submit()" style="min-width:140px;">
        </form>

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Timestamp</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Module</th>
                        <th>Description</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">No activity logs found</td>
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
                            <div style="font-weight:500;font-size:13px;"><?= escape($log['user_name']) ?></div>
                            <small class="text-muted"><?= escape($log['user_email']) ?></small>
                            <?php else: ?>
                            <span class="text-muted">System</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge bg-primary"><?= escape(ucfirst($log['action'])) ?></span></td>
                        <td><span class="badge bg-secondary"><?= escape(ucfirst($log['module'])) ?></span></td>
                        <td style="max-width:300px;">
                            <span style="font-size:13px;"><?= escape($log['description'] ?: '-') ?></span>
                        </td>
                        <td style="font-size:12px;font-family:monospace;"><?= escape($log['ip_address'] ?: '-') ?></td>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
